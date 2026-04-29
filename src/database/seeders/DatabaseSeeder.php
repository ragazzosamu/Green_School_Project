<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utenti;
use App\Models\Stazioni;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Pulizia tabelle di sicurezza
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Utenti::truncate();
        Stazioni::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. CREAZIONE UTENTE MARIO ROSSI
        Utenti::create([
            'id_utente' => 'U001',
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'mario.rossi@email.it',
            'password' => Hash::make('password123'),
            'cellulare' => '3331122334',
            'tipo_account' => 'completo',
            'attivo' => true
        ]);

        // 2. CREAZIONE STAZIONE CON COORDINATA (Formato POINT)
        // Nota: Nel formato WKT si mette prima Longitudine poi Latitudine
        Stazioni::create([
            'id_stazione' => 'S001',
            'nome' => 'Parcheggio Nord',
            'indirizzo' => 'Via della Scuola, 1',
            'latitudine' => 45.4642,
            'longitudine' => 9.1900,
            'coordinata' => DB::raw("ST_GeomFromText('POINT(9.1900 45.4642)')"), 
            'tipo_area' => 'pubblico',
            'data_attivazione' => now(),
        ]);

        $this->command->info('Database popolato con successo (Inclusa la coordinata)!');
    }
}