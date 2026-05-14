<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_profilo_utente', function (Blueprint $table) {
            $table->char('id_utente', 36)->primary();
            $table->foreign('id_utente')
                ->references('id_utente')->on('utenti')
                ->onDelete('cascade');

            $table->unsignedInteger('xp_totali')->default(0);

            // livello: generated column STORED, formula RPG classica
            // Livello 1 a 100 XP, Livello 2 a 400 XP, Livello 3 a 900 XP, ...
            // Lo aggiungiamo dopo via raw SQL perché Schema Builder non gestisce
            // bene le generated column su tutte le versioni di MariaDB.

            $table->decimal('co2_risparmiata_kg', 10, 3)->default(0);
            $table->unsignedInteger('streak_giorni')->default(0);
            $table->timestamp('data_ultima_ricarica')->nullable();

            $table->timestamps();
        });

        // Aggiunta della generated column (MariaDB 10.2+).
        // STORED → indicizzabile, perfetto per ORDER BY livello DESC nelle leaderboard.
        DB::statement("
            ALTER TABLE gamification_profilo_utente
            ADD COLUMN livello SMALLINT UNSIGNED
            AS (FLOOR(SQRT(xp_totali / 100))) STORED
            AFTER xp_totali
        ");

        DB::statement("CREATE INDEX idx_livello ON gamification_profilo_utente (livello)");
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_profilo_utente');
    }
};
