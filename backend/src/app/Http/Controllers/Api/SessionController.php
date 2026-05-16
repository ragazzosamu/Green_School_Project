<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sessioni_ricarica;
use App\Services\MqttService;
use App\Services\QrService;
use App\Services\SessioneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Flusso d'avvio sessione (QR -> cavo, finestra 60s):
 *  1. Utente scansiona QR -> validiamo nonce e firma.
 *  2. Cache::add('qr_pending:{id_punto}') con TTL 60s. Se un altro utente
 *     ha gia' prenotato lo stesso punto, 409.
 *  3. Comunichiamo alla colonnina "autenticazione_completata" via MQTT.
 *  4. Risposta 202 Attesa_Cavo.
 *  5. Il frontend redirige a /profilo, che fa polling sulla sessione
 *     attiva dell'utente: quando il worker MQTT vede "cavo_collegato"
 *     crea la sessione su DB e il polling la trova.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly QrService $qrService,
        private readonly SessioneService $sessioni,
        private readonly MqttService $mqtt,
    ) {
    }

    public function AutenticazioneQr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_stazione' => ['required', 'string'],
            'id_punto'    => ['required', 'string'],
            'firma'       => ['required', 'string', 'size:64'],
        ]);

        $userId   = $request->user()->id_utente;
        $cacheKey = "scan_nonce:{$userId}:{$data['id_stazione']}";
        $nonce    = Cache::pull($cacheKey);

        Log::info('[NONCE CERCATO]', [
            'userId'     => $userId,
            'idStazione' => $data['id_stazione'],
            'cacheKey'   => $cacheKey,
        ]);

        if ($nonce === null) {
            return response()->json(['error' => 'Nonce_invalido'], 422);
        }

        if (! $this->qrService->VerificaFirma($data['id_stazione'], $data['firma'])) {
            return response()->json(['error' => 'Qr_invalido'], 422);
        }

        // Prenotazione atomica (Cache::add -> SETNX): il primo vince.
        $prenotato = $this->sessioni->memorizzaQrInAttesa(
            $data['id_punto'],
            $userId,
            $data['id_stazione'],
        );

        if (! $prenotato) {
            return response()->json([
                'error' => 'Punto già prenotato da un altro utente, riprova fra qualche istante.',
            ], 409);
        }

        // Comunico alla colonnina che l'utente e' autenticato.
        try {
            $this->mqtt->publish(
                "stazione/{$data['id_punto']}/comandi",
                json_encode([
                    'comando'  => 'autenticazione_completata',
                    'id_punto' => $data['id_punto'],
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('[AutenticazioneQr] publish autenticazione_completata fallito', [
                'err'      => $e->getMessage(),
                'id_punto' => $data['id_punto'],
            ]);
        }

        return response()->json([
            'status'              => 'Attesa_Cavo',
            'reservation_timeout' => SessioneService::TTL_QR_PENDING,
            'id_punto'            => $data['id_punto'],
        ], 202);
    }

    /**
     * Interrompe anticipatamente una sessione di ricarica in corso.
     * Chiude DB (sp_termina_sessione) + invia STOP via MQTT alla colonnina.
     */
    public function InterrompiSessione(string $id_sessione, Request $request): JsonResponse
    {
        $sessione = Sessioni_ricarica::findOrFail($id_sessione);

        if ($sessione->data_fine !== null) {
            return response()->json(['error' => 'Sessione già conclusa'], 409);
        }

        $userId = $request->user()->id_utente;
        if ($sessione->id_utente !== null && $sessione->id_utente !== $userId) {
            return response()->json(['error' => 'Non autorizzato a interrompere questa sessione'], 403);
        }

        // I kWh totali della sessione in corso vivono in Redis (il DB resta a 0
        // finche' sp_termina_sessione non scrive il valore finale).
        $kwh = $this->sessioni->kwhCorrenti($sessione->id_sessione);
        $esito = $this->sessioni->termina($sessione->id_sessione, $kwh);

        if (! $esito['ok']) {
            Log::error('[InterrompiSessione] sp_termina_sessione fallita', ['id_sessione' => $id_sessione]);
            return response()->json(['error' => 'Impossibile chiudere la sessione'], 500);
        }

        try {
            $this->mqtt->publish(
                "stazione/{$sessione->id_punto}/comandi",
                json_encode(['comando' => 'STOP', 'id_sessione' => $sessione->id_sessione]),
            );
        } catch (\Throwable $e) {
            Log::warning('[InterrompiSessione] publish STOP fallito', ['err' => $e->getMessage()]);
        }

        $this->sessioni->pulisciStatoRendezVous($sessione->id_punto);

        return response()->json([
            'status'      => 'sessione_chiusa',
            'id_sessione' => $sessione->id_sessione,
            'id_punto'    => $sessione->id_punto,
            'kwh_totali'  => $kwh,
            'costo'       => $esito['costo'] ?? 0,
        ], 200);
    }

    public function show(string $id_sessione): JsonResponse
    {
        $sessione       = Sessioni_ricarica::findOrFail($id_sessione);
        $tempoTrascorso = now()->diffInMinutes($sessione->data_inizio);

        // Per le sessioni ATTIVE i kWh stanno in Redis (DB e' 0 fino alla
        // chiusura). Per le sessioni CHIUSE Redis e' stato pulito e il valore
        // definitivo e' su DB.
        $kwh = $sessione->data_fine === null
            ? $this->sessioni->kwhCorrenti($sessione->id_sessione)
            : (float) ($sessione->quantita_kwh ?? 0);

        return response()->json([
            'kwh_erogati'     => $kwh,
            'tempo_trascorso' => $tempoTrascorso,
        ]);
    }

    /**
     * Endpoint usato dal polling della pagina /profilo per scoprire se
     * l'utente loggato ha una sessione di ricarica attiva.
     * Risposta sempre 200 con { attiva: bool, id_sessione?, id_punto?, kwh_erogati? }.
     */
    public function SessioneAttivaUtente(Request $request): JsonResponse
    {
        $userId = $request->user()->id_utente;

        $sessione = Sessioni_ricarica::where('id_utente', $userId)
            ->whereNull('data_fine')
            ->orderByDesc('data_inizio')
            ->first();

        if ($sessione === null) {
            return response()->json(['attiva' => false], 200);
        }

        return response()->json([
            'attiva'      => true,
            'id_sessione' => $sessione->id_sessione,
            'id_punto'    => $sessione->id_punto,
            // Letto da Redis: rispecchia lo stato vero in tempo reale, non lo 0 del DB
            'kwh_erogati' => $this->sessioni->kwhCorrenti($sessione->id_sessione),
            'data_inizio' => $sessione->data_inizio,
        ], 200);
    }
}
