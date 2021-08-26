/**
 * tournaments module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/* 2021-06-27-1 */	DROP VIEW `tabellenstaende_view`;
/* 2021-08-26-1 */	CREATE TABLE `jobs` (`job_id` int unsigned NOT NULL AUTO_INCREMENT, `job_category_id` int unsigned NOT NULL, `event_id` int unsigned NOT NULL, `runde_no` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, `prioritaet` tinyint NOT NULL DEFAULT '0', `start` datetime DEFAULT NULL, `ende` datetime DEFAULT NULL, `erfolgreich` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein', `request` tinyint unsigned NOT NULL DEFAULT '1', PRIMARY KEY (`job_id`), UNIQUE KEY `job_kategorie_id_termin_id_runde_no_start` (`job_category_id`,`event_id`,`runde_no`,`start`), KEY `termin_id` (`event_id`), KEY `runde_no` (`runde_no`), KEY `prioritaet` (`prioritaet`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

