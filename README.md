# Green School Project – Setup iniziale

---

## 📦 Prerequisiti

- [GitHub Desktop](https://desktop.github.com/) (o Git CLI)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (in esecuzione)
- [DBeaver](https://dbeaver.io/) (opzionale, per gestire il database)
- [Node.Js](https://nodejs.org/) (Serve per far funzionare i WebSocket (Reverb) con il browser)

---

## 📑 Documenti comuni
 - **Tabella di marcia**: [Google Sheet Lavoro](https://docs.google.com/spreadsheets/d/1Zt4d4UcoLS2TqQH-RpuAC34dMfk1Hsu7lD3KwQfXs3c/edit?usp=sharing)

---

## 🚀 Procedura passo passo

## 1. Clona la repository

Apri GitHub Desktop e clona il repository, oppure usa il terminale:

```bash
git clone <url-del-repository> 
cd Green_School_Project
```
oppure usa git hub desktop

## 2. Configurazione file .env
```bash
cd src
cp .env.example .env
```

## 3. Installazione Dipendenze e Compilazione (Vite)
**ATTENZIONE:** Questi comandi vanno lanciati sul tuo PC (Windows/Mac), NON dentro Docker.
```bash
cd src
npm install
npm install --save-dev laravel-echo pusher-js
npm run build
```

## 4. Avvio container in background
```bash
docker-compose up -d
```

## 5. Importazione dati sql
```bash
docker exec -it green_app php artisan migrate:fresh --seed
```

## 6. Parametri di connessione DBeaver
 - Tipo: MySql
 - Host: localhost
 - Porta: 3306
 - Database: db_green_school
 - Username: admin
 - Password: password

 ### 🌐 Accesso Browser
* **Sito Web:** [http://localhost](http://localhost)
* **WebSocket Test:** *work in progress*

## Comandi utili
```bash
# Genera il QR code SVG per una stazione di ricarica (sostituisci 1 con l'ID della stazione)
# L'output si trova nella cartella storage/app/private/public/qrcodes
docker exec -it green_app php artisan app:genera 1
```

```bash
# Genera tutti i QR
# L'output si trova nella cartella storage/app/private/public/qrcodes
docker exec -it green_app php artisan app:genera-tutti
```

## Comandi di gestione
```bash
docker-compose stop      # Ferma i container (senza eliminarli)
docker-compose down      # Spegne e rimuove i container
docker logs -f green_app # Visualizza gli errori PHP in tempo reale
```

## 🚀 Guida ai Test API con Postman

Utilizziamo un file JSON per condividere le rotte. Grazie all'uso delle **variabili di ambiente** e degli **script automatici**, non dovrai mai cambiare manualmente l'URL delle richieste o incollare i Token a mano.

## 1. 🌍 Setup Ambiente (Da fare SOLO la prima volta)
Per far sì che Postman sappia dove punta il tuo container locale:
1. In alto a destra, clicca su **Environments**.
2. Clicca sul tasto **+** e chiama l'ambiente `Sviluppo Locale`.
3. Aggiungi la variabile `api`.
4. Nel campo **Initial Value**, scrivi l'indirizzo completo fino alla cartella api:
   - `http://localhost/api`
5. Clicca su **Save** in alto a destra.
6. **IMPORTANTE:** In alto a destra nell'interfaccia principale di Postman, dove c'è scritto "No Environment", seleziona dal menu a tendina `Sviluppo Locale`.

## 2. 📂 Importazione Collezione
1. Scarica il file `postman/Green_School_Project.postman_collection.json` dal progetto.
2. Su Postman, clicca **Import** e trascina il file.
3. Se lo avevi già, seleziona **Replace**.
4. Ora puoi lanciare le richieste. Noterai che l'URL è scritto come `{{api}}/NOME_ROTTA`: Postman sostituirà automaticamente `{{api}}` con l'indirizzo del tuo ambiente.

## 3. 🔐 Autenticazione Automatica (Login & Token)
Il progetto usa Laravel Sanctum per l'autenticazione. **Non devi copiare e incollare il token a mano!**
1. Apri la cartella della collezione importata.
2. Cerca la richiesta **Login** e aprila.
3. Clicca su **Send**.
4. *Magia:* Uno script integrato leggerà la risposta del server e salverà automaticamente il tuo Token d'accesso.
5. Da questo momento, puoi lanciare qualsiasi altra rotta protetta (es. Profilo, Ricariche): Postman allegherà il tuo "Badge VIP" in completa autonomia.

---

## 🔄 Cosa fare ad ogni modifica (Regole del Team)
Se modifichi un controller o aggiungi una rotta su Laravel:

1. **Aggiorna Postman:** Crea o modifica la richiesta nel tuo Postman locale.
2. **Esporta il JSON:**
   - Clicca sui tre puntini `...` della collezione -> **More** -> **Export**.
   - Salva il file sovrascrivendo quello nella repository del progetto Git.
3. **Commit & Push:** Carica il file JSON su GitHub insieme al tuo codice PHP.
4. **Segnala:** Scrivi sul gruppo: *"Nuova rotta aggiunta: [nome rotta]. Fate pull e re-importate il JSON!"*.
5. **Ricezione:** I compagni fanno `git pull` e re-importano il file (l'ambiente `Sviluppo Locale` non va toccato, continuerà a funzionare e il token automatico non si romperà).
