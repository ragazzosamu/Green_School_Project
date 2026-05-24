# 🖥️ Backend — Green School Project

Backend del progetto, scritto in **Laravel 11**. È il cervello del sistema: espone le
**API REST**, gestisce il **database**, l'autenticazione, la comunicazione con le
colonnine (via **MQTT**) e gli aggiornamenti in tempo reale verso il browser (via
**WebSocket Reverb**).

> Il codice Laravel vero e proprio è nella sottocartella [`src/`](src/).
> Per l'architettura completa (MQTT, WebSocket, Redis, flussi, schema DB) vedi
> [`ARCHITECTURE.md`](../ARCHITECTURE.md).

---

## Indice

1. [Cosa fa il backend](#-cosa-fa-il-backend)
2. [Processi (container)](#-processi-container)
3. [API REST — tutte le rotte](#-api-rest--tutte-le-rotte)
4. [Autenticazione](#-autenticazione)
5. [Flusso completo di una ricarica (lato API)](#-flusso-completo-di-una-ricarica-lato-api)
6. [Controller — uno per uno](#-controller--uno-per-uno)
7. [Models — uno per uno](#-models--uno-per-uno)
8. [Services — logica di dominio](#-services--logica-di-dominio)
9. [Console Commands — worker permanenti](#-console-commands--worker-permanenti)
10. [Events — broadcast WebSocket](#-events--broadcast-websocket)
11. [Middleware](#-middleware)
12. [Routes, channels, broadcasting](#-routes-channels-broadcasting)
13. [Database, seeders, stored procedure](#-database-seeders-stored-procedure)
14. [Concetti chiave](#-concetti-chiave)
15. [Comandi artisan utili](#-comandi-artisan-utili)

---

## 🎯 Cosa fa il backend

- **Autenticazione utenti** via Laravel Sanctum (token Bearer) e sessione web.
- **Mappa** delle stazioni e punti di ricarica con il loro stato.
- **Avvio sessione di ricarica** col **codice monouso a 6 cifre** mostrato dalla
  colonnina (non più QR): l'utente lo digita, il backend lo verifica, attende
  che colleghi il cavo entro 60s (rendez-vous su Redis).
- **Monitoraggio kWh in tempo reale** via WebSocket privato.
- **Comunicazione IoT via MQTT**: registrazione colonnine, heartbeat, telemetria, eventi.
- **Gamification**: XP, livelli, badge, classifica, sfide settimanali.
- **Pannello admin**: setup stazioni, manutenzione, gestione utenti, report CSV.
- **Profilo scuola e consumi**: dati energetici, CO₂, fattori di efficientamento.

---

## 🧩 Processi (container)

Lo stesso codice Laravel gira in più container con ruoli diversi:

| Container                  | Comando                            | Ruolo                                            |
|----------------------------|------------------------------------|--------------------------------------------------|
| `green_app`                | Apache + PHP                       | API REST + sito web (HTTP)                       |
| `green_reverb`             | `artisan reverb:start`             | Server WebSocket (aggiornamenti live al browser) |
| `green_queue`              | `artisan queue:work`               | Worker dei job in coda                           |
| `green_mqtt_worker`        | `artisan mqtt:leggi`               | Consuma i messaggi MQTT dalle colonnine          |
| `green_heartbeat_checker`  | `artisan app:heartbeat-checker`    | Watchdog: marca offline i punti silenti          |

---

## 🔌 API REST — tutte le rotte

Tutte le rotte sono sotto `/api` (vedi [`src/routes/api.php`](src/routes/api.php)).
La collezione Postman pronta all'uso sta nella root: [`Green_School_Project.postman_collection.json`](../Green_School_Project.postman_collection.json).

### Pubbliche (nessun token)

| Metodo | Rotta | Descrizione |
|---|---|---|
| `GET`  | `/health`       | Healthcheck (usato da Docker per liveness probe) |
| `POST` | `/login`        | Login utente. Risponde `access_token` (Sanctum). Lockout 5 tentativi → 15 min. |
| `POST` | `/register`     | Registrazione nuovo utente (auto-login con token) |
| `POST` | `/iot/registra` | Registrazione colonnina (MAC + password globale IoT) |

### Protette (header `Authorization: Bearer <token>`)

**Stazioni / mappa**
| Metodo | Rotta | Risponde |
|---|---|---|
| `GET` | `/stations` | Lista stazioni `attiva` con `punti_ricarica[]` (per la mappa) |
| `GET` | `/station/{id}` | Dettaglio singola stazione con tutti i punti |

**Sessione di ricarica**
| Metodo | Rotta | Cosa fa |
|---|---|---|
| `POST` | `/{id_stazione}/verifica-codice` | Verifica codice 6 cifre **per quella stazione** → memorizza rendez-vous su Redis (60s). Risposta 202. L'`id_stazione` (MAC) è nel path: il client lo conosce perché ha aperto il dettaglio della stazione. |
| `GET`  | `/me/sessione-attiva` | Sessione attualmente attiva dell'utente (con kWh live da Redis). |
| `GET`  | `/me/attesa-cavo` | Secondi residui del rendez-vous codice→cavo per il banner UI. |
| `GET`  | `/session/{id}` | Stato di una sessione (kWh attuali, durata). |
| `POST` | `/session/{id}/stop` | Interrompe la sessione in corso (manda STOP via MQTT). |

**Scuola**
| Metodo | Rotta | Risponde |
|---|---|---|
| `GET` | `/school/profile`     | Profilo edificio (denominazione, classe energetica, FV, interventi…) |
| `GET` | `/school/consumption?anno=YYYY` | Consumi mensili (elettrico, termico, FV, CO₂) |

**Gamification**
| Metodo | Rotta | Risponde |
|---|---|---|
| `GET` | `/gamification/profile`     | XP totali, livello, soglia prossimo livello, streak, CO₂, sessioni totali |
| `GET` | `/gamification/badges`      | `{sbloccati, da_sbloccare}` con icone e descrizioni |
| `GET` | `/gamification/leaderboard` | Top 10 utenti per XP (con livello, sessioni, CO₂) |
| `GET` | `/gamification/sessioni`    | Ultime 10 sessioni dell'utente con kWh, durata, costo, XP guadagnati |
| `GET` | `/gamification/sfide`       | 3 sfide settimanali con progresso e bonus XP |

**Utility**
| Metodo | Rotta | Cosa fa |
|---|---|---|
| `POST` | `/logout` | Invalida il token corrente |
| `POST` | `/broadcasting/auth` | Autorizza Echo a sottoscrivere PrivateChannel (per React). Vedi [WebSocket auth](#-routes-channels-broadcasting). |

**Admin** (middleware `auth:sanctum + admin.api`)
| Metodo | Rotta | Cosa fa |
|---|---|---|
| `GET`  | `/admin/dashboard`            | KPI globali (sessioni totali, kWh, revenue) + grafico settimanale + top utenti + ultime sessioni |
| `GET`  | `/admin/utenti`               | Lista utenti con filtri |
| `GET`  | `/admin/utenti/{id}`          | Dettaglio utente con statistiche personali |
| `PUT`  | `/admin/utenti/{id}`          | Modifica dati anagrafici + ruolo |
| `POST` | `/admin/utenti/{id}/toggle`   | Attiva/disattiva (campo `attivo`) |
| `POST` | `/admin/utenti/{id}/reset`    | Reset password |
| `GET`  | `/admin/sessioni`             | Tutte le sessioni paginate (kWh live da Redis per quelle aperte) |
| `GET`  | `/admin/stazioni`             | Stato aggregato stazioni (`online`, `liberi/totali`, manutenzione) |
| `POST` | `/admin/stazioni/{id}/toggle` | Metti/togli stazione in manutenzione |
| `GET`  | `/admin/stazioni/{id}/setup`  | Pre-setup (per il form Completa Setup) |
| `POST` | `/admin/stazioni/{id}/setup`  | Conferma setup (nome, indirizzo, coordinate) |
| `GET`  | `/admin/report/csv?...`       | Esporta sessioni in CSV (filtrabile per utente/giorno) |

> **Heartbeat e fine sessione lato colonnine NON passano da HTTP**: viaggiano su
> MQTT e li consuma il worker `mqtt:leggi`. La colonnina, dopo `/iot/registra`,
> parla solo via MQTT.

---

## 🔐 Autenticazione

Il backend gestisce **tre tipi di client diversi** — utente web, utente React, colonnina
IoT — e per ognuno ha un meccanismo di auth dedicato. Tutti convivono nello stesso
Laravel grazie al sistema **multi-guard**.

### Quadro d'insieme

| Tipo di client       | Meccanismo                            | Dove si vede nel codice                 | Sopravvive al refresh? |
|----------------------|---------------------------------------|------------------------------------------|------------------------|
| Browser Blade (`/login`) | Sessione cookie + CSRF             | Guard `web`, `WebAuthController`         | Sì (cookie httpOnly)   |
| React (SPA)              | Token Bearer **Sanctum**           | Guard `sanctum`, `Api\AuthController`    | Sì (`localStorage`)    |
| Colonnina (ESP32 / sim)  | Password globale `IOT_REGISTRATION_PASSWORD` + MAC | `Api\IotController::Registra()`         | Una sola volta: poi solo MQTT |
| Postman / `curl`         | Stesso Sanctum del React           | `POST /api/login` → `Authorization: Bearer …` | Sì, finché non fai logout |

> Sanctum **non è OAuth**: è un emettitore di token opaco. Quando fai login, Laravel
> crea una riga in `personal_access_tokens` con un hash del token; il client deve
> rispedire la stringa originale nell'header `Authorization`. Niente refresh-token,
> niente scadenza automatica: il token vive finché non chiami `/logout` o non lo
> revochi a mano.

### 1. Login utente (Blade o API) — `POST /login` e `POST /api/login`

Sono **due rotte distinte** che fanno cose simili ma per client diversi:

| | `POST /login` (Blade) | `POST /api/login` (Sanctum) |
|---|---|---|
| Controller | `WebAuthController::login` | `Api\AuthController::login` |
| Risponde | Redirect 302 + cookie sessione | JSON `{ access_token, token_type, user }` |
| Usato da | Form HTML dentro il sito | React, Postman, Python |
| Persiste | Cookie httpOnly `green_school_session` | Header `Authorization: Bearer <token>` |

Body atteso da entrambe:
```json
{ "email": "test.test@email.it", "password": "password123" }
```

**Trucco interno**: `WebAuthController::login` dopo l'auth via sessione crea **anche**
un token Sanctum e lo salva in `session('api_token')`. Serve al JS dentro le Blade
(mappa, profilo) per chiamare le API JSON come fa React, senza dover loggare due volte.

### 2. Difese contro brute force

Stesso codice in entrambi i controller:

- **Lockout dopo 5 tentativi falliti** → `login_bloccato_fino = now() + 15 minuti`.
- Il counter `login_tentativi` si azzera ad ogni login riuscito.
- Risposta in caso di blocco: **HTTP 429** con `Retry-After: <secondi>`.
- Check `attivo`: se un admin ha disabilitato l'utente (`/admin/utenti/{id}/toggle`),
  qualsiasi login fallisce con **HTTP 403**.

### 3. Usare il token Sanctum (per client API)

Una volta ottenuto `access_token` da `POST /api/login`:

```http
POST /api/stations HTTP/1.1
Authorization: Bearer 7|AbCdEf...XyZ
Accept: application/json
```

Senza il header → **HTTP 401 Unauthenticated**.
Token errato/revocato → **HTTP 401 Unauthenticated**.
Token valido ma rotta admin senza ruolo admin → **HTTP 403 Forbidden** (vedi middleware
[`AdminApiMiddleware`](src/app/Http/Middleware/AdminApiMiddleware.php)).

`POST /api/logout` revoca **solo il token corrente** (`$user->currentAccessToken()->delete()`).
Token diversi dello stesso utente (es. mobile + Postman) restano validi.

### 4. Registrazione colonnina — `POST /api/iot/registra`

L'unica rotta che la colonnina chiama via HTTP. È pubblica perché l'ESP32 al primo
boot non ha credenziali, ma è protetta da:

- **Password globale** `IOT_REGISTRATION_PASSWORD` (vedi `.env` backend e
  `simulatore/params/worker-N.env`: i due valori **devono coincidere**, altrimenti
  401 `Password registrazione non valida`).
- **MAC address** come identificativo della colonnina: se è la prima volta che vede
  quel MAC, Laravel crea il record `stazioni` con `stato_setup='in_setup'`. Se il MAC
  esiste già, restituisce l'`id_stazione` esistente (registrazione idempotente).

Body atteso:
```json
{ "mac_address": "AA:BB:CC:DD:EE:01", "numero_punti": 2,
  "password_registrazione": "greenschool-iot-2025" }
```

> Dopo questa singola chiamata HTTP la colonnina **non parla più HTTP**: tutto il resto
> (heartbeat, telemetria, eventi, comandi) viaggia su MQTT. Vedi
> [`simulatore/README.md`](../simulatore/README.md) per il dettaglio dei topic.

### 5. Auth dei canali WebSocket privati

I dati live (kWh durante la ricarica, eventi sessione) viaggiano su Reverb su canali
**privati** `private-user.{id_utente}`. Echo, prima di sottoscriversi, chiama un
endpoint di auth che restituisce una firma HMAC se l'utente ha diritto al canale.

Esistono **due endpoint** che puntano alla stessa closure di [`channels.php`](src/routes/channels.php):

| Endpoint | Middleware | Usato da |
|---|---|---|
| `POST /broadcasting/auth` | `web` (sessione + CSRF) | Blade — Echo legge il cookie di sessione |
| `POST /api/broadcasting/auth` | `auth:sanctum` (Bearer token) | React — Echo invia `Authorization: Bearer …` |

La closure è una sola:
```php
Broadcast::channel('user.{id_utente}', function ($user, $id_utente) {
    return (string) $user->id_utente === (string) $id_utente;
});
```

Quindi un utente può sottoscriversi SOLO al canale che porta il suo id. Tentativi su
canali altrui → **HTTP 403**.

### 6. Pannello admin

Due middleware, stessa logica:

- **`admin`** (`AdminMiddleware`): per le rotte Blade `/admin/*`. Render di errore HTML
  con `abort(403)`.
- **`admin.api`** (`AdminApiMiddleware`): per le rotte API `/api/admin/*`. Risponde
  JSON `{ error: 'forbidden' }` con HTTP 403.

Entrambi controllano `Auth::user()->ruolo === 'admin'`. Per promuovere un utente non
c'è un endpoint dedicato: si modifica direttamente in DB o via Tinker.

```bash
docker exec -it green_app php artisan tinker
> App\Models\Utenti::where('email','x@y.it')->update(['ruolo'=>'admin'])
```

---

## 🔄 Flusso completo di una ricarica (lato API)

Sequenza tipica di chiamate per portare a termine una ricarica. Le frecce `→` sono
HTTP, gli eventi WS sono indicati a parte.

```
1. POST /api/login                                       → { access_token }
2. GET  /api/stations                                    → lista stazioni per la mappa
3. GET  /api/station/{id}                                → dettaglio + punti della stazione scelta
4. POST /api/{id_stazione}/verifica-codice  body {codice}→ 202 {status:"Attesa_Cavo", reservation_timeout:60}
                                                          (rendez-vous in Redis, finestra 60s)
   ─── nel frattempo l'utente ATTACCA IL CAVO ───
       la colonnina pubblica su MQTT `stazione/{mac}/{id_punto}/eventi`:
         {tipo: "cavo_collegato"}
       mqtt-worker chiude il rendez-vous → CALL sp_avvio_sessione → crea sessione
       → broadcast WS `SessioneAvviata` sul canale private-user.{id}
5. GET  /api/me/sessione-attiva                          → { attiva:true, id_sessione, ... }
   (oppure ascolto l'evento WS e salto il polling)
6. — WS — `.ricarica.heartbeat` ogni ~5s con delta kWh   (canale private-user.{id})
7. POST /api/session/{id}/stop                           → STOP via MQTT, sessione chiusa
8. POST /api/logout                                      → revoca il token corrente
```

Tutti gli step da `2.` in poi richiedono `Authorization: Bearer <token>`.

---

## 🎮 Controller — uno per uno

### Controller Web (rendono Blade views, rotte in [`web.php`](src/routes/web.php))

| Classe | Cosa fa | Rotte gestite |
|---|---|---|
| **`WebAuthController`** | Login/registrazione/logout *Blade*. Mostra le form, autentica via sessione, crea token Sanctum salvato in `session('api_token')` per il JS della mappa. Implementa lockout 5 tentativi → 15 min e check `attivo`. | `GET/POST /login`, `GET/POST /register`, `POST /logout` |
| **`AdminController`** | Pannello admin **Blade** (la sidebar viola). Restituisce sempre `view('admin.xxx', $data)` — quindi HTML. | `/admin`, `/admin/utenti`, `/admin/utenti/{id}`, `/admin/sessioni`, `/admin/stazioni`, `/admin/stazioni/{id}/setup`, `/admin/report/csv` |

> Le altre rotte Blade (`/map`, `/profilo`, `/classifica`, `/scuola`, `/stazione/{id}`,
> `/session/{uuid}`) sono **chiusure inline** in `web.php` che si limitano a fare
> `view('xxx', $datiCalcolatiServerSide)`. Per i dati live (kWh, classifica, ecc.) il
> Blade chiama lo stesso `api.php` di React via JS.

### Controller API (rendono JSON, rotte in [`api.php`](src/routes/api.php))

| Classe | Cosa fa | Tipica risposta |
|---|---|---|
| **`Api\AuthController`** | Login/logout via token. Stesse difese di `WebAuthController` (lockout, check `attivo`). Usato sia da React sia da client esterni (Postman, Python). | `{ access_token, token_type, user }` |
| **`Api\RegisterController`** | Registrazione utente da React: crea record `utenti`, restituisce token Sanctum (auto-login). | `{ access_token, user }` |
| **`Api\StationController`** | `all()`: stazioni `attiva` con `puntiRicarica` eager-loadato. `show($id)`: dettaglio singolo. | `{ status, data }` |
| **`Api\SessionController`** | Cuore del flusso sessione: `AutenticazioneCodice(string $id_stazione, …)` riceve la stazione dal path, verifica il codice contro quella sola stazione, memorizza il rendez-vous Redis e pubblica MQTT `autenticazione_completata`. `SessioneAttivaUtente()`, `AttesaCavo()`, `show()`, `InterrompiSessione()`. | varia per metodo |
| **`Api\IotController`** | `Registra()`: registrazione colonnina IoT con password globale + MAC. Crea record `stazioni` con stato `in_setup`. | `{ status, id_stazione }` |
| **`Api\SchoolController`** | `profile()`: profilo scuola. `consumption()`: 12 mesi di consumi per anno selezionato (con anni disponibili per il selettore). | `{ status, data }` |
| **`Api\GamificationController`** | `profile()`, `badges()`, `leaderboard()`, `sessioni()`, `sfide()`. Tutta la matematica XP/livello/CO₂. | varia |
| **`Api\AdminApiController`** | Versione JSON di `AdminController` per il pannello admin React. Stessi metodi/logica, output JSON anziché Blade. | varia |
| **`Controller`** | Classe base astratta di Laravel. Tutti i controller la estendono. Non contiene logica. | — |

> **Perché `AdminController` e `Api\AdminApiController` separati?** Il primo serve a
> Blade (HTML), il secondo a React (JSON). Stessa logica, render diverso. Vedi
> [ARCHITECTURE.md](../ARCHITECTURE.md) per il razionale.

---

## 🗂️ Models — uno per uno

Tutti in `src/app/Models/`. Sono classi Eloquent: ogni istanza è una riga di una
tabella, con relazioni e cast definiti come proprietà.

| Modello | Tabella | Cosa rappresenta | Relazioni principali |
|---|---|---|---|
| **`Utenti`** | `utenti` | Utente del sistema (studenti, docenti, admin) con login Sanctum. Campi: `nome`, `cognome`, `email`, `password`, `ruolo` (`utente`/`admin`), `attivo`, `login_tentativi`, `login_bloccato_fino`. | `hasMany` sessioni, `hasMany` badge_utente |
| **`Stazioni`** | `stazioni` | Stazione fisica (= un Arduino con N prese). Campi: `id_stazione` (MAC), `nome`, `indirizzo`, `latitudine`, `longitudine`, `stato_setup` (`in_setup`/`attiva`), `in_manutenzione`. Espone `statoAggregatoPerPunto()` per calcolare quanti punti sono liberi. | `hasMany` `puntiRicarica` |
| **`Punti_ricarica`** | `punti_ricarica` | Singola presa di una stazione. Campi: `id_punto`, `id_stazione`, `identificativo_fisico` (es. "P1"), `potenza_max_kw`, `libera` (0/1), `stato_hardware` (`online`/`offline`/`guasto`/`manutenzione`), `data_ultimo_heartbeat`. | `belongsTo` `Stazioni` |
| **`Sessioni_ricarica`** | `sessioni_ricarica` | Una ricarica (chiusa o aperta). Campi: `id_sessione`, `id_utente`, `id_stazione`, `id_punto`, `metodo_avvio`, `data_inizio`, `data_fine`, `quantita_kwh`, `costo_totale`. Cast `datetime` su date e `float` su numerici (per ISO UTC corretto via JSON). | `belongsTo` `Utenti`, `puntoRicarica`, `stazione` |
| **`Scuola_profilo`** | `scuola_profilo` | Anagrafica edificio scolastico: denominazione, classe energetica, anno costruzione, superficie, FV installato, JSON interventi. | `hasMany` `Scuola_consumo_mensile` |
| **`Scuola_consumo_mensile`** | `scuola_consumo_mensile` | Riga mensile dei consumi: `anno`, `mese`, `consumo_elettrico_kwh`, `consumo_termico_kwh`, `produzione_fv_kwh`, `co2_emessa_kg`. | `belongsTo` `Scuola_profilo` |
| **`Gamification_profilo_utente`** | `gamification_profilo_utente` | Stato gamification persistente per utente: `xp_totali`, `livello` (generated column), `co2_risparmiata_kg`, `streak_giorni`, `data_ultima_ricarica`. | `belongsTo` `Utenti` |
| **`Gamification_badge_catalogo`** | `gamification_badge_catalogo` | Catalogo di tutti i badge possibili: `codice`, `nome`, `descrizione`, `icona_emoji`, `condizione_json`. | `hasMany` `Gamification_badge_utente` |
| **`Gamification_badge_utente`** | `gamification_badge_utente` | Tabella ponte: quando un utente sblocca un badge. `id_utente`, `id_badge`, `data_sblocco`. | `belongsTo` catalogo, utente |
| **`Gamification_sfida_settimanale`** | `gamification_sfide_settimanali` | Stato di una sfida per un utente in una settimana specifica: `codice_sfida`, `progresso`, `target`, `stato`. | `belongsTo` `Utenti` |
| **`Badge_utente`** | (legacy) | Modello vecchio, sostituito da `Gamification_badge_utente`. Conservato per retrocompatibilità con seeders/migration più datati. | — |

---

## ⚙️ Services — logica di dominio

Cartella `src/app/Services/`. Sono classi che incapsulano la logica di business e
sono iniettate via constructor injection nei controller / command.

| Service | Cosa fa | Usato da |
|---|---|---|
| **`SessioneService`** | Apertura/chiusura sessione (chiama le stored procedure `sp_avvio_sessione` / `sp_termina_sessione`), gestione del rendez-vous codice→cavo su Redis (60s TTL), tracking kWh live su Redis, dispatch eventi WS. Esempio: `avvia()`, `termina()`, `memorizzaCodiceInAttesa()`, `kwhCorrenti()`, `secondiAttesaResidui()`. | `SessionController`, `MqttWorker`, `AdminController` (per kWh live in tabelle) |
| **`CodiceMonousoService`** | Gestisce i codici 6 cifre delle stazioni: scrittura/lettura su Redis (chiave `codice:{idStazione}`, **TTL 60s**, sincronizzato col refresh della colonnina). `verifica($idStazione, $codice)` confronta il codice digitato con quello in Redis per QUELLA stazione (no iterazione). | `SessionController`, `MqttWorker` |
| **`GamificationService`** | Aggiornamento XP, livello, CO₂, streak quando una sessione finisce. Sblocco badge in base a regole (prima ricarica, streak, kWh accumulati…). | `SessioneService::termina()` |
| **`SfideSettimanaliService`** | Definizioni statiche delle 3 sfide settimanali. Crea/aggiorna le righe in `gamification_sfide_settimanali` ricalcolando il progresso dalle sessioni reali ad ogni chiamata. | `GamificationController::sfide()` |
| **`MqttService`** | Wrapper attorno al client MQTT (php-mqtt/laravel-client). Connect+publish lazy: ogni `publish()` apre una connessione, invia, chiude. Usato per mandare comandi alle colonnine (`START`, `STOP`, `autenticazione_completata`). | `SessioneService`, `SessionController`, `AdminController` |

---

## 🔨 Console Commands — worker permanenti

Cartella `src/app/Console/Commands/`. Comandi artisan che girano come daemon
all'interno dei container dedicati.

| Comando | Container | Cosa fa |
|---|---|---|
| **`MqttWorker`** (`mqtt:leggi`) | `green_mqtt_worker` | Loop infinito che si iscrive ai topic MQTT `stazione/#/eventi`, `stazione/#/telemetria`, `stazione/#/codice`, `stazione/#/heartbeat`. Per ogni messaggio: aggiorna DB/Redis, dispatcha eventi broadcast (es. `TelemetriaRicevuta`), e — quando arriva `cavo_collegato` — chiude il rendez-vous codice→cavo avviando la sessione. È il punto di contatto tra il mondo IoT e il mondo Laravel. |
| **`HeartbeatChecker`** (`app:heartbeat-checker`) | `green_heartbeat_checker` | Loop con sleep di ~10s. Marca offline (`stato_hardware = 'offline'`) i punti che non hanno mandato heartbeat oltre la soglia. Dispatch `PuntoHardwareStatusChanged` solo se lo stato cambia, per evitare event-spam. |

---

## 📡 Events — broadcast WebSocket

Cartella `src/app/Events/`. Sono classi Laravel che implementano `ShouldBroadcastNow`:
quando vengono `dispatch`-ate, Laravel le manda a Reverb che le push al browser via
WebSocket. Il client Echo le ascolta via `channel.listen('.event-name')`.

| Evento | broadcastAs | Canale | Quando |
|---|---|---|---|
| **`SessioneAvviata`** | `.sessione.avviata` | `private-user.{id}` | Sessione creata (dopo `cavo_collegato`) |
| **`TelemetriaRicevuta`** | `.ricarica.heartbeat` | `private-user.{id}` | Ogni telemetria della colonnina (~5s). Porta `cambiamento_kwh` (delta), non il totale. |
| **`PuntoStatusChanged`** | `.punto.status` | `mappa` (pubblico) | Una presa si libera/occupa |
| **`PuntoHardwareStatusChanged`** | `.punto.hardware.status` | `mappa` (pubblico) | Una presa va offline/online/guasto |
| **`StazioneStatusChanged`** | `.stazione.status` | `mappa` (pubblico) | Stato aggregato della stazione cambia (es. ultima presa occupata) |

Tutti sono `ShouldBroadcastNow` (sincroni, niente queue) per non avere ritardi
visibili sull'UI.

---

## 🛡️ Middleware

Cartella `src/app/Http/Middleware/`. Sono i "filtri" che intercettano le richieste
prima dei controller. Aliasati in [`bootstrap/app.php`](src/bootstrap/app.php).

| Middleware | Alias | Applicato a | Cosa fa |
|---|---|---|---|
| **`AdminMiddleware`** | `admin` | `/admin/*` (Blade) | `abort(403)` se `Auth::user()->ruolo !== 'admin'`. Renderizza una pagina di errore Laravel. |
| **`AdminApiMiddleware`** | `admin.api` | `/api/admin/*` | Come sopra ma per le API: risponde JSON `{ error: 'forbidden' }` con HTTP 403. |
| **`RedirectIfAuthenticated`** | `guest` | rotte di registrazione, ecc. | Se l'utente è già loggato lo redirige a `/map` invece di mostrargli di nuovo il form. |

---

## 🛣️ Routes, channels, broadcasting

| File | Contenuto |
|---|---|
| [`routes/web.php`](src/routes/web.php) | Rotte web (Blade). Login, mappa, profilo, classifica, scuola, station-detail, session-active, admin/*. Le rotte di pagina hanno spesso una chiusura inline che chiama un service per i dati e poi `view()`. |
| [`routes/api.php`](src/routes/api.php) | Rotte API JSON. Pubbliche (login, register, iot, health) + protette via `auth:sanctum` (stations, session, school, gamification) + admin via `auth:sanctum + admin.api`. Registra anche `Broadcast::routes(['middleware' => ['auth:sanctum']])` per il canale WS privato di React. |
| [`routes/channels.php`](src/routes/channels.php) | Autorizzazioni canali privati. Closure `Broadcast::channel('user.{id_utente}', …)`: ritorna `true` solo se l'utente loggato chiede il SUO canale. |
| [`routes/console.php`](src/routes/console.php) | Comandi artisan custom (qui poco/niente: i comandi sono classi). |

**Endpoint di broadcasting auth** (vedi [ARCHITECTURE.md sez. C](../ARCHITECTURE.md#c-websocket-reverb-broadcast-verso-il-browser)):
- `POST /broadcasting/auth` — middleware `web` (sessione + CSRF) → lo usa Blade.
- `POST /api/broadcasting/auth` — middleware `auth:sanctum` (Bearer token) → lo usa React.

Entrambi finiscono nella stessa closure di `channels.php`. La differenza è solo
**come** è stato autenticato l'utente prima.

---

## 🗄️ Database, seeders, stored procedure

| Cartella | Contenuto |
|---|---|
| [`database/migrations/`](src/database/migrations/) | Tabelle Laravel (`users`, `personal_access_tokens`, ecc.) + tabelle progetto (`utenti`, `stazioni`, `punti_ricarica`, `sessioni_ricarica`, gamification, scuola). Include anche le **stored procedure** `sp_avvio_sessione`, `sp_termina_sessione`, `sp_verifica_disponibilita` e trigger. |
| [`database/seeders/`](src/database/seeders/) | Popolano il DB con dati di test: utenti default, badge catalogo, profilo scuola, consumi mensili degli anni passati, sfide settimanali base. |
| [`database/factories/`](src/database/factories/) | Factory per generare record di test (poco usate qui). |

> Per partire da zero: `docker exec -it green_app php artisan migrate:fresh --seed`.

---

## 🔑 Concetti chiave

- **Auth a 3 livelli**:
  - Sessione cookie (Blade)
  - Sanctum token Bearer (React, API esterne)
  - Password globale `IOT_REGISTRATION_PASSWORD` (registrazione colonnina, una sola volta)
- **Codice monouso**: 6 cifre per stazione, generato dalla colonnina ogni 60s, TTL Redis 60s. È un numero pseudocasuale salvato in Redis come stringa: la verifica è un semplice confronto stringa, niente firma HMAC. Sostituisce il vecchio QR che era statico e quindi insicuro.
- **Rendez-vous codice→cavo**: tra "ho verificato il codice" e "ho attaccato il cavo" passa un tempo. Il sistema dà **60 secondi**: in quella finestra il pending è in Redis (`codice_pending:{idStazione}`). Se scade, sessione non parte. Vedi `SessioneService::memorizzaCodiceInAttesa()`.
- **Stored procedure transazionali**: `sp_avvio_sessione` e `sp_termina_sessione` fanno tutti i check e gli UPDATE in transazione atomica. Niente sessione "a metà".
- **Redis**:
  - sessioni HTTP Laravel (`SESSION_DRIVER=redis`)
  - cache Laravel (`CACHE_STORE=redis`)
  - codice monouso, rendez-vous, kWh live (chiavi con TTL automatico)
- **`ShouldBroadcastNow` ovunque**: niente queue per i broadcast, gli eventi arrivano live.
- **Italiano nei nomi**: classi e tabelle in italiano (Utenti, Stazioni, Punti_ricarica) per coerenza col dominio della scuola. Le convenzioni Laravel a volte si rompono (no `User`, no `posts`), ma il codice resta leggibile a chi conosce il dominio.

---

## 🛠️ Comandi artisan utili

```bash
# Ricrea il database da zero e lo popola
docker exec -it green_app php artisan migrate:fresh --seed

# Pulisce TUTTA la cache di Laravel (config, route, view, eventi)
docker exec -it green_app php artisan optimize:clear

# Worker MQTT in foreground (di solito gira in green_mqtt_worker)
docker exec -it green_app php artisan mqtt:leggi

# Lista rotte (utile per verificare che broadcasting/auth esista)
docker exec -it green_app php artisan route:list --path=broadcasting

# Tinker (shell PHP interattiva con Laravel caricato)
docker exec -it green_app php artisan tinker
```

Per l'avvio dell'intero stack e i test con Postman, vedi il [README principale](../README.md).
