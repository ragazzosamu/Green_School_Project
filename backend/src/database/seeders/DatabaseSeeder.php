<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utenti;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CREAZIONE UTENTE TEST
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

        \App\Models\Utenti::factory(10)->create();
        \App\Models\Badge_utente::factory(10)->create();

        // NOTA: stazioni, punti e sessioni NON vengono piu' seedati.
        // Le stazioni nascono dalla registrazione IoT reale; punti e
        // sessioni si popolano usando l'app.

        // --- Seeder Green School (gamification + scuola) ---
        // NOTA: Gamification_profilo_utenteSeeder NON viene piu' chiamato:
        // i profili (XP / punti classifica) si creano e crescono usando l'app.
        // Il profilo viene comunque creato on-the-fly da GamificationController.
        $this->call([
            Gamification_badge_catalogoSeeder::class,
            ScuolaSeeder::class,
            AdminSeeder::class, //ogni volta che lamcio la migration si crea un nuovo admin in automatico
        ]);

    }
}
