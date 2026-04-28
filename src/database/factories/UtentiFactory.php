<?php

namespace Database\Factories;

use App\Models\Utenti;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Utenti>
 */
class UtentiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
       return [
        'id_utente'       => fake()->unique()->uuid(),
        'email'           => fake()->unique()->safeEmail(),
        'cellulare'       => fake()->phoneNumber(),
        'nome'            => fake()->firstName(),
        'cognome'         => fake()->lastName(),
        'tipo_account'    => 'completo',
        'attivo'          => true,
        'data_registrazione' => now(),
    ];
    }
}
