<?php

namespace Database\Factories;

use App\Models\Gamification_profilo_utente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class Gamification_profilo_utenteFactory extends Factory
{
    protected $model = Gamification_profilo_utente::class;

    public function definition(): array
    {
        return [
            // id_utente va fornito dal seeder (deve essere un UUID esistente in utenti)
            'id_utente'            => (string) Str::uuid(),
            'xp_totali'            => $this->faker->numberBetween(0, 5000),
            // livello è generato automaticamente da MariaDB → non lo settiamo
            'co2_risparmiata_kg'   => $this->faker->randomFloat(3, 0, 250),
            'streak_giorni'        => $this->faker->numberBetween(0, 45),
            'data_ultima_ricarica' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
