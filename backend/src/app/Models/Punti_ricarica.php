<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Punti_ricarica extends Model
{
    use HasFactory;

    protected $table = 'punti_ricarica'; //collega il modello alla tabella che contiene l elenco di tutte le prese di ricarica della scuola
    protected $primaryKey = 'id_punto'; //dice che il codice univoco della presa è id_punto
    public $incrementing = false; //anche qui non usiamo numeri progressivi automatici per gli id
    protected $keyType = 'string'; //conferma che l identificativo della presa è scritto come testo
    public $timestamps = false; //disabilita le date automatiche di sistema

    protected $fillable = [
        'id_punto', 
        'id_stazione', 
        'identificativo_fisico', 
        'tipo_veicolo', 
        'tipo_connettore', 
        'potenza_max_kw', 
        'stato_hardware', 
        'data_ultimo_heartbeat'
    ]; //qui ci sono tutte le caratteristiche della presa, tipo se è per macchine o moto e se in questo momento funziona (stato hardware)

    protected static function booted()
    {
        static::creating(function ($punto) {
            if (!$punto->data_ultimo_heartbeat) {
                $punto->data_ultimo_heartbeat = now(); //appena registriamo una nuova presa segna l ora attuale per dire che la colonnina è online e funzionante
            }
        });
    }

    public function stazione() 
    {
        return $this->belongsTo(Stazioni::class, 'id_stazione', 'id_stazione'); //serve per capire in quale zona o parcheggio specifico della scuola si trova questa presa
    }

    public function sessioni() 
    {
        return $this->hasMany(Sessioni_ricarica::class, 'id_punto', 'id_punto'); //permette di vedere la lista completa di tutte le ricariche che sono state fatte su questa presa nel tempo
    }
}