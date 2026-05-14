# 🖥️ Backend — Green School Project

Backend del progetto, scritto in **Laravel**. È il cervello del sistema: espone le
**API REST**, gestisce il **database**, l'autenticazione degli utenti e la comunicazione
con le colonnine di ricarica.

> Il codice Laravel vero e proprio è nella sottocartella [`src/`](src/).
> Questo README descrive *cosa fa* il backend; verrà ampliato man mano che il progetto avanza.

---

## 🎯 Di cosa si occupa

- **Autenticazione utenti** tramite Laravel Sanctum (login con token).
- **Mappa delle stazioni**: elenco colonnine e relativi punti di ricarica con il loro stato.
- **Avvio sessione di ricarica**: l'utente scansiona un QR code, il backend verifica la
  firma e crea la sessione.
- **Gestione sessioni**: monitoraggio e interruzione di una ricarica in corso.
- **Endpoint IoT**: riceve heartbeat e fine-sessione dalle colonnine (fisiche o simulate).
- **Generazione QR code** firmati per ogni stazione.

---

## 🔌 API principali

Tutte le rotte sono sotto `/api` (vedi `src/routes/api.php`).

### Pubbliche
| Metodo | Rotta     | Descrizione                          |
|--------|-----------|--------------------------------------|
| POST   | `/login`  | Login utente, restituisce il token   |

### Protette — richiedono token Sanctum (`auth:sanctum`)
| Metodo | Rotta                  | Descrizione                                      |
|--------|------------------------|--------------------------------------------------|
| GET    | `/stations`            | Elenco stazioni per la mappa                     |
| GET    | `/station/{id}`        | Dettaglio di una singola stazione                |
| POST   | `/scan-qr`             | Avvia una sessione dopo la scansione del QR      |
| GET    | `/session/{id}`        | Stato di una sessione di ricarica                |
| POST   | `/session/{id}/stop`   | Interrompe una sessione in corso                 |
| POST   | `/logout`              | Invalida il token corrente                       |

### IoT — riservate alle colonnine (`device.token`)
Autenticate tramite header `X-Device-Token` (token della stazione).
| Metodo | Rotta                              | Descrizione                                |
|--------|------------------------------------|--------------------------------------------|
| POST   | `/heartbeat_punto`                 | La colonnina segnala di essere viva        |
| POST   | `/{id_punto}/termina_sessione`     | La colonnina comunica la fine di una ricarica |

---

## 🗂️ Struttura (`src/`)

| Cartella / file                     | Contenuto                                              |
|-------------------------------------|--------------------------------------------------------|
| `app/Http/Controllers/Api/`         | Controller delle API (`Auth`, `Station`, `Session`, `Iot`) |
| `app/Http/Middleware/`              | Middleware, incluso `device.token` per le rotte IoT    |
| `app/Models/`                       | Modelli Eloquent (utenti, stazioni, punti, sessioni, gamification…) |
| `app/Console/Commands/`             | Comandi artisan custom (generazione QR, token stazioni) |
| `database/migrations/`              | Migrazioni: tabelle, stored procedure, trigger         |
| `database/seeders/`                 | Dati di esempio per popolare il DB                     |
| `routes/api.php`                    | Definizione delle rotte API                            |

---

## 🔑 Concetti chiave

- **Autenticazione utenti**: token Bearer rilasciato al login da Laravel Sanctum.
- **Autenticazione colonnine**: ogni stazione ha un token segreto; le rotte IoT lo
  verificano col middleware `device.token` (header `X-Device-Token`).
- **QR code firmati**: il QR di una stazione contiene una firma che il backend valida
  in `/scan-qr`, così non si possono avviare sessioni con QR falsi.
- **Stored procedure**: la logica critica delle sessioni (avvio/terminazione) è gestita
  da stored procedure SQL, create dalle migrazioni.

---

## 🛠️ Comandi artisan utili

```bash
# Ricrea il database da zero e lo popola
docker exec -it green_app php artisan migrate:fresh --seed

# Genera il QR di una stazione (output in storage/app/private/public/qrcodes)
docker exec -it green_app php artisan app:genera 1

# Genera tutti i QR
docker exec -it green_app php artisan app:genera-tutti

# Stampa i token delle stazioni
docker exec -it green_app php artisan app:stampa-token
```

Per l'avvio dell'intero stack e i test con Postman, vedi il [README principale](../README.md).
