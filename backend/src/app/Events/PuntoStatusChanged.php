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
     *
     * id_punto ora e' locale alla stazione (vale "1", "2", ...): per evitare
     * collisioni tra stazioni il canale per-punto include il MAC senza ":"
     * (i due punti non sono caratteri validi nei nomi canale di
     * Pusher/Reverb). Il canale stazione.{id} ascolta il dettaglio stazione.
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('mappa')];

        if ($this->idStazione !== null) {
            // I ":" non sono caratteri validi nei nomi canale Pusher/Reverb:
            // normalizziamo il MAC togliendoli sia nel canale per-punto sia
            // nel canale per-stazione. Frontend usa la stessa regola.
            $macNorm = str_replace(':', '', $this->idStazione);
            $channels[] = new Channel("punto.{$macNorm}.{$this->idPunto}");
            $channels[] = new Channel("stazione.{$macNorm}");
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
