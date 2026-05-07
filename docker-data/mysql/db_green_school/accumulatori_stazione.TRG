TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_accumulatori_uuid_ins\n    BEFORE INSERT ON accumulatori_stazione\n    FOR EACH ROW\n    BEGIN\n        IF NEW.id_accumulatore IS NULL OR NEW.id_accumulatore = \'\' THEN\n            SET NEW.id_accumulatore = UUID();\n        END IF;\n    END'
sql_modes=1168113696
definers='admin@%'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci'
created=1778171567885447
