# 🖥️ Backend — Green School Project

Backend del progetto, scritto in **Laravel**. È il cervello del sistema: espone le
**API REST**, gestisce il **database**, l'autenticazione degli utenti, la comunicazione
con le colonnine di ricarica (via **MQTT**) e gli aggiornamenti in tempo reale verso il
browser (via **WebSocket**).

> Il codice Laravel vero e proprio è nella sottocartella [`src/`](src/).
> Per l'architettura completa (MQTT, WebSocket, Redis, flussi, schema DB) vedi
> [`ARCHITECTURE.md`](../ARCHITECTURE.md).

---

## 🎯 Di cosa si occupa

- **Autenticazione utenti** tramite Laravel Sanctum (login con token Bearer) e sessione web.
- **Mappa delle stazioni**: elenco colonnine e punti di ricarica con il loro stato.
- **Avvio sessione di ricarica** con il **codice monouso a 6 cifre** mostrato dalla
  colonnina (non più QR): l'utente lo digita, il backend lo verifica.
- **Gestione sessioni**: monitoraggio dei kWh in tempo reale e terminazione.
- **Comunicazione IoT via MQTT**: registrazione colonnine, heartbeat, telemetria, eventi.
- **Gamification**: XP, livelli, badge, classifica e sfide settimanali.
- **Pannello admin**: setup stazioni, manutenzione, gestione utenti, report.

---

## 🧩 Processi del backend

Lo stesso codice Laravel gira in più container con ruoli diversi:

| Container             | Comando                          | Ruolo                                              |
|-----------------------|----------------------------------|----------------------------------------------------|
| `green_app`           | Apache + PHP                     | API REST e sito web                                |
| `green_reverb`        | `artisan reverb:start`           | Server WebSocket (aggiornamenti live al browser)   |
| `green_queue`         | `artisan queue:work`             | Worker dei job in coda                             |
| `green_mqtt_worker`   | `artisan mqtt:leggi`             | Consuma i messaggi MQTT dalle colonnine            |
| `green_heartbeat_checker` | `artisan app:heartbeat-checker` | Watchdog: marca offline i punti silenti         |

---

## 🔌 API principali

Tutte le rotte sono sotto `/api` (vedi [`src/routes/api.php`](src/routes/api.php)).

### Pubbliche
| Metodo | Rotta            | Descrizione                                        |
|--------|------------------|----------------------------------------------------|
| GET    | `/health`        | Healthcheck (usato da Docker)                      |
| POST   | `/login`         | Login utente, restituisce il token Sanctum         |
| POST   | `/iot/registra`  | Registrazione di una colonnina (MAC + password)    |

### Protette — richiedono token Sanctum (`auth:sanctum`)
| Metodo | Rotta                       | Descrizione                                  |
|--------|-----------------------------|----------------------------------------------|
| GET    | `/stations`                 | Elenco stazioni per la mappa                 |
| GET    | `/station/{id}`             | Dettaglio di una singola stazione            |
| POST   | `/verifica-codice`          | Avvia il rendez-vous con il codice monouso   |
| GET    | `/me/sessione-attiva`       | Sessione attiva dell'utente (polling)        |
| GET    | `/session/{id}`             | Stato di una sessione di ricarica            |
| POST   | `/session/{id}/stop`        | Interrompe una sessione in corso             |
| GET    | `/school/profile`           | Profilo della scuola                         |
| GET    | `/school/consumption`       | Consumi energetici mensili                   |
| GET    | `/gamification/profile`     | XP, livello, CO₂, streak                     |
| GET    | `/gamification/badges`      | Badge sbloccati e da sbloccare               |
| GET    | `/gamification/leaderboard` | Classifica top 10 per XP                     |
| GET    | `/gamification/sessioni`    | Ultime sessioni dell'utente                  |
| GET    | `/gamification/sfide`       | Sfide settimanali                            |
| POST   | `/logout`                   | Invalida il token corrente                   |

> **Heartbeat e fine sessione delle colonnine NON passano da HTTP**: viaggiano su
> MQTT e vengono consumati dal worker `mqtt:leggi`. Non esiste più un endpoint
> device-autenticato (`X-Device-Token`): la colonnina, dopo `/iot/registra`,
> comunica solo via MQTT.

---

## 🗂️ Struttura (`src/`)

| Cartella / file               | Contenuto                                                  |
|-------------------------------|------------------------------------------------------------|
| `app/Http/Controllers/Api/`   | Controller API (`Auth`, `Station`, `Session`, `Iot`, `School`, `Gamification`) |
| `app/Http/Controllers/`       | `AdminController`, `WebAuthController` (sito e pannello)    |
| `app/Http/Middleware/`        | Middleware, incluso `admin` per il pannello                |
| `app/Models/`                 | Modelli Eloquent (utenti, stazioni, punti, sessioni, gamification…) |
| `app/Services/`               | Logica di dominio (`SessioneService`, `GamificationService`, `MqttService`…) |
| `app/Console/Commands/`       | `MqttWorker` (`mqtt:leggi`), `HeartbeatChecker` (`app:heartbeat-checker`) |
| `app/Events/`                 | Eventi broadcast verso il WebSocket                        |
| `database/migrations/`        | Tabelle, stored procedure e trigger                        |
| `database/seeders/`           | Dati di base (utenti, badge, profilo scuola, consumi)      |
| `routes/api.php` · `web.php`  | Rotte API e rotte web/admin                                |

---

## 🔑 Concetti chiave

- **Autenticazione utenti**: token Bearer (Sanctum) per le API, sessione cookie per il sito.
- **Registrazione colonnine**: la stazione si auto-registra con `POST /iot/registra`
  usando una password globale; poi comunica solo via MQTT.
- **Codice monouso**: la colonnina genera un codice a 6 cifre che cambia di continuo
  (TTL 60s in Redis). Sostituisce il vecchio QR statico, poco sicuro.
- **Stored procedure**: avvio e terminazione sessione sono transazionali, gestite da
  procedure SQL create dalle migrazioni.
- **Redis**: stato volatile (codice, prenotazioni, kWh live) e niente race condition.

---

## 🛠️ Comandi artisan utili

```bash
# Ricrea il database da zero e lo popola
docker exec -it green_app php artisan migrate:fresh --seed

# Pulisce la cache di Laravel
docker exec -it green_app php artisan optimize:clear

# Worker MQTT in foreground (di solito gira nel container green_mqtt_worker)
docker exec -it green_app php artisan mqtt:leggi
```

Per l'avvio dell'intero stack e i test con Postman, vedi il [README principale](../README.md).
