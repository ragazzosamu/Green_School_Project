<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica IL SOLO utente proprietario della sessione che e' stata avviata.
 * Canale privato user.{id_utente}: solo quell'utente autorizzato lo riceve.
 *
 * ShouldBroadcastNow: emesso sincrono (non passa dalla queue).
 */
class SessioneAvviata implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $idSessione,
        public string $idPunto,
        public string $idUtente,
    ) {}

    public function broadcastAs(): string
    {
        return 'sessione.avviata';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->idUtente}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id_sessione' => $this->idSessione,
            'id_punto'    => $this->idPunto,
            // id_utente non serve nel payload: chi riceve l'evento
            // sul canale privato e' gia' l'utente in questione.
        ];
    }
}
