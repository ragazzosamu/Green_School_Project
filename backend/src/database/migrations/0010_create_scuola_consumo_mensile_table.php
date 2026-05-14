<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scuola_consumo_mensile', function (Blueprint $table) {
            $table->increments('id_consumo');

            $table->unsignedSmallInteger('id_scuola');
            $table->foreign('id_scuola')
                ->references('id_scuola')->on('scuola_profilo')
                ->onDelete('cascade');

            $table->year('anno');
            $table->unsignedTinyInteger('mese'); // 1..12

            $table->decimal('consumo_elettrico_kwh', 10, 2)->default(0);
            $table->decimal('consumo_termico_kwh',   10, 2)->default(0);
            $table->decimal('produzione_fv_kwh',     10, 2)->default(0);
            $table->decimal('co2_emessa_kg',         10, 2)->default(0);

            $table->timestamps();

            $table->unique(['id_scuola', 'anno', 'mese'], 'uq_scuola_anno_mese');
            $table->index(['anno', 'mese']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scuola_consumo_mensile');
    }
};
