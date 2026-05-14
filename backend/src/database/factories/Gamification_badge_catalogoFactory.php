<?php

namespace Database\Factories;

use App\Models\Gamification_badge_catalogo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class Gamification_badge_catalogoFactory extends Factory
{
    protected $model = Gamification_badge_catalogo::class;

    public function definition(): array
    {
        $nome = $this->faker->unique()->words(2, true);

        return [
            'codice'      => Str::upper(Str::slug($nome, '_')),
            'nome'        => Str::title($nome),
            'descrizione' => $this->faker->sentence(),
            'icona_emoji' => $this->faker->randomElement(['🌱','⚡','🌙','🏆','🚴','🔋','♻️','🌍']),
            'condizione_json' => [
                'tipo'   => 'soglia_xp',
                'valore' => $this->faker->numberBetween(100, 2000),
            ],
        ];
    }
}
