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
        // 1. Trigger per UUID Utenti
        // Nota: Il tuo schema accetta stringhe (es. "U001"). Questo interviene solo se il campo è vuoto.
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

        // 2. Trigger per UUID e Coordinate Stazioni (Insert)
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_stazioni_coordinata_ins
            BEFORE INSERT ON stazioni
            FOR EACH ROW
            BEGIN
                IF NEW.id_stazione IS NULL OR NEW.id_stazione = '' THEN
                    SET NEW.id_stazione = UUID();
                END IF;
                SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
            END;
        SQL);

        // 3. Trigger per Coordinate Stazioni (Update)
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_stazioni_coordinata_upd
            BEFORE UPDATE ON stazioni
            FOR EACH ROW
            BEGIN
                IF NEW.longitudine <> OLD.longitudine OR NEW.latitudine <> OLD.latitudine THEN
                    SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
                END IF;
            END;
        SQL);

        // 4. Trigger per UUID Punti Ricarica
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_punti_uuid_ins
            BEFORE INSERT ON punti_ricarica
            FOR EACH ROW
            BEGIN
                IF NEW.id_punto IS NULL OR NEW.id_punto = '' THEN
                    SET NEW.id_punto = UUID();
                END IF;
            END;
        SQL);

        // 5. Trigger per UUID Sessioni (Paracadute per Eloquent, la SP lo fa già)
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

        // 6. Trigger per UUID Accumulatori (PULITO DAL CALCOLO ERRATO)
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_set_accumulatori_uuid_ins
            BEFORE INSERT ON accumulatori_stazione
            FOR EACH ROW
            BEGIN
                IF NEW.id_accumulatore IS NULL OR NEW.id_accumulatore = '' THEN
                    SET NEW.id_accumulatore = UUID();
                END IF;
            END;
        SQL);

        // 7. NUOVO TRIGGER: Calcolo Percentuale da Storico Batteria
        // Questo fa la vera magia: quando un sensore inserisce i kWh attuali nello storico,
        // calcola la percentuale basandosi sulla capacita_totale_kwh e aggiorna l'accumulatore.
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_aggiorna_percentuale_accumulatore
            AFTER INSERT ON storico_livello_batteria
            FOR EACH ROW
            BEGIN
                UPDATE accumulatori_stazione
                SET percentuale_carica = ROUND((NEW.livello_kwh / NULLIF(capacita_totale_kwh, 0)) * 100, 2)
                WHERE id_accumulatore = NEW.id_accumulatore;
            END;
        SQL);

        // 8. Trigger per Check Giorno Tariffe (Insert)
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_check_tariffa_giorno_ins
            BEFORE INSERT ON tariffe_orarie
            FOR EACH ROW
            BEGIN
                IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'giorno_settimana deve essere tra 0 e 6';
                END IF;
            END;
        SQL);

        // 9. Trigger per Check Giorno Tariffe (Update)
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_check_tariffa_giorno_upd
            BEFORE UPDATE ON tariffe_orarie
            FOR EACH ROW
            BEGIN
                IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'giorno_settimana deve essere tra 0 e 6';
                END IF;
            END;
        SQL);

        // 10. Trigger per Check Sessione Aperta
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_check_sessione_aperta
            BEFORE INSERT ON sessioni_ricarica
            FOR EACH ROW
            BEGIN
                DECLARE v_count INT;
                SELECT COUNT(*) INTO v_count
                FROM sessioni_ricarica
                WHERE id_punto = NEW.id_punto
                  AND data_fine IS NULL;
             
                IF v_count > 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Punto di ricarica gia occupato da una sessione attiva';
                END IF;
            END;
        SQL);

        // 11. Trigger per Update Heartbeat dopo Sessione
        DB::unprepared(<<<SQL
            CREATE TRIGGER trg_update_heartbeat_after_session
            AFTER INSERT ON sessioni_ricarica
            FOR EACH ROW
            BEGIN
                UPDATE punti_ricarica
                SET data_ultimo_heartbeat = NOW()
                WHERE id_punto = NEW.id_punto;
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
            'trg_set_accumulatori_uuid_ins',
            'trg_aggiorna_percentuale_accumulatore', // <--- Aggiornato nel down()
            'trg_check_tariffa_giorno_ins',
            'trg_check_tariffa_giorno_upd',
            'trg_check_sessione_aperta',
            'trg_update_heartbeat_after_session',
        ];

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};