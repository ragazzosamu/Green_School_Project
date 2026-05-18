<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge la colonna `ruolo` alla tabella utenti.
 *
 * Valori possibili:
 *   - 'utente'  → utente normale (default)
 *   - 'admin'   → amministratore con accesso al pannello /admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->enum('ruolo', ['utente', 'admin'])
                  ->default('utente')
                  ->after('tipo_account');
        });
    }

    public function down(): void
    {
        Schema::table('utenti', function (Blueprint $table) {
            $table->dropColumn('ruolo');
        });
    }
};