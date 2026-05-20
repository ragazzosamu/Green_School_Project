<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge alla tabella utenti i campi necessari per il blocco temporaneo
 * dell'account dopo troppi tentativi di login falliti.
 *
 * Campi aggiunti:
 *   - login_tentativi      → contatore tentativi falliti consecutivi
 *   - login_bloccato_fino  → timestamp fino al quale l'account è bloccato (null = non bloccato)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->unsignedTinyInteger('login_tentativi')
                  ->default(0)
                  ->after('attivo')
                  ->comment('Numero di tentativi di login falliti consecutivi');

            $table->timestamp('login_bloccato_fino')
                  ->nullable()
                  ->after('login_tentativi')
                  ->comment('Timestamp fino al quale il login è bloccato; NULL = non bloccato');
        });
    }

    public function down(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->dropColumn(['login_tentativi', 'login_bloccato_fino']);
        });
    }
};