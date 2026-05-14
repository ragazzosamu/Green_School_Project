<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_badge_utente', function (Blueprint $table) {
            $table->char('id_utente', 36);
            $table->unsignedSmallInteger('id_badge');
            $table->timestamp('data_sblocco')->useCurrent();
            $table->char('id_sessione_trigger', 36)->nullable();

            $table->primary(['id_utente', 'id_badge']);

            $table->foreign('id_utente')
                ->references('id_utente')->on('utenti')
                ->onDelete('cascade');

            $table->foreign('id_badge')
                ->references('id_badge')->on('gamification_badge_catalogo')
                ->onDelete('cascade');

            $table->foreign('id_sessione_trigger')
                ->references('id_sessione')->on('sessioni_ricarica')
                ->onDelete('set null');

            $table->index('data_sblocco');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_badge_utente');
    }
};
