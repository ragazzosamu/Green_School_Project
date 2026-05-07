TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_sessioni_uuid_ins\n    BEFORE INSERT ON sessioni_ricarica\n    FOR EACH ROW\n    BEGIN\n        IF NEW.id_sessione IS NULL OR NEW.id_sessione = \'\' THEN\n            SET NEW.id_sessione = UUID();\n        END IF;\n    END' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_check_sessione_aperta\n    BEFORE INSERT ON sessioni_ricarica\n    FOR EACH ROW\n    BEGIN\n        DECLARE v_count INT;\n        SELECT COUNT(*) INTO v_count\n        FROM sessioni_ricarica\n        WHERE id_punto = NEW.id_punto\n          AND data_fine IS NULL;\n     \n        IF v_count > 0 THEN\n            SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'Punto di ricarica gia occupato da una sessione attiva\';\n        END IF;\n    END' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_update_heartbeat_after_session\n    AFTER INSERT ON sessioni_ricarica\n    FOR EACH ROW\n    BEGIN\n        UPDATE punti_ricarica\n        SET data_ultimo_heartbeat = NOW()\n        WHERE id_punto = NEW.id_punto;\n    END'
sql_modes=1168113696 1168113696 1168113696
definers='admin@%' 'admin@%' 'admin@%'
client_cs_names='utf8mb4' 'utf8mb4' 'utf8mb4'
connection_cl_names='utf8mb4_unicode_ci' 'utf8mb4_unicode_ci' 'utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1778171567884037 1778171567895365 1778171567898032
