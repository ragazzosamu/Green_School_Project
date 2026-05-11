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

        // 1. CREAZIONE UTENTE TEST  'id_utente'       => fake()->unique()->uuid(),
        Utenti::create
        ([
            'id_utente' => fake()->unique()->uuid(),
            'nome' => 'test',
            'cognome' => 'test',
            'email' => 'test.test@email.it',
            'password' => Hash::make('password123'),
            'cellulare' => '3331122334',
            'tipo_account' => 'completo',
            'attivo' => true
        ]);


        $lat = fake()->latitude(45.665, 45.679);
        $lon = fake()->longitude(11.925, 11.939);
        
        Stazioni::create([

            'id_stazione' => 'c1d1d1c3-2806-3007-8f48-33f2b28c4839',
            'nome' => 'Stazione TEST',
            'indirizzo' => fake()->streetName() . ', Castelfranco Veneto',
            'latitudine' => $lat,
            'longitudine' => $lon,
            'coordinata' => DB::raw("ST_GeomFromText('POINT($lon $lat)')"),
            'tipo_area' => 'pubblico',
            'token'     => 'ad08bf1f9a0900dfefe3e3d52913b600025ccdd7122dbb7f3a524a5a9c9125f3'
        ]);

        \App\Models\Utenti::factory(10)->create();
        \App\Models\Stazioni::factory(5)->create();


        \App\Models\Badge_utente::factory(10)->create();
        \App\Models\Accumulatori_stazione::factory(5)->create();
        \App\Models\Punti_ricarica::factory(15)->create();

        \App\Models\Sessioni_ricarica::factory(30)->create();
        \App\Models\StoricoLivelloBatteria::factory(100)->create();

        // --- Seeder Green School (gamification + scuola) ---
        $this->call([
            Gamification_badge_catalogoSeeder::class,
            Gamification_profilo_utenteSeeder::class,
            ScuolaSeeder::class,
        ]);
    }
}