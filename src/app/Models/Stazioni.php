<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stazioni extends Model
{
    use HasFactory;

    protected $table = 'stazioni'; //tabella che raggruppa le varie aree della scuola dove ci sono colonnine (esempio parcheggio sud o laboratorio)
    protected $primaryKey = 'id_stazione'; //il codice univoco della stazione intera
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_stazione', 
        'nome', 
        'indirizzo', 
        'latitudine', 
        'longitudine', 
        'tipo_area'
    ]; //info generali sulla stazione: il nome della zona e le coordinate geografiche per trovarla sulla mappa

    public function puntiRicarica() {
        return $this->hasMany(Punti_ricarica::class, 'id_stazione', 'id_stazione'); //permette di vedere quante e quali colonnine sono montate dentro questa specifica stazione
    }
    public function accumulatori() {
        return $this->hasMany(Accumulatori_stazione::class, 'id_stazione', 'id_stazione'); //serve a vedere quali batterie di accumulo sono instalate in questa zona per gestire l energia
    }
}