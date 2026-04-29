<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Utenti;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    public function test_utente_puo_effettuare_login()
    {
        // Creiamo un utente temporaneo con ID numerico (999)
        Utenti::updateOrCreate(
            ['email' => 'test@esempio.it'],
            [
                'id_utente' => 999, 
                'nome' => 'Test',
                'cognome' => 'User',
                'password' => Hash::make('password_test'),
                'cellulare' => '1234567890',
                'tipo_account' => 'completo',
                'attivo' => true
            ]
        );

        $response = $this->postJson('/api/login', [
            'email' => 'test@esempio.it',
            'password' => 'password_test',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_login_fallisce_con_password_errata()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@esempio.it',
            'password' => 'password_sbagliata',
        ]);

        $response->assertStatus(401);
    }
}