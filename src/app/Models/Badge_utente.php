<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Badge_utente extends Model
{
    use HasFactory;

    protected $table = 'badge_utente'; //tabella che associa le tessere fisiche alle persone registrate nel sistema
    protected $primaryKey = 'id_badge'; //indica l identificativo della tessera
    public $timestamps = false; //niente date automatiche di sistema

    protected $fillable = ['id_badge', 'id_utente', 'codice_rfid', 'nome_badge', 'bloccato']; //qui salviamo il codice della tessera e se è stata bloccata (magari se qualcuno l ha persa)

    public function utente() {
        return $this->belongsTo(Utenti::class, 'id_utente', 'id_utente'); //collegamento fondamentale: serve a capire a quale studente o prof appartiene la tessera che è stata appena usata
    }
}