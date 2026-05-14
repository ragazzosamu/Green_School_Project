<?php

namespace Database\Factories;

use App\Models\StoricoLivelloBatteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoricoLivelloBatteria>
 */
class StoricoLivelloBatteriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Recupera un accumulatore esistente (magari creato poco prima nel Seeder)
        // Se il DB è vuoto, la factory di Accumulatori_stazione ne creerà uno al volo
        $accumulatore = \App\Models\Accumulatori_stazione::inRandomOrder()->first() 
                        ?? \App\Models\Accumulatori_stazione::factory()->create();

        return [
            'id_accumulatore' => $accumulatore->id_accumulatore,
            'livello_kwh' => fake()->randomFloat(2, 0, $accumulatore->capacita_totale_kwh),
            'timestamp_misurazione' => now(),
        ];
    }
}
