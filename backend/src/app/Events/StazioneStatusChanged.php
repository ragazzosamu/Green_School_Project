<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StazioneStatusChanged implements ShouldBroadcastNow
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
        // I ":" non sono caratteri validi nei nomi canale Pusher/Reverb:
        // normalizziamo il MAC (id_stazione) togliendoli.
        $macNorm = str_replace(':', '', (string) $this->idStazione);

        return [
            new Channel('mappa'),
            new Channel("stazione.{$macNorm}"),
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
            'libera'        => $this->stato,
            'data_cambiamento'  => now()->timestamp,
        ];

    }
}
