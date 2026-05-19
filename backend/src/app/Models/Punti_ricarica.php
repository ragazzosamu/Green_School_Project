<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Punti_ricarica extends Model
{
    use HasFactory;

    protected $table = 'punti_ricarica';
    // Chiave reale a livello DB e' composta (id_stazione, id_punto), ma
    // Eloquent non la supporta nativamente. Teniamo 'id_punto' come
    // primaryKey nominale per non rompere hydration/relations Eloquent
    // (es. inRandomOrder()->first() restituirebbe un modello mal formato
    // con primaryKey=null). NON usare ::find() perche' tornerebbe un punto
    // qualsiasi con quel id_punto in qualsiasi stazione: usa sempre
    // ->where(['id_stazione'=>X, 'id_punto'=>Y]) o DB::table().
    protected $primaryKey = 'id_punto';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_punto',
        'id_stazione',
        'identificativo_fisico',
        'tipo_veicolo',
        'tipo_connettore',
        'potenza_max_kw',
        'stato_hardware',
        'libera',
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
        return Sessioni_ricarica::query()
            ->where('id_stazione', $this->id_stazione)
            ->where('id_punto', $this->id_punto);
    }
}