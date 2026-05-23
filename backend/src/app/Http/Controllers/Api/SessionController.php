<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sessioni_ricarica;
use App\Services\CodiceMonousoService;
use App\Services\MqttService;
use App\Services\SessioneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Flusso d'avvio sessione (codice monouso -> cavo, finestra prenotazione 60s):
 *  1. La colonnina genera ogni 30s UN codice a 6 cifre per tutta la stazione
 *     e lo pubblica su stazione/{id_stazione}/codice. MqttWorker lo salva in
 *     Redis (chiave codice:{id_stazione}, TTL 35s).
 *  2. L'utente digita il codice nell'app -> POST /api/verifica-codice.
 *  3. Validiamo con CodiceMonousoService::verifica iterando sulle stazioni
 *     'attiva': il match ritorna SOLO id_stazione (il punto specifico verra'
 *     scelto in base a dove l'utente attacca il cavo). Il codice resta in
 *     Redis per i 35s di TTL anche dopo il match; l'unicita' della
 *     prenotazione e' garantita dal SETNX su codice_pending:{id_stazione}.
 *  4. Notifichiamo la stazione via MQTT (stazione/{mac}/comandi) che un
 *     utente e' stato autorizzato.
 *  5. Risposta 202: il frontend va in /profilo e fa polling sulla sessione
 *     attiva dell'utente. Quando il worker MQTT riceve "cavo_collegato" su
 *     un punto qualsiasi della stazione, consuma il pending e avvia la
 *     sessione su quel punto specifico.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly CodiceMonousoService $codici,
        private readonly SessioneService $sessioni,
        private readonly MqttService $mqtt,
    ) {
    }

    public function AutenticazioneCodice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codice' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $userId = $request->user()->id_utente;

        // Il codice e' unico per stazione: la verifica ritorna solo
        // l'id_stazione. L'utente collega il cavo a uno qualsiasi dei punti
        // della stazione e il worker MQTT, al cavo_collegato, sceglie quel
        // punto specifico per la sessione.
        $idStazione = $this->codici->verifica($data['codice']);
        if ($idStazione === null) {
            return response()->json(['error' => 'Codice non valido o scaduto'], 422);
        }

        $prenotato = $this->sessioni->memorizzaCodiceInAttesa($idStazione, $userId);
        if (! $prenotato) {
            return response()->json([
                'error' => 'Stazione gia\' prenotata da un altro utente, riprova fra qualche istante.',
            ], 409);
        }

        // autenticazione_completata viene mandato a livello stazione: serve
        // all'Arduino per sapere che un utente e' stato autorizzato (anche
        // se non sappiamo ancora su quale punto attaccera' il cavo).
        // I comandi START/STOP veri e propri sono per-punto e partono dal
        // worker MQTT al cavo_collegato.
        try {
            $this->mqtt->publish(
                "stazione/{$idStazione}/comandi",
                json_encode([
                    'comando'     => 'autenticazione_completata',
                    'id_stazione' => $idStazione,
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('[AutenticazioneCodice] publish fallito', [
                'err' => $e->getMessage(),
                'id_stazione' => $idStazione,
            ]);
        }

        return response()->json([
            'status'              => 'Attesa_Cavo',
            'reservation_timeout' => SessioneService::TTL_CODICE_PENDING,
            'id_stazione'         => $idStazione,
        ], 202);
    }

    public function InterrompiSessione(string $id_sessione, Request $request): JsonResponse
    {
        $sessione = Sessioni_ricarica::findOrFail($id_sessione);

        if ($sessione->data_fine !== null) {
            return response()->json(['error' => 'Sessione gia\' conclusa'], 409);
        }

        $userId = $request->user()->id_utente;
        if ($sessione->id_utente !== null && $sessione->id_utente !== $userId) {
            return response()->json(['error' => 'Non autorizzato a interrompere questa sessione'], 403);
        }

        $kwh = $this->sessioni->kwhCorrenti($sessione->id_sessione);
        $esito = $this->sessioni->termina($sessione->id_sessione, $kwh);

        if (! $esito['ok']) {
            Log::error('[InterrompiSessione] sp_termina_sessione fallita', ['id_sessione' => $id_sessione]);
            return response()->json(['error' => 'Impossibile chiudere la sessione'], 500);
        }

        try {
            $this->mqtt->publish(
                "stazione/{$sessione->id_stazione}/{$sessione->id_punto}/comandi",
                json_encode(['comando' => 'STOP', 'id_sessione' => $sessione->id_sessione]),
            );
        } catch (\Throwable $e) {
            Log::warning('[InterrompiSessione] publish STOP fallito', ['err' => $e->getMessage()]);
        }

        $this->sessioni->pulisciStatoRendezVous($sessione->id_stazione);

        return response()->json([
            'status'      => 'sessione_chiusa',
            'id_sessione' => $sessione->id_sessione,
            'id_stazione' => $sessione->id_stazione,
            'id_punto'    => $sessione->id_punto,
            'kwh_totali'  => $kwh,
            'costo'       => $esito['costo'] ?? 0,
        ], 200);
    }

    public function show(string $id_sessione): JsonResponse
    {
        $sessione       = Sessioni_ricarica::findOrFail($id_sessione);
        $tempoTrascorso = now()->diffInMinutes($sessione->data_inizio);

        $kwh = $sessione->data_fine === null
            ? $this->sessioni->kwhCorrenti($sessione->id_sessione)
            : (float) ($sessione->quantita_kwh ?? 0);

        return response()->json([
            'kwh_erogati'     => $kwh,
            'tempo_trascorso' => $tempoTrascorso,
        ]);
    }

    public function SessioneAttivaUtente(Request $request): JsonResponse
    {
        $userId = $request->user()->id_utente;

        // Eager load della stazione: serve a React per mostrare nome
        // e indirizzo al posto del MAC nudo nell'header sessione.
        $sessione = Sessioni_ricarica::with('stazione:id_stazione,nome,indirizzo')
            ->where('id_utente', $userId)
            ->whereNull('data_fine')
            ->orderByDesc('data_inizio')
            ->first();

        if ($sessione === null) {
            return response()->json(['attiva' => false], 200);
        }

        return response()->json([
            'attiva'       => true,
            'id_sessione'  => $sessione->id_sessione,
            'id_stazione'  => $sessione->id_stazione,
            'id_punto'     => $sessione->id_punto,
            'kwh_erogati'  => $this->sessioni->kwhCorrenti($sessione->id_sessione),
            // Grazie al cast 'datetime' sul modello, data_inizio esce come
            // ISO 8601 UTC ("...Z") e JavaScript lo interpreta correttamente
            // anche con timezone locale diversa da UTC.
            'data_inizio'  => $sessione->data_inizio,
            'metodo_avvio' => $sessione->metodo_avvio,
            'stazione'     => $sessione->stazione ? [
                'id_stazione' => $sessione->stazione->id_stazione,
                'nome'        => $sessione->stazione->nome,
                'indirizzo'   => $sessione->stazione->indirizzo,
            ] : null,
        ], 200);
    }

    /**
     * GET /api/me/attesa-cavo?id_stazione=...
     *
     * Espone i secondi residui del rendez-vous codice->cavo (Redis,
     * vedi SessioneService::secondiAttesaResidui). Serve a React per
     * mostrare il banner "in attesa del cavo" con countdown — l'equivalente
     * di quello che il Blade /profilo calcola server-side al render.
     *
     * Risposta:
     *   { attesa: true,  id_stazione, secondi_residui }   se rendez-vous valido
     *   { attesa: false }                                  altrimenti
     */
    public function AttesaCavo(Request $request): JsonResponse
    {
        $idStazione = $request->query('id_stazione');
        if (! $idStazione) {
            return response()->json(['attesa' => false], 200);
        }

        $userId   = $request->user()->id_utente;
        $secondi  = $this->sessioni->secondiAttesaResidui($idStazione, $userId);

        if ($secondi === null) {
            return response()->json(['attesa' => false], 200);
        }

        return response()->json([
            'attesa'           => true,
            'id_stazione'      => $idStazione,
            'secondi_residui'  => $secondi,
        ], 200);
    }
}
