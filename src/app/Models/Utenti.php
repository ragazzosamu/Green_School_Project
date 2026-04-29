<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Utenti extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'utenti';
    protected $primaryKey = 'id_utente';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_utente', 
        'email',
        'password', // Aggiunta per il login
        'cellulare',
        'nome',
        'cognome',
        'tipo_account',
        'attivo'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function sessioni() {
        return $this->hasMany(Sessioni_ricarica::class, 'id_utente', 'id_utente');
    }

    public function badges() {
        return $this->hasMany(Badge_utente::class, 'id_utente', 'id_utente');
    }
}