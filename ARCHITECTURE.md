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
- [8. Gamification](#8-gamification)

**Parte III — Reference**
- [9. API REST](#9-api-rest)
- [10. Middleware](#10-middleware)
- [11. Topic MQTT](#11-topic-mqtt)
- [12. WebSocket](#12-websocket)
- [13. Schema DB completo](#13-schema-db-completo)

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
toccare il DB nei punti delicati: imposta solo un **flag** e manda un comando MQTT alla
colonnina.

### Flusso ([`AdminController::toggleStazione`](backend/src/app/Http/Controllers/AdminController.php))

1. Flip booleano di `stazioni.in_manutenzione`.
2. Se entra in manutenzione: UPDATE di tutti i punti della stazione a `stato_hardware='offline'`.
   La mappa lo riflette subito senza aspettare il prossimo heartbeat.
3. **Publish MQTT** su `stazione/{mac}/manutenzione` con `{ "comando": "manutenzione", "on": true|false }`.
4. La colonnina riceve: se `on=true` ferma generazione codice e heartbeat; se `on=false`
   riparte.

### Effetto sulla UI

- Tabella admin `stazioni`: badge giallo "manutenzione".
- Mappa pubblica: stazione offline finché manutenzione attiva.

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
     │  POST /verifica-codice                              │
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

`POST /api/verifica-codice` ([`SessionController::AutenticazioneCodice`](backend/src/app/Http/Controllers/Api/SessionController.php))

1. Valida codice (6 cifre).
2. `CodiceMonousoService::verifica($codice)` ritorna l'`id_stazione` o null.
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

## 8. Gamification

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

# Parte III — Reference

## 9. API REST

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
| POST | `/api/verifica-codice` | `SessionController::AutenticazioneCodice` |
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

## 10. Middleware

| Alias | Classe | Cosa fa |
|---|---|---|
| `auth` | Laravel default | Verifica sessione/Sanctum, redirect a `/login` se assente |
| `auth:sanctum` | Sanctum | Bearer token API |
| `admin` | [`AdminMiddleware`](backend/src/app/Http/Middleware/AdminMiddleware.php) | Richiede `ruolo='admin'`, altrimenti 403. Registrato in [`bootstrap/app.php`](backend/src/bootstrap/app.php) |

---

## 11. Topic MQTT

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

## 12. WebSocket

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

## 13. Schema DB completo

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
