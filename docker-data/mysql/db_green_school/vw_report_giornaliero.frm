TYPE=VIEW
query=select cast(`s`.`data_inizio` as date) AS `data`,`p`.`tipo_veicolo` AS `tipo_veicolo`,count(0) AS `numero_ricariche`,sum(`s`.`quantita_kwh`) AS `totale_kwh`,sum(case when `s`.`tipo_tariffa_applicata` = \'standard\' then `s`.`costo_totale` else 0 end) AS `incasso_standard`,sum(case when `s`.`tipo_tariffa_applicata` like \'gratuita%\' then `s`.`quantita_kwh` else 0 end) AS `kwh_gratuiti`,avg(timestampdiff(MINUTE,`s`.`data_inizio`,`s`.`data_fine`)) AS `durata_media_minuti` from (`db_green_school`.`sessioni_ricarica` `s` join `db_green_school`.`punti_ricarica` `p` on(`s`.`id_punto` = `p`.`id_punto`)) where `s`.`data_fine` is not null group by cast(`s`.`data_inizio` as date),`p`.`tipo_veicolo` order by cast(`s`.`data_inizio` as date) desc,`p`.`tipo_veicolo`
md5=060462287332e89f99c0f1e640261482
updatable=0
algorithm=0
definer_user=admin
definer_host=%
suid=2
with_check_option=0
timestamp=0001776271873524427
create-version=2
source=SELECT\n    DATE(s.data_inizio) AS data,\n    p.tipo_veicolo,\n    COUNT(*) AS numero_ricariche,\n    SUM(s.quantita_kwh) AS totale_kwh,\n    SUM(CASE WHEN s.tipo_tariffa_applicata = \'standard\' THEN s.costo_totale ELSE 0 END) AS incasso_standard,\n    SUM(CASE WHEN s.tipo_tariffa_applicata LIKE \'gratuita%\' THEN s.quantita_kwh ELSE 0 END) AS kwh_gratuiti,\n    AVG(TIMESTAMPDIFF(MINUTE, s.data_inizio, s.data_fine)) AS durata_media_minuti\nFROM sessioni_ricarica s\nJOIN punti_ricarica p ON s.id_punto = p.id_punto\nWHERE s.data_fine IS NOT NULL\nGROUP BY DATE(s.data_inizio), p.tipo_veicolo\nORDER BY data DESC, p.tipo_veicolo
client_cs_name=utf8mb3
connection_cl_name=utf8mb3_general_ci
view_body_utf8=select cast(`s`.`data_inizio` as date) AS `data`,`p`.`tipo_veicolo` AS `tipo_veicolo`,count(0) AS `numero_ricariche`,sum(`s`.`quantita_kwh`) AS `totale_kwh`,sum(case when `s`.`tipo_tariffa_applicata` = \'standard\' then `s`.`costo_totale` else 0 end) AS `incasso_standard`,sum(case when `s`.`tipo_tariffa_applicata` like \'gratuita%\' then `s`.`quantita_kwh` else 0 end) AS `kwh_gratuiti`,avg(timestampdiff(MINUTE,`s`.`data_inizio`,`s`.`data_fine`)) AS `durata_media_minuti` from (`db_green_school`.`sessioni_ricarica` `s` join `db_green_school`.`punti_ricarica` `p` on(`s`.`id_punto` = `p`.`id_punto`)) where `s`.`data_fine` is not null group by cast(`s`.`data_inizio` as date),`p`.`tipo_veicolo` order by cast(`s`.`data_inizio` as date) desc,`p`.`tipo_veicolo`
mariadb-version=101116
