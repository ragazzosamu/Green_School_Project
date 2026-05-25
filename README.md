# ⚡ Green School Project

Sistema di gestione per **stazioni di ricarica** di veicoli elettrici in ambito scolastico:
mappa delle colonnine, avvio ricarica con **codice monouso a 6 cifre** mostrato sul display,
monitoraggio in tempo reale dei consumi e gamification.

> 📖 Per il funzionamento interno (API, MQTT, WebSocket, flusso sessione, schema DB) vedi
> [`ARCHITECTURE.md`](ARCHITECTURE.md). Questo README copre **setup e operatività**.

---

## 🧩 Architettura

Il progetto gira interamente in **Docker**. I servizi che compongono lo stack:

| Servizio             | Container                    | Ruolo                                                              |
|----------------------|------------------------------|--------------------------------------------------------------------|
| `app`                | `green_app`                  | Backend **Laravel** (API REST + sito web). Porta `80`              |
| `mariadb`            | `green_db`                   | Database **MariaDB**. Porta `3306`                                 |
| `redis`              | `green_redis`                | Cache, code, sessioni e kWh in tempo reale                         |
| `queue`              | `green_queue`                | Worker Laravel per i job in coda                                   |
| `mqtt`               | `green_mqtt-broker`          | Broker **Mosquitto**: comunicazione colonnine ↔ backend. Porte `1883` (MQTT) e `9001` (MQTT su WebSocket) |
| `mqtt-worker`        | `green_mqtt_worker`          | Worker che consuma i messaggi MQTT (codice, telemetria, heartbeat, eventi) e dispatcha eventi Laravel |
| `heartbeat_checker`  | `green_heartbeat_checker`    | Watchdog: marca offline i punti che non mandano heartbeat da oltre 3 minuti |
| `reverb`             | `green_reverb`               | Server **WebSocket** verso il browser (broadcast eventi real-time). Porta `8080`, complementare a MQTT |
| `react`              | `green_react`                | Dev server **Vite** della SPA React (`frontend/`). Porta `5173`        |
| `worker-1` … `worker-7` | `gs-worker-1-1` … `gs-worker-7-1` | **Simulatori** delle colonnine di ricarica: 7 worker, uno per stazione (vedi `simulatore/`) |

```
   Browser ──HTTP──► app (Laravel) ──► MariaDB / Redis
                        │
                        ├──WebSocket──► reverb ──► Browser  (eventi live)
                        └──MQTT──► mqtt (Mosquitto) ◄──MQTT── simulatore / Arduino
                                     ▲
                                     └── mqtt-worker (Laravel) consuma e scrive su DB/Redis
```

📂 **Documentazione per componente**
- Architettura dell'app (API, MQTT, WS, DB) → [`ARCHITECTURE.md`](ARCHITECTURE.md)
- Backend Laravel → [`backend/README.md`](backend/README.md)
- Frontend React (SPA) → [`frontend/README.md`](frontend/README.md)
- Simulatore colonnine → [`simulatore/README.md`](simulatore/README.md)

---

## 📦 Prerequisiti

