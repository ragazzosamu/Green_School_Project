TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_punti_uuid_ins\n    BEFORE INSERT ON punti_ricarica\n    FOR EACH ROW\n    BEGIN\n        IF NEW.id_punto IS NULL OR NEW.id_punto = \'\' THEN\n            SET NEW.id_punto = UUID();\n        END IF;\n    END'
sql_modes=1168113696
definers='admin@%'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci'
created=1778074570490167
