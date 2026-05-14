<?php

namespace Database\Factories;

use App\Models\Scuola_consumo_mensile;
use Illuminate\Database\Eloquent\Factories\Factory;

class Scuola_consumo_mensileFactory extends Factory
{
    protected $model = Scuola_consumo_mensile::class;

    public function definition(): array
    {
        $elettrico = $this->faker->randomFloat(2, 3000, 9000);
        $termico   = $this->faker->randomFloat(2, 2000, 18000);
        $fv        = $this->faker->randomFloat(2, 0, 4000);

        return [
            'id_scuola' => 1,
            'anno'      => now()->year,
            'mese'      => $this->faker->numberBetween(1, 12),
            'consumo_elettrico_kwh' => $elettrico,
            'consumo_termico_kwh'   => $termico,
            'produzione_fv_kwh'     => $fv,
            // Fattori medi Italia: elettrico ~0.31 kg CO2/kWh, termico (gas) ~0.20 kg CO2/kWh
            'co2_emessa_kg' => round(($elettrico - $fv) * 0.31 + $termico * 0.20, 2),
        ];
    }
}
