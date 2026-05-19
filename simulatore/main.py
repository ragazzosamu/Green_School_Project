"""
Console di simulazione della colonnina.

Flusso:
  1. La stazione chiama POST /api/iot/registra (MAC + password globale +
     numero_punti) e riceve host/porta del broker MQTT.
  2. Se stato_setup='in_setup' attende il messaggio MQTT 'ready' dall'admin.
  3. A 'ready' parte il loop di generazione codice (uno per stazione, ogni 30s)
     e l'heartbeat. Il codice corrente e' sempre visibile in cima al menu.

Il simulatore NON stampa eventi rumorosi (telemetria, kWh, energy log):
controllali entrando nello "Stato punto" dal menu del singolo punto.
"""
import sys
import time
import uuid

from stazione import Stazione, Punto_ricarica


# Banner stazione mostrato in cima al menu. Lo aggiorniamo manualmente
# ad ogni iterazione cosi' l'utente vede sempre il codice piu' recente
# senza che il loop asincrono interrompa l'input.
def stampa_banner_stazione(stazione: Stazione):
    print()
    print("=" * 56)
    print(f"  STAZIONE  {stazione.id_stazione}")
    codice = stazione.codice_attuale or "------"
    print(f"  CODICE    {codice}    (valido per qualsiasi punto)")
    if not stazione.attiva:
        print("  STATO     in attesa di ready dal backend / manutenzione")
    print("=" * 56)


def _descrizione_sessione(p: Punto_ricarica) -> str:
    return "ricarica in corso" if p.sessione_attuale is not None else "libero"


def stampa_lista_punti(stazione: Stazione):
    stampa_banner_stazione(stazione)
    for i, (id_punto, p) in enumerate(stazione.punti.items(), 1):
        cavo = "[cavo]" if p.cavo_connesso else "[    ]"
        print(f"  [{i}] punto {id_punto}  {cavo}  {p.stato.name:<8}  {_descrizione_sessione(p)}")
    print("  [q] Esci")
    print("-" * 56)


def stampa_stato_punto(stazione: Stazione, p: Punto_ricarica):
    print("-" * 56)
    print(f"  STAZIONE       {stazione.id_stazione}")
    print(f"  CODICE attuale {stazione.codice_attuale or '-'}")
    print(f"  punto          {p.id_punto}")
    print(f"  stato          {p.stato.name}")
    print(f"  cavo           {'connesso' if p.cavo_connesso else 'NON connesso'}")
    s = p.sessione_attuale
    if s is not None:
        print(f"  sessione       ATTIVA  id={s.id_sessione}")
        print(f"    kWh erogati  {s.quantita_kwh:.4f}")
        print(f"    V/I attuali  {p.voltaggio_attuale:.0f}V  {p.corrente_attuale:.2f}A")
    else:
        print(f"  sessione       nessuna")
    print("-" * 56)


MENU_PUNTO = """
  PUNTO {id_breve}
  [1] Collega cavo
  [2] Scollega cavo
  [3] Simula START (per test, normalmente arriva da backend)
  [4] Stato punto
  [5] Termina sessione manualmente
  [b] Indietro
  [q] Esci
> """


def menu_punto(stazione: Stazione, p: Punto_ricarica) -> str:
    while True:
        try:
            scelta = input(MENU_PUNTO.format(id_breve=p.id_punto)).strip().lower()
        except (EOFError, KeyboardInterrupt):
            print()
            return 'q'

        if scelta == '1':
            p.collega_cavo()
        elif scelta == '2':
            p.scollega_cavo()
        elif scelta == '3':
            sid = input("id_sessione da simulare [vuoto = uuid casuale]: ").strip() or str(uuid.uuid4())
            p.gestione_messaggio_start(sid)
        elif scelta == '4':
            stampa_stato_punto(stazione, p)
        elif scelta == '5':
            p.termina_sessione(notifica_backend=False)
        elif scelta == 'b':
            return 'b'
        elif scelta == 'q':
            return 'q'
        else:
            print("Scelta non valida.")


def chiudi(stazione: Stazione):
    print("Uscita...")
    try:
        if stazione.mqtt is not None:
            stazione.mqtt.loop_stop()
            stazione.mqtt.disconnect()
    except Exception:
        pass
    sys.exit(0)


def main():
    stazione = Stazione()
    if not stazione.registra():
        sys.exit(1)

    while not stazione.attiva:
        print("  ... in attesa di 'ready' dal backend (completa il setup admin) ...")
        time.sleep(5)

    while True:
        stampa_lista_punti(stazione)
        try:
            scelta = input("> ").strip().lower()
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
        if menu_punto(stazione, punto) == 'q':
            chiudi(stazione)


if __name__ == "__main__":
    main()
