<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PuntoStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $idPunto, public $stato)
    {}

    /**
     * Il nome con cui l'evento verrà chiamato nel frontend
     */

    public function broadcastAs(): string
    {
        return 'punto.status.{$this->idPunto}';
    
    }

    /**
     * Canali su cui trasmettere l'evento.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mappa'), 
            new Channel("punto.{$this->idPunto}"), 
        ];
    }

    /**
     * Payload che arriva al frontend.
     */
    public function broadcastWith(): array
    {
        return [
            'id_punto'   => $this->idPunto,
            'libera'     => $this->stato,
            'data_cambiamento'  => now()->timestamp,
        ];
    }
}
