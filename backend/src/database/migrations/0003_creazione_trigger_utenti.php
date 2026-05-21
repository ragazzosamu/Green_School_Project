<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Esegui le migrazioni.
     */
    public function up(): void
    {
        // --- 1. Trigger per UUID Utenti ---
        DB::unprepared("DROP TRIGGER IF EXISTS trg_set_utenti_uuid_ins");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_utenti_uuid_ins
            BEFORE INSERT ON utenti
            FOR EACH ROW
            BEGIN
                IF NEW.id_utente IS NULL OR NEW.id_utente = '' THEN
                    SET NEW.id_utente = UUID();
                END IF;
            END;
        SQL);

        // --- 2. Trigger Coordinate Stazioni (Insert) ---
        // id_stazione = MAC ADDRESS passato dall'IoT, niente auto-UUID.
        // lat/lng sono NULLABLE durante stato_setup='in_setup': la
        // coordinata si calcola SOLO quando entrambe sono valorizzate
        // (POINT(NULL, NULL) farebbe fallire l'INSERT).
        DB::unprepared("DROP TRIGGER IF EXISTS trg_set_stazioni_coordinata_ins");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_stazioni_coordinata_ins
            BEFORE INSERT ON stazioni
            FOR EACH ROW
            BEGIN
                IF NEW.longitudine IS NOT NULL AND NEW.latitudine IS NOT NULL THEN
                    SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
                END IF;
            END;
        SQL);

        // --- 3. Trigger Coordinate Stazioni (Update) ---
        DB::unprepared("DROP TRIGGER IF EXISTS trg_set_stazioni_coordinata_upd");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_stazioni_coordinata_upd
            BEFORE UPDATE ON stazioni
            FOR EACH ROW
            BEGIN
                IF NEW.longitudine IS NOT NULL AND NEW.latitudine IS NOT NULL
                   AND (NEW.longitudine <=> OLD.longitudine = 0
                        OR NEW.latitudine  <=> OLD.latitudine  = 0) THEN
                    SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
                END IF;
            END;
        SQL);

        // --- 4. (rimosso) Trigger auto-UUID per punti_ricarica ---
        // id_punto e' ora un numero locale alla stazione ("1", "2", ...) e
        // viene sempre passato esplicitamente da IoT/admin: niente auto-UUID.
        DB::unprepared("DROP TRIGGER IF EXISTS trg_set_punti_uuid_ins");

        // --- 5. Trigger per UUID Sessioni ---
        DB::unprepared("DROP TRIGGER IF EXISTS trg_set_sessioni_uuid_ins");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_sessioni_uuid_ins
            BEFORE INSERT ON sessioni_ricarica
            FOR EACH ROW
            BEGIN
                IF NEW.id_sessione IS NULL OR NEW.id_sessione = '' THEN
                    SET NEW.id_sessione = UUID();
                END IF;
            END;
        SQL);

        // --- 6. Check Sessione Aperta ---
        // id_punto e' locale alla stazione: il filtro DEVE includere
        // entrambe le colonne, altrimenti due stazioni diverse con punto '1'
        // si bloccherebbero a vicenda.
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_sessione_aperta");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_check_sessione_aperta
            BEFORE INSERT ON sessioni_ricarica
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                SELECT COUNT(*) INTO v_count
                FROM sessioni_ricarica
                WHERE id_stazione = NEW.id_stazione
                  AND id_punto    = NEW.id_punto
                  AND data_fine IS NULL;

                IF v_count > 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Punto di ricarica gia occupato da una sessione attiva';
                END IF;
            END;
        SQL);

        // --- 7. Update Heartbeat dopo Sessione ---
        // Stesso discorso: filtro su (id_stazione, id_punto) per non
        // aggiornare lo stesso id_punto su tutte le stazioni.
        DB::unprepared("DROP TRIGGER IF EXISTS trg_update_heartbeat_after_session");
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_update_heartbeat_after_session
            AFTER INSERT ON sessioni_ricarica
            FOR EACH ROW
            BEGIN
                UPDATE punti_ricarica
                SET data_ultimo_heartbeat = NOW()
                WHERE id_stazione = NEW.id_stazione
                  AND id_punto    = NEW.id_punto;
            END;
        SQL);
    }

    /**
     * Inverti le migrazioni.
     */
    public function down(): void
    {
        $triggers = [
            'trg_set_utenti_uuid_ins',
            'trg_set_stazioni_coordinata_ins',
            'trg_set_stazioni_coordinata_upd',
            'trg_set_punti_uuid_ins',
            'trg_set_sessioni_uuid_ins',
            'trg_check_sessione_aperta',
            'trg_update_heartbeat_after_session',
        ];

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};