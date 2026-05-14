# 🔌 Simulatore Colonnine — Green School Project

Simulatore in **Python** delle colonnine di ricarica. Riproduce il comportamento di una
stazione fisica (con i suoi punti di ricarica) e comunica con il backend tramite il
broker **MQTT** (Mosquitto).

Serve per sviluppare e testare tutto il flusso di ricarica **senza hardware reale**.

---

## ⚙️ Come funziona

Il simulatore modella due entità:

- **`Stazione`** — il dispositivo fisico. Si connette al broker MQTT, gestisce uno o più
  punti di ricarica e invia un *heartbeat* periodico per ognuno.
- **`Punto_ricarica`** — il singolo connettore. Gestisce lo stato del cavo, l'avvio/fine
  della sessione e, durante la ricarica, simula l'erogazione di energia inviando i
  consumi (delta kWh) ogni pochi secondi.

### Flusso di una ricarica
1. L'utente **collega il cavo** al punto → il simulatore pubblica l'evento `cavo_collegato`.
2. Laravel autorizza la ricarica e invia il comando **`START`** via MQTT.
3. Il punto avvia la sessione e comincia a pubblicare la **telemetria** (consumi) ogni 5s.
4. La sessione termina perché:
   - arriva il comando **`STOP`** da Laravel, **oppure**
   - il **cavo viene scollegato** / la **batteria è piena** → il simulatore notifica il backend con l'evento di fine.

---

## 📡 Topic MQTT

Tutti i topic seguono lo schema `stazione/{id_punto}/...`.

### In uscita (il simulatore pubblica)
| Topic                              | Quando                          | Contenuto                                  |
|------------------------------------|---------------------------------|--------------------------------------------|
| `stazione/{id_punto}/heartbeat`    | Periodicamente (`retain`)       | Stato del punto + timestamp                |
| `stazione/{id_punto}/telemetria`   | Ogni `METER_INTERVAL` (5s)      | `id_sessione`, `delta_kwh`                 |
| `stazione/{id_punto}/eventi`       | Cavo collegato, fine sessione   | Tipo evento + dati sessione                |

### In entrata (il simulatore ascolta)
| Topic                              | Comandi accettati                                       |
|------------------------------------|---------------------------------------------------------|
| `stazione/{id_punto}/comandi`      | `START` (con `id_sessione`) · `STOP`                    |

---

## 📂 File

| File             | Contenuto                                                         |
|------------------|-------------------------------------------------------------------|
| `main.py`        | Console interattiva per pilotare a mano la stazione (vedi sotto)  |
| `stazione.py`    | Classi `Stazione` e `Punto_ricarica` — tutta la logica            |
| `sessione.py`    | Dataclass `Sessione` (dati di una singola ricarica)               |
| `config.py`      | Lettura della configurazione dal file `.env`                      |
| `requirements.txt` | Dipendenze Python (`python-dotenv`, `paho-mqtt`)                |
| `.env.example`   | Modello del file di configurazione                                |

---

## 🔧 Configurazione (`.env`)

Copia il modello e compila i valori:
```bash
cp .env.example .env
```

| Variabile                 | Descrizione                                                |
|---------------------------|------------------------------------------------------------|
| `ID_STAZIONE`             | Identificativo della stazione                              |
| `ID_PUNTO1`, `ID_PUNTO2`… | Id dei punti gestiti (numerati a partire da 1)             |
| `BACKEND_URL`             | URL del backend Laravel                                    |
| `MQTT_HOST` / `MQTT_PORT` | Indirizzo del broker Mosquitto (vuoti = `localhost:1883`)  |
| `MQTT_NOME_UTENTE`        | Utente MQTT della stazione                                 |
| `MQTT_PASSWORD_STAZIONE`  | Password MQTT della stazione                               |
| `METER_INTERVAL`          | Ogni quanti secondi inviare la telemetria (default 5)      |
| `HEARTBEAT_INTERVAL`      | Ogni quanti secondi inviare l'heartbeat (default 60)       |

---

## ▶️ Avvio

### Con Docker (consigliato)
Il simulatore parte insieme agli altri servizi:
```bash
docker-compose up -d
docker logs -f green_simulatore
```

### In locale
```bash
pip install -r requirements.txt
python main.py
```

---

## 🎮 Console interattiva (`main.py`)

All'avvio carica la `Stazione` con tutti i punti definiti nel `.env`. Si naviga con due menu:

1. **Lista punti** — mostra ogni punto con stato e cavo; si sceglie con il numero.
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
