<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punti_ricarica', function (Blueprint $table) {
            $table->boolean('libera')
                  ->default(true)
                  ->after('stato_hardware');

            $table->timestamp('data_ultima_misurazione')
                  ->nullable()
                  ->after('data_ultimo_heartbeat');
        });
    }

    public function down(): void
    {
        Schema::table('punti_ricarica', function (Blueprint $table) {
            $table->dropColumn([
                'libera',
                'data_ultima_misurazione',
            ]);
        });
    }
};