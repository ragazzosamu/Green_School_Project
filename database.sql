-- ==========================================
-- DATABASE STAZIONE RICARICA
-- Compatibile con MariaDB 10.x
-- ==========================================
 
-- ==========================================
-- 1. TABELLE
-- ==========================================
 
CREATE TABLE utenti (
    id_utente CHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    cellulare VARCHAR(20),
    nome VARCHAR(100),
    cognome VARCHAR(100),
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    tipo_account ENUM('completo', 'badge_anonimo', 'ospite') DEFAULT 'completo',
    attivo TINYINT(1) DEFAULT 1,
    INDEX idx_utenti_email (email),
    INDEX idx_utenti_cellulare (cellulare)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE badge_utente (
    id_badge INT AUTO_INCREMENT PRIMARY KEY,
    id_utente CHAR(36) NOT NULL,
    codice_rfid VARCHAR(100) UNIQUE NOT NULL,
    nome_badge VARCHAR(50) DEFAULT 'Principale',
    data_attivazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    bloccato TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE CASCADE,
    INDEX idx_badge_rfid (codice_rfid),
    INDEX idx_badge_utente (id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE stazioni (
    id_stazione CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    indirizzo TEXT,
    latitudine DECIMAL(10,8) NOT NULL,
    longitudine DECIMAL(11,8) NOT NULL,
    coordinata POINT NOT NULL,
    tipo_area ENUM('pubblico', 'privato', 'aziendale') DEFAULT 'pubblico',
    data_attivazione DATE DEFAULT CURRENT_DATE,
    INDEX idx_stazioni_coordinate (latitudine, longitudine),
    SPATIAL INDEX idx_stazioni_geo (coordinata)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE punti_ricarica (
    id_punto CHAR(36) PRIMARY KEY,
    id_stazione CHAR(36) NOT NULL,
    identificativo_fisico VARCHAR(50),
    tipo_veicolo ENUM('auto', 'bici', 'monopattino') NOT NULL,
    tipo_connettore VARCHAR(30),
    potenza_max_kw DECIMAL(6,2),
    stato_hardware ENUM('online', 'offline', 'guasto', 'manutenzione_programmata') DEFAULT 'online',
    data_ultimo_heartbeat TIMESTAMP NULL,
    tariffa_predefinita DECIMAL(6,4),
    metodi_autenticazione_supportati LONGTEXT,
    FOREIGN KEY (id_stazione) REFERENCES stazioni(id_stazione) ON DELETE CASCADE,
    INDEX idx_punti_stazione (id_stazione),
    INDEX idx_punti_heartbeat (data_ultimo_heartbeat),
    INDEX idx_punti_hardware (stato_hardware)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE tariffe_orarie (
    id_tariffa INT AUTO_INCREMENT PRIMARY KEY,
    id_punto CHAR(36) NOT NULL,
    giorno_settimana TINYINT COMMENT '0=Domenica, 6=Sabato',
    ora_inizio TIME NOT NULL,
    ora_fine TIME NOT NULL,
    prezzo_kwh DECIMAL(6,4) NOT NULL,
    data_attivazione DATE NOT NULL,
    data_scadenza DATE,
    FOREIGN KEY (id_punto) REFERENCES punti_ricarica(id_punto) ON DELETE CASCADE,
    INDEX idx_tariffe_periodo (id_punto, giorno_settimana, ora_inizio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE sessioni_ricarica (
    id_sessione CHAR(36) PRIMARY KEY,
    id_utente CHAR(36),
    id_punto CHAR(36) NOT NULL,
    id_badge_usato INT,
    metodo_avvio ENUM('APP', 'RFID', 'QR_CODE', 'ADMIN') NOT NULL,
    data_inizio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_fine TIMESTAMP NULL,
    quantita_kwh DECIMAL(10,3),
    tipo_tariffa_applicata ENUM('standard', 'gratuita_promozione', 'gratuita_abbonamento', 'gratuita_struttura', 'forfettaria') DEFAULT 'standard',
    costo_totale DECIMAL(10,2) DEFAULT 0.00,
    motivazione_gratuita TEXT,
    stato_pagamento ENUM('non_richiesto', 'in_attesa_pagamento', 'completato', 'fallito', 'gratuito') DEFAULT 'non_richiesto',
    id_transazione_pagamento VARCHAR(100),
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE SET NULL,
    FOREIGN KEY (id_punto) REFERENCES punti_ricarica(id_punto) ON DELETE CASCADE,
    FOREIGN KEY (id_badge_usato) REFERENCES badge_utente(id_badge) ON DELETE SET NULL,
    INDEX idx_sessioni_aperte (id_punto, data_fine),
    INDEX idx_sessioni_utente (id_utente, data_inizio),
    INDEX idx_sessioni_periodo (data_inizio, data_fine),
    INDEX idx_sessioni_badge (id_badge_usato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE accumulatori_stazione (
    id_accumulatore CHAR(36) PRIMARY KEY,
    id_stazione CHAR(36) NOT NULL,
    nome VARCHAR(50) DEFAULT 'Accumulatore Principale',
    capacita_totale_kwh DECIMAL(10,2) NOT NULL,
    capacita_utilizzabile_kwh DECIMAL(10,2) NOT NULL,
    potenza_max_carica_kw DECIMAL(6,2),
    potenza_max_scarica_kw DECIMAL(6,2),
    livello_corrente_kwh DECIMAL(10,2) DEFAULT 0,
    percentuale_carica DECIMAL(5,2) DEFAULT 0.00,
    stato_operativo ENUM('carica', 'scarica', 'standby', 'guasto', 'manutenzione') DEFAULT 'standby',
    data_ultimo_aggiornamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    soglia_minima_perc DECIMAL(5,2) DEFAULT 20.00,
    soglia_massima_perc DECIMAL(5,2) DEFAULT 90.00,
    FOREIGN KEY (id_stazione) REFERENCES stazioni(id_stazione) ON DELETE CASCADE,
    INDEX idx_accumulatore_stazione (id_stazione),
    INDEX idx_accumulatore_livello (percentuale_carica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
CREATE TABLE storico_livello_batteria (
    id_misurazione BIGINT AUTO_INCREMENT PRIMARY KEY,
    id_accumulatore CHAR(36) NOT NULL,
    timestamp_misurazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    livello_kwh DECIMAL(10,2) NOT NULL,
    potenza_istantanea_kw DECIMAL(7,2),
    temperatura_celsius DECIMAL(4,1),
    FOREIGN KEY (id_accumulatore) REFERENCES accumulatori_stazione(id_accumulatore) ON DELETE CASCADE,
    INDEX idx_storico_batteria_tempo (id_accumulatore, timestamp_misurazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 
-- ==========================================
-- 2. VISTE
-- ==========================================
 
CREATE OR REPLACE VIEW vw_stato_colonnine AS
SELECT
    p.id_punto,
    p.identificativo_fisico,
    p.tipo_veicolo,
    p.tipo_connettore,
    p.potenza_max_kw,
    p.tariffa_predefinita,
    s.id_stazione,
    s.nome AS nome_stazione,
    s.latitudine,
    s.longitudine,
    s.indirizzo,
    CASE
        WHEN p.stato_hardware IN ('guasto', 'manutenzione_programmata') THEN 'fuori_servizio'
        WHEN p.data_ultimo_heartbeat IS NULL THEN 'offline'
        WHEN p.data_ultimo_heartbeat < (NOW() - INTERVAL 5 MINUTE) THEN 'offline'
        WHEN sess.id_sessione IS NOT NULL THEN 'occupata'
        ELSE 'libera'
    END AS stato_calcolato,
    sess.id_sessione,
    sess.data_inizio AS occupata_da,
    TIMESTAMPDIFF(MINUTE, sess.data_inizio, NOW()) AS minuti_trascorsi,
    u.nome AS occupante_nome,
    u.cognome AS occupante_cognome,
    acc.percentuale_carica AS batteria_stazione_perc,
    acc.livello_corrente_kwh AS batteria_stazione_kwh,
    acc.stato_operativo AS batteria_stato
FROM punti_ricarica p
INNER JOIN stazioni s ON p.id_stazione = s.id_stazione
LEFT JOIN sessioni_ricarica sess ON p.id_punto = sess.id_punto AND sess.data_fine IS NULL
LEFT JOIN utenti u ON sess.id_utente = u.id_utente
LEFT JOIN accumulatori_stazione acc ON s.id_stazione = acc.id_stazione;
 
CREATE OR REPLACE VIEW vw_report_giornaliero AS
SELECT
    DATE(s.data_inizio) AS data,
    p.tipo_veicolo,
    COUNT(*) AS numero_ricariche,
    SUM(s.quantita_kwh) AS totale_kwh,
    SUM(CASE WHEN s.tipo_tariffa_applicata = 'standard' THEN s.costo_totale ELSE 0 END) AS incasso_standard,
    SUM(CASE WHEN s.tipo_tariffa_applicata LIKE 'gratuita%' THEN s.quantita_kwh ELSE 0 END) AS kwh_gratuiti,
    AVG(TIMESTAMPDIFF(MINUTE, s.data_inizio, s.data_fine)) AS durata_media_minuti
FROM sessioni_ricarica s
JOIN punti_ricarica p ON s.id_punto = p.id_punto
WHERE s.data_fine IS NOT NULL
GROUP BY DATE(s.data_inizio), p.tipo_veicolo
ORDER BY data DESC, p.tipo_veicolo;
 
-- ==========================================
-- 3. PROCEDURE / FUNZIONI
-- ==========================================
 
DELIMITER //
 
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
            SET p_disponibile = 1;
            SET p_messaggio = 'Colonnina disponibile';
        END IF;
    END IF;
END //
 
CREATE PROCEDURE sp_avvia_sessione(
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
END //
 
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
END //
 
CREATE PROCEDURE sp_aggiorna_batteria(
    IN p_id_accumulatore CHAR(36),
    IN p_livello_kwh DECIMAL(10,2),
    IN p_potenza_kw DECIMAL(7,2),
    IN p_temperatura DECIMAL(4,1)
)
BEGIN
    DECLARE v_stato_operativo VARCHAR(20);
 
    IF p_potenza_kw > 0.5 THEN
        SET v_stato_operativo = 'carica';
    ELSEIF p_potenza_kw < -0.5 THEN
        SET v_stato_operativo = 'scarica';
    ELSE
        SET v_stato_operativo = 'standby';
    END IF;
 
    START TRANSACTION;
    UPDATE accumulatori_stazione
    SET livello_corrente_kwh = p_livello_kwh,
        stato_operativo = v_stato_operativo,
        data_ultimo_aggiornamento = NOW()
    WHERE id_accumulatore = p_id_accumulatore;
 
    INSERT INTO storico_livello_batteria (
        id_accumulatore, livello_kwh, potenza_istantanea_kw, temperatura_celsius
    ) VALUES (
        p_id_accumulatore, p_livello_kwh, p_potenza_kw, p_temperatura
    );
    COMMIT;
END //
 
CREATE FUNCTION fn_calcola_distanza_km(
    lat1 DECIMAL(10,8), lon1 DECIMAL(11,8),
    lat2 DECIMAL(10,8), lon2 DECIMAL(11,8)
) RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE r INT DEFAULT 6371;
    DECLARE dlat DECIMAL(10,8);
    DECLARE dlon DECIMAL(10,8);
    DECLARE a DECIMAL(12,10);
    DECLARE c DECIMAL(12,10);
 
    SET dlat = RADIANS(lat2 - lat1);
    SET dlon = RADIANS(lon2 - lon1);
    SET a = SIN(dlat/2) * SIN(dlat/2) + COS(RADIANS(lat1)) * COS(RADIANS(lat2)) * SIN(dlon/2) * SIN(dlon/2);
    SET c = 2 * ATAN2(SQRT(a), SQRT(1-a));
    RETURN ROUND(r * c, 2);
END //
 
CREATE FUNCTION fn_stazioni_vicine(
    p_lat DECIMAL(10,8),
    p_lon DECIMAL(11,8),
    p_raggio_km DECIMAL(5,2)
) RETURNS LONGTEXT
NOT DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_json LONGTEXT;
 
    SELECT CONCAT(
        '[',
        IFNULL(
            GROUP_CONCAT(
                CONCAT(
                    '{',
                    '"id_stazione":"', s.id_stazione, '",',
                    '"nome":"', REPLACE(IFNULL(s.nome, ''), '"', '\"'), '",',
                    '"indirizzo":"', REPLACE(IFNULL(s.indirizzo, ''), '"', '\"'), '",',
                    '"distanza_km":', ROUND(fn_calcola_distanza_km(p_lat, p_lon, s.latitudine, s.longitudine), 2), ',',
                    '"punti_disponibili":', (
                        SELECT COUNT(*)
                        FROM vw_stato_colonnine v
                        WHERE v.id_stazione = s.id_stazione
                          AND v.stato_calcolato = 'libera'
                    ),
                    '}'
                )
                ORDER BY fn_calcola_distanza_km(p_lat, p_lon, s.latitudine, s.longitudine)
                SEPARATOR ','
            ),
            ''
        ),
        ']'
    ) INTO v_json
    FROM stazioni s
    WHERE fn_calcola_distanza_km(p_lat, p_lon, s.latitudine, s.longitudine) <= p_raggio_km;
 
    RETURN v_json;
END //
 
DELIMITER ;
 
-- ==========================================
-- 4. TRIGGER
-- ==========================================
 
DELIMITER //
 
CREATE TRIGGER trg_set_utenti_uuid_ins
BEFORE INSERT ON utenti
FOR EACH ROW
BEGIN
    IF NEW.id_utente IS NULL OR NEW.id_utente = '' THEN
        SET NEW.id_utente = UUID();
    END IF;
END //
 
CREATE TRIGGER trg_set_stazioni_coordinata_ins
BEFORE INSERT ON stazioni
FOR EACH ROW
BEGIN
    IF NEW.id_stazione IS NULL OR NEW.id_stazione = '' THEN
        SET NEW.id_stazione = UUID();
    END IF;
    SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
END //
 
CREATE TRIGGER trg_set_stazioni_coordinata_upd
BEFORE UPDATE ON stazioni
FOR EACH ROW
BEGIN
    IF NEW.longitudine <> OLD.longitudine OR NEW.latitudine <> OLD.latitudine THEN
        SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);
    END IF;
END //
 
CREATE TRIGGER trg_set_punti_uuid_ins
BEFORE INSERT ON punti_ricarica
FOR EACH ROW
BEGIN
    IF NEW.id_punto IS NULL OR NEW.id_punto = '' THEN
        SET NEW.id_punto = UUID();
    END IF;
END //
 
CREATE TRIGGER trg_set_sessioni_uuid_ins
BEFORE INSERT ON sessioni_ricarica
FOR EACH ROW
BEGIN
    IF NEW.id_sessione IS NULL OR NEW.id_sessione = '' THEN
        SET NEW.id_sessione = UUID();
    END IF;
END //
 
CREATE TRIGGER trg_set_accumulatori_uuid_ins
BEFORE INSERT ON accumulatori_stazione
FOR EACH ROW
BEGIN
    IF NEW.id_accumulatore IS NULL OR NEW.id_accumulatore = '' THEN
        SET NEW.id_accumulatore = UUID();
    END IF;
    SET NEW.percentuale_carica = ROUND((NEW.livello_corrente_kwh / NULLIF(NEW.capacita_utilizzabile_kwh, 0)) * 100, 2);
END //
 
CREATE TRIGGER trg_accumulatori_recalc_perc_upd
BEFORE UPDATE ON accumulatori_stazione
FOR EACH ROW
BEGIN
    SET NEW.percentuale_carica = ROUND((NEW.livello_corrente_kwh / NULLIF(NEW.capacita_utilizzabile_kwh, 0)) * 100, 2);
END //
 
CREATE TRIGGER trg_check_tariffa_giorno_ins
BEFORE INSERT ON tariffe_orarie
FOR EACH ROW
BEGIN
    IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'giorno_settimana deve essere tra 0 e 6';
    END IF;
END //
 
CREATE TRIGGER trg_check_tariffa_giorno_upd
BEFORE UPDATE ON tariffe_orarie
FOR EACH ROW
BEGIN
    IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'giorno_settimana deve essere tra 0 e 6';
    END IF;
END //
 
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
END //
 
CREATE TRIGGER trg_update_heartbeat_after_session
AFTER INSERT ON sessioni_ricarica
FOR EACH ROW
BEGIN
    UPDATE punti_ricarica
    SET data_ultimo_heartbeat = NOW()
    WHERE id_punto = NEW.id_punto;
END //
 
DELIMITER ;
 
-- ==========================================
-- 5. MANUTENZIONE MANUALE
-- ==========================================
 
DELIMITER //
CREATE PROCEDURE sp_cleanup_stale_sessions()
BEGIN
    UPDATE sessioni_ricarica s
    JOIN punti_ricarica p ON s.id_punto = p.id_punto
    SET s.data_fine = NOW(),
        s.quantita_kwh = 0,
        s.costo_totale = 0.00,
        s.tipo_tariffa_applicata = 'gratuita_struttura',
        s.stato_pagamento = 'gratuito',
        s.motivazione_gratuita = 'Sessione terminata automaticamente per timeout'
    WHERE s.data_fine IS NULL
      AND s.data_inizio < (NOW() - INTERVAL 24 HOUR)
      AND (p.data_ultimo_heartbeat IS NULL OR p.data_ultimo_heartbeat < (NOW() - INTERVAL 6 HOUR));
END //
DELIMITER ;
 
DELIMITER //
CREATE PROCEDURE sp_purge_battery_history()
BEGIN
    DELETE FROM storico_livello_batteria
    WHERE timestamp_misurazione < (NOW() - INTERVAL 90 DAY);
END //
DELIMITER ;
 
-- ==========================================
-- 6. DATI DEMO
-- ==========================================
 
SET @staz1 = UUID();
SET @staz2 = UUID();
SET @staz3 = UUID();
 
INSERT INTO stazioni (id_stazione, nome, indirizzo, latitudine, longitudine, tipo_area) VALUES
(@staz1, 'Stazione Centrale FS', 'Piazza Duca d''Aosta, 1, Milano', 45.48590000, 9.20440000, 'pubblico'),
(@staz2, 'Parco Sempione', 'Viale Elvezia, Milano', 45.47320000, 9.17780000, 'pubblico'),
(@staz3, 'Azienda Tech Campus', 'Via Privata, Milano', 45.46420000, 9.19000000, 'privato');
 
INSERT INTO punti_ricarica (
    id_punto, id_stazione, identificativo_fisico, tipo_veicolo,
    tipo_connettore, potenza_max_kw, tariffa_predefinita, metodi_autenticazione_supportati
) VALUES
(UUID(), @staz1, 'COL-1', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]'),
(UUID(), @staz1, 'COL-2', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]'),
(UUID(), @staz2, 'COL-1', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]'),
(UUID(), @staz2, 'COL-2', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]'),
(UUID(), @staz3, 'COL-1', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]'),
(UUID(), @staz3, 'COL-2', 'auto', 'Type2', 22.0, 0.45, '["APP","RFID"]');
 
INSERT INTO punti_ricarica (
    id_punto, id_stazione, identificativo_fisico, tipo_veicolo,
    tipo_connettore, potenza_max_kw, tariffa_predefinita, metodi_autenticazione_supportati
) VALUES
(UUID(), @staz1, 'EBIKE-1', 'bici', 'Schuko', 0.5, 0.00, '["APP","RFID","QR_CODE"]'),
(UUID(), @staz2, 'EBIKE-1', 'bici', 'Schuko', 0.5, 0.00, '["APP","RFID","QR_CODE"]');
 
INSERT INTO accumulatori_stazione (
    id_accumulatore, id_stazione, nome, capacita_totale_kwh, capacita_utilizzabile_kwh,
    potenza_max_carica_kw, potenza_max_scarica_kw, livello_corrente_kwh, soglia_minima_perc, soglia_massima_perc
) VALUES
(UUID(), @staz1, 'Tesla Powerwall 2', 13.5, 12.2, 5.0, 7.0, 8.5, 20.0, 90.0),
(UUID(), @staz2, 'Tesla Powerwall 2', 13.5, 12.2, 5.0, 7.0, 8.5, 20.0, 90.0);
 
-- ==========================================
-- 7. DATI REALI - PROGETTO GREEN SCHOOL
-- Sedi: ITIS Barsanti e IPSIA Galilei (Castelfranco Veneto)
-- ==========================================
 
SET @staz_itis = UUID();
SET @staz_ipsia = UUID();
 
INSERT INTO stazioni (id_stazione, nome, indirizzo, latitudine, longitudine, tipo_area) VALUES
(@staz_itis, 'ITIS G. Barsanti', 'Via dei Carpani 19, Castelfranco Veneto', 45.67812000, 11.92345000, 'pubblico'),
(@staz_ipsia, 'IPSIA G. Galilei', 'Via G. Marconi 4, Castelfranco Veneto', 45.67123000, 11.93120000, 'pubblico');
 
INSERT INTO utenti (email, nome, cognome, tipo_account) VALUES
('carmelo.cardaci@barsantigalilei.edu.it', 'Carmelo', 'Cardaci', 'completo'),
('adrian.necula@barsantigalilei.edu.it', 'Adrian', 'Necula', 'completo'),
('alberto.marin@barsantigalilei.edu.it', 'Alberto', 'Marin', 'completo'),
('alessandro.pirobon@barsantigalilei.edu.it', 'Alessandro', 'Pirobon', 'completo'),
('alexandru.miron@barsantigalilei.edu.it', 'Alexandru', 'Miron', 'completo'),
('alex.rigon@barsantigalilei.edu.it', 'Alex', 'Rigon', 'completo'),
('anna.brion@barsantigalilei.edu.it', 'Anna', 'Brion Bordignon', 'completo'),
('daniele.caverzan@barsantigalilei.edu.it', 'Daniele', 'Caverzan', 'completo'),
('daniele.ferraro@barsantigalilei.edu.it', 'Daniele', 'Ferraro', 'completo'),
('giovanni.longo@barsantigalilei.edu.it', 'Giovanni', 'Longo', 'completo'),
('hongming.ye@barsantigalilei.edu.it', 'Hong Ming', 'Ye', 'completo'),
('leonardo.stasi@barsantigalilei.edu.it', 'Leonardo', 'Stasi', 'completo'),
('luca.beraldo@barsantigalilei.edu.it', 'Luca', 'Beraldo', 'completo'),
('lucacristian.tasca@barsantigalilei.edu.it', 'Luca Cristian', 'Tasca', 'completo'),
('matteo.simionato@barsantigalilei.edu.it', 'Matteo', 'Simionato', 'completo'),
('mattias.campagnolo@barsantigalilei.edu.it', 'Mattias', 'Campagnolo', 'completo'),
('nicola.dissegna@barsantigalilei.edu.it', 'Nicola', 'Dissegna', 'completo'),
('paolo.pontarolo@barsantigalilei.edu.it', 'Paolo', 'Pontarolo', 'completo'),
('nicolas.spincin@barsantigalilei.edu.it', 'Nicolas', 'Spincin', 'completo'),
('riccardo.andrei@barsantigalilei.edu.it', 'Riccardo', 'Andrei', 'completo'),
('sabrina.ciriello@barsantigalilei.edu.it', 'Sabrina', 'Ciriello', 'completo'),
('samuele.ragazzo@barsantigalilei.edu.it', 'Samuele', 'Ragazzo', 'completo'),
('veronica.antigo@barsantigalilei.edu.it', 'Veronica', 'Antigo', 'completo'),
('viorel.corobceanu@barsantigalilei.edu.it', 'Viorel', 'Corobceanu', 'completo'),
('saucedo.eydan@barsantigalilei.edu.it', 'Saucedo', 'Eydan', 'completo'),
('manuel.vallotto@barsantigalilei.edu.it', 'Manuel', 'Vallotto', 'completo');
 
SET @p_itis = UUID();
INSERT INTO punti_ricarica (id_punto, id_stazione, identificativo_fisico, tipo_veicolo, tipo_connettore, potenza_max_kw, tariffa_predefinita, metodi_autenticazione_supportati) VALUES
(@p_itis,  @staz_itis,  'ITIS-COL-01',   'auto',        'Type2',  22.0, 0.00, '["APP","RFID"]'),
(UUID(),   @staz_itis,  'ITIS-BICI-01',  'bici',        'Schuko',  0.5, 0.00, '["APP","RFID","QR_CODE"]'),
(UUID(),   @staz_ipsia, 'IPSIA-COL-01',  'auto',        'Type2',  11.0, 0.00, '["APP","RFID"]'),
(UUID(),   @staz_ipsia, 'IPSIA-MOTO-01', 'monopattino', 'Schuko',  0.5, 0.00, '["APP","RFID","QR_CODE"]');
 
INSERT INTO badge_utente (id_utente, codice_rfid, nome_badge) VALUES
((SELECT id_utente FROM utenti WHERE email = 'carmelo.cardaci@barsantigalilei.edu.it'), 'RFID-CARDACI-01', 'Badge Carmelo'),
((SELECT id_utente FROM utenti WHERE email = 'adrian.necula@barsantigalilei.edu.it'),   'RFID-NECULA-01',  'Badge Adrian');
 
INSERT INTO sessioni_ricarica (id_sessione, id_utente, id_punto, metodo_avvio, data_inizio, data_fine, quantita_kwh, tipo_tariffa_applicata, costo_totale, stato_pagamento) VALUES
(UUID(), (SELECT id_utente FROM utenti WHERE email = 'carmelo.cardaci@barsantigalilei.edu.it'), @p_itis, 'APP',  NOW() - INTERVAL 1 DAY, NOW(), 45.5, 'gratuita_struttura', 0.00, 'gratuito'),
(UUID(), (SELECT id_utente FROM utenti WHERE email = 'adrian.necula@barsantigalilei.edu.it'),   @p_itis, 'RFID', NOW() - INTERVAL 1 DAY, NOW(), 32.1, 'gratuita_struttura', 0.00, 'gratuito');
 
INSERT INTO accumulatori_stazione (id_accumulatore, id_stazione, nome, capacita_totale_kwh, capacita_utilizzabile_kwh, potenza_max_carica_kw, potenza_max_scarica_kw, livello_corrente_kwh, soglia_minima_perc, soglia_massima_perc) VALUES
(UUID(), @staz_itis,  'Tesla Powerwall ITIS',  20.0, 18.0, 5.0, 7.0, 15.0, 20.0, 90.0),
(UUID(), @staz_ipsia, 'Tesla Powerwall IPSIA', 20.0, 18.0, 5.0, 7.0, 12.0, 20.0, 90.0);
 
-- ==========================================
-- FINE SCRIPT
-- ==========================================
