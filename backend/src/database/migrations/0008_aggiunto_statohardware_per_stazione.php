<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stazioni.data_ultimo_heartbeat: aggiornato ad ogni heartbeat MQTT proveniente
 *                                 da uno qualsiasi dei punti della stazione.
 * stazioni.in_manutenzione:       flag manuale impostato dall'admin (toggle).
 *                                 Quando true Laravel pubblica MQTT
 *                                 stazione/{mac}/manutenzione e la stazione
 *                                 deve fermarsi (offline).
 *
 * NB: lo "stato online/offline" della stazione NON e' una colonna ma viene
 * derivato a runtime con una query: stazione e' online se ha almeno un
 * punto_ricarica.stato_hardware = 'online' e in_manutenzione = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->timestamp('data_ultimo_heartbeat')->nullable()->after('libera');
            $table->boolean('in_manutenzione')->default(false)->after('data_ultimo_heartbeat');
        });
    }

    public function down(): void
    {
        Schema::table('stazioni', function (Blueprint $table) {
            $table->dropColumn(['data_ultimo_heartbeat', 'in_manutenzione']);
        });
    }
};
