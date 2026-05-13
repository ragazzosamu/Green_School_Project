"""
Wrapper Redis per la colonnina.

La stazione, durante una sessione di ricarica, scrive *sempre* su Redis:
sia il totale aggiornato dei kWh erogati sia l'incremento dell'ultimo
intervallo. Questo permette a chi consuma i dati real-time (es. il
backend Laravel o un bridge verso il WS-server) di leggere senza
interrogare la stazione.

Schema usato (semplice, una chiave per punto):

    HASH  punto:{id_punto}
        kwh_totali      -> float, totale cumulato della sessione corrente
        ultimo_delta    -> float, ultimo incremento spedito
        aggiornato_il   -> timestamp unix dell'ultimo update
        sessione_attiva -> "1" / "0"

    PUBLISH  punto.{id_punto}.kwh  <payload JSON>   (per i subscriber)
"""
import json
import time
import redis
import config


class RedisClient:
    def __init__(self):
        self.r = redis.Redis(
            host=config.REDIS_HOST,
            port=config.REDIS_PORT,
            db=config.REDIS_DB,
            decode_responses=True,
        )

    def _key(self, id_punto: str) -> str:
        return f"punto:{id_punto}"

    def _channel(self, id_punto: str) -> str:
        return f"punto.{id_punto}.kwh"

    def avvio_sessione(self, id_punto: str):
        """Resetta i contatori della sessione precedente."""
        self.r.hset(self._key(id_punto), mapping={
            'kwh_totali':      '0',
            'ultimo_delta':    '0',
            'aggiornato_il':   str(int(time.time())),
            'sessione_attiva': '1',
        })

    def scrivi_incremento(self, id_punto: str, delta_kwh: float, totale_kwh: float):
        """Aggiorna l'hash e pubblica l'evento sul pub/sub."""
        ora = int(time.time())

        self.r.hset(self._key(id_punto), mapping={
            'kwh_totali':    f"{totale_kwh:.6f}",
            'ultimo_delta':  f"{delta_kwh:.6f}",
            'aggiornato_il': str(ora),
        })

        payload = json.dumps({
            'id_punto':         id_punto,
            'delta_kwh':        round(delta_kwh, 6),
            'kwh_totali':       round(totale_kwh, 6),
            'data_cambiamento': ora,
        })
        self.r.publish(self._channel(id_punto), payload)

    def fine_sessione(self, id_punto: str):
        self.r.hset(self._key(id_punto), 'sessione_attiva', '0')
