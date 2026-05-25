# Green School Project — Applicazione Laravel

Questa cartella contiene il codice **Laravel 11** del backend: API REST, sito web,
pannello admin, worker MQTT e WebSocket.

> Questo non è un progetto Laravel generico: fa parte del **Green School Project**.

## Documentazione di riferimento

Per capire il sistema parti **sempre** da questi tre documenti, in quest'ordine:

1. [`../README.md`](../README.md) — sintesi del backend (cosa fa, container, API,
   autenticazione, controller, models, services).
2. [`../../README.md`](../../README.md) — README principale del progetto: come avviare lo
   stack, ngrok, dati di esempio, Postman, comandi utili.
3. [`../../ARCHITECTURE.md`](../../ARCHITECTURE.md) — architettura completa e tutti i
   flussi step-by-step (registrazione colonnina, manutenzione, codice monouso, avvio
   sessione, telemetria, terminazione, heartbeat, gamification, login/registrazione).
   È la fonte di verità per *come funzionano davvero* le cose dietro le quinte: API
   chiamate, eventi WebSocket, messaggi MQTT, query SQL, eventuali race condition.

## Avvio rapido

Il backend non si avvia da qui a mano: gira nei container Docker definiti in
`docker-compose.yaml`. Dalla radice del progetto:

```bash
docker compose up -d
docker exec -it green_app php artisan migrate:fresh --seed
```

Per ricostruire la cache di Laravel dopo modifiche pesanti:

```bash
docker exec -it green_app php artisan optimize:clear
```

## Struttura (cosa cercare dove)

| Cartella                                          | Contenuto                                                                 |
|---------------------------------------------------|--------------------------------------------------------------------------|
| [`app/Http/Controllers/`](app/Http/Controllers/)  | Controller HTTP. Sottocartella `Api/` per JSON (React, Postman, Python). I controller a livello root sono per il sito Blade. |
| [`app/Http/Middleware/`](app/Http/Middleware/)    | `AdminMiddleware` (Blade), `AdminApiMiddleware` (API), `RedirectIfAuthenticated` (guest). Aliasati in `bootstrap/app.php`. |
| [`app/Services/`](app/Services/)                  | Logica di dominio condivisa tra controller e worker. Iniettata via constructor injection. `SessioneService` è il più grande. |
| [`app/Console/Commands/`](app/Console/Commands/)  | Worker artisan persistenti: `MqttWorker` (`mqtt:leggi`) consuma MQTT, `HeartbeatChecker` (`app:heartbeat-checker`) marca offline i punti silenti. |
| [`app/Events/`](app/Events/)                      | Classi `ShouldBroadcastNow` che Reverb push verso il browser. Una per evento WebSocket. |
| [`app/Models/`](app/Models/)                      | Modelli Eloquent. Nomi in italiano (`Utenti`, `Stazioni`, `Punti_ricarica`, `Sessioni_ricarica`) per coerenza col dominio. |
| [`database/migrations/`](database/migrations/)    | Schema DB. Include anche stored procedure (`sp_avvio_sessione`, `sp_termina_sessione`, `sp_verifica_disponibilita`) e trigger MySQL (UUID auto, coordinate, check sessione aperta). |
| [`database/seeders/`](database/seeders/)          | Dati di test: utenti default, badge catalogo, profilo scuola, consumi mensili, sfide settimanali. |
| [`routes/api.php`](routes/api.php)                | Rotte JSON. Tre gruppi: pubbliche, `auth:sanctum`, `auth:sanctum + admin.api`. |
| [`routes/web.php`](routes/web.php)                | Rotte HTML. Login Blade, sito, admin Blade. |
| [`routes/channels.php`](routes/channels.php)      | Authorize dei canali WebSocket privati (`user.{id_utente}`). |
| [`resources/views/`](resources/views/)            | Viste Blade (`map.blade.php`, `station-detail.blade.php`, `admin/*`, `gamification-profile.blade.php`, `session-active.blade.php`, ecc.). |
| [`config/services.php`](config/services.php)      | Configurazione MQTT broker e password globale IoT registrazione. |

## Mappa veloce dei file critici

Se devi capire o modificare uno dei flussi principali, questi sono i punti di entrata:

- **Login / registrazione utente** → `Api/AuthController`, `Api/RegisterController`,
  `WebAuthController`. Modello `Utenti`. Tabella `personal_access_tokens` (Sanctum).
  Flusso completo in `ARCHITECTURE.md` §10.
- **Registrazione colonnina IoT** → `Api/IotController::Registra`. Endpoint pubblico
  protetto da password globale. Flusso in `ARCHITECTURE.md` §1.
