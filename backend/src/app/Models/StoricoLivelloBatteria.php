<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoricoLivelloBatteria extends Model
{
    use HasFactory;

    // Nome esatto della tabella nel database
    protected $table = 'storico_livello_batteria';

    // La chiave primaria è incrementale (bigIncrements), quindi lasciamo le impostazioni di default
    protected $primaryKey = 'id_misurazione';

    // Disabilitiamo i timestamp standard (created_at/updated_at) 
    // perché usi 'timestamp_misurazione'
    public $timestamps = false;

    protected $fillable = [
        'id_accumulatore',
        'timestamp_misurazione',
        'livello_kwh'
    ];

    // Relazione: Lo storico appartiene a un accumulatore
    public function accumulatore()
    {
        return $this->belongsTo(Accumulatori_stazione::class, 'id_accumulatore', 'id_accumulatore');
    }
}
