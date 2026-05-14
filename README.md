# ⚡ Green School Project

Sistema di gestione per **stazioni di ricarica** di veicoli elettrici in ambito scolastico:
mappa delle colonnine, avvio ricarica tramite QR code, monitoraggio in tempo reale dei
consumi e gamification.

---

## 🧩 Architettura

Il progetto gira interamente in **Docker**. I servizi che compongono lo stack:

| Servizio   | Container            | Ruolo                                                              |
|------------|----------------------|--------------------------------------------------------------------|
| `app`      | `green_app`          | Backend **Laravel** (API REST + sito web). Porta `80`              |
| `mariadb`  | `green_db`           | Database **MariaDB**. Porta `3306`                                 |
| `redis`    | `green_redis`        | Cache, code e sessioni                                             |
| `queue`    | `green_queue`        | Worker Laravel per i job in coda                                   |
| `mqtt`     | `green_mqqt-broker`  | Broker **Mosquitto**: canale di comunicazione con le colonnine. Porte `1883` (MQTT) e `9001` (WebSocket) |
| `reverb`   | `green_reverb`       | WebSocket per il browser (in via di sostituzione con MQTT)          |
| `python`   | `green_simulatore`   | **Simulatore** delle colonnine di ricarica (vedi `simulatore/`)    |

```
   Browser ──HTTP──► app (Laravel) ──► MariaDB / Redis
                        │
                        └──MQTT──► mqtt (Mosquitto) ◄──MQTT── simulatore (colonnine)
```

📂 **Documentazione per componente**
- Backend Laravel → [`backend/README.md`](backend/README.md)
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

### 3. Installa e compila gli asset frontend
> ⚠️ Questi comandi vanno lanciati **sul tuo PC**, NON dentro Docker.
```bash
cd backend/src
npm install
npm install --save-dev laravel-echo pusher-js
npm run build
```

### 4. Avvia i container
```bash
docker-compose up -d
```

### 5. Crea e popola il database
```bash
docker exec -it green_app php artisan migrate:fresh --seed
```

---

## 🌐 Punti di accesso

