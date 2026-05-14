<?php

namespace Database\Seeders;

use App\Models\Gamification_profilo_utente;
use App\Models\Utenti;
use Illuminate\Database\Seeder;

class Gamification_profilo_utenteSeeder extends Seeder
{
    /**
     * Crea un profilo gamification per ogni utente esistente
     * con valori demo plausibili. Il livello viene calcolato
     * automaticamente dalla generated column.
     */
    public function run(): void
    {
        $utenti = Utenti::all();

        if ($utenti->isEmpty()) {
            $this->command->warn('⚠ Nessun utente in tabella utenti — skip seeder profili gamification.');
            return;
        }

        foreach ($utenti as $utente) {
            Gamification_profilo_utente::updateOrCreate(
                ['id_utente' => $utente->id_utente],
                [
                    'xp_totali'            => mt_rand(0, 3000),
                    'co2_risparmiata_kg'   => round(mt_rand(0, 25000) / 100, 3),
                    'streak_giorni'        => mt_rand(0, 30),
                    'data_ultima_ricarica' => now()->subDays(mt_rand(0, 14)),
                ]
            );
        }

        $this->command->info('✓ Profili gamification creati per ' . $utenti->count() . ' utenti.');
    }
}
