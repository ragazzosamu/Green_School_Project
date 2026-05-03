<?php

namespace Database\Factories;

use App\Models\Utenti;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Utenti>
 */
class UtentiFactory extends Factory
{
    protected $model = Utenti::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Genera un ID tipo U123 invece di un UUID lungo, per restare simile ai tuoi dati
            'id_utente'       => fake()->unique()->uuid(),
            'email'           => fake()->unique()->safeEmail(),
            'password'        => Hash::make('password123'), // <--- AGGIUNTO: tutti gli utenti fake avranno questa password
            'cellulare'       => fake()->phoneNumber(),
            'nome'            => fake()->firstName(),
            'cognome'         => fake()->lastName(),
            'tipo_account'    => 'completo',
            'attivo'          => true,
            'data_registrazione' => now(),
        ];
    }
}