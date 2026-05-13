
import requests
import config
from sessione import Sessione


class Apiclass:
    def __init__(self):
        self.url = f"{config.BACKEND_URL}/api"
        self.headers = {"X-Device-Token": config.TOKEN_STAZIONE}

    def api_scrivi_heartbeat(self, id_punto):
        url_api = f"{self.url}/heartbeat_punto"
        dati = {"id_punto": id_punto}

        risposta = requests.post(url=url_api, json=dati, headers=self.headers)

        print(f"Stato: {risposta.status_code}")
        print(f"Risposta dal server: {risposta.text}")

        return risposta

    def api_sessione_finita(self, sessione: Sessione):
        url_api = f"{self.url}/{sessione.id_punto}/termina_sessione"
        dati = {"quantita_kwh": sessione.quantita_kwh}

        risposta = requests.post(url=url_api, json=dati, headers=self.headers)

        print(f"Stato: {risposta.status_code}")
        print(f"Risposta dal server: {risposta.text}")

        return risposta