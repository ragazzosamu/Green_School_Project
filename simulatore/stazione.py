"""
Stazione + Punto di ricarica.

Una `Stazione` è il dispositivo fisico (id_stazione) con uno o più punti
collegati. Si occupa solo dell'heartbeat HTTP unico per tutti i suoi punti.

Un `Punto_ricarica`:
  - gestisce lo stato fisico (cavo connesso, libero/occupato);
  - avvia/termina una sessione di ricarica;
  - ogni 5s spinge i delta kWh su Redis (NON sul DB, NON via Laravel);
  - resta in ascolto sul WebSocket-server Node: se arriva un comando di stop
    dal browser dell'utente, chiude la sessione;
  - si auto-stoppa se l'utente scollega il cavo durante la ricarica.

Flusso autenticazione QR:
  - scansione QR → il Punto entra in `ATTESA_CAVO` per TIMEOUT_AUTH secondi;
  - se entro la finestra l'utente collega il cavo → parte la ricarica;
  - se il cavo è già collegato al momento del QR → parte subito;
  - se il timer scade → autenticazione annullata.
"""
import threading
import time
from enum import Enum

import config
from sessione import Sessione
from api_client import Apiclass
from ws_client import WSClient
from redis_client import RedisClient


class Stato(Enum):
    OFFLINE     = 0
    OCCUPATA    = 1
    LIBERA      = 2
    GUASTO      = 3
    ATTESA_CAVO = 4  # QR scansionato, in attesa che l'utente colleghi il cavo


# ===========================================================================
#  PUNTO DI RICARICA
# ===========================================================================

