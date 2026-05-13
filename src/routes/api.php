<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\IotController;

// Rotta pubblica per login da dispositivi esterni (Postman/Python)
Route::post('/login', [AuthController::class, 'login']);

// Tutte le rotte che richiedono il Token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    
    // Recupera stazioni per la mappa
    Route::get('/stations', [StationController::class, 'all']); 
    
    // Dettaglio stazione singola
    Route::get('/station/{id}', [StationController::class, 'show']); 

    // Avvio sessione
    Route::post('/scan-qr',[SessionController::class, 'AvvioSessione']);//utente legge qr con telecamera, avrà questo input: gs:{$idPunto}:{$firma}. quesre cose saranno da mandare come body json a questa rotta.

    // In futuro da modificare per gamification
    Route::get('/session/{id}',[SessionController::class, 'show']);

    Route::post('/session/{id}/stop',[SessionController::class, 'InterrompiSessione']);
    
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Rotte IoT (ESP32 / simulatore) — autenticate tramite X-Device-Token
Route::middleware('device.token')->group(function () {
    Route::post('/heartbeat_punto', [IotController::class, 'Heartbeat']);
    Route::post('/{id_punto}/termina_sessione', [IotController::class, 'TerminaSessione']);
});