# ⚛️ Frontend — Green School Project

Frontend del progetto: una **Single Page Application** in **React** (build con **Vite**)
che consuma le API REST del backend Laravel.

> Per l'architettura completa del progetto vedi il [README principale](../README.md)
> e [`ARCHITECTURE.md`](../ARCHITECTURE.md).

---

## 🎯 Cosa fa

- **Login** utente con token (Laravel Sanctum) conservato lato client.
- **Mappa** delle colonnine con stato in tempo reale.
- **Avvio ricarica** con il codice monouso a 6 cifre.
- **Sessione attiva**: kWh aggiornati live via WebSocket.
- **Profilo gamification**: XP, livello, badge, classifica.
- **Sezione scuola**: grafici dei consumi energetici.

---

## 🗂️ Struttura (`src/`)

| Cartella       | Contenuto                                               |
|----------------|---------------------------------------------------------|
| `pages/`       | Le pagine della SPA (mappa, profilo, scuola, sessione…) |
| `components/`  | Componenti riutilizzabili                               |
| `context/`     | Context API (utente loggato, sessione attiva)           |
| `hooks/`       | Custom hook (es. canali WebSocket)                      |
| `api/`         | Client per le chiamate alle API del backend             |
| `assets/`      | Immagini e risorse statiche                             |

---

## ▶️ Avvio

### Con Docker (consigliato)
Il frontend parte insieme agli altri servizi (`docker-compose up -d`) nel container
`green_react`, raggiungibile su [http://localhost:5173](http://localhost:5173).

### In locale
```bash
npm install
npm run dev      # server di sviluppo Vite con hot reload
npm run build    # build di produzione
```

> Il backend deve essere in esecuzione: la SPA chiama le API su `/api`.
