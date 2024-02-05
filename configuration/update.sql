/**
 * tournaments module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021-2024 Gustaf Mossakowski
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
/* 2022-04-19-1 */	UPDATE _relations SET `master_table` = 'participations' WHERE `master_table` = 'teilnahmen';
/* 2022-04-21-1 */	ALTER TABLE `teams` CHANGE `kennung` `identifier` varchar(63) COLLATE 'latin1_general_ci' NOT NULL AFTER `team_no`;
/* 2022-04-21-2 */	ALTER TABLE `teams` ADD UNIQUE `identifier` (`identifier`), DROP INDEX `kennung`;
/* 2022-06-13-1 */	DROP VIEW `buchholz_einzel_mit_kampflosen_view`;
/* 2022-07-25-1 */	DROP TABLE `turniere_partien`;
/* 2022-07-25-2 */	DELETE FROM `_relations` WHERE `detail_table` = 'turniere_partien';
/* 2023-01-08-1 */	INSERT INTO categories (`category`, `description`, `main_category_id`, `path`, `parameters`, `sequence`, `last_update`, `glossary`) VALUES ('Note Registration Form', 'Notice for registration, which is at the end of the registration form.', (SELECT category_id FROM categories c WHERE path = 'event-texts' OR parameters LIKE '%&alias=event-texts&%'), 'event-texts/note-registration-form', '&alias=event-texts/note-registration-form&module=tournaments&team=1', 5, NOW(), 'no');
/* 2023-01-08-2 */	INSERT INTO categories (`category`, `description`, `main_category_id`, `path`, `parameters`, `sequence`, `last_update`, `glossary`) VALUES ('Note Lineup', 'Information on what to consider during lineup.', (SELECT category_id FROM categories c WHERE path = 'event-texts' OR parameters LIKE '%&alias=event-texts&%'), 'event-texts/note-lineup', '&alias=event-texts/note-lineup&module=tournaments&team=1', 4, NOW(), 'no');
/* 2023-01-08-3 */	INSERT INTO eventtexts (event_id, eventtext, eventtext_category_id, published) SELECT event_id, hinweis_aufstellung, (SELECT category_id FROM categories WHERE path = 'event-texts/note-lineup' OR parameters LIKE '%&alias=event-texts/note-lineup%'), 'yes' FROM tournaments WHERE NOT ISNULL(hinweis_aufstellung);
/* 2023-01-08-4 */	INSERT INTO eventtexts (event_id, eventtext, eventtext_category_id, published) SELECT event_id, hinweis_meldebogen, (SELECT category_id FROM categories WHERE path = 'event-texts/note-registration-form' OR parameters LIKE '%&alias=event-texts/note-registration-form%'), 'yes' FROM tournaments WHERE NOT ISNULL(hinweis_meldebogen);
/* 2023-01-08-5 */	ALTER TABLE `tournaments` DROP `hinweis_aufstellung`, DROP `hinweis_meldebogen`;
/* 2023-03-28-1 */	UPDATE webpages SET content = REPLACE(content, '%%% forms turniere ', '%%% forms tournaments ') WHERE content LIKE '%\%\%\% forms turniere %';
/* 2023-04-26-1 */	ALTER TABLE `cronjobs` RENAME TO `_jobqueue`;
/* 2023-04-26-2 */	UPDATE `_relations` SET `detail_table` = '_jobqueue' WHERE `detail_table` = 'cronjobs';
/* 2023-04-26-3 */	ALTER TABLE `_jobqueue` CHANGE `cronjob_id` `job_id` int unsigned NOT NULL AUTO_INCREMENT FIRST;
/* 2023-04-26-4 */	UPDATE `_relations` SET `detail_id_field` = 'job_id' WHERE `detail_table` = '_jobqueue';
/* 2023-04-26-5 */	ALTER TABLE `_jobqueue` CHANGE `cronjob_category_id` `job_category_id` int unsigned NOT NULL AFTER `job_id`;
/* 2023-04-26-6 */	ALTER TABLE `_jobqueue` ADD UNIQUE `job_category_id` (`job_category_id`, `event_id`, `runde_no`, `start`), DROP INDEX `cronjob_category_id`;
/* 2023-04-26-7 */	UPDATE `_relations` SET `detail_field` = 'job_category_id' WHERE `detail_field` = 'cronjob_category_id';
/* 2023-04-26-8 */	ALTER TABLE `_jobqueue` CHANGE `prioritaet` `priority` tinyint NOT NULL DEFAULT '0' AFTER `runde_no`, CHANGE `start` `started` datetime NULL AFTER `priority`, CHANGE `ende` `finished` datetime NULL AFTER `started`, CHANGE `request` `job_category_no` tinyint unsigned NOT NULL DEFAULT '1' AFTER `erfolgreich`;
/* 2023-04-26-9 */	ALTER TABLE `_jobqueue` ADD INDEX `priority` (`priority`), DROP INDEX `prioritaet`;
/* 2023-04-26-10 */	ALTER TABLE `_jobqueue` ADD `job_status` enum('not_started','running','successful','failed','abandoned') COLLATE 'latin1_general_ci' NULL DEFAULT 'not_started' AFTER `erfolgreich`;
/* 2023-04-26-11 */	UPDATE _jobqueue SET job_status = 'successful' WHERE erfolgreich = 'ja';
/* 2023-04-26-12 */	ALTER TABLE `_jobqueue` DROP `erfolgreich`;
/* 2023-04-26-13 */	ALTER TABLE `_jobqueue` ADD `job_url` varchar(255) COLLATE 'latin1_general_ci' NOT NULL AFTER `job_category_id`, ADD `created` datetime NULL AFTER `priority`, ADD `wait_until` datetime NULL AFTER `finished`, ADD `try_no` tinyint unsigned NOT NULL DEFAULT '0' AFTER `job_status`, ADD `error_msg` text COLLATE 'utf8mb4_unicode_ci' NULL AFTER `try_no`;
/* 2023-04-26-14 */	UPDATE _jobqueue SET created = NOW() WHERE ISNULL(created);
/* 2023-04-26-15 */	ALTER TABLE `_jobqueue` CHANGE `created` `created` datetime NOT NULL AFTER `priority`;
/* 2023-04-26-16 */	UPDATE _jobqueue LEFT JOIN events USING (event_id) LEFT JOIN categories ON _jobqueue.job_category_id = categories.category_id SET job_url = CONCAT('/_', path, '/', identifier, '/', _jobqueue.runde_no, '/') WHERE NOT ISNULL(_jobqueue.runde_no);
/* 2023-04-26-17 */	UPDATE _jobqueue LEFT JOIN events USING (event_id) LEFT JOIN categories ON _jobqueue.job_category_id = categories.category_id SET job_url = CONCAT('/_', path, '/', identifier, '/') WHERE ISNULL(_jobqueue.runde_no);
/* 2023-04-26-18 */	ALTER TABLE `_jobqueue` ADD UNIQUE `job_category_id_job_url_started` (`job_category_id`, `job_url`, `started`), DROP INDEX `job_category_id`, DROP INDEX `termin_id`, DROP INDEX `runde_no`;
/* 2023-04-26-19 */	ALTER TABLE `_jobqueue` DROP `event_id`, DROP `runde_no`;
/* 2023-04-26-20 */	DELETE FROM _relations WHERE master_table = 'events' AND detail_table = '_jobqueue';
/* 2023-04-26-21 */	UPDATE categories SET `category` = 'Jobs', `path` = 'jobs', `parameters` = '&alias=jobs' WHERE `path` = 'cronjobs';
/* 2023-04-26-22 */	UPDATE categories SET `path` = REPLACE(path, 'cronjobs/', 'jobs/') WHERE `path` LIKE 'cronjobs/%';
/* 2023-04-26-23 */	UPDATE categories SET `parameters` = REPLACE(parameters, 'alias=cronjobs', 'alias=jobs') WHERE `parameters` LIKE '%alias=cronjobs%';
/* 2023-07-22-1 */	UPDATE webpages SET content = REPLACE(content, '%%% forms runde *', '%%% forms rounds *') WHERE content LIKE '%\%\%\% forms runde *%';
/* 2023-09-25-1 */	UPDATE webpages SET content = REPLACE(content, '%%% request landesverband', '%%% request federation') WHERE content LIKE '%\%\%\% request landesverband%';
/* 2023-09-25-2 */	UPDATE webpages SET content = REPLACE(content, '%%% request landesverbaende', '%%% request federations') WHERE content LIKE '%\%\%\% request landesverbaende%';
/* 2024-02-05-1 */	DROP VIEW `buchholz_view`;
