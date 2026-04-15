# Green School Project – Setup iniziale

> ⚠️ **Importante:** questa procedura va eseguita **una sola volta** all'inizio del progetto.

---

## 📦 Prerequisiti

- [GitHub Desktop](https://desktop.github.com/) (o Git CLI)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (in esecuzione)
- [DBeaver](https://dbeaver.io/) (opzionale, per gestire il database)

---

## 🚀 Procedura passo passo

### 1. Clona la repository

Apri GitHub Desktop e clona il repository, oppure usa il terminale:

```bash
git clone <url-del-repository> 
cd Green_School_Project

```
oppure usa git hub desktop

# [AVVIO CONTAINER IN BACKGROUND]
docker-compose up -d

# [INSTALLAZIONE DIPENDENZE]
docker exec -it green_app bash
composer install
exit

# [IMPORTAZIONE DATI SQL]
Get-Content database.sql | docker exec -i green_db mariadb -uadmin -ppassword db_green_school

# [PARAMETRI DI CONNESSIONE DBEAVER]
Tipo: MariaDB
Host: localhost
Porta: 3306
Database: db_green_school
Username: admin
Password: password

# [COMANDI DI GESTIONE]
docker-compose stop      # Ferma i container (senza eliminarli)
docker-compose down      # Spegne e rimuove i container
docker logs -f green_app # Visualizza gli errori PHP in tempo reale