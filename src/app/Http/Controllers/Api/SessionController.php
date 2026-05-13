<?php

namespace App\Http\Controllers\Api;

use App\Events\PuntoStatusChanged;
use App\Events\StazioneStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Sessioni_ricarica;
use App\Models\Stazioni;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\QrService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;


/**
 * Controller per la gestione delle sessioni di ricarica.
 * Espone endpoint per avviare, monitorare e interrompere una sessione.
 */
class SessionController extends Controller
{
    public function __construct(private readonly QrService $qrService)
    { }

    /**
     * Avvia una nuova sessione di ricarica per l'utente autenticato.
     *
     * - Valida i dati in ingresso (id_stazione e firma QR).
     * - Verifica che il nonce monouso associato all'utente e alla stazione
     * sia presente in cache (anti-replay), consumandolo immediatamente.
     * - Controlla la validità della firma del QR code tramite QrService.
     * - Invoca la stored procedure `sp_avvio_sessione` che internamente:
     * · acquisisce un lock FOR UPDATE sulla stazione
     * · verifica disponibilità e livello batteria
     * · inserisce la riga in sessioni_ricarica se tutto è ok
     * - Legge i parametri OUT della procedura per determinare esito e messaggio.
     * - Restituisce 201 con session_id in caso di successo,
     * oppure 422/409 con il messaggio descrittivo in caso di errore.
     *
     * @param  Request  $request  Richiesta HTTP con i campi `id_stazione` e `firma`.
     * @return JsonResponse
     */
    public function AvvioSessione(Request $request): JsonResponse
    {
        // Validazione dei campi obbligatori: id punto e firma HMAC/hash a 64 caratteri
        $data = $request->validate([
            'id_stazione' => ['required', 'string'],
            'id_punto'    => ['required', 'string'],
            'firma'       => ['required', 'string', 'size:64'],
        ]);

        $userId = $request->user()->id_utente;

        // Chiave univoca del nonce in cache, legata all'utente e alla stazione specifica
        $cacheKey = "scan_nonce:{$userId}:{$data['id_stazione']}";

        // Cache::pull rimuove e restituisce il valore: se null il nonce è scaduto o mai emesso
        // Chiave univoca del nonce in cache, legata all'utente e alla stazione specifica
        $cacheKey = "scan_nonce:{$userId}:{$data['id_stazione']}";

        $nonceSalvato = Cache::pull($cacheKey);

        Log::info('[NONCE CERCATO]', [
            'userId'     => $userId,
            'idStazione' => $data['id_stazione'],
            'cacheKey'   => $cacheKey,
        ]);

        if ($nonceSalvato === null) {
            return response()->json(['error' => 'Nonce_invalido'], 422);
        }

        // Verifica crittografica della firma allegata al QR code
        if (! $this->qrService->VerificaFirma($data['id_stazione'], $data['firma'])) {
            return response()->json(['error' => 'Qr_invalido'], 422);
        }

        // Invoca la stored procedure passando i parametri IN e destinando i risultati
        // a variabili di sessione MySQL (@), lette subito dopo con una SELECT

        DB::statement('CALL sp_avvio_sessione(?, ?, ?, ?, @id_sessione, @successo, @messaggio)', [
            $userId,
            $data['id_punto'],
            'QR_CODE',
            null, // id_badge: null quando l'avvio avviene tramite QR e non badge fisico
        ]);

        // Legge i parametri OUT: p_successo, p_id_sessione e p_messaggio
        $result = DB::selectOne('SELECT @id_sessione as id, @successo as successo, @messaggio as messaggio');

        // La procedura comunica qualsiasi errore di business tramite p_successo = 0
        // (stazione occupata, offline, batteria scarica, ecc.) — nessuna eccezione attesa
        if (! $result->successo) {
            return response()->json(['error' => $result->messaggio], 409);
        }

        // Notifico che lo stato di un punto è stato modificato
        PuntoStatusChanged :: dispatch($data['id_punto'],false);
        
        // Controllo se il cambiamento dello stato del punto ha inciso sulla disponibilità della stazione
        $statoStazione = Stazioni :: statoAggregatoPerPunto($data['id_punto']);

        // Tutti i punti della stazione sono stati occupati, la stazione diventa occupata
        if($statoStazione->liberi === 0)
        {
            StazioneStatusChanged :: dispatch($data['id_stazione'],false);
        }
        
        return response()->json([
            'session_id'          => $result->id,
            'session_uuid'        => $result->id, // Aggiunto per permettere al JS di fare il redirect
            'reservation_timeout' => 60, // secondi entro cui il cliente deve iniziare la ricarica
        ], 201);
    }



