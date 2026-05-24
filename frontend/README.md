# ⚛️ Frontend — Green School Project

**Single Page Application** in **React 18** (build con **Vite**) che consuma le API REST
del backend Laravel e si aggiorna in tempo reale via **WebSocket (Reverb)**.

> Per l'architettura completa vedi il [README principale](../README.md) e
> [`ARCHITECTURE.md`](../ARCHITECTURE.md). Per le API consumate, il
> [backend README → API REST](../backend/README.md#-api-rest--tutte-le-rotte).

---

## Indice

1. [Cosa fa](#-cosa-fa)
2. [Stack tecnico](#-stack-tecnico)
3. [Struttura `src/`](#-struttura-src)
4. [Avvio](#-avvio)
5. [Configurazione `.env`](#-configurazione-env)
6. [Autenticazione (token Sanctum)](#-autenticazione-token-sanctum)
7. [Comunicazione con il backend](#-comunicazione-con-il-backend)
8. [Aggiornamenti in tempo reale (Echo + Reverb)](#-aggiornamenti-in-tempo-reale-echo--reverb)
9. [Routing](#-routing)

---

## 🎯 Cosa fa

- **Login / registrazione** utente con token Sanctum salvato lato client.
- **Mappa** delle colonnine con stato in tempo reale (Leaflet).
- **Dettaglio stazione** + form per inserire il **codice monouso a 6 cifre**.
- **Sessione attiva**: kWh aggiornati live via WebSocket.
- **Profilo gamification**: XP, livello, badge, classifica top-10.
- **Sezione scuola**: profilo edificio e grafici dei consumi (Chart.js).

---

## 🧰 Stack tecnico

| Cosa                        | Libreria                          |
|-----------------------------|-----------------------------------|
| Framework UI                | React 18                          |
| Build tool / dev server     | Vite                              |
| Routing                     | React Router                      |
| Chiamate HTTP               | Axios (con interceptor token)     |
| WebSocket client            | Laravel Echo + pusher-js          |
| Mappa                       | Leaflet                           |
| Grafici                     | Chart.js                          |

---

## 🗂️ Struttura `src/`

```
src/
├── api/            client HTTP centralizzato (axios), gestione token
├── assets/         immagini e icone statiche
├── components/     componenti riutilizzabili (NavBar, BadgeCard, …)
├── context/        Context API: utente loggato, sessione attiva
├── hooks/          custom hook (es. sottoscrizioni Echo)
├── pages/          una cartella/file per ogni rotta della SPA
└── main.jsx        entry point + setup router + provider
```

---

## ▶️ Avvio

### Con Docker (consigliato)

Il frontend parte insieme agli altri servizi:

```bash
docker-compose up -d
```

Il container `green_react` espone il dev server di Vite su
[http://localhost:5173](http://localhost:5173) con **hot reload** attivo. Le modifiche
ai file `src/` si vedono subito senza ricostruire.

> ⚠️ Per parlare con il backend la SPA chiama `http://localhost/api` (porta 80,
> container `green_app`). Quindi anche `green_app` **deve essere su**.

### In locale (senza Docker)

```bash
cd frontend
npm install
npm run dev      # server di sviluppo Vite con hot reload (porta 5173)
npm run build    # build di produzione → dist/
npm run preview  # serve la build di produzione localmente
```

---

## ⚙️ Configurazione `.env`

Il file è in `.gitignore` — **va creato in locale su ogni PC**:

```bash
cd frontend
cp .env.example .env
```

Variabili dentro `.env`:

| Variabile               | Esempio                           | A cosa serve                                       |
|-------------------------|-----------------------------------|----------------------------------------------------|
| `VITE_REVERB_APP_KEY`   | `a350d2367d13394c34e998d87a135962`| **DEVE coincidere** con `REVERB_APP_KEY` del backend. Echo la usa nell'handshake WS. |
| `VITE_REVERB_HOST`      | `localhost`                       | Host visto dal browser per il WebSocket            |
| `VITE_REVERB_PORT`      | `5173`                            | Porta del proxy WS di Vite (vedi `vite.config.js`) |
| `VITE_REVERB_SCHEME`    | `http`                            | `http` in dev, `https` se servi via ngrok/produzione |

> ⚠️ **Senza `.env` i WebSocket non funzionano**: Echo apre la connessione con
> `key=undefined`, Reverb rifiuta l'handshake, e i kWh in tempo reale restano fermi.
> Sintomo tipico: nella sessione attiva "Energia erogata" non si aggiorna mai.

---

## 🔐 Autenticazione (token Sanctum)

La SPA usa **Laravel Sanctum** con token Bearer (no cookie di sessione, no CSRF).

### Login

1. La pagina Login fa `POST /api/login` con `{ email, password }`.
2. Il backend risponde:
   ```json
   { "access_token": "7|AbCd…XyZ", "token_type": "Bearer",
     "user": { "id_utente": "…", "email": "…", "ruolo": "utente|admin", … } }
   ```
3. La SPA salva `access_token` in `localStorage` e l'oggetto `user` nel context.
4. Tutte le chiamate successive partono dal client HTTP (`src/api/client.js`) che
   aggiunge automaticamente l'header `Authorization: Bearer <token>` via interceptor.

### Persistenza al refresh

Al boot la SPA legge il token da `localStorage`. Se presente, prova subito una chiamata
"di verifica" (es. `GET /api/me/sessione-attiva`): se risponde **401**, il token è
stato revocato → la SPA pulisce `localStorage` e rimanda al login.

### Logout

`POST /api/logout` revoca il **token corrente**. Il client lo chiama e poi pulisce
`localStorage`. Token diversi dello stesso utente (es. una seconda sessione in altro
browser) restano validi.

### Gestione degli errori auth

| HTTP | Significato                            | Cosa fa la SPA                                  |
|------|-----------------------------------------|-------------------------------------------------|
| 401  | Token mancante o revocato               | Logout automatico, redirect a `/react/login`    |
| 403  | Token valido ma rotta non autorizzata (es. admin) | Mostra messaggio "Non autorizzato"   |
| 429  | Lockout dopo 5 login falliti            | Mostra il countdown letto da `Retry-After`      |

---

## 🌐 Comunicazione con il backend

Il modulo `src/api/client.js` espone un'istanza axios pre-configurata:

```js
import apiClient from '../api/client';

// GET protetto: header Bearer aggiunto dall'interceptor
const { data } = await apiClient.get('/stations');

// POST con id stazione nel path
await apiClient.post(`/${encodeURIComponent(id)}/verifica-codice`, { codice });
```

- **Base URL** → `import.meta.env.VITE_API_BASE_URL || '/api'`.
- **Interceptor request**: aggiunge `Authorization: Bearer <token>` se in `localStorage`.
- **Interceptor response**: su 401 fa logout + redirect.

Esempi di rotte chiamate (elenco completo nel [backend README](../backend/README.md#-api-rest--tutte-le-rotte)):

| Pagina React           | Rotte API                                                  |
|------------------------|------------------------------------------------------------|
| Login / Register       | `POST /api/login`, `POST /api/register`                    |
| Mappa                  | `GET /api/stations`                                        |
| Dettaglio stazione     | `GET /api/station/{id}`, `POST /api/{id}/verifica-codice`  |
| Sessione attiva        | `GET /api/me/sessione-attiva`, `GET /api/me/attesa-cavo`, `POST /api/session/{id}/stop` |
| Profilo gamification   | `GET /api/gamification/{profile,badges,sessioni,sfide}`    |
| Classifica             | `GET /api/gamification/leaderboard`                        |
| Scuola                 | `GET /api/school/profile`, `GET /api/school/consumption`   |

---

## 📡 Aggiornamenti in tempo reale (Echo + Reverb)

I dati live (kWh durante la ricarica, eventi sessione, stato delle prese sulla mappa)
arrivano via **WebSocket** dal server **Reverb** del backend. La SPA usa **Laravel Echo**
come client.

### Setup di Echo

Avviene una sola volta a livello globale (vedi `src/main.jsx` o equivalente):

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  authEndpoint: '/api/broadcasting/auth',                  // endpoint Sanctum
  auth: { headers: { Authorization: `Bearer ${token}` } }, // letto da localStorage
});
```

> L'`authEndpoint` punta a **`/api/broadcasting/auth`** (non `/broadcasting/auth`):
> il primo accetta il Bearer token, il secondo usa la sessione cookie ed è per Blade.

### Canali

| Canale                       | Tipo     | Eventi ascoltati                                                 |
|------------------------------|----------|-------------------------------------------------------------------|
| `private-user.{id_utente}`   | privato  | `.sessione.avviata`, `.ricarica.heartbeat` (delta kWh ogni ~5s)   |
| `mappa`                      | pubblico | `.punto.status`, `.punto.hardware.status`, `.stazione.status`     |

Esempio sottoscrizione (`useEffect` dentro la pagina sessione):

```js
const canale = window.Echo.private(`user.${user.id_utente}`);
canale.listen('.ricarica.heartbeat', (e) => {
  setKwh((prev) => prev + e.cambiamento_kwh);
});
return () => canale.stopListening('.ricarica.heartbeat');
```

> ⚠️ L'evento `.ricarica.heartbeat` porta il **delta** di kWh dall'ultima telemetria,
> non il totale. Va sommato lato client.

---

## 🛣️ Routing

Tutte le rotte React vivono sotto il prefisso `/react/...` per non collidere con le
pagine Blade che il backend serve ancora su `/login`, `/map`, `/profilo`, ecc.

| Path SPA                        | Pagina                              |
|---------------------------------|-------------------------------------|
| `/react/login`                  | Login                               |
| `/react/register`               | Registrazione                       |
| `/react/map`                    | Mappa stazioni                      |
| `/react/stazione/:id`           | Dettaglio stazione + form codice    |
| `/react/profilo`                | Profilo utente + gamification       |
| `/react/sessione/:uuid`         | Sessione in corso (kWh live)        |
| `/react/classifica`             | Top-10 utenti                       |
| `/react/scuola`                 | Profilo scuola + grafici consumi    |

Le rotte protette sono avvolte da un componente `<RequireAuth>` che reindirizza a
`/react/login` se il context utente è vuoto.
