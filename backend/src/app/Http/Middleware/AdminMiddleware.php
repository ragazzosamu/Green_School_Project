<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che protegge tutte le rotte /admin/*.
 * Se l'utente non è autenticato → redirect al login.
 * Se è autenticato ma non admin → redirect alla mappa con messaggio di errore.
 *
 * NOTA: Non usiamo abort(403) perché causerebbe una pagina bianca e, in alcuni
 * casi, invaliderebbe la sessione Laravel perdendo lo stato dell'utente loggato.
 * Il redirect pulito verso /map preserva la sessione e mostra un feedback chiaro.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->ruolo !== 'admin') {
            return redirect()->route('map')
                ->with('error', 'Solo gli amministratori sono autorizzati ad accedere a questa sezione.');
        }

        return $next($request);
    }
}