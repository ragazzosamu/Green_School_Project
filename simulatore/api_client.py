import requests
import config


class ApiClient:
    def __init__(self):
        # Niente "/api/" di prefisso embeddato per evitare doppio slash
        self.base = config.BACKEND_URL.rstrip("/")

    def registra_colonnina(self, mac: str, password: str, numero_punti: int) -> dict | None:
        """
        Chiama POST /api/iot/registra. Ritorna il JSON di risposta:
            {
              id_stazione, device_token, stato_setup, id_punti,
              mqtt: {host, port}, topic_*
            }
        oppure None se la chiamata fallisce.

        numero_punti e' il numero di prese fisiche della stazione (deciso
        dall'hardware): al primo contatto il backend crea altrettanti record
        punti_ricarica con id_punto = "1", "2", ..., "N".
        """
        url = f"{self.base}/api/iot/registra"
        payload = {"mac": mac, "password": password, "numero_punti": numero_punti}
        try:
            res = requests.post(url, json=payload, timeout=10)
        except requests.RequestException as e:
            print(f"[API] Errore di rete: {e}")
            return None

        if res.status_code not in (200, 201):
            print(f"[API] Registrazione fallita ({res.status_code}): {res.text}")
            return None

        return res.json()
