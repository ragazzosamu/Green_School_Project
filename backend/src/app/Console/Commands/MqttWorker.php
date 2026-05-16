<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MqttService;
use App\Services\SessioneService;
use App\Models\Punti_ricarica;
use App\Events\TelemetriaRicevuta;

#[Signature('mqtt:leggi')]
#[Description('Funziona in background e ascolta se ci sono aggiornamenti sui topic mqtt')]
class MqttWorker extends Command
{
    public function __construct(
        private readonly MqttService $mqttService,
        private readonly SessioneService $sessioni,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // UNA sola subscribe con wildcard a due livelli, poi smistiamo
            // in base al topic. Evita problemi di multi-subscribe della libreria.
            $this->mqttService->subscribe('stazione/+/+', function ($topic, $message) {
                $parts    = explode('/', $topic);
                $id_punto = $parts[1] ?? null;
                $canale   = $parts[2] ?? null;

                if (! $id_punto || ! $canale) return;

                $data = json_decode($message, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Errore: Ricevuto messaggio non JSON su $topic");
                    return;
                }

                match ($canale) {
                    'telemetria' => $this->gestisciTelemetria($id_punto, $data),
                    'eventi'     => $this->gestisciEvento($id_punto, $data, $topic),
                    'heartbeat'  => $this->gestisciHeartbeat($id_punto, $data),
                    'comandi'    => null, // li pubblichiamo noi, non li consumiamo
                    default      => $this->warn("Canale sconosciuto '$canale' su $topic"),
                };
            });

            $this->mqttService->loop();
        } catch (\Throwable $e) {
            Log::error('[MQTT Worker] ' . $e->getMessage(), ['exception' => $e]);
            $this->error('Worker MQTT terminato per errore: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Riceve telemetria grezza dal simulatore (V, I, intervallo) e:
     *  1. calcola il delta kWh: (V * I / 1000) * (intervallo / 3600)
     *  2. accumula il totale corrente in Redis (Cache::put su chiave
     *     sessione_kwh:{id}). Cosi' se l'utente fa refresh / cambia pagina
     *     ritrova il valore esatto. Il DB resta a 0 finche' la sessione non
     *     viene chiusa (sp_termina_sessione scrive il totale finale).
     *  3. dispatcha TelemetriaRicevuta per l'aggiornamento live del browser.
     */
    private function gestisciTelemetria(string $idPunto, array $data): void
    {
        $idSessione = $data['id_sessione']    ?? null;
        $voltaggio  = (float) ($data['voltaggio']      ?? 0);
        $corrente   = (float) ($data['corrente']       ?? 0);
        $intervallo = (float) ($data['intervallo_sec'] ?? 0);

        if (! $idSessione || $intervallo <= 0) {
            $this->comment("Telemetria scartata: dati insufficienti ($idSessione, V=$voltaggio, I=$corrente, Δt=$intervallo)");
            return;
        }

        // Formula classica: kWh = potenza(kW) * tempo(h)
        //   potenza_kw = V * I / 1000
        //   tempo_h    = intervallo / 3600
        $deltaKwh = ($voltaggio * $corrente / 1000.0) * ($intervallo / 3600.0);

        // Lookup utente per indirizzare l'evento sul canale privato corretto
        $idUtente = DB::table('sessioni_ricarica')
            ->where('id_sessione', $idSessione)
            ->value('id_utente');

        if (! $idUtente) {
            $this->comment("Telemetria scartata: nessun id_utente per sessione $idSessione");
            return;
        }

        // Accumulo su Redis: read-modify-write. Il worker e' l'unico writer
        // di questa chiave, quindi non serve un lock.
        $totaleAggiornato = $this->sessioni->aggiungiKwh($idSessione, $deltaKwh);

        $this->info(sprintf(
            '-> Telemetria %s: V=%.1fV I=%.2fA Δt=%.0fs Δkwh=%.4f totale=%.4f',
            $idPunto, $voltaggio, $corrente, $intervallo, $deltaKwh, $totaleAggiornato
        ));

        TelemetriaRicevuta::dispatch($idPunto, $deltaKwh, $idSessione, $idUtente);
    }

    private function gestisciHeartbeat(string $idPunto, array $data): void
    {
        $this->comment("Heartbeat punto $idPunto");

        $ts = isset($data['ts']) && is_numeric($data['ts'])
            ? Carbon::createFromTimestamp((int) $data['ts'])
            : now();

        Punti_ricarica::where('id_punto', $idPunto)
            ->update(['data_ultimo_heartbeat' => $ts]);
    }

    private function gestisciEvento(string $idPunto, array $data, string $topic): void
    {
        $this->info("Evento da punto $idPunto: " . json_encode($data));

        $evento = $data['evento'] ?? null;

        match ($evento) {
            'cavo_collegato'  => $this->gestisciCavoCollegato($idPunto),
            'cavo_scollegato' => $this->gestisciFineSessione($idPunto, $data, 'cavo_scollegato'),
            'batteria_piena'  => $this->gestisciFineSessione($idPunto, $data, 'batteria_piena'),
            default           => $this->warn("Evento sconosciuto '$evento' su $topic"),
        };
    }

    /**
     * Flusso unico: se trovo un qr_pending (utente ha scansionato QR negli
     * ultimi 60s) avvio la sessione, altrimenti ignoro (il cavo va collegato
     * DOPO la scansione).
     */
    private function gestisciCavoCollegato(?string $idPunto): void
    {
        if (! $idPunto) return;

        $qrPending = $this->sessioni->consumaQrInAttesa($idPunto);

        if ($qrPending === null) {
            $this->comment("Cavo collegato su $idPunto ma nessun QR in attesa: ignorato.");
            return;
        }

        $this->info("-> QR gia' scansionato per $idPunto, avvio sessione.");
        $esito = $this->sessioni->avvia(
            $idPunto,
            $qrPending['id_utente'],
            $qrPending['id_stazione'],
        );

        if (! $esito['ok']) {
            Log::warning('[MQTT Worker] avvio sessione fallito', [
                'id_punto' => $idPunto,
                'msg'      => $esito['messaggio'] ?? '',
            ]);
            $this->error("Avvio sessione fallito: " . ($esito['messaggio'] ?? '?'));
        }
    }

    private function gestisciFineSessione(?string $idPunto, array $data, string $motivo): void
    {
        if (! $idPunto) return;

        $this->sessioni->pulisciStatoRendezVous($idPunto);

        $idSessione = $data['id_sessione'] ?? $this->sessioni->sessioneAttivaPerPunto($idPunto);
        if (! $idSessione) {
            $this->comment("Nessuna sessione attiva su $idPunto al momento di '$motivo'.");
            return;
        }

        $kwh = isset($data['kwh_totali']) && is_numeric($data['kwh_totali'])
            ? (float) $data['kwh_totali']
            : 0.0;

        $esito = $this->sessioni->termina($idSessione, $kwh);

        if (! $esito['ok']) {
            $this->error("Chiusura sessione $idSessione fallita ($motivo).");
            return;
        }

        $this->info("Sessione $idSessione chiusa ($motivo). kWh: $kwh, costo: " . ($esito['costo'] ?? 0));
    }
}
