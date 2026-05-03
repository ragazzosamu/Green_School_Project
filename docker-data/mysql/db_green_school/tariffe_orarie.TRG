TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_check_tariffa_giorno_ins\n    BEFORE INSERT ON tariffe_orarie\n    FOR EACH ROW\n    BEGIN\n        IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN\n            SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'giorno_settimana deve essere tra 0 e 6\';\n        END IF;\n    END' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_check_tariffa_giorno_upd\n    BEFORE UPDATE ON tariffe_orarie\n    FOR EACH ROW\n    BEGIN\n        IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN\n            SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'giorno_settimana deve essere tra 0 e 6\';\n        END IF;\n    END'
sql_modes=1168113696 1168113696
definers='admin@%' 'admin@%'
client_cs_names='utf8mb4' 'utf8mb4'
connection_cl_names='utf8mb4_unicode_ci' 'utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1777807371739990 1777807371743306
