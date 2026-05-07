<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;

// Se l'utente va all'indirizzo base (/), lo mandiamo automaticamente al login
Route::get('/', function () {
    return redirect('/login');
});

// --- ROTTE PER L'AUTENTICAZIONE ---
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// --- ROTTA DELLA MAPPA ---
Route::get('/map', function () {
    return view('map', [
        'api_token' => session('api_token'),
        'center_lat' => config('map.center_lat'),
        'center_lng' => config('map.center_lng'),
        'zoom' => config('map.default_zoom'),
    ]);
})->middleware('auth');

// --- AGGIUNTA PUNTO 2.9: ROTTA SESSIONE ATTIVA ---
// Questa rotta serve per visualizzare la pagina della ricarica in corso
Route::get('/session/{uuid}', function ($uuid) {
    return view('session-active', [
        'session_uuid' => $uuid
    ]);
})->name('session.active')->middleware('auth');