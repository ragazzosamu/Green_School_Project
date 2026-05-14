<?php

namespace Database\Factories;

use App\Models\Punti_ricarica;
use Illuminate\Database\Eloquent\Factories\Factory;
use \App\Models\Stazioni;

/**
 * @extends Factory<Punti_ricarica>
 */
class Punti_ricaricaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
    return [
        'id_punto' => fake()->unique()->uuid(),
        'id_stazione' => \App\Models\Stazioni::where('id_stazione', '!=', 'c1d1d1c3-2806-3007-8f48-33f2b28c4839')
        ->inRandomOrder()->first()?->id_stazione ?? \App\Models\Stazioni::factory(),
        'identificativo_fisico' => 'Presa ' . fake()->bothify('#-??'),
        'tipo_veicolo' => fake()->randomElement(['bici', 'monopattino']),
        'tipo_connettore' => 'Schuko',
        'potenza_max_kw' => fake()->randomFloat(2, 0.5, 1.5),
        'stato_hardware' => 'online',
        'data_ultimo_heartbeat' => now(), // Heartbeat "vivo"
        'tariffa_predefinita' => 0.00,
        'metodi_autenticazione_supportati' => 'QR_CODE, RFID',
    ];
}
}
