-- MySQL dump 10.13  Distrib 8.0.18, for osx10.13 (x86_64)
--
-- Host: localhost    Database: dsj
-- ------------------------------------------------------
-- Server version	8.0.18

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary view structure for view `buchholz_einzel_mit_kampflosen_view`
--

DROP TABLE IF EXISTS `buchholz_einzel_mit_kampflosen_view`;
/*!50001 DROP VIEW IF EXISTS `buchholz_einzel_mit_kampflosen_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `buchholz_einzel_mit_kampflosen_view` AS SELECT 
 1 AS `partiestatus_category_id`,
 1 AS `runde_no`,
 1 AS `termin_id`,
 1 AS `person_id`,
 1 AS `gegner_id`,
 1 AS `punkte`,
 1 AS `runde_gegner`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `buchholz_mit_kampflosen_view`
--

DROP TABLE IF EXISTS `buchholz_mit_kampflosen_view`;
/*!50001 DROP VIEW IF EXISTS `buchholz_mit_kampflosen_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `buchholz_mit_kampflosen_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `runde_no`,
 1 AS `team_id`,
 1 AS `buchholz_mit_korrektur`,
 1 AS `buchholz`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `buchholz_view`
--

DROP TABLE IF EXISTS `buchholz_view`;
/*!50001 DROP VIEW IF EXISTS `buchholz_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `buchholz_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `team_id`,
 1 AS `runde_no`,
 1 AS `buchholz_mit_korrektur`,
 1 AS `buchholz`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `paarungen`
--

DROP TABLE IF EXISTS `paarungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paarungen` (
  `paarung_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `termin_id` int(10) unsigned NOT NULL,
  `runde_no` tinyint(3) unsigned NOT NULL,
  `place_contact_id` int(10) unsigned DEFAULT NULL,
  `spielbeginn` time DEFAULT NULL,
  `tisch_no` tinyint(3) unsigned NOT NULL,
  `heim_team_id` int(10) unsigned DEFAULT NULL,
  `auswaerts_team_id` int(10) unsigned DEFAULT NULL,
  `kommentar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `letzte_aenderung` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`paarung_id`),
  UNIQUE KEY `runde_no` (`termin_id`,`runde_no`,`tisch_no`),
  UNIQUE KEY `runde_termin_id` (`termin_id`,`heim_team_id`,`auswaerts_team_id`),
  KEY `ort_id` (`place_contact_id`),
  KEY `heim_team_id` (`heim_team_id`),
  KEY `auswaerts_team_id` (`auswaerts_team_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `paarungen_ergebnisse_view`
--

DROP TABLE IF EXISTS `paarungen_ergebnisse_view`;
/*!50001 DROP VIEW IF EXISTS `paarungen_ergebnisse_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `paarungen_ergebnisse_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `team_id`,
 1 AS `gegner_team_id`,
 1 AS `runde_no`,
 1 AS `kampflos`,
 1 AS `brettpunkte`,
 1 AS `brettpunkte_gegner`,
 1 AS `mannschaftspunkte`,
 1 AS `mannschaftspunkte_gegner`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `partien`
--

DROP TABLE IF EXISTS `partien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partien` (
  `partie_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `termin_id` int(10) unsigned NOT NULL,
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
  `letzte_aenderung` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`partie_id`),
  KEY `paarung_id` (`paarung_id`),
  KEY `termin_id` (`termin_id`),
  KEY `weiss_person_id` (`weiss_person_id`),
  KEY `schwarz_person_id` (`schwarz_person_id`),
  KEY `partiestatus_kategorie_id` (`partiestatus_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `partien_einzelergebnisse`
--

DROP TABLE IF EXISTS `partien_einzelergebnisse`;
/*!50001 DROP VIEW IF EXISTS `partien_einzelergebnisse`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `partien_einzelergebnisse` AS SELECT 
 1 AS `partie_id`,
 1 AS `partiestatus_category_id`,
 1 AS `termin_id`,
 1 AS `runde_no`,
 1 AS `person_id`,
 1 AS `gegner_id`,
 1 AS `ergebnis`,
 1 AS `brett_no`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `partien_ergebnisse_view`
--

DROP TABLE IF EXISTS `partien_ergebnisse_view`;
/*!50001 DROP VIEW IF EXISTS `partien_ergebnisse_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `partien_ergebnisse_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `team_id`,
 1 AS `gegner_team_id`,
 1 AS `runde_no`,
 1 AS `brett_no`,
 1 AS `partie_id`,
 1 AS `ergebnis`,
 1 AS `ergebnis_gegner`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `tabellenstaende`
--

DROP TABLE IF EXISTS `tabellenstaende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tabellenstaende` (
  `tabellenstand_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `termin_id` int(10) unsigned NOT NULL,
  `runde_no` tinyint(3) unsigned NOT NULL,
  `team_id` int(10) unsigned DEFAULT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `platz_no` tinyint(3) unsigned NOT NULL,
  `platz_brett_no` tinyint(3) unsigned DEFAULT NULL,
  `spiele_g` tinyint(3) unsigned DEFAULT NULL,
  `spiele_u` tinyint(3) unsigned DEFAULT NULL,
  `spiele_v` tinyint(3) unsigned DEFAULT NULL,
  `letzte_aenderung` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tabellenstand_id`),
  UNIQUE KEY `team_id_termin_id_runde_no` (`team_id`,`termin_id`,`runde_no`),
  UNIQUE KEY `person_id_termin_id_runde_no` (`person_id`,`termin_id`,`runde_no`),
  KEY `termin_id` (`termin_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `tabellenstaende_guv_view`
--

DROP TABLE IF EXISTS `tabellenstaende_guv_view`;
/*!50001 DROP VIEW IF EXISTS `tabellenstaende_guv_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `tabellenstaende_guv_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `runde_no`,
 1 AS `team_id`,
 1 AS `gewonnen`,
 1 AS `unentschieden`,
 1 AS `verloren`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `tabellenstaende_termine_view`
--

DROP TABLE IF EXISTS `tabellenstaende_termine_view`;
/*!50001 DROP VIEW IF EXISTS `tabellenstaende_termine_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `tabellenstaende_termine_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `runde_no`,
 1 AS `team_id`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `tabellenstaende_view`
--

DROP TABLE IF EXISTS `tabellenstaende_view`;
/*!50001 DROP VIEW IF EXISTS `tabellenstaende_view`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `tabellenstaende_view` AS SELECT 
 1 AS `termin_id`,
 1 AS `runde_no`,
 1 AS `team_id`,
 1 AS `reihenfolge`,
 1 AS `wertung_category_id`,
 1 AS `wertung`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `tabellenstaende_wertungen`
--

DROP TABLE IF EXISTS `tabellenstaende_wertungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tabellenstaende_wertungen` (
  `tsw_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tabellenstand_id` int(10) unsigned NOT NULL,
  `wertung_category_id` int(10) unsigned NOT NULL,
  `wertung` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tsw_id`),
  UNIQUE KEY `tabellenstand_id` (`tabellenstand_id`,`wertung_category_id`),
  KEY `wertung_kategorie_id` (`wertung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere`
--

DROP TABLE IF EXISTS `turniere`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turniere` (
  `turnier_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `termin_id` int(10) unsigned NOT NULL,
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
  `urkunde_id` int(10) unsigned DEFAULT NULL,
  `urkunde_ort` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_datum` date DEFAULT NULL,
  `urkunde_unterschrift1` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_unterschrift2` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `urkunde_parameter` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tabellenstaende` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabellenstand_runde_no` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`turnier_id`),
  UNIQUE KEY `termin_id` (`termin_id`),
  KEY `modus_kategorie_id` (`modus_category_id`),
  KEY `turnierform_kategorie_id` (`turnierform_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere_bedenkzeiten`
--

DROP TABLE IF EXISTS `turniere_bedenkzeiten`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turniere_bedenkzeiten` (
  `tb_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `phase` tinyint(3) unsigned NOT NULL,
  `bedenkzeit_sec` smallint(5) unsigned NOT NULL,
  `zeitbonus_sec` tinyint(3) unsigned DEFAULT NULL,
  `zuege` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`tb_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere_kennungen`
--

DROP TABLE IF EXISTS `turniere_kennungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turniere_kennungen` (
  `tk_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `kennung` varchar(15) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `kennung_category_id` int(10) unsigned NOT NULL,
  `letzte_aenderung` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tk_id`),
  UNIQUE KEY `turnier_id_kennung_kategorie_id` (`turnier_id`,`kennung_category_id`),
  UNIQUE KEY `kennung_kennung_kategorie_id` (`kennung`,`kennung_category_id`),
  KEY `kennung_kategorie_id` (`kennung_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere_partien`
--

DROP TABLE IF EXISTS `turniere_partien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turniere_partien` (
  `tp_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `partien_pfad` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tp_id`),
  KEY `turnier_id` (`turnier_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere_status`
--

DROP TABLE IF EXISTS `turniere_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turniere_status` (
  `turnier_status_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `turnier_id` int(10) unsigned NOT NULL,
  `status_category_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`turnier_status_id`),
  UNIQUE KEY `turnier_id_status_kategorie_id` (`turnier_id`,`status_category_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `turniere_wertungen`
--

DROP TABLE IF EXISTS `turniere_wertungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'tournaments'
--
/*!50003 DROP FUNCTION IF EXISTS `termin_id` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `termin_id`() RETURNS int(11)
    NO SQL
    DETERMINISTIC
return @termin_id ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `buchholz_einzel_mit_kampflosen_view`
--

/*!50001 DROP VIEW IF EXISTS `buchholz_einzel_mit_kampflosen_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `buchholz_einzel_mit_kampflosen_view` AS select `ergebnisse_gegner`.`partiestatus_category_id` AS `partiestatus_category_id`,`ergebnisse`.`runde_no` AS `runde_no`,`ergebnisse`.`termin_id` AS `termin_id`,`ergebnisse`.`person_id` AS `person_id`,`ergebnisse`.`gegner_id` AS `gegner_id`,`ergebnisse_gegner`.`ergebnis` AS `punkte`,`ergebnisse_gegner`.`runde_no` AS `runde_gegner` from (`partien_einzelergebnisse` `ergebnisse` join `partien_einzelergebnisse` `ergebnisse_gegner` on(((`ergebnisse`.`termin_id` = `ergebnisse_gegner`.`termin_id`) and (`ergebnisse`.`gegner_id` = `ergebnisse_gegner`.`person_id`)))) order by `ergebnisse`.`person_id`,`ergebnisse`.`gegner_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `buchholz_mit_kampflosen_view`
--

/*!50001 DROP VIEW IF EXISTS `buchholz_mit_kampflosen_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `buchholz_mit_kampflosen_view` AS select `tabellenstaende_termine_view`.`termin_id` AS `termin_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,sum(if((`gegners_paarungen`.`kampflos` = 1),1,`gegners_paarungen`.`mannschaftspunkte`)) AS `buchholz_mit_korrektur`,sum(`gegners_paarungen`.`mannschaftspunkte`) AS `buchholz` from ((`paarungen_ergebnisse_view` left join `tabellenstaende_termine_view` on(((`paarungen_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) left join `paarungen_ergebnisse_view` `gegners_paarungen` on(((`gegners_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`gegner_team_id`) and (`gegners_paarungen`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`termin_id`,`tabellenstaende_termine_view`.`team_id`,`tabellenstaende_termine_view`.`runde_no` union select `tabellenstaende_termine_view`.`termin_id` AS `termin_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,(sum(`bisherige_paarungen`.`mannschaftspunkte`) + (`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`)) AS `buchholz_kampflos_mit_korrektur`,0 AS `buchholz_kampflos` from (`paarungen_ergebnisse_view` `bisherige_paarungen` left join (`paarungen_ergebnisse_view` left join `tabellenstaende_termine_view` on(((`paarungen_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) on(((`bisherige_paarungen`.`termin_id` = `paarungen_ergebnisse_view`.`termin_id`) and (`bisherige_paarungen`.`team_id` = `paarungen_ergebnisse_view`.`team_id`) and (`bisherige_paarungen`.`runde_no` < `paarungen_ergebnisse_view`.`runde_no`)))) where (`paarungen_ergebnisse_view`.`kampflos` = 1) group by `tabellenstaende_termine_view`.`termin_id`,`tabellenstaende_termine_view`.`team_id`,`tabellenstaende_termine_view`.`runde_no`,`paarungen_ergebnisse_view`.`runde_no` union select `tabellenstaende_termine_view`.`termin_id` AS `termin_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,(`tabellenstaende_termine_view`.`runde_no` - `paarungen_ergebnisse_view`.`runde_no`) AS `buchholz_kampflos_mit_korrektur`,0 AS `buchholz_kampflos` from (`tabellenstaende_termine_view` join `paarungen_ergebnisse_view` on(((`paarungen_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` = 1) and (`paarungen_ergebnisse_view`.`kampflos` = 1)))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `buchholz_view`
--

/*!50001 DROP VIEW IF EXISTS `buchholz_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `buchholz_view` AS select `buchholz_mit_kampflosen_view`.`termin_id` AS `termin_id`,`buchholz_mit_kampflosen_view`.`team_id` AS `team_id`,`buchholz_mit_kampflosen_view`.`runde_no` AS `runde_no`,ifnull(sum(`buchholz_mit_kampflosen_view`.`buchholz_mit_korrektur`),0) AS `buchholz_mit_korrektur`,ifnull(sum(`buchholz_mit_kampflosen_view`.`buchholz`),0) AS `buchholz` from `buchholz_mit_kampflosen_view` group by `buchholz_mit_kampflosen_view`.`termin_id`,`buchholz_mit_kampflosen_view`.`team_id`,`buchholz_mit_kampflosen_view`.`runde_no` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `paarungen_ergebnisse_view`
--

/*!50001 DROP VIEW IF EXISTS `paarungen_ergebnisse_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `paarungen_ergebnisse_view` AS select `partien_ergebnisse_view`.`termin_id` AS `termin_id`,`partien_ergebnisse_view`.`team_id` AS `team_id`,`partien_ergebnisse_view`.`gegner_team_id` AS `gegner_team_id`,`partien_ergebnisse_view`.`runde_no` AS `runde_no`,0 AS `kampflos`,sum(`partien_ergebnisse_view`.`ergebnis`) AS `brettpunkte`,sum(`partien_ergebnisse_view`.`ergebnis_gegner`) AS `brettpunkte_gegner`,(case when (sum(`partien_ergebnisse_view`.`ergebnis`) < sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 0 when (sum(`partien_ergebnisse_view`.`ergebnis`) > sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 2 when (sum(`partien_ergebnisse_view`.`ergebnis`) = sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 1 end) AS `mannschaftspunkte`,(case when (sum(`partien_ergebnisse_view`.`ergebnis`) < sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 2 when (sum(`partien_ergebnisse_view`.`ergebnis`) > sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 0 when (sum(`partien_ergebnisse_view`.`ergebnis`) = sum(`partien_ergebnisse_view`.`ergebnis_gegner`)) then 1 end) AS `mannschaftspunkte_gegner` from `partien_ergebnisse_view` where (`partien_ergebnisse_view`.`termin_id` = `termin_id`()) group by `partien_ergebnisse_view`.`termin_id`,`partien_ergebnisse_view`.`team_id`,`partien_ergebnisse_view`.`runde_no`,`partien_ergebnisse_view`.`gegner_team_id` union select `paarungen`.`termin_id` AS `termin_id`,`paarungen`.`heim_team_id` AS `team_id`,`teams`.`team_id` AS `gegner_team_id`,`paarungen`.`runde_no` AS `runde_no`,1 AS `kampflos`,`turniere`.`bretter_min` AS `brettpunkte`,0 AS `brettpunkte_gegner`,2 AS `mannschaftspunkte`,0 AS `mannschaftspunkte_gegner` from ((`paarungen` join `teams` on(((`teams`.`team_id` = `paarungen`.`auswaerts_team_id`) and (`teams`.`spielfrei` = 'ja')))) join `turniere` on((`turniere`.`termin_id` = `paarungen`.`termin_id`))) where (`paarungen`.`termin_id` = `termin_id`()) union select `paarungen`.`termin_id` AS `termin_id`,`paarungen`.`auswaerts_team_id` AS `team_id`,`teams`.`team_id` AS `gegner_team_id`,`paarungen`.`runde_no` AS `runde_no`,1 AS `kampflos`,`turniere`.`bretter_min` AS `brettpunkte`,0 AS `brettpunkte_gegner`,2 AS `mannschaftspunkte`,0 AS `mannschaftspunkte_gegner` from ((`paarungen` join `teams` on(((`teams`.`team_id` = `paarungen`.`heim_team_id`) and (`teams`.`spielfrei` = 'ja')))) join `turniere` on((`turniere`.`termin_id` = `paarungen`.`termin_id`))) where (`paarungen`.`termin_id` = `termin_id`()) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `partien_einzelergebnisse`
--

/*!50001 DROP VIEW IF EXISTS `partien_einzelergebnisse`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `partien_einzelergebnisse` AS select `partien`.`partie_id` AS `partie_id`,`partien`.`partiestatus_category_id` AS `partiestatus_category_id`,`partien`.`termin_id` AS `termin_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`weiss_person_id` AS `person_id`,`partien`.`schwarz_person_id` AS `gegner_id`,`partien`.`weiss_ergebnis` AS `ergebnis`,`partien`.`brett_no` AS `brett_no` from `partien` where (`partien`.`termin_id` = `termin_id`()) union all select `partien`.`partie_id` AS `partie_id`,`partien`.`partiestatus_category_id` AS `partiestatus_category_id`,`partien`.`termin_id` AS `termin_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`schwarz_person_id` AS `person_id`,`partien`.`weiss_person_id` AS `gegner_id`,`partien`.`schwarz_ergebnis` AS `ergebnis`,`partien`.`brett_no` AS `brett_no` from `partien` where (`partien`.`termin_id` = `termin_id`()) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `partien_ergebnisse_view`
--

/*!50001 DROP VIEW IF EXISTS `partien_ergebnisse_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `partien_ergebnisse_view` AS select `partien`.`termin_id` AS `termin_id`,`paarungen`.`heim_team_id` AS `team_id`,`paarungen`.`auswaerts_team_id` AS `gegner_team_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`brett_no` AS `brett_no`,`partien`.`partie_id` AS `partie_id`,`partien`.`heim_wertung` AS `ergebnis`,`partien`.`auswaerts_wertung` AS `ergebnis_gegner` from (`paarungen` left join `partien` on((`paarungen`.`paarung_id` = `partien`.`paarung_id`))) where (`partien`.`termin_id` = `termin_id`()) union select `partien`.`termin_id` AS `termin_id`,`paarungen`.`auswaerts_team_id` AS `team_id`,`paarungen`.`heim_team_id` AS `gegner_team_id`,`partien`.`runde_no` AS `runde_no`,`partien`.`brett_no` AS `brett_no`,`partien`.`partie_id` AS `partie_id`,`partien`.`auswaerts_wertung` AS `ergebnis`,`partien`.`heim_wertung` AS `ergebnis_gegner` from (`paarungen` left join `partien` on((`paarungen`.`paarung_id` = `partien`.`paarung_id`))) where (`partien`.`termin_id` = `termin_id`()) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `tabellenstaende_guv_view`
--

/*!50001 DROP VIEW IF EXISTS `tabellenstaende_guv_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tabellenstaende_guv_view` AS select `tabellenstaende_termine_view`.`termin_id` AS `termin_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 2),1,0)) AS `gewonnen`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 1),1,0)) AS `unentschieden`,sum(if((`paarungen_ergebnisse_view`.`mannschaftspunkte` = 0),1,0)) AS `verloren` from (`tabellenstaende_termine_view` left join `paarungen_ergebnisse_view` on(((`paarungen_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`termin_id`,`tabellenstaende_termine_view`.`runde_no`,`tabellenstaende_termine_view`.`team_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `tabellenstaende_termine_view`
--

/*!50001 DROP VIEW IF EXISTS `tabellenstaende_termine_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tabellenstaende_termine_view` AS select `teams`.`termin_id` AS `termin_id`,`paarungen`.`runde_no` AS `runde_no`,`teams`.`team_id` AS `team_id` from (`paarungen` left join `teams` on((`paarungen`.`termin_id` = `teams`.`termin_id`))) where (`paarungen`.`termin_id` = `termin_id`()) group by `teams`.`termin_id`,`paarungen`.`runde_no`,`teams`.`team_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `tabellenstaende_view`
--

/*!50001 DROP VIEW IF EXISTS `tabellenstaende_view`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `tabellenstaende_view` AS select `tabellenstaende_termine_view`.`termin_id` AS `termin_id`,`tabellenstaende_termine_view`.`runde_no` AS `runde_no`,`tabellenstaende_termine_view`.`team_id` AS `team_id`,`turniere_wertungen`.`reihenfolge` AS `reihenfolge`,`turniere_wertungen`.`wertung_category_id` AS `wertung_category_id`,(case `turniere_wertungen`.`wertung_category_id` when 144 then sum(`paarungen_ergebnisse_view`.`mannschaftspunkte`) when 145 then sum(`paarungen_ergebnisse_view`.`brettpunkte`) when 146 then (select `buchholz_view`.`buchholz_mit_korrektur` from `buchholz_view` where ((`buchholz_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`buchholz_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`buchholz_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) when 215 then (select `buchholz_view`.`buchholz` from `buchholz_view` where ((`buchholz_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`buchholz_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`buchholz_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) when 147 then (select sum((case `partien_ergebnisse_view`.`ergebnis` when 1 then ((1 + `turniere`.`bretter_min`) - `partien_ergebnisse_view`.`brett_no`) when 0.5 then (((1 + `turniere`.`bretter_min`) - `partien_ergebnisse_view`.`brett_no`) / 2) when 0 then 0 end)) AS `berliner_wertung` from `partien_ergebnisse_view` where ((`partien_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`partien_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`) and (`partien_ergebnisse_view`.`team_id` = `paarungen_ergebnisse_view`.`team_id`))) when 150 then (select `tabellenstaende_guv_view`.`gewonnen` from `tabellenstaende_guv_view` where ((`tabellenstaende_guv_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`tabellenstaende_guv_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`tabellenstaende_guv_view`.`runde_no` = `tabellenstaende_termine_view`.`runde_no`))) end) AS `wertung` from (`paarungen_ergebnisse_view` left join (`turniere_wertungen` left join (`turniere` left join `tabellenstaende_termine_view` on((`turniere`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`))) on((`turniere_wertungen`.`turnier_id` = `turniere`.`turnier_id`))) on(((`paarungen_ergebnisse_view`.`termin_id` = `tabellenstaende_termine_view`.`termin_id`) and (`paarungen_ergebnisse_view`.`team_id` = `tabellenstaende_termine_view`.`team_id`) and (`paarungen_ergebnisse_view`.`runde_no` <= `tabellenstaende_termine_view`.`runde_no`)))) group by `tabellenstaende_termine_view`.`termin_id`,`tabellenstaende_termine_view`.`runde_no`,`tabellenstaende_termine_view`.`team_id`,`turniere_wertungen`.`reihenfolge`,`turniere_wertungen`.`wertung_category_id`,`paarungen_ergebnisse_view`.`team_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
