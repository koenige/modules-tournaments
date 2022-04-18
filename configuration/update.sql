/**
 * tournaments module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/* 2021-06-27-1 */	DROP VIEW `tabellenstaende_view`;
/* 2021-08-26-1 */	CREATE TABLE `jobs` (`job_id` int unsigned NOT NULL AUTO_INCREMENT, `job_category_id` int unsigned NOT NULL, `event_id` int unsigned NOT NULL, `runde_no` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL, `prioritaet` tinyint NOT NULL DEFAULT '0', `start` datetime DEFAULT NULL, `ende` datetime DEFAULT NULL, `erfolgreich` enum('ja','nein') CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT 'nein', `request` tinyint unsigned NOT NULL DEFAULT '1', PRIMARY KEY (`job_id`), UNIQUE KEY `job_kategorie_id_termin_id_runde_no_start` (`job_category_id`,`event_id`,`runde_no`,`start`), KEY `termin_id` (`event_id`), KEY `runde_no` (`runde_no`), KEY `prioritaet` (`prioritaet`)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/* 2021-11-24-1 */	ALTER TABLE `jobs` CHANGE `job_id` `cronjob_id` int unsigned NOT NULL AUTO_INCREMENT FIRST, CHANGE `job_category_id` `cronjob_category_id` int unsigned NOT NULL AFTER `cronjob_id`, RENAME TO `cronjobs`;
/* 2021-11-24-2 */	ALTER TABLE `cronjobs` ADD UNIQUE `cronjob_category_id` (`cronjob_category_id`, `event_id`, `runde_no`, `start`), DROP INDEX `job_kategorie_id_termin_id_runde_no_start`;
/* 2021-11-24-3 */	UPDATE categories SET `category` = 'Cronjobs', `path` = 'cronjobs', `parameters` = REPLACE(`parameters`, 'alias=jobs', 'alias=cronjobs') WHERE path = 'jobs' OR parameters LIKE '%alias=jobs%';
/* 2021-11-24-4 */	UPDATE _relations SET `detail_table` = 'cronjobs', `detail_id_field` = 'cronjob_id' WHERE `detail_table` = 'jobs';
/* 2021-11-24-5 */	UPDATE _relations SET `detail_field` = 'cronjob_category_id' WHERE `detail_table` = 'cronjobs' AND `detail_field` = 'job_category_id';
/* 2022-04-18-1 */	ALTER TABLE `anmerkungen` CHANGE `teilnahme_id` `participation_id` int unsigned NULL AFTER `team_id`;
/* 2022-04-18-2 */	ALTER TABLE `anmerkungen` ADD INDEX `participation_id` (`participation_id`), DROP INDEX `teilnahme_id`;
/* 2022-04-18-3 */	UPDATE _relations SET `detail_field` = 'participation_id', `master_field` = 'participation_id' WHERE `detail_table` = 'anmerkungen' AND `detail_field` = 'teilnahme_id' AND `master_field` = 'teilnahme_id';
