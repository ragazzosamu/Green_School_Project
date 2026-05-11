<?php

namespace Database\Factories;

use App\Models\Gamification_sfida_settimanale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Gamification_sfida_settimanaleFactory extends Factory
{
    protected $model = Gamification_sfida_settimanale::class;

    public function definition(): array
    {
        $inizio = Carbon::now()->startOfWeek();
        $fine   = (clone $inizio)->endOfWeek();

        $target    = $this->faker->randomFloat(3, 5, 50);
        $progresso = $this->faker->randomFloat(3, 0, $target);

        return [
            'id_utente'    => (string) Str::uuid(), // sovrascritto dal seeder
            'codice_sfida' => $this->faker->randomElement([
                'RICARICHE_5_SETTIMANA',
                'CO2_RISPARMIATA_10KG',
                'NOTTURNO_GREEN_3',
                'STREAK_7_GIORNI',
            ]),
            'target'      => $target,
            'progresso'   => $progresso,
            'data_inizio' => $inizio,
            'data_fine'   => $fine,
            'stato'       => 'attiva',
        ];
    }

    public function completata(): self
    {
        return $this->state(fn (array $a) => [
            'progresso' => $a['target'],
            'stato'     => 'completata',
        ]);
    }

    public function fallita(): self
    {
        return $this->state(fn (array $a) => [
            'data_inizio' => Carbon::now()->subWeeks(2)->startOfWeek(),
            'data_fine'   => Carbon::now()->subWeeks(2)->endOfWeek(),
            'stato'       => 'fallita',
        ]);
    }
}
