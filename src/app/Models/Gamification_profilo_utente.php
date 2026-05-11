<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gamification_profilo_utente extends Model
{
    use HasFactory;

    protected $table      = 'gamification_profilo_utente';
    protected $primaryKey = 'id_utente';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id_utente',
        'xp_totali',
        'co2_risparmiata_kg',
        'streak_giorni',
        'data_ultima_ricarica',
    ];

    /**
     * livello è una GENERATED COLUMN nel DB: read-only lato Eloquent.
     * Non va in $fillable e non si aggiorna manualmente — lo calcola MariaDB.
     */
    protected $guarded = ['livello'];

    protected $casts = [
        'xp_totali'            => 'integer',
        'livello'              => 'integer',
        'streak_giorni'        => 'integer',
        'co2_risparmiata_kg'   => 'decimal:3',
        'data_ultima_ricarica' => 'datetime',
    ];

    public function utente(): BelongsTo
    {
        return $this->belongsTo(Utenti::class, 'id_utente', 'id_utente');
    }
}
