<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gamification_sfida_settimanale extends Model
{
    use HasFactory;

    protected $table      = 'gamification_sfide_settimanali';
    protected $primaryKey = 'id_sfida';
    public    $incrementing = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'id_utente',
        'codice_sfida',
        'target',
        'progresso',
        'data_inizio',
        'data_fine',
        'stato',
    ];

    protected $casts = [
        'target'      => 'decimal:3',
        'progresso'   => 'decimal:3',
        'data_inizio' => 'datetime',
        'data_fine'   => 'datetime',
    ];

    public function utente(): BelongsTo
    {
        return $this->belongsTo(Utenti::class, 'id_utente', 'id_utente');
    }

    // Helper utili per le query
    public function scopeAttive($query)
    {
        return $query->where('stato', 'attiva');
    }

    public function scopeInCorso($query)
    {
        return $query->where('stato', 'attiva')
                     ->where('data_inizio', '<=', now())
                     ->where('data_fine',   '>=', now());
    }
}
