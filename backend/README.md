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
4. [Controller — uno per uno](#-controller--uno-per-uno)
5. [Models — uno per uno](#-models--uno-per-uno)
6. [Services — logica di dominio](#-services--logica-di-dominio)
7. [Console Commands — worker permanenti](#-console-commands--worker-permanenti)
8. [Events — broadcast WebSocket](#-events--broadcast-websocket)
9. [Middleware](#-middleware)
10. [Routes, channels, broadcasting](#-routes-channels-broadcasting)
11. [Database, seeders, stored procedure](#-database-seeders-stored-procedure)
12. [Concetti chiave](#-concetti-chiave)
13. [Comandi artisan utili](#-comandi-artisan-utili)

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
| `POST` | `/verifica-codice` | Verifica codice 6 cifre → memorizza rendez-vous su Redis (60s). Risposta 202. |
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
| **`Api\SessionController`** | Cuore del flusso sessione: `AutenticazioneCodice()` verifica codice + memorizza rendez-vous Redis + pubblica MQTT `autenticazione_completata`. `SessioneAttivaUtente()`, `AttesaCavo()`, `show()`, `InterrompiSessione()`. | varia per metodo |
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
| **`CodiceMonousoService`** | Gestisce i codici 6 cifre delle stazioni: scrittura/lettura su Redis (chiave `codice:{idStazione}`, TTL 35s, sincronizzato con il refresh della colonnina). `verifica($codice)` itera sulle stazioni `attiva` e restituisce l'`id_stazione` che matcha. | `SessionController`, `MqttWorker` |
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
- **Codice monouso**: 6 cifre per stazione, cambia ogni 30s, TTL Redis 35s. Sostituisce il vecchio QR che era statico e quindi insicuro.
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
