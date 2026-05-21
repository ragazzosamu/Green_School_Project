<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\IotController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\GamificationController;

// Healthcheck pubblico — usato dall'healthcheck Docker del container `app`.
// Risponde 200 appena Laravel e' in piedi: non tocca il DB di proposito,
// cosi' verifica solo che Apache/PHP/Laravel abbiano fatto boot.
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Rotta pubblica per login da dispositivi esterni (Postman/Python)
Route::post('/login', [AuthController::class, 'login']);

// Registrazione colonnina IoT (pubblica, protetta da password globale + MAC)
Route::post('/iot/registra', [IotController::class, 'Registra']);

// Tutte le rotte che richiedono il Token Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Recupera stazioni per la mappa
    Route::get('/stations', [StationController::class, 'all']);

    // Dettaglio stazione singola
    Route::get('/station/{id}', [StationController::class, 'show']);

    Route::get('/school/profile',     [SchoolController::class, 'profile']);
    Route::get('/school/consumption', [SchoolController::class, 'consumption']);

    Route::get('/gamification/profile',     [GamificationController::class, 'profile']);
    Route::get('/gamification/badges',      [GamificationController::class, 'badges']);
    Route::get('/gamification/leaderboard', [GamificationController::class, 'leaderboard']);
    Route::get('/gamification/sessioni',    [GamificationController::class, 'sessioni']);
    Route::get('/gamification/sfide',       [GamificationController::class, 'sfide']);

    // Avvio sessione tramite codice monouso a 6 cifre generato dalla colonnina
    // (rendez-vous codice -> cavo, finestra 60s)
    Route::post('/verifica-codice', [SessionController::class, 'AutenticazioneCodice']);

    // Polling: ha l'utente loggato una sessione attiva in questo momento?
    Route::get('/me/sessione-attiva', [SessionController::class, 'SessioneAttivaUtente']);

    // Dettaglio sessione singola
    Route::get('/session/{id}',      [SessionController::class, 'show']);
    Route::post('/session/{id}/stop',[SessionController::class, 'InterrompiSessione']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

// Nota: heartbeat e fine sessione passano da MQTT (worker mqtt:leggi), non
// da HTTP. Non c'e' piu' un endpoint device-autenticato per la colonnina.
