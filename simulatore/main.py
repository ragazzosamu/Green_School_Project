"""
Console di simulazione: ti mette "davanti" alla colonnina.

La stazione carica tutti i punti elencati nell'env (ID_PUNTO1, ID_PUNTO2, ...).
Dal menu principale scegli un punto e poi operi su quel punto.

NB: l'autenticazione vera arriva via WebSocket da Laravel (evento
`sessione.pending` sul canale `punto.{id_punto}`). L'opzione [3] qui sotto
serve solo a simulare a mano quell'evento durante i test, generando un
finto id_sessione.
"""
import sys
import uuid
import config
from stazione import Stazione, Punto_ricarica


MENU_PUNTO = """
================ PUNTO {id_breve} ================
  [1] Collega cavo
  [2] Scollega cavo
  [3] Scansiona QR (autenticazione utente)
  [4] Stato punto
  [5] Termina sessione (manualmente)
  [b] Torna alla lista punti
  [q] Esci
==================================================
> """


def stampa_lista_punti(stazione: Stazione):
    print()
    print(f"=========== STAZIONE {stazione.id_stazione} ===========")
    for i, (id_punto, p) in enumerate(stazione.punti.items(), 1):
        cavo = "🔌" if p.cavo_connesso else "  "
        sess = "⚡ ricarica" if p.sessione_attuale else ("⏳ in attesa cavo" if p.stato.name == 'ATTESA_CAVO' else "libero")
        print(f"  [{i}] {id_punto}  {cavo}  {p.stato.name:<12}  {sess}")
    print("  [q] Esci")
    print("=" * (28 + len(stazione.id_stazione)))


def stampa_stato_punto(p: Punto_ricarica):
    print("--------------------------------------------------------------")
    print(f"  id_punto       : {p.id_punto}")
    print(f"  stato          : {p.stato.name}")
    print(f"  cavo connesso  : {'SI' if p.cavo_connesso else 'NO'}")

    rimasti = p.secondi_rimasti_auth()
    if rimasti is not None:
        print(f"  attesa cavo    : {rimasti}s rimasti (sess: {p._pending_id_sessione})")

    sess = p.sessione_attuale
    if sess:
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
            ok = p.autentica_qr(sid)
            print("→ autenticazione " + ("ACCETTATA" if ok else "RIFIUTATA"))
        elif scelta == '4':
            stampa_stato_punto(p)
        elif scelta == '5':
            p.termina_sessione()
        elif scelta == 'b':
            return 'b'
        elif scelta == 'q':
            return 'q'
        else:
            print("Scelta non valida.")


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
            print("Uscita...")
            stazione.shutdown()
            sys.exit(0)

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
            print("Uscita...")
            stazione.shutdown()
            sys.exit(0)


if __name__ == "__main__":
    main()