class Punto_ricarica:
    def __init__(self, id_punto):
        self.id_punto = id_punto
        self.stato = Stato.LIBERA
        self.cavo_connesso = False
        self.sessione_attuale: Sessione | None = None
        self.delta_kwh_accumulato = 0.0

        self.api   = Apiclass()
        self.redis = RedisClient()
        self.ws    = WSClient(
            id_punto=self.id_punto,
            on_stop_richiesto=self._on_stop_remoto,
            on_sessione_pendente=self.autentica_qr,
        )

        self._lock = threading.Lock()

        # Auth in attesa di cavo (id_sessione che Laravel ha creato)
        self._pending_id_sessione: str | None = None
        self._pending_scadenza: float | None  = None
        self._pending_timer: threading.Timer | None = None

        # WS sempre attivo: la colonnina deve poter ricevere comandi anche da ferma
        self.ws.connetti(timeout=5)

    # ============================================================ logging =====
    def log(self, tag, messaggio):
        timestamp = time.strftime("%H:%M:%S")
        print(f"[{timestamp}] [{self.id_punto[:8]}] [{tag}] {messaggio}")

    # ============================================================ stato cavo ==
    def collega_cavo(self):
        pending_id_sessione = None
        with self._lock:
            if self.cavo_connesso:
                self.log("CAVO", "Cavo già collegato.")
                return
            self.cavo_connesso = True
            self.log("CAVO", "Cavo collegato fisicamente.")

            if self._pending_id_sessione is not None:
                pending_id_sessione = self._pending_id_sessione
                self._cancella_pending()

        if pending_id_sessione is not None:
            self.log("AUTH", f"Cavo collegato in finestra di auth → avvio sessione {pending_id_sessione}.")
            self.avvio_sessione(pending_id_sessione, corrente_max=32, voltaggio=230, soc_iniziale=0.2)

    def scollega_cavo(self):
        with self._lock:
            if not self.cavo_connesso:
                self.log("CAVO", "Cavo già scollegato.")
                return
            self.cavo_connesso = False
            self.log("CAVO", "Cavo scollegato fisicamente.")
            sessione_da_chiudere = self.sessione_attuale is not None

        if sessione_da_chiudere:
            self.log("SESSIONE", "Cavo scollegato durante ricarica → termino la sessione.")
            # Solo in questo caso (sessione attiva + cavo staccato fisicamente)
            # è la colonnina a dover notificare il backend per chiudere la
            # sessione su DB. In tutti gli altri scenari la chiusura su DB la
            # fa già chi ha richiesto lo stop (es. browser via Laravel).
            self.termina_sessione(notifica_backend=True)

    # ============================================================ autentic. ==
    def autentica_qr(self, id_sessione: str) -> bool:
        """Laravel ci notifica via WS che un utente ha scansionato il QR per
        questo punto: apriamo una finestra di TIMEOUT_AUTH secondi in cui
        aspettiamo che il cavo venga collegato. Se è già collegato, partiamo
        subito."""
        with self._lock:
            if self.sessione_attuale is not None:
                self.log("AUTH", "Sessione già in corso, ignoro QR.")
                return False
            if self._pending_id_sessione is not None:
                self.log("AUTH", "C'è già un'autenticazione in attesa.")
                return False

            cavo_subito = self.cavo_connesso
            if not cavo_subito:
                self._pending_id_sessione = id_sessione
                self._pending_scadenza    = time.time() + config.TIMEOUT_AUTH
                self._pending_timer       = threading.Timer(config.TIMEOUT_AUTH, self._scadenza_auth, args=(id_sessione,))
                self._pending_timer.daemon = True
                self._pending_timer.start()
                self.stato = Stato.ATTESA_CAVO

        if cavo_subito:
            self.log("AUTH", f"Cavo già collegato → avvio immediato sessione {id_sessione}.")
            self.avvio_sessione(id_sessione, corrente_max=32, voltaggio=230, soc_iniziale=0.2)
        else:
            self.log("AUTH", f"QR accettato (sess {id_sessione}). Hai {config.TIMEOUT_AUTH}s per collegare il cavo.")
        return True

    def _cancella_pending(self):
        """Chiamare DA DENTRO il lock."""
        if self._pending_timer is not None:
            self._pending_timer.cancel()
        self._pending_timer = None
        self._pending_id_sessione = None
        self._pending_scadenza = None
        if self.stato == Stato.ATTESA_CAVO:
            self.stato = Stato.LIBERA

    def _scadenza_auth(self, id_sessione):
        with self._lock:
            if self._pending_id_sessione != id_sessione:
                return
            self._cancella_pending()
        self.log("AUTH", f"Timeout {config.TIMEOUT_AUTH}s scaduto per sess {id_sessione}. Autenticazione annullata.")

    def secondi_rimasti_auth(self) -> int | None:
        if self._pending_scadenza is None:
            return None
        return max(0, int(self._pending_scadenza - time.time()))

    # ============================================================ sessione ===
    def avvio_sessione(self, id_sessione, corrente_max, voltaggio, soc_iniziale):
        with self._lock:
            self.stato = Stato.OCCUPATA
            self.sessione_attuale = Sessione(
                id_punto=self.id_punto,
                id_sessione=id_sessione,
            )
            self.delta_kwh_accumulato = 0.0

        self.redis.avvio_sessione(self.id_punto)
        self.log("SESSIONE", f"Avvio ricarica sessione {id_sessione}")

        threading.Thread(target=self._loop_kwh,   args=(corrente_max, voltaggio, soc_iniziale), daemon=True).start()
        threading.Thread(target=self._loop_redis, daemon=True).start()

    def termina_sessione(self, notifica_backend: bool = False):
        """Chiusura sessione: marca la fine, ferma i loop, opzionalmente
        notifica Laravel. Idempotente: chiamarla più volte è sicuro.

        :param notifica_backend: True solo quando la causa della chiusura è
            "cavo staccato fisicamente durante la ricarica". In tutti gli
            altri casi (stop remoto da browser, batteria piena, chiusura
            manuale da console) il DB viene già aggiornato da chi ha
            innescato lo stop, quindi NON dobbiamo richiamare l'IoT API.
        """
        with self._lock:
            sessione = self.sessione_attuale
            if sessione is None or sessione.ora_fine is not None:
                return
            sessione.ora_fine = time.time()
            self.stato = Stato.LIBERA

        self.log("SESSIONE", f"Terminata. Totale kWh erogati: {sessione.quantita_kwh:.4f}")

        if notifica_backend:
            try:
                self.api.api_sessione_finita(sessione)
            except Exception as e:
                self.log("API", f"Errore notifica fine sessione: {e}")

        self.redis.fine_sessione(self.id_punto)
        self.sessione_attuale = None

    def _on_stop_remoto(self):
        """Callback chiamata dal WSClient quando il backend richiede lo stop."""
        if self.sessione_attuale is None:
            self.log("WS", "STOP remoto ricevuto ma nessuna sessione attiva.")
            return
        self.termina_sessione()

    # ============================================================ logica fis ==
    def calcola_output_ricarica(self, v_input, i_input, soc_attuale):
        if soc_attuale > 0.8:
            fattore_riduzione = (1.0 - soc_attuale) / 0.2
            i_reale = max(i_input * fattore_riduzione, 1.0)
        else:
            i_reale = i_input
        potenza_kw = (v_input * i_reale) / 1000
        return i_reale, potenza_kw

    # ============================================================ loop kWh ====
    def _loop_kwh(self, corrente_max, voltaggio, batteria_attuale):
        capacita_batteria_kwh = 75.0

        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            if not self.cavo_connesso:
                self.log("SISTEMA", "Cavo non più connesso → stop loop kWh.")
                # Stessa causa di scollega_cavo: avvisiamo il backend.
                self.termina_sessione(notifica_backend=True)
                break

            _, potenza_erogata = self.calcola_output_ricarica(voltaggio, corrente_max, batteria_attuale)

            delta_kwh = potenza_erogata / 3600
            batteria_attuale = min(batteria_attuale + (delta_kwh / capacita_batteria_kwh), 1.0)

            self.sessione_attuale.quantita_kwh += delta_kwh
            self.delta_kwh_accumulato         += delta_kwh

            self.log("ENERGY",
                     f"SoC: {batteria_attuale:>6.2%} | P: {potenza_erogata:>5.2f}kW | "
                     f"Tot: {self.sessione_attuale.quantita_kwh:.4f}kWh")

            if batteria_attuale >= 1.0:
                self.log("SISTEMA", "Batteria carica al 100%.")
                self.termina_sessione()
                break

            time.sleep(1)

    # ============================================================ loop redis ==
    def _loop_redis(self):
        """Ogni METER_INTERVAL secondi spinge il delta accumulato su Redis."""
        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            time.sleep(config.METER_INTERVAL)
            if self.sessione_attuale is None:
                break

            delta = self.delta_kwh_accumulato
            self.delta_kwh_accumulato = 0.0

            try:
                self.redis.scrivi_incremento(
                    id_punto=self.id_punto,
                    delta_kwh=delta,
                    totale_kwh=self.sessione_attuale.quantita_kwh,
                )
                self.log("REDIS", f"+{delta:.4f} kWh (tot {self.sessione_attuale.quantita_kwh:.4f})")
            except Exception as e:
                self.log("REDIS", f"Errore scrittura: {e}")


