<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('punti_ricarica', function (Blueprint $table) {
            $table->string('token', 64)->unique()->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('punti_ricarica', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};