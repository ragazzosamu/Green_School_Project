<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Gamification_badge_utente extends Pivot
{
    use HasFactory;

    protected $table      = 'gamification_badge_utente';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        'id_utente',
        'id_badge',
        'data_sblocco',
        'id_sessione_trigger',
    ];

    protected $casts = [
        'data_sblocco' => 'datetime',
    ];

    public function utente(): BelongsTo
    {
        return $this->belongsTo(Utenti::class, 'id_utente', 'id_utente');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Gamification_badge_catalogo::class, 'id_badge', 'id_badge');
    }

    public function sessioneTrigger(): BelongsTo
    {
        return $this->belongsTo(Sessioni_ricarica::class, 'id_sessione_trigger', 'id_sessione');
    }
}
