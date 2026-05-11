<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class DeviceTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // leggo il token
        $token = $request->header('X-Device-Token');

        if(empty($token))
        {
            abort(401, 'Device token mancante');
        }

        $stazione = DB::table('stazioni')
        ->select('id_stazione', 'token')
        ->where('token', $token)
        ->first();
        
        if(!$stazione)
        {
            abort(401, 'Device token mancante');
        }

        $request->attributes->set('stazione', $stazione);

        return $next($request);
    }
}
