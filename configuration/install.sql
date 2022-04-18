/**
 * tournaments module
 * SQL for installation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


CREATE TABLE `anmerkungen` (
  `anmerkung_id` int unsigned NOT NULL AUTO_INCREMENT,
  `anmerkung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` int unsigned DEFAULT NULL,
  `participation_id` int unsigned DEFAULT NULL,
  `autor_person_id` int unsigned NOT NULL,
  `sichtbarkeit` set('Team','Organisator') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `anmerkung_status` enum('offen','erledigt') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'offen',
  `erstellt` datetime NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`anmerkung_id`),
  KEY `team_id` (`team_id`),
  KEY `autor_person_id` (`autor_person_id`),
  KEY `participation_id` (`participation_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'team_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teilnahmen', 'participation_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'participation_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'persons', 'person_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'autor_person_id', 'no-delete');


CREATE TABLE `cronjobs` (
  `cronjob_id` int unsigned NOT NULL AUTO_INCREMENT,
  `cronjob_category_id` int unsigned NOT NULL,
  `event_id` int unsigned NOT NULL,
  `runde_no` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prioritaet` tinyint NOT NULL DEFAULT '0',
  `start` datetime DEFAULT NULL,
  `ende` datetime DEFAULT NULL,
  `erfolgreich` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein',
  `request` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`cronjob_id`),
  UNIQUE KEY `cronjob_category_id` (`cronjob_category_id`,`event_id`,`runde_no`,`start`),
  KEY `termin_id` (`event_id`),
  KEY `runde_no` (`runde_no`),
  KEY `prioritaet` (`prioritaet`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'cronjobs', 'cronjob_id', 'cronjob_category_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'cronjobs', 'cronjob_id', 'event_id', 'delete');

INSERT INTO categories (`category`, `description`, `main_category_id`, `path`, `parameters`, `sequence`, `last_update`) VALUES ('Cronjobs', NULL, NULL, 'cronjobs', '&alias=cronjobs', NULL, NOW());


CREATE TABLE `paarungen` (
  `paarung_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `runde_no` tinyint unsigned NOT NULL,
  `place_contact_id` int unsigned DEFAULT NULL,
  `spielbeginn` time DEFAULT NULL,
  `tisch_no` tinyint unsigned NOT NULL,
  `heim_team_id` int unsigned DEFAULT NULL,
  `auswaerts_team_id` int unsigned DEFAULT NULL,
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`paarung_id`),
  UNIQUE KEY `runde_no` (`event_id`,`runde_no`,`tisch_no`),
  UNIQUE KEY `runde_termin_id` (`event_id`,`heim_team_id`,`auswaerts_team_id`),
  KEY `ort_id` (`place_contact_id`),
  KEY `heim_team_id` (`heim_team_id`),
  KEY `auswaerts_team_id` (`auswaerts_team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'event_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'contacts', 'contact_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'place_contact_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'heim_team_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'auswaerts_team_id', 'no-delete');


CREATE TABLE `partien` (
  `partie_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `runde_no` tinyint unsigned NOT NULL,
  `paarung_id` int unsigned DEFAULT NULL,
  `brett_no` tinyint unsigned DEFAULT NULL,
  `weiss_person_id` int unsigned DEFAULT NULL,
  `weiss_ergebnis` decimal(2,1) unsigned DEFAULT NULL,
  `schwarz_person_id` int unsigned DEFAULT NULL,
  `schwarz_ergebnis` decimal(2,1) unsigned DEFAULT NULL,
  `heim_spieler_farbe` enum('weiß','schwarz') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `heim_wertung` decimal(2,1) unsigned DEFAULT NULL,
  `auswaerts_wertung` decimal(2,1) unsigned DEFAULT NULL,
  `partiestatus_category_id` int unsigned NOT NULL,
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pgn` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `eco` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `halbzuege` smallint unsigned DEFAULT NULL,
  `block_ergebnis_aus_pgn` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `vertauschte_farben` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'nein',
  `weiss_zeit` time DEFAULT NULL,
  `schwarz_zeit` time DEFAULT NULL,
  `ergebnis_gemeldet_um` datetime DEFAULT NULL,
  `url` tinytext CHARACTER SET latin1 COLLATE latin1_general_ci,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`partie_id`),
  UNIQUE KEY `paarung_id_brett_no` (`paarung_id`,`brett_no`),
  KEY `paarung_id` (`paarung_id`),
  KEY `termin_id` (`event_id`),
  KEY `weiss_person_id` (`weiss_person_id`),
  KEY `schwarz_person_id` (`schwarz_person_id`),
  KEY `partiestatus_kategorie_id` (`partiestatus_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tabellenstaende` (
  `tabellenstand_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `runde_no` tinyint unsigned NOT NULL,
  `team_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `platz_no` tinyint unsigned NOT NULL,
  `platz_brett_no` tinyint unsigned DEFAULT NULL,
  `spiele_g` tinyint unsigned DEFAULT NULL,
  `spiele_u` tinyint unsigned DEFAULT NULL,
  `spiele_v` tinyint unsigned DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabellenstand_id`),
  UNIQUE KEY `team_id_termin_id_runde_no` (`team_id`,`event_id`,`runde_no`),
  UNIQUE KEY `person_id_termin_id_runde_no` (`person_id`,`event_id`,`runde_no`),
  KEY `termin_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tabellenstaende_wertungen` (
  `tsw_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tabellenstand_id` int unsigned NOT NULL,
  `wertung_category_id` int unsigned NOT NULL,
  `wertung` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tsw_id`),
  UNIQUE KEY `tabellenstand_id` (`tabellenstand_id`,`wertung_category_id`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `teams` (
  `team_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `club_contact_id` int unsigned DEFAULT NULL,
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
  KEY `club_contact_id` (`club_contact_id`),
  KEY `berechtigung_kategorie_id` (`berechtigung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tournaments` (
  `tournament_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `turnierform_category_id` int unsigned NOT NULL,
  `bretter_min` tinyint unsigned DEFAULT NULL,
  `bretter_max` tinyint DEFAULT NULL,
  `runden` tinyint unsigned NOT NULL,
  `gastspieler` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `modus_category_id` int unsigned NOT NULL,
  `alter_min` tinyint unsigned DEFAULT NULL,
  `alter_max` tinyint unsigned DEFAULT NULL,
  `geschlecht` set('m','w') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'm,w',
  `dwz_min` smallint unsigned DEFAULT NULL,
  `dwz_max` smallint unsigned DEFAULT NULL,
  `elo_min` smallint unsigned DEFAULT NULL,
  `elo_max` smallint unsigned DEFAULT NULL,
  `pseudo_dwz` smallint unsigned DEFAULT NULL,
  `ratings_updated` date DEFAULT NULL,
  `teams_max` smallint unsigned DEFAULT NULL,
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
  `urkunde_parameter` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabellenstaende` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabellenstand_runde_no` tinyint unsigned DEFAULT NULL,
  `main_tournament_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`tournament_id`),
  UNIQUE KEY `termin_id` (`event_id`),
  KEY `modus_kategorie_id` (`modus_category_id`),
  KEY `turnierform_kategorie_id` (`turnierform_category_id`),
  KEY `main_tournament_id` (`main_tournament_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_bedenkzeiten` (
  `tb_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `phase` tinyint unsigned NOT NULL,
  `bedenkzeit_sec` smallint unsigned NOT NULL,
  `zeitbonus_sec` tinyint unsigned DEFAULT NULL,
  `zuege` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`tb_id`),
  KEY `tournament_id` (`tournament_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_kennungen` (
  `tk_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `kennung` varchar(15) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `kennung_category_id` int unsigned NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tk_id`),
  UNIQUE KEY `turnier_id_kennung_kategorie_id` (`tournament_id`,`kennung_category_id`),
  UNIQUE KEY `kennung_kennung_kategorie_id` (`kennung`,`kennung_category_id`),
  KEY `kennung_kategorie_id` (`kennung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_partien` (
  `tp_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `partien_pfad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tp_id`),
  KEY `turnier_id` (`tournament_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_status` (
  `turnier_status_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `status_category_id` int unsigned NOT NULL,
  PRIMARY KEY (`turnier_status_id`),
  UNIQUE KEY `turnier_id_status_kategorie_id` (`tournament_id`,`status_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `turniere_wertungen` (
  `tw_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `wertung_category_id` int unsigned NOT NULL,
  `reihenfolge` tinyint unsigned NOT NULL,
  `anzeigen` enum('immer','bei Gleichstand') CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL DEFAULT 'immer',
  PRIMARY KEY (`tw_id`),
  UNIQUE KEY `turnier_id` (`tournament_id`,`wertung_category_id`),
  KEY `reihenfolge` (`reihenfolge`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE FUNCTION `event_id`() RETURNS int
    NO SQL
    DETERMINISTIC
return @event_id;


CREATE OR REPLACE VIEW `partien_einzelergebnisse` AS
	SELECT `partie_id`, `partiestatus_category_id`, `event_id`, `runde_no`, `weiss_person_id` AS `person_id`, `schwarz_person_id` AS `gegner_id`, `weiss_ergebnis` AS `ergebnis`, `brett_no`
	FROM `partien`
	WHERE `event_id` = `event_id`()
	UNION ALL SELECT `partie_id`, `partiestatus_category_id`, `event_id`, `runde_no`, `schwarz_person_id` AS `person_id`, `weiss_person_id` AS `gegner_id`, `schwarz_ergebnis` AS `ergebnis`, `brett_no`
	FROM `partien`
	WHERE `event_id` = `event_id`();


CREATE OR REPLACE VIEW `buchholz_einzel_mit_kampflosen_view` AS
	SELECT `ergebnisse_gegner`.`partiestatus_category_id`, `ergebnisse`.`runde_no`, `ergebnisse`.`event_id`, `ergebnisse`.`person_id`, `ergebnisse`.`gegner_id`, `ergebnisse_gegner`.`ergebnis` AS `punkte`, `ergebnisse_gegner`.`runde_no` AS `runde_gegner`
	FROM `partien_einzelergebnisse` `ergebnisse`
	JOIN `partien_einzelergebnisse` `ergebnisse_gegner`
		ON `ergebnisse`.`event_id` = `ergebnisse_gegner`.`event_id`
		AND `ergebnisse`.`gegner_id` = `ergebnisse_gegner`.`person_id`
	ORDER BY `ergebnisse`.`person_id`, `ergebnisse`.`gegner_id`;


CREATE OR REPLACE VIEW `partien_ergebnisse_view` AS
	SELECT `partien`.`event_id`, `paarungen`.`heim_team_id` AS `team_id`, `paarungen`.`auswaerts_team_id` AS `gegner_team_id`, `partien`.`runde_no`, `partien`.`brett_no`, `partien`.`partie_id`, `partien`.`heim_wertung` AS `ergebnis`, `partien`.`auswaerts_wertung` AS `ergebnis_gegner`
	FROM `paarungen`
	LEFT JOIN `partien` USING (`paarung_id`)
	WHERE `partien`.`event_id` = `event_id`()
	UNION SELECT `partien`.`event_id`, `paarungen`.`auswaerts_team_id` AS `team_id`, `paarungen`.`heim_team_id` AS `gegner_team_id`, `partien`.`runde_no`, `partien`.`brett_no`, `partien`.`partie_id`, `partien`.`auswaerts_wertung` AS `ergebnis`, `partien`.`heim_wertung` AS `ergebnis_gegner`
	FROM `paarungen`
	LEFT JOIN `partien` USING (`paarung_id`)
	WHERE `partien`.`event_id` = `event_id`();


CREATE OR REPLACE VIEW `paarungen_ergebnisse_view` AS
	SELECT `partien_ergebnisse_view`.`event_id`, `partien_ergebnisse_view`.`team_id`, `partien_ergebnisse_view`.`gegner_team_id`, `partien_ergebnisse_view`.`runde_no`, 0 AS `kampflos`, SUM(`partien_ergebnisse_view`.`ergebnis`) AS `brettpunkte`, SUM(`partien_ergebnisse_view`.`ergebnis_gegner`) AS `brettpunkte_gegner`, (CASE WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) < SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 0 WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) > SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 2 WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) = SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 1 END) AS `mannschaftspunkte`, (CASE WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) < SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 2 WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) > SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 0 WHEN (SUM(`partien_ergebnisse_view`.`ergebnis`) = SUM(`partien_ergebnisse_view`.`ergebnis_gegner`)) THEN 1 END) AS `mannschaftspunkte_gegner`
	FROM `partien_ergebnisse_view`
	WHERE `event_id` = `event_id`()
	GROUP BY `event_id`, `team_id`, `runde_no`, `gegner_team_id`
	UNION SELECT `paarungen`.`event_id`, `paarungen`.`heim_team_id` AS `team_id`, `teams`.`team_id` AS `gegner_team_id`, `paarungen`.`runde_no`, 1 AS `kampflos`, `tournaments`.`bretter_min` AS `brettpunkte`, 0 AS `brettpunkte_gegner`, 2 AS `mannschaftspunkte`, 0 AS `mannschaftspunkte_gegner`
	FROM `paarungen`
	JOIN `teams`
		ON `teams`.`team_id` = `paarungen`.`auswaerts_team_id`
		AND `teams`.`spielfrei` = 'ja'
	JOIN `tournaments`
		ON `tournaments`.`event_id` = `paarungen`.`event_id`
	WHERE `paarungen`.`event_id` = `event_id`()
	UNION SELECT `paarungen`.`event_id`, `paarungen`.`auswaerts_team_id` AS `team_id`, `teams`.`team_id` AS `gegner_team_id`, `paarungen`.`runde_no`, 1 AS `kampflos`, `tournaments`.`bretter_min` AS `brettpunkte`, 0 AS `brettpunkte_gegner`, 2 AS `mannschaftspunkte`, 0 AS `mannschaftspunkte_gegner`
	FROM `paarungen`
	JOIN `teams`
		ON `teams`.`team_id` = `paarungen`.`heim_team_id`
		AND `teams`.`spielfrei` = 'ja'
	JOIN `tournaments`
		ON `tournaments`.`event_id` = `paarungen`.`event_id`
	WHERE `paarungen`.`event_id` = `event_id`();


CREATE OR REPLACE VIEW `tabellenstaende_termine_view` AS
	SELECT `teams`.`event_id`, `paarungen`.`runde_no`, `teams`.`team_id`
	FROM `paarungen`
	LEFT JOIN `teams` USING (`event_id`)
	WHERE `paarungen`.`event_id` = `event_id`()
	GROUP BY `event_id`, `runde_no`, `team_id`;


CREATE OR REPLACE VIEW `tabellenstaende_guv_view` AS
	SELECT `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`runde_no`, `tabellenstaende_termine_view`.`team_id`, SUM(IF((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 2),1,0)) AS `gewonnen`, SUM(IF((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 1),1,0)) AS `unentschieden`, SUM(IF((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 0),1,0)) AS `verloren`
	FROM `tabellenstaende_termine_view`
	LEFT JOIN `paarungen_ergebnisse_view`
		ON `paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`
		AND `paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`
		AND `paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`
	GROUP BY `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`runde_no`, `tabellenstaende_termine_view`.`team_id`;


CREATE OR REPLACE VIEW `buchholz_mit_kampflosen_view` AS
	SELECT `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`runde_no`, `tabellenstaende_termine_view`.`team_id`, SUM(IF((`gegners_paarungen`.`kampflos` = 1), 1, `gegners_paarungen`.`mannschaftspunkte`)) AS `buchholz_mit_korrektur`, SUM(`gegners_paarungen`.`mannschaftspunkte`) AS `buchholz`
	FROM `paarungen_ergebnisse_view`
	LEFT JOIN `tabellenstaende_termine_view`
		ON `paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`
		AND `paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`
		AND `paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`
	LEFT JOIN `paarungen_ergebnisse_view` `gegners_paarungen`
		ON `gegners_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`gegner_team_id`
		AND `gegners_paarungen`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`
	GROUP BY `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`team_id`, `tabellenstaende_termine_view`.`runde_no`
	UNION SELECT `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`runde_no`, `tabellenstaende_termine_view`.`team_id`, (SUM(`bisherige_paarungen`.`mannschaftspunkte`) + (`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`)) AS `buchholz_kampflos_mit_korrektur`,0 AS `buchholz_kampflos`
	FROM `paarungen_ergebnisse_view` `bisherige_paarungen`
	LEFT JOIN `paarungen_ergebnisse_view`
		ON `bisherige_paarungen`.`event_id` = `paarungen_ergebnisse_view`.`event_id`
		AND `bisherige_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`team_id`
		AND `bisherige_paarungen`.`runde_no` < `paarungen_ergebnisse_view`.`runde_no`
	LEFT JOIN `tabellenstaende_termine_view`
		ON `paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`
		AND `paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`
		AND `paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`
	WHERE `paarungen_ergebnisse_view`.`kampflos` = 1
	GROUP BY `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`team_id`, `tabellenstaende_termine_view`.`runde_no`, `paarungen_ergebnisse_view`.`runde_no`
	UNION SELECT `tabellenstaende_termine_view`.`event_id`, `tabellenstaende_termine_view`.`runde_no`, `tabellenstaende_termine_view`.`team_id`, (`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`) AS `buchholz_kampflos_mit_korrektur`, 0 AS `buchholz_kampflos`
	FROM `tabellenstaende_termine_view`
	JOIN `paarungen_ergebnisse_view`
		ON `paarungen_ergebnisse_view`.`event_id` = `tabellenstaende_termine_view`.`event_id`
		AND `paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`
		AND `paarungen_ergebnisse_view`.`runde_no` = 1
		AND `paarungen_ergebnisse_view`.`kampflos` = 1;

CREATE OR REPLACE VIEW `buchholz_view` AS
	SELECT `buchholz_mit_kampflosen_view`.`event_id`, `buchholz_mit_kampflosen_view`.`team_id`, `buchholz_mit_kampflosen_view`.`runde_no`, IFNULL(SUM(`buchholz_mit_kampflosen_view`.`buchholz_mit_korrektur`),0) AS `buchholz_mit_korrektur`, IFNULL(SUM(`buchholz_mit_kampflosen_view`.`buchholz`),0) AS `buchholz`
	FROM `buchholz_mit_kampflosen_view`
	GROUP BY `event_id`, `team_id`, `runde_no`;
