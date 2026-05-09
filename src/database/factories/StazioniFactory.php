<?php

namespace Database\Factories;

use App\Models\Stazioni;
use Illuminate\Database\Eloquent\Factories\Factory;
use \Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Stazioni>
 */
class StazioniFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lat = fake()->latitude(45.665, 45.679);
        $lon = fake()->longitude(11.925, 11.939);
        $scuole = ['ITI Barsanti', 'Liceo Giorgione', 'IPSIA Nightingale', 'Sartor'];

        return [
            'id_stazione' => fake()->unique()->uuid(),
            'nome' => 'Stazione ' . fake()->randomElement($scuole),
            'indirizzo' => fake()->streetName() . ', Castelfranco Veneto',
            'latitudine' => $lat,
            'longitudine' => $lon,
            'coordinata' => DB::raw("ST_GeomFromText('POINT($lon $lat)')"),
            'tipo_area' => 'pubblico',
            'token' => bin2hex(random_bytes(64)),
        ];
    }
}
