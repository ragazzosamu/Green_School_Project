<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scuola_profilo', function (Blueprint $table) {
            $table->smallIncrements('id_scuola');
            $table->string('denominazione', 150);
            $table->year('anno_costruzione')->nullable();
            $table->decimal('superficie_mq', 10, 2)->nullable();
            $table->enum('classe_energetica', ['A4', 'A3', 'A2', 'A1', 'B', 'C', 'D', 'E', 'F', 'G'])->nullable();
            $table->json('interventi_efficientamento')->nullable();
            $table->decimal('fotovoltaico_kwp', 7, 2)->nullable();
            $table->text('descrizione')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scuola_profilo');
    }
};
