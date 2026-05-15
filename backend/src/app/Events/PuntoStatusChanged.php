<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PuntoStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $idPunto,
        public $stato,
        public ?string $idStazione = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'punto.status';
    }

    /**
     * Canali su cui trasmettere l'evento.
     * Includiamo stazione.{id} quando disponibile così la pagina di dettaglio
     * stazione può ascoltare un solo canale invece di uno per ogni punto.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('mappa'),
            new Channel("punto.{$this->idPunto}"),
        ];

        if ($this->idStazione !== null) {
            $channels[] = new Channel("stazione.{$this->idStazione}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'id_punto'         => $this->idPunto,
            'id_stazione'      => $this->idStazione,
            'libera'           => $this->stato,
            'data_cambiamento' => now()->timestamp,
        ];
    }
}
