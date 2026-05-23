<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware per le rotte API admin React.
 * A differenza di AdminMiddleware (che fa redirect),
 * questo restituisce JSON 403 per i client REST.
 */
class AdminApiMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->ruolo !== 'admin') {
            return response()->json(['message' => 'Accesso riservato agli amministratori.'], 403);
        }

        return $next($request);
    }
}
