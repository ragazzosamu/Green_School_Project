# Green School Project

Sistema di ricarica veicoli elettrici (auto, bici, monopattini) per ambito scolastico:
mappa colonnine, **avvio ricarica con codice monouso a 6 cifre**, monitoraggio in
tempo reale, pannello admin, gamification.

> Questo README copre **setup, comandi e troubleshooting**.
> Per il funzionamento interno (API, MQTT, WebSocket, flusso sessione, DB),
> vedi [`ARCHITECTURE.md`](ARCHITECTURE.md).

---

## Servizi Docker

Stack definito in [`docker-compose.yaml`](docker-compose.yaml):

| Servizio              | Container                 | Porta host | Ruolo |
|-----------------------|---------------------------|------------|-------|
| `app`                 | `green_app`               | `80`       | Backend Laravel (web + API). |
| `mariadb`             | `green_db`                | `3306`     | Database. |
| `redis`               | `green_redis`             | `6379`     | Cache + sessioni + kWh in tempo reale. |
| `queue`               | `green_queue`             | —          | Worker Laravel queue. |
| `mqtt`                | `green_mqtt-broker`       | `1883`, `9001` | Broker Mosquitto, anonimo. |
| `mqtt-worker`         | `green_mqtt_worker`       | —          | Consuma MQTT (codice, telemetria, heartbeat, eventi) e dispatcha eventi Laravel. |
| `heartbeat_checker`   | `green_heartbeat_checker` | —          | Watchdog: marca offline i punti senza heartbeat. |
| `reverb`              | `green_reverb`            | `8080`     | WebSocket → browser (broadcast eventi real-time). |
| `python`              | `green_simulatore`        | —          | Simulatore colonnine (CLI interattiva). |

```
Browser ──HTTP──► app (Laravel) ──► MariaDB / Redis
                       │
                       ├──WebSocket──► reverb ──► Browser  (eventi live)
                       └──MQTT──► mqtt-broker ◄──MQTT── simulatore / Arduino
                                     ▲
                                     └── mqtt-worker (Laravel) consuma e scrive su DB/Redis
```

---

## Setup iniziale

```bash
# 1. Configurazione env
cp backend/src/.env.example backend/src/.env
cp simulatore/.env.example  simulatore/.env

# 2. Build + avvio
docker compose build
docker compose up -d

# 3. Migrazioni + seed (TEST station + utente test + factory stations)
docker exec green_app composer install
docker exec green_app php artisan key:generate
docker exec green_app php artisan migrate:fresh --seed
```

Apri http://localhost — di default trovi:
- **Utente test:** `test.test@email.it` / `password123`
- **Admin:** creato dal `AdminSeeder` (controlla il file per email/password).
- **Stazione TEST attiva** con MAC `AA:BB:CC:DD:EE:FF` e 2 punti.
- **5 stazioni factory** + sessioni storiche.

---

## File `.env` essenziali

### Backend (`backend/src/.env`)

Le voci che riguardano la migrazione codice monouso:

```env
IOT_REGISTRATION_PASSWORD=greenschool-iot-2025   # uguale a quella del simulatore
MQTT_HOST=mqtt
MQTT_PORT=1883
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
```

### Simulatore (`simulatore/.env`)

```env
MAC_ADDRESS=AA:BB:CC:DD:EE:FF                    # id della colonnina nel DB
PASSWORD_REGISTRAZIONE=greenschool-iot-2025      # = IOT_REGISTRATION_PASSWORD del backend
NUMERO_PUNTI=2                                   # numero di prese fisiche
BACKEND_URL=http://app                           # service name docker (NON localhost!)
MQTT_HOST=green_mqtt-broker
MQTT_PORT=1883
METER_INTERVAL=5
HEARTBEAT_INTERVAL=60
CODICE_INTERVAL=30                               # nuovo codice ogni 30s (TTL Redis: 60s)
```

> Se lanci il simulatore dal tuo host (fuori da Docker), usa `BACKEND_URL=http://localhost`
> e `MQTT_HOST=localhost`.

