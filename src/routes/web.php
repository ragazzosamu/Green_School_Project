<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;

// Rotta Home (semplice benvenuto)
Route::get('/', function () {
    return view('layouts.app'); // Per ora carichiamo il layout vuoto
});

// Rotte Autenticazione
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Rotta Mappa (protetta da middleware auth)
// Creeremo la vista 'map' nel prossimo step del frontend
Route::get('/map', function () {
    return view('map'); 
})->middleware('auth');