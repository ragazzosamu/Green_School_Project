TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_accumulatori_uuid_ins\nBEFORE INSERT ON accumulatori_stazione\nFOR EACH ROW\nBEGIN\n    IF NEW.id_accumulatore IS NULL OR NEW.id_accumulatore = \'\' THEN\n        SET NEW.id_accumulatore = UUID();\n    END IF;\n    SET NEW.percentuale_carica = ROUND((NEW.livello_corrente_kwh / NULLIF(NEW.capacita_utilizzabile_kwh, 0)) * 100, 2);\nEND' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_accumulatori_recalc_perc_upd\nBEFORE UPDATE ON accumulatori_stazione\nFOR EACH ROW\nBEGIN\n    SET NEW.percentuale_carica = ROUND((NEW.livello_corrente_kwh / NULLIF(NEW.capacita_utilizzabile_kwh, 0)) * 100, 2);\nEND'
sql_modes=1411383296 1411383296
definers='admin@%' 'admin@%'
client_cs_names='utf8mb3' 'utf8mb3'
connection_cl_names='utf8mb3_general_ci' 'utf8mb3_general_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1776271873816007 1776271873863226
