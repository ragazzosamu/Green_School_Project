import threading
import time
import config
from enum import Enum
from sessione import Sessione

# Mock di classi esterne per rendere il codice eseguibile
class Stato(Enum):
    OFFLINE = 0
    OCCUPATA = 1
    LIBERA = 2
    GUASTO = 3

class Punto_ricarica:
    def __init__(self, id_punto):
        self.id_punto = id_punto
        self.stato = Stato.LIBERA
        self.cavo_connesso = False
        self.sessione_attuale = None
        self.delta_kwh_accumulato = 0  # kWh da inviare al prossimo WS cycle

    def log(self, tag, messaggio):
        timestamp = time.strftime("%H:%M:%S")
        print(f"[{timestamp}] [{tag}] {messaggio}")

    def cavoconnesso(self, badge_usato):
        self.cavo_connesso = True
        self.log(f"PUNTO {self.id_punto}", "Cavo collegato fisicamente.")
        # Avviamo la sessione (passando i parametri necessari per la simulazione)
        self.avvio_sessione(badge_usato, corrente_max=32, voltaggio=230, soc_iniziale=0.2)

    def avvio_sessione(self, badge_usato, corrente_max, voltaggio, soc_iniziale):
        self.stato = Stato.OCCUPATA
        self.sessione_attuale = Sessione(self.id_punto, badge_usato, time.time(), None, 0)
        
        self.log("SESSIONE", f"Avvio ricarica per badge {badge_usato}")

        # Correzione: Passiamo gli argomenti ai thread tramite 'args'
        t1 = threading.Thread(target=self._loop_kwh, args=(corrente_max, voltaggio, soc_iniziale), daemon=True)
        t2 = threading.Thread(target=self._loop_heartbeat, daemon=True)
        t3 = threading.Thread(target=self._loop_websocket, daemon=True)

        t1.start()
        t2.start()
        t3.start()

    def termina_sessione(self):
        if self.sessione_attuale:
            self.sessione_attuale.ora_fine = time.time()
            self.stato = Stato.LIBERA
            self.log("SESSIONE", f"Terminata. Totale kWh erogati: {self.sessione_attuale.kwh:.4f}")
            # Qui chiameresti api_client.invia_fine_sessione(...)

    def calcola_output_ricarica(self, v_input, i_input, soc_attuale):
        if soc_attuale > 0.8:
            fattore_riduzione = (1.0 - soc_attuale) / 0.2
            i_reale = max(i_input * fattore_riduzione, 1.0)
        else:
            i_reale = i_input
        
        potenza_kw = (v_input * i_reale) / 1000
        return i_reale, potenza_kw

    def _loop_kwh(self, corrente_max, voltaggio, batteria_attuale):
        capacita_batteria_kwh = 75.0 
        
        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            corrente_erogata, potenza_erogata = self.calcola_output_ricarica(voltaggio, corrente_max, batteria_attuale)

            # Fisica: kW / 3600 = kWh al secondo
            delta_kwh = potenza_erogata / 3600
            batteria_attuale = min(batteria_attuale + (delta_kwh / capacita_batteria_kwh), 1.0)
            
            # Aggiorniamo i contatori
            self.sessione_attuale.kwh += delta_kwh
            self.delta_kwh_accumulato += delta_kwh

            self.log("ENERGY", f"SoC: {batteria_attuale:>6.2%} | P: {potenza_erogata:>5.2f}kW | Tot: {self.sessione_attuale.kwh:.4f}kWh")

            if batteria_attuale >= 1.0:
                self.log("SISTEMA", "Batteria carica al 100%.")
                self.termina_sessione()
                break
            
            time.sleep(1)

    def _loop_heartbeat(self):
        while self.stato != Stato.GUASTO:
            self.log("HEARTBEAT", f"Stato attuale: {self.stato.name}")
            # api_client.send_heartbeat(self.id_stazione, self.stato)
            time.sleep(10) # Ridotto per il test, metti 60 in prod time.sleep(config.HEARTBEAT_AUTH)

    def _loop_websocket(self):
        while self.sessione_attuale and self.sessione_attuale.ora_fine is None:
            # Simulazione invio WS
            self.log("WS-SEND", f"Push dati real-time: +{self.delta_kwh_accumulato:.4f} kWh")
            self.delta_kwh_accumulato = 0 # Reset del delta dopo l'invio
            time.sleep(5)