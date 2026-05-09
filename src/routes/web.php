<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Models\Stazioni; // <--- Importante per caricare i dati nella rotta
use Illuminate\Support\Facades\Cache; // <--- Importante per la gestione del nonce

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
    // Carichiamo la stazione con tutte le sue prese (puntiRicarica)
    $stazione = Stazioni::with('puntiRicarica')->findOrFail($id);
    
    // --- MODIFICA: GENERAZIONE NONCE PER LO SCANNER ---
    // Generiamo un codice casuale e lo salviamo in cache collegato all'utente e alla stazione
    $nonce = bin2hex(random_bytes(16));
    $userId = auth()->user()->id;
    $cacheKey = "scan_nonce:{$userId}:{$id}";
    
    // Salviamo il nonce in cache per 5 minuti
    Cache::put($cacheKey, $nonce, now()->addMinutes(5));
    // -------------------------------------------------

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