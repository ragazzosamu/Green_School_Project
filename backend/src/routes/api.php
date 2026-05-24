<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\IotController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\GamificationController;
use App\Http\Controllers\Api\AdminApiController;

// Broadcasting auth per i client a TOKEN (React).
// L'endpoint default /broadcasting/auth registrato da withBroadcasting() in
// bootstrap/app.php usa middleware 'web' (sessione + CSRF) ed e' quello che
// usa Blade. React via Sanctum non ha sessione, percio' usa questo endpoint
// alternativo /api/broadcasting/auth con auth:sanctum.
// Nota: NIENTE 'prefix' => 'api' qui — withRouting() in bootstrap/app.php
// applica gia' automaticamente il prefisso /api a tutto routes/api.php, e
// aggiungerlo di nuovo creerebbe /api/api/broadcasting/auth (404 -> kWh
// real-time non funziona).
// Echo lato React deve puntare a `authEndpoint: '/api/broadcasting/auth'`.
Broadcast::routes(['middleware' => ['auth:sanctum']]);

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
    // (rendez-vous codice -> cavo, finestra 60s).
    // L'id_stazione e' un MAC address (AA:BB:CC:DD:EE:FF): il vincolo regex
    // accetta solo hex e separatori, cosi' la rotta non collide con altre.
    Route::post('/{id_stazione}/verifica-codice', [SessionController::class, 'AutenticazioneCodice'])
        ->where('id_stazione', '[0-9A-Fa-f:.\-]+');

    // Polling: ha l'utente loggato una sessione attiva in questo momento?
    Route::get('/me/sessione-attiva', [SessionController::class, 'SessioneAttivaUtente']);

    // Secondi residui del rendez-vous codice->cavo (Redis): usato dal banner
    // "in attesa del cavo" in React. Blade lo legge gia' nella rotta /profilo.
    Route::get('/me/attesa-cavo', [SessionController::class, 'AttesaCavo']);

    // Dettaglio sessione singola
    Route::get('/session/{id}',      [SessionController::class, 'show']);
    Route::post('/session/{id}/stop',[SessionController::class, 'InterrompiSessione']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'admin.api'])->prefix('admin')->group(function () {
    Route::get('/dashboard',              [AdminApiController::class, 'dashboard']);
    Route::get('/utenti',                 [AdminApiController::class, 'utenti']);
    Route::get('/utenti/{id}',            [AdminApiController::class, 'dettaglioUtente']);
    Route::put('/utenti/{id}',            [AdminApiController::class, 'modificaUtente']);
    Route::post('/utenti/{id}/toggle',    [AdminApiController::class, 'toggleUtente']);
    Route::post('/utenti/{id}/reset',     [AdminApiController::class, 'resetPassword']);
    Route::get('/sessioni',               [AdminApiController::class, 'sessioni']);
    Route::get('/stazioni',               [AdminApiController::class, 'stazioni']);
    Route::post('/stazioni/{id}/toggle',  [AdminApiController::class, 'toggleStazione']);
    Route::get('/stazioni/{id}/setup',    [AdminApiController::class, 'setupStazione']);
    Route::post('/stazioni/{id}/setup',   [AdminApiController::class, 'completaSetupStazione']);
    Route::get('/report/csv',             [AdminApiController::class, 'scaricaReportCsv']);
});

// Nota: heartbeat e fine sessione passano da MQTT (worker mqtt:leggi), non
// da HTTP. Non c'e' piu' un endpoint device-autenticato per la colonnina.
// Registrazione utente da React (pubblica)
Route::post('/register', [App\Http\Controllers\Api\RegisterController::class, 'register']);