---

## Comandi quotidiani

### Avviare il simulatore

```bash
docker exec -it green_simulatore python main.py
```

Output atteso:
```
[REG] Stazione AA:BB:CC:DD:EE:FF registrata. stato_setup=attiva.
[MQTT] Connesso a green_mqtt-broker:1883.
[READY] Stazione attivata con punti ['1', '2'].

========================================================
  STAZIONE  AA:BB:CC:DD:EE:FF
  CODICE    482931    (valido per qualsiasi punto)
========================================================
  [1] punto 1  [    ]  LIBERA    libero
  [2] punto 2  [    ]  LIBERA    libero
  [q] Esci
```

Comandi del menu:
- numero → entra nel punto
- dentro al punto: `1` collega cavo, `2` scollega, `4` stato, `5` termina, `b` indietro, `q` esci

Il codice in cima si aggiorna ogni 30s (in background). Per vederlo aggiornato premi `b` per
rifare il menu o rientra nella lista punti.

### Log e debug

```bash
docker logs -f green_mqtt_worker         # vede ogni messaggio MQTT in arrivo
docker logs -f green_app                 # log Laravel (API, broadcast)
docker logs -f green_simulatore          # output del simulatore
docker exec green_app php artisan pail   # tail live dei log Laravel
```

### Reset DB

```bash
docker exec green_app php artisan migrate:fresh --seed
```

### Pulizia cache Laravel

```bash
docker exec green_app php artisan optimize:clear
```

### Composer (dopo modifica `composer.json` o spostamento file)

```bash
docker exec green_app composer dump-autoload -o
docker exec green_app composer install
```

### Apri MariaDB CLI

```bash
docker exec -it green_db mariadb -u admin -ppassword db_green_school
```

Query utili:
```sql
SELECT id_stazione, stato_setup, in_manutenzione FROM stazioni;
SELECT id_stazione, id_punto, stato_hardware, libera, data_ultimo_heartbeat FROM punti_ricarica;
SELECT * FROM sessioni_ricarica WHERE data_fine IS NULL;
```

### Apri Redis CLI

```bash
docker exec -it green_redis redis-cli
```

Chiavi tipiche:
```
KEYS codice:*                # codici monouso attivi
KEYS codice_pending:*        # prenotazioni dopo /api/verifica-codice
KEYS sessione_kwh:*          # kWh accumulati delle sessioni in corso
GET codice:AA:BB:CC:DD:EE:FF
```

---

## Flusso operativo (overview)

1. **Una nuova colonnina si registra:** `docker exec -it green_simulatore python main.py` (o lo stesso codice su Arduino).
   - Il simulatore manda `POST /api/iot/registra { mac, password, numero_punti }`.
   - Backend crea `stazioni` con `stato_setup='in_setup'` + N record in `punti_ricarica`.
2. **L'admin completa il setup:** apre `/admin/stazioni` → vede la stazione marcata "In setup" → clicca **Completa setup** → compila nome, coordinate, tipo veicolo / connettore / potenza per ogni punto → **Salva e attiva**.
   - Backend porta `stato_setup='attiva'` e pubblica `stazione/{mac}/ready` via MQTT.
