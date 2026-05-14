<?php

namespace Database\Factories;

use App\Models\Badge_utente;
use Illuminate\Database\Eloquent\Factories\Factory;
use \App\Models\Utenti;

/**
 * @extends Factory<Badge_utente>
 */
class Badge_utenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $utente = \App\Models\Utenti::inRandomOrder()->first() 
              ?? \App\Models\Utenti::factory()->create();
        return [
            'id_utente' => $utente->id_utente,
            'codice_rfid' => fake()->unique()->hexColor(),
            'nome_badge' => 'Badge di ' . $utente->nome,
            'bloccato' => false,
        ];
    }
}
