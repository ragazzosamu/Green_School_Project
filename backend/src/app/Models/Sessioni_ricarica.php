<?php

namespace App\Models;

use App\Models\Utenti;
use App\Models\Punti_ricarica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sessioni_ricarica extends Model 
{
    use HasFactory;

    protected $table = 'sessioni_ricarica'; //indica a laravel il nome preciso della tabella nel database così sa dove andare a leggere i dati delle ricariche
    protected $primaryKey = 'id_sessione'; //specifica che per identificare una ricarica singola deve usare il campo id_sessione e non il nome standard
    public $incrementing = false; //visto che non usiamo numeri normali come 1,2,3 ma dei codici scritti mettiamo false così laravel non prova a sommare i numeri
    protected $keyType = 'string'; //chiarisce al sistema che l id è fatto di lettere e numeri (testo) e non è un numero intero
    public $timestamps = false; //serve a dire a laravel di non cercare le sue colonne prefissate per le date perchè usiamo i campi data_inizio e data_fine

    protected $fillable = [
        'id_sessione',
        'id_utente',
        'id_stazione',
        'id_punto',
        'metodo_avvio',
        'data_inizio',
        'data_fine',
        'quantita_kwh',
        'costo_totale',
        'stato_pagamento'
    ]; //questo è un elenco di sicurezza: sono gli unici campi della tabella dove il codice ha il permesso di scrivere dei dati

    protected static function booted()
    {
        static::creating(function ($sessione) {
            if (!$sessione->data_inizio) {
                $sessione->data_inizio = now(); //questo comando inserisce in automatico l orario esatto di questo momento nel campo data_inizio appena parte la ricarica
            }
        });
    }

    public function utente() 
    {
        return $this->belongsTo(Utenti::class, 'id_utente', 'id_utente'); //crea un collegamento logico per risalire subito a quale persona tra tutti gli utenti ha effettuato questa ricarica
    }

    public function puntoRicarica() 
    {
        return $this->belongsTo(Punti_ricarica::class, 'id_punto', 'id_punto'); //collega la ricarica alla colonnina specifica che è stata usata fisicamente
    }

    
}