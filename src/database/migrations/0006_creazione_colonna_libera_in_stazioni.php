<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->boolean('libera')
                  ->default(true)
                  ->after('stato_hardware');

        });
    }

    public function down(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->dropColumn([
                'libera',
            ]);
        });
    }
};