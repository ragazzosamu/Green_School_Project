<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sessioni_ricarica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use App\Services\QrService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class SessionController extends Controller
{

    public function __construct(private readonly QrService $qrService)
    { }

    public function AvvioSessione(Request $request) : JsonResponse
    {
        $data = $request->validate
        ([
            'id_stazione' => ['required', 'string'],
            'firma'       => ['required', 'string', 'size:64'],
        ]);

        $userId = $request->user()->id;
        $cacheKey = "scan_nonce:{$userId}:{$data['id_stazione']}";

        $nonceSalvato = Cache::pull($cacheKey);
        if ($nonceSalvato === null ) {
        return response()->json(['error' => 'Nonce_invalido'], 422);
        }

        if(! $this->qrService->VerificaFirma($data['id_stazione'],$data['firma']))
        {
            return response()->json(['error' => 'Qr_invalido'], 422);
        }

        try {
            DB::statement('CALL sp_avvia_sessione(?, ?, ?)', [
                $data['station_id'],
                $userId,
                'QR_CODE',
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'STATION_BUSY')) {
                return response()->json(['error' => 'Stazione_occupata'], 409);
            }
            throw $e;
        }

        $sessionId = DB::selectOne(
        'SELECT id FROM sessioni WHERE id_utente = ? ORDER BY id DESC LIMIT 1',
        [$userId]
    )->id;

    // 4) Broadcast
    #Da Sistemare
    #broadcast(new StazioneStatusChanged($data['station_id'], 'occupata'));

    return response()->json([
        'session_id'          => $sessionId,
        'reservation_timeout' => 60,
    ], 201);

    }

    public function show(string $id_sessione) :JsonResponse
    {
        $sessione = Sessioni_ricarica::findOrFail($id_sessione);
        $tempoTrascorso = now()->diffInMinutes($sessione->ora_inizio);
        #$costoParziale  = round($sessione->kwh_erogati * $tariffaAttiva->prezzo_per_kwh, 2);

        return response()->json([
            'kwh_erogati'     => $sessione->kwh_erogati,
            'tempo_trascorso' => $tempoTrascorso,
            #'costo_parziale'  => $costoParziale,
        ]);

    }

    public function InterrompiSessione(string $id_sessione) : JsonResponse
    {
        try {
            DB::statement('CALL sp_avvia_sessione(?)', [
                $id_sessione
            ]);
        } catch (QueryException $e) {
            
            return response()->json(['error' => 'Impossibile annullare'], 500);      
        }

        $sessione = Sessioni_ricarica::findOrFail($id_sessione);
        return response()->json([
            'data'     => $sessione
        ]);
    }
}