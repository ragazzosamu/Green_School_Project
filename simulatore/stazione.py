from enum import Enum
import config
import threading
import api_client
from sessione import Sessione
import time

class Stato(Enum):
    OFFLINE = 0
    OCCUPATA = 1
    LIBERA = 2
    GUASTO = 3

class Stazione:
    def __init__(self,punti_ricarica):
        self.id_stazione     = config.ID_STAZIONE
        self.punti_ricarica  = punti_ricarica


class Punto_ricarica:
    def __init__(self,id_punto):
        self.id_punto                   = id_punto
        self.stato                      = Stato.LIBERA
        self.cavo_connesso              = False
        self.timer_auth                 = config.TIMEOUT_AUTH
        self.heartbeat                  = config.HEARTBEAT_INTERVAL
        self.sessione_attuale: Sessione = None

    def cavoconnesso(self):
        self.cavo_connesso = True
        print(f"[PUNTO: {self.id_punto}] cavo connesso, inizio ricarica")
        self.avvio_sessione

    def scollegacavo(self):
        if self.sessione.ora_fine is None:
            #termina_sessione()
            print(1)
        print(f"[PUNTO: {self.id_punto}] cavo scollegato")
        self.cavo_connesso = False
    
    def avvio_sessione(self,badge_usato):
        self.sessione_attuale = Sessione(self.id_punto,badge_usato,time.time,None,0)

    
    



    




