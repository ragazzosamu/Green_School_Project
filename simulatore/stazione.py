import threading
import time
import json
import secrets
import paho.mqtt.client as mqtt
from enum import Enum
from typing import Optional

import config
from api_client import ApiClient
from sessione import Sessione


class Stato(Enum):
    OFFLINE  = 0
    OCCUPATA = 1
    LIBERA   = 2
    GUASTO   = 3


# ===========================================================================
#  PUNTO DI RICARICA
# ===========================================================================
class Punto_ricarica:
    """
    Un singolo connettore della stazione. Identificato a livello logico
    da (id_stazione, id_punto) — qui dentro abbiamo entrambi sotto mano via
    self.stazione.
    """

    def __init__(self, id_punto: str, stazione: "Stazione"):
        self.id_punto = id_punto
        self.stazione = stazione
        self.stato = Stato.LIBERA
        self.cavo_connesso = False
        self.sessione_attuale: Optional[Sessione] = None
        self.voltaggio_attuale: float = 0.0
        self.corrente_attuale: float = 0.0
        self._lock = threading.Lock()

    # --- log helper ---

    def _topic(self, canale: str) -> str:
        return f"stazione/{self.stazione.id_stazione}/{self.id_punto}/{canale}"

    def log(self, tag: str, messaggio: str) -> None:
        ts = time.strftime("%H:%M:%S")
        print(f"[{ts}] [{self.stazione.id_stazione}/{self.id_punto}] [{tag}] {messaggio}")

    # --- gestione fisica cavo ---

    def collega_cavo(self):
        with self._lock:
            if self.cavo_connesso:
                return
            self.cavo_connesso = True
            self.log("CAVO", "Cavo inserito fisicamente.")
            self.stazione.pubblica(self._topic("eventi"), {
                "evento":      "cavo_collegato",
                "id_stazione": self.stazione.id_stazione,
                "id_punto":    self.id_punto,
            })

    def scollega_cavo(self):
        sessione_da_chiudere = False
        with self._lock:
            if not self.cavo_connesso:
                return
            self.cavo_connesso = False
            self.log("CAVO", "Cavo scollegato fisicamente.")
            if self.sessione_attuale is not None:
                sessione_da_chiudere = True

        if sessione_da_chiudere:
            self.termina_sessione(notifica_backend=True, evento="cavo_scollegato")

    # --- sessione ---

    def gestione_messaggio_start(self, id_sessione_da_laravel):
        with self._lock:
            if self.cavo_connesso and self.sessione_attuale is None:
                self.log("AUTH", f"Avvio autorizzato: {id_sessione_da_laravel}")
                self.sessione_attuale = Sessione(
                    id_punto=self.id_punto,
                    id_sessione=id_sessione_da_laravel,
                    quantita_kwh=0.0,
                    ora_inizio=time.time(),
                )
                self.avvio_sessione(corrente_max=32, voltaggio=230, soc_iniziale=0.2)
            else:
                self.log("ERRORE", "START fallito: cavo non connesso o sessione occupata.")

    def avvio_sessione(self, corrente_max, voltaggio, soc_iniziale):
        self.stato = Stato.OCCUPATA
        threading.Thread(
            target=self._loop_kwh,
            args=(corrente_max, voltaggio, soc_iniziale),
            daemon=True,
        ).start()
        threading.Thread(target=self._loop_telemetria, daemon=True).start()

    def termina_sessione(self, notifica_backend: bool = False, evento: str = None):
        with self._lock:
            sessione = self.sessione_attuale
            if sessione is None or sessione.ora_fine is not None:
                return
            sessione.ora_fine = time.time()
            self.stato = Stato.LIBERA
            self.sessione_attuale = None

        self.log("SESSIONE", f"Terminata. Totale: {sessione.quantita_kwh:.4f} kWh")

        if notifica_backend:
            self.stazione.pubblica(self._topic("eventi"), {
                "evento":      evento,
                "id_sessione": sessione.id_sessione,
                "kwh_totali":  sessione.quantita_kwh,
                "ora_fine":    sessione.ora_fine,
            })

    # --- loop interni di ricarica/telemetria (invariati rispetto a prima) ---

    def calcola_output_ricarica(self, v_input, i_input, soc_attuale):
        if soc_attuale > 0.8:
            fattore = (1.0 - soc_attuale) / 0.2
            i_reale = max(i_input * fattore, 1.0)
        else:
            i_reale = i_input
        return i_reale, (v_input * i_reale) / 1000

    def _loop_kwh(self, corrente_max, voltaggio, batteria_attuale):
        # Loop silenzioso: niente print ad ogni iterazione. I dati restano
        # disponibili in self.voltaggio_attuale / corrente_attuale / sessione_attuale
        # e li puoi consultare dal menu "Stato punto".
        capacita_batteria_kwh = 75.0

        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            if not self.cavo_connesso:
                self.termina_sessione(notifica_backend=True, evento="cavo_scollegato")
                break

            i_reale, potenza_erogata = self.calcola_output_ricarica(voltaggio, corrente_max, batteria_attuale)
            self.voltaggio_attuale = voltaggio
            self.corrente_attuale  = i_reale

            delta_kwh = potenza_erogata / 3600
            batteria_attuale = min(batteria_attuale + (delta_kwh / capacita_batteria_kwh), 1.0)
            self.sessione_attuale.quantita_kwh += delta_kwh

            if batteria_attuale >= 1.0:
                self.termina_sessione(notifica_backend=True, evento="batteria_piena")
                break

            time.sleep(1)

    def _loop_telemetria(self):
        while True:
            time.sleep(config.METER_INTERVAL)
            with self._lock:
                if not self.sessione_attuale:
                    break
                id_sess = self.sessione_attuale.id_sessione
                v = self.voltaggio_attuale
                i = self.corrente_attuale

            self.stazione.pubblica(self._topic("telemetria"), {
                "id_sessione":    id_sess,
                "voltaggio":      v,
                "corrente":       i,
                "intervallo_sec": config.METER_INTERVAL,
            })


