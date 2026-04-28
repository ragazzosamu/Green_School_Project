<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Accumulatori_stazione extends Model
{
    use HasFactory;

    protected $table = 'accumulatori_stazione'; //si riferisce alle batterie di accumulo che salvano l energia prodotta dai pannelli della stazione
    protected $primaryKey = 'id_accumulatore'; //codice univoco della batteria
    public $incrementing = false; 
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_accumulatore', 
        'id_stazione', 
        'capacita_max_kwh', 
        'livello_carica_attuale', 
        'stato_salute_soh'
    ]; //informazioni tecniche sulla batteria: quanta energia può tenere, quanta ne ha ora e se si sta rovinando (stato salute)

    public function stazione()
    {
        return $this->belongsTo(Stazioni::class, 'id_stazione', 'id_stazione'); //dice a quale stazione di ricarica è collegata fisicamente questa batteria
    }
}