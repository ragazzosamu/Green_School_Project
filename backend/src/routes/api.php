<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\IotController;
use App\Http\Controllers\Api\SchoolController;

// Rotta pubblica per login da dispositivi esterni (Postman/Python)
Route::post('/login', [AuthController::class, 'login']);

// Tutte le rotte che richiedono il Token Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Recupera stazioni per la mappa
    Route::get('/stations', [StationController::class, 'all']);

    // Dettaglio stazione singola
    Route::get('/station/{id}', [StationController::class, 'show']);

    Route::get('/school/profile',     [SchoolController::class, 'profile']);
    Route::get('/school/consumption', [SchoolController::class, 'consumption']);

    // Avvio sessione (gestisce rendez-vous QR -> cavo, finestra 60s)
    Route::post('/scan-qr', [SessionController::class, 'AutenticazioneQr']);

    // Polling: ha l'utente loggato una sessione attiva in questo momento?
    Route::get('/me/sessione-attiva', [SessionController::class, 'SessioneAttivaUtente']);

    // Dettaglio sessione singola
    Route::get('/session/{id}',      [SessionController::class, 'show']);
    Route::post('/session/{id}/stop',[SessionController::class, 'InterrompiSessione']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

// Rotte IoT (ESP32 / simulatore) — autenticate tramite X-Device-Token
Route::middleware('device.token')->group(function () {
    Route::post('/heartbeat_punto', [IotController::class, 'Heartbeat']);
    Route::post('/{id_punto}/termina_sessione', [IotController::class, 'TerminaSessione']);
});
