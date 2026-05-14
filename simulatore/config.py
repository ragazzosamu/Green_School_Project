import os
from dotenv import load_dotenv

load_dotenv()

def richiedi(chiave: str) -> str:
    val = os.getenv(chiave)
    if not val:
        raise RuntimeError(f"[CONFIG] Variabile mancante nel .env: {chiave}")
    return val

# --- Identità della stazione ---
ID_STAZIONE = richiedi('ID_STAZIONE')
BACKEND_URL = richiedi('BACKEND_URL')

# --- Punti gestiti da questa stazione ---
# Leggiamo ID_PUNTO1, ID_PUNTO2, ... finché ne troviamo nel .env.
# Almeno uno è obbligatorio.
ID_PUNTI: list[str] = []
_i = 1
while True:
    _val = os.getenv(f'ID_PUNTO{_i}')
    if not _val:
        break
    ID_PUNTI.append(_val)
    _i += 1

if not ID_PUNTI:
    raise RuntimeError("[CONFIG] Nessun ID_PUNTO trovato nel .env (atteso ID_PUNTO1, ID_PUNTO2, ...)")

# --- MQTT (broker Mosquitto) ---
MQTT_HOST              = os.getenv('MQTT_HOST') or 'localhost'
MQTT_PORT              = int(os.getenv('MQTT_PORT') or '1883')
MQTT_NOME_UTENTE       = os.getenv('MQTT_NOME_UTENTE', 'colonnina')
MQTT_PASSWORD_STAZIONE = os.getenv('MQTT_PASSWORD_STAZIONE', '')

# --- Intervalli (secondi) ---
METER_INTERVAL     = int(os.getenv("METER_INTERVAL",     "5"))
HEARTBEAT_INTERVAL = int(os.getenv("HEARTBEAT_INTERVAL", "60"))
