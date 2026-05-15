<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PuntoHardwareStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $idPunto,
        public string $stato,
        public string $idStazione,
    ) {}

    public function broadcastAs(): string
    {
        return 'punto.hardware.status';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('mappa'),
            new Channel("punto.{$this->idPunto}"),
            new Channel("stazione.{$this->idStazione}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id_punto'         => $this->idPunto,
            'id_stazione'      => $this->idStazione,
            'stato_hardware'            => $this->stato,
            'data_cambiamento' => now()->timestamp,
        ];
    }
}