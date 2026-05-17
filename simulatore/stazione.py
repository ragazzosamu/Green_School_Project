import threading
import time
import json
import paho.mqtt.client as mqtt
from enum import Enum
from typing import Optional
from api_client import ApiClient

# Importiamo le dipendenze dal tuo progetto
import config
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
    Gestisce lo stato fisico e la logica di ricarica del singolo connettore.
    Pubblica V, I e intervallo ogni 5s tramite MQTT: il calcolo del delta kWh
    e' compito del worker lato backend (vedi MqttWorker::gestisciTelemetria).
    """

    def __init__(self, id_punto: str, stazione: 'Stazione'):
        self.id_punto = id_punto
        self.stazione = stazione
        self.stato = Stato.LIBERA
        self.cavo_connesso = False
        self.sessione_attuale: Optional[Sessione] = None
        # Stato elettrico istantaneo, aggiornato da _loop_kwh ogni secondo e
        # letto da _loop_telemetria per pubblicare i valori "freschi".
        self.voltaggio_attuale: float = 0.0
        self.corrente_attuale: float = 0.0
        self._lock = threading.Lock()

    def log(self, tag, messaggio):
        timestamp = time.strftime("%H:%M:%S")
        print(f"[{timestamp}] [{self.id_punto[:8]}] [{tag}] {messaggio}")

    # --- Gestione Fisica Cavo & QR ---

    def collega_cavo(self):
        """Rileva l'inserimento del cavo e aggiorna lo stato."""
        with self._lock:
            if self.cavo_connesso:
                return
            self.cavo_connesso = True
            self.log("CAVO", "Cavo inserito fisicamente.")
            
            # Se eravamo in attesa dopo scansione QR, il punto è pronto a partire
            # (La ricarica vera e propria parte dopo il comando START da backend)
            self.stazione.pubblica(f"stazione/{self.id_punto}/eventi", {
                "evento": "cavo_collegato", 
                "id_punto": self.id_punto
            })

    def scollega_cavo(self):
        """Gestisce il distacco del cavo: auto-stop se c'è una ricarica attiva."""
        sessione_da_chiudere = False
        with self._lock:
            if not self.cavo_connesso:
                return
            self.cavo_connesso = False
            self.log("CAVO", "Cavo scollegato fisicamente.")
            
            if self.sessione_attuale is not None:
                sessione_da_chiudere = True

        if sessione_da_chiudere:
            # Notifica backend perché il distacco è un evento imprevisto/fisico
            self.termina_sessione(notifica_backend=True, evento = "cavo_scollegato")

    # --- Gestione Sessione ---

    def gestione_messaggio_start(self, id_sessione_da_laravel):
        """Chiamata quando arriva il comando START via MQTT/Laravel."""
        with self._lock:
            # La ricarica parte solo se il cavo è presente (come da specifica)
            if self.cavo_connesso and self.sessione_attuale is None:
                self.log("AUTH", f"Avvio autorizzato: {id_sessione_da_laravel}")
                
                # Istanziamo la classe Sessione dal file sessione.py
                self.sessione_attuale = Sessione(
                    id_punto=self.id_punto,
                    id_sessione=id_sessione_da_laravel,
                    quantita_kwh=0.0,
                    ora_inizio=time.time()
                )
                
                self.avvio_sessione(corrente_max=32, voltaggio=230, soc_iniziale=0.2)
            else:
                self.log("ERRORE", "START fallito: cavo non connesso o sessione occupata.")

    def avvio_sessione(self, corrente_max, voltaggio, soc_iniziale):
        """Attiva i thread di simulazione ricarica e telemetria."""
        self.stato = Stato.OCCUPATA
        self.delta_kwh_accumulato = 0.0

        # Thread per simulazione erogazione
        threading.Thread(
            target=self._loop_kwh,
            args=(corrente_max, voltaggio, soc_iniziale),
            daemon=True
        ).start()

        # Thread per invio dati Redis/MQTT ogni 5s
        threading.Thread(
            target=self._loop_telemetria,
            daemon=True
        ).start()

    def termina_sessione(self, notifica_backend: bool = False, evento : str = None):
        """Chiude la ricarica e resetta lo stato del punto."""
        with self._lock:
            sessione = self.sessione_attuale
            if sessione is None or sessione.ora_fine is not None:
                return
            
            sessione.ora_fine = time.time()
            self.stato = Stato.LIBERA
            self.sessione_attuale = None

        self.log("SESSIONE", f"Terminata. Totale: {sessione.quantita_kwh:.4f} kWh")

        # Se non bisogna notificare il backend significa che i dati sono già stati scritti sul DB
        if notifica_backend:
            self.stazione.pubblica(f"stazione/{self.id_punto}/eventi", {
                "evento": evento,
                "id_sessione": sessione.id_sessione,
                "kwh_totali": sessione.quantita_kwh,
                "ora_fine": sessione.ora_fine
            })   

    # --- Loop Interni ---

    def calcola_output_ricarica(self, v_input, i_input, soc_attuale):
        """Simula la curva di ricarica: oltre l'80% di SoC la corrente
        viene ridotta progressivamente. Ritorna (corrente_reale, potenza_kW)."""
        if soc_attuale > 0.8:
            fattore_riduzione = (1.0 - soc_attuale) / 0.2
            i_reale = max(i_input * fattore_riduzione, 1.0)
        else:
            i_reale = i_input

        potenza_kw = (v_input * i_reale) / 1000
        return i_reale, potenza_kw

    def _loop_kwh(self, corrente_max, voltaggio, batteria_attuale):
        """
        Loop interno per la simulazione fisica della ricarica.
        Ogni secondo aggiorna SoC e corrente reale (applicando la curva di carica).
        Espone V e I correnti tramite self.voltaggio_attuale / self.corrente_attuale
        cosi' _loop_telemetria li puo' pubblicare.
        """
        capacita_batteria_kwh = 75.0

        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            if not self.cavo_connesso:
                self.log("SISTEMA", "Cavo non più connesso → stop loop kWh.")
                self.termina_sessione(notifica_backend=True)
                break

            i_reale, potenza_erogata = self.calcola_output_ricarica(voltaggio, corrente_max, batteria_attuale)

            # Aggiorno lo stato elettrico istantaneo (sara' letto da _loop_telemetria)
            self.voltaggio_attuale = voltaggio
            self.corrente_attuale  = i_reale

            # SoC interna alla simulazione (solo per la curva, non viene mandata)
            delta_kwh = potenza_erogata / 3600
            batteria_attuale = min(batteria_attuale + (delta_kwh / capacita_batteria_kwh), 1.0)
            self.sessione_attuale.quantita_kwh += delta_kwh

            self.log("ENERGY",
                     f"SoC: {batteria_attuale:>6.2%} | V: {voltaggio:.0f}V I: {i_reale:>5.2f}A "
                     f"P: {potenza_erogata:>5.2f}kW")

            if batteria_attuale >= 1.0:
                self.log("SISTEMA", "Batteria carica al 100%.")
                self.termina_sessione(notifica_backend=True, evento="batteria_piena")
                break

            time.sleep(1)

    def _loop_telemetria(self):
        """
        Invia ogni METER_INTERVAL secondi i valori grezzi al backend:
            voltaggio, corrente_reale (dopo la curva), intervallo.
        Il calcolo del delta kWh lo fa il worker Laravel.
        """
        while True:
            time.sleep(config.METER_INTERVAL)

            with self._lock:
                if not self.sessione_attuale:
                    break
                id_sess = self.sessione_attuale.id_sessione
                v = self.voltaggio_attuale
                i = self.corrente_attuale

            self.stazione.pubblica(f"stazione/{self.id_punto}/telemetria", {
                "id_sessione":    id_sess,
                "voltaggio":      v,
                "corrente":       i,
                "intervallo_sec": config.METER_INTERVAL,
            })

