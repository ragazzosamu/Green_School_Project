<?php

namespace Database\Factories;

use App\Models\Gamification_badge_utente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class Gamification_badge_utenteFactory extends Factory
{
    protected $model = Gamification_badge_utente::class;

    public function definition(): array
    {
        return [
            'id_utente'           => (string) Str::uuid(), // sovrascritto dal seeder
            'id_badge'            => 1,                     // sovrascritto dal seeder
            'data_sblocco'        => $this->faker->dateTimeBetween('-3 months', 'now'),
            'id_sessione_trigger' => null,
        ];
    }
}
