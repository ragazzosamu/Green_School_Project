<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_verifica_disponibilita");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_avvio_sessione");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_termina_sessione");

        // 1. VERIFICA DISPONIBILITA' (su chiave composta id_stazione,id_punto)
        DB::unprepared("
            CREATE PROCEDURE sp_verifica_disponibilita(
                IN p_id_stazione VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_id_punto VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                OUT p_disponibile TINYINT(1),
                OUT p_messaggio VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            )
            BEGIN
                DECLARE v_stato_hardware VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                DECLARE v_heartbeat TIMESTAMP;
                DECLARE v_libera TINYINT(1);
                DECLARE v_no_data INT DEFAULT 0;

                DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_no_data = 1;

                SELECT stato_hardware, data_ultimo_heartbeat, libera
                INTO v_stato_hardware, v_heartbeat, v_libera
                FROM punti_ricarica
                WHERE id_stazione = p_id_stazione AND id_punto = p_id_punto
                LIMIT 1;

                IF v_no_data = 1 THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Punto di ricarica non trovato';
                ELSEIF v_stato_hardware IN ('guasto', 'manutenzione_programmata') THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Colonnina fuori servizio';
                ELSEIF v_heartbeat IS NULL OR v_heartbeat < (NOW() - INTERVAL 5 MINUTE) THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Colonnina offline o non raggiungibile';
                ELSEIF v_libera = 0 THEN
                    SET p_disponibile = 0;
                    SET p_messaggio = 'Colonnina occupata';
                ELSE
                    SET p_disponibile = 1;
                    SET p_messaggio = 'Colonnina disponibile';
                END IF;
            END
        ");

        // 2. AVVIO SESSIONE
        DB::unprepared("
            CREATE PROCEDURE sp_avvio_sessione(
                IN p_id_utente CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_id_stazione VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_id_punto VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_metodo_avvio VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_id_badge INT,
                OUT p_id_sessione CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                OUT p_successo TINYINT(1),
                OUT p_messaggio VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            )
            BEGIN
                DECLARE v_disponibile TINYINT(1);

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    DECLARE _sqlstate CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                    DECLARE _msg TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                    GET DIAGNOSTICS CONDITION 1 _sqlstate = RETURNED_SQLSTATE, _msg = MESSAGE_TEXT;
                    ROLLBACK;
                    SET p_successo = 0;
                    SET p_messaggio = CONCAT('Errore SQL: ', COALESCE(_msg, 'Sconosciuto')) COLLATE utf8mb4_unicode_ci;
                END;

                START TRANSACTION;
                CALL sp_verifica_disponibilita(p_id_stazione, p_id_punto, v_disponibile, p_messaggio);

                IF v_disponibile = 0 THEN
                    SET p_successo = 0;
                    ROLLBACK;
                ELSE
                    SET p_id_sessione = UUID();
                    INSERT INTO sessioni_ricarica
                        (id_sessione, id_utente, id_stazione, id_punto, id_badge_usato, metodo_avvio, data_inizio, stato_pagamento)
                    VALUES
                        (p_id_sessione, p_id_utente, p_id_stazione, p_id_punto, p_id_badge, p_metodo_avvio, NOW(), 'non_richiesto');

                    UPDATE punti_ricarica
                    SET libera = 0
                    WHERE id_stazione = p_id_stazione AND id_punto = p_id_punto;

                    SET p_successo = 1;
                    SET p_messaggio = 'Sessione avviata';
                    COMMIT;
                END IF;
            END
        ");

        // 3. TERMINA SESSIONE
        DB::unprepared("
            CREATE PROCEDURE sp_termina_sessione(
                IN p_id_sessione CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                IN p_quantita_kwh DECIMAL(10,3),
                OUT p_successo TINYINT(1),
                OUT p_costo_calcolato DECIMAL(10,2)
            )
            BEGIN
                DECLARE v_id_stazione VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                DECLARE v_id_punto VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                DECLARE v_tariffa DECIMAL(6,4);
                DECLARE v_data_inizio TIMESTAMP;

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    SET p_successo = 0;
                END;

                START TRANSACTION;
                SELECT id_stazione, id_punto, data_inizio
                INTO v_id_stazione, v_id_punto, v_data_inizio
                FROM sessioni_ricarica
                WHERE id_sessione = p_id_sessione AND data_fine IS NULL
                FOR UPDATE;

                IF v_id_punto IS NULL THEN
                    SET p_successo = 0;
                    ROLLBACK;
                ELSE
                    SELECT tariffa_predefinita INTO v_tariffa
                    FROM punti_ricarica
                    WHERE id_stazione = v_id_stazione AND id_punto = v_id_punto
                    LIMIT 1;

                    IF v_tariffa IS NULL THEN SET v_tariffa = 0.50; END IF;

                    SET p_costo_calcolato = ROUND(p_quantita_kwh * v_tariffa, 2);
                    UPDATE sessioni_ricarica
                    SET data_fine = NOW(),
                        quantita_kwh = p_quantita_kwh,
                        costo_totale = p_costo_calcolato,
                        stato_pagamento = IF(p_costo_calcolato > 0, 'in_attesa_pagamento', 'gratuito')
                    WHERE id_sessione = p_id_sessione;

                    UPDATE punti_ricarica
                    SET libera = 1
                    WHERE id_stazione = v_id_stazione AND id_punto = v_id_punto;

                    SET p_successo = 1;
                    COMMIT;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_verifica_disponibilita");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_avvio_sessione");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_termina_sessione");
    }
};
