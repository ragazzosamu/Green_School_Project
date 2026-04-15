TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_punti_uuid_ins\nBEFORE INSERT ON punti_ricarica\nFOR EACH ROW\nBEGIN\n    IF NEW.id_punto IS NULL OR NEW.id_punto = \'\' THEN\n        SET NEW.id_punto = UUID();\n    END IF;\nEND'
sql_modes=1411383296
definers='admin@%'
client_cs_names='utf8mb3'
connection_cl_names='utf8mb3_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1776271873730900
