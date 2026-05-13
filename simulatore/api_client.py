import requests
import config
from sessione import Sessione

class Apiclass:
    def __init__(self)
        self.url = f"{config.BACKEND_URL}/api"

    @staticmethod
    def api_scrivi_heartbeat(id_punto):
        # 1. Specifichiamo Apiclass.url per dirgli dove pescare la variabile
        url_api = self.url + "/heartbeat_punto"
        
        # (Opzionale: evito di chiamare la variabile "json" per non confonderla con il modulo nativo di Python)
        dati = {
            "X-Device_Token" : config.TOKEN_STAZIONE,
            "id_punto"       : id_punto
        }

        # 2. Salviamo la risposta del server in una variabile
        risposta = requests.post(url=url_api, json=dati)

        # 3. Stampiamo i risultati così "escono le cose" e capisci se funziona
        print(f"Stato: {risposta.status_code}") # Es: 200 (OK) o 404 (Errore)
        print(f"Risposta dal server: {risposta.text}")

        # Restituiamo l'oggetto nel caso serva ad altre parti del codice
        return risposta

    @staticmethod
    def api_sessione_finita(Sessione sessione):
        url_api = f"{self.url}/{sessione.id_punto}/termina_sessione"
