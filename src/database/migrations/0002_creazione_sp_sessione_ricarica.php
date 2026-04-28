<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        // 2. Creazione procedura VERIFICA DISPONIBILITÀ
        DB::unprepared("
            CREATE PROCEDURE sp_verifica_disponibilita(
                IN p_id_punto CHAR(36),
                OUT p_disponibile TINYINT(1),
                OUT p_messaggio VARCHAR(255)
            )
            BEGIN
                DECLARE v_stato_hardware VARCHAR(30);
                DECLARE v_heartbeat TIMESTAMP;
                DECLARE v_sessione_attiva CHAR(36);
                DECLARE v_occupante_nome VARCHAR(100);
                DECLARE v_no_data INT DEFAULT 0;

                DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_no_data = 1;

                SELECT stato_hardware, data_ultimo_heartbeat
                INTO v_stato_hardware, v_heartbeat
                FROM punti_ricarica
                WHERE id_punto = p_id_punto
                FOR UPDATE;

                IF v_no_data = 1 THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Punto di ricarica non trovato';
                ELSEIF v_stato_hardware IN ('guasto', 'manutenzione_programmata') THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Colonnina fuori servizio';
                ELSEIF v_heartbeat IS NULL OR v_heartbeat < (NOW() - INTERVAL 5 MINUTE) THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Colonnina offline o non raggiungibile';
                ELSE
                    SET v_no_data = 0;
                    SELECT sr.id_sessione, u.nome
                    INTO v_sessione_attiva, v_occupante_nome
                    FROM sessioni_ricarica sr
                    LEFT JOIN utenti u ON sr.id_utente = u.id_utente
                    WHERE sr.id_punto = p_id_punto AND sr.data_fine IS NULL
                    LIMIT 1;

                    IF v_sessione_attiva IS NOT NULL THEN
                        SET p_disponibile = 0;
                        SET p_messaggio = CONCAT('Colonnina occupata', IF(v_occupante_nome IS NOT NULL, CONCAT(' da ', v_occupante_nome), ''));
                    ELSE
                        p_disponibile = 1;
                        SET p_messaggio = 'Colonnina disponibile';
                    END IF;
                END IF;
            END
        ");

        // 3. Creazione procedura AVVIO SESSIONE
        DB::unprepared("
            CREATE PROCEDURE sp_avvio_sessione(
                IN p_id_utente CHAR(36),
                IN p_id_punto CHAR(36),
                IN p_metodo_avvio VARCHAR(20),
                IN p_id_badge INT,
                OUT p_id_sessione CHAR(36),
                OUT p_successo TINYINT(1),
                OUT p_messaggio VARCHAR(255)
            )
            BEGIN
                DECLARE v_disponibile TINYINT(1);
                DECLARE v_batteria_sufficiente TINYINT(1) DEFAULT 1;

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_successo = 0;
                    SET p_messaggio = 'Errore durante l''avvio della sessione';
                END;

                START TRANSACTION;
                CALL sp_verifica_disponibilita(p_id_punto, v_disponibile, p_messaggio);

                IF v_disponibile = 0 THEN
                    SET p_successo = 0;
                    ROLLBACK;
                ELSE
                    SELECT (acc.percentuale_carica > acc.soglia_minima_perc OR acc.id_accumulatore IS NULL)
                    INTO v_batteria_sufficiente
                    FROM punti_ricarica p
                    LEFT JOIN stazioni s ON p.id_stazione = s.id_stazione
                    LEFT JOIN accumulatori_stazione acc ON s.id_stazione = acc.id_stazione
                    WHERE p.id_punto = p_id_punto;

                    IF v_batteria_sufficiente = 0 THEN
                        SET p_successo = 0;
                        SET p_messaggio = 'Batteria della stazione scarica. Ricarica non disponibile.';
                        ROLLBACK;
                    ELSE
                        SET p_id_sessione = UUID();
                        INSERT INTO sessioni_ricarica (
                            id_sessione, id_utente, id_punto, id_badge_usato, metodo_avvio, data_inizio
                        ) VALUES (
                            p_id_sessione, p_id_utente, p_id_punto, p_id_badge, p_metodo_avvio, NOW()
                        );
                        SET p_successo = 1;
                        SET p_messaggio = 'Sessione avviata con successo';
                        COMMIT;
                    END IF;
                END IF;
            END
        ");

        // 4. Creazione procedura TERMINA SESSIONE
        DB::unprepared("
            CREATE PROCEDURE sp_termina_sessione(
                IN p_id_sessione CHAR(36),
                IN p_quantita_kwh DECIMAL(10,3),
                OUT p_successo TINYINT(1),
                OUT p_costo_calcolato DECIMAL(10,2)
            )
            BEGIN
                DECLARE v_id_punto CHAR(36);
                DECLARE v_tariffa DECIMAL(6,4);
                DECLARE v_data_inizio TIMESTAMP;
                DECLARE v_tipo_tariffa VARCHAR(30);
                DECLARE v_stato_pagamento VARCHAR(30);

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_successo = 0;
                    SET p_costo_calcolato = 0;
                END;

                START TRANSACTION;

                SELECT id_punto, data_inizio INTO v_id_punto, v_data_inizio
                FROM sessioni_ricarica
                WHERE id_sessione = p_id_sessione AND data_fine IS NULL
                FOR UPDATE;

                IF v_id_punto IS NULL THEN
                    SET p_successo = 0;
                    ROLLBACK;
                ELSE
                    SELECT tariffa_predefinita INTO v_tariffa
                    FROM punti_ricarica WHERE id_punto = v_id_punto;

                    IF v_tariffa IS NULL THEN
                        SELECT prezzo_kwh INTO v_tariffa
                        FROM tariffe_orarie
                        WHERE id_punto = v_id_punto
                          AND giorno_settimana = DAYOFWEEK(v_data_inizio) - 1
                          AND TIME(v_data_inizio) BETWEEN ora_inizio AND ora_fine
                          AND data_attivazione <= DATE(v_data_inizio)
                          AND (data_scadenza IS NULL OR data_scadenza >= DATE(v_data_inizio))
                        ORDER BY data_attivazione DESC
                        LIMIT 1;

                        IF v_tariffa IS NULL THEN
                            SET v_tariffa = 0.50;
                        END IF;
                    END IF;

                    IF v_tariffa = 0 THEN
                        SET v_tipo_tariffa = 'gratuita_struttura';
                        SET p_costo_calcolato = 0;
                        SET v_stato_pagamento = 'gratuito';
                    ELSE
                        SET v_tipo_tariffa = 'standard';
                        SET p_costo_calcolato = ROUND(p_quantita_kwh * v_tariffa, 2);
                        SET v_stato_pagamento = 'in_attesa_pagamento';
                    END IF;

                    UPDATE sessioni_ricarica
                    SET data_fine = NOW(),
                        quantita_kwh = p_quantita_kwh,
                        tipo_tariffa_applicata = v_tipo_tariffa,
                        costo_totale = p_costo_calcolato,
                        stato_pagamento = v_stato_pagamento,
                        motivazione_gratuita = IF(v_tipo_tariffa = 'gratuita_struttura', 'Tariffa punto impostata a zero', NULL)
                    WHERE id_sessione = p_id_sessione;

                    SET p_successo = 1;
                    COMMIT;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_verifica_disponibilita");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_avvio_sessione");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_termina_sessione");
    }
};


?>