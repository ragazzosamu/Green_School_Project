<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->enum('stato_hardware', [
                'online',
                'offline', 
                'guasto',
                'manutenzione_programmata'
            ])->default('offline')->after('libera');
            $table->timestamp('data_ultimo_heartbeat')->nullable()->after('stato_hardware');
        });
    }

    public function down(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->dropColumn([
                'stato_hardware',
                'data_ultimo_heartbeat',
            ]);
        });
    }
};