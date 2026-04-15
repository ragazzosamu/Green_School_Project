TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_stazioni_coordinata_ins\nBEFORE INSERT ON stazioni\nFOR EACH ROW\nBEGIN\n    IF NEW.id_stazione IS NULL OR NEW.id_stazione = \'\' THEN\n        SET NEW.id_stazione = UUID();\n    END IF;\n    SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);\nEND' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_set_stazioni_coordinata_upd\nBEFORE UPDATE ON stazioni\nFOR EACH ROW\nBEGIN\n    IF NEW.longitudine <> OLD.longitudine OR NEW.latitudine <> OLD.latitudine THEN\n        SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);\n    END IF;\nEND'
sql_modes=1411383296 1411383296
definers='admin@%' 'admin@%'
client_cs_names='utf8mb3' 'utf8mb3'
connection_cl_names='utf8mb3_general_ci' 'utf8mb3_general_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1776271873615350 1776271873663982
