<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Utenti extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'utenti';
    protected $primaryKey = 'id_utente'; // La tua chiave
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_utente', 'email', 'password', 'cellulare', 'nome', 'cognome', 'tipo_account', 'attivo'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * IMPORTANTE PER SANCTUM: 
     * Sovrascriviamo questo metodo per dire a Sanctum che la chiave è id_utente
     */
    public function getAuthIdentifierName()
    {
        return 'id_utente';
    }

    public function sessioni() {
        return $this->hasMany(Sessioni_ricarica::class, 'id_utente', 'id_utente');
    }

    public function badges() {
        return $this->hasMany(Badge_utente::class, 'id_utente', 'id_utente');
    }
}