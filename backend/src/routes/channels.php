<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Autorizzazioni canali WebSocket.
 *
 * Echo (browser) chiama POST /broadcasting/auth quando sottoscrive un
 * PrivateChannel. Laravel chiama qui sotto la closure corrispondente al
 * pattern del canale: deve restituire true (utente autorizzato) o false.
 */

// Canale privato per-utente: ricariche, telemetria, eventi sessione.
// id_utente nella URL DEVE corrispondere all'utente loggato.
Broadcast::channel('user.{id_utente}', function ($user, $id_utente) {
    return $user->id_utente === $id_utente;
});
