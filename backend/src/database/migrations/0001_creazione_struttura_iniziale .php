<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabella UTENTI ---
        Schema::create('utenti', function (Blueprint $table) {
            $table->uuid('id_utente')->primary(); 
            $table->string('email', 255)->unique();
            $table->string('password'); // <--- AGGIUNTO PER SANCTUM
            $table->string('cellulare', 20)->index();
            $table->string('nome', 100);
            $table->string('cognome', 100);
            $table->timestamp('data_registrazione')->useCurrent();
            $table->enum('tipo_account', ['completo', 'badge_anonimo', 'ospite'])->default('completo');
            $table->boolean('attivo')->default(true);
            $table->rememberToken(); // <--- AGGIUNTO PER LARAVEL AUTH
        });

        // --- Tabella STAZIONI ---
        // id_stazione = MAC ADDRESS della colonnina (es. "AA:BB:CC:DD:EE:FF").
        // Al primo POST /api/iot/registra (con MAC + password globale) la stazione
        // viene creata con stato_setup='in_setup'. L'admin completa nome/indirizzo/
        // coordinate/punti dal pannello e con "attiva" passa a stato_setup='attiva'
        // e Laravel pubblica MQTT stazione/{mac}/ready. Solo da lì la stazione
        // inizia a generare codici monouso e heartbeat.
        Schema::create('stazioni', function (Blueprint $table) {
            $table->string('id_stazione', 32)->primary();
            $table->string('password_hash')->nullable(); // hash della password specifica della stazione (post-registrazione)
            $table->enum('stato_setup', ['in_setup', 'attiva'])->default('in_setup')->index();
            $table->string('nome', 100)->nullable();
            $table->text('indirizzo')->nullable();
            $table->decimal('latitudine', 10, 8)->nullable();
            $table->decimal('longitudine', 11, 8)->nullable();
            $table->geometry('coordinata')->nullable();
            $table->enum('tipo_area', ['pubblico', 'privato', 'aziendale'])->default('pubblico');
            $table->date('data_attivazione')->nullable();
            $table->index(['latitudine', 'longitudine'], 'idx_stazioni_coordinate');
        });

        // --- Tabella BADGE_UTENTE ---
        Schema::create('badge_utente', function (Blueprint $table) {
            $table->increments('id_badge');
            $table->string('id_utente');
            $table->foreign('id_utente')->references('id_utente')->on('utenti')->onDelete('cascade');
            $table->string('codice_rfid', 100)->unique();
            $table->string('nome_badge', 50)->default('Principale');
            $table->timestamp('data_attivazione')->useCurrent();
            $table->boolean('bloccato')->default(false);
        });

        // --- Tabella PUNTI_RICARICA ---
        // Chiave composta: (id_stazione, id_punto). id_punto e' "1", "2", ...
        // ovvero il numero locale del connettore sulla stazione.
        Schema::create('punti_ricarica', function (Blueprint $table) {
            $table->string('id_stazione', 32);
            $table->string('id_punto', 8);
            $table->primary(['id_stazione', 'id_punto']);
            $table->foreign('id_stazione')->references('id_stazione')->on('stazioni')->onDelete('cascade');
            $table->string('identificativo_fisico', 50)->nullable();
            $table->enum('tipo_veicolo', ['auto', 'bici', 'monopattino']);
            $table->string('tipo_connettore', 30)->nullable();
            $table->decimal('potenza_max_kw', 6, 2)->nullable();
            $table->enum('stato_hardware', ['online', 'offline', 'guasto', 'manutenzione_programmata'])->default('offline')->index();
            $table->timestamp('data_ultimo_heartbeat')->nullable()->index();
            $table->decimal('tariffa_predefinita', 6, 4)->nullable();
            $table->longText('metodi_autenticazione_supportati')->nullable();
        });

        // --- Tabella TARIFFE_ORARIE ---
        Schema::create('tariffe_orarie', function (Blueprint $table) {
            $table->increments('id_tariffa');
            $table->string('id_stazione', 32);
            $table->string('id_punto', 8);
            $table->foreign(['id_stazione', 'id_punto'])
                  ->references(['id_stazione', 'id_punto'])->on('punti_ricarica')->onDelete('cascade');
            $table->tinyInteger('giorno_settimana');
            $table->time('ora_inizio');
            $table->time('ora_fine');
            $table->decimal('prezzo_kwh', 6, 4);
            $table->date('data_attivazione');
            $table->date('data_scadenza')->nullable();
            $table->index(['id_stazione', 'id_punto', 'giorno_settimana', 'ora_inizio'], 'idx_tariffe_periodo');
        });

        // --- Tabella SESSIONI_RICARICA ---
        Schema::create('sessioni_ricarica', function (Blueprint $table) {
            $table->string('id_sessione')->primary();
            $table->string('id_utente')->nullable();
            $table->foreign('id_utente')->references('id_utente')->on('utenti')->onDelete('set null');
            $table->string('id_stazione', 32);
            $table->string('id_punto', 8);
            $table->foreign(['id_stazione', 'id_punto'])
                  ->references(['id_stazione', 'id_punto'])->on('punti_ricarica')->onDelete('cascade');
            $table->integer('id_badge_usato')->unsigned()->nullable()->index();
            $table->foreign('id_badge_usato')->references('id_badge')->on('badge_utente')->onDelete('set null');
            $table->enum('metodo_avvio', ['RFID', 'CODICE'])->default('CODICE');
            $table->timestamp('data_inizio')->useCurrent();
            $table->timestamp('data_fine')->nullable();
            $table->decimal('quantita_kwh', 10, 3)->nullable();
            $table->decimal('costo_totale', 10, 2)->default(0.00);
            $table->enum('stato_pagamento', ['non_richiesto', 'in_attesa_pagamento', 'completato', 'fallito', 'gratuito'])->default('non_richiesto');
            $table->index(['id_stazione', 'id_punto', 'data_fine'], 'idx_sessioni_aperte');
            $table->index(['id_utente', 'data_inizio'], 'idx_sessioni_utente');
        });

        // --- Tabella ACCUMULATORI_STAZIONE ---
        Schema::create('accumulatori_stazione', function (Blueprint $table) {
            $table->string('id_accumulatore')->primary();
            $table->string('id_stazione', 32);
            $table->foreign('id_stazione')->references('id_stazione')->on('stazioni')->onDelete('cascade');
            $table->string('nome', 50)->default('Accumulatore Principale');
            $table->decimal('capacita_totale_kwh', 10, 2);
            $table->decimal('percentuale_carica', 5, 2)->default(0.00)->index();
            $table->enum('stato_operativo', ['carica', 'scarica', 'standby', 'guasto', 'manutenzione'])->default('standby');
            $table->timestamp('data_ultimo_aggiornamento')->useCurrent()->useCurrentOnUpdate();
        });

        // --- Tabella STORICO_LIVELLO_BATTERIA ---
        Schema::create('storico_livello_batteria', function (Blueprint $table) {
            $table->bigIncrements('id_misurazione');
            $table->string('id_accumulatore');
            $table->foreign('id_accumulatore')->references('id_accumulatore')->on('accumulatori_stazione')->onDelete('cascade');
            $table->timestamp('timestamp_misurazione')->useCurrent();
            $table->decimal('livello_kwh', 10, 2);
            $table->index(['id_accumulatore', 'timestamp_misurazione'], 'idx_storico_batteria_tempo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storico_livello_batteria');
        Schema::dropIfExists('accumulatori_stazione');
        Schema::dropIfExists('sessioni_ricarica');
        Schema::dropIfExists('tariffe_orarie');
        Schema::dropIfExists('punti_ricarica');
        Schema::dropIfExists('badge_utente');
        Schema::dropIfExists('stazioni');
        Schema::dropIfExists('utenti');
    }
};