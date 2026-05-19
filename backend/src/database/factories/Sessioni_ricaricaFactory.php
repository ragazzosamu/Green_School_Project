<?php

namespace Database\Factories;

use App\Models\Sessioni_ricarica;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Sessioni_ricarica>
 */
class Sessioni_ricaricaFactory extends Factory
{
    public function definition(): array
    {
        // Uso DB::table per evitare problemi di hydration Eloquent con la PK
        // composta di punti_ricarica. La factory si aspetta che il seeder
        // abbia gia' creato almeno un punto.
        $punto = DB::table('punti_ricarica')->inRandomOrder()->first();
        if (! $punto) {
            throw new \RuntimeException(
                'Sessioni_ricaricaFactory: nessun punto_ricarica nel DB. '
                . 'Il seeder deve creare i punti prima di chiamare questa factory.'
            );
        }

        $utente = \App\Models\Utenti::inRandomOrder()->first()
            ?? \App\Models\Utenti::factory()->create();

        return [
            'id_sessione'     => fake()->unique()->uuid(),
            'id_utente'       => $utente->id_utente,
            'id_stazione'     => $punto->id_stazione,
            'id_punto'        => $punto->id_punto,
            'metodo_avvio'    => 'CODICE',
            'data_inizio'     => now()->subMinutes(rand(30, 120)),
            'data_fine'       => now(),
            'quantita_kwh'    => fake()->randomFloat(3, 0.2, 1.2),
            'costo_totale'    => 0.00,
            'stato_pagamento' => 'gratuito',
        ];
    }
}
