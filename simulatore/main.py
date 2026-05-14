"""
Console di simulazione: ti mette "davanti" alla colonnina.

La Stazione carica tutti i punti elencati nell'env (ID_PUNTO1, ID_PUNTO2, ...)
e si collega al broker MQTT. Dal menu principale scegli un punto e poi operi
su quel punto.

NB sul flusso reale:
  - il comando di START arriva da Laravel via MQTT sul topic
    `stazione/{id_punto}/comandi` con payload {"comando":"START","id_sessione":...}
    e viene gestito da Stazione._on_message → Punto.gestione_messaggio_start();
  - l'opzione [3] qui sotto chiama direttamente gestione_messaggio_start() per
    simulare a mano quel comando, senza dover passare dal broker.
"""
import sys
import uuid
import config
from stazione import Stazione, Punto_ricarica


MENU_PUNTO = """
================ PUNTO {id_breve} ================
  [1] Collega cavo
  [2] Scollega cavo
  [3] Simula comando START (QR autorizzato da Laravel)
  [4] Stato punto
  [5] Termina sessione (manualmente)
  [b] Torna alla lista punti
  [q] Esci
==================================================
> """


def _descrizione_sessione(p: Punto_ricarica) -> str:
    if p.sessione_attuale is not None:
        return "ricarica in corso"
    return "libero"


def stampa_lista_punti(stazione: Stazione):
    print()
    intestazione = f"=========== STAZIONE {stazione.id_stazione} ==========="
    print(intestazione)
    for i, (id_punto, p) in enumerate(stazione.punti.items(), 1):
        cavo = "[cavo]" if p.cavo_connesso else "[----]"
        print(f"  [{i}] {id_punto}  {cavo}  {p.stato.name:<12}  {_descrizione_sessione(p)}")
    print("  [q] Esci")
    print("=" * len(intestazione))


def stampa_stato_punto(p: Punto_ricarica):
    print("--------------------------------------------------------------")
    print(f"  id_punto       : {p.id_punto}")
    print(f"  stato          : {p.stato.name}")
    print(f"  cavo connesso  : {'SI' if p.cavo_connesso else 'NO'}")

    sess = p.sessione_attuale
    if sess is not None:
        print(f"  sessione       : ATTIVA")
        print(f"    id_sessione  : {sess.id_sessione}")
        print(f"    kWh erogati  : {sess.quantita_kwh:.4f}")
        print(f"    iniziata     : {sess.ora_inizio:.0f}")
    else:
        print(f"  sessione       : nessuna")
    print("--------------------------------------------------------------")


def menu_punto(p: Punto_ricarica) -> str:
    """Restituisce 'q' se l'utente vuole uscire del tutto, altrimenti 'b'."""
    id_breve = p.id_punto[:8]
    while True:
        try:
            scelta = input(MENU_PUNTO.format(id_breve=id_breve)).strip().lower()
        except (EOFError, KeyboardInterrupt):
            print()
            return 'q'

        if scelta == '1':
            p.collega_cavo()

        elif scelta == '2':
            p.scollega_cavo()

        elif scelta == '3':
            sid = input("id_sessione da simulare [vuoto = uuid casuale]: ").strip() or str(uuid.uuid4())
            # Nel flusso reale questo comando arriva via MQTT da Laravel;
            # qui lo iniettiamo direttamente nel punto.
            p.gestione_messaggio_start(sid)
            print(f"→ comando START inviato (id_sessione={sid})")

        elif scelta == '4':
            stampa_stato_punto(p)

        elif scelta == '5':
            # Stop manuale da console: il DB lo aggiorna chi ha richiesto lo
            # stop, quindi non rinotifichiamo il backend.
            p.termina_sessione(notifica_backend=False)

        elif scelta == 'b':
            return 'b'

        elif scelta == 'q':
            return 'q'

        else:
            print("Scelta non valida.")


def chiudi(stazione: Stazione):
    """Chiusura ordinata: ferma il loop MQTT e disconnette."""
    print("Uscita...")
    try:
        stazione.mqtt.loop_stop()
        stazione.mqtt.disconnect()
    except Exception:
        pass
    sys.exit(0)


def main():
    print(f"Avvio stazione id={config.ID_STAZIONE} con {len(config.ID_PUNTI)} punto/i")
    stazione = Stazione(config.ID_STAZIONE, config.ID_PUNTI)

    while True:
        stampa_lista_punti(stazione)
        try:
            scelta = input("Scegli punto (numero) o q per uscire: ").strip().lower()
        except (EOFError, KeyboardInterrupt):
            print()
            scelta = 'q'

        if scelta == 'q':
            chiudi(stazione)

        try:
            idx = int(scelta)
        except ValueError:
            print("Scelta non valida.")
            continue

        ids = list(stazione.punti.keys())
        if not (1 <= idx <= len(ids)):
            print("Numero fuori range.")
            continue

        punto = stazione.punti[ids[idx - 1]]
        if menu_punto(punto) == 'q':
            chiudi(stazione)


if __name__ == "__main__":
    main()
