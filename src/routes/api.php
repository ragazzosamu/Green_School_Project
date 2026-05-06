<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;

// Rotta pubblica per login da dispositivi esterni (Postman/Python)
Route::post('/login', [AuthController::class, 'login']);

// Tutte le rotte che richiedono il Token Sanctum
Route::middleware('auth:sanctum')->group(function () {
    
    // Recupera stazioni per la mappa
    Route::get('/stations', [StationController::class, 'all']); 
    
    // Dettaglio stazione singola
    Route::get('/station/{id}', [StationController::class, 'show']); 

    
    Route::post('/logout', [AuthController::class, 'logout']);
});