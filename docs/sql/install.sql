/**
 * Zugzwang Project
 * SQL for installation of tournaments module
 *
 * http://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


CREATE TABLE `paarungen` (
  `paarung_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `runde_no` tinyint(3) unsigned NOT NULL,
  `place_contact_id` int(10) unsigned DEFAULT NULL,
  `spielbeginn` time DEFAULT NULL,
  `tisch_no` tinyint(3) unsigned NOT NULL,
  `heim_team_id` int(10) unsigned DEFAULT NULL,
  `auswaerts_team_id` int(10) unsigned DEFAULT NULL,
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`paarung_id`),
  UNIQUE KEY `runde_no` (`event_id`,`runde_no`,`tisch_no`),
  UNIQUE KEY `runde_event_id` (`event_id`,`heim_team_id`,`auswaerts_team_id`),
  KEY `ort_id` (`place_contact_id`),
  KEY `heim_team_id` (`heim_team_id`),
  KEY `auswaerts_team_id` (`auswaerts_team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `partien` (
  `partie_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `runde_no` tinyint(3) unsigned NOT NULL,
  `paarung_id` int(10) unsigned DEFAULT NULL,
  `brett_no` tinyint(3) unsigned DEFAULT NULL,
  `weiss_person_id` int(10) unsigned DEFAULT NULL,
  `weiss_ergebnis` decimal(2,1) unsigned DEFAULT NULL,
  `schwarz_person_id` int(10) unsigned DEFAULT NULL,
  `schwarz_ergebnis` decimal(2,1) unsigned DEFAULT NULL,
  `heim_spieler_farbe` enum('weiß','schwarz') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `heim_wertung` decimal(2,1) unsigned DEFAULT NULL,
  `auswaerts_wertung` decimal(2,1) unsigned DEFAULT NULL,
  `partiestatus_category_id` int(10) unsigned NOT NULL,
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pgn` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `eco` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `halbzuege` smallint(5) unsigned DEFAULT NULL,
  `block_ergebnis_aus_pgn` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `vertauschte_farben` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `weiss_zeit` time DEFAULT NULL,
  `schwarz_zeit` time DEFAULT NULL,
  `ergebnis_gemeldet_um` datetime DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`partie_id`),
  KEY `paarung_id` (`paarung_id`),
  KEY `event_id` (`event_id`),
  KEY `weiss_person_id` (`weiss_person_id`),
  KEY `schwarz_person_id` (`schwarz_person_id`),
  KEY `partiestatus_kategorie_id` (`partiestatus_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tabellenstaende` (
  `tabellenstand_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `runde_no` tinyint(3) unsigned NOT NULL,
  `team_id` int(10) unsigned DEFAULT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `platz_no` tinyint(3) unsigned NOT NULL,
  `platz_brett_no` tinyint(3) unsigned DEFAULT NULL,
  `spiele_g` tinyint(3) unsigned DEFAULT NULL,
  `spiele_u` tinyint(3) unsigned DEFAULT NULL,
  `spiele_v` tinyint(3) unsigned DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabellenstand_id`),
  UNIQUE KEY `team_id_event_id_runde_no` (`team_id`,`event_id`,`runde_no`),
  UNIQUE KEY `person_id_event_id_runde_no` (`person_id`,`event_id`,`runde_no`),
  KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tabellenstaende_wertungen` (
  `tsw_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tabellenstand_id` int(10) unsigned NOT NULL,
  `wertung_category_id` int(10) unsigned NOT NULL,
  `wertung` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tsw_id`),
  UNIQUE KEY `tabellenstand_id` (`tabellenstand_id`,`wertung_category_id`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `teams` (
  `team_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `verein_org_id` int unsigned DEFAULT NULL,
  `team` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_no` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kennung` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `berechtigung_category_id` int unsigned DEFAULT NULL,
  `team_status` enum('Teilnehmer','Nachrücker','Löschung','Teilnahmeberechtigt') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'Teilnehmer',
  `nachruecker_reihenfolge` smallint DEFAULT NULL,
  `datum_anreise` date DEFAULT NULL,
  `uhrzeit_anreise` time DEFAULT NULL,
  `datum_abreise` date DEFAULT NULL,
  `uhrzeit_abreise` time DEFAULT NULL,
  `spielbeginn` time DEFAULT NULL,
  `setzliste_no` smallint unsigned DEFAULT NULL,
  `eintrag_datum` datetime DEFAULT NULL,
  `meldung` enum('offen','teiloffen','gesperrt','komplett') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'gesperrt',
  `meldung_datum` datetime DEFAULT NULL,
  `meldung_hash` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `fremdschluessel` smallint unsigned DEFAULT NULL,
  `spielfrei` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`team_id`),
  UNIQUE KEY `meldung_hash` (`meldung_hash`),
  UNIQUE KEY `kennung` (`kennung`),
  KEY `termin_id` (`event_id`),
  KEY `verein_org_id` (`verein_org_id`),
  KEY `berechtigung_kategorie_id` (`berechtigung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere` (
  `turnier_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `turnierform_category_id` int(10) unsigned NOT NULL,
  `bretter_min` tinyint(3) unsigned DEFAULT NULL,
  `bretter_max` tinyint(4) DEFAULT NULL,
  `runden` tinyint(3) unsigned NOT NULL,
  `gastspieler` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `modus_category_id` int(10) unsigned NOT NULL,
  `alter_min` tinyint(3) unsigned DEFAULT NULL,
  `alter_max` tinyint(3) unsigned DEFAULT NULL,
  `geschlecht` set('m','w') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'm,w',
  `dwz_min` smallint(5) unsigned DEFAULT NULL,
  `dwz_max` smallint(5) unsigned DEFAULT NULL,
  `elo_min` smallint(5) unsigned DEFAULT NULL,
  `elo_max` smallint(5) unsigned DEFAULT NULL,
  `pseudo_dwz` smallint(4) unsigned DEFAULT NULL,
  `ratings_updated` date DEFAULT NULL,
  `teams_max` smallint(5) unsigned DEFAULT NULL,
  `wertung_spielfrei` enum('Sieg','Unentschieden','keine') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'Sieg',
  `hinweis_aufstellung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hinweis_meldebogen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `zimmerbuchung` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'ja',
  `teilnehmerliste` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein',
  `turnierkennung` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notationspflicht` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'ja',
  `livebretter` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `spielerphotos` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `teamphotos` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `spielernachrichten` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `fehler` text CHARACTER SET latin1 COLLATE latin1_general_ci,
  `komplett` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `certificate_id` int(10) unsigned DEFAULT NULL,
  `urkunde_ort` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_datum` date DEFAULT NULL,
  `urkunde_unterschrift1` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_unterschrift2` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_parameter` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabellenstaende` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabellenstand_runde_no` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`turnier_id`),
  UNIQUE KEY `event_id` (`event_id`),
  KEY `modus_kategorie_id` (`modus_category_id`),
  KEY `turnierform_kategorie_id` (`turnierform_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_bedenkzeiten` (
  `tb_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `phase` tinyint(3) unsigned NOT NULL,
  `bedenkzeit_sec` smallint(5) unsigned NOT NULL,
  `zeitbonus_sec` tinyint(3) unsigned DEFAULT NULL,
  `zuege` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`tb_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_kennungen` (
  `tk_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `kennung` varchar(15) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `kennung_category_id` int(10) unsigned NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tk_id`),
  UNIQUE KEY `turnier_id_kennung_kategorie_id` (`turnier_id`,`kennung_category_id`),
  UNIQUE KEY `kennung_kennung_kategorie_id` (`kennung`,`kennung_category_id`),
  KEY `kennung_kategorie_id` (`kennung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_partien` (
  `tp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `partien_pfad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tp_id`),
  KEY `turnier_id` (`turnier_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_status` (
  `turnier_status_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `status_category_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`turnier_status_id`),
  UNIQUE KEY `turnier_id_status_kategorie_id` (`turnier_id`,`status_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_wertungen` (
  `tw_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `wertung_category_id` int(10) unsigned NOT NULL,
  `reihenfolge` tinyint(3) unsigned NOT NULL,
  `anzeigen` enum('immer','bei Gleichstand') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'immer',
  PRIMARY KEY (`tw_id`),
  UNIQUE KEY `turnier_id` (`turnier_id`,`wertung_category_id`),
  KEY `reihenfolge` (`reihenfolge`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DELIMITER ;;
CREATE FUNCTION `event_id`() RETURNS int(11)
    NO SQL
    DETERMINISTIC
return @event_id ;;
DELIMITER ;


CREATE VIEW `partien_einzelergebnisse` AS select `partien`.`partie_id` AS `partie_id`,`partien`.`partiestatus_category_id` AS `partiestatus_category_id`,`partien`.`event_id` AS `event_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`weiss_person_id` AS `person_id`,`partien`.`schwarz_person_id` AS `gegner_id`,`partien`.`weiss_ergebnis` AS `ergebnis`,`partien`.`brett_no` AS `brett_no` from `partien` where (`partien`.`event_id` = `event_id`()) union all select `partien`.`partie_id` AS `partie_id`,`partien`.`partiestatus_category_id` AS `partiestatus_category_id`,`partien`.`event_id` AS `event_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`schwarz_person_id` AS `person_id`,`partien`.`weiss_person_id` AS `gegner_id`,`partien`.`schwarz_ergebnis` AS `ergebnis`,`partien`.`brett_no` AS `brett_no` from `partien` where (`partien`.`event_id` = `event_id`());


CREATE VIEW `buchholz_einzel_mit_kampflosen_view` AS select `ergebnisse_gegner`.`partiestatus_category_id` AS `partiestatus_category_id`,`ergebnisse`.`runde_no` AS `runde_no`,`ergebnisse`.`event_id` AS `event_id`,`ergebnisse`.`person_id` AS `person_id`,`ergebnisse`.`gegner_id` AS `gegner_id`,`ergebnisse_gegner`.`ergebnis` AS `punkte`,`ergebnisse_gegner`.`runde_no` AS `runde_gegner` from (`partien_einzelergebnisse` `ergebnisse` join `partien_einzelergebnisse` `ergebnisse_gegner` on(((`ergebnisse`.`event_id` = `ergebnisse_gegner`.`event_id`) and (`ergebnisse`.`gegner_id` = `ergebnisse_gegner`.`person_id`)))) order by `ergebnisse`.`person_id`,`ergebnisse`.`gegner_id`;


CREATE VIEW `partien_ergebnisse_view` AS select `partien`.`event_id` AS `event_id`,`paarungen`.`heim_team_id` AS `team_id`,`paarungen`.`auswaerts_team_id` AS `gegner_team_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`brett_no` AS `brett_no`,`partien`.`partie_id` AS `partie_id`,`partien`.`heim_wertung` AS `ergebnis`,`partien`.`auswaerts_wertung` AS `ergebnis_gegner` from (`paarungen` left join `partien` on((`paarungen`.`paarung_id` = `partien`.`paarung_id`))) where (`partien`.`event_id` = `event_id`()) union select `partien`.`event_id` AS `event_id`,`paarungen`.`auswaerts_team_id` AS `team_id`,`paarungen`.`heim_team_id` AS `gegner_team_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`brett_no` AS `brett_no`,`partien`.`partie_id` AS `partie_id`,`partien`.`auswaerts_wertung` AS `ergebnis`,`partien`.`heim_wertung` AS `ergebnis_gegner` from (`paarungen` left join `partien` on((`paarungen`.`paarung_id` = `partien`.`paarung_id`))) where (`partien`.`event_id` = `event_id`());


CREATE VIEW `paarungen_ergebnisse_view` AS select `partien_ergebnisse_view`.`event_id` AS `event_id`,`partien_ergebnisse_view`.`team_id` AS `team_id`,`partien_ergebnisse_view`.`gegner_team_id` AS `gegner_team_id`,`partien_ergebnisse_view`.`runde_no` AS `runde_no`,0 AS `kampflos`,sum(`partien_ergebnisse_view`.`ergebnis`) AS `brettpunkte`,sum(`partien_ergebnisse_view`.`ergebnis_gegner`) AS `brettpunkte_gegner`,(case when (sum(`partien_ergebnisse_view`.`ergebnis`) < sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 0 when (sum(`partien_ergebnisse_view`.`ergebnis`) > sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 2 when (sum(`partien_ergebnisse_view`.`ergebnis`) = sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 1 end) AS `mannschaftspunkte`,(case when (sum(`partien_ergebnisse_view`.`ergebnis`) < sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 2 when (sum(`partien_ergebnisse_view`.`ergebnis`) > sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 0 when (sum(`partien_ergebnisse_view`.`ergebnis`) = sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 1 end) AS `mannschaftspunkte_gegner` from `partien_ergebnisse_view` where (`partien_ergebnisse_view`.`event_id` = `event_id`()) group by `partien_ergebnisse_view`.`event_id`,`partien_ergebnisse_view`.`team_id`,`partien_ergebnisse_view`.`runde_no`,`partien_ergebnisse_view`.`gegner_team_id` union select `paarungen`.`event_id` AS `event_id`,`paarungen`.`heim_team_id` AS `team_id`,`teams`.`team_id` AS `gegner_team_id`,`paarungen`.`runde_no` AS `runde_no`,1 AS `kampflos`,`turniere`.`bretter_min` AS `brettpunkte`,0 AS `brettpunkte_gegner`,2 AS `mannschaftspunkte`,0 AS `mannschaftspunkte_gegner` from ((`paarungen` join `teams` on(((`teams`.`team_id` = `paarungen`.`auswaerts_team_id`) and (`teams`.`spielfrei` = 'ja')))) join `turniere` on((`turniere`.`event_id` = `paarungen`.`event_id`))) where (`paarungen`.`event_id` = `event_id`()) union select `paarungen`.`event_id` AS `event_id`,`paarungen`.`auswaerts_team_id` AS `team_id`,`teams`.`team_id` AS `gegner_team_id`,`paarungen`.`runde_no` AS `runde_no`,1 AS `kampflos`,`turniere`.`bretter_min` AS `brettpunkte`,0 AS `brettpunkte_gegner`,2 AS `mannschaftspunkte`,0 AS `mannschaftspunkte_gegner` from ((`paarungen` join `teams` on(((`teams`.`team_id` = `paarungen`.`heim_team_id`) and (`teams`.`spielfrei` = 'ja')))) join `turniere` on((`turniere`.`event_id` = `paarungen`.`event_id`))) where (`paarungen`.`event_id` = `event_id`());


CREATE VIEW `tabellenstaende_termine_view` AS select `teams`.`event_id` AS `event_id`,`paarungen`.`runde_no` AS `runde_no`,`teams`.`team_id` AS `team_id` from (`paarungen` left join `teams` on((`paarungen`.`event_id` = `teams`.`event_id`))) where (`paarungen`.`event_id` = `event_id`()) group by `teams`.`event_id`,`paarungen`.`runde_no`,`teams`.`team_id`;


CREATE VIEW `tabellenstaende_guv_view` AS select `tabellenstaende_termine_view`.`event_id` AS `event_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 2),1,0)) AS `gewonnen`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 1),1,0)) AS `unentschieden`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 0),1,0)) AS `verloren` from (`tabellenstaende_termine_view` left join `paarungen_ergebnisse_view` on(((`paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`event_id`,`tabellenstaende_termine_view`.`runde_no`,`tabellenstaende_termine_view`.`team_id`;


CREATE VIEW `buchholz_mit_kampflosen_view` AS select `tabellenstaende_termine_view`.`event_id` AS `event_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,sum(if((`gegners_paarungen`.`kampflos` = 1),1,`gegners_paarungen`.`mannschaftspunkte`)) AS `buchholz_mit_korrektur`,sum(`gegners_paarungen`.`mannschaftspunkte`) AS `buchholz` from ((`paarungen_ergebnisse_view` left join `tabellenstaende_termine_view` on(((`paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) left join `paarungen_ergebnisse_view` `gegners_paarungen` on(((`gegners_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`gegner_team_id`) and (`gegners_paarungen`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`event_id`,`tabellenstaende_termine_view`.`team_id`,`tabellenstaende_termine_view`.`runde_no` union select `tabellenstaende_termine_view`.`event_id` AS `event_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,(sum(`bisherige_paarungen`.`mannschaftspunkte`) + (`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`)) AS `buchholz_kampflos_mit_korrektur`,0 AS `buchholz_kampflos` from (`paarungen_ergebnisse_view` `bisherige_paarungen` left join (`paarungen_ergebnisse_view` left join `tabellenstaende_termine_view` on(((`paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) on(((`bisherige_paarungen`.`event_id` = `paarungen_ergebnisse_view`.`event_id`) and (`bisherige_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`team_id`) and (`bisherige_paarungen`.`runde_no` < `paarungen_ergebnisse_view`.`runde_no`)))) where (`paarungen_ergebnisse_view`.`kampflos` = 1) group by `tabellenstaende_termine_view`.`event_id`,`tabellenstaende_termine_view`.`team_id`,`tabellenstaende_termine_view`.`runde_no`,`paarungen_ergebnisse_view`.`runde_no` union select `tabellenstaende_termine_view`.`event_id` AS `event_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,(`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`) AS `buchholz_kampflos_mit_korrektur`,0 AS `buchholz_kampflos` from (`tabellenstaende_termine_view` join `paarungen_ergebnisse_view` on(((`paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` = 1) and (`paarungen_ergebnisse_view`.`kampflos` = 1))));


CREATE VIEW `buchholz_view` AS select `buchholz_mit_kampflosen_view`.`event_id` AS `event_id`,`buchholz_mit_kampflosen_view`.`team_id` AS `team_id`,`buchholz_mit_kampflosen_view`.`runde_no` AS `runde_no`,ifnull(sum(`buchholz_mit_kampflosen_view`.`buchholz_mit_korrektur`),0) AS `buchholz_mit_korrektur`,ifnull(sum(`buchholz_mit_kampflosen_view`.`buchholz`),0) AS `buchholz` from `buchholz_mit_kampflosen_view` group by `buchholz_mit_kampflosen_view`.`event_id`,`buchholz_mit_kampflosen_view`.`team_id`,`buchholz_mit_kampflosen_view`.`runde_no`;


CREATE VIEW `tabellenstaende_view` AS select `tabellenstaende_termine_view`.`event_id` AS `event_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,`turniere_wertungen`.`reihenfolge` AS `reihenfolge`,`turniere_wertungen`.`wertung_category_id` AS `wertung_category_id`,(case `turniere_wertungen`.`wertung_category_id` when 144 then sum(`paarungen_ergebnisse_view`.`mannschaftspunkte`) when 145 then sum(`paarungen_ergebnisse_view`.`brettpunkte`) when 146 then (select `buchholz_view`.`buchholz_mit_korrektur` from `buchholz_view` where ((`buchholz_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`buchholz_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`buchholz_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) when 215 then (select `buchholz_view`.`buchholz` from `buchholz_view` where ((`buchholz_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`buchholz_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`buchholz_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) when 147 then (select sum((case `partien_ergebnisse_view`.`ergebnis` when 1 then ((1 + `turniere`.`bretter_min`) - `partien_ergebnisse_view`.`brett_no`) when 0.5 then (((1 + `turniere`.`bretter_min`) - `partien_ergebnisse_view`.`brett_no`) / 2) when 0 then 0 end)) AS `berliner_wertung` from `partien_ergebnisse_view` where ((`partien_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`partien_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`) and (`partien_ergebnisse_view`.`team_id` = `paarungen_ergebnisse_view`.`team_id`))) when 150 then (select `tabellenstaende_guv_view`.`gewonnen` from `tabellenstaende_guv_view` where ((`tabellenstaende_guv_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`tabellenstaende_guv_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`tabellenstaende_guv_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) end) AS `wertung` from (`paarungen_ergebnisse_view` left join (`turniere_wertungen` left join (`turniere` left join `tabellenstaende_termine_view` on((`turniere`.`event_id` = `tabellenstaende_termine_view`.`event_id`))) on((`turniere_wertungen`.`turnier_id` = `turniere`.`turnier_id`))) on(((`paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`event_id`,`tabellenstaende_termine_view`.`runde_no`,`tabellenstaende_termine_view`.`team_id`,`turniere_wertungen`.`reihenfolge`,`turniere_wertungen`.`wertung_category_id`,`paarungen_ergebnisse_view`.`team_id`;