| Cosa             | Indirizzo                                  |
|------------------|--------------------------------------------|
| Sito web         | [http://localhost](http://localhost)       |
| API              | `http://localhost/api`                     |
| Broker MQTT      | `localhost:1883`                           |
| MQTT su WebSocket| `localhost:9001`                           |

**Connessione DBeaver:**
- Tipo: `MySQL` · Host: `localhost` · Porta: `3306`
- Database: `db_green_school` · Username: `admin` · Password: `password`

---

## 🛠️ Comandi utili

```bash
# Genera il QR code SVG di una stazione (output in storage/app/private/public/qrcodes)
docker exec -it green_app php artisan app:genera 1

# Genera tutti i QR
docker exec -it green_app php artisan app:genera-tutti

# Stampa i token delle stazioni
docker exec -it green_app php artisan app:stampa-token
```

### Gestione container
```bash
docker-compose stop          # Ferma i container (senza eliminarli)
docker-compose down          # Spegne e rimuove i container (immagini e volumi restano)
docker-compose down --rmi all # Spegne e rimuove anche le IMMAGINI
docker-compose up -d          # Avvia (riusa le immagini esistenti, NON ricostruisce)
docker-compose up -d --build # Avvia RICOSTRUENDO le immagini (rifà pip install / composer install)
docker logs -f green_app     # Log PHP in tempo reale
docker logs -f green_simulatore   # Log del simulatore colonnine
```

> 💡 **Quando serve `--build`?** Il codice (`.py`, `.php`) è montato come volume, quindi
> le modifiche si vedono subito senza ricostruire. Ma le **dipendenze** (`requirements.txt`,
> `composer.json`) sono installate *dentro l'immagine*: se le cambi devi rifare il build,
> altrimenti Docker riusa l'immagine vecchia con le dipendenze vecchie.
> Vedi la sezione [Volumi vs Immagine](#-volumi-vs-immagine--cosa-cambia-dove).

### Pulire la cache di Laravel
```bash
docker exec -it green_app php artisan optimize:clear
# oppure singolarmente:
docker exec -it green_app php artisan config:clear
docker exec -it green_app php artisan cache:clear
docker exec -it green_app php artisan route:clear
docker exec -it green_app php artisan view:clear
```

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

---

## 📦 Volumi vs Immagine — cosa cambia dove

Capire questa distinzione evita il 90% dei "perché non funziona se ho già modificato il file?".

**L'immagine** è la "fotografia" costruita dal `Dockerfile`: dentro ci finiscono il sistema,
gli strumenti e le **dipendenze installate** (`pip install -r requirements.txt`,
`composer install`). Si crea/aggiorna solo con un **build**.

**Il volume** (bind mount, es. `./simulatore:/simulatore` o `./backend/src:/var/www/html`)
è una cartella del tuo PC "prestata" al container: i file sono gli stessi, quindi se
modifichi il codice sull'host lo vedi subito anche nel container, **senza ricostruire**.

Il punto chiave: **il volume monta SOPRA l'immagine**. Quindi il codice viene dal tuo PC,
ma le dipendenze NO — quelle stanno in posti dell'immagine (`site-packages`, `vendor/`)
che il volume non tocca.

| Cosa hai cambiato | Serve il rebuild? | Comando |
|---|---|---|
| Codice (`.py`, `.php`, `.blade.php`) | ❌ No, basta il volume | il file è già aggiornato |
| `requirements.txt` / `composer.json` | ✅ Sì | `docker-compose up -d --build` |
| `Dockerfile` | ✅ Sì | `docker-compose up -d --build` |
| `docker-compose.yaml` | ❌ No (ricrea il container) | `docker-compose up -d` |

> Esempio concreto: il simulatore gira sul codice montato da `./simulatore`, quindi se
> tocchi `stazione.py` la modifica c'è subito. Ma se aggiungi una libreria a
> `requirements.txt`, finché non fai `--build` il `pip install` non viene rifatto e il
> container continua a girare senza quella libreria → `ModuleNotFoundError`.

### Ispezionare MQTT
```bash
# Vede tutti i messaggi che passano sul broker
docker exec -it green_mqqt-broker mosquitto_sub -t 'stazione/#' -v
```
Oppure usa **MQTT Explorer** connettendoti a `localhost:1883`.

---

## 🧪 Guida ai Test API con Postman

Usiamo un file JSON condiviso per le rotte. Grazie alle **variabili d'ambiente** e agli
**script automatici**, non devi mai cambiare a mano gli URL o incollare i token.

### 1. Setup ambiente (solo la prima volta)
1. In alto a destra, clicca su **Environments**.
2. Crea un nuovo ambiente con il tasto **+** e chiamalo `Sviluppo Locale`.
3. Aggiungi la variabile `api` con **Initial Value** = `http://localhost/api`.
4. Salva, poi seleziona `Sviluppo Locale` dal menu a tendina in alto a destra.

### 2. Importazione collezione
1. Su Postman, clicca **Import** e trascina `postman/Green_School_Project.postman_collection.json`.
2. Se la avevi già, scegli **Replace**.
3. Gli URL sono scritti come `{{api}}/NOME_ROTTA`: Postman sostituisce `{{api}}` da solo.

### 3. Autenticazione automatica (Login & Token)
Il progetto usa **Laravel Sanctum**. Non serve copiare il token a mano:
1. Apri la richiesta **Login** e clicca **Send**.
2. Uno script salva automaticamente il token d'accesso.
3. Da qui in poi tutte le rotte protette useranno il token in autonomia.

---

## 🔄 Regole del team — ad ogni modifica delle API

Se modifichi un controller o aggiungi una rotta su Laravel:

1. **Aggiorna Postman**: crea/modifica la richiesta nel tuo Postman locale.
2. **Esporta il JSON**: tre puntini `...` sulla collezione → **Export**, sovrascrivi il file nella repo.
3. **Commit & Push**: carica il JSON insieme al codice PHP.
4. **Segnala** sul gruppo: *"Nuova rotta: [nome]. Fate pull e re-importate il JSON!"*.
5. **Ricezione**: i compagni fanno `git pull` e re-importano il file (l'ambiente `Sviluppo Locale` non va toccato).
