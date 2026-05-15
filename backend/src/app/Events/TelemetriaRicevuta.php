<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Telemetria di ricarica (kWh incrementali) destinata SOLO all'utente
 * che sta caricando. Canale privato user.{id_utente}.
 */
class TelemetriaRicevuta implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $idPunto,
        public $deltaKwh,
        public $id_sessione,
        public ?string $idUtente = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'ricarica.heartbeat';
    }

    public function broadcastOn(): array
    {
        // Se non abbiamo id_utente (chiamata legacy) non broadcastiamo
        // su un canale pubblico per non leakare dati. Restituiamo array
        // vuoto: l'evento viene dispatchato ma nessuno lo riceve.
        if (! $this->idUtente) {
            return [];
        }

        return [
            new PrivateChannel("user.{$this->idUtente}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id_punto'         => $this->idPunto,
            'cambiamento_kwh'  => $this->deltaKwh,
            'data_cambiamento' => now()->timestamp,
            'id_sessione'      => $this->id_sessione,
        ];
    }
}
