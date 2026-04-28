<?php

namespace App\Models;
use App\Models\Badge_utente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Utenti extends Model
{
    use HasFactory;

    protected $table = 'utenti'; //indica la tabella con l anagrafica di tutti quelli che possono usare il sistema (nomi, mail ecc)
    protected $primaryKey = 'id_utente'; //il codice univoco che identifica ogni persona iscritta
    public $incrementing = false; //id non numerico progressivo
    protected $keyType = 'string'; 
    public $timestamps = false;

    protected $fillable = [
        'id_utente', 
        'email',
        'cellulare',
        'nome',
        'cognome',
        'tipo_account',
        'attivo'
    ]; //dati personali e tipo di account (per capire se è un dipendente della scuola o uno studente)

    public function sessioni() {
        return $this->hasMany(Sessioni_ricarica::class, 'id_utente', 'id_utente'); //permette di trovare velocemente tutte le ricariche che questa specifica persona ha fatto
    }

    public function badges() {
        return $this->hasMany(Badge_utente::class, 'id_utente', 'id_utente'); //serve a vedere quali e quante tessere rfid sono collegate a questa persona
    }
}