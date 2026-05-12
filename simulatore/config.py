import os
from dotenv import load_dotenv

load_dotenv()

def richiedi(chiave: str) ->str:
    val = os.getenv(key = chiave)
    if not val:
        raise RuntimeError(f"[CONFIG] Variabile mancante nel .env: {chiave}")
    return val

ID_STAZIONE = richiedi('ID_STAZIONE')
TOKEN_STAZIONE = richiedi('TOKEN_STAZIONE')
BACKEND_URL = richiedi('BACKEND_URL')
TIMEOUT_AUTH = int(os.getenv("TIMEOUT_AUTH",   "60"))
METER_INTERVAL = int(os.getenv("METER_INTERVAL", "5"))
HEARTBEAT_INTERVAL = int(os.getenv("HEARTBEAT_INTERVAL", "60"))