# ===========================================================================
#  STAZIONE
# ===========================================================================
class Stazione:
    """
    Gateway MQTT. Non si crea piu' con id_stazione+id_punti hardcoded:
    il bootstrap passa da Stazione.registra() che chiama POST /api/iot/registra
    e attende il messaggio MQTT 'ready' prima di iniziare l'operativita'.
    """

    def __init__(self):
        self.api_client = ApiClient()
        self.id_stazione: Optional[str] = None
        self.punti: dict[str, Punto_ricarica] = {}
        self.mqtt: Optional[mqtt.Client] = None
        self.attiva = False  # diventa True quando arriva 'ready' o stato_setup gia' attiva

        # Codice monouso unico per tutta la stazione: vale per qualsiasi
        # punto. Mostrato a display, rigenerato ogni CODICE_INTERVAL secondi.
        self.codice_attuale: Optional[str] = None
        self._codice_thread_avviato = False

    # ---- registrazione ----

    def registra(self) -> bool:
        dati = self.api_client.registra_colonnina(
            config.MAC_ADDRESS,
            config.PASSWORD_REGISTRAZIONE,
            config.NUMERO_PUNTI,
        )
        if not dati:
            print("[ERRORE] Registrazione fallita.")
            return False

        self.id_stazione   = dati.get("id_stazione")
        self.device_token  = dati.get("device_token")
        stato_setup        = dati.get("stato_setup", "in_setup")
        id_punti           = dati.get("id_punti", []) or []

        # L'API restituisce host/porta del broker. Il broker e' anonimo:
        # niente user/password (le aggiungeremo quando ci servira').
        mqtt_info = dati.get("mqtt", {})
        host_mqtt = mqtt_info.get("host") or config.MQTT_HOST
        port_mqtt = int(mqtt_info.get("port") or config.MQTT_PORT)

        print(f"[REG] Stazione {self.id_stazione} registrata. stato_setup={stato_setup}.")

        self._connetti_mqtt(host_mqtt, port_mqtt)

        if stato_setup == "attiva" and id_punti:
            # La stazione era gia' configurata: parto subito.
            self._on_ready(id_punti)
        else:
            print("[REG] In attesa di setup dal pannello admin (topic 'ready')...")

        return True

    def _connetti_mqtt(self, host, port):
        client_id = f"stazione-{self.id_stazione}"
        self.mqtt = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=client_id)
        self.mqtt.on_connect = self._on_connect
        self.mqtt.on_message = self._on_message

        try:
            self.mqtt.connect(host, port, keepalive=60)
            self.mqtt.loop_start()
            print(f"[MQTT] Connesso a {host}:{port}.")
        except Exception as e:
            print(f"[ERRORE CONNETTIVITA'] Broker MQTT non raggiungibile: {e}")

    def _on_connect(self, client, userdata, flags, reason_code, properties):
        if reason_code != 0:
            print(f"[MQTT] Connessione rifiutata: {reason_code}")
            return

        # Sottoscrizioni station-wide:
        #  - ready: admin completa setup
        #  - comandi (station-wide): autenticazione_completata (per Arduino)
        #  - manutenzione: admin attiva/disattiva manutenzione
        # I comandi START/STOP sono per-punto e si sub al ready, sotto.
        self.mqtt.subscribe(f"stazione/{self.id_stazione}/ready")
        self.mqtt.subscribe(f"stazione/{self.id_stazione}/comandi")
        self.mqtt.subscribe(f"stazione/{self.id_stazione}/manutenzione")

        # Sottoscrizioni 'comandi' per ogni punto gia' noto.
        for id_punto in self.punti:
            self.mqtt.subscribe(f"stazione/{self.id_stazione}/{id_punto}/comandi")

        print("[MQTT] Iscrizioni completate.")

    def _on_message(self, client, userdata, msg):
        try:
            payload = json.loads(msg.payload.decode())
            parti = msg.topic.split("/")

            # stazione/{id}/ready
            if len(parti) == 3 and parti[2] == "ready":
                id_punti = payload.get("id_punti", []) or []
                self._on_ready(id_punti)
                return

            # stazione/{id}/comandi  (station-wide: autenticazione_completata)
            if len(parti) == 3 and parti[2] == "comandi":
                if payload.get("comando") == "autenticazione_completata":
                    print(f"\n[AUTH] Utente autenticato, in attesa cavo.")
                return

            # stazione/{id}/manutenzione (admin attiva/disattiva)
            if len(parti) == 3 and parti[2] == "manutenzione":
                on = bool(payload.get("on", False))
                if on:
                    self.attiva = False  # ferma codici/heartbeat
                    print(f"\n[MANUTENZIONE] Attivata: stazione fermata.")
                else:
                    if not self.attiva and self.punti:
                        self.attiva = True
                        self._avvia_generazione_codici()
                        threading.Thread(target=self._loop_heartbeat, daemon=True).start()
                        print(f"\n[MANUTENZIONE] Disattivata: stazione ripartita.")
                return

            # stazione/{id}/{id_punto}/comandi
            if len(parti) == 4 and parti[3] == "comandi":
                id_punto = parti[2]
                punto = self.punti.get(id_punto)
                if not punto:
                    return
                comando = payload.get("comando")
                if comando == "START":
                    punto.gestione_messaggio_start(payload.get("id_sessione"))
                elif comando == "STOP":
                    punto.termina_sessione(notifica_backend=False)
                elif comando == "autenticazione_completata":
                    punto.log("AUTH", "Backend ha verificato il codice: in attesa cavo.")
        except Exception as e:
            print(f"[MQTT ERROR] {e}")

    def _on_ready(self, id_punti: list[str]):
        """Attiva la stazione: crea i punti, sottoscrive comandi, avvia
        heartbeat e il loop UNICO di generazione codice (per tutta la stazione)."""
        if self.attiva:
            return
        if not id_punti:
            print("[READY] Nessun punto annunciato, attivazione rimandata.")
            return

        self.punti = {id_p: Punto_ricarica(id_p, self) for id_p in id_punti}
        for id_p in self.punti:
            self.mqtt.subscribe(f"stazione/{self.id_stazione}/{id_p}/comandi")

        self.attiva = True
        threading.Thread(target=self._loop_heartbeat, daemon=True).start()
        self._avvia_generazione_codici()
        print(f"[READY] Stazione attivata con punti {list(self.punti.keys())}.")

    # ---- codice monouso della stazione (un solo codice per tutti i punti) ----

    def _avvia_generazione_codici(self):
        if self._codice_thread_avviato:
            return
        self._codice_thread_avviato = True
        threading.Thread(target=self._loop_codice, daemon=True).start()

    def _loop_codice(self):
        # Niente print asincrono: il codice viene mostrato dal menu interattivo
        # ogni volta che si torna alla lista punti (cosi' non si sovrappone
        # all'input dell'utente). E' sempre disponibile in self.codice_attuale.
        while self.attiva:
            codice = f"{secrets.randbelow(1_000_000):06d}"
            self.codice_attuale = codice
            self.pubblica(f"stazione/{self.id_stazione}/codice", {
                "codice":   codice,
                "scadenza": 60,
            })
            time.sleep(config.CODICE_INTERVAL)

    # ---- loop / publish ----

    def _loop_heartbeat(self):
        while self.attiva:
            for id_punto, punto in self.punti.items():
                payload = {
                    "id_stazione": self.id_stazione,
                    "id_punto":    id_punto,
                    "stato":       punto.stato.name,
                    "ts":          time.time(),
                }
                self.pubblica(
                    f"stazione/{self.id_stazione}/{id_punto}/heartbeat",
                    payload,
                    retain=True,
                )
            time.sleep(config.HEARTBEAT_INTERVAL)

    def pubblica(self, topic: str, payload: dict, qos: int = 1, retain: bool = False):
        if not self.mqtt:
            return
        self.mqtt.publish(topic, json.dumps(payload), qos=qos, retain=retain)
