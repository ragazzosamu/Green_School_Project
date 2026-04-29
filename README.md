# Green School Project – Setup iniziale

---

## 📦 Prerequisiti

- [GitHub Desktop](https://desktop.github.com/) (o Git CLI)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (in esecuzione)
- [DBeaver](https://dbeaver.io/) (opzionale, per gestire il database)

---

## Documenti comuni
 - Link per il Google Sheet in cui segnare il lavoro : https://docs.google.com/spreadsheets/d/18hh5FdGq2FQlrV5g2JsqiP4sDy6E7cDKPTx9qOVLbyI/edit?usp=sharing

## 🚀 Procedura passo passo

### 1. Clona la repository

Apri GitHub Desktop e clona il repository, oppure usa il terminale:

```bash
git clone <url-del-repository> 
cd Green_School_Project

```
oppure usa git hub desktop

## 2. Avvio container in background
```bash
docker-compose up -d
```

## 3. Installazione dipendenze
```bash
docker exec -it green_app bash
composer install
exit
```
# 4. Configurazione file .env
rinominate il file .env.example in .env

# 5. Importazione dati sql
```bash
docker exec -it green_app php artisan migrate:fresh --seed
```

# 6. Parametri di connessione DBeaver
 - Tipo: MySql
 - Host: localhost
 - Porta: 3306
 - Database: db_green_school
 - Username: admin
 - Password: password



## Comandi di gestione
```bash
docker-compose stop      # Ferma i container (senza eliminarli)
docker-compose down      # Spegne e rimuove i container
docker logs -f green_app # Visualizza gli errori PHP in tempo reale
```
