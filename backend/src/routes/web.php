<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Models\Stazioni; // <--- Importante per caricare i dati nella rotta
use App\Http\Controllers\Api\StationController;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminController;

// Se l'utente va all'indirizzo base (/), lo mandiamo automaticamente al login
Route::get('/', function () {
    return redirect('/login');
});

// --- ROTTE PER L'AUTENTICAZIONE ---
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login']);
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register')->middleware('guest');
Route::post('/register', [WebAuthController::class, 'register'])->middleware('guest');
Route::get('/classifica', fn() => view('classifica'))->name('classifica')->middleware('auth');
Route::get('/scuola', fn() => view('scuola'))->name('scuola')->middleware('auth');

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
Route::get('/stazione/{id}', function (string $id, Request $request) {
    // 1. Chiamiamo direttamente il metodo 'show' del controller API
    $response = app(StationController::class)->show($id, $request);

    // 2. Verifichiamo se l'API ha restituito un errore (es. 404)
    if ($response->getStatusCode() === 404) {
        // Mostriamo la pagina 404 di default di Laravel
        abort(404, 'Stazione non trovata'); 
    }

    // 3. Estraiamo i dati dalla risposta JSON
    // $response->getData() converte il JSON in un oggetto PHP (stdClass)
    // che conterrà 'status' e 'data' (come definito nel tuo controller)
    $stazione = $response->getData()->data;
    
    // Trasformi l'array semplice in una Collection "intelligente"
    $stazione->puntiRicarica = collect($stazione->punti_ricarica);

    // 4. Restituiamo la vista passando i dati
    return view('station-detail', [
        'stazione' => $stazione
    ]);
})->name('station.show')->middleware('auth');

// --- ROTTA SESSIONE ATTIVA ---
Route::get('/session/{uuid}', function ($uuid) {
    $sessione = \App\Models\Sessioni_ricarica::findOrFail($uuid);

    // Per le sessioni ATTIVE leggo kWh da Redis; per quelle CHIUSE da DB.
    $kwhIniziali = $sessione->data_fine === null
        ? app(\App\Services\SessioneService::class)->kwhCorrenti($sessione->id_sessione)
        : (float) ($sessione->quantita_kwh ?? 0);

    return view('session-active', [
        'session_uuid' => $sessione->id_sessione,
        'id_punto'     => $sessione->id_punto,
        'kwh_iniziali' => $kwhIniziali,
        'api_token'    => session('api_token'),
        // Timestamp reali (in ms) della sessione: la durata mostrata deve
        // partire dall'inizio EFFETTIVO della ricarica, non da quando si apre
        // la pagina. data_inizio e' impostato da sp_avvio_sessione e persiste
        // sul DB, quindi il tempo e' corretto anche se la pagina viene aperta
        // a ricarica gia' avviata.
        'inizio_ms'    => $sessione->data_inizio
            ? \Illuminate\Support\Carbon::parse($sessione->data_inizio)->timestamp * 1000
            : null,
        'fine_ms'      => $sessione->data_fine
            ? \Illuminate\Support\Carbon::parse($sessione->data_fine)->timestamp * 1000
            : null,
    ]);
})->name('session.active')->middleware('auth');

Route::get('/profilo', function (Request $request) {
    // Stato iniziale renderizzato server-side: se l'utente ha gia' una
    // sessione attiva la mostriamo subito (senza dover aspettare un evento
    // WS), così la pagina e' utile anche se viene aperta direttamente.
    $userId = $request->user()->id_utente;

    $sessioneAttiva = \App\Models\Sessioni_ricarica::where('id_utente', $userId)
        ->whereNull('data_fine')
        ->orderByDesc('data_inizio')
        ->first();

    // kWh correnti: in Redis se la sessione e' attiva, 0 altrimenti
    $kwhAttuali = $sessioneAttiva
        ? app(\App\Services\SessioneService::class)->kwhCorrenti($sessioneAttiva->id_sessione)
        : 0.0;

    // ?attesa_stazione=<mac>&attesa=<id_punto> arrivano da /stazione/{id}
    // dopo /api/{id_stazione}/verifica-codice riuscito. id_punto e' locale alla stazione,
    // serve anche la stazione per ricostruire il canale WebSocket.
    //
    // Il banner "in attesa del cavo" viene mostrato SOLO se il rendez-vous in
    // Redis e' ancora valido: cosi' un reload della pagina con la vecchia
    // query string (?attesa=...) dopo la scadenza non fa ripartire il
    // countdown a 60s. I secondi residui sono autorevoli (calcolati da Redis).
    $attesaStazione = $request->query('attesa_stazione');
    $attesaPunto    = $request->query('attesa');
    $attesaSecondi  = null;

    if ($attesaStazione && $attesaPunto) {
        $attesaSecondi = app(\App\Services\SessioneService::class)
            ->secondiAttesaResidui($attesaStazione, $userId);

        if ($attesaSecondi === null) {
            // Rendez-vous scaduto o non piu' valido: niente banner di attesa.
            $attesaStazione = null;
            $attesaPunto    = null;
        }
    }

    return view('gamification-profile', [
        'api_token'       => session('api_token'),
        'attesa_stazione' => $attesaStazione,
        'attesa_punto'    => $attesaPunto,
        'attesa_secondi'  => $attesaSecondi,
        'sessione_attiva' => $sessioneAttiva,
        'kwh_attuali'     => $kwhAttuali,
    ]);
})->name('profilo')->middleware('auth');

// ── ROTTE ADMIN ──────────────────────────────────────────────────────────────
// Accessibili solo agli utenti con ruolo = 'admin'.
// Il middleware 'admin' controlla ruolo e fa abort(403) se non autorizzato.
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/',                          [AdminController::class, 'dashboard']);
    Route::get('/utenti',                    [AdminController::class, 'utenti']);
    Route::get('/utenti/{id}',               [AdminController::class, 'dettaglioUtente']);
    Route::post('/utenti/{id}',              [AdminController::class, 'modificaUtente']);
    Route::post('/utenti/{id}/toggle',           [AdminController::class, 'toggleUtente']);
    Route::post('/utenti/{id}/reset',            [AdminController::class, 'resetPassword']);
    Route::get('/sessioni',                  [AdminController::class, 'sessioni']);
    
    Route::get('/report/csv',                [AdminController::class, 'scaricaReportCsv'])->name('admin.report.csv');
    Route::get('/stazioni',                  [AdminController::class, 'stazioni']);
    Route::get('/stazioni/{id}/setup',           [AdminController::class, 'setupStazione']);
    Route::post('/stazioni/{id}/setup',          [AdminController::class, 'completaSetupStazione']);
    Route::post('/stazioni/{id}/toggle',         [AdminController::class, 'toggleStazione']);
});