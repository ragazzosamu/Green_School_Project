<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Stazioni;
use App\Models\Punti_ricarica;
use App\Events\StazioneStatusChanged;
use App\Events\PuntoHardwareStatusChanged;

#[Signature('app:heartbeat-checker')]
#[Description('Controlla continuamente gli heartbeat dal DB e marca offline i punti che non parlano da troppo tempo')]
class HeartbeatChecker extends Command
{
    /**
     * Soglia oltre la quale un punto viene considerato offline.
     * 3 minuti = 180 secondi. Il simulatore manda heartbeat ogni 60s,
     * quindi 3 minuti = 3 heartbeat persi consecutivamente (ragionevole).
     */
    private const SOGLIA_OFFLINE_SEC = 180;

    /**
     * Intervallo tra una passata e la successiva. Vale la pena tenerlo
     * a meta' della soglia per garantire reattivita' senza spammare query.
     */
    private const INTERVALLO_CHECK_SEC = 90;

    public function handle()
    {
        $this->info('[HeartbeatChecker] Avviato. Soglia offline: ' . self::SOGLIA_OFFLINE_SEC . 's, intervallo: ' . self::INTERVALLO_CHECK_SEC . 's.');

        while (true) {
            try {
                $this->controlloUnGiro();
            } catch (\Throwable $e) {
                Log::error('[HeartbeatChecker] errore nel giro di controllo', ['ex' => $e->getMessage()]);
                $this->error('Errore nel giro: ' . $e->getMessage());
            }

            sleep(self::INTERVALLO_CHECK_SEC);
        }
    }

    /**
     * Una singola passata: per ogni stazione controlla tutti i suoi punti,
     * marca offline quelli scaduti e online quelli che hanno ricominciato a
     * mandare heartbeat. Dispatcha gli eventi solo quando lo stato cambia.
     */
    private function controlloUnGiro(): void
    {
        $stazioni = Stazioni::with('puntiRicarica')->get();
        $sogliaTs = now()->subSeconds(self::SOGLIA_OFFLINE_SEC);

        foreach ($stazioni as $stazione) {
            $statoStazioneCambiato = false;

            foreach ($stazione->puntiRicarica as $punto) {
                $heartbeatScaduto = $punto->data_ultimo_heartbeat === null
                    || $punto->data_ultimo_heartbeat < $sogliaTs;

                // Punto online ma heartbeat troppo vecchio -> diventa offline
                if ($heartbeatScaduto && $punto->stato_hardware === 'online') {
                    DB::table('punti_ricarica')
                        ->where('id_stazione', $stazione->id_stazione)
                        ->where('id_punto', $punto->id_punto)
                        ->update(['stato_hardware' => 'offline']);

                    PuntoHardwareStatusChanged::dispatch(
                        $punto->id_punto,
                        'offline',
                        $stazione->id_stazione,
                    );

                    $this->warn("→ Punto {$punto->id_punto} marcato OFFLINE (ultimo heartbeat: {$punto->data_ultimo_heartbeat})");
                    $statoStazioneCambiato = true;
                    continue;
                }

                // Punto offline ma heartbeat fresco -> torna online
                if (! $heartbeatScaduto && $punto->stato_hardware === 'offline') {
                    DB::table('punti_ricarica')
                        ->where('id_stazione', $stazione->id_stazione)
                        ->where('id_punto', $punto->id_punto)
                        ->update(['stato_hardware' => 'online']);

                    PuntoHardwareStatusChanged::dispatch(
                        $punto->id_punto,
                        'online',
                        $stazione->id_stazione,
                    );

                    $this->info("→ Punto {$punto->id_punto} torna ONLINE");
                    $statoStazioneCambiato = true;
                }
            }

            // Solo se almeno un punto e' cambiato ricalcoliamo lo stato aggregato
            if ($statoStazioneCambiato) {
                $this->aggiornaStatoStazione($stazione);
            }
        }
    }

    /**
     * Ricalcola se la stazione ha almeno un punto disponibile.
     * Un punto e' disponibile se ha libera=true e lo stato hardware e' funzionante.
     * Fa il broadcast SOLO se la disponibilita' della stazione e' effettivamente
     * cambiata, evitando eventi inutili (es. secondo guasto su stazione gia' piena).
     */
    private function aggiornaStatoStazione(Stazioni $stazione): void
    {
        $haUnPuntoDisponibile = Punti_ricarica::where('id_stazione', $stazione->id_stazione)
            ->where('libera', true)
            ->whereNotIn('stato_hardware', ['guasto', 'offline', 'manutenzione_programmata'])
            ->exists();

        $nuovoStato = (bool) $haUnPuntoDisponibile;

        if ((bool) $stazione->libera !== $nuovoStato) {
            $stazione->update(['libera' => $nuovoStato]);

            StazioneStatusChanged::dispatch(
                $stazione->id_stazione,
                $nuovoStato,
            );
        }
    }
}
