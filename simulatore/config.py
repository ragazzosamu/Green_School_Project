import os
from dotenv import load_dotenv

load_dotenv()

def richiedi(chiave: str) -> str:
    val = os.getenv(chiave)
    if not val:
        raise RuntimeError(f"[CONFIG] Variabile mancante nel .env: {chiave}")
    return val

# --- Backend Laravel ---
BACKEND_URL    = richiedi('BACKEND_URL')
ID_STAZIONE    = richiedi('ID_STAZIONE')
TOKEN_STAZIONE = richiedi('TOKEN_STAZIONE')

TIMEOUT_AUTH       = int(os.getenv("TIMEOUT_AUTH",       "60"))
METER_INTERVAL     = int(os.getenv("METER_INTERVAL",     "5"))
HEARTBEAT_INTERVAL = int(os.getenv("HEARTBEAT_INTERVAL", "60"))

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

# --- WebSocket server Node ---
WS_URL           = os.getenv('WS_URL',           'ws://ws-node:8080/')
WS_STATION_TOKEN = os.getenv('WS_STATION_TOKEN', 'dev-station-token-change-me')

# --- Redis ---
REDIS_HOST = os.getenv('REDIS_HOST', 'redis')
REDIS_PORT = int(os.getenv('REDIS_PORT', '6379'))
REDIS_DB   = int(os.getenv('REDIS_DB',   '0'))
