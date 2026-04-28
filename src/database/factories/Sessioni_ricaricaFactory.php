<?php

namespace Database\Factories;

use App\Models\Sessioni_ricarica;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sessioni_ricarica>
 */
class Sessioni_ricaricaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
    $punto = \App\Models\Punti_ricarica::inRandomOrder()->first() ?? \App\Models\Punti_ricarica::factory()->create();
    $utente = \App\Models\Utenti::inRandomOrder()->first() ?? \App\Models\Utenti::factory()->create();
    $badge = \App\Models\Badge_utente::where('id_utente', $utente->id_utente)->first();

    return [
        'id_sessione' => fake()->unique()->uuid(),
        'id_utente' => $utente->id_utente,
        'id_punto' => $punto->id_punto,
        'metodo_avvio' =>  'QR_CODE',
        'data_inizio' => now()->subMinutes(rand(30, 120)),
        'data_fine' => now(),
        'quantita_kwh' => fake()->randomFloat(3, 0.2, 1.2), // Energia reale per bici/monopattini
        'costo_totale' => 0.00,
        'stato_pagamento' => 'gratuito',
    ];
}
}
