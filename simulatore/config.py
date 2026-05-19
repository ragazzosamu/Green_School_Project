import os
import uuid
from dotenv import load_dotenv

load_dotenv()


def richiedi(chiave: str) -> str:
    val = os.getenv(chiave)
    if not val:
        raise RuntimeError(f"[CONFIG] Variabile mancante nel .env: {chiave}")
    return val


# --- Identita' della stazione ---
# Per la registrazione servono MAC ADDRESS, password globale e NUMERO_PUNTI:
# il numero di prese fisiche della stazione e' deciso dall'hardware (Arduino),
# non dall'admin. Il backend usa questo valore per creare i record
# punti_ricarica con id_punto = "1", "2", ..., "N". L'admin potra' solo
# completare i metadati (tipo veicolo, connettore, potenza) ma non
# aggiungere o rimuovere punti.
MAC_ADDRESS = richiedi("MAC_ADDRESS")
PASSWORD_REGISTRAZIONE = richiedi("PASSWORD_REGISTRAZIONE")
NUMERO_PUNTI = int(os.getenv("NUMERO_PUNTI", "2"))
BACKEND_URL = richiedi("BACKEND_URL")

# --- MQTT (broker Mosquitto) ---
# Host/porta vengono usati come fallback se l'API di registrazione non li
# restituisce. Le credenziali (user/password) NON le teniamo in .env: arrivano
# tutte dall'API di registrazione e cambiano dopo la prima richiesta HTTP.
MQTT_HOST = os.getenv("MQTT_HOST", "localhost")
MQTT_PORT = int(os.getenv("MQTT_PORT", "1883"))

# --- Intervalli (secondi) ---
METER_INTERVAL     = int(os.getenv("METER_INTERVAL",     "5"))
HEARTBEAT_INTERVAL = int(os.getenv("HEARTBEAT_INTERVAL", "60"))
# La stazione genera un codice monouso a 6 cifre ogni CODICE_INTERVAL secondi
# e lo pubblica via MQTT. L'utente lo digita nell'app al posto del vecchio QR.
# Backend: TTL Redis 35s (5s di overlap col prossimo codice per coprire eventuali ritardi rete).
CODICE_INTERVAL    = int(os.getenv("CODICE_INTERVAL",    "30"))
