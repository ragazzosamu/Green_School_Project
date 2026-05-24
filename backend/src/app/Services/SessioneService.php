<?php

namespace App\Services;

use App\Events\PuntoStatusChanged;
use App\Events\SessioneAvviata;
use App\Events\StazioneStatusChanged;
use App\Models\Punti_ricarica;
use App\Services\GamificationService;
use App\Models\Stazioni;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logica di apertura/chiusura sessione di ricarica condivisa fra:
 *  - SessionController (chiamata dopo POST /api/{id_stazione}/verifica-codice)
 *  - MqttWorker       (chiamata da evento "cavo_collegato")
 *
 * Tutta la persistenza passa dalle stored procedure sp_avvio_sessione /
 * sp_termina_sessione. Qui ci occupiamo solo di orchestrazione: chiamata SP,
 * dispatch eventi di broadcasting, comando MQTT START verso il punto.
 */
class SessioneService
{
    public function __construct(private readonly MqttService $mqtt, private readonly GamificationService $gamification)
    {
    }

    /**
     * Apre una sessione: chiama sp_avvio_sessione, fa broadcast, manda START via MQTT.
     *
     * @return array{ok:bool, id_sessione?:string, messaggio?:string}
     */
    public function avvia(string $idStazione, string $idPunto, string $idUtente, string $metodo = 'CODICE'): array
    {
        DB::statement('CALL sp_avvio_sessione(?, ?, ?, ?, ?, @id_sessione, @successo, @messaggio)', [
            $idUtente,
            $idStazione,
            $idPunto,
            $metodo,
            null,
        ]);

        $result = DB::selectOne('SELECT @id_sessione as id, @successo as successo, @messaggio as messaggio');

        if (! $result->successo) {
            return ['ok' => false, 'messaggio' => $result->messaggio];
        }

        SessioneAvviata::dispatch($result->id, $idPunto, $idUtente, $idStazione);
        PuntoStatusChanged::dispatch($idPunto, false, $idStazione);

        $statoStazione = Stazioni::statoAggregatoPerPunto($idStazione, $idPunto);
        if ($statoStazione && (int) $statoStazione->liberi === 0) {
            StazioneStatusChanged::dispatch($idStazione, false);
        }

        // Comando START verso il punto specifico della stazione
        $this->mqtt->publish(
            "stazione/{$idStazione}/{$idPunto}/comandi",
            json_encode(['comando' => 'START', 'id_sessione' => $result->id]),
        );

        return ['ok' => true, 'id_sessione' => $result->id];
    }

    /**
     * Chiude una sessione: chiama sp_termina_sessione, fa broadcast.
     * @return array{ok:bool, costo?:float}
     */
    public function termina(string $idSessione, float $kwhTotali): array
    {
        DB::statement('CALL sp_termina_sessione(?, ?, @successo, @costo)', [
            $idSessione,
            $kwhTotali,
        ]);

        $result = DB::selectOne('SELECT @successo as successo, @costo as costo');

        if (! $result || ! $result->successo) {
            Log::warning('[SessioneService] termina fallita', ['id_sessione' => $idSessione]);
            return ['ok' => false];
        }

        $row = DB::table('sessioni_ricarica')
            ->where('id_sessione', $idSessione)
            ->select('id_utente', 'id_stazione', 'id_punto')
            ->first();

        if ($row) {
            $this->gamification->aggiorna($idSessione, $row->id_utente, $kwhTotali);
            PuntoStatusChanged::dispatch($row->id_punto, true, $row->id_stazione);

            $statoStazione = Stazioni::statoAggregatoPerPunto($row->id_stazione, $row->id_punto);
            if ($statoStazione && (int) $statoStazione->liberi > 0) {
                StazioneStatusChanged::dispatch($row->id_stazione, true);
            }
        }

        // Pulisco la chiave Redis dei kWh: la sessione e' chiusa, il totale
        // definitivo e' adesso su DB (quantita_kwh impostato da sp_termina_sessione).
        $this->pulisciKwh($idSessione);

        return ['ok' => true, 'costo' => (float) $result->costo];
    }

    // ---- Helpers per il rendez-vous codice -> cavo (60s) -----------------------

    public const TTL_CODICE_PENDING = 60;

