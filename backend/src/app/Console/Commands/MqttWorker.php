<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\CodiceMonousoService;
use App\Services\MqttService;
use App\Services\SessioneService;

#[Signature('mqtt:leggi')]
#[Description('Worker MQTT: ascolta gli aggiornamenti delle colonnine sui topic stazione/{id_stazione}/{id_punto}/{canale}.')]
class MqttWorker extends Command
{
    public function __construct(
        private readonly MqttService $mqttService,
        private readonly SessioneService $sessioni,
        private readonly CodiceMonousoService $codici,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        try {
            // Una sola subscribe wildcard "stazione/#" che cattura tutto e poi
            // smista in base al numero di livelli del topic:
            //   stazione/{mac}/codice                 -> 3 livelli, station-wide
            //   stazione/{mac}/comandi                -> 3 livelli, pubblicato da noi (ignora)
            //   stazione/{mac}/ready                  -> 3 livelli, pubblicato da noi (ignora)
            //   stazione/{mac}/{id_punto}/{canale}    -> 4 livelli, per-punto
            // Evita problemi che alcune versioni della libreria PHP-MQTT hanno
            // con due subscribe sovrapposte sullo stesso client.
            $this->mqttService->subscribe('stazione/#', function ($topic, $message) {
                $parts = explode('/', $topic);

                $data = json_decode($message, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Errore: messaggio non JSON su $topic");
                    return;
                }

                // stazione/{mac}/{canale}   (station-wide)
                if (count($parts) === 3) {
                    $idStazione = $parts[1] ?? null;
                    $canale     = $parts[2] ?? null;
                    if (! $idStazione || ! $canale) return;

                    match ($canale) {
                        'codice'  => $this->gestisciCodice($idStazione, $data),
                        'comandi' => null, // li pubblichiamo noi
                        'ready'   => null, // pubblicato noi
                        default   => $this->warn("Canale station-wide sconosciuto '$canale' su $topic"),
                    };
                    return;
                }

                // stazione/{mac}/{id_punto}/{canale}   (per-punto)
                if (count($parts) === 4) {
                    $idStazione = $parts[1] ?? null;
                    $idPunto    = $parts[2] ?? null;
                    $canale     = $parts[3] ?? null;
                    if (! $idStazione || ! $idPunto || ! $canale) return;

                    match ($canale) {
                        'telemetria' => $this->gestisciTelemetria($idStazione, $idPunto, $data),
                        'eventi'     => $this->gestisciEvento($idStazione, $idPunto, $data, $topic),
                        'heartbeat'  => $this->gestisciHeartbeat($idStazione, $idPunto, $data),
                        'comandi'    => null, // li pubblichiamo noi
                        default      => $this->warn("Canale per-punto sconosciuto '$canale' su $topic"),
                    };
                    return;
                }

                $this->warn("Topic con numero di livelli inatteso: $topic");
            });

            $this->mqttService->loop();
        } catch (\Throwable $e) {
            Log::error('[MQTT Worker] ' . $e->getMessage(), ['exception' => $e]);
            $this->error('Worker MQTT terminato per errore: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function gestisciCodice(string $idStazione, array $data): void
    {
        $codice = (string) ($data['codice'] ?? '');
        if ($codice === '') return;

        $this->codici->memorizza($idStazione, $codice);
        $this->comment("Codice $codice memorizzato per stazione $idStazione");
    }

    private function gestisciTelemetria(string $idStazione, string $idPunto, array $data): void
    {
        $idSessione = $data['id_sessione']    ?? null;
        $voltaggio  = (float) ($data['voltaggio']      ?? 0);
        $corrente   = (float) ($data['corrente']       ?? 0);
        $intervallo = (float) ($data['intervallo_sec'] ?? 0);

        if (! $idSessione || $intervallo <= 0) {
            $this->comment("Telemetria scartata: dati insufficienti");
            return;
        }

        $deltaKwh = ($voltaggio * $corrente / 1000.0) * ($intervallo / 3600.0);

        $idUtente = DB::table('sessioni_ricarica')
            ->where('id_sessione', $idSessione)
            ->value('id_utente');

        if (! $idUtente) {
            $this->comment("Telemetria scartata: nessun id_utente per sessione $idSessione");
            return;
        }

        $totaleAggiornato = $this->sessioni->aggiungiKwh($idSessione, $deltaKwh);

        $this->info(sprintf(
            '-> Telemetria %s/%s: V=%.1fV I=%.2fA Δt=%.0fs Δkwh=%.4f totale=%.4f',
            $idStazione, $idPunto, $voltaggio, $corrente, $intervallo, $deltaKwh, $totaleAggiornato
        ));

        \App\Events\TelemetriaRicevuta::dispatch($idPunto, $deltaKwh, $idSessione, $idUtente);
    }

    private function gestisciHeartbeat(string $idStazione, string $idPunto, array $data): void
    {
        $ts = isset($data['ts']) && is_numeric($data['ts'])
            ? Carbon::createFromTimestamp((int) $data['ts'])
            : now();

        DB::table('punti_ricarica')
            ->where('id_stazione', $idStazione)
            ->where('id_punto', $idPunto)
            ->update(['data_ultimo_heartbeat' => $ts]);

        DB::table('stazioni')
            ->where('id_stazione', $idStazione)
            ->update(['data_ultimo_heartbeat' => $ts]);
    }

    private function gestisciEvento(string $idStazione, string $idPunto, array $data, string $topic): void
    {
        $this->info("Evento da $idStazione/$idPunto: " . json_encode($data));
        $evento = $data['evento'] ?? null;

        match ($evento) {
            'cavo_collegato'  => $this->gestisciCavoCollegato($idStazione, $idPunto),
            'cavo_scollegato' => $this->gestisciFineSessione($idStazione, $idPunto, $data, 'cavo_scollegato'),
            'batteria_piena'  => $this->gestisciFineSessione($idStazione, $idPunto, $data, 'batteria_piena'),
            default           => $this->warn("Evento sconosciuto '$evento' su $topic"),
        };
    }

    private function gestisciCavoCollegato(string $idStazione, string $idPunto): void
    {
        // Il pending e' a livello stazione: chiunque colleghi un cavo su un
        // qualsiasi punto della stazione consuma il pending e avvia la
        // sessione sul punto fisico scelto.
        $pending = $this->sessioni->consumaCodiceInAttesa($idStazione);

        if ($pending === null) {
            $this->comment("Cavo collegato su $idStazione/$idPunto ma nessun codice in attesa: ignorato.");
            return;
        }

        $this->info("-> Codice gia' verificato per stazione $idStazione, avvio sessione su punto $idPunto.");
        $esito = $this->sessioni->avvia($idStazione, $idPunto, $pending['id_utente']);

        if (! $esito['ok']) {
            Log::warning('[MQTT Worker] avvio sessione fallito', [
                'id_stazione' => $idStazione,
                'id_punto'    => $idPunto,
                'msg'         => $esito['messaggio'] ?? '',
            ]);
        }
    }

    private function gestisciFineSessione(string $idStazione, string $idPunto, array $data, string $motivo): void
    {
        $this->sessioni->pulisciStatoRendezVous($idStazione);

        $idSessione = $data['id_sessione'] ?? $this->sessioni->sessioneAttivaPerPunto($idStazione, $idPunto);
        if (! $idSessione) {
            $this->comment("Nessuna sessione attiva su $idStazione/$idPunto al momento di '$motivo'.");
            return;
        }

        $kwh = $this->sessioni->kwhCorrenti($idSessione);
        $esito = $this->sessioni->termina($idSessione, $kwh);

        if (! $esito['ok']) {
            $this->error("Chiusura sessione $idSessione fallita ($motivo).");
            return;
        }

        $this->info("Sessione $idSessione chiusa ($motivo). kWh: $kwh, costo: " . ($esito['costo'] ?? 0));
    }
}
