<?php

namespace App\Services;

use App\Events\PuntoStatusChanged;
use App\Events\SessioneAvviata;
use App\Events\StazioneStatusChanged;
use App\Models\Punti_ricarica;
use App\Models\Stazioni;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Logica di apertura/chiusura sessione di ricarica condivisa fra:
 *  - SessionController (chiamata da QR scan)
 *  - MqttWorker       (chiamata da evento "cavo_collegato")
 *
 * Tutta la persistenza passa dalle stored procedure sp_avvio_sessione /
 * sp_termina_sessione. Qui ci occupiamo solo di orchestrazione: chiamata SP,
 * dispatch eventi di broadcasting, comando MQTT START verso il punto.
 */
class SessioneService
{
    public function __construct(private readonly MqttService $mqtt)
    {
    }

    /**
     * Apre una sessione: chiama sp_avvio_sessione, fa broadcast, manda START via MQTT.
     *
     * @return array{ok:bool, id_sessione?:string, messaggio?:string}
     */
    public function avvia(string $idPunto, string $idUtente, string $idStazione, string $metodo = 'QR_CODE'): array
    {
        DB::statement('CALL sp_avvio_sessione(?, ?, ?, ?, @id_sessione, @successo, @messaggio)', [
            $idUtente,
            $idPunto,
            $metodo,
            null,
        ]);

        $result = DB::selectOne('SELECT @id_sessione as id, @successo as successo, @messaggio as messaggio');

        if (! $result->successo) {
            return ['ok' => false, 'messaggio' => $result->messaggio];
        }

        // Notifica WS (best-effort, il flusso non dipende da questo)
        SessioneAvviata::dispatch($result->id, $idPunto, $idUtente);

        // Broadcast stato punto (ora occupato)
        PuntoStatusChanged::dispatch($idPunto, false, $idStazione);

        // Eventuale broadcast stazione se è diventata completamente occupata
        $statoStazione = Stazioni::statoAggregatoPerPunto($idPunto);
        if ($statoStazione && (int) $statoStazione->liberi === 0) {
            StazioneStatusChanged::dispatch($idStazione, false);
        }

        // Comando START verso la colonnina
        $this->mqtt->publish(
            "stazione/{$idPunto}/comandi",
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

        $row = DB::table('sessioni_ricarica as s')
            ->join('punti_ricarica as p', 'p.id_punto', '=', 's.id_punto')
            ->where('s.id_sessione', $idSessione)
            ->selectRaw('s.id_punto, p.id_stazione')
            ->first();

        if ($row) {
            PuntoStatusChanged::dispatch($row->id_punto, true, $row->id_stazione);

            $statoStazione = Stazioni::statoAggregatoPerPunto($row->id_punto);
            if ($statoStazione && (int) $statoStazione->liberi > 0) {
                StazioneStatusChanged::dispatch($row->id_stazione, true);
            }
        }

        return ['ok' => true, 'costo' => (float) $result->costo];
    }

    // ---- Helpers per il rendez-vous QR -> cavo (60s) -----------------------

    public const TTL_QR_PENDING = 60;

    public static function qrPendingKey(string $idPunto): string
    {
        return "qr_pending:{$idPunto}";
    }

    /**
     * Prenota il punto per questo utente per i prossimi 60s in attesa del
     * cavo_collegato. Ritorna true SOLO se nessun altro aveva gia' prenotato.
     */
    public function memorizzaQrInAttesa(string $idPunto, string $idUtente, string $idStazione): bool
    {
        return Cache::add(
            self::qrPendingKey($idPunto),
            ['id_utente' => $idUtente, 'id_stazione' => $idStazione],
            self::TTL_QR_PENDING,
        );
    }

    public function consumaQrInAttesa(string $idPunto): ?array
    {
        return Cache::pull(self::qrPendingKey($idPunto));
    }

    public function pulisciStatoRendezVous(string $idPunto): void
    {
        Cache::forget(self::qrPendingKey($idPunto));
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

    public function sessioneAttivaPerPunto(string $idPunto): ?string
    {
        return DB::table('sessioni_ricarica')
            ->where('id_punto', $idPunto)
            ->whereNull('data_fine')
            ->orderByDesc('data_inizio')
            ->value('id_sessione');
    }
}
