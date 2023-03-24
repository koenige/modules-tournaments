<?php 

/**
 * tournaments module
 * table script: cron jobs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015, 2019-2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Cron Jobs';
$zz['table'] = 'cronjobs';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'cronjob_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Kategorie';
$zz['fields'][2]['field_name'] = 'cronjob_category_id';
$zz['fields'][2]['type'] = 'write_once';
$zz['fields'][2]['type_detail'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories';
$zz['fields'][2]['display_field'] = 'category';
$zz['fields'][2]['show_hierarchy'] = 'main_category_id';
$zz['fields'][2]['show_hierarchy_subtree'] = wrap_category_id('cronjobs');
$zz['fields'][2]['key_field_name'] = 'category_id';

$zz['fields'][3]['field_name'] = 'event_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT event_id
		, CONCAT(event, ", ", DATE_FORMAT(date_begin, "%d.%m.%Y")) AS event
	FROM events
	WHERE ISNULL(main_event_id)
';
$zz['fields'][3]['display_field'] = 'event';
$zz['fields'][3]['search'] = 'CONCAT(events.event, " ", IFNULL(event_year, YEAR(date_begin)))';

$zz['fields'][4]['title'] = 'Runde';
$zz['fields'][4]['field_name'] = 'runde_no';

$zz['fields'][5]['title'] = 'Priorität';
$zz['fields'][5]['field_name'] = 'prioritaet';
$zz['fields'][5]['type'] = 'number';
$zz['fields'][5]['null'] = true;

$zz['fields'][6]['field_name'] = 'start';
$zz['fields'][6]['type'] = 'datetime';

$zz['fields'][7]['field_name'] = 'ende';
$zz['fields'][7]['type'] = 'datetime';

$zz['fields'][8]['field_name'] = 'erfolgreich';
$zz['fields'][8]['type'] = 'select';
$zz['fields'][8]['enum'] = ['ja', 'nein'];
$zz['fields'][8]['default'] = 'nein';


$zz['sql'] = 'SELECT cronjobs.*, categories.category
		, CONCAT(events.event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
	FROM cronjobs
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = cronjobs.cronjob_category_id
';
$zz['sqlorder'] = ' ORDER BY category, IF(ISNULL(cronjobs.start), 0, 1), IF(ISNULL(cronjobs.ende), 0, 1), cronjobs.start DESC, cronjobs.ende DESC, prioritaet ASC, cronjob_id';

$zz['filter'][1]['title'] = 'Kategorie';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'cronjob_category_id';
$zz['filter'][1]['sql'] = 'SELECT DISTINCT category_id, category
	FROM categories
	JOIN cronjobs
		ON categories.category_id = cronjobs.cronjob_category_id
	ORDER BY category';

wrap_setting('zzform_logging', false);
