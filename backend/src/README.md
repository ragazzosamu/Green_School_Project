# Green School Project — Applicazione Laravel

Questa cartella contiene il codice **Laravel** del backend: API REST, sito web,
pannello admin, worker MQTT e WebSocket.

> Questo non è un progetto Laravel generico: fa parte del **Green School Project**.

## Documentazione

- **Cosa fa il backend e le API** → [`../README.md`](../README.md)
- **Architettura** (MQTT, WebSocket, Redis, flussi, schema DB) → [`../../ARCHITECTURE.md`](../../ARCHITECTURE.md)
- **Avvio dello stack e setup** → [README principale](../../README.md)

## Avvio rapido

Il backend non si avvia da qui a mano: gira nei container Docker definiti in
`docker-compose.yaml`. Dalla radice del progetto:

```bash
docker-compose up -d
docker exec -it green_app php artisan migrate:fresh --seed
```

## Struttura

| Cartella                | Contenuto                                              |
|-------------------------|--------------------------------------------------------|
| `app/Http/Controllers/` | Controller API, web e admin                            |
| `app/Services/`         | Logica di dominio (sessioni, gamification, MQTT)       |
| `app/Console/Commands/` | Worker MQTT (`mqtt:leggi`) e watchdog heartbeat        |
| `app/Events/`           | Eventi broadcast verso il WebSocket (Reverb)           |
| `database/migrations/`  | Tabelle, stored procedure e trigger                    |
| `database/seeders/`     | Dati di base (utenti, badge, scuola)                   |
| `routes/`               | `api.php` (API), `web.php` (sito + admin)              |
| `resources/views/`      | Viste Blade del sito web                               |
