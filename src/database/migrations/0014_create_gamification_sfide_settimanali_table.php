<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_sfide_settimanali', function (Blueprint $table) {
            $table->increments('id_sfida');

            $table->char('id_utente', 36);
            $table->foreign('id_utente')
                ->references('id_utente')->on('utenti')
                ->onDelete('cascade');

            $table->string('codice_sfida', 40);

            $table->decimal('target',    10, 3);
            $table->decimal('progresso', 10, 3)->default(0);

            $table->dateTime('data_inizio');
            $table->dateTime('data_fine');

            $table->enum('stato', ['attiva', 'completata', 'fallita'])->default('attiva');

            $table->timestamps();

            $table->index(['id_utente', 'stato']);
            $table->index(['data_inizio', 'data_fine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_sfide_settimanali');
    }
};
