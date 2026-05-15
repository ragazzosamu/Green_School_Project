<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // withBroadcasting registra anche la route POST /broadcasting/auth, che
    // serve a Echo per autorizzare la sottoscrizione ai PrivateChannel.
    // Senza questa chiamata i canali privati NON funzionano (Echo riceve 404).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Fidiamoci dei proxy (ngrok, Cloudflare, reverse proxy locali ecc.).
        // Senza questa riga Laravel non legge X-Forwarded-Proto e crede di
        // ricevere richieste HTTP anche quando il client le ha mandate via
        // HTTPS, generando URL e cookie incoerenti -> CSRF 419 al login.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR  |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
