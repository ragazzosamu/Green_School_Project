<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Models\Stazioni; // <--- Importante per caricare i dati nella rotta

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
})->name('map')->middleware('auth'); // Aggiunto ->name('map') per comodità nei link

// --- NUOVA ROTTA: DETTAGLIO COLONNINA (PUNTI DI RICARICA) ---
// Questa è la rotta che serve per aprire la pagina delle prese quando clicchi sul pallino
Route::get('/stazione/{id}', function ($id) {
    // Carichiamo la stazione con tutte le sue prese (puntiRicarica) per il rendering.
    // Il nonce per l'anti-replay non viene piu' generato qui: lo crea l'API
    // GET /api/station/{id}, che il frontend chiama prima di aprire lo scanner.
    $stazione = Stazioni::with('puntiRicarica')->findOrFail($id);

    return view('station-detail', [
        'stazione' => $stazione
    ]);
})->name('station.show')->middleware('auth');

// --- ROTTA SESSIONE ATTIVA ---
Route::get('/session/{uuid}', function ($uuid) {
    return view('session-active', [
        'session_uuid' => $uuid
    ]);
})->name('session.active')->middleware('auth');