3. **Il simulatore riceve `ready`** e parte a generare un codice ogni 30s sul topic `stazione/{mac}/codice`. Il mqtt-worker lo salva su Redis (`codice:{mac}`, TTL 60s).
4. **L'utente apre la mappa** (http://localhost), seleziona una stazione attiva, clicca su un punto libero → vede il form **"Inserisci codice monouso"**.
5. **L'utente legge il codice dal display della colonnina** (nel sim: lo trova in cima al menu) e lo digita nell'app → `POST /api/verifica-codice`.
6. **Backend verifica**, prenota la stazione (SETNX `codice_pending:{mac}`, TTL 60s), pubblica `stazione/{mac}/comandi { autenticazione_completata }` per l'hardware → risposta `202` → frontend va su `/profilo`.
7. **L'utente collega il cavo** su uno qualsiasi dei punti della stazione → il simulatore pubblica `stazione/{mac}/{id_punto}/eventi { cavo_collegato }`.
8. **Il mqtt-worker** consuma il pending, chiama `sp_avvio_sessione`, crea la riga in `sessioni_ricarica`, pubblica `stazione/{mac}/{id_punto}/comandi { START, id_sessione }`.
9. **Il simulatore riceve `START`**, avvia il thread di telemetria che pubblica V/I ogni 5s su `stazione/{mac}/{id_punto}/telemetria`. Il backend accumula kWh in Redis (`sessione_kwh:{id}`).
10. **L'utente vede il kWh live** sul profilo (WebSocket `user.{id_utente}` evento `ricarica.heartbeat`).
11. **Termine sessione:** l'utente scollega il cavo → `cavo_scollegato` → `sp_termina_sessione` chiude la riga DB, scrive `quantita_kwh` finale, calcola costo, aggiorna gamification.

Tutti i dettagli (endpoints, payload, middleware, topic, schema DB, broadcast) sono in [`ARCHITECTURE.md`](ARCHITECTURE.md).

---

## Troubleshooting

| Sintomo | Causa probabile | Fix |
|---|---|---|
| `Errore di rete: Connection refused` lato sim | `BACKEND_URL=http://localhost` in container | usa `http://app` |
| `Password registrazione non valida` 401 | `IOT_REGISTRATION_PASSWORD` ≠ `PASSWORD_REGISTRAZIONE` | allinea i due `.env` |
| Sim resta su "in attesa di ready" | Admin non ha completato setup | vai su `/admin/stazioni`, clicca **Completa setup** |
| `Codice non valido o scaduto` 422 | TTL Redis scaduto (>60s) o codice mai pubblicato | verifica `KEYS codice:*` in Redis; controlla che il mqtt-worker veda il codice nei log |
| Sessione non si avvia dopo `cavo_collegato` | SP `sp_verifica_disponibilita` blocca (heartbeat null o stato_hardware ≠ online) | controlla `SELECT data_ultimo_heartbeat, stato_hardware FROM punti_ricarica` |
| `Duplicate entry '…-1' for key 'PRIMARY'` su seed | seeder vecchio cached nel container | `composer dump-autoload -o` + `optimize:clear` |
| Eventi WebSocket non arrivano al frontend | `:` nei nomi canale (non validi Pusher/Reverb) | gli eventi normalizzano già il MAC; verifica che reverb sia su |
| `Failed to open stream … Controller.php` | classmap Composer stale | `composer dump-autoload -o` |
| Container vede vecchio codice | Docker volume non riflette host | `docker restart green_app` o `docker compose down/up` |

---

## Layout repo

```
.
├── README.md                          ← questo file
├── ARCHITECTURE.md                    ← spiegazione dell'app
├── docker-compose.yaml
├── backend/                           ← Laravel
│   ├── Dockerfile
│   └── src/
│       ├── app/
│       │   ├── Console/Commands/      ← MqttWorker, HeartbeatChecker
│       │   ├── Events/                ← eventi broadcast WebSocket
│       │   ├── Http/Controllers/
│       │   │   ├── AdminController.php          ← pannello admin
│       │   │   ├── Api/IotController.php        ← /api/iot/registra
│       │   │   ├── Api/SessionController.php    ← /api/verifica-codice
│       │   │   ├── Api/StationController.php    ← /api/stations
│       │   │   └── …
│       │   ├── Models/
│       │   └── Services/              ← CodiceMonousoService, SessioneService, MqttService, GamificationService
│       ├── database/
│       │   ├── migrations/            ← schema DB + stored procedure
│       │   └── seeders/
│       ├── resources/views/           ← Blade
│       └── routes/                    ← api.php / web.php
├── simulatore/                        ← Python CLI
│   ├── main.py
│   ├── stazione.py
│   ├── sessione.py
│   ├── api_client.py
│   └── config.py
├── mqtt/                              ← config Mosquitto
└── postman/                           ← collezione Postman
```
