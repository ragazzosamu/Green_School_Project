<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_badge_catalogo', function (Blueprint $table) {
            $table->smallIncrements('id_badge');
            $table->string('codice', 40)->unique();
            $table->string('nome', 100);
            $table->text('descrizione');
            $table->string('icona_emoji', 10);
            $table->json('condizione_json'); // regola di sblocco machine-readable
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_badge_catalogo');
    }
};
