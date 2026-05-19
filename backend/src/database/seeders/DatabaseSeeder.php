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
        echo "[SEEDER] versione nuova (no Punti_ricarica::factory)\n";

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
        
        // Stazione TEST: id = MAC address fittizio, gia' "attiva" (setup completo)
        $macTest = 'AA:BB:CC:DD:EE:FF';
        Stazioni::create([
            'id_stazione' => $macTest,
            'stato_setup' => 'attiva',
            'nome' => 'Stazione TEST',
            'indirizzo' => fake()->streetName() . ', Castelfranco Veneto',
            'latitudine' => $lat,
            'longitudine' => $lon,
            'coordinata' => DB::raw("ST_GeomFromText('POINT($lon $lat)')"),
            'tipo_area' => 'pubblico',
        ]);

        // Due punti locali per la stazione TEST: id_punto 1, 2
        foreach (['1', '2'] as $idp) {
            Punti_ricarica::create([
                'id_punto'      => $idp,
                'id_stazione'   => $macTest,
                'identificativo_fisico' => "Presa #$idp",
                'tipo_veicolo'  => fake()->randomElement(['bici', 'monopattino']),
                'tipo_connettore' => 'Schuko',
                'potenza_max_kw' => fake()->randomFloat(2, 0.5, 1.5),
                'stato_hardware' => 'offline',
                'libera'         => 1,
                'data_ultimo_heartbeat' => now(),
                'tariffa_predefinita' => 0.00,
                'metodi_autenticazione_supportati' => 'CODICE, RFID',
            ]);
        }

        \App\Models\Utenti::factory(10)->create();
        \App\Models\Stazioni::factory(5)->create();


        \App\Models\Badge_utente::factory(10)->create();
        \App\Models\Accumulatori_stazione::factory(5)->create();

        // Punti deterministici: per ogni stazione factory creiamo 2 o 3 punti
        // numerati 1..N. Questo evita le collisioni sulla PK composta
        // (id_stazione, id_punto) che ci sarebbero con una factory random.
        \App\Models\Stazioni::where('id_stazione', '!=', $macTest)
            ->get()
            ->each(function ($stazione) {
                $n = rand(2, 3);
                for ($i = 1; $i <= $n; $i++) {
                    \App\Models\Punti_ricarica::create([
                        'id_punto'              => (string) $i,
                        'id_stazione'           => $stazione->id_stazione,
                        'identificativo_fisico' => "Presa #$i",
                        'tipo_veicolo'          => fake()->randomElement(['bici', 'monopattino']),
                        'tipo_connettore'       => 'Schuko',
                        'potenza_max_kw'        => fake()->randomFloat(2, 0.5, 1.5),
                        'stato_hardware'        => 'offline',
                        'libera'                => 1,
                        'data_ultimo_heartbeat' => now(),
                        'tariffa_predefinita'   => 0.00,
                        'metodi_autenticazione_supportati' => 'CODICE, RFID',
                    ]);
                }
            });

        // Sessioni di esempio: prendo i punti gia' creati e per ognuno
        // genero qualche sessione storica chiusa. Niente factory random:
        // la PK e' composta e i loop manuali sono blindati.
        $puntiPerSessioni = DB::table('punti_ricarica')->get();
        $utenti = \App\Models\Utenti::all();
        foreach ($puntiPerSessioni as $p) {
            $n = rand(2, 5);
            for ($i = 0; $i < $n; $i++) {
                $utente = $utenti->random();
                \App\Models\Sessioni_ricarica::create([
                    'id_sessione'     => fake()->unique()->uuid(),
                    'id_utente'       => $utente->id_utente,
                    'id_stazione'     => $p->id_stazione,
                    'id_punto'        => $p->id_punto,
                    'metodo_avvio'    => 'CODICE',
                    'data_inizio'     => now()->subMinutes(rand(60, 1440 * 30)),
                    'data_fine'       => now()->subMinutes(rand(1, 50)),
                    'quantita_kwh'    => fake()->randomFloat(3, 0.2, 1.2),
                    'costo_totale'    => 0.00,
                    'stato_pagamento' => 'gratuito',
                ]);
            }
        }

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