<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StazioneStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public $idStazione, public $stato)
    {}

    /**
     * Canali su cui trasmettere l'evento.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mappa'),
            new Channel("stazione.{$this->idStazione}"), 
        ];
    }

    public function broadcastAs(): string
    {
        return 'stazione.status';
    }

    /**
     * Payload che arriva al frontend.
     */
    public function broadcastWith()
    {
        return [
            'id_stazione'   => $this->idStazione,
            'stato'      => $this->stato,
            'data_cambiamento'  => now()->timestamp,
        ];

    }
}
