<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Gamification_badge_catalogo extends Model
{
    use HasFactory;

    protected $table      = 'gamification_badge_catalogo';
    protected $primaryKey = 'id_badge';
    public    $incrementing = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'codice',
        'nome',
        'descrizione',
        'icona_emoji',
        'condizione_json',
    ];

    protected $casts = [
        'condizione_json' => 'array',
    ];

    public function utenti(): BelongsToMany
    {
        return $this->belongsToMany(
            Utenti::class,
            'gamification_badge_utente',
            'id_badge',
            'id_utente'
        )->using(Gamification_badge_utente::class)
         ->withPivot(['data_sblocco', 'id_sessione_trigger']);
    }
}
