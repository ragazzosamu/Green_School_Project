<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se l'utente è già autenticato e tenta di accedere a pagine "guest"
 * (/login, /register), lo reindirizziamo alla mappa invece che a "home"
 * (che non esiste nel progetto, causava un loop /→/login).
 */
class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect('/map');
            }
        }

        return $next($request);
    }
}