TYPE=TRIGGERS
triggers='CREATE DEFINER=`admin`@`%` TRIGGER trg_aggiorna_percentuale_accumulatore\n    AFTER INSERT ON storico_livello_batteria\n    FOR EACH ROW\n    BEGIN\n        UPDATE accumulatori_stazione\n        SET percentuale_carica = ROUND((NEW.livello_kwh / NULLIF(capacita_totale_kwh, 0)) * 100, 2)\n        WHERE id_accumulatore = NEW.id_accumulatore;\n    END'
sql_modes=1168113696
definers='admin@%'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_unicode_ci'
db_cl_names='utf8mb4_general_ci'
created=1777566699781293
