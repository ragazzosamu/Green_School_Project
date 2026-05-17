import requests
import config

class ApiClient:
    def __init__(self):
        self.url = config.BACKEND_URL + "/api/"
        pass

    def registra_colonnina(self,password):
        url_api = self.url + "/iot/registra"

        payload = {
            "password" : password,
        }

        res = requests.post(url_api,json=payload)

        return res.json