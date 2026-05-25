# Green School — Architettura

Documento didattico/tecnico: spiega **come** funziona l'app **e perché** funziona così.

- **Parte I — Concetti**: cosa sono MQTT, WebSocket, Redis, Sanctum, perché abbiamo
  scelto un certo design DB. Pensata per chi non conosce ancora la tecnologia.
- **Parte II — Flussi dell'app**: come si registra una colonnina, come parte una sessione,
  come viene gestita la telemetria, come si chiude la sessione, manutenzione, gamification.
- **Parte III — Reference**: lista API, topic MQTT, canali WebSocket, eventi, schema DB.

Per setup e comandi vedi [`README.md`](README.md).

---

## Indice

**Parte I — Concetti**
- [A. Architettura a servizi](#a-architettura-a-servizi)
- [B. MQTT: comunicazione coi dispositivi](#b-mqtt-comunicazione-coi-dispositivi)
- [C. WebSocket (Reverb): broadcast verso il browser](#c-websocket-reverb-broadcast-verso-il-browser)
- [D. Redis: stato volatile e race-free](#d-redis-stato-volatile-e-race-free)
- [E. Autenticazione: Sanctum e device password](#e-autenticazione-sanctum-e-device-password)
- [F. Design del database](#f-design-del-database)

**Parte II — Flussi**
- [1. Registrazione colonnina](#1-registrazione-colonnina)
- [2. Setup stazione (admin)](#2-setup-stazione-admin)
- [3. Manutenzione](#3-manutenzione)
- [4. Codice monouso](#4-codice-monouso)
- [5. Avvio sessione](#5-avvio-sessione-di-ricarica)
- [6. Telemetria e kWh live](#6-telemetria-e-kwh-live)
- [7. Terminazione sessione](#7-terminazione-sessione)
- [8. Heartbeat e rilevamento offline](#8-heartbeat-e-rilevamento-offline)
- [9. Gamification](#9-gamification)
- [10. Login e registrazione utente](#10-login-e-registrazione-utente)

**Parte III — Reference**
- [11. API REST](#11-api-rest)
- [12. Middleware](#12-middleware)
- [13. Topic MQTT](#13-topic-mqtt)
- [14. WebSocket](#14-websocket)
- [15. Schema DB completo](#15-schema-db-completo)

---

# Parte I — Concetti

## A. Architettura a servizi

Il sistema è composto da **container Docker indipendenti** che si parlano via tre canali distinti:

```
┌────────────┐                                ┌────────────┐
│  Browser   │ ── HTTPS ─────► Laravel (app)──┤ MariaDB    │
│            │                    │           └────────────┘
│  (Echo JS) │ ◄── WSS ── Reverb  │ ── MQTT ──┐
└────────────┘                    │           │
                                  │           ▼
                                  │       ┌────────────┐
                                  │       │ Mosquitto  │ broker MQTT
                                  │       └────────────┘
                                  │           ▲
                                  │           │ MQTT
                                  │           │
                                  │     ┌────────────┐
                                  │     │ Arduino /  │
                                  │     │ simulatore │
                                  │     └────────────┘
                                  ▼
                              ┌─────────┐
                              │  Redis  │
                              └─────────┘
```

**Perché tanti servizi separati?**

- **Laravel** è bravo a fare HTTP + DB ma fa schifo a tenere connessioni TCP persistenti
  (come quelle MQTT). Quindi tiene un **worker dedicato** (`mqtt-worker`) che è un processo
  Laravel che gira in foreground e fa solo quello.
- **Reverb** è un server WebSocket scritto in PHP che parla il protocollo **Pusher**. Lo
  abbiamo separato dall'app HTTP perché un server WebSocket deve tenere migliaia di
  socket aperti — Apache/PHP-FPM non sono fatti per questo.
- **Redis** sta a parte perché serve a tutti (Laravel HTTP, worker, queue, cache) come
  store comune e *thread-safe*.

---

## B. MQTT: comunicazione coi dispositivi

### Cos'è MQTT

**MQTT** (Message Queue Telemetry Transport) è un protocollo di messaggistica
*publish/subscribe* progettato per dispositivi IoT (bassi consumi, reti instabili,
payload piccoli). I tre attori sono:

- **Publisher**: chi pubblica un messaggio.
- **Subscriber**: chi riceve i messaggi.
- **Broker**: il mediatore (per noi Mosquitto). I publisher e i subscriber **non si
  conoscono fra di loro**: parlano solo col broker.

```
Publisher ──msg──► Broker ──msg──► Subscriber A
                       │
                       └──msg────► Subscriber B
```

> Concetto chiave: il **disaccoppiamento**. La colonnina non deve sapere chi è "Laravel",
> e Laravel non deve sapere quali colonnine esistono: si scambiano messaggi attraverso
> un *topic*.

### Topic

Un **topic** è una stringa gerarchica con `/`, tipo `stazione/AA:BB:CC:DD:EE:FF/1/heartbeat`.
Il subscriber può iscriversi a un topic preciso o usare **wildcard**:
- `+` matcha **un** livello (es. `stazione/+/codice` matcha `stazione/X/codice` ma non `stazione/X/Y/codice`).
- `#` matcha **zero o più** livelli (es. `stazione/#` matcha qualsiasi cosa che comincia con `stazione/`).

### QoS (Quality of Service)

Tre livelli:
- **0** "at most once": send-and-forget. Veloce, ma se il broker è giù il messaggio è perso.
- **1** "at least once": il broker garantisce la consegna; può duplicare se ACK perso.
- **2** "exactly once": il più costoso, handshake a 4 vie.

> Noi usiamo QoS 1 per heartbeat/telemetria (`retain=true` per heartbeat: il broker
> conserva l'ultimo, utile a nuovi subscriber). QoS 0 per i comandi rapidi.

### Perché funziona bene per noi

- Le colonnine non hanno un IP pubblico raggiungibile: si **connettono al broker** in uscita.
- Il backend può **mandare comandi** alle colonnine via lo stesso canale, senza dover
  aprire connessioni inbound verso ogni dispositivo.
- Il broker fa **fan-out**: se 100 utenti aprono la mappa, il broker non gli manda 100 copie
  — è Laravel che inoltra agli interessati via WebSocket.

### Nel nostro progetto

- Broker: **Mosquitto** (container `green_mqtt-broker`), porte 1883 (MQTT) e 9001 (MQTT
  over WebSocket). In dev `allow_anonymous true`.
- Subscriber backend: `mqtt-worker` con un solo SUBSCRIBE wildcard `stazione/#` che
  matcha **tutto** quello che ci interessa. Il routing per canale lo facciamo in PHP
  in base al numero di livelli del topic ricevuto. Perché una sola sub? Alcune versioni
  di `php-mqtt/client` hanno problemi con sub multiple sovrapposte sullo stesso client
  persistente (un filter "vince" sull'altro), e usare `#` ci toglie quel problema.

---

## C. WebSocket (Reverb): broadcast verso il browser

### Cos'è un WebSocket

HTTP è **request/response**: il client chiede, il server risponde, fine. Se il server
ha qualcosa di nuovo da dire deve aspettare la prossima richiesta. Con il **polling**
(il browser chiede ogni N secondi "novità?") si genera traffico inutile.

Un **WebSocket** è una connessione TCP *full-duplex* aperta tra browser e server, su cui
entrambi possono mandare messaggi in qualsiasi momento. Costo: il server deve tenere la
socket aperta (un processo PHP-FPM normale non può, scade dopo la response). Per questo
esistono server specializzati come **Reverb**.

```
Browser ── ws://server/app/{key} ──► Reverb (persistente)
   ▲                                    │
   │       evento push                  │
   └────────────────────────────────────┘
```

### Pusher protocol

**Reverb** parla il protocollo di **Pusher** (servizio commerciale popolare). Il client
JavaScript (`Laravel Echo` + `Pusher.js`) si connette, si **sottoscrive** a uno o più
**canali**, e Reverb gli invia gli eventi pubblicati su quei canali.

### Canali pubblici vs privati

- **Public channel**: chiunque può sottoscrivere. Per dati non sensibili (mappa generale,
  stato delle stazioni). Esempio nel progetto: `mappa` (vedi [`useStationsEcho.js`](frontend/src/hooks/useStationsEcho.js)).
- **Private channel**: prima della sottoscrizione il client deve **dimostrare al server
  di essere autorizzato** a quel canale specifico. Per dati per-utente (kWh del SUO
  punto di ricarica, eventi della SUA sessione). Esempio: `user.{id_utente}`.

### Come funziona davvero la sottoscrizione a un canale privato

Quando il JS chiama `echo.private('user.42')`, parte un protocollo a 4 step:

```
1. Echo apre la WS verso Reverb (è una connessione anonima — non sa ancora chi sei).
2. Echo manda al SERVER LARAVEL (non a Reverb!) un POST /broadcasting/auth con:
      { socket_id: "12345.67890", channel_name: "private-user.42" }
3. Laravel guarda chi sei (middleware auth), poi chiama la closure di channels.php
   che corrisponde al pattern del canale. Se torna true, Laravel firma un token
   HMAC con la chiave dell'app e lo restituisce a Echo.
4. Echo manda il token a Reverb sul canale WS. Reverb verifica la firma con la
   stessa chiave e, se ok, ti iscrive al canale e da quel momento ti inoltra
   gli eventi.
```

Punto importante: **Reverb non parla con Laravel direttamente**. È il browser che fa
da messaggero tra Laravel e Reverb portando il token firmato. Reverb non sa chi sei,
sa solo che hai un token valido firmato dalla stessa chiave dell'app.

La closure che decide se autorizzare sta in [`channels.php`](backend/src/routes/channels.php):

```php
Broadcast::channel('user.{id_utente}', function ($user, $id_utente) {
    return $user->id_utente === $id_utente;   // solo tu puoi ascoltare il TUO canale
});
```

Il parametro `$user` arriva dal middleware di autenticazione del route `/broadcasting/auth`.
Qui sta il dettaglio cruciale del prossimo paragrafo.

### Il problema: Blade ha la sessione, React ha solo un token

Il route `/broadcasting/auth` viene registrato automaticamente da
[`bootstrap/app.php`](backend/src/bootstrap/app.php) tramite `withBroadcasting()`, e di
default usa il middleware **`web`** (sessione cookie + CSRF). Questo va benissimo per
Blade, che ha la sessione e manda l'`X-CSRF-TOKEN` con il `<meta>`:

```js
// session-active.blade.php
authEndpoint: '/broadcasting/auth',
auth: {
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
},
```

React invece autentica via Sanctum **Bearer token**, non ha sessione cookie e non ha
CSRF. Se React usasse lo stesso `/broadcasting/auth` riceverebbe 401 o redirect al
login, Echo non si sottoscriverebbe, e gli eventi privati non arriverebbero mai
(sintomo classico: i kWh sembrano "congelati" durante la ricarica).

**Soluzione**: c'è un secondo endpoint registrato esplicitamente in
[`routes/api.php`](backend/src/routes/api.php), protetto via Sanctum:

```php
// Stesso channels.php, ma con middleware diverso e prefisso /api
Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api']);
```

Effetto: oltre a `POST /broadcasting/auth` (per Blade) esiste anche
`POST /api/broadcasting/auth` (per React). Echo lato React lo configura così
([`useSessionChannel.js`](frontend/src/hooks/useSessionChannel.js)):

```js
new Echo({
  broadcaster: 'reverb',
  // ...
  authEndpoint: '/api/broadcasting/auth',   // <-- override del default
  auth: {
    headers: {
      Authorization: `Bearer ${token}`,    // Sanctum token, NO CSRF
      Accept: 'application/json',
    },
  },
});
```

Entrambi gli endpoint chiamano alla fine la **stessa** closure di `channels.php`. Cambia
solo COME è stato autenticato l'utente prima.

### Schema riassuntivo

```
       ┌──────────── Blade ──────────┐         ┌──────────── React ──────────┐
       │                              │         │                              │
Echo ──► POST /broadcasting/auth  ────┤    Echo ──► POST /api/broadcasting/auth┤
       │   middleware: web            │         │   middleware: auth:sanctum   │
       │   auth: cookie sessione      │         │   auth: Bearer token         │
       │   CSRF richiesto             │         │   CSRF non richiesto         │
       │                              │         │                              │
       └─────────────┬────────────────┘         └──────────────┬───────────────┘
                     │                                         │
                     └──────────► channels.php ◄──────────────┘
                                  closure decide  → firma HMAC  → Reverb iscrive
```

Pubblici e privati hanno lo stesso costo di trasporto; cambia solo l'**handshake
iniziale** di autorizzazione. Una volta iscritti, un evento `ShouldBroadcastNow`
arriva con la stessa latenza in entrambi i casi.

### `ShouldBroadcast` vs `ShouldBroadcastNow`

Quando un evento Laravel implementa una delle due interfacce:
- `ShouldBroadcast`: il broadcast passa per la **queue** (asincrono, può ritardare).
- `ShouldBroadcastNow`: broadcast **sincrono**, parte subito appena dispatch.

> Per noi `ShouldBroadcastNow` ovunque: gli aggiornamenti devono arrivare nell'istante,
> non con secondi di delay dovuti alla queue.

### Naming dei canali

Pusher (e quindi Reverb) **non accetta** `:` nei nomi canale. Il nostro MAC è
`AA:BB:CC:DD:EE:FF`: lo normalizziamo a `AABBCCDDEEFF` (senza `:`) prima di costruire
il nome canale. Stesso schema lato backend (`str_replace`) e lato JS (`replace(/:/g, '')`)
così frontend e backend usano lo stesso nome.

### Differenza con MQTT

| | MQTT | WebSocket (Reverb) |
|---|---|---|
| Chi parla | dispositivi IoT ↔ backend | browser ↔ backend |
| Protocollo | MQTT (binario, leggero) | Pusher (JSON su WS) |
| Per cosa | telemetria, comandi, heartbeat | aggiornamenti live UI |
| Persistenza messaggi | retain flag | nessuna |

I due canali sono **complementari**: il dispositivo manda un messaggio MQTT, il
mqtt-worker lo elabora e *poi* dispatcha un evento broadcast che Reverb spinge al
browser. Non c'è un MQTT diretto verso il browser (anche se Mosquitto supporterebbe
MQTT-over-WS sulla porta 9001, in questo progetto non lo usiamo).

---

## D. Redis: stato volatile e race-free

### Cos'è Redis

**Redis** è un *key-value store* in RAM. Velocità sub-millisecondo. Lo usiamo come:
- **Cache** Laravel (chiave → valore con TTL).
- **Sessioni** Laravel (cookie → dati utente).
- **Queue** (job in attesa).
- **Lock distribuito** per evitare race condition.

### TTL (Time-To-Live)

Ogni chiave Redis può avere una scadenza: dopo N secondi viene cancellata
automaticamente. Utile per dati **volatili** che non hanno senso conservare.

> Esempio nel progetto: `codice:AABBCCDDEEFF → 482931` con TTL 60s. Dopo 60s il codice
> non vale più, Redis lo butta via senza che noi dobbiamo fare niente.

### SETNX (SET if Not eXists)

Operazione **atomica**: setta la chiave solo se non esiste ancora. Restituisce true
(ho impostato) o false (esisteva già). Usata per implementare un **lock**:

```
SETNX punto_lock:1 = "user_A"  →  true     (user_A vince)
SETNX punto_lock:1 = "user_B"  →  false    (user_B perde)
```

Se due utenti tentano nello stesso istante di prenotare un punto, **uno solo vince**.
Niente race condition. In Laravel: `Cache::add($key, $value, $ttl)` ritorna il booleano.

> Nel progetto: `codice_pending:{mac}` viene impostato con `Cache::add` quando un utente
> digita il codice valido. Se un secondo utente lo digita prima che il primo colleghi
> il cavo, la SETNX fallisce → risposta 409.

### Perché Redis e non MariaDB

Per dati che cambiano molto velocemente (es. kWh accumulati che si aggiornano ogni 5s
per ogni sessione attiva) scrivere su disco è uno spreco. Redis sta in RAM, è 100x più
veloce. Il valore *definitivo* invece va su DB alla chiusura della sessione (stored
procedure `sp_termina_sessione`).

### Cache::pull, Cache::get, Cache::put

L'API Laravel per Redis (via `Cache` facade):
- `put(key, value, ttl)` — scrivi.
- `get(key, default)` — leggi.
- `add(key, value, ttl)` — scrivi solo se non esiste (SETNX).
- `pull(key)` — leggi **e cancella** (atomico).
- `forget(key)` — cancella.

### Chiavi nel progetto

| Pattern | Cosa contiene | TTL | Service |
|---|---|---|---|
| `codice:{mac}` | codice monouso a 6 cifre | 60s | [`CodiceMonousoService`](backend/src/app/Services/CodiceMonousoService.php) |
| `codice_pending:{mac}` | `{id_utente}` di chi ha verificato il codice | 60s | [`SessioneService`](backend/src/app/Services/SessioneService.php) |
| `sessione_kwh:{id_sessione}` | kWh accumulati per la sessione | 24h | `SessioneService` |

---

## E. Autenticazione: Sanctum e device password

Nel progetto coesistono **tre sistemi di auth distinti** perché hanno requisiti diversi.

### 1. Sessione web (cookie)

Per l'utente che usa l'app dal browser: login con email/password, Laravel mette
`id_utente` in sessione, manda un cookie `laravel_session`. Le successive richieste web
arrivano col cookie e Laravel sa chi sei. È il classico web stateful.

Middleware: `auth`. Tutto ciò che è in `/admin` o `/profilo` usa questo.

### 2. Token Sanctum (API)

Per le chiamate `/api/*` che il JS del frontend fa verso Laravel (e per Postman).
Al login, Laravel emette un **Personal Access Token** (`laravel_sanctum`) e il
client lo passa come `Authorization: Bearer <token>`.

Middleware: `auth:sanctum`. Stesso utente, canale diverso.

> Perché 2 sistemi? Perché le chiamate AJAX da JS non vogliono passare per cookie+CSRF
> (più complicato cross-domain), e Sanctum è progettato per quello.

### 3. Password globale per device IoT

Le colonnine **non sono utenti**, sono dispositivi. Hanno una password unica
condivisa (`IOT_REGISTRATION_PASSWORD`) che usano **una sola volta** al primo
`POST /api/iot/registra`. Dopo, comunicano via MQTT senza autenticazione.

Niente token persistente, niente sessione. Volutamente minimale per ora — in prod si
potrà passare a un broker MQTT autenticato + certificati device.

### Channel auth (WebSocket)

Per i `PrivateChannel` (es. `user.{id_utente}`), Reverb chiama `/broadcasting/auth` con
il cookie/token Sanctum, Laravel valuta la closure in [`channels.php`](backend/src/routes/channels.php),
ritorna true/false. Senza questa, il browser non riceve nessun evento sul canale privato.

---

## F. Design del database

### Perché `id_stazione = MAC ADDRESS`

Una colonnina **conosce solo il proprio MAC** (è scritto nell'hardware). Se usassimo UUID
random, l'admin dovrebbe pre-registrare ogni colonnina con un UUID e configurarlo
dentro l'hardware — impossibile in produzione. Con il MAC come PK, la colonnina si
autoidentifica al primo contatto e non serve nessuna pre-configurazione lato DB.

### Perché `punti_ricarica` ha PK composta `(id_stazione, id_punto)`

L'hardware decide quanti punti ha e li numera localmente: punto 1, punto 2, … Lo schema
deve rispecchiare il dominio: "punto N della stazione X" è la vera identità del
connettore.

L'alternativa (id_punto come UUID globale) farebbe perdere il legame fisico: il sim
non saprebbe quale UUID corrisponde al suo connettore 1 senza un'altra round-trip al
backend. Composta è più naturale, anche se Eloquent non supporta PK composte
nativamente (noi tipicamente usiamo query builder `DB::table(...)->where(...)` per
queste tabelle).

### Perché esiste `stazioni.stato_setup` ma non `stazioni.stato_hardware`

- `stato_setup` è uno stato **del workflow**: "questa stazione esiste a DB ma non è
  ancora configurata". L'admin lo fa transitare a `attiva`.
- "online/offline" è invece uno stato **derivato**, che cambia in continuazione in base
  agli heartbeat dei punti. Mantenerlo come colonna richiederebbe UPDATE continui. Lo
  calcoliamo a runtime con una query:
  ```
  online = NOT in_manutenzione AND ∃ punto con stato_hardware='online'
  ```

### Stored procedure: perché

Le tre SP (`sp_verifica_disponibilita`, `sp_avvio_sessione`, `sp_termina_sessione`) fanno
operazioni **transazionali** (verifica + INSERT + UPDATE) che vanno tutte insieme o
niente. Implementarle in PHP richiederebbe un `DB::transaction()` con controlli
race-sensitivi. Su DB:
- la transazione è "vicina ai dati" → meno latenza
- gli `EXIT HANDLER FOR SQLEXCEPTION` ci garantiscono il rollback in caso di errore
- il `FOR UPDATE` su `sp_termina_sessione` blocca la riga della sessione finché finisce

### Trigger: a cosa servono

- `trg_set_stazioni_coordinata_ins/upd`: trasforma automaticamente
  `latitudine + longitudine` in un punto GEOMETRY per query spaziali. Trigger = nessuna
  duplicazione di logica in PHP.
- `trg_check_sessione_aperta`: garantisce che non possano esistere due sessioni aperte
  sullo stesso `(id_stazione, id_punto)`. Il check è atomico, fatto dal DB → evita race
  fra due `INSERT` simultanei che PHP non potrebbe prevenire (transazione separata).

---

# Parte II — Flussi

## 1. Registrazione colonnina

### Concetto

Le colonnine si auto-registrano al primo contatto. **Non c'è una pre-configurazione**
lato DB. Il backend si fida del MAC dichiarato (ce ne servirà il rispetto in prod tramite
certificati o whitelist), valida la password globale, e crea i record.

### Endpoint

`POST /api/iot/registra` (pubblico)

**Body JSON:**
```json
{ "mac": "AA:BB:CC:DD:EE:FF", "password": "greenschool-iot-2025", "numero_punti": 2 }
```

### Cosa succede ([`IotController::Registra`](backend/src/app/Http/Controllers/Api/IotController.php))

1. Valida `password` con `hash_equals` (timing-attack safe) contro `IOT_REGISTRATION_PASSWORD`.
2. Normalizza il MAC in uppercase.
3. Se la stazione **non esiste**:
   - INSERT `stazioni` con `stato_setup='in_setup'`, `in_manutenzione=false`.
   - Per `i = 1..N`: INSERT `punti_ricarica` con `id_punto = (string) i`, `tipo_veicolo = 'auto'` (placeholder), `stato_hardware = 'offline'`.
4. Se esiste già: idempotente. Restituisce comunque il payload (utile in caso di restart
   della colonnina o cambio MAC del simulatore).
5. Risposta JSON con `id_stazione`, `stato_setup`, `id_punti`, `mqtt.host/port`, lista
   topic da usare.

### Idempotenza e edge case

- Restart della colonnina → ri-chiama `registra`, riceve gli stessi dati, va avanti.
- `numero_punti` dichiarato diverso da quello a DB → log warning, **non tocca i record**
  (potrebbero esserci sessioni storiche legate ai punti esistenti).

---

## 2. Setup stazione (admin)

### Concetto

L'admin non crea la stazione: la colonnina l'ha già creata. L'admin compila i metadati
(nome, indirizzo, coordinate, e per ogni punto già dichiarato: tipo veicolo, connettore,
potenza). Senza setup, la stazione **non compare** sulla mappa pubblica.

### Flusso

1. Admin va su `/admin/stazioni` → vede la stazione "in_setup" col badge giallo.
2. Click su **Completa setup** → `GET /admin/stazioni/{mac}/setup` mostra il form
   precompilato con i punti già esistenti.
3. Submit `POST /admin/stazioni/{mac}/setup`:
   - Sanity check: gli `id_punto` inviati corrispondono **esattamente** a quelli a DB
     (l'admin non può aggiungere/togliere punti).
   - UPDATE `stazioni`: nome, indirizzo, lat/lng, `stato_setup='attiva'`.
   - UPDATE in place ogni `punti_ricarica` (no DELETE/INSERT).
   - **Publish MQTT** su `stazione/{mac}/ready` con la lista degli `id_punti`.
4. Il simulatore in ascolto su `stazione/{mac}/ready` riceve l'evento, crea in memoria
   gli oggetti `Punto_ricarica`, sottoscrive `stazione/{mac}/{id_punto}/comandi` per
   ognuno, lancia il thread di generazione codice e il thread di heartbeat.

---

## 3. Manutenzione

### Concetto

L'admin può fermare una stazione (per intervento fisico, malfunzionamento, ecc.) senza
toccare il DB nei punti delicati: imposta solo un **flag** sulla stazione, marca i punti
con uno stato hardware dedicato, manda un comando MQTT alla colonnina perché smetta di
generare codici e heartbeat, e broadcast WebSocket perché tutte le UI aperte (mappa,
pagina di dettaglio, admin) si aggiornino immediatamente sugli altri dispositivi.

Il flusso esiste in due copie speculari per i due frontend admin:
- **React** → `POST /api/admin/stazioni/{mac}/toggle` → [`AdminApiController::toggleStazione`](backend/src/app/Http/Controllers/Api/AdminApiController.php)
- **Blade** → form sull'admin classico → [`AdminController::toggleStazione`](backend/src/app/Http/Controllers/AdminController.php)

I due metodi eseguono **la stessa identica sequenza** (descritta sotto) e si differenziano
solo nel formato della risposta (JSON vs redirect+flash). Tenere sincronizzate le due
copie è una regola del team: ogni modifica deve essere replicata.

### Pre-condizione bloccante

Se la stazione ha **una sessione di ricarica attiva** (`sessioni_ricarica.data_fine IS NULL`),
il toggle in entrata viene rifiutato: fermare la stazione interromperebbe la sessione di
un utente. Risposta `409 Conflict` (API) o flash error (Blade). Il toggle in uscita
(fine manutenzione) non ha pre-condizioni.

### Step 1 — DB update sulla stazione

```sql
UPDATE stazioni SET in_manutenzione = NOT in_manutenzione WHERE id_stazione = ?
```

`in_manutenzione` è il flag autoritativo. Lo leggono:
- `MqttWorker::gestisciHeartbeat` per scartare heartbeat in volo (vedi Step 4).
- `AdminApiController::stazioni` / `dashboard` per il conteggio "offline" nella lista admin.
- I controller mappa per *non* sommare la stazione tra quelle libere.

### Step 2 — DB update sui punti

Tutti i punti della stazione vengono aggiornati a uno stato hardware dipendente dalla
direzione del toggle:

```php
$nuovoStatoHw = $nuovoStato ? 'manutenzione_programmata' : 'offline';
UPDATE punti_ricarica SET stato_hardware = $nuovoStatoHw WHERE id_stazione = ?
```

- **Entrata in manutenzione** → `'manutenzione_programmata'`. Stato dedicato che
  [`HeartbeatChecker`](backend/src/app/Console/Commands/HeartbeatChecker.php) **ignora
  completamente** (i due `if` controllano solo `'online'`/`'offline'`, e la query del
  calcolo `libera` aggregato esclude esplicitamente `'manutenzione_programmata'` con
  `whereNotIn`). Senza questo stato dedicato, HeartbeatChecker vedrebbe i punti come
  `'offline'` + heartbeat fresco e li rimetterebbe `'online'` ogni 60s, causando un
  "lampeggio" stato online → offline → online finché il simulatore non smette davvero
  di mandare heartbeat (~2 min dopo aver ricevuto il comando MQTT).
- **Uscita dalla manutenzione** → `'offline'`. Da qui il prossimo heartbeat MQTT valido
  riporta naturalmente i punti `'online'` via HeartbeatChecker (vedi flusso 6 e codice
  in [`HeartbeatChecker::controlloUnGiro`](backend/src/app/Console/Commands/HeartbeatChecker.php)).

### Step 3 — Broadcast WebSocket (canale `mappa`)

L'aggiornamento del DB da solo non basta: i browser hanno una **cache locale** delle
stazioni popolata al caricamento di `/api/stations` (vedi `useStationsEcho` in React,
`stazioniDataMap` nel Blade) e da quel momento si fidano solo degli eventi WebSocket per
aggiornarla. Senza eventi, gli altri dispositivi vedono ancora lo stato vecchio finché
non ricaricano la pagina.

Si dispatchano **due gruppi di eventi**:

**a) Un `StazioneStatusChanged` (solo entrando in manutenzione)**

```php
StazioneStatusChanged::dispatch($id, /*libera*/ false);
```
- Canale: `mappa` (pubblico).
- Evento: `.stazione.status`.
- Payload: `{ id_stazione, libera: false }`.
- Effetto sui listener: aggiornano `station.libera = false` nello state. Non cambia il
  colore del pallino (perché `markerColor` legge dai punti, non dall'aggregato) ma serve
  come segnale di "indisponibilità aggregata" per consumer futuri.

**b) Un `PuntoHardwareStatusChanged` per ogni punto della stazione (sempre, in entrata e in uscita)**

```php
foreach ($puntiIds as $idPunto) {
    PuntoHardwareStatusChanged::dispatch($idPunto, $nuovoStatoHw, $id);
}
```
- Canale: `mappa` (pubblico).
- Evento: `.punto.hardware.status`.
- Payload: `{ id_punto, stato_hardware, id_stazione }`.
- Effetto sui listener: aggiornano `punti_ricarica[i].stato_hardware` nella cache
  locale. Da qui:
  - **Mappa** (React in [`MapPage.jsx`](frontend/src/pages/MapPage.jsx) e Blade in
    [`map.blade.php`](backend/src/resources/views/map.blade.php)) ricalcola
    `markerColor()`. Siccome ora nessun punto ha `stato_hardware === 'online'`,
    `tuttiOffline` diventa `true` → pallino **grigio in tempo reale**.
  - **Pagina di dettaglio stazione** ([`StationDetailPage.jsx`](frontend/src/pages/StationDetailPage.jsx) e
    [`station-detail.blade.php`](backend/src/resources/views/station-detail.blade.php))
    aggiorna ogni card presa: classe `stato-offline`, dot grigio, badge "Offline",
    rimuove il bottone "Scegli". Ricalcola anche i chip "Libere/In uso/Totale".

Questo è il broadcast che porta l'UI aggiornata sugli **altri dispositivi** che hanno
una pagina aperta ma non hanno fatto l'azione: senza, devono cambiare pagina per
forzare un refetch.

### Step 4 — Publish MQTT verso la colonnina

```php
$mqtt->publish("stazione/{$id}/manutenzione", json_encode([
    'comando' => 'manutenzione',
    'on'      => $nuovoStato,
]));
```

- Topic: `stazione/{mac}/manutenzione`.
- Se `on=true`: il simulatore (o l'Arduino reale) ferma `_loop_codice` e `_loop_heartbeat`.
  Da quel momento non arrivano più heartbeat al backend.
- Se `on=false`: i loop ripartono. Il primo heartbeat che arriva tornerà online il punto
  via HeartbeatChecker entro 60s.

La pubblicazione MQTT è dentro un `try/catch`: se il broker è giù, il fallimento viene
loggato ma **il toggle non viene rolled-back**. Il DB e i broadcast WebSocket sono già
applicati, quindi l'admin vede comunque l'effetto. La colonnina resterà "viva" finché
non riceve l'ordine; al massimo dopo qualche ciclo di heartbeat persi diventerà offline
autonomamente (vedi flusso 6).

### Step 5 — Guard sul worker MQTT

Anche con lo Step 4 c'è una piccola finestra: tra il `publish` e il momento in cui il
simulatore riceve e processa il comando, può inviare ancora un heartbeat in coda. Per
evitare che `HeartbeatChecker` veda `data_ultimo_heartbeat` aggiornato e *si confonda*
quando la manutenzione termina (potrebbe rimettere `'online'` un punto prima che il
simulatore abbia davvero ripreso), [`MqttWorker::gestisciHeartbeat`](backend/src/app/Console/Commands/MqttWorker.php)
controlla `stazioni.in_manutenzione` prima di scrivere:

```php
if ($inManutenzione) {
    return; // scarta heartbeat
}
```

Cintura di sicurezza che rende l'intero flusso robusto anche con race condition di
qualche secondo tra publish MQTT e applicazione del comando lato dispositivo.

### Schema riassuntivo

```
   [admin]                              [backend]                              [altri dispositivi]
     │  POST /api/admin/stazioni/{mac}/toggle                                          │
     │ ─────────────────────────────►   │                                              │
     │                                  │  check sessione attiva → 409 se sì            │
     │                                  │                                              │
     │                                  │  UPDATE stazioni.in_manutenzione = true       │
     │                                  │  UPDATE punti_ricarica.stato_hardware =      │
     │                                  │      'manutenzione_programmata'              │
     │                                  │                                              │
     │                                  │  broadcast .stazione.status (libera=false)   │ ──►  cache.station.libera=false
     │                                  │  broadcast .punto.hardware.status (xN)       │ ──►  cache.punto.stato_hw=
     │                                  │                                              │      'manutenzione_programmata'
     │                                  │                                              │      → markerColor() → grigio
     │                                  │                                              │      → badge "Offline" sulle card
     │                                  │                                              │
     │                                  │  publish MQTT stazione/{mac}/manutenzione    │
     │                                  │      {on: true}                              │
     │                                  │     └──► simulatore ferma codice + heartbeat │
     │  200 / 409                       │                                              │
     │ ◄─────────────────────────────   │                                              │
     │                                  │                                              │
     │                                  │  heartbeat in volo arrivano → MqttWorker     │
     │                                  │      vede in_manutenzione=true → scarta      │
     │                                  │                                              │
     │                                  │  HeartbeatChecker (ogni 60s) ignora i punti  │
     │                                  │      'manutenzione_programmata' → nessun     │
     │                                  │      cambio stato, nessun broadcast spurio   │
```

Per il toggle in uscita la sequenza è identica con valori invertiti
(`in_manutenzione=false`, `stato_hardware='offline'`, MQTT `{on:false}`); il `StazioneStatusChanged`
NON viene dispatchato in uscita (la stazione torna libera solo quando un punto torna
davvero online via heartbeat, e a quel punto è HeartbeatChecker che dispatcha l'evento
con `libera=true`).

### Effetto sulla UI

- **Mappa pubblica** (React + Blade): pallino grigio in tempo reale su tutti i
  dispositivi connessi a Reverb.
- **Pagina di dettaglio stazione**: tutte le prese mostrano badge "Offline", nessun
  bottone "Scegli".
- **Tabella admin `stazioni`**: badge giallo "manutenzione" (il flag `in_manutenzione`
  arriva al frontend al prossimo refetch della lista, oppure dopo refresh).
- **Tentativo di verifica codice durante manutenzione**: il simulatore non genera più
  codici (loop fermo), quindi il codice in Redis scade entro 60s e tutti i tentativi
  successivi falliscono con `422 Codice non valido o scaduto`.

---

## 4. Codice monouso

### Concetto

Al posto di un QR code permanente, le colonnine generano un **codice a 6 cifre** che
cambia continuamente. L'utente lo legge dal display e lo digita nell'app. Vantaggi:
- Niente generazione/distribuzione di QR.
- Codice **vivo solo per ~60s**: anche se qualcuno lo intercetta, fra poco non vale più.
- Un solo codice per stazione (semplifica il display).

### Generazione (simulatore)

[`Stazione._loop_codice`](simulatore/stazione.py):
- Ogni `CODICE_INTERVAL` (default 30s):
  - Estrae `secrets.randbelow(1_000_000)` formattato a 6 cifre.
    Perché `secrets` e non `random`: `random` ha uno stato globale Mersenne Twister, in
    threading può avere comportamenti meno intuitivi. `secrets` legge da `os.urandom`
    (kernel CSPRNG) — *crypto-grade* e thread-safe.
  - Salva in `self.codice_attuale` (visibile dal menu interattivo).
  - Pubblica MQTT su `stazione/{mac}/codice` con `{ codice, scadenza: 60 }`.

### Storage (backend)

[`CodiceMonousoService`](backend/src/app/Services/CodiceMonousoService.php):
- Chiave Redis `codice:{id_stazione}` → `"NNNNNN"`, TTL **60s**.
- TTL > intervallo di generazione: c'è sovrapposizione tra codice vecchio e codice nuovo,
  così se la rete è lenta o l'utente è lento a digitare, il codice non scade di colpo
  appena ne arriva uno nuovo.

### Verifica

```php
foreach (stazioni 'attiva' as $s) {
    if (Cache::get("codice:{$s}") === $codice) return $s;
}
return null;
```

Iteriamo sulle stazioni attive (poche decine in dev): per ognuna controlliamo se il
codice corrente coincide con quello digitato. È O(n) lineare sulle stazioni — perfetto
per il volume previsto. Per produzione scalare basterebbe un indice inverso aggiuntivo
(`codice_lookup:{NNNNNN} → id_stazione`).

> Importante: il codice **non viene cancellato** al match. Resta valido fino alla
> scadenza naturale. L'unicità della prenotazione si gestisce a valle (SETNX, vedi
> sotto).

---

## 5. Avvio sessione di ricarica

### Concetto — "rendez-vous codice → cavo"

Il flusso è in **due fasi**, intercalate da un *rendez-vous* in Redis:

1. **Fase logica**: l'utente digita il codice → "intendo iniziare una ricarica su una
   delle prese di questa stazione".
2. **Fase fisica**: l'utente attacca il cavo a una presa → "voglio quella specifica
   presa".

Tra le due c'è una finestra di 60s: se l'utente non collega il cavo, la prenotazione
scade e la stazione torna libera. Il match avviene quando l'evento `cavo_collegato`
arriva, e in quel momento la sessione viene davvero creata su DB.

```
   [user]                  [Redis pending]              [colonnina]
     │  POST /{id_stazione}/verifica-codice                │
     │ ──────────►  SETNX codice_pending:{mac} = user      │
     │                       │                             │
     │  202 Attesa_Cavo      │ (TTL 60s)                   │
     │ ◄──────────           │                             │
     │                       │                             │
     │   attacca cavo        │    publish cavo_collegato   │
     │ ─────────────────────────────────────────────────►  │
     │                       │                             │
     │                       │  ◄── cavo_collegato         │
     │                       │      consume (pull)         │
     │                       │      ──► CALL sp_avvio_sessione
     │                       │      ──► publish START
     │                       │  ◄── START verso colonnina ►
```

### Step 1 — verifica codice

`POST /api/{id_stazione}/verifica-codice` ([`SessionController::AutenticazioneCodice`](backend/src/app/Http/Controllers/Api/SessionController.php))

L'`id_stazione` (il MAC) viaggia nel path: il client lo conosce già perché ha
aperto il dettaglio della stazione (`/station/{id}`).

1. Valida codice (6 cifre).
2. `CodiceMonousoService::verifica($idStazione, $codice)` confronta il codice
   digitato con quello salvato in Redis per QUELLA stazione. Ritorna `true`/`false`.
3. `Cache::add("codice_pending:{mac}", ['id_utente' => $userId], 60)` — **SETNX**.
   - Se false → 409 (un altro utente è già in attesa).
4. Publish MQTT su `stazione/{mac}/comandi` con `autenticazione_completata` (informativo,
   l'Arduino può accendere un LED).
5. Risposta 202 → frontend va in `/profilo` col banner "In attesa del cavo" e
   countdown 60s.

### Step 2 — cavo collegato

Il sim (o l'Arduino) pubblica `stazione/{mac}/{id_punto}/eventi { evento: "cavo_collegato" }`.

[`MqttWorker::gestisciCavoCollegato`](backend/src/app/Console/Commands/MqttWorker.php):
1. `$pending = $sessioni->consumaCodiceInAttesa($idStazione)` — `Cache::pull`
   (read-and-delete atomic).
2. Se null → log "nessun codice in attesa: ignorato" (cavo attaccato a freddo).
3. Altrimenti `$sessioni->avvia($idStazione, $idPunto, $pending['id_utente'])`.

### Step 3 — `sp_avvio_sessione`

[`SessioneService::avvia`](backend/src/app/Services/SessioneService.php):
1. `CALL sp_avvio_sessione(id_utente, id_stazione, id_punto, 'CODICE', null)`.
2. La SP fa: `sp_verifica_disponibilita` + INSERT in `sessioni_ricarica` + UPDATE
   `punti_ricarica.libera=0`, tutto in transazione. Se qualcosa fallisce, rollback.
3. Dispatch eventi broadcast: `SessioneAvviata` (canale privato utente),
   `PuntoStatusChanged` (canale mappa).
4. Publish MQTT `START` con l'`id_sessione` verso la colonnina.

### Step 4 — colonnina riceve START

Il sim riceve `START` su `stazione/{mac}/{id_punto}/comandi`:
1. Crea l'oggetto `Sessione` in memoria.
2. Lancia il thread `_loop_kwh` (simula la curva di carica).
3. Lancia il thread `_loop_telemetria` (pubblica V/I ogni 5s).

---

## 6. Telemetria e kWh live

### Concetto

La colonnina non manda direttamente i kWh: manda i **valori grezzi V (volt) e I (ampere)**
ad ogni intervallo. È il backend a calcolare il delta energia. Questo perché:
- I dati di base sono **misurabili dal hardware** (sensori di V/I).
- Il calcolo `kWh = (V × I × t) / 3.6e6` lo facciamo una volta sola, in un posto solo.

### Pubblicazione

[`Punto._loop_telemetria`](simulatore/stazione.py): ogni `METER_INTERVAL` (5s) pubblica:
```json
{ "id_sessione": "...", "voltaggio": 230.0, "corrente": 14.2, "intervallo_sec": 5 }
```
su topic `stazione/{mac}/{id_punto}/telemetria`.

### Consumo (worker)

[`MqttWorker::gestisciTelemetria`](backend/src/app/Console/Commands/MqttWorker.php):
1. `deltaKwh = (V * I / 1000) * (intervallo / 3600)`.
2. `SessioneService::aggiungiKwh($idSessione, $deltaKwh)`:
   - `$totale = Cache::get("sessione_kwh:{$id}", 0)`
   - `$totale += $deltaKwh`
   - `Cache::put("sessione_kwh:{$id}", $totale, 86400)`
3. Lookup `id_utente` dalla sessione, dispatch `TelemetriaRicevuta` sul canale privato
   `user.{id_utente}` (evento `.ricarica.heartbeat`).

> **Perché Redis e non DB?** Una sessione di 1h dura 720 incrementi (12/min × 60). Con 30
> sessioni concorrenti = 21.600 INSERT/h. Inutile: il valore finale lo scriviamo a DB
> alla chiusura. Durante la sessione i kWh stanno in Redis.

### Frontend live

[`gamification-profile.blade.php`](backend/src/resources/views/gamification-profile.blade.php):
```js
echo.private('user.' + idUtente).listen('.ricarica.heartbeat', e => {
    kwhCorrenti += Number(e.cambiamento_kwh) || 0;
    aggiornaKwhUI();
});
```

---

## 7. Terminazione sessione

### Due trigger possibili

| Da chi | Cosa fa |
|---|---|
| **Simulatore** (scollega cavo o batteria piena) | Publish `cavo_scollegato` / `batteria_piena` su `stazione/{mac}/{id_punto}/eventi` |
| **App utente** | `POST /api/session/{id}/stop` → [`SessionController::InterrompiSessione`](backend/src/app/Http/Controllers/Api/SessionController.php) |

Entrambi convergono in [`SessioneService::termina($idSessione, $kwh)`](backend/src/app/Services/SessioneService.php).

### Cosa fa `termina`

1. `CALL sp_termina_sessione($id, $kwh)`:
   - `SELECT id_stazione, id_punto, data_inizio FROM sessioni_ricarica WHERE id = ? AND data_fine IS NULL FOR UPDATE` — locka la riga.
   - Cerca la tariffa (`tariffa_predefinita` del punto, poi tabella `tariffe_orarie` per fascia).
   - Calcola `costo = round(kwh * tariffa, 2)`.
   - UPDATE `sessioni_ricarica.data_fine = NOW(), quantita_kwh, costo_totale, stato_pagamento`.
   - UPDATE `punti_ricarica.libera = 1`.
2. `GamificationService::aggiorna` per XP/CO₂/streak/badge.
3. Dispatch `PuntoStatusChanged(true)`, eventualmente `StazioneStatusChanged(true)`.
4. `Cache::forget("sessione_kwh:{$id}")` — il dato definitivo è ora su DB.

---

## 8. Heartbeat e rilevamento offline

### Concetto

Le colonnine inviano periodicamente un *heartbeat* per dimostrare di essere vive. Se il
backend smette di riceverne per un certo tempo, marca i punti offline. Quando ricominciano,
li rimarca online. Questo è il meccanismo che fa diventare grigio il pallino sulla mappa
quando una colonnina si scollega davvero (cavo Ethernet staccato, alimentazione tolta,
crash hardware) — senza richiedere alcuna azione manuale dell'admin.

Il ciclo coinvolge tre attori:
- **Simulatore / colonnina reale** che pubblica gli heartbeat su MQTT.
- **`MqttWorker`** che riceve l'heartbeat e aggiorna `data_ultimo_heartbeat` su DB.
- **`HeartbeatChecker`** (worker dedicato in container separato) che ogni 60s controlla i
  timestamp e decide chi è online/offline.

### Step 1 — Pubblicazione (simulatore)

[`Punto._loop_heartbeat`](simulatore/stazione.py): ogni 60s ogni punto pubblica:

- Topic: `stazione/{mac}/{id_punto}/heartbeat`
- QoS: 1, `retain: true` (il broker tiene l'ultimo heartbeat anche se nessuno è
  sottoscritto in quel momento — vedi Parte I, sezione B)
- Payload: `{ "stato": "ok", "ts": 1716624000 }` (timestamp Unix in secondi)

Il loop si ferma se la stazione riceve il comando `manutenzione {on: true}` (vedi flusso 3),
o se viene terminata l'istanza Python.

### Step 2 — Ricezione (MqttWorker)

[`MqttWorker::gestisciHeartbeat`](backend/src/app/Console/Commands/MqttWorker.php):

1. **Guard manutenzione**: legge `stazioni.in_manutenzione` per quel MAC. Se `true`,
   logga "Heartbeat ignorato" e **scarta il messaggio** senza scrivere nulla. Questo
   evita che un heartbeat in volo, arrivato tra il publish MQTT del comando manutenzione
   e il momento in cui il simulatore lo elabora, possa "rinfrescare" il timestamp e
   confondere HeartbeatChecker.
2. Estrae `ts` dal payload se presente e numerico, altrimenti `now()`.
3. UPDATE `punti_ricarica.data_ultimo_heartbeat = $ts` per quel `(id_stazione, id_punto)`.
4. UPDATE `stazioni.data_ultimo_heartbeat = $ts` (a livello stazione, ridondante ma
   comodo per query rapide tipo "qual è stata l'ultima volta che ho sentito la
   colonnina X?" senza dover joinare i punti).

**Importante**: questo step **non cambia mai `stato_hardware`**. Aggiorna solo il
timestamp. Il passaggio online/offline è demandato a HeartbeatChecker, che ha
una vista globale.

### Step 3 — Controllo periodico (HeartbeatChecker)

Container dedicato `green_heartbeat_checker` che esegue il comando Artisan
`app:heartbeat-checker` (vedi [`HeartbeatChecker`](backend/src/app/Console/Commands/HeartbeatChecker.php)).
Loop infinito: `controlloUnGiro()` ogni `INTERVALLO_CHECK_SEC` (60s).

Per ogni stazione (eager load `puntiRicarica`):

Per ogni punto, calcola `heartbeatScaduto`:
```php
$heartbeatScaduto = $punto->data_ultimo_heartbeat === null
    || $punto->data_ultimo_heartbeat < now()->subSeconds(120);
```
La soglia è **2 minuti = 2 heartbeat persi consecutivi**, abbastanza tollerante per non
scattare al primo singhiozzo di rete ma abbastanza reattivo per accorgersi di un'offline
reale entro 3 minuti.

Quattro casi possibili — il codice gestisce esplicitamente solo due:

| `stato_hardware` | `heartbeatScaduto` | Azione |
|---|---|---|
| `'online'` | `true` | UPDATE → `'offline'` + broadcast `.punto.hardware.status` |
| `'offline'` | `false` | UPDATE → `'online'` + broadcast `.punto.hardware.status` |
| `'manutenzione_programmata'` | qualsiasi | **ignorato** (skip totale) |
| `'guasto'` | qualsiasi | **ignorato** (riservato a uso futuro, non scritto da nessuno oggi) |

Il "skip" sui due stati speciali è implicito: i due `if` controllano stringhe esatte
(`=== 'online'` e `=== 'offline'`), quindi tutto il resto cade fuori da entrambi.

### Step 4 — Broadcast del cambio stato

Se il giro ha cambiato lo stato di almeno un punto della stazione:

**a) Broadcast `PuntoHardwareStatusChanged` per ogni punto cambiato** (dispatchato dentro
il ciclo, una alla volta):
- Canale: `mappa`
- Evento: `.punto.hardware.status`
- Payload: `{ id_punto, stato_hardware, id_stazione }`
- Effetto identico a quello descritto nel flusso 3: la cache locale nei browser viene
  aggiornata, `markerColor` ricalcola, il pallino sulla mappa cambia colore.

**b) Ricalcolo aggregato della stazione** (`aggiornaStatoStazione`):
```php
$haUnPuntoDisponibile = Punti_ricarica::where('id_stazione', $id)
    ->where('libera', true)
    ->whereNotIn('stato_hardware', ['guasto', 'offline', 'manutenzione_programmata'])
    ->exists();
```
Se questo valore differisce dal vecchio `stazioni.libera`, UPDATE + broadcast
`StazioneStatusChanged` (`.stazione.status`, payload `{ id_stazione, libera }`).

Notare il `whereNotIn` che esclude `manutenzione_programmata`: garantisce che, finché
la stazione è in manutenzione, non viene mai conteggiata come libera anche se i flag
`punti_ricarica.libera = 1` sono rimasti (sì, restano: la manutenzione tocca solo
`stato_hardware`, non `libera`).

### Schema riassuntivo

```
   [simulatore]                  [broker MQTT]                  [MqttWorker]                  [DB]                  [HeartbeatChecker]                 [browser]
       │  publish heartbeat ts=T     │                                │                          │                          │                                │
       │ ──────────────────────────► │  ◄── subscribe stazione/#      │                          │                          │                                │
       │                             │ ─────────────────────────────► │  in_manutenzione?        │                          │                                │
       │                             │                                │ ────────────────────────►│                          │                                │
       │                             │                                │  no → UPDATE             │                          │                                │
       │                             │                                │      data_ultimo_heartbeat = T                      │                                │
       │                             │                                │                          │                          │                                │
       │                             │                                │                          │  (60s loop)              │                                │
       │                             │                                │                          │ ◄── SELECT stazioni      │                                │
       │                             │                                │                          │      with puntiRicarica  │                                │
       │                             │                                │                          │                          │                                │
       │  (... silenzio per 2 min ...)                                │                          │                          │                                │
       │                             │                                │                          │  punto.heartbeat < now-120s                              │
       │                             │                                │                          │  → UPDATE stato='offline'│                                │
       │                             │                                │                          │ ─────────────────────────│  broadcast .punto.hardware.status ──►  cache.punto.stato_hw='offline'
       │                             │                                │                          │                          │                                │  → markerColor() → grigio
       │                             │                                │                          │  ricalcolo libera        │                                │
       │                             │                                │                          │  diversa → UPDATE +      │                                │
       │                             │                                │                          │  broadcast .stazione.status                              │
```

### Interazione con la manutenzione

I due flussi convivono per evitare il "lampeggio" descritto nel flusso 3:

| Scenario | Stato punti | HeartbeatChecker | Risultato |
|---|---|---|---|
| Stazione viva, heartbeat regolari | `'online'` | `heartbeatScaduto=false`, già online → no-op | Pallino verde/rosso |
| Stazione spenta da 2+ min | `'online'` | `heartbeatScaduto=true` → `'offline'` + broadcast | Pallino grigio |
| Stazione tornata viva | `'offline'` | `heartbeatScaduto=false` → `'online'` + broadcast | Pallino verde/rosso |
| Stazione messa in manutenzione | `'manutenzione_programmata'` | skip totale | Pallino grigio (settato dal toggle, non da qui) |
| Stazione tolta dalla manutenzione | `'offline'` | `heartbeatScaduto=true` finché niente heartbeat | Resta grigio finché simulatore riparte |
| Heartbeat in arrivo durante manutenzione | `'manutenzione_programmata'` | MqttWorker scarta l'heartbeat (Step 2) | Stato congelato, niente lampeggio |

### Costi e scalabilità

- Query per giro: 1 SELECT con eager load `puntiRicarica`, poi 0..N UPDATE.
- Frequenza: 1 giro / 60s.
- Latenza max prima di rilevare un offline: 60s (check) + 120s (soglia) = **3 minuti**.
- Per ridurre latenza basta abbassare `SOGLIA_OFFLINE_SEC` e/o `INTERVALLO_CHECK_SEC`,
  a costo di più sensibilità ai jitter di rete.

Per scalare oltre qualche centinaio di stazioni conviene sostituire il loop PHP con
un'unica `UPDATE ... WHERE data_ultimo_heartbeat < ?` bulk + un secondo UPDATE per il
contrario, eliminando il ciclo applicativo. Per il volume scolastico attuale la
versione iterativa è sufficiente e più leggibile.

---

## 9. Gamification

### Concetto

A ogni sessione chiusa l'utente guadagna:
- **XP** (esperienza): `max(5, kWh × 10)`.
- **CO₂ risparmiata**: `kWh × 0.233` (fattore ISPRA 2023, emissioni medie rete IT).
- **Streak**: giorni consecutivi con almeno una ricarica.

I valori si accumulano in `gamification_profilo_utente`. Il **livello** è una colonna
GENERATED COLUMN MariaDB calcolata da `xp_totali` (es. ogni 100 XP = +1 livello).

### Badge

Il **catalogo badge** (`gamification_badge_catalogo`) è seedato con 8 badge progressivi:

| Codice | Trigger |
|---|---|
| `PRIMA_RICARICA` | 1 sessione |
| `VETERANO` | 10 sessioni |
| `PRIMI_KWH` | 10 kWh totali |
| `CENTOKWH` | 100 kWh totali |
| `ECO_BRONZE` | 10 kg CO₂ |
| `ECO_CHAMPION` | 50 kg CO₂ |
| `SETTIMANA_GREEN` | streak 7 giorni |
| `LIVELLO_5` | livello ≥ 5 |

Ogni badge ha una `condizione_json` interpretata da `GamificationService::verificaCondizione`.
Tipi supportati: `conteggio_sessioni`, `soglia_kwh_totali`, `soglia_co2`, `streak_giorni`,
`livello_minimo`. Aggiungerne uno nuovo = aggiungere un case al `match` PHP.

L'unlock è **idempotente**: la PK composta `(id_utente, id_badge)` su `gamification_badge_utente`
impedisce duplicati.

---

## 10. Login e registrazione utente

### Concetto

L'utente accede all'app per consultare la mappa, avviare ricariche, vedere il profilo
gamification e (se admin) entrare nel pannello. Esistono **due frontend** con due
strategie di autenticazione diverse che convivono sullo stesso modello `Utenti` e sulla
stessa tabella `personal_access_tokens` di Sanctum:

- **Blade (server-rendered)**: cookie di sessione PHP + token Sanctum salvato nella
  sessione lato server. Le pagine sono `/login`, `/register`, `/map`, ecc.
- **React (SPA via Vite)**: solo token Sanctum, salvato in `localStorage` del browser e
  iniettato manualmente nell'header `Authorization: Bearer ...` da `apiClient`. Le
  pagine sono `/react/login`, `/react/register`, `/react/map`, ecc.

Entrambe convergono su `personal_access_tokens` per la validazione delle richieste
API protette da `auth:sanctum`. Significato pratico: lo **stesso utente** può essere
loggato simultaneamente da Blade e React e i due "ambienti" non si pestano i piedi,
ma il logout di uno revoca i token e disconnette anche l'altro (vedi sotto).

### Tabelle e colonne coinvolte

**`utenti`** ([migration 0001](backend/src/database/migrations/0001_creazione_struttura_iniziale%20.php)):
- `id_utente` (UUID, PK) — auto-generato dal trigger
  [`trg_set_utenti_uuid_ins`](backend/src/database/migrations/0003_creazione_trigger_utenti.php#L14)
  se l'INSERT non lo passa esplicitamente.
- `email` (unique), `password` (hash bcrypt via `Hash::make`), `cellulare`, `nome`,
  `cognome`.
- `tipo_account` enum `completo|badge_anonimo|ospite` (default `completo` per
  registrazioni web/React).
- `attivo` boolean (default `true`) — l'admin può disattivare un utente da pannello.
- `ruolo` enum `utente|admin` (aggiunto in [migration 0015](backend/src/database/migrations/0015_aggiunta_ruolo_utenti.php)),
  default `utente`. Solo `admin` accede a `/admin/*` e `/api/admin/*`.
- `login_tentativi` (unsigned tinyint) + `login_bloccato_fino` (timestamp nullable) —
  aggiunti in [migration 0016](backend/src/database/migrations/0016_aggiunta_lockout_login_utenti.php)
  per il throttle/lockout.

**`personal_access_tokens`** ([migration 0004](backend/src/database/migrations/0004_creazione_personal_access_tokens_table.php)):
- Versione custom della tabella Sanctum: usa `uuidMorphs('tokenable')` invece di
  `morphs()` perché il `tokenable_id` è l'UUID di `utenti`, non un BIGINT.
- `token` (varchar 64 unique) — **hash SHA-256 del token**, non il token in chiaro.
  Il valore in chiaro esce una sola volta da `createToken()->plainTextToken` e va
  consegnato subito al client; al ritorno il backend confronta `hash('sha256', $plain)`
  col valore in DB.
- `name` — etichetta libera (`'auth_token'` per /api/login, `'web-access'` per il flow
  Blade). Permette di distinguere i token nel DB ma non ha effetti funzionali.
- `last_used_at` — aggiornato da Sanctum a ogni chiamata autenticata. Utile per audit.

### Flusso A — Registrazione via API (React)

**Endpoint**: `POST /api/register` (pubblico, [api.php:94](backend/src/routes/api.php#L94))
→ [`RegisterController::register`](backend/src/app/Http/Controllers/Api/RegisterController.php).

**Body JSON**:
```json
{
  "nome": "Mario", "cognome": "Rossi",
  "email": "mario@example.com", "cellulare": "3331234567",
  "password": "almeno8car", "password_confirmation": "almeno8car"
}
```

**Step 1 — Validazione**:
- `email`: required, formato email, max 255, **unique** su `utenti.email` (con messaggio
  custom "Questa email è già registrata.").
- `password`: required, **confirmed** (richiede `password_confirmation` identica),
  min 8.
- `nome`/`cognome`: required, string, max 100.
- `cellulare`: nullable, max 20.

Se fallisce, Laravel ritorna `422` con `{ errors: { campo: [msg, ...] } }` automaticamente.

**Step 2 — Creazione utente**:
```php
Utenti::create([
    'id_utente'    => Str::uuid()->toString(),
    'tipo_account' => 'completo',
    'password'     => Hash::make($request->password),
    'attivo'       => 1,
    // + nome, cognome, email, cellulare dal body
]);
```
- L'UUID viene generato lato applicazione anche se il trigger DB sarebbe in grado di
  farlo: passarlo esplicitamente evita un `RETURNING` o un round-trip per leggerlo.
- `Hash::make` usa bcrypt con cost 12 (default Laravel) → l'hash include il salt al
  suo interno, niente colonna separata.
- `ruolo` non è passato → default DB `'utente'`. Per creare admin si fa manualmente da
  CLI/seeder, non c'è endpoint di registrazione admin.

**Step 3 — Emissione token**:
```php
$token = $utente->createToken('auth_token')->plainTextToken;
```
- Sanctum genera 40 byte random, prende SHA-256, inserisce la riga in
  `personal_access_tokens` con `tokenable_type='App\\Models\\Utenti'`, `tokenable_id=<uuid>`,
  `token=<sha256 hash>`, `abilities='["*"]'`.
- `plainTextToken` è la concatenazione `{id}|{plain}` (l'ID serve a Sanctum per
  cercare la riga in O(1) invece che fare `WHERE token = hash($plain)` sull'intera
  tabella).

**Step 4 — Risposta `201 Created`**:
```json
{
  "access_token": "5|abc123...xyz",
  "token_type": "Bearer",
  "user": { "id_utente": "...", "nome": "...", "email": "...", "tipo": "completo", "ruolo": "utente" }
}
```

Il frontend React (vedi `frontend/src/api/client.js`) salva `access_token` in
`localStorage` e lo inietta come `Authorization: Bearer ...` su ogni richiesta API
successiva tramite un interceptor axios.

### Flusso B — Registrazione via Blade

**Endpoint**: `POST /register` (web, [web.php:20](backend/src/routes/web.php#L20)) →
[`WebAuthController::register`](backend/src/app/Http/Controllers/WebAuthController.php#L173).

Differenze rispetto a A:

1. Stesse validazioni, stesso `Utenti::create(...)`.
2. **Auto-login post-registrazione**: `Auth::login($utente)` + `$request->session()->regenerate()`
   (rigenera l'ID di sessione per prevenire session fixation).
3. **Pulisce token vecchi** prima di crearne uno nuovo: `$utente->tokens()->delete()`.
   Questo serve perché lo stesso utente potrebbe aver già fatto registrazione+login
   da React e avere un token vivo; il design Blade qui assume "un solo token attivo
   per utente" (semplifica il /logout).
4. Crea token con nome `'web-access'` e lo salva nella **sessione PHP**:
   ```php
   session(['api_token' => $token]);
   ```
   Questo è il trucco chiave per Blade: la pagina HTML server-rendered non può
   memorizzare il token come fa una SPA, quindi lo tiene la sessione lato server. Le
   view che hanno bisogno di chiamare API (es. `/map` per fare fetch su `/api/stations`)
   ricevono il token come variabile Blade da `web.php` e lo iniettano nel JS della
   pagina come `window.API_TOKEN` o simile.
5. `redirect()->intended('map')` — Laravel ricorda l'ultima pagina che richiedeva auth
   e ti ci porta dopo il login; default `/map`.

### Flusso C — Login via API (React)

**Endpoint**: `POST /api/login` (pubblico, [api.php:32](backend/src/routes/api.php#L32))
→ [`AuthController::login`](backend/src/app/Http/Controllers/Api/AuthController.php).

**Body JSON**: `{ "email": "...", "password": "..." }`.

**Step 1 — Validazione base**:
- `email`: required, email format.
- `password`: required.

**Step 2 — Lookup utente**:
```php
$user = Utenti::where('email', $request->email)->first();
```

**Step 3 — Check lockout** (prima di toccare la password):
```php
if ($user && $user->login_bloccato_fino && Carbon::now()->lt($user->login_bloccato_fino)) {
    return response()->json([...], 423);
}
```
- Codice HTTP **423 Locked**.
- Il check va fatto PRIMA della verifica password: altrimenti un attaccante con lockout
  attivo riceverebbe "credenziali errate" e capirebbe che la password che stava provando
  non è valida (oracolo).
- Il messaggio include i minuti rimanenti, calcolati con `ceil(diffInMinutes(..., false))`
  + `abs` per gestire correttamente lo sign del Carbon diff.

**Step 4 — Verifica password**:
```php
if (! $user || ! Hash::check($request->password, $user->password)) {
    if ($user) {
        $user->login_tentativi += 1;
        if ($user->login_tentativi >= 5) {
            $user->login_bloccato_fino = Carbon::now()->addMinutes(15);
            $user->login_tentativi     = 0;
            $user->save();
            return response()->json([...], 423);
        }
        $user->save();
        return response()->json([..., 'tentativi_rimasti' => 5 - $user->login_tentativi], 401);
    }
    return response()->json([..., 'message' => 'Credenziali non valide.'], 401);
}
```

Punti chiave:
- **`Hash::check`** confronta in tempo costante per non rivelare info via timing.
- Il contatore `login_tentativi` viene incrementato **solo se l'utente esiste**. Se
  incrementassimo anche per email inesistenti, un attaccante non avrebbe modo di
  scoprire account validi ma noi paghiamo INSERT/UPDATE inutili e (più grave) il
  messaggio generico "credenziali non valide" è sempre lo stesso a prescindere → un
  attaccante non capirebbe se l'email esiste. Non incrementando, manteniamo il
  comportamento simmetrico e non-enumerabile.
- Soglia 5 tentativi falliti → blocco 15 minuti. Al blocco, contatore azzerato (così
  al prossimo ciclo riparte pulito).
- Risposta `401 Unauthorized` con `tentativi_rimasti` per UX.

**Step 5 — Check account attivo**:
```php
if (! $user->attivo) {
    return response()->json([...], 403);
}
```
Account disattivato dall'admin → `403 Forbidden`, senza emettere token.

**Step 6 — Login OK, reset contatori, token**:
```php
$user->login_tentativi     = 0;
$user->login_bloccato_fino = null;
$user->save();
$token = $user->createToken('auth_token')->plainTextToken;
```

Notare: il flusso API **non** cancella i token esistenti (a differenza del Blade) →
un utente può avere più sessioni API contemporaneamente (es. browser + Postman + mobile
in futuro). Ogni `createToken` crea una nuova riga.

**Step 7 — Risposta `200 OK`**: stesso shape della registrazione (`access_token`,
`token_type`, `user`).

### Flusso D — Login via Blade

**Endpoint**: `POST /login` ([web.php:17](backend/src/routes/web.php#L17)) →
[`WebAuthController::login`](backend/src/app/Http/Controllers/WebAuthController.php#L34).

Stesse regole di lockout/throttle (`MAX_TENTATIVI = 5`, `BLOCCO_MINUTI = 15`) ma con due
differenze sostanziali rispetto all'API:

1. **Auth classica via `Auth::attempt($credentials)`**: usa il guard `web` (sessione +
   cookie). Se successo, `$request->session()->regenerate()` rigenera l'ID di sessione
   (anti session fixation).
2. **Single-token policy**: `$user->tokens()->delete()` prima di `createToken('web-access')`.
   Solo l'ultimo token Blade è valido. Conseguenza: se l'utente è loggato anche da
   React e poi fa login da Blade, il token React viene revocato → React riceverà 401
   sulla prossima chiamata e dovrà rifare login.

Gli errori non vengono ritornati come JSON: usa `ValidationException::withMessages([...])`
che Laravel converte in redirect alla pagina di login con i messaggi flashati in sessione
(letti dal template Blade come `@error('email') {{ $message }} @enderror`).

Dopo successo: token salvato in `session(['api_token' => $token])` e
`redirect()->intended('map')`.

### Flusso E — Logout

**API React** ([AuthController::logout](backend/src/app/Http/Controllers/Api/AuthController.php#L98)):
- Rotta `POST /api/logout` sotto `auth:sanctum`.
- `$request->user()->currentAccessToken()->delete()` cancella **solo il token corrente**
  (non gli altri eventuali token dello stesso utente).
- Risposta JSON `{ message: ... }`. Il frontend rimuove `access_token` dal localStorage.

**Blade** ([WebAuthController::logout](backend/src/app/Http/Controllers/WebAuthController.php#L147)):
- Rotta `POST /logout` (web).
- `$user->tokens()->delete()` cancella **tutti** i token (coerente con la single-token
  policy del login Blade).
- `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()` per
  azzerare completamente la sessione.
- Redirect a `/login`.

### Flusso F — Autenticazione di una richiesta successiva

Per ogni richiesta a una rotta sotto `auth:sanctum` (es. `GET /api/stations`):

1. Sanctum estrae il token dall'header `Authorization: Bearer {token}`.
2. Splitta `{id}|{plain}` → cerca `personal_access_tokens` WHERE `id = {id}`.
3. Confronta `hash_equals(hash('sha256', $plain), $row->token)`. Se diverso → 401.
4. Se `expires_at IS NOT NULL AND expires_at < NOW()` → 401. (Oggi non scadono mai:
   `createToken` non passa expiry).
5. Aggiorna `last_used_at = NOW()` (Sanctum lo fa async via observer, non blocca la
   risposta).
6. Risolve `$request->user()` a `Utenti::find($row->tokenable_id)`.
7. Procede col middleware successivo (es. `admin.api`) o col controller.

**Middleware `admin.api`** ([AdminApiMiddleware](backend/src/app/Http/Middleware/AdminApiMiddleware.php)):
```php
if (! $request->user() || $request->user()->ruolo !== 'admin') {
    return response()->json(['message' => '...'], 403);
}
```
Banale: legge `ruolo` dall'utente già caricato da Sanctum. Restituisce JSON 403 per i
client REST (a differenza di `AdminMiddleware` che fa redirect per il flusso web).

### Flusso G — Autenticazione WebSocket (canali privati)

Quando il browser sottoscrive un canale privato (es. `private-user.{id_utente}` per i
kWh real-time), Echo fa una richiesta di autorizzazione che deve essere autenticata
**come se fosse una normale chiamata API**. Le rotte sono:

- **`/broadcasting/auth`** (web, middleware `web` → cookie sessione) — usata da Blade.
- **`/api/broadcasting/auth`** (API, middleware `auth:sanctum`) — usata da React
  (registrata in [api.php:24](backend/src/routes/api.php#L24)).

React deve essere configurato per puntare a `/api/broadcasting/auth` invece del default
`/broadcasting/auth`, altrimenti Echo tenta l'auth col cookie (che non esiste in
contesto SPA) e fallisce con 401 → niente real-time. Vedi dettagli in Parte I, sezione C.

L'handler dell'auth route ([`channels.php`](backend/src/routes/channels.php)) controlla
che `$user->id_utente === $id_utente` per il canale `user.{id_utente}` (un utente può
ascoltare solo i suoi eventi).

### Schema riassuntivo

```
   [browser React]                  [Laravel]                  [DB]                  [Sanctum]
        │  POST /api/register          │                          │                          │
        │ ───────────────────────────► │                          │                          │
        │                              │  validate (email unique) │                          │
        │                              │  Utenti::create(uuid,    │                          │
        │                              │      hash bcrypt)        │                          │
        │                              │ ───────────────────────► │                          │
        │                              │                          │  trigger UUID se mancante│
        │                              │  createToken('auth')     │ ─────────────────────────│ ──► INSERT personal_access_tokens
        │  201 { access_token, user }  │                          │                          │
        │ ◄─────────────────────────── │                          │                          │
        │  localStorage.set(token)     │                          │                          │
        │                              │                          │                          │
        │  GET /api/stations           │                          │                          │
        │      Authorization: Bearer.. │                          │                          │
        │ ───────────────────────────► │  middleware auth:sanctum │                          │
        │                              │ ──────────────────────────────────────────────────► │  WHERE id=X
        │                              │                          │                          │  hash_equals(sha256, plain)
        │                              │                          │                          │  UPDATE last_used_at
        │                              │  $request->user()=Utenti(uuid)                      │
        │                              │  controller logic                                   │
        │  200 { stations: [...] }     │                          │                          │
        │ ◄─────────────────────────── │                          │                          │

   [browser Blade]
        │  POST /login (form HTML)     │                          │                          │
        │ ───────────────────────────► │  Auth::attempt           │                          │
        │                              │  session->regenerate()   │                          │
        │                              │  tokens()->delete()      │ ──► DELETE all tokens   │
        │                              │  createToken('web')      │ ──► INSERT new token    │
        │                              │  session(['api_token'=>])│                          │
        │  302 → /map                  │                          │                          │
        │ ◄─────────────────────────── │  set cookie laravel_session                         │
        │                              │                          │                          │
        │  GET /map                    │                          │                          │
        │      Cookie: laravel_session │                          │                          │
        │ ───────────────────────────► │  middleware web (auth)   │                          │
        │                              │  pass api_token to view  │                          │
        │  HTML con window.API_TOKEN=..│                          │                          │
        │ ◄─────────────────────────── │                          │                          │
        │                              │                          │                          │
        │  fetch /api/stations         │                          │                          │
        │      Authorization: Bearer.. │  (same as React above)   │                          │
```

### Edge case e considerazioni

- **Account disattivato dopo login**: il check `attivo` avviene solo al momento del
  login. Se l'admin disattiva un utente mentre questo è già loggato, l'utente
  **continua a poter usare l'app** finché il token resta vivo. Per disconnetterlo
  subito, l'admin può fare `resetPassword` (che invalida tutti i token) — vedi
  `AdminApiController::resetPassword`.
- **Lockout durante il blocco**: tentativi di login a un account bloccato non
  resettano `login_bloccato_fino` (il check è il primo), quindi un attaccante non può
  prolungare un blocco con altri tentativi falliti — il timer scorre comunque.
- **Email enumeration**: il messaggio generico "Credenziali non valide" è identico
  per email inesistente e password sbagliata. L'unico segnale che differisce è
  `tentativi_rimasti` (presente solo se l'utente esiste), che potrebbe essere usato
  per enumerare. Trade-off accettato per UX migliore.
- **Token non scadono**: oggi `createToken()` non setta `expires_at`. In produzione
  conviene impostare una scadenza (`createToken('name', ['*'], now()->addDays(30))`)
  per ridurre il rischio di token rubati validi all'infinito.
- **Niente rate limit su `/api/login`**: il lockout per-utente protegge il singolo
  account ma non protegge dal flood (es. attaccante che tenta password diverse su
  account diversi). Aggiungere `->middleware('throttle:10,1')` sulla rotta sarebbe
  una protezione complementare.
- **Niente verifica email**: l'utente è subito attivo dopo `register`. Per produzione
  conviene un flusso di conferma (Laravel ha `MustVerifyEmail` integrato).
- **Niente reset password self-service**: solo l'admin può resettare via
  `AdminApiController::resetPassword`. Per produzione si aggiunge il flusso classico
  "password dimenticata" via email.

---

# Parte III — Reference

## 11. API REST

Definite in [`routes/api.php`](backend/src/routes/api.php).

### Pubbliche

| Method | Path | Controller |
|---|---|---|
| POST | `/api/login` | `AuthController::login` |
| POST | `/api/iot/registra` | `IotController::Registra` |

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
| POST | `/api/{id_stazione}/verifica-codice` | `SessionController::AutenticazioneCodice` |
| GET  | `/api/me/sessione-attiva` | `SessionController::SessioneAttivaUtente` |
| GET  | `/api/session/{id}` | `SessionController::show` |
| POST | `/api/session/{id}/stop` | `SessionController::InterrompiSessione` |
| POST | `/api/logout` | `AuthController::logout` |

### Web admin (`auth` + `admin`)

| Method | Path | Azione |
|---|---|---|
| GET  | `/admin` | dashboard |
| GET  | `/admin/utenti` | lista utenti |
| GET  | `/admin/utenti/{id}` | dettaglio utente |
| POST | `/admin/utenti/{id}` | modifica |
| POST | `/admin/utenti/{id}/toggle` | attiva/disattiva |
| POST | `/admin/utenti/{id}/reset` | reset password |
| GET  | `/admin/sessioni` | lista sessioni |
| GET  | `/admin/stazioni` | lista stazioni (tag online derivato) |
| GET  | `/admin/stazioni/{id}/setup` | form setup |
| POST | `/admin/stazioni/{id}/setup` | salva e attiva |
| POST | `/admin/stazioni/{id}/toggle` | toggle manutenzione |

---

## 12. Middleware

| Alias | Classe | Cosa fa |
|---|---|---|
| `auth` | Laravel default | Verifica sessione/Sanctum, redirect a `/login` se assente |
| `auth:sanctum` | Sanctum | Bearer token API |
| `admin` | [`AdminMiddleware`](backend/src/app/Http/Middleware/AdminMiddleware.php) | Richiede `ruolo='admin'`, altrimenti 403. Registrato in [`bootstrap/app.php`](backend/src/bootstrap/app.php) |

---

## 13. Topic MQTT

### Station-wide (3 livelli)

| Topic | Direzione | Payload | Note |
|---|---|---|---|
| `stazione/{mac}/ready` | Laravel → sim | `{ comando: "READY", id_punti: [...] }` | Admin completa setup |
| `stazione/{mac}/codice` | sim → Laravel | `{ codice, scadenza }` | Ogni 30s |
| `stazione/{mac}/comandi` | Laravel → sim | `{ comando: "autenticazione_completata" }` | Informativo (Arduino) |
| `stazione/{mac}/manutenzione` | Laravel → sim | `{ comando, on: bool }` | Toggle admin |

### Per-punto (4 livelli)

| Topic | Direzione | Payload | Note |
|---|---|---|---|
| `stazione/{mac}/{id_punto}/heartbeat` | sim → Laravel | `{ stato, ts }` | Ogni 60s, retain=true |
| `stazione/{mac}/{id_punto}/eventi` | sim → Laravel | `{ evento, id_sessione?, kwh_totali? }` | cavo_collegato/scollegato/batteria_piena |
| `stazione/{mac}/{id_punto}/telemetria` | sim → Laravel | `{ id_sessione, voltaggio, corrente, intervallo_sec }` | Ogni 5s |
| `stazione/{mac}/{id_punto}/comandi` | Laravel → sim | `{ comando, id_sessione }` | START / STOP |

Il [`MqttWorker`](backend/src/app/Console/Commands/MqttWorker.php) usa una sola subscribe
`stazione/#` con routing per numero di livelli.

---

## 14. WebSocket

### Canali pubblici

| Canale | Eventi | Ascolta |
|---|---|---|
| `mappa` | `.punto.status`, `.punto.hardware.status`, `.stazione.status` | [`map.blade.php`](backend/src/resources/views/map.blade.php) |
| `punto.{macNorm}.{id_punto}` | `.punto.status` | [`gamification-profile.blade.php`](backend/src/resources/views/gamification-profile.blade.php) |
| `stazione.{macNorm}` | `.punto.hardware.status`, `.stazione.status` | libero per uso futuro |

`macNorm` = MAC senza `:` (es. `AABBCCDDEEFF`).

### Canali privati

| Canale | Auth | Eventi |
|---|---|---|
| `user.{id_utente}` | [`channels.php`](backend/src/routes/channels.php): `$user->id_utente === $id_utente` | `.sessione.avviata`, `.ricarica.heartbeat` |

### Endpoint di autorizzazione per i canali privati

Per i canali privati Echo deve autenticarsi prima di sottoscriversi (vedi
[sezione C](#c-websocket-reverb-broadcast-verso-il-browser) per il flusso completo).
Esistono due endpoint che chiamano la stessa closure di `channels.php`:

| Endpoint | Middleware | Usato da | Header richiesti |
|---|---|---|---|
| `POST /broadcasting/auth` | `web` | Blade | `X-CSRF-TOKEN` |
| `POST /api/broadcasting/auth` | `auth:sanctum` | React | `Authorization: Bearer <token>` |

Il secondo è registrato in [`routes/api.php`](backend/src/routes/api.php) con
`Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api'])`. Echo lato
React punta lì via `authEndpoint: '/api/broadcasting/auth'` in
[`useSessionChannel.js`](frontend/src/hooks/useSessionChannel.js).

### Eventi

| Classe | broadcastAs | Quando |
|---|---|---|
| [`SessioneAvviata`](backend/src/app/Events/SessioneAvviata.php) | `sessione.avviata` | sessione creata su DB |
| [`TelemetriaRicevuta`](backend/src/app/Events/TelemetriaRicevuta.php) | `ricarica.heartbeat` | ogni telemetria |
| [`PuntoStatusChanged`](backend/src/app/Events/PuntoStatusChanged.php) | `punto.status` | punto si libera/occupa |
| [`PuntoHardwareStatusChanged`](backend/src/app/Events/PuntoHardwareStatusChanged.php) | `punto.hardware.status` | punto va offline/online |
| [`StazioneStatusChanged`](backend/src/app/Events/StazioneStatusChanged.php) | `stazione.status` | stato aggregato cambia |

Tutti `ShouldBroadcastNow`.

---

## 15. Schema DB completo

Vedi le migration ([`backend/src/database/migrations/`](backend/src/database/migrations/)).

```
utenti (UUID)
  └─ ruolo: utente|admin
  └─ tipo_account: completo|badge_anonimo|ospite

stazioni (PK = MAC)
  ├─ stato_setup: in_setup|attiva
  ├─ in_manutenzione: bool
  ├─ libera: bool
  ├─ data_ultimo_heartbeat
  └─ nome, indirizzo, latitudine, longitudine, coordinata (geometry)

punti_ricarica (PK composta: id_stazione, id_punto)
  ├─ stato_hardware: online|offline|guasto|manutenzione_programmata
  ├─ libera: bool
  ├─ tipo_veicolo, tipo_connettore, potenza_max_kw
  ├─ tariffa_predefinita
  └─ data_ultimo_heartbeat

sessioni_ricarica (UUID)
  ├─ FK (id_stazione, id_punto) → punti_ricarica
  ├─ FK id_utente → utenti
  ├─ metodo_avvio: RFID|CODICE
  ├─ data_inizio, data_fine
  ├─ quantita_kwh, costo_totale
  └─ stato_pagamento: non_richiesto|in_attesa_pagamento|completato|fallito|gratuito

accumulatori_stazione → storico_livello_batteria

gamification_profilo_utente, gamification_badge_catalogo,
gamification_badge_utente, gamification_sfide_settimanali

scuola_profilo, scuola_consumo_mensile
```

Stored procedure: `sp_verifica_disponibilita`, `sp_avvio_sessione`, `sp_termina_sessione`.
Trigger: 11 trigger in [`0003_creazione_trigger_utenti.php`](backend/src/database/migrations/0003_creazione_trigger_utenti.php).
