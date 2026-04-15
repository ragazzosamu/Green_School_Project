TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_check_tariffa_giorno_ins\nBEFORE INSERT ON tariffe_orarie\nFOR EACH ROW\nBEGIN\n    IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN\n        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'giorno_settimana deve essere tra 0 e 6\';\n    END IF;\nEND' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_check_tariffa_giorno_upd\nBEFORE UPDATE ON tariffe_orarie\nFOR EACH ROW\nBEGIN\n    IF NEW.giorno_settimana < 0 OR NEW.giorno_settimana > 6 THEN\n        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'giorno_settimana deve essere tra 0 e 6\';\n    END IF;\nEND'
sql_modes=1411383296 1411383296
definers='admin@%' 'admin@%'
client_cs_names='utf8mb3' 'utf8mb3'
connection_cl_names='utf8mb3_general_ci' 'utf8mb3_general_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1776271873971811 1776271874022236
