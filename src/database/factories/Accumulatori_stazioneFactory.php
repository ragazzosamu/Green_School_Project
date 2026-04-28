<?php

namespace Database\Factories;

use App\Models\Accumulatori_stazione;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accumulatori_stazione>
 */
class Accumulatori_stazioneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
    return [
        'id_accumulatore' => fake()->unique()->uuid(),
        'id_stazione' => \App\Models\Stazioni::inRandomOrder()->first()?->id_stazione ?? \App\Models\Stazioni::factory(),
        'nome' => 'Tesla Powerwall',
        'capacita_totale_kwh' => 150.00,
        'percentuale_carica' => fake()->randomFloat(2, 20, 95),
        'stato_operativo' => 'standby',
    ];
}
}
