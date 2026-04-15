TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_sessioni_uuid_ins\nBEFORE INSERT ON sessioni_ricarica\nFOR EACH ROW\nBEGIN\n    IF NEW.id_sessione IS NULL OR NEW.id_sessione = \'\' THEN\n        SET NEW.id_sessione = UUID();\n    END IF;\nEND' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_check_sessione_aperta\nBEFORE INSERT ON sessioni_ricarica\nFOR EACH ROW\nBEGIN\n    DECLARE v_count INT;\n    SELECT COUNT(*) INTO v_count\n    FROM sessioni_ricarica\n    WHERE id_punto = NEW.id_punto\n      AND data_fine IS NULL;\n\n    IF v_count > 0 THEN\n        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Punto di ricarica gia occupato da una sessione attiva\';\n    END IF;\nEND' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_update_heartbeat_after_session\nAFTER INSERT ON sessioni_ricarica\nFOR EACH ROW\nBEGIN\n    UPDATE punti_ricarica\n    SET data_ultimo_heartbeat = NOW()\n    WHERE id_punto = NEW.id_punto;\nEND'
sql_modes=1411383296 1411383296 1411383296
definers='admin@%' 'admin@%' 'admin@%'
client_cs_names='utf8mb3' 'utf8mb3' 'utf8mb3'
connection_cl_names='utf8mb3_general_ci' 'utf8mb3_general_ci' 'utf8mb3_general_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1776271873767081 1776271874110998 1776271874198599
