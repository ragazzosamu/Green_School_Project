TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_set_stazioni_coordinata_ins\n    BEFORE INSERT ON stazioni\n    FOR EACH ROW\n    BEGIN\n        IF NEW.id_stazione IS NULL OR NEW.id_stazione = \'\' THEN\n            SET NEW.id_stazione = UUID();\n        END IF;\n        SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);\n    END' 'CREATE DEFINER=`admin`@`%` TRIGGER trg_set_stazioni_coordinata_upd\n    BEFORE UPDATE ON stazioni\n    FOR EACH ROW\n    BEGIN\n        IF NEW.longitudine <> OLD.longitudine OR NEW.latitudine <> OLD.latitudine THEN\n            SET NEW.coordinata = POINT(NEW.longitudine, NEW.latitudine);\n        END IF;\n    END'
sql_modes=1168113696 1168113696
definers='admin@%' 'admin@%'
client_cs_names='utf8mb4' 'utf8mb4'
connection_cl_names='utf8mb4_unicode_ci' 'utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci' 'utf8mb4_general_ci'
created=1777807371722137 1777807371724440
