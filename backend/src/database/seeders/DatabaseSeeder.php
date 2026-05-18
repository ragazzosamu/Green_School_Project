<?php

namespace Database\Seeders;

use App\Models\Punti_ricarica;
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

        Punti_ricarica::create
        ([
            'id_punto' => '03ad4c87-612e-304e-a4ba-0774eea23b49',
            'id_stazione' => 'c1d1d1c3-2806-3007-8f48-33f2b28c4839',
            'identificativo_fisico' => 'Presa ' . fake()->bothify('#-??'),
            'tipo_veicolo' => fake()->randomElement(['bici', 'monopattino']),
            'tipo_connettore' => 'Schuko',
            'potenza_max_kw' => fake()->randomFloat(2, 0.5, 1.5),
            'stato_hardware' => 'online',
            'data_ultimo_heartbeat' => now(), // Heartbeat "vivo"
            'tariffa_predefinita' => 0.00,
            'metodi_autenticazione_supportati' => 'QR_CODE, RFID',
        ]);

        Punti_ricarica::create
        ([
            'id_punto' => 'c6510a93-cdd9-3730-a5ff-2105e69fc62e',
            'id_stazione' => 'c1d1d1c3-2806-3007-8f48-33f2b28c4839',
            'identificativo_fisico' => 'Presa ' . fake()->bothify('#-??'),
            'tipo_veicolo' => fake()->randomElement(['bici', 'monopattino']),
            'tipo_connettore' => 'Schuko',
            'potenza_max_kw' => fake()->randomFloat(2, 0.5, 1.5),
            'stato_hardware' => 'online',
            'data_ultimo_heartbeat' => now(), // Heartbeat "vivo"
            'tariffa_predefinita' => 0.00,
            'metodi_autenticazione_supportati' => 'QR_CODE, RFID',
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
            AdminSeeder::class, //ogni volta che lamcio la migration si crea un nuovo admin in automatico
        ]);

    }
}