    /**
     * Restituisce lo stato corrente di una sessione di ricarica attiva.
     *
     * - Carica la sessione tramite il suo ID (404 automatico se non esiste).
     * - Calcola il tempo trascorso dall'inizio della sessione in minuti.
     * - Restituisce i kWh erogati e il tempo trascorso.
     * (Il costo parziale è momentaneamente commentato in attesa della logica tariffaria.)
     *
     * @param  string  $id_sessione  Identificativo della sessione da interrogare.
     * @return JsonResponse          200 con kwh_erogati e tempo_trascorso.
     */
    public function show(string $id_sessione): JsonResponse
    {
        // findOrFail lancia automaticamente un 404 se la sessione non esiste
        $sessione = Sessioni_ricarica::findOrFail($id_sessione);

        // Differenza in minuti tra adesso e l'orario di avvio della sessione
        $tempoTrascorso = now()->diffInMinutes($sessione->data_inizio);

        // TODO: recuperare la tariffa attiva e calcolare il costo parziale
        # $costoParziale = round($sessione->quantita_kwh * $tariffaAttiva->prezzo_per_kwh, 2);

        return response()->json([
            'kwh_erogati'     => $sessione->quantita_kwh,
            'tempo_trascorso' => $tempoTrascorso,
            # 'costo_parziale' => $costoParziale,
        ]);
    }

    /**
     * Interrompe anticipatamente una sessione di ricarica in corso.
     *
     * - Invoca la stored procedure `sp_interrompi_sessione` passando l'ID sessione.
     * - Legge i parametri OUT per determinare l'esito dell'operazione.
     * - In caso di errore restituisce 409 con il messaggio dalla procedura.
     * - Se la procedura va a buon fine, ricarica e restituisce i dati aggiornati
     * della sessione (kWh finali, durata, ecc.).
     *
     * @param  string  $id_sessione  Identificativo della sessione da interrompere.
     * @return JsonResponse          200 con i dati della sessione conclusa,
     * oppure 409 in caso di errore.
     */
    public function InterrompiSessione(string $id, Request $request): JsonResponse
    {
        $id_punto = $id;

        $data = $request->validate([
            'id_stazione' => ['required', 'string'],
             
        ]);
        // Invoca la procedura di chiusura sessione e legge l'esito tramite parametri OUT
        DB::statement('CALL sp_interrompi_sessione(?, @successo, @messaggio)', [
            $id_sessione,
        ]);

        $result = DB::selectOne('SELECT @successo as successo, @messaggio as messaggio');

        // La procedura comunica eventuali errori (sessione non trovata, già chiusa, ecc.)
        // tramite p_successo = 0, senza lanciare eccezioni
        if (! $result->successo) {
            return response()->json(['error' => $result->messaggio], 409);
        }

        // Ricarica la sessione dal DB per restituire i dati aggiornati al termine
        $sessione = Sessioni_ricarica::findOrFail($id_sessione);

        $statoStazione = Stazioni :: statoAggregatoPerPunto($data['id_punto']);
        
        // Si è liberato un punto, la stazione torna libera
        if($statoStazione->liberi === 1)
        {
            StazioneStatusChanged :: dispatch($data['id_stazione'],true);
        }

        return response()->json([
            'data' => $sessione,
        ]);
    }
}