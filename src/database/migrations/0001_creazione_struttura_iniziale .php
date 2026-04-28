<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- Tabella UTENTI: Gestione anagrafica e tipi account ---
        Schema::create('utenti', function (Blueprint $table) {
            $table->uuid('id_utente')->primary(); // Identificativo unico UUID (Chiave Primaria)
            $table->string('email', 255)->unique(); // Email unica per login (Indicizzata automaticamente)
            $table->string('cellulare', 20)->index(); // Cellulare per contatti/SMS (Indice manuale per ricerche)
            $table->string('nome', 100); // Nome dell'utente
            $table->string('cognome', 100); // Cognome dell'utente
            $table->timestamp('data_registrazione')->useCurrent(); // Data iscrizione automatica
            $table->enum('tipo_account', ['completo', 'badge_anonimo', 'ospite'])->default('completo'); // Livello di profilazione
            $table->boolean('attivo')->default(true); // Flag per disabilitazione rapida account
        });

        // --- Tabella STAZIONI: Luoghi fisici dove risiedono i punti di ricarica ---
        Schema::create('stazioni', function (Blueprint $table) {
            $table->uuid('id_stazione')->primary(); // Identificativo unico stazione
            $table->string('nome', 100); // Nome descrittivo (es. "Stazione Centrale")
            $table->text('indirizzo')->nullable(); // Indirizzo fisico completo
            $table->decimal('latitudine', 10, 8); // Coordinata GPS Lat (Precisione 8 decimali)
            $table->decimal('longitudine', 11, 8); // Coordinata GPS Lon (Precisione 8 decimali)
            $table->point('coordinata'); // Oggetto spaziale per query geografiche (Point)
            $table->enum('tipo_area', ['pubblico', 'privato', 'aziendale'])->default('pubblico'); // Accessibilità
            $table->date('data_attivazione')->useCurrent(); // Data di messa in funzione

            // Indici per performance geografiche
            $table->index(['latitudine', 'longitudine'], 'idx_stazioni_coordinate'); // Indice per filtri box
            $table->spatialIndex('coordinata', 'idx_stazioni_geo'); // Indice spaziale per distanze radiali
        });

        // --- Tabella BADGE_UTENTE: Associazione tessere RFID fisiche agli utenti ---
        Schema::create('badge_utente', function (Blueprint $table) {
            $table->increments('id_badge'); // Chiave primaria incrementale
            $table->foreignUuid('id_utente')->constrained('utenti', 'id_utente')->onDelete('cascade'); // Relazione utente (Elimina badge se utente rimosso)
            $table->string('codice_rfid', 100)->unique(); // Codice univoco letto dal chip fisico
            $table->string('nome_badge', 50)->default('Principale'); // Etichetta mnemonica (es. "Mio Badge")
            $table->timestamp('data_attivazione')->useCurrent(); // Data di prima attivazione del badge
            $table->boolean('bloccato')->default(false); // Flag per smarrimento o sospensione
        });

        // --- Tabella PUNTI_RICARICA: Le singole colonnine/prese nelle stazioni ---
        Schema::create('punti_ricarica', function (Blueprint $table) {
            $table->uuid('id_punto')->primary(); // Identificativo unico del punto ricarica
            $table->foreignUuid('id_stazione')->constrained('stazioni', 'id_stazione')->onDelete('cascade'); // Link alla stazione madre
            $table->string('identificativo_fisico', 50)->nullable(); // Etichetta fisica (es. "Presa A")
            $table->enum('tipo_veicolo', ['auto', 'bici', 'monopattino']); // Target veicolo
            $table->string('tipo_connettore', 30)->nullable(); // Es: Type 2, CCS, Schuko
            $table->decimal('potenza_max_kw', 6, 2)->nullable(); // Potenza massima erogabile
            $table->enum('stato_hardware', ['online', 'offline', 'guasto', 'manutenzione_programmata'])->default('online')->index(); // Stato in tempo reale
            $table->timestamp('data_ultimo_heartbeat')->nullable()->index(); // Ultimo segnale "vita" ricevuto dal server
            $table->decimal('tariffa_predefinita', 6, 4)->nullable(); // Prezzo base se non ci sono fasce orarie
            $table->longText('metodi_autenticazione_supportati')->nullable(); // JSON o testo dei metodi (App, RFID, SMS)
        });

        // --- Tabella TARIFFE_ORARIE: Fasce di prezzo dinamiche per punto ricarica ---
        Schema::create('tariffe_orarie', function (Blueprint $table) {
            $table->increments('id_tariffa'); // ID unico tariffa
            $table->foreignUuid('id_punto')->constrained('punti_ricarica', 'id_punto')->onDelete('cascade'); // Link al punto ricarica specifico
            $table->tinyInteger('giorno_settimana'); // 0 (Dom) - 6 (Sab)
            $table->time('ora_inizio'); // Orario inizio validità
            $table->time('ora_fine'); // Orario fine validità
            $table->decimal('prezzo_kwh', 6, 4); // Costo per kWh (Alta precisione)
            $table->date('data_attivazione'); // Da quando la tariffa entra in vigore
            $table->date('data_scadenza')->nullable(); // Eventuale fine validità

            // Indice per calcolare velocemente il prezzo al momento del plug-in
            $table->index(['id_punto', 'giorno_settimana', 'ora_inizio'], 'idx_tariffe_periodo');
        });

        // --- Tabella SESSIONI_RICARICA: Registro storico e attivo delle ricariche ---
        Schema::create('sessioni_ricarica', function (Blueprint $table) {
            $table->uuid('id_sessione')->primary(); // ID univoco sessione
            $table->foreignUuid('id_utente')->nullable()->constrained('utenti', 'id_utente')->onDelete('set null'); // Link utente (Scollegato ma non eliminato se utente rimosso)
            $table->foreignUuid('id_punto')->constrained('punti_ricarica', 'id_punto')->onDelete('cascade'); // Link punto ricarica
            $table->integer('id_badge_usato')->unsigned()->nullable()->index(); // Badge fisico utilizzato
            $table->foreign('id_badge_usato')->references('id_badge')->on('badge_utente')->onDelete('set null'); // Link al badge

            $table->enum('metodo_avvio', [ 'RFID', 'QR_CODE',]) ->default('QR_CODE'); // Modalità di sblocco
            $table->timestamp('data_inizio')->useCurrent(); // Inizio sessione
            $table->timestamp('data_fine')->nullable(); // Fine sessione (NULL se in corso)
            $table->decimal('quantita_kwh', 10, 3)->nullable(); // Energia totale erogata
            $table->decimal('costo_totale', 10, 2)->default(0.00); // Importo finale calcolato
            $table->enum('stato_pagamento', ['non_richiesto', 'in_attesa_pagamento', 'completato', 'fallito', 'gratuito'])->default('non_richiesto'); // Stato transazione

            // Indici per dashboard "Live" e storico utente
            $table->index(['id_punto', 'data_fine'], 'idx_sessioni_aperte'); // Trova sessioni attive su una colonnina
            $table->index(['id_utente', 'data_inizio'], 'idx_sessioni_utente'); // Trova cronologia utente
        });

        // --- Tabella ACCUMULATORI_STAZIONE: Sistemi di storage energetico (Batterie locali) ---
        Schema::create('accumulatori_stazione', function (Blueprint $table) {
            $table->uuid('id_accumulatore')->primary(); // ID accumulatore
            $table->foreignUuid('id_stazione')->constrained('stazioni', 'id_stazione')->onDelete('cascade'); // Stazione a cui è collegato
            $table->string('nome', 50)->default('Accumulatore Principale'); // Nome batteria
            $table->decimal('capacita_totale_kwh', 10, 2); // Capacità nominale (es. 100 kWh)
            $table->decimal('percentuale_carica', 5, 2)->default(0.00)->index(); // SoC (State of Charge) attuale
            $table->enum('stato_operativo', ['carica', 'scarica', 'standby', 'guasto', 'manutenzione'])->default('standby'); // Stato attività
            $table->timestamp('data_ultimo_aggiornamento')->useCurrent()->useCurrentOnUpdate(); // Auto-update del timestamp alla modifica
        });

        // --- Tabella STORICO_LIVELLO_BATTERIA: Serie storica per telemetria e grafici ---
        Schema::create('storico_livello_batteria', function (Blueprint $table) {
            $table->bigIncrements('id_misurazione'); // ID misurazione (BigInt per moli dati elevate)
            $table->foreignUuid('id_accumulatore')->constrained('accumulatori_stazione', 'id_accumulatore')->onDelete('cascade'); // Link accumulatore
            $table->timestamp('timestamp_misurazione')->useCurrent(); // Quando è stata fatta la lettura
            $table->decimal('livello_kwh', 10, 2); // Energia presente in quel momento

            // Indice temporale per generare grafici velocemente
            $table->index(['id_accumulatore', 'timestamp_misurazione'], 'idx_storico_batteria_tempo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminazione delle tabelle in ordine inverso rispetto alla creazione per evitare errori di vincolo (Foreign Keys)
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