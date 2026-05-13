"""
Client WebSocket per la colonnina.

Si collega al server Node ws-server (vedi ws-server/server.js) e si comporta
come un client subscriber: si iscrive al canale del proprio punto di ricarica
("punto.{id_punto}") per ricevere comandi dal backend, in particolare la
richiesta di STOP arrivata dal browser dell'utente.

Gli incrementi di kWh ogni 5s NON passano da qui: vengono scritti su Redis
(vedi redis_client.py). Questa classe ascolta soltanto.
"""
import json
import threading
import time
import websocket  # pacchetto: websocket-client
import config


class WSClient:
    def __init__(self, id_punto: str, on_stop_richiesto, on_sessione_pendente=None):
        """
        :param id_punto: identificativo del punto di ricarica
        :param on_stop_richiesto: callback() — chiamata quando il backend
            notifica che la sessione deve terminare (utente STOP dal browser).
        :param on_sessione_pendente: callback(id_sessione: str) — chiamata
            quando Laravel notifica che un utente ha scansionato il QR per
            questo punto: la colonnina apre la finestra di attesa cavo.
        """
        self.id_punto = id_punto
        self.on_stop_richiesto    = on_stop_richiesto
        self.on_sessione_pendente = on_sessione_pendente
        self.canale = f"punto.{id_punto}"

        self.ws: websocket.WebSocketApp | None = None
        self._thread: threading.Thread | None = None
        self._stop_event = threading.Event()
        self._connected = threading.Event()

    # ---------------------------- log helpers ---------------------------------
    def log(self, tag, messaggio):
        timestamp = time.strftime("%H:%M:%S")
        print(f"[{timestamp}] [{tag}] {messaggio}")

    # ---------------------------- lifecycle -----------------------------------
    def connetti(self, timeout: float = 10.0) -> bool:
        """Avvia la connessione in un thread di background. Ritorna True se
        la connessione si apre entro `timeout` secondi."""
        self.ws = websocket.WebSocketApp(
            config.WS_URL,
            on_open    = self._on_open,
            on_message = self._on_message,
            on_close   = self._on_close,
            on_error   = self._on_error,
        )

        self._thread = threading.Thread(target=self._run_forever, daemon=True)
        self._thread.start()

        return self._connected.wait(timeout=timeout)

    def _run_forever(self):
        # ping_interval/ping_timeout: tiene viva la connessione e rileva i
        # disconnect "silenziosi" (rete che cade senza FIN).
        while not self._stop_event.is_set():
            try:
                self.ws.run_forever(ping_interval=20, ping_timeout=10)
            except Exception as e:
                self.log("WS", f"Errore loop: {e}")

            if self._stop_event.is_set():
                break

            # Riconnessione automatica con piccolo backoff
            self.log("WS", "Connessione chiusa, riprovo fra 3s...")
            time.sleep(3)

    def disconnetti(self):
        self._stop_event.set()
        if self.ws is not None:
            try:
                self.ws.close()
            except Exception:
                pass

    # ---------------------------- callbacks WS --------------------------------
    def _on_open(self, ws):
        self.log("WS", f"Connesso a {config.WS_URL}. Mi iscrivo a '{self.canale}'.")
        # Il server Node si aspetta {"action":"subscribe","channel":"..."}
        ws.send(json.dumps({"action": "subscribe", "channel": self.canale}))
        self._connected.set()

    def _on_message(self, ws, raw):
        try:
            msg = json.loads(raw)
        except Exception:
            self.log("WS-RX", f"Messaggio non-JSON ignorato: {raw[:80]}")
            return

        event = msg.get('event')
        data  = msg.get('data', {})

        # Eventi di servizio
        if event in ('connected', 'subscribed', 'pong'):
            self.log("WS-RX", f"{event} {data}")
            return

        self.log("WS-RX", f"Evento '{event}' sul canale '{msg.get('channel')}' data={data}")

        # Comando esplicito di stop ricarica inviato dal backend
        if event == 'sessione.stop':
            self.log("WS", "Ricevuto STOP dal backend.")
            try:
                self.on_stop_richiesto()
            except Exception as e:
                self.log("WS", f"Errore callback stop: {e}")
            return

        # Laravel notifica che un utente ha scansionato il QR su questo punto:
        # apriamo la finestra di attesa cavo.
        if event == 'sessione.pending':
            id_sessione = (data or {}).get('id_sessione')
            self.log("WS", f"Ricevuto QR pendente (id_sessione={id_sessione}).")
            if self.on_sessione_pendente is not None and id_sessione:
                try:
                    self.on_sessione_pendente(id_sessione)
                except Exception as e:
                    self.log("WS", f"Errore callback pendente: {e}")
            return

    def _on_close(self, ws, status_code, msg):
        self._connected.clear()
        self.log("WS", f"Connessione chiusa (code={status_code}, msg={msg}).")

    def _on_error(self, ws, error):
        self.log("WS", f"Errore: {error}")
