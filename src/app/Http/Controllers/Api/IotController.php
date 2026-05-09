<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sessioni_ricarica;
use App\Models\Punti_ricarica;
use App\Models\Stazioni;
use App\Events\HeartbeatRicevuto;
use App\Events\PuntoHardwareStatusChanged;
use App\Events\StazioneStatusChanged;

class IotController extends Controller
{
    public function __construct() {}

    /**
     * Riceve tensione e corrente durante una sessione di ricarica attiva.
     * Calcola il delta kWh dell'intervallo (5s) e lo accumula sulla sessione.
     * Fa il broadcast del nuovo incremento per aggiornare la dashboard in tempo reale.
     */
    public function Incremento_Ricarica(Request $request)
    {
        $data = $request->validate([
            'voltaggio' => ['required', 'numeric'],
            'corrente'  => ['required', 'numeric'],
            'id_punto'  => ['required', 'string'],
        ]);

        $intervallo = 5 / 3600;
        $KW         = ($data['voltaggio'] * $data['corrente']) / 1000;
        $deltaKWh   = $KW * $intervallo;

        Sessioni_ricarica::where('id_punto', $data['id_punto'])
            ->increment('quantita_kwh', $deltaKWh);

        HeartbeatRicevuto::dispatch($data['id_punto'], $deltaKWh);
    }

    /**
     * Ping periodico inviato dall'ESP32 ogni minuto anche quando non sta caricando.
     * Aggiorna lo stato hardware e il timestamp di ogni punto della stazione.
     * Fa il broadcast per tenere aggiornata la mappa in tempo reale.
     */
    public function Heartbeat(Request $request)
{
    $stazione = $request->attributes->get('stazione');

    $stazione->update([
        'data_ultimo_heartbeat' => now(),
        'stato_hardware'        => 'online',
    ]);

    $data = $request->validate([
        'punti'                  => ['required', 'array'],
        'punti.*.id_punto'       => ['required', 'string'],
        'punti.*.stato_hardware' => ['required', 'string', 'in:idle,online,offline,guasto,manutenzione_programmata'],
    ]);

    foreach ($data['punti'] as $punto) {
        Punti_ricarica::where('id_punto', $punto['id_punto'])
            ->update([
                'stato_hardware'        => $punto['stato_hardware'],
                'data_ultimo_heartbeat' => now(),
            ]);

        PuntoHardwareStatusChanged::dispatch(
            $punto['id_punto'],
            $punto['stato_hardware'],
            $stazione->id_stazione,
        );
    }
}

    /**
     * Riceve un cambio di stato hardware esplicito (es. guasto, manutenzione).
     * Aggiorna ogni punto coinvolto e fa il broadcast del nuovo stato.
     * Dopodichè ricalcola la disponibilità complessiva della stazione.
     */
    public function Cambio_stato_hardware(Request $request)
    {
        $stazione = $request->attributes->get('stazione');

        $data = $request->validate([
            'punti'                  => ['required', 'array'],
            'punti.*.id_punto'       => ['required', 'string', 'exists:punti_ricarica,id_punto'],
            'punti.*.stato_hardware' => ['required', 'string', 'in:idle,online,offline,guasto,manutenzione_programmata'],
        ]);

        foreach ($data['punti'] as $punto) {
            Punti_ricarica::where('id_punto', $punto['id_punto'])
                ->update(['stato_hardware' => $punto['stato_hardware']]);

            PuntoHardwareStatusChanged::dispatch(
                $punto['id_punto'],
                $punto['stato_hardware'],
                $stazione->id_stazione,
            );
        }

        $this->aggiornaStatoStazione($stazione);
    }

    // -------------------------------------------------------
    // PRIVATI
    // -------------------------------------------------------

    /**
     * Ricalcola se la stazione ha almeno un punto disponibile.
     * Un punto è disponibile se ha libera=true e lo stato hardware è funzionante.
     * Fa il broadcast solo se la disponibilità della stazione è effettivamente cambiata,
     * evitando eventi inutili (es. secondo guasto su stazione già occupata).
     */
    private function aggiornaStatoStazione(Stazioni $stazione): void
    {
        $haUnPuntoDisponibile = Punti_ricarica::where('id_stazione', $stazione->id_stazione)
            ->where('libera', true)
            ->whereNotIn('stato_hardware', ['guasto', 'offline', 'manutenzione_programmata'])
            ->exists();

        $nuovoStato = $haUnPuntoDisponibile ? true : false;

        if ($stazione->libera !== $nuovoStato) {
            $stazione->update(['libera' => $nuovoStato]);

            StazioneStatusChanged::dispatch(
                $stazione->id_stazione,
                $nuovoStato,
            );
        }
    }
}