<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;

// Rotta per il login - PUBBLICA
Route::post('/login', [AuthController::class, 'login']);

// Rotte protette - Solo chi ha il Token può entrare qui
Route::middleware('auth:sanctum')->group(function () {
    
    // Test per vedere se il token funziona: restituisce i dati dell'utente loggato
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rotta per vedere tutte le stazioni, (problema con coordinate)
    Route::get('/stations',[StationController::class, 'all']); 

    // Rotta per vedere una stazione specifica
    Route::get('/station/{id}',[StationController::class, 'show']); 


    Route::post('/logout', [AuthController::class, 'logout']);

});






