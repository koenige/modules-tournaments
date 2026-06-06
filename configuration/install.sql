/**
 * tournaments module
 * SQL for installation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- anmerkungen --
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'team_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'participations', 'participation_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'participation_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'persons', 'person_id', (SELECT DATABASE()), 'anmerkungen', 'anmerkung_id', 'autor_person_id', 'no-delete');


-- paarungen --
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
  KEY `place_contact_id` (`place_contact_id`),
  KEY `heim_team_id` (`heim_team_id`),
  KEY `auswaerts_team_id` (`auswaerts_team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'event_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'contacts', 'contact_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'place_contact_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'heim_team_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'paarungen', 'paarung_id', 'auswaerts_team_id', 'no-delete');


-- partien --
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'partien', 'partie_id', 'event_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'paarungen', 'paarung_id', (SELECT DATABASE()), 'partien', 'partie_id', 'paarung_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'partien', 'partie_id', 'partiestatus_category_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'persons', 'person_id', (SELECT DATABASE()), 'partien', 'partie_id', 'schwarz_person_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'persons', 'person_id', (SELECT DATABASE()), 'partien', 'partie_id', 'weiss_person_id', 'no-delete');


-- playermessages --
CREATE TABLE `playermessages` (
  `playermessage_id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `sender` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `participation_id` int NOT NULL,
  `created` datetime NOT NULL,
  `hash` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `verified` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `missing_image` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `processed` datetime DEFAULT NULL,
  PRIMARY KEY (`playermessage_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'participations', 'participation_id', (SELECT DATABASE()), 'playermessages', 'playermessage_id', 'participation_id', 'delete');


-- tabellenstaende --
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'tabellenstaende', 'tabellenstand_id', 'event_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'persons', 'person_id', (SELECT DATABASE()), 'tabellenstaende', 'tabellenstand_id', 'person_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'teams', 'team_id', (SELECT DATABASE()), 'tabellenstaende', 'tabellenstand_id', 'team_id', 'delete');


-- tabellenstaende_wertungen --
CREATE TABLE `tabellenstaende_wertungen` (
  `tsw_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tabellenstand_id` int unsigned NOT NULL,
  `wertung_category_id` int unsigned NOT NULL,
  `wertung` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tsw_id`),
  UNIQUE KEY `tabellenstand_id` (`tabellenstand_id`,`wertung_category_id`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tabellenstaende', 'tabellenstand_id', (SELECT DATABASE()), 'tabellenstaende_wertungen', 'tsw_id', 'tabellenstand_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'tabellenstaende_wertungen', 'tsw_id', 'wertung_category_id', 'no-delete');


-- teams --
CREATE TABLE `teams` (
  `team_id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL,
  `club_contact_id` int unsigned DEFAULT NULL,
  `team` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_no` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier` varchar(63) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
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
  UNIQUE KEY `identifier` (`identifier`),
  KEY `termin_id` (`event_id`),
  KEY `club_contact_id` (`club_contact_id`),
  KEY `berechtigung_kategorie_id` (`berechtigung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'teams', 'team_id', 'berechtigung_category_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'contacts', 'contact_id', (SELECT DATABASE()), 'teams', 'team_id', 'club_contact_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'teams', 'team_id', 'event_id', 'no-delete');


-- tournaments --
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
  `pairing_bye_scoring` enum('win','draw','none') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'win',
  `zimmerbuchung` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'ja',
  `teilnehmerliste` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein',
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'events', 'event_id', (SELECT DATABASE()), 'tournaments', 'tournament_id', 'event_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tournaments', 'tournament_id', (SELECT DATABASE()), 'tournaments', 'tournament_id', 'main_tournament_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'tournaments', 'tournament_id', 'modus_category_id', 'no-delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'tournaments', 'tournament_id', 'turnierform_category_id', 'no-delete');


-- turniere_bedenkzeiten --
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tournaments', 'tournament_id', (SELECT DATABASE()), 'turniere_bedenkzeiten', 'tb_id', 'tournament_id', 'delete');


-- tournaments_identifiers --
CREATE TABLE `tournaments_identifiers` (
  `tournament_identifier_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `identifier` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `identifier_category_id` int unsigned NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tournament_identifier_id`),
  UNIQUE KEY `tournament_id_identifier_category_id` (`tournament_id`,`identifier_category_id`),
  UNIQUE KEY `identifier_identifier_category_id` (`identifier`,`identifier_category_id`),
  KEY `identifier_category_id` (`identifier_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tournaments', 'tournament_id', (SELECT DATABASE()), 'tournaments_identifiers', 'tournament_identifier_id', 'tournament_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'tournaments_identifiers', 'tournament_identifier_id', 'identifier_category_id', 'no-delete');


-- turniere_status --
CREATE TABLE `turniere_status` (
  `turnier_status_id` int unsigned NOT NULL AUTO_INCREMENT,
  `tournament_id` int unsigned NOT NULL,
  `status_category_id` int unsigned NOT NULL,
  PRIMARY KEY (`turnier_status_id`),
  UNIQUE KEY `turnier_id_status_kategorie_id` (`tournament_id`,`status_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tournaments', 'tournament_id', (SELECT DATABASE()), 'turniere_status', 'turnier_status_id', 'tournament_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'turniere_status', 'turnier_status_id', 'status_category_id', 'no-delete');


-- turniere_wertungen --
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

INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'tournaments', 'tournament_id', (SELECT DATABASE()), 'turniere_wertungen', 'tw_id', 'tournament_id', 'delete');
INSERT INTO _relations (`master_db`, `master_table`, `master_field`, `detail_db`, `detail_table`, `detail_id_field`, `detail_field`, `delete`) VALUES ((SELECT DATABASE()), 'categories', 'category_id', (SELECT DATABASE()), 'turniere_wertungen', 'tw_id', 'wertung_category_id', 'no-delete');


-- views --
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
