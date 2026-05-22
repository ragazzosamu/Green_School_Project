# 🔌 Simulatore Colonnine — Green School Project

Simulatore in **Python** delle colonnine di ricarica. Riproduce il comportamento di una
stazione fisica (con i suoi punti di ricarica) e comunica con il backend tramite il
broker **MQTT** (Mosquitto) e una chiamata REST iniziale di registrazione.

Serve per sviluppare e testare tutto il flusso di ricarica **senza hardware reale**.
Per il backend è indistinguibile da un ESP32 vero.

---

## ⚙️ Come funziona

Il simulatore modella due entità:

- **`Stazione`** — il dispositivo fisico. Si registra al backend, si connette al broker
  MQTT, gestisce uno o più punti di ricarica, genera il **codice monouso** e invia un
  *heartbeat* periodico.
- **`Punto_ricarica`** — il singolo connettore. Gestisce lo stato del cavo, l'avvio/fine
  della sessione e, durante la ricarica, simula l'erogazione di energia inviando la
  **telemetria** (voltaggio/corrente) ogni pochi secondi.

### Flusso completo
1. **Registrazione** — `POST /api/iot/registra` con `MAC + PASSWORD_REGISTRAZIONE +
   NUMERO_PUNTI`. Se è la prima volta la stazione viene creata con `stato_setup='in_setup'`.
2. **Attesa setup** — se `in_setup`, la stazione aspetta il messaggio MQTT `ready`
   pubblicato dall'admin dopo aver completato la configurazione dal pannello.
3. **Operativa** — generato un **codice a 6 cifre** ogni `CODICE_INTERVAL` secondi e
   inviato l'heartbeat. Il codice è visibile in cima alla console.
4. L'utente **collega il cavo** → il simulatore pubblica l'evento `cavo_collegato`.
5. Laravel autorizza la ricarica e invia il comando **`START`** via MQTT.
6. Il punto avvia la sessione e pubblica la **telemetria** ogni `METER_INTERVAL` secondi.
7. La sessione termina perché arriva **`STOP`** da Laravel, oppure il **cavo viene
   scollegato** / la **batteria è piena** → il simulatore notifica il backend.

---

## 📡 Topic MQTT

I topic seguono lo schema `stazione/{mac}/{id_punto}/...` (per-punto) e
`stazione/{mac}/...` (per l'intera stazione).

### In uscita (il simulatore pubblica)
| Topic                                   | Quando                        | Contenuto                       |
|------------------------------------------|-------------------------------|----------------------------------|
| `stazione/{mac}/{id_punto}/heartbeat`    | Ogni `HEARTBEAT_INTERVAL`     | Stato del punto + timestamp      |
| `stazione/{mac}/{id_punto}/telemetria`   | Ogni `METER_INTERVAL`         | `id_sessione`, voltaggio, corrente |
| `stazione/{mac}/{id_punto}/eventi`       | Cavo collegato/scollegato…    | Tipo evento + dati sessione      |
| `stazione/{mac}/{id_punto}/codice`       | Ogni `CODICE_INTERVAL`        | Codice monouso a 6 cifre         |

### In entrata (il simulatore ascolta)
| Topic                                   | Comandi accettati                            |
|------------------------------------------|----------------------------------------------|
| `stazione/{mac}/{id_punto}/comandi`      | `START` (con `id_sessione`) · `STOP`         |
| `stazione/{mac}/ready`                   | Sblocco dopo il setup admin                  |
| `stazione/{mac}/manutenzione`            | Attiva/disattiva la manutenzione             |

---

## 📂 File

| File               | Contenuto                                                       |
|--------------------|-----------------------------------------------------------------|
| `main.py`          | Console interattiva per pilotare a mano la stazione             |
| `stazione.py`      | Classi `Stazione` e `Punto_ricarica` — tutta la logica          |
| `sessione.py`      | Dataclass `Sessione` (dati di una singola ricarica)             |
| `config.py`        | Lettura della configurazione dal file `.env`                    |
| `api_client.py`    | Chiamata REST di registrazione al backend                       |
| `requirements.txt` | Dipendenze Python (`python-dotenv`, `paho-mqtt`, `requests`)    |
| `params/`          | Un file `.env` per ogni worker (`worker-1.env` … `worker-7.env`) |

---

## 🔧 Configurazione

Ogni simulatore legge un file di parametri. In Docker sono in `params/worker-N.env`,
indicati al container dalla variabile `WORKER_ENV_FILE` (vedi `docker-compose.yaml`).

| Variabile                | Descrizione                                                |
|--------------------------|------------------------------------------------------------|
| `MAC_ADDRESS`            | Identità della stazione (MAC, es. `AA:BB:CC:DD:EE:01`)     |
| `PASSWORD_REGISTRAZIONE` | Password globale per `POST /api/iot/registra`              |
| `NUMERO_PUNTI`           | Numero di prese fisiche della stazione                     |
| `BACKEND_URL`            | URL del backend Laravel (in Docker: `http://app`)          |
| `MQTT_HOST` / `MQTT_PORT`| Indirizzo del broker Mosquitto                             |
| `METER_INTERVAL`         | Ogni quanti secondi inviare la telemetria (default 5)      |
| `HEARTBEAT_INTERVAL`     | Ogni quanti secondi inviare l'heartbeat (default 60)       |
| `CODICE_INTERVAL`        | Ogni quanti secondi generare un nuovo codice (default 60)  |

---

## ▶️ Avvio

### Con Docker (consigliato)
I 7 simulatori (`worker-1` … `worker-7`) partono insieme agli altri servizi:
```bash
docker-compose up -d
```
Per **pilotare una stazione** a mano, agganciati alla sua console:
```bash
docker attach gs-worker-1-1
```
> Per staccarti senza fermare la stazione usa `Ctrl+P` poi `Ctrl+Q` (NON `Ctrl+C`).
> Log di un worker: `docker logs -f gs-worker-1-1`.

### In locale
```bash
pip install -r requirements.txt
WORKER_ENV_FILE=params/worker-1.env python main.py
```

---

## 🎮 Console interattiva (`main.py`)

All'avvio carica la `Stazione` con tutti i punti. Si naviga con due menu:

1. **Lista punti** — mostra ogni punto con stato e cavo; si sceglie con il numero.
   In cima è sempre visibile il **codice monouso** corrente.
2. **Menu del punto** — operazioni sul punto selezionato:
   - `[1]` Collega cavo
   - `[2]` Scollega cavo
   - `[3]` Simula comando START (come se arrivasse da Laravel via MQTT)
   - `[4]` Stato del punto
   - `[5]` Termina sessione manualmente
   - `[b]` Torna alla lista · `[q]` Esci

> Nel flusso reale il comando `START` arriva da Laravel via MQTT; l'opzione `[3]` serve
> solo a iniettarlo a mano durante i test, senza passare dal backend.

Per l'architettura completa del progetto vedi il [README principale](../README.md).