    // Il codice e' UNICO per stazione: il pending si tiene a livello stazione,
    // non per punto. Quando arriva il cavo_collegato su (stazione, qualsiasi
    // punto) il rendez-vous matcha e la sessione si avvia su quel punto.
    public static function codicePendingKey(string $idStazione): string
    {
        return "codice_pending:{$idStazione}";
    }

    public function memorizzaCodiceInAttesa(string $idStazione, string $idUtente): bool
    {
        return Cache::add(
            self::codicePendingKey($idStazione),
            [
                'id_utente'   => $idUtente,
                'id_stazione' => $idStazione,
                // Timestamp di scadenza: serve a calcolare i secondi residui
                // in modo autorevole lato server, indipendente dal client.
                'scade_a'     => now()->addSeconds(self::TTL_CODICE_PENDING)->timestamp,
            ],
            self::TTL_CODICE_PENDING,
        );
    }

    /**
     * Secondi rimanenti del rendez-vous codice -> cavo per una stazione.
     *
     * Restituisce un intero > 0 se l'attesa e' ancora valida (e, se passato
     * $idUtente, appartiene a quell'utente); null se la chiave Redis e'
     * assente/scaduta. Usato da /profilo per non far ripartire il countdown
     * a 60s dopo un reload con la query string ?attesa=... ormai stantia.
     */
    public function secondiAttesaResidui(string $idStazione, ?string $idUtente = null): ?int
    {
        $pending = Cache::get(self::codicePendingKey($idStazione));
        if (! $pending) {
            return null;
        }
        if ($idUtente !== null && ($pending['id_utente'] ?? null) !== $idUtente) {
            return null;
        }
        $scadeA = $pending['scade_a'] ?? null;
        if (! $scadeA) {
            return null;
        }
        $residui = (int) $scadeA - now()->timestamp;
        return $residui > 0 ? $residui : null;
    }

    public function consumaCodiceInAttesa(string $idStazione): ?array
    {
        return Cache::pull(self::codicePendingKey($idStazione));
    }

    public function pulisciStatoRendezVous(string $idStazione): void
    {
        Cache::forget(self::codicePendingKey($idStazione));
    }

    // ---- kWh corrente della sessione (cache Redis) -------------------------
    //
    // Durante la ricarica il DB resta a quantita_kwh=0 fino alla chiusura
    // (sp_termina_sessione). Per non perdere lo stato quando l'utente naviga
    // tra le pagine, accumuliamo i delta in Redis. Il valore corrente e' la
    // somma di tutti i delta arrivati dal worker MQTT.

    public const TTL_KWH_SESSIONE = 86400; // 24h: una sessione realistica e' molto piu' breve

    public static function kwhSessioneKey(string $idSessione): string
    {
        return "sessione_kwh:{$idSessione}";
    }

    /**
     * Somma un delta al totale corrente della sessione e restituisce il nuovo
     * totale. Chiamato dal worker a ogni telemetria.
     */
    public function aggiungiKwh(string $idSessione, float $delta): float
    {
        $key    = self::kwhSessioneKey($idSessione);
        $totale = ((float) Cache::get($key, 0.0)) + $delta;
        Cache::put($key, $totale, self::TTL_KWH_SESSIONE);
        return $totale;
    }

    /**
     * Legge il totale corrente della sessione (0 se non c'e' ancora telemetria).
     * Chiamato dal controller / dalle view per inizializzare il display.
     */
    public function kwhCorrenti(string $idSessione): float
    {
        return (float) Cache::get(self::kwhSessioneKey($idSessione), 0.0);
    }

    public function pulisciKwh(string $idSessione): void
    {
        Cache::forget(self::kwhSessioneKey($idSessione));
    }

    /**
     * Recupera l'id_sessione attiva di un utente (data_fine IS NULL).
     * Usato dal polling lato /profilo.
     */
    public function sessioneAttivaPerUtente(string $idUtente): ?string
    {
        return DB::table('sessioni_ricarica')
            ->where('id_utente', $idUtente)
            ->whereNull('data_fine')
            ->orderByDesc('data_inizio')
            ->value('id_sessione');
    }

    public function sessioneAttivaPerPunto(string $idStazione, string $idPunto): ?string
    {
        return DB::table('sessioni_ricarica')
            ->where('id_stazione', $idStazione)
            ->where('id_punto', $idPunto)
            ->whereNull('data_fine')
            ->orderByDesc('data_inizio')
            ->value('id_sessione');
    }
}