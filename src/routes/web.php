<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;

// Se l'utente va all'indirizzo base (/), lo mandiamo automaticamente al login
Route::get('/', function () {
    return redirect('/login');
});

// --- ROTTE PER L'AUTENTICAZIONE ---
// Mostra la pagina di login
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
// Gestisce l'invio dei dati di login
Route::post('/login', [WebAuthController::class, 'login']);
// Gestisce l'uscita dell'utente
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// --- ROTTA DELLA MAPPA ---
// Route::get('/map', ...) definisce l'indirizzo della pagina
Route::get('/map', function () {
    // Restituiamo la vista 'map' passando i dati necessari
    return view('map', [
        'api_token' => session('api_token'),      // Recuperiamo il token salvato in sessione al login
        'center_lat' => config('map.center_lat'), // Leggiamo la latitudine dal file config/map.php
        'center_lng' => config('map.center_lng'), // Leggiamo la longitudine dal file config/map.php
        'zoom' => config('map.default_zoom'),     // Leggiamo lo zoom dal file config/map.php
    ]);
})->middleware('auth'); // Il middleware 'auth' blocca l'accesso a chi non è loggato