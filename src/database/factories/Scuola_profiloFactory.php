<?php

namespace Database\Factories;

use App\Models\Scuola_profilo;
use Illuminate\Database\Eloquent\Factories\Factory;

class Scuola_profiloFactory extends Factory
{
    protected $model = Scuola_profilo::class;

    public function definition(): array
    {
        return [
            'denominazione'     => 'ITIS ' . $this->faker->lastName(),
            'anno_costruzione'  => $this->faker->numberBetween(1965, 2015),
            'superficie_mq'     => $this->faker->randomFloat(2, 2500, 8000),
            'classe_energetica' => $this->faker->randomElement(['C', 'D', 'E', 'F']),
            'interventi_efficientamento' => [
                'cappotto_termico'    => $this->faker->boolean(40),
                'sostituzione_infissi'=> $this->faker->boolean(60),
                'led_relamping'       => $this->faker->boolean(80),
            ],
            'fotovoltaico_kwp' => $this->faker->randomFloat(2, 0, 80),
            'descrizione'      => $this->faker->paragraph(3),
        ];
    }
}