# ===========================================================================
#  STAZIONE
# ===========================================================================
class Stazione:
    """Gateway principale che gestisce la connessione MQTT e l'Heartbeat."""

    #Da cambiare 
    def __init__(self, id_stazione: str, lista_id_punti: list[str]):

        self.api_client = ApiClient()
        self.id_stazione = id_stazione
        self.punti: dict[str, Punto_ricarica] = {
            id_p: Punto_ricarica(id_p, self) for id_p in lista_id_punti
        }

        # Setup MQTT (paho-mqtt 2.x: serve la CallbackAPIVersion)
        self.mqtt = mqtt.Client(
            mqtt.CallbackAPIVersion.VERSION2,
            client_id=f"stazione-{id_stazione}",
        )
        self.mqtt.username_pw_set(config.MQTT_NOME_UTENTE, config.MQTT_PASSWORD_STAZIONE)
        self.mqtt.on_connect = self._on_connect
        self.mqtt.on_message = self._on_message

        try:
            self.mqtt.connect(config.MQTT_HOST, config.MQTT_PORT, keepalive=60)
            self.mqtt.loop_start()
        except Exception as e:
            print(f"[ERRORE CONNETTIVITÀ] Impossibile collegarsi al Broker: {e}")

        # Heartbeat unico per tutta la stazione
        threading.Thread(target=self._loop_heartbeat, daemon=True).start()

    def registra(self):
        # 1. Recupero la password dal file di configurazione
        #password = config.PASSWORD_REGISTRAZIONE  # Cambialo con la tua variabile reale in config
        password = ""

        # 2. Faccio la chiamata API e catturo la risposta in sicurezza
        dati = self.api_client.registra_colonnina(password)
        if not dati:
            print("[ERRORE] Registrazione fallita, impossibile procedere.")
            return

        # 3. Estraggo i dati usando i metodi sicuri (con i default in caso di errore)
        self.id_stazione = dati.get("id_stazione")
        id_punti = dati.get("id_punti", [])
        
        # 4. Genero i punti di ricarica dinamici ricevuti dall'API
        self.punti = {
            id_punto: Punto_ricarica(id_punto, self) for id_punto in id_punti
        }

        # 5. Configuro il client MQTT con il nuovo ID della stazione
        user_mqtt = dati.get("user_mqtt")
        password_mqtt = dati.get("password_mqtt")
        host_mqtt = dati.get("host_mqtt")
        port_mqtt = dati.get("port_mqtt")

        self.mqtt = mqtt.Client(
            mqtt.CallbackAPIVersion.VERSION2,
            client_id=f"stazione-{self.id_stazione}",
        )
        self.mqtt.username_pw_set(user_mqtt, password_mqtt)
        self.mqtt.on_connect = self._on_connect
        self.mqtt.on_message = self._on_message



        try:
            self.mqtt.connect(host_mqtt, port_mqtt, keepalive=60)
            self.mqtt.loop_start()
            print(f"[MQTT] Connesso con successo per la stazione: {self.id_stazione}")
        except Exception as e:
            print(f"[ERRORE CONNETTIVITÀ] Impossibile collegarsi al Broker MQTT: {e}")


        threading.Thread(target=self._loop_heartbeat, daemon=True).start()




    def _on_connect(self, client, userdata, flags, reason_code, properties):
        if reason_code == 0:
            print("[MQTT] Connesso. In ascolto per comandi punti...")
            for id_punto in self.punti:
                self.mqtt.subscribe(f"stazione/{id_punto}/comandi")
        else:
            print(f"[MQTT] Connessione rifiutata dal broker: {reason_code}")

    def _on_message(self, client, userdata, msg):
        try:
            payload = json.loads(msg.payload.decode())
            parti = msg.topic.split("/")
            id_punto = parti[1]
            punto = self.punti.get(id_punto)

            if punto:
                comando = payload.get("comando")
                if comando == "START":
                    punto.gestione_messaggio_start(payload.get("id_sessione"))
                elif comando == "STOP":
                    # Lo stop remoto assume che Laravel abbia già aggiornato il DB
                    punto.termina_sessione(notifica_backend=False)
        except Exception as e:
            print(f"[MQTT ERROR] {e}")

    def _loop_heartbeat(self):
        """Invia lo stato di tutti i punti a Laravel via HTTP o MQTT (come da config)."""
        while True:
            for id_punto, punto in self.punti.items():
                payload = {
                    "id_punto": id_punto,
                    "stato": punto.stato.name,
                    "ts": time.time()
                }
                self.pubblica(f"stazione/{id_punto}/heartbeat", payload, retain=True)
            
            time.sleep(config.HEARTBEAT_INTERVAL)

    def pubblica(self, topic: str, payload: dict, qos: int = 1, retain: bool = False):
        """Metodo centralizzato per inviare messaggi MQTT."""
        self.mqtt.publish(topic, json.dumps(payload), qos=qos, retain=retain)