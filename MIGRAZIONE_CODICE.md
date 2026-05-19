# Migrazione QR → codice monouso

Note operative per ripartire dopo i cambi.

## Cosa è cambiato

### Database
- `stazioni.id_stazione` è ora il **MAC ADDRESS** (es. `AA:BB:CC:DD:EE:FF`), non più UUID.
- `stazioni` ha due nuove colonne: `password_hash` (riservato, oggi non popolato) e `stato_setup` (`in_setup` | `attiva`).
- `stazioni.nome`, `indirizzo`, `latitudine`, `longitudine`, `coordinata` sono **nullable** finché l'admin non completa il setup.
- `punti_ricarica` ha **chiave primaria composta** `(id_stazione, id_punto)` e `id_punto` è un numero locale `'1'`, `'2'`, ...
- `sessioni_ricarica` ora ha **anche** `id_stazione` per la FK composta verso `punti_ricarica`.
- `metodo_avvio` enum: `RFID | CODICE` (era `RFID | QR_CODE`).
- Stored procedure `sp_avvio_sessione` / `sp_verifica_disponibilita` accettano ora `(id_stazione, id_punto)`.

### Backend Laravel
- Nuovo `POST /api/iot/registra` con body `{ mac, password, numero_punti }`: la stazione dichiara il numero di prese fisiche che ha (deciso dall'hardware), il backend crea `stazioni` (`stato_setup='in_setup'`) + N `punti_ricarica` con `id_punto = "1".."N"` e ritorna host/porta MQTT + i nomi dei topic. Idempotente: alle chiamate successive non ricrea i punti (registra solo un warning se il numero dichiarato differisce dal DB).
- Sostituito `POST /api/scan-qr` con `POST /api/verifica-codice` con body `{ codice }` (6 cifre).
- Nuovo service `CodiceMonousoService`: TTL **35s** in Redis. Chiave `codice:{mac}:{id_punto}` → valore `"NNNNNN"` (cioe' il codice corrente). La stazione ne genera uno nuovo ogni **30s** → al massimo 1 codice valido per punto + 5s di overlap quando arriva il nuovo prima che scada il vecchio. Verifica: il service itera sui punti delle stazioni `attiva` e confronta il valore corrente. **Il codice non viene rimosso da Redis al match**: resta valido fino alla scadenza naturale (35s). L'unicita' viene garantita a valle dal SETNX su `codice_pending:{mac}:{id_punto}` (un secondo utente che tentasse lo stesso codice troverebbe il punto gia' prenotato → 409).
- `MqttWorker` ora ascolta su `stazione/+/+/+` (4 livelli) con canali: `codice`, `heartbeat`, `telemetria`, `eventi`. I codici ricevuti su `codice` finiscono in Redis.
- Admin: nuova route `GET /admin/stazioni/{id}/setup` con form per nome/coordinate + metadati di ogni punto (tipo veicolo, tipo connettore, potenza). Il numero di punti e i loro ID **non** sono modificabili dall'admin: li ha gia' dichiarati la colonnina. `POST` aggiorna in place i record e pubblica `stazione/{mac}/ready` via MQTT.
- Rimossi: `QrService`, `GeneraQrStazione`, `GeneraTuttiQRStazione`, dipendenza `simplesoftwareio/simple-qrcode`. Esegui `composer update`.

### Frontend
- `station-detail.blade.php`: scanner QR sostituito con un input a 6 cifre che chiama `/api/verifica-codice`.

### Simulatore Python
- Bootstrap: `Stazione().registra()` → POST `/api/iot/registra` con `MAC_ADDRESS` + `PASSWORD_REGISTRAZIONE`.
- Se `stato_setup='in_setup'` attende il messaggio MQTT `stazione/{mac}/ready` pubblicato dall'admin.
- Dopo `ready`: crea i punti annunciati, avvia heartbeat **e** un thread per punto che ogni `CODICE_INTERVAL` (default 30s) genera un codice a 6 cifre, lo **stampa a schermo** (riquadro) e lo pubblica su `stazione/{mac}/{id_punto}/codice`.

## Topic MQTT (riepilogo)

| Topic                                       | Direzione         | Note |
|---------------------------------------------|-------------------|------|
| `stazione/{mac}/ready`                      | Laravel → sim     | Admin completa setup. 3 livelli. Non matchato dal worker. |
| `stazione/{mac}/{id_punto}/comandi`         | Laravel → sim     | START, STOP, autenticazione_completata. |
| `stazione/{mac}/{id_punto}/codice`          | sim → Laravel     | Payload `{codice, scadenza}`. |
| `stazione/{mac}/{id_punto}/heartbeat`       | sim → Laravel     | Retain=true. |
| `stazione/{mac}/{id_punto}/telemetria`      | sim → Laravel     | V, I, intervallo. |
| `stazione/{mac}/{id_punto}/eventi`          | sim → Laravel     | cavo_collegato / cavo_scollegato / batteria_piena. |

## Flusso di autenticazione (riepilogo)

- La colonnina conosce **una sola** password, scritta nel proprio `.env` come
  `PASSWORD_REGISTRAZIONE`. La usa **una volta sola** sulla prima chiamata
  `POST /api/iot/registra` per dimostrare di essere autorizzata.
- Il backend confronta quella password con `IOT_REGISTRATION_PASSWORD` (deve
  combaciare) e restituisce host/porta del broker MQTT + nomi dei topic.
- Da quel momento la colonnina **non usa più** `PASSWORD_REGISTRAZIONE`:
  comunica solo via MQTT, senza autenticazione (il broker è in
  `allow_anonymous true` nella config attuale). Non c'è più un device-token
  HTTP perché heartbeat/fine sessione passano dal worker MQTT.

> **Nota:** l'autenticazione MQTT è stata volutamente lasciata fuori per ora.
> Quando vorrai aggiungerla:
> 1. Cambia `mqtt/config/mosquitto.conf` con `allow_anonymous false` + file `password_file`.
> 2. Reintroduci `IOT_MQTT_USER` / `IOT_MQTT_PASSWORD` in `backend/src/config/services.php` (chiave `iot`) e nel `.env` del backend.
> 3. Aggiungi i due campi nella risposta di `IotController::Registra` (array `mqtt`).
> 4. Nel simulatore (`stazione.py::_connetti_mqtt`) chiama di nuovo `self.mqtt.username_pw_set(user, password)` con i valori restituiti dall'API.

## Visibilità delle stazioni sulla mappa

`GET /api/stations` e `GET /api/station/{id}` filtrano su `stato_setup='attiva'`.
Una stazione che si è appena registrata via `/api/iot/registra` esiste a DB
ma è `in_setup`: **non compare sulla mappa** finché l'admin non completa nome,
coordinate e punti dal pannello (→ `stato_setup='attiva'` + MQTT `ready`).

## File `.env`

I default in `backend/src/.env.example` e `simulatore/.env.example` ora combaciano:
- `IOT_REGISTRATION_PASSWORD=greenschool-iot-2025` (backend) = `PASSWORD_REGISTRAZIONE=greenschool-iot-2025` (sim).
- `IOT_MQTT_USER=colonnina`, `IOT_MQTT_PASSWORD=` (vuota → broker anonimo).

Il `.env` del simulatore **non contiene più** `MQTT_NOME_UTENTE` / `MQTT_PASSWORD_STAZIONE`:
quei valori arrivano dalla risposta di `/api/iot/registra`.

Copia i `.example` in `.env` prima di partire.

## Come portare questa migrazione su `main`

La migrazione è stata sviluppata in un worktree sul branch `claude/vibrant-boyd-ed4084`. Per riportarla su `main`:

```bash
# 1. (dal worktree o da qualsiasi clone) verifica che main non sia avanti
git fetch origin
git log origin/main..claude/vibrant-boyd-ed4084 --oneline   # tutti i commit nuovi
git log claude/vibrant-boyd-ed4084..origin/main --oneline   # vuoto = fast-forward possibile

# 2. fast-forward locale di main verso il branch della migrazione
#    (funziona anche dal worktree senza checkout esplicito)
git push . claude/vibrant-boyd-ed4084:main

# 3. quando sei pronto a pubblicare
git push origin main

# 4. cleanup
git worktree remove .claude/worktrees/vibrant-boyd-ed4084
git branch -d claude/vibrant-boyd-ed4084
```

Se nel frattempo `origin/main` è avanzato (qualcuno ha fatto commit indipendenti) il fast-forward fallisce e serve un merge esplicito:

```bash
git checkout main
git pull --ff-only origin main
git merge --no-ff claude/vibrant-boyd-ed4084
```

## Procedura per ripartire

```
# backend
cd backend/src
composer update                # rimuove simplesoftwareio/simple-qrcode
php artisan migrate:fresh --seed
php artisan mqtt:leggi          # worker in foreground (oppure il container mqtt-worker)

# simulatore (in un altro terminale o container)
cd simulatore
python main.py
```

Al primo avvio la stazione finisce in `in_setup`. Dal pannello admin (`/admin/stazioni`) clicca **Completa setup**, inserisci coordinate e i punti (almeno 1, default `id_punto='1'`, `'2'`, ...). Al salvataggio il simulatore riceve `ready` e inizia a stampare i codici a schermo.

## Bug noti / punti d'attenzione

1. **MAC con `:` nei path URL del pannello admin.** I link generati a mano (`/admin/stazioni/AA:BB:CC:DD:EE:FF/setup`) funzionano in Laravel/Chrome/Firefox perché `:` è permesso nei segmenti URL (RFC 3986). Se vai dietro un proxy strano (nginx con regex aggressive) e ti dà 404, normalizza il MAC togliendo i `:` lato simulatore (`AABBCCDDEEFF`).

2. **Eloquent + PK composta su `Punti_ricarica`.** Ho impostato `protected $primaryKey = null`: significa che `$punto->save()` / `$punto->update()` / `Punti_ricarica::find()` **non funzionano**. Ho già migrato `HeartbeatChecker` a `DB::table()->where(...)->update()`. Se aggiungi codice nuovo, **usa sempre il query builder** o filtra esplicitamente per `(id_stazione, id_punto)`.

3. **Collisioni di codici monouso.** 6 cifre = 1M combinazioni. Con generazione ogni 30s e TTL 35s c'è in media **1 codice valido per punto** (più un brevissimo overlap di 5s in cui ce ne sono 2). La chiave Redis è per punto (`codice:{mac}:{id_punto}`) quindi *non* c'è il problema di "ultimo punto sovrascrive il primo": ogni punto ha la sua chiave. Resta il caso (raro) in cui due punti generano simultaneamente lo stesso numero a 6 cifre: l'utente digita il codice e `consuma()` ritorna il primo match trovato — può non essere quello atteso. Per evitarlo del tutto in produzione: codice a 8 cifre o un'asserzione lato utente ("sei sicuro di voler usare il punto X?").

4. **Race condition su `/api/iot/registra`.** Due chiamate concorrenti con lo stesso MAC potrebbero entrambe vedere `Stazione::where()->first() === null` e tentare due INSERT. Il secondo fallirà col duplicate PK. Innocuo (la prima vince) ma fa 500. In produzione: avvolgi in transazione + `INSERT ... ON DUPLICATE KEY UPDATE`.

5. **Sottoscrizione `comandi` dopo `_on_connect`.** Nel simulatore, se la stazione era già `attiva` al boot, `_on_ready` viene chiamato dentro `registra()` prima che paho-mqtt abbia ricevuto `CONNACK` (il `loop_start` è asincrono). Le `subscribe` ai topic `comandi` finiscono in coda e vengono inviate appena la connessione è pronta — paho gestisce questa coda, ma su broker MQTT 5 stretti potrebbe servire `wait_for_publish`. In dev funziona.

6. **`MqttService::publish` apre/chiude una connessione MQTT ad ogni call** (admin "ready", controller "comandi"). Funziona ma è inefficiente. Per produzione tieni un client persistente.

7. **Il seeder `DatabaseSeeder` crea 5 stazioni + 15 punti random.** `Punti_ricaricaFactory` assegna `id_punto` da `numberBetween(1, 9)`: con 15 punti su 5 stazioni alcune combinazioni `(id_stazione, id_punto)` possono collidere e il seeder fallisce per duplicate PK. Se ti capita, abbassa `Punti_ricarica::factory(15)` a `factory(5)` o rendi il `id_punto` deterministico per stazione.

8. **Sessioni vecchie con `metodo_avvio = 'QR_CODE'`.** Se hai dati pre-migrazione, la enum non li accetta più. Il `migrate:fresh` risolve. In produzione servirebbe una migrazione di trasformazione che mappa `QR_CODE → CODICE`.

9. **`Stazioni::statoAggregatoPerPunto` ha cambiato firma.** Ora vuole `(id_stazione, id_punto)` invece del solo `id_punto`. Cerca eventuali chiamate residue prima di un rilascio.

10. **Broker MQTT anonimo.** Va benissimo in dev (la config `mqtt/config/mosquitto.conf` ha `allow_anonymous true`). In produzione abilita user/password e popola davvero `IOT_MQTT_USER` / `IOT_MQTT_PASSWORD`.

11. **Niente autenticazione device.** L'endpoint `/api/iot/registra` chiede solo la password globale `IOT_REGISTRATION_PASSWORD`; dopo, la colonnina parla solo con MQTT (anonimo). Se vorrai un secondo strato di sicurezza, reintroduci la colonna `stazioni.token`, un middleware `device.token` e ripristina gli endpoint HTTP `heartbeat_punto` / `termina_sessione`.

12. **L'admin "completa setup" aggiorna i punti in place** senza cancellare/ricreare nulla: il numero di prese e gli id sono fissati dalla colonnina al `POST /api/iot/registra` e non sono modificabili lato admin. Se la colonnina cambia il numero di prese dopo essere stata registrata, il backend logga un warning ma non tocca i record (servirebbe un endpoint di "ri-registrazione" o un reset manuale dal pannello).
