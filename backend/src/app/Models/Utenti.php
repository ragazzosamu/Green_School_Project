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
    protected $primaryKey = 'id_utente';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id_utente', 'email', 'password', 'cellulare',
        'nome', 'cognome', 'tipo_account', 'ruolo', 'attivo',
        'login_tentativi', 'login_bloccato_fino',   // ← lockout
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Cast automatici: login_bloccato_fino viene trattato come Carbon datetime.
     */
    protected $casts = [
        'login_bloccato_fino' => 'datetime',
    ];

    public function getAuthIdentifierName()
    {
        return 'id_utente';
    }

    /** Restituisce true se l'utente è amministratore */
    public function isAdmin(): bool
    {
        return $this->ruolo === 'admin';
    }

    public function sessioni()
    {
        return $this->hasMany(Sessioni_ricarica::class, 'id_utente', 'id_utente');
    }

    public function badges()
    {
        return $this->hasMany(Badge_utente::class, 'id_utente', 'id_utente');
    }
}