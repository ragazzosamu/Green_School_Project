<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crea le basi
        \App\Models\Utenti::factory(10)->create();
        \App\Models\Stazioni::factory(5)->create();

        // 2. Crea i rami (dipendono dalle basi)
        \App\Models\Badge_utente::factory(10)->create();
        \App\Models\Accumulatori_stazione::factory(5)->create();
        \App\Models\Punti_ricarica::factory(15)->create();

        // 3. Crea i frutti (dipendono da tutto il resto)
        // Ora basta chiamare create() senza parametri!
        \App\Models\Sessioni_ricarica::factory(30)->create();
        \App\Models\StoricoLivelloBatteria::factory(100)->create();
    }
}