# ===========================================================================
#  STAZIONE
# ===========================================================================

class Stazione:
    """Colonnina fisica con N punti di ricarica. Si occupa anche
    dell'heartbeat HTTP unico verso Laravel per tutti i suoi punti."""

    def __init__(self, id_stazione: str, lista_id_punti: list[str]):
        self.id_stazione = id_stazione
        self.api = Apiclass()
        self.punti: dict[str, Punto_ricarica] = {
            id_punto: Punto_ricarica(id_punto) for id_punto in lista_id_punti
        }

        print(f"[STAZIONE {id_stazione}] {len(self.punti)} punto/i caricato/i.")
        threading.Thread(target=self._loop_heartbeat, daemon=True).start()

    def _loop_heartbeat(self):
        while True:
            for id_punto in list(self.punti.keys()):
                try:
                    self.api.api_scrivi_heartbeat(id_punto)
                except Exception as e:
                    print(f"[HEARTBEAT] errore su {id_punto}: {e}")
            time.sleep(config.HEARTBEAT_INTERVAL)

    def punto(self, id_punto: str) -> Punto_ricarica | None:
        return self.punti.get(id_punto)

    def shutdown(self):
        for p in self.punti.values():
            try:
                p.termina_sessione()
            except Exception:
                pass
            try:
                p.ws.disconnetti()
            except Exception:
                pass