- [GitHub Desktop](https://desktop.github.com/) (o Git CLI)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) — deve essere in esecuzione
- [Node.js](https://nodejs.org/) — serve per compilare gli asset del frontend
- [DBeaver](https://dbeaver.io/) — opzionale, per ispezionare il database
- [MQTT Explorer](https://mqtt-explorer.com/) — opzionale, per vedere il traffico MQTT in tempo reale

---

## 📑 Documenti comuni
- **Tabella di marcia**: [Google Sheet Lavoro](https://docs.google.com/spreadsheets/d/1Zt4d4UcoLS2TqQH-RpuAC34dMfk1Hsu7lD3KwQfXs3c/edit?usp=sharing)
- **Presentazione**: [Google Presentazione](https://docs.google.com/presentation/d/1Ce5nIDGTAi5vJbSqfyW69fZ8uYClXnc4F36eB3Q0UAY/edit?usp=sharing)

---

## 🚀 Avvio del progetto

### 1. Clona la repository
```bash
git clone <url-del-repository>
cd Green_School_Project
```
(oppure usa GitHub Desktop)

### 2. Configura il file `.env` del backend
```bash
cd backend/src
cp .env.example .env
```

Le voci essenziali (già presenti nel `.env.example`):

```env
IOT_REGISTRATION_PASSWORD=greenschool-iot-2025   # password globale per /api/iot/registra
MQTT_HOST=mqtt
MQTT_PORT=1883
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
```

### 3. Configura il file `.env` del frontend (React)
Il file è in `.gitignore`, quindi va creato in locale su ogni PC:
```bash
cd frontend
cp .env.example .env
```

Le quattro variabili dentro `.env`:

```env
VITE_REVERB_APP_KEY=a350d2367d13394c34e998d87a135962   # DEVE coincidere con REVERB_APP_KEY del backend
VITE_REVERB_HOST=localhost                              # host visto dal browser
VITE_REVERB_PORT=5173                                   # porta del proxy WS Vite (vedi vite.config.js)
VITE_REVERB_SCHEME=http
```

> ⚠️ **Senza questo file i WebSocket non funzionano**: Echo apre la connessione con
> `key=undefined`, Reverb rifiuta l'handshake, e i kWh in tempo reale rimangono fermi
> al valore iniziale (sintomo tipico: "Energia erogata" non sale mai).
> Se vedi `[Echo] VITE_REVERB_APP_KEY mancante` nella Console DevTools, hai saltato questo step.

### 4. Compila gli asset Blade (Vite)
Servono al sito **Blade** (mappa, profilo, classifica…) per i CSS/JS compilati,
incluso il client Echo che gestisce gli aggiornamenti in tempo reale. La SPA React
ha una build separata gestita dal container `green_react`, qui non serve.

> ⚠️ Questi comandi vanno lanciati **sul tuo PC**, NON dentro Docker.
```bash
cd backend/src
npm install            # installa le dipendenze elencate in package.json
npm run build          # genera public/build/* (asset minificati)
```

### 5. Avvia i container
```bash
docker-compose up -d
```

### 6. Crea e popola il database
```bash
docker exec -it green_app php artisan migrate:fresh --seed
```

Il seeder crea **solo i dati di base** — niente stazioni, punti o sessioni:
- **Utente test:** `test.test@email.it` / `password123`
- **Admin:** `admin@greenschool.it` / `password123`
- Catalogo badge gamification, profilo scuola e 12 mesi di consumi

> ⚠️ **Stazioni, punti, sessioni di ricarica, XP e classifica NON vengono seedati.**
> Vanno creati a mano usando l'app (vedi il flusso operativo qui sotto). Il database
> parte volutamente "vuoto": registri le colonnine col simulatore, completi il setup
> dal pannello admin e avvii le sessioni di ricarica autonomamente. XP, punti in
> classifica e sfide settimanali si popolano man mano che usi l'app.

---

## 🌐 Punti di accesso

| Cosa              | Indirizzo                                  |
|-------------------|--------------------------------------------|
| Sito web          | [http://localhost](http://localhost)       |
| API               | `http://localhost/api`                     |
| Pannello admin    | [http://localhost/admin](http://localhost/admin) |
| Broker MQTT       | `localhost:1883`                           |
| MQTT su WebSocket | `localhost:9001`                           |
| WebSocket Reverb  | `localhost:8080` (di solito acceduto via Apache proxy, non direttamente) |

**Connessione DBeaver:**
- Tipo: `MySQL` · Host: `localhost` · Porta: `3306`
- Database: `db_green_school` · Username: `admin` · Password: `password`

---

## 🌍 Esporre il progetto via ngrok (demo da telefono o da remoto)

Per provare l'app da telefono o farla vedere a distanza serve un tunnel HTTPS verso il
tuo `localhost`. Usiamo **ngrok**: una sola porta esposta (la `80`), tutto il resto
(WebSocket Reverb compreso) passa attraverso Apache che fa da reverse proxy. Vedi anche
[ADR — Apache come reverse proxy WebSocket](docs/decisioni/2026-05-15-apache-reverse-proxy-websocket.md).

### Prerequisiti

1. Account gratuito su [ngrok.com](https://ngrok.com/) e [scarica il client](https://ngrok.com/download).
2. Una tantum, autentica il tuo ngrok con il token che ti dà il sito:
   ```bash
   ngrok config add-authtoken <il-tuo-token>
   ```

### Avvio del tunnel

I container devono essere già su (`docker-compose up -d`). Poi in un terminale:

```bash
ngrok http 80
```

ngrok stampa un URL pubblico tipo:
```
Forwarding   https://botch-survival-repaying.ngrok-free.dev -> http://localhost:80
```

Quello è l'indirizzo da aprire dal telefono o da condividere. Funzionano:
- Sito web (`/login`, `/map`, `/profilo`, ecc.)
- API (`/api/...`)
- WebSocket per gli aggiornamenti in tempo reale (passa sullo stesso URL, path `/app/`)

### Cose da sapere

- **L'URL cambia ogni volta** che riavvii `ngrok` (sul piano free). Se devi distribuirlo,
  ripassalo a chi serve dopo ogni riavvio.
- **Il login da remoto** funziona perché il backend si fida dei proxy esterni
  (`trustProxies` in `bootstrap/app.php`) e legge `X-Forwarded-Proto: https` per gestire
  i cookie in modo coerente.
- **Il WebSocket** non richiede configurazione aggiuntiva: il JavaScript delle viste
  legge l'host della pagina dinamicamente (`window.location.hostname`) e Apache fa il
  tunneling verso Reverb via `mod_proxy_wstunnel`.
- **`ngrok free` permette un solo tunnel**: non serve aprirne uno separato per la porta
  `8080` di Reverb, è proprio il punto del reverse proxy.

---

## 📍 Dati di esempio: scuole di Castelfranco Veneto

Dopo `migrate:fresh --seed` il database **non contiene nessuna stazione**: le devi
inserire tu. Quando registri una colonnina e completi il setup dal pannello admin
(step 4 del flusso operativo) ti vengono chiesti **nome, indirizzo, latitudine e
longitudine**. Qui sotto le scuole superiori di Castelfranco Veneto con le coordinate
già pronte da copiare — usale come stazioni di esempio così la mappa ha senso.

| Scuola | Indirizzo | Latitudine | Longitudine |
|--------|-----------|------------|-------------|
| Liceo "Giorgione" | Via Giuseppe Verdi 25 | `45.67093060` | `11.93940310` |
| I.T.T. "G.B. Martini" | Via Giuseppe Verdi 40 | `45.67082920` | `11.94357910` |
| Liceo / I.P. "Florence Nightingale" | Via Giuseppe Verdi 60 | `45.67054850` | `11.94262750` |
| I.T.I. "Eugenio Barsanti" | Via dei Carpani 19/B | `45.68190760` | `11.93726270` |
| I.I.S. Agrario "D. Sartor" | Via Postioma di Salvarosa 28 | `45.69173640` | `11.94681220` |
| I.S. "C. Rosselli" (Liceo Artistico) | Via G. Rizzetti 10 | `45.66807400` | `11.92694150` |
| I.P.S.S.E.O.A. "G. Maffioli" | Via Valsugana 74 | `45.68053480` | `11.90364420` |
| I.P.S.I.A. "G. Galilei" | Via Avenale 6 | `45.68037580` | `11.92520950` |

> Coordinate geocodificate da OpenStreetMap (Nominatim). Sono nel formato richiesto
> dal DB: `latitudine` `DECIMAL(10,8)`, `longitudine` `DECIMAL(11,8)`.

---

## 🔋 Avviare una ricarica end-to-end (flusso operativo)

> ⚠️ **Il database parte vuoto.** Non ci sono stazioni, punti né sessioni: vanno
> create a mano. Questo flusso parte da zero — registri la colonnina, completi il
> setup come admin e avvii la sessione. XP e classifica si popolano di conseguenza.

Tutorial passo-passo per provare l'intero flusso di una ricarica con il nuovo **codice
monouso** (al posto del vecchio QR). Funziona identico da **localhost** o da **ngrok**.

### 1. Apri il sito e fai login

Vai su [http://localhost](http://localhost) (oppure URL ngrok). Login con:
- **Email**: `test.test@email.it`
- **Password**: `password123`

> 💡 Se l'utente non esiste, hai saltato il `php artisan migrate:fresh --seed`.

### 2. Mappa: stazioni in attesa di vita

Vai su `/map`. La stazione TEST appare grigia/offline perché nessuna colonnina sta
mandando heartbeat. Serve avviare il simulatore.

### 3. Avvia il simulatore colonnine

I 7 simulatori (`worker-1` … `worker-7`) **partono già** con `docker-compose up -d`:
ognuno esegue `python main.py` e legge i suoi parametri da `simulatore/params/worker-N.env`.

Per pilotare una stazione a mano, agganciati alla sua console interattiva:

```bash
docker attach gs-worker-1-1
```

> Per staccarti **senza fermare** la stazione usa la sequenza `Ctrl+P` poi `Ctrl+Q`
> (NON `Ctrl+C`, che ucciderebbe il processo). Puoi anche avviare solo i worker che
> ti servono: `docker compose up -d worker-1 worker-3`.

Ogni simulatore:
1. Chiama `POST /api/iot/registra` con `MAC + PASSWORD_REGISTRAZIONE + NUMERO_PUNTI`
2. Se è la prima volta, la stazione viene creata su DB con `stato_setup='in_setup'`
3. Resta in attesa di un messaggio MQTT `ready` dall'admin

Se è la stazione TEST già seedata come `attiva`, il sim parte subito a inviare heartbeat
e a generare codici. Vedrai un menu interattivo con il **codice monouso a 6 cifre** in
cima, valido per qualsiasi punto della stazione. Il codice cambia automaticamente ogni
60 secondi (TTL Redis 60s).

```
========================================================
  STAZIONE  AA:BB:CC:DD:EE:FF
  CODICE    482931    (valido per qualsiasi punto)
========================================================
  [1] punto 1  [    ]  LIBERA    libero
  [2] punto 2  [    ]  LIBERA    libero
  [q] Esci
```

### 4. (Solo per stazioni NUOVE) Completa setup dal pannello admin

Se hai registrato una colonnina nuova (MAC mai visto), il sim attende il `ready`. Dal
browser:

1. Vai su `/admin` e fai login come admin
2. `/admin/stazioni` — la nuova stazione appare col badge "In setup"
3. Click su **Completa setup** → compila nome, indirizzo, lat/lng e per ogni punto:
   tipo veicolo, connettore, potenza. Per nome/indirizzo/coordinate puoi copiare
   una delle scuole nella tabella [📍 Dati di esempio](#-dati-di-esempio-scuole-di-castelfranco-veneto).
4. **Salva e attiva** → Laravel pubblica MQTT `stazione/{mac}/ready` e il sim parte

> Il numero di punti e i loro ID (1, 2, …) **non sono modificabili dall'admin**: li
> dichiara la colonnina al momento della registrazione. L'admin compila solo i metadati.

### 5. Avvia la sessione lato utente

Dal sito utente:
1. `/map` → click sul pallino verde della stazione → "Vai al dettaglio"
2. Click su una presa libera → "Scegli"
3. Si apre il form **"Inserisci codice monouso"**
4. Leggi il codice dal display del simulatore (es. `482931`) e digitalo
5. Backend valida → risposta `202` → redirect a `/profilo?attesa=…` con banner giallo
   **"In attesa del cavo"** e countdown 60s

### 6. Simula il collegamento del cavo

Torna al simulatore:
1. Premi il **numero del punto** (es. `1`) → entri nel sotto-menu
2. Premi **`1`** → "Collega cavo"

Il sim pubblica `cavo_collegato` su MQTT. Il `mqtt-worker` consuma il pending Redis,
chiama `sp_avvio_sessione`, dispatcha `SessioneAvviata` via WebSocket e pubblica `START`
verso il punto. Il sim avvia il thread di telemetria.

### 7. La ricarica è partita

Sul sito il banner diventa verde "Sessione di ricarica in corso" e i kWh salgono ogni
5 secondi. Click su "Vai alla sessione →" per il dettaglio.

Per terminare:
- Nel sito click su **"Termina ricarica"**, oppure
- Nel simulatore scollega il cavo (`2` dentro al punto) o termina manualmente (`5`)

---

## 🛠️ Comandi utili

### Reset DB e seed
```bash
docker exec -it green_app php artisan migrate:fresh --seed
```

### Pulire la cache di Laravel
```bash
docker exec -it green_app php artisan optimize:clear
# oppure singolarmente:
docker exec -it green_app php artisan config:clear
docker exec -it green_app php artisan cache:clear
docker exec -it green_app php artisan route:clear
docker exec -it green_app php artisan view:clear
```

### Composer (dopo modifica `composer.json` o spostamento file)
```bash
docker exec -it green_app composer install
docker exec -it green_app composer dump-autoload -o
```

### Apri Redis CLI (controlla codici / pending / kWh live)
```bash
docker exec -it green_redis redis-cli
```
Chiavi utili:
```
KEYS codice:*                 # codici monouso attivi (chiave: codice:{mac})
KEYS codice_pending:*         # prenotazioni dopo /api/{id_stazione}/verifica-codice
KEYS sessione_kwh:*           # kWh accumulati delle sessioni in corso
GET  codice:AA:BB:CC:DD:EE:FF
TTL  codice:AA:BB:CC:DD:EE:FF
```

### Apri MariaDB CLI
```bash
docker exec -it green_db mariadb -u admin -ppassword db_green_school
```
Query utili:
```sql
SELECT id_stazione, stato_setup, in_manutenzione, data_ultimo_heartbeat FROM stazioni;
SELECT id_stazione, id_punto, stato_hardware, libera, data_ultimo_heartbeat FROM punti_ricarica;
SELECT * FROM sessioni_ricarica WHERE data_fine IS NULL;
```

### Gestione container
```bash
docker-compose stop          # Ferma i container (senza eliminarli)
docker-compose down          # Spegne e rimuove i container (immagini e volumi restano)
docker-compose down --rmi all # Spegne e rimuove anche le IMMAGINI
docker-compose up -d          # Avvia (riusa le immagini esistenti, NON ricostruisce)
docker-compose up -d --build # Avvia RICOSTRUENDO le immagini (rifà pip install / composer install)
docker compose restart worker-1 worker-2 worker-3 worker-4 worker-5 worker-6 worker-7 # Riavvia i 7 simulatori colonnine
docker logs -f green_app          # Log PHP in tempo reale
docker logs -f green_mqtt_worker  # Worker MQTT: vede ogni messaggio in arrivo
docker logs -f gs-worker-1-1      # Log di un simulatore (worker-1)
```

> 💡 **Quando serve `--build`?** Il codice (`.py`, `.php`) è montato come volume, quindi
> le modifiche si vedono subito senza ricostruire. Ma le **dipendenze** (`requirements.txt`,
> `composer.json`) sono installate *dentro l'immagine*: se le cambi devi rifare il build,
> altrimenti Docker riusa l'immagine vecchia con le dipendenze vecchie.

### Far ripartire tutto da capo (reset completo)
> ⚠️ Cancella i container, i volumi e i dati del database. Da usare quando qualcosa
> è "incastrato" e vuoi ripartire pulito.
```bash
docker-compose down -v               # Spegne e rimuove container + volumi
docker-compose up -d --build         # Ricostruisce e riavvia tutto
docker exec -it green_app php artisan migrate:fresh --seed   # Ricrea il database
```
Se vuoi azzerare **anche le immagini** (es. dopo aver cambiato `requirements.txt` /
`composer.json` o se l'immagine è corrotta):
```bash
docker-compose down -v --rmi all     # Rimuove container + volumi + immagini del progetto
docker-compose up -d --build         # Ricostruisce tutto da zero
docker exec -it green_app php artisan migrate:fresh --seed
```
Per liberare spazio in modo aggressivo (rimuove anche immagini/cache di altri progetti):
```bash
docker-compose down -v
docker system prune -af              # Rimuove immagini, build cache e roba inutilizzata
docker-compose up -d --build
docker exec -it green_app php artisan migrate:fresh --seed
```

### Ispezionare MQTT
```bash
# Vede tutti i messaggi che passano sul broker
docker exec -it green_mqtt-broker mosquitto_sub -t 'stazione/#' -v
```
Oppure usa **MQTT Explorer** connettendoti a `localhost:1883`.

---

## 🧪 Guida ai Test API con Postman

Il file [`Green_School_Project.postman_collection.json`](Green_School_Project.postman_collection.json)
contiene **tutte le rotte** (auth, stations, session, school, gamification, admin, IoT,
broadcasting, health) divise in **cartelle per area**, con variabili `{{api}}` /
`{{token}}` / `{{id_*}}` che vengono iniettate automaticamente.

### 1. Setup ambiente (solo la prima volta)
1. In alto a destra, clicca su **Environments**.
2. Crea un nuovo ambiente con il tasto **+** e chiamalo `Sviluppo Locale`.
3. Aggiungi la variabile `api` con **Initial Value** = `http://localhost/api`.
4. Salva, poi seleziona `Sviluppo Locale` dal menu a tendina in alto a destra.

> La collezione include comunque un default `api = http://localhost/api` a livello
> collezione, quindi puoi anche saltare il punto 3 se ti basta lo sviluppo locale.

### 2. Importazione collezione
1. Su Postman, clicca **Import** e trascina `Green_School_Project.postman_collection.json`.
2. Se la avevi già, scegli **Replace**.
3. Gli URL sono scritti come `{{api}}/NOME_ROTTA`: Postman sostituisce `{{api}}` da solo.

### 3. Autenticazione — come funziona

Il backend ha **3 contesti di autenticazione** (riassunto; dettaglio nel
[backend README → Autenticazione](backend/README.md#-autenticazione)):

| Chi          | Come si autentica                          | Header / cookie                    |
|--------------|--------------------------------------------|------------------------------------|
| Browser Blade | Form `/login` → cookie di sessione         | `Cookie: green_school_session=…`   |
| React / Postman / Python | `POST /api/login` → token Sanctum | `Authorization: Bearer <token>`    |
| Colonnina IoT | Password globale + MAC, una sola volta     | body `mac=… password=… numero_punti=…` |

Per Postman ti serve solo il **Bearer token Sanctum**:

1. Apri **Auth → Login**. Se servono altre credenziali, modifica il body:
   ```json
   { "email": "test.test@email.it", "password": "password123" }
   ```
2. **Send**. La risposta è del tipo:
   ```json
   { "access_token": "7|AbCd…XyZ", "token_type": "Bearer",
     "user": { "id_utente": "…", "email": "…", "ruolo": "utente", … } }
   ```
3. Uno **script di test** sul tab "Tests" della richiesta Login estrae
   `access_token` e `user.id_utente` e li salva come variabili `{{token}}` e
   `{{id_utente}}` (ambiente + collezione). Tutte le request successive le riusano
   in automatico.
4. Le **rotte protette** (stations, session, school, gamification, admin) hanno
   l'auth a livello di collezione: prendono `{{token}}` da sole. Le **rotte pubbliche**
   (`login`, `register`, `iot/registra`, `health`) hanno `"auth": { "type": "noauth" }`
   esplicito così non rischi di mandarci dentro un token sbagliato.
5. Per **disautenticarti**: **Auth → Logout** (`POST /api/logout`). Revoca **solo**
   il token corrente; eventuali altri token dello stesso utente restano validi.

> ⚠️ **Token persistente.** Sanctum non scade automaticamente: una volta ottenuto,
> il token vale finché non chiami `/logout` o non lo cancelli dalla tabella
> `personal_access_tokens`. Se rifai login senza logout, ottieni un secondo token e
> il primo resta attivo.

**Lockout brute force**: dopo 5 login falliti consecutivi dello stesso utente, il
backend risponde **HTTP 423 Locked** per 15 minuti. Il messaggio nel body include i
minuti residui (calcolati lato server, non c'è header `Retry-After`). Nota:
il contatore tentativi viene incrementato solo se l'email esiste — login con email
inesistente non fa scattare il lockout (anti enumeration). Se sei rimasto bloccato in
test, sbloccati via Tinker:
```bash
docker exec -it green_app php artisan tinker
> App\Models\Utenti::where('email','test.test@email.it')
    ->update(['login_tentativi'=>0,'login_bloccato_fino'=>null])
```

**Admin in Postman**: serve un utente con `ruolo='admin'`. Le credenziali di default
sono `admin@greenschool.it` / `password123` (seed). Dopo login con quell'utente,
`{{token}}` apre anche le rotte sotto `/api/admin/*`.

### 4. Struttura cartelle

| Cartella | Cosa contiene |
|---|---|
| **Auth** | `POST /login`, `POST /register`, `POST /logout` |
| **Stations** | `GET /stations`, `GET /station/{id}` |
| **Session** | flusso ricarica: `POST /{id_stazione}/verifica-codice`, `GET /me/sessione-attiva`, `GET /me/attesa-cavo`, `GET /session/{id}`, `POST /session/{id}/stop` |
| **School** | `GET /school/profile`, `GET /school/consumption?anno=YYYY` |
| **Gamification** | `profile`, `badges`, `leaderboard`, `sessioni`, `sfide` |
| **Admin** | dashboard, utenti (CRUD + toggle/reset), sessioni, stazioni (toggle/setup), report CSV — protette da `auth:sanctum + admin.api` |
| **IoT** | `POST /iot/registra` (registrazione colonnina, pubblico con password globale) |
| **Realtime** | `POST /broadcasting/auth` per debug handshake canali privati (vedi sotto) |
| **Health** | `GET /health` pubblico |

### 5. Variabili di percorso (path params)

Le rotte tipo `GET /station/{id}` usano placeholder `{{id_stazione}}`,
`{{id_utente}}`, `{{id_sessione}}`. Settale al volo nel pannello **Variables** della
collezione (o nell'environment) e tutte le request che le usano si aggiornano insieme.

### 6. Testare l'handshake WebSocket (canali privati)

I dati live (kWh durante la ricarica, eventi sessione) viaggiano via **Reverb** su
canali privati `user.{id_utente}`. Echo prima di sottoscriversi chiama l'endpoint
di autorizzazione `POST /api/broadcasting/auth` (vedi [ARCHITECTURE.md sezione C](ARCHITECTURE.md#c-websocket-reverb-broadcast-verso-il-browser)).

Per simulare l'handshake da Postman:
1. Esegui **Auth → Login** per ottenere il token.
2. Apri **Realtime → POST /broadcasting/auth**.
3. Il body urlencoded contiene `socket_id` (valore di esempio, in realtà lo genera
   Reverb) e `channel_name=private-user.{{id_utente}}`. Setta `{{id_utente}}`.
4. Clicca **Send**. Risposta attesa: `{"auth": "<chiave>:<hmac>"}` (200).
5. Se ricevi 403, la closure in [`channels.php`](backend/src/routes/channels.php) ha
   negato l'autorizzazione perché stai chiedendo il canale di un altro utente.
6. Se ricevi 404, il route non è caricato: `docker compose exec app php artisan route:list --path=broadcasting` per controllare.

Nota: in produzione l'endpoint non si chiama mai a mano, lo invoca Echo dal browser.
Questa request serve solo per debugging del flusso di auth.

---

## 🐛 Troubleshooting

| Sintomo | Causa probabile | Fix |
|---|---|---|
| `Connection refused` lato sim | `BACKEND_URL=http://localhost` in container | usa `http://app` |
| `Password registrazione non valida` 401 | `IOT_REGISTRATION_PASSWORD` ≠ `PASSWORD_REGISTRAZIONE` | allinea i due `.env` |
| Sim resta su "in attesa di ready" | Admin non ha completato setup | `/admin/stazioni` → **Completa setup** |
| `Codice non valido o scaduto` 422 | TTL Redis scaduto (>60s) o codice mai pubblicato | `KEYS codice:*` in Redis; controlla `mqtt-worker` |
| Sessione non si avvia dopo cavo_collegato | `sp_verifica_disponibilita` blocca (heartbeat null o stato_hardware ≠ online) | `SELECT data_ultimo_heartbeat, stato_hardware FROM punti_ricarica` |
| Eventi WebSocket non arrivano al browser | reverb spento o canale sbagliato | `docker logs green_reverb`, verifica che il MAC nel canale sia senza `:` |
| `Failed to open stream … Controller.php` | classmap Composer stale | `composer dump-autoload -o` |
| Container vede vecchio codice | Docker volume non sincronizzato | `docker restart green_app` o `docker compose down/up` |

---

## 🔄 Regole del team — ad ogni modifica delle API

Se modifichi un controller o aggiungi una rotta su Laravel:

1. **Aggiorna Postman**: crea/modifica la richiesta nel tuo Postman locale.
2. **Esporta il JSON**: tre puntini `...` sulla collezione → **Export**, sovrascrivi il file nella repo.
3. **Commit & Push**: carica il JSON insieme al codice PHP.
4. **Segnala** sul gruppo: *"Nuova rotta: [nome]. Fate pull e re-importate il JSON!"*.
5. **Ricezione**: i compagni fanno `git pull` e re-importano il file (l'ambiente `Sviluppo Locale` non va toccato).