- **Setup stazione (admin)** → `Api/AdminApiController::setupStazione` /
  `completaSetupStazione`. Pubblica MQTT `stazione/{mac}/ready` al termine. Flusso §2.
- **Manutenzione** → due controller paralleli: `AdminController::toggleStazione` (Blade)
  e `Api/AdminApiController::toggleStazione` (React). **Devono restare allineati**:
  stessa logica, output diverso. Flusso §3.
- **Codice monouso** → `Services/CodiceMonousoService` (Redis). Verifica in
  `Api/SessionController::AutenticazioneCodice`. Flusso §4.
- **Avvio sessione** → `Services/SessioneService::avvia` chiama
  `sp_avvio_sessione`. Triggerato da `MqttWorker::gestisciCavoCollegato` dopo evento
  `cavo_collegato`. Flusso §5.
- **Telemetria / kWh live** → `MqttWorker::gestisciTelemetria` →
  `SessioneService::aggiungiKwh` (Redis) → dispatch `TelemetriaRicevuta`. Flusso §6.
- **Terminazione sessione** → `SessioneService::termina` → `sp_termina_sessione` +
  `GamificationService::aggiorna`. Triggerata da `cavo_scollegato`/`batteria_piena`
  (MQTT) o da `POST /api/session/{id}/stop`. Flusso §7.
- **Heartbeat / online-offline** → `MqttWorker::gestisciHeartbeat` (scrittura
  timestamp, con guard manutenzione) + `Console/Commands/HeartbeatChecker` (loop
  60s, soglia 120s). Flusso §8.
- **WebSocket auth** → due endpoint nello stesso `bootstrap/app.php`/`api.php`:
  `/broadcasting/auth` (cookie sessione, Blade) e `/api/broadcasting/auth` (Bearer,
  React). Logica di autorizzazione canale in `routes/channels.php`.

## Convenzioni di codice

- **Nomi italiani** per modelli e tabelle quando coerenti col dominio: `Utenti`,
  `Stazioni`, `Punti_ricarica`, `Sessioni_ricarica`. Inevitabilmente si discosta dalla
  convenzione Laravel (`User`, `posts`), ma rende il codice più leggibile a chi
  conosce il glossario della scuola.
- **PK composta `(id_stazione, id_punto)`** per `punti_ricarica`: `id_punto` è
  locale alla stazione (`"1"`, `"2"`, …), non un UUID globale. Ogni query che tocca i
  punti deve filtrare su entrambe le colonne — vedi i trigger MySQL per le check di
  sessione aperta.
- **`id_stazione` = MAC address** della colonnina (`AA:BB:CC:DD:EE:FF`, uppercase
  normalizzato lato server). Niente UUID per le stazioni.
- **Stored procedure transazionali** per le operazioni critiche sulle sessioni
  (`sp_avvio_sessione`, `sp_termina_sessione`). Sono il punto di verità per
  l'integrità — i controller le chiamano e non si occupano di transazioni.
- **`ShouldBroadcastNow` ovunque** per gli eventi WebSocket: niente queue worker,
  arrivano al browser in tempo reale. Trade-off: il dispatch è sincrono dentro la
  richiesta HTTP/MQTT che li scatena.
- **Redis come stato volatile**: codice monouso (`codice:{mac}`, TTL 60s),
  rendez-vous codice→cavo (`codice_pending:{mac}`, TTL 60s, SETNX), kWh live
  (`sessione_kwh:{id}`, TTL 24h). I dati definitivi vanno su MariaDB solo alla
  chiusura della sessione.

## Cosa NON fare

- ❌ Modificare uno dei due `toggleStazione` (Blade o API) senza modificare anche
  l'altro: ogni cambio di comportamento sulla manutenzione va replicato in
  `AdminController` e `Api/AdminApiController`. Vedi `ARCHITECTURE.md` §3 per la
  motivazione e §10 per il pattern in generale.
- ❌ Aggiungere logica di business nei controller: vanno nei `Services/`. I
  controller sono "traduttori" tra HTTP/MQTT e dominio.
- ❌ Scrivere `stato_hardware` con valori arbitrari: l'enum DB accetta solo
  `online | offline | guasto | manutenzione_programmata`. I primi due li gestisce
  `HeartbeatChecker` automaticamente; `manutenzione_programmata` lo scrive solo il
  toggle manutenzione; `guasto` è riservato a uso futuro e oggi nessuno lo scrive.
- ❌ Far passare heartbeat / fine sessione via HTTP: dopo `/iot/registra` la colonnina
  parla **solo** MQTT. Non esistono endpoint REST device-autenticati per quello.
- ❌ Cancellare una sessione attiva con DELETE diretto: usa
  `SessioneService::termina` perché chiude correttamente Redis, gamification,
  broadcast WS, MQTT STOP.
