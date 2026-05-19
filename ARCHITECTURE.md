# Green School — Architettura

Questo documento spiega in dettaglio **come funziona l'app**: endpoint API, middleware,
canali WebSocket, topic MQTT, schema database, flussi di registrazione stazione e
avvio/terminazione sessione, integrazione con gamification.

Per setup e comandi vedi [`README.md`](README.md).

---

## Indice

- [1. Database](#1-database)
- [2. Registrazione colonnina](#2-registrazione-colonnina)
- [3. Setup stazione (admin)](#3-setup-stazione-admin)
- [4. Manutenzione](#4-manutenzione)
- [5. Codice monouso](#5-codice-monouso)
- [6. Avvio sessione di ricarica](#6-avvio-sessione-di-ricarica)
- [7. Telemetria e kWh live](#7-telemetria-e-kwh-live)
- [8. Terminazione sessione](#8-terminazione-sessione)
- [9. API REST](#9-api-rest)
- [10. Middleware](#10-middleware)
- [11. Topic MQTT](#11-topic-mqtt)
- [12. WebSocket (Reverb)](#12-websocket-reverb)
- [13. Gamification](#13-gamification)

---

## 1. Database

Schema definito da [`migrations/0001_creazione_struttura_iniziale .php`](backend/src/database/migrations/0001_creazione_struttura_iniziale%20.php) + migrazioni successive.

### Tabelle principali

**`utenti`** (PK: `id_utente` UUID)
- `email`, `password` (Sanctum), `nome`, `cognome`, `cellulare`
- `tipo_account` enum (`completo`/`badge_anonimo`/`ospite`)
- `ruolo` enum (`utente`/`admin`) — vedi `0015_aggiunta_ruolo_utenti.php`

**`stazioni`** (PK: `id_stazione` = **MAC ADDRESS** della colonnina, es. `AA:BB:CC:DD:EE:FF`)
- `stato_setup` enum (`in_setup` / `attiva`) — l'admin la porta a `attiva` dal pannello
- `nome`, `indirizzo`, `latitudine`, `longitudine`, `coordinata` (GEOMETRY, calcolato da trigger) — **nullable** durante `in_setup`
- `libera` boolean
- `data_ultimo_heartbeat` aggiornato dal MQTT worker ad ogni heartbeat
- `in_manutenzione` boolean — flag manuale settato dall'admin (toggle)
- `tipo_area` enum, `data_attivazione`

> ⚠️ La stazione **non ha** una colonna `stato_hardware`: il tag online/offline viene
> derivato a runtime con una query (stazione online ⇔ `in_manutenzione=false` AND almeno
> un punto della stazione ha `stato_hardware='online'`).

**`punti_ricarica`** (PK composta: `(id_stazione, id_punto)`)
- `id_punto` = numero locale alla stazione: `"1"`, `"2"`, …, `"N"`. Lo dichiara l'**hardware** al primo `POST /api/iot/registra` mandando `numero_punti`.
- `tipo_veicolo` (`auto`/`bici`/`monopattino`), `tipo_connettore`, `potenza_max_kw` — compilati dall'admin
- `stato_hardware` enum (`online`/`offline`/`guasto`/`manutenzione_programmata`)
- `libera` boolean (0 quando è in sessione)
- `data_ultimo_heartbeat`, `tariffa_predefinita`, `metodi_autenticazione_supportati`

**`sessioni_ricarica`** (PK: `id_sessione` UUID generata da `sp_avvio_sessione`)
- `id_utente`, `id_stazione`, `id_punto` (FK composta verso `punti_ricarica`)
- `metodo_avvio` enum (`RFID`/`CODICE`)
- `data_inizio`, `data_fine`, `quantita_kwh`, `costo_totale`, `stato_pagamento`

**`accumulatori_stazione`** + **`storico_livello_batteria`** — batterie buffer (opzionali in dev).

**`badge_utente`**, **`gamification_*`** — gamification (XP, livelli, badge, sfide settimanali).

### Stored procedure ([`0002_creazione_sp_sessione_ricarica.php`](backend/src/database/migrations/0002_creazione_sp_sessione_ricarica.php))

- `sp_verifica_disponibilita(id_stazione, id_punto)` — controlla che il punto sia online (heartbeat fresco), non in guasto/manutenzione e libero.
- `sp_avvio_sessione(id_utente, id_stazione, id_punto, metodo, id_badge)` — crea la riga `sessioni_ricarica`, marca `libera=0`, genera UUID di sessione.
- `sp_termina_sessione(id_sessione, kwh_totali)` — chiude la sessione, calcola il costo dalla tariffa, marca `libera=1`.

### Trigger ([`0003_creazione_trigger_utenti.php`](backend/src/database/migrations/0003_creazione_trigger_utenti.php))

- Auto-UUID su `utenti.id_utente`, `sessioni_ricarica.id_sessione`, `accumulatori_stazione.id_accumulatore`.
- `coordinata = POINT(lng, lat)` automatico su INSERT/UPDATE di `stazioni` (solo se lat/lng valorizzate).
- `trg_check_sessione_aperta` — blocca due sessioni aperte sullo stesso `(id_stazione, id_punto)`.
- `trg_update_heartbeat_after_session` — aggiorna `data_ultimo_heartbeat` del punto all'inserimento di una sessione.
- `trg_aggiorna_percentuale_accumulatore` — ricalcola % batteria dopo INSERT su storico.

---

## 2. Registrazione colonnina

**Endpoint:** `POST /api/iot/registra` (pubblico)

**Body JSON:**
```json
{ "mac": "AA:BB:CC:DD:EE:FF", "password": "greenschool-iot-2025", "numero_punti": 2 }
```

**Logica** ([`IotController::Registra`](backend/src/app/Http/Controllers/Api/IotController.php)):

1. Valida la password contro `config('services.iot.registration_password')` (env `IOT_REGISTRATION_PASSWORD`).
2. Normalizza MAC in uppercase.
3. Se la stazione **non esiste** a DB:
   - INSERT in `stazioni` con `stato_setup='in_setup'`, `in_manutenzione=false`, `libera=1`.
   - Per `i=1..N` INSERT in `punti_ricarica` con `id_punto=(string)$i`, `tipo_veicolo='auto'` (placeholder), `stato_hardware='offline'`, `libera=1`.
4. Se esiste già: idempotente. Se `numero_punti` dichiarato differisce dai punti a DB, logga warning ma non tocca i record.
5. Restituisce host/porta MQTT e i nomi dei topic da usare.

**Risposta (201):**
```json
{
  "id_stazione": "AA:BB:CC:DD:EE:FF",
  "stato_setup": "in_setup",
  "id_punti": ["1", "2"],
  "mqtt": {"host": "mqtt", "port": 1883},
  "topic_ready":     "stazione/AA:BB:CC:DD:EE:FF/ready",
  "topic_codice":    "stazione/AA:BB:CC:DD:EE:FF/codice",
  "topic_comandi_stazione": "stazione/AA:BB:CC:DD:EE:FF/comandi",
  "topic_heartbeat": "stazione/AA:BB:CC:DD:EE:FF/{id_punto}/heartbeat",
  "topic_eventi":    "stazione/AA:BB:CC:DD:EE:FF/{id_punto}/eventi",
  "topic_telemetria":"stazione/AA:BB:CC:DD:EE:FF/{id_punto}/telemetria",
  "topic_comandi":   "stazione/AA:BB:CC:DD:EE:FF/{id_punto}/comandi"
}
```

L'unica password che la colonnina conosce è `PASSWORD_REGISTRAZIONE`: la usa **solo** per questa chiamata. Dopo, comunica con il broker MQTT senza autenticazione (il broker dev è `allow_anonymous true`).

---

## 3. Setup stazione (admin)

Una stazione `in_setup` non compare sulla mappa pubblica (`StationController` filtra `stato_setup='attiva'`). L'admin la trova in `/admin/stazioni` e la configura:

1. **`GET /admin/stazioni/{mac}/setup`** ([`AdminController::setupStazione`](backend/src/app/Http/Controllers/AdminController.php)) — form precompilato con i punti già creati dalla colonnina.
2. L'admin compila nome, indirizzo, lat/lng, tipo_area e per ogni punto: tipo_veicolo / connettore / potenza.
3. **`POST /admin/stazioni/{mac}/setup`** ([`completaSetupStazione`](backend/src/app/Http/Controllers/AdminController.php)):
   - Valida che gli id_punto inviati corrispondano a quelli a DB (l'hardware decide il numero di prese, l'admin non può aggiungere/rimuovere).
   - UPDATE `stazioni` con i metadati e `stato_setup='attiva'`, `in_manutenzione=false`.
   - UPDATE in place dei `punti_ricarica` con i metadati per punto (nessun DELETE/INSERT).
   - **Publish MQTT** su `stazione/{mac}/ready` con la lista degli `id_punti`.
4. Il simulatore riceve `ready`, crea i `Punto_ricarica` in memoria, sottoscrive `stazione/{mac}/{id_punto}/comandi`, avvia heartbeat e generazione codice.

---

## 4. Manutenzione

Toggle gestito da [`AdminController::toggleStazione`](backend/src/app/Http/Controllers/AdminController.php) (`POST /admin/stazioni/{mac}/toggle`):

1. Flip di `stazioni.in_manutenzione` (true ⇄ false).
2. Se entra in manutenzione: UPDATE di tutti i `punti_ricarica` della stazione a `stato_hardware='offline'` (così la mappa lo riflette subito senza aspettare il prossimo heartbeat).
3. **Publish MQTT** su `stazione/{mac}/manutenzione` con `{ "comando": "manutenzione", "on": true|false }`.
4. La colonnina riceve il messaggio (`_on_message` nel simulatore):
   - Se `on=true`: ferma generazione codice e heartbeat (`self.attiva = False`).
   - Se `on=false`: riprende.

Visualizzazione admin (tabella `/admin/stazioni`): il tag stazione viene calcolato a runtime:
- `in_manutenzione=true` → badge **manutenzione** (giallo)
- altrimenti, almeno un punto online → **online** (verde)
- altrimenti → **offline** (rosso)

---

## 5. Codice monouso

**1 codice per stazione**, **6 cifre**, vale per qualsiasi punto di quella stazione.

### Generazione (simulatore)

[`Stazione._loop_codice`](simulatore/stazione.py):
- Ogni `CODICE_INTERVAL` (default 30s):
  - Estrae `secrets.randbelow(1_000_000)` formattato a 6 cifre (`secrets` usa randomness kernel, thread-safe, niente seed condiviso).
  - Memorizza in `self.codice_attuale` (visibile dal menu).
  - Publish MQTT su `stazione/{mac}/codice` con `{ "codice": "NNNNNN", "scadenza": 60 }`.

### Storage (backend)

[`CodiceMonousoService`](backend/src/app/Services/CodiceMonousoService.php):
- Chiave Redis: `codice:{id_stazione}` → `"NNNNNN"`.
- **TTL: 60 secondi** (`CodiceMonousoService::TTL_CODICE = 60`). Il codice resta valido in Redis anche dopo che la stazione ne pubblica uno nuovo (sovrascrittura naturale).
- Il `MqttWorker` riceve sul topic `stazione/+/codice` e chiama `memorizza(idStazione, codice)`.

### Verifica

[`CodiceMonousoService::verifica($codice)`](backend/src/app/Services/CodiceMonousoService.php):
- Itera le stazioni `stato_setup='attiva'`.
- Per ognuna controlla se `Cache::get("codice:{mac}") === $codice`.
- Ritorna `id_stazione` o `null`.
- **NON cancella la chiave**: resta valida fino alla scadenza TTL (utile se 2 utenti tentano in fretta — l'unicità del rendez-vous è garantita dal SETNX sul pending, vedi sotto).

---

## 6. Avvio sessione di ricarica

### Step 1 — Verifica codice

**`POST /api/verifica-codice`** ([`SessionController::AutenticazioneCodice`](backend/src/app/Http/Controllers/Api/SessionController.php))

Body: `{ "codice": "NNNNNN" }`. Header: `Authorization: Bearer <sanctum_token>`.

1. Valida il formato (`size:6`, `regex:/^\d{6}$/`).
2. `$idStazione = $codici->verifica($codice)` — se null → `422 Codice non valido o scaduto`.
3. **Rendez-vous SETNX:** `$sessioni->memorizzaCodiceInAttesa($idStazione, $userId)` mette in Redis `codice_pending:{mac}` → `{id_utente}` con TTL 60s usando `Cache::add` (atomic, "first wins"). Se già presente → `409 Stazione già prenotata`.
4. **Publish MQTT** su `stazione/{mac}/comandi` con `{ "comando": "autenticazione_completata", "id_stazione": ... }` (per l'Arduino, ad esempio per accendere un LED).
5. Risposta `202 { "status": "Attesa_Cavo", "reservation_timeout": 60, "id_stazione": "..." }`.

Il frontend va su `/profilo?attesa=&attesa_stazione=...` che mostra il banner "In attesa del cavo" con countdown 60s.

### Step 2 — Collega cavo

L'utente attacca il cavo a uno qualsiasi dei punti della stazione. Il simulatore (o l'Arduino) pubblica MQTT su `stazione/{mac}/{id_punto}/eventi`:

```json
{ "evento": "cavo_collegato", "id_stazione": "...", "id_punto": "1" }
```

### Step 3 — Worker MQTT crea la sessione

[`MqttWorker::gestisciCavoCollegato`](backend/src/app/Console/Commands/MqttWorker.php):

1. `$pending = $sessioni->consumaCodiceInAttesa($idStazione)` — `Cache::pull` (atomic read-and-delete).
2. Se null → log "nessun codice in attesa: ignorato" (qualcuno ha collegato un cavo senza autenticarsi).
3. Altrimenti: `$sessioni->avvia($idStazione, $idPunto, $pending['id_utente'])`.

[`SessioneService::avvia`](backend/src/app/Services/SessioneService.php):

1. `CALL sp_avvio_sessione(id_utente, id_stazione, id_punto, 'CODICE', null)`.
2. Se successo: nuova riga `sessioni_ricarica`, `punti_ricarica.libera=0`.
3. Dispatch `SessioneAvviata` (WebSocket privato user) e `PuntoStatusChanged` (broadcast pubblico).
4. **Publish MQTT** su `stazione/{mac}/{id_punto}/comandi` con `{ "comando": "START", "id_sessione": "..." }`.

### Step 4 — Stazione riceve START

[`Stazione._on_message`](simulatore/stazione.py) → `Punto.gestione_messaggio_start(id_sessione)`:

1. Crea oggetto `Sessione` in memoria.
2. Lancia thread `_loop_kwh` (simula la curva di ricarica e calcola V/I).
3. Lancia thread `_loop_telemetria`.

---

## 7. Telemetria e kWh live

### Pubblicazione (sim)

[`Punto._loop_telemetria`](simulatore/stazione.py): ogni `METER_INTERVAL` (5s) pubblica su `stazione/{mac}/{id_punto}/telemetria`:

```json
{ "id_sessione": "...", "voltaggio": 230.0, "corrente": 14.2, "intervallo_sec": 5 }
```

### Consumo (worker)

[`MqttWorker::gestisciTelemetria`](backend/src/app/Console/Commands/MqttWorker.php):

1. `deltaKwh = (V * I / 1000) * (intervallo / 3600)`.
2. `SessioneService::aggiungiKwh($idSessione, $deltaKwh)` accumula su Redis `sessione_kwh:{id_sessione}` (TTL 24h).
3. Lookup `id_utente` dalla sessione e dispatch `TelemetriaRicevuta` su canale privato `user.{id_utente}` (broadcast `.ricarica.heartbeat`).

> Il valore finale di `quantita_kwh` viene scritto a DB **solo alla chiusura della sessione** (dentro `sp_termina_sessione`). Durante la ricarica la colonna è 0 e i valori vivono in Redis.

### Frontend live update

[`gamification-profile.blade.php`](backend/src/resources/views/gamification-profile.blade.php):
- `echo.private('user.' + idUtente).listen('.ricarica.heartbeat', e => { kwhCorrenti += e.cambiamento_kwh })`

---

## 8. Terminazione sessione

Due trigger possibili:

### A. Dal simulatore (scollega cavo / batteria piena)

`Punto.scollega_cavo()` → `termina_sessione(notifica_backend=True, evento="cavo_scollegato")` → publish `stazione/{mac}/{id_punto}/eventi`.

[`MqttWorker::gestisciFineSessione`](backend/src/app/Console/Commands/MqttWorker.php):
1. `pulisciStatoRendezVous` (nel caso fosse rimasto un pending Redis).
2. `id_sessione = data.id_sessione ?? sessioneAttivaPerPunto(stazione, punto)`.
3. `kwh = sessioni->kwhCorrenti(id_sessione)` (Redis).
4. `sessioni->termina(id_sessione, kwh)`.

### B. Dall'app utente

`POST /api/session/{id_sessione}/stop` ([`SessionController::InterrompiSessione`](backend/src/app/Http/Controllers/Api/SessionController.php)):
1. Verifica che la sessione sia dell'utente loggato.
2. `kwh = sessioni->kwhCorrenti($id)`; `sessioni->termina($id, $kwh)`.
3. Publish MQTT `stazione/{mac}/{id_punto}/comandi { STOP, id_sessione }` per fermare la colonnina.

### `SessioneService::termina`

1. `CALL sp_termina_sessione(id_sessione, kwh_totali)` — chiude la riga DB, calcola costo da tariffa, marca `libera=1` sul punto.
2. **Gamification:** `GamificationService::aggiorna($id, $idUtente, $kwh)` aggiorna XP/CO₂/streak e sblocca badge maturati.
3. Dispatch `PuntoStatusChanged` e (se cambia) `StazioneStatusChanged`.
4. `pulisciKwh` — cancella la chiave Redis `sessione_kwh:{id}` (il dato definitivo è ora su DB).

---

## 9. API REST

Definite in [`routes/api.php`](backend/src/routes/api.php).

### Pubbliche

| Method | Path | Controller | Note |
|---|---|---|---|
| POST | `/api/login` | `AuthController::login` | Crea token Sanctum. |
| POST | `/api/iot/registra` | `IotController::Registra` | Registrazione colonnina con password globale. |

### Autenticate (`auth:sanctum`)

| Method | Path | Controller |
|---|---|---|
| GET  | `/api/stations` | `StationController::all` (filtro `stato_setup='attiva'`) |
| GET  | `/api/station/{id}` | `StationController::show` |
| GET  | `/api/school/profile` | `SchoolController::profile` |
| GET  | `/api/school/consumption` | `SchoolController::consumption` |
| GET  | `/api/gamification/profile` | `GamificationController::profile` |
| GET  | `/api/gamification/badges` | `GamificationController::badges` |
| GET  | `/api/gamification/leaderboard` | `GamificationController::leaderboard` |
| GET  | `/api/gamification/sessioni` | `GamificationController::sessioni` |
| POST | `/api/verifica-codice` | `SessionController::AutenticazioneCodice` |
| GET  | `/api/me/sessione-attiva` | `SessionController::SessioneAttivaUtente` |
| GET  | `/api/session/{id}` | `SessionController::show` |
| POST | `/api/session/{id}/stop` | `SessionController::InterrompiSessione` |
| POST | `/api/logout` | `AuthController::logout` |

### Web (`routes/web.php`)

Mappa, profilo utente, login web, dettaglio sessione, e tutto il gruppo `prefix('admin')` (vedi sotto).

### Admin (`auth` + `admin` middleware)

| Method | Path | Azione |
|---|---|---|
| GET  | `/admin` | dashboard (statistiche aggregate) |
| GET  | `/admin/utenti` | lista utenti |
| GET  | `/admin/utenti/{id}` | dettaglio utente |
| POST | `/admin/utenti/{id}` | modifica utente |
| POST | `/admin/utenti/{id}/toggle` | attiva/disattiva utente |
| POST | `/admin/utenti/{id}/reset` | reset password |
| GET  | `/admin/sessioni` | lista sessioni |
| GET  | `/admin/stazioni` | lista stazioni con tag online/offline derivato |
| GET  | `/admin/stazioni/{id}/setup` | form completamento setup |
| POST | `/admin/stazioni/{id}/setup` | salva e pubblica `ready` MQTT |
| POST | `/admin/stazioni/{id}/toggle` | toggle manutenzione + pubblica MQTT |

---

## 10. Middleware

| Alias | Classe | Uso |
|---|---|---|
| `auth` | Default Laravel | Verifica utente loggato (sessione web o Sanctum API). Redirect a `/login` se assente. |
| `auth:sanctum` | Laravel Sanctum | Per `/api/*` autenticate: legge `Authorization: Bearer <token>`. |
| `admin` | [`AdminMiddleware`](backend/src/app/Http/Middleware/AdminMiddleware.php) | Richiede `$request->user()->ruolo === 'admin'`, altrimenti `abort(403)`. Registrato in [`bootstrap/app.php`](backend/src/bootstrap/app.php). |

> ⚠️ La colonna `stazioni.token` e il middleware `DeviceTokenAuth` **sono stati rimossi**: la colonnina non ha più un token HTTP. La sicurezza IoT si basa su `IOT_REGISTRATION_PASSWORD` (solo al primo contatto) + broker MQTT (che in prod si abiliterà con autenticazione).

---

## 11. Topic MQTT

Broker: Mosquitto, anonimo ([`mqtt/config/mosquitto.conf`](mqtt/config/mosquitto.conf)).

### Station-wide (3 livelli `stazione/{mac}/<canale>`)

| Topic | Direzione | Payload | Note |
|---|---|---|---|
| `stazione/{mac}/ready` | Laravel → sim | `{ comando: "READY", id_punti: [...] }` | Admin completa setup. |
| `stazione/{mac}/codice` | sim → Laravel | `{ codice: "NNNNNN", scadenza: 60 }` | Ogni 30s, codice valido per qualsiasi punto. |
| `stazione/{mac}/comandi` | Laravel → sim | `{ comando: "autenticazione_completata", id_stazione }` | Notifica generica (per Arduino). |
| `stazione/{mac}/manutenzione` | Laravel → sim | `{ comando: "manutenzione", on: bool }` | Toggle dall'admin. |

### Per-punto (4 livelli `stazione/{mac}/{id_punto}/<canale>`)

| Topic | Direzione | Payload | Note |
|---|---|---|---|
| `stazione/{mac}/{id_punto}/heartbeat` | sim → Laravel | `{ id_stazione, id_punto, stato, ts }` | Ogni 60s, retain=true. |
| `stazione/{mac}/{id_punto}/eventi` | sim → Laravel | `{ evento: "cavo_collegato" \| "cavo_scollegato" \| "batteria_piena", id_sessione?, kwh_totali? }` | |
| `stazione/{mac}/{id_punto}/telemetria` | sim → Laravel | `{ id_sessione, voltaggio, corrente, intervallo_sec }` | Ogni 5s durante la ricarica. |
| `stazione/{mac}/{id_punto}/comandi` | Laravel → sim | `{ comando: "START", id_sessione }` o `{ comando: "STOP", id_sessione }` | |

### Worker MQTT

[`MqttWorker`](backend/src/app/Console/Commands/MqttWorker.php) usa **una sola** sottoscrizione `stazione/#` con routing manuale per numero di livelli (evita problemi della libreria PHP-MQTT con subscribe sovrapposte sullo stesso client). Vedi `handle()` per il dispatch.

---

## 12. WebSocket (Reverb)

Server Reverb su `localhost:8080`. Il client browser usa `Echo` (CDN) + `Pusher` JS protocol.

### Canali pubblici

| Canale | Eventi | Frontend ascolta |
|---|---|---|
| `mappa` | `.punto.status`, `.punto.hardware.status`, `.stazione.status` | [`map.blade.php`](backend/src/resources/views/map.blade.php) |
| `punto.{macNorm}.{id_punto}` | `.punto.status` | [`gamification-profile.blade.php`](backend/src/resources/views/gamification-profile.blade.php) |
| `stazione.{macNorm}` | `.punto.hardware.status`, `.stazione.status` | (libero per uso futuro pagina dettaglio stazione) |

> `macNorm` = MAC senza `:` (es. `AABBCCDDEEFF`). Pusher/Reverb **non accettano `:`** nei nomi canale. Backend e frontend usano la stessa regola di normalizzazione.

### Canali privati

| Canale | Auth | Eventi |
|---|---|---|
| `user.{id_utente}` | [`channels.php`](backend/src/routes/channels.php) controlla `$user->id_utente === $id_utente` | `.sessione.avviata`, `.ricarica.heartbeat` |

### Eventi

| Classe | broadcastAs | Quando |
|---|---|---|
| [`SessioneAvviata`](backend/src/app/Events/SessioneAvviata.php) | `sessione.avviata` | sessione creata su DB → notifica solo l'utente |
| [`TelemetriaRicevuta`](backend/src/app/Events/TelemetriaRicevuta.php) | `ricarica.heartbeat` | ogni telemetria → solo l'utente |
| [`PuntoStatusChanged`](backend/src/app/Events/PuntoStatusChanged.php) | `punto.status` | punto si libera/occupa → mappa + utente |
| [`PuntoHardwareStatusChanged`](backend/src/app/Events/PuntoHardwareStatusChanged.php) | `punto.hardware.status` | punto va offline/online → mappa |
| [`StazioneStatusChanged`](backend/src/app/Events/StazioneStatusChanged.php) | `stazione.status` | stazione cambia stato aggregato |

Tutti implementano `ShouldBroadcastNow` (broadcast sincrono, no queue).

---

## 13. Gamification

### Modello

Su `gamification_profilo_utente` ogni utente ha:
- `xp_totali`, `livello` (generated column da formula), `co2_risparmiata_kg`, `streak_giorni`, `data_ultima_ricarica`.

### Aggiornamento

[`GamificationService::aggiorna`](backend/src/app/Services/GamificationService.php) viene chiamato da `SessioneService::termina`:

1. `xp = max(5, kWh × 10)` (intero, minimo 5 per sessione).
2. `co2 = kWh × 0.233` (fattore ISPRA emissioni rete IT 2023).
3. UPDATE `gamification_profilo_utente` incrementale + ricalcolo streak.
4. Itera il catalogo `gamification_badge_catalogo` e sblocca quelli appena maturati.

### Catalogo badge

[`Gamification_badge_catalogoSeeder`](backend/src/database/seeders/Gamification_badge_catalogoSeeder.php) carica 8 badge:

| Codice | Condizione |
|---|---|
| `PRIMA_RICARICA` | 1 sessione completata |
| `VETERANO` | 10 sessioni |
| `PRIMI_KWH` | 10 kWh totali |
| `CENTOKWH` | 100 kWh totali |
| `ECO_BRONZE` | 10 kg CO₂ risparmiati |
| `ECO_CHAMPION` | 50 kg CO₂ |
| `SETTIMANA_GREEN` | streak 7 giorni |
| `LIVELLO_5` | livello ≥ 5 |

Tipi di condizione supportati dal service: `conteggio_sessioni`, `soglia_kwh_totali`, `soglia_co2`, `streak_giorni`, `livello_minimo`.

---

## Allegati: file chiave per partire

- **Bootstrap colonnina:** [`simulatore/stazione.py:Stazione.registra`](simulatore/stazione.py)
- **Setup admin:** [`AdminController::completaSetupStazione`](backend/src/app/Http/Controllers/AdminController.php)
- **Verifica codice:** [`SessionController::AutenticazioneCodice`](backend/src/app/Http/Controllers/Api/SessionController.php)
- **MQTT routing:** [`MqttWorker::handle`](backend/src/app/Console/Commands/MqttWorker.php)
- **SP DB:** [`0002_creazione_sp_sessione_ricarica.php`](backend/src/database/migrations/0002_creazione_sp_sessione_ricarica.php)
- **Catalogo badge:** [`Gamification_badge_catalogoSeeder.php`](backend/src/database/seeders/Gamification_badge_catalogoSeeder.php)
