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
        // id_punto e' locale alla stazione ("1", "2", ...): il canale per-punto
        // include il MAC senza ":" per non collidere fra stazioni diverse.
        $macNorm = str_replace(':', '', $this->idStazione);

        return [
            new Channel('mappa'),
            new Channel("punto.{$macNorm}.{$this->idPunto}"),
            new Channel("stazione.{$macNorm}"),
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