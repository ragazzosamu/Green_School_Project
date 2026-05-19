<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Crea l'account amministratore di default.
 *
 * Credenziali:
 *   email:    admin@greenschool.it
 *   password: Admin@GreenSchool2026
 *
 * Esegui con:  php artisan db:seed --class=AdminSeeder
 * Oppure aggiungi AdminSeeder::class in DatabaseSeeder.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@greenschool.it';

        // Evita duplicati se il seeder viene eseguito più volte
        if (DB::table('utenti')->where('email', $email)->exists()) {
            $this->command->info('[AdminSeeder] Admin già presente, skip.');
            return;
        }

        DB::table('utenti')->insert([
            'id_utente'    => Str::uuid()->toString(),
            'email'        => $email,
            'password'     => Hash::make('password123'),
            'nome'         => 'Admin',
            'cognome'      => 'GreenSchool',
            'cellulare'    => '0000000000',
            'tipo_account' => 'completo',
            'ruolo'        => 'admin',
            'attivo'       => true,
        ]);

        $this->command->info('[AdminSeeder] Account admin creato: ' . $email);
    }
}