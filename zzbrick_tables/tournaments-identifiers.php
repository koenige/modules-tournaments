<?php 

/**
 * tournaments module
 * table script: tournament identifiers
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2015, 2017-2021, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Tournament Identifiers';
$zz['table'] = 'tournaments_identifiers';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tournament_identifier_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
	FROM tournaments
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'event';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin)))';

$zz['fields'][3]['field_name'] = 'identifier';
$zz['fields'][3]['dont_copy'] = true;

$zz['fields'][4]['title'] = 'Category';
$zz['fields'][4]['field_name'] = 'identifier_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT category_id, category
	FROM categories
	WHERE main_category_id = /*_ID categories identifiers _*/
	AND parameters LIKE "%tournaments_identifier=1%"';
$zz['fields'][4]['display_field'] = 'category';
$zz['fields'][4]['key_field_name'] = 'category_id';
$zz['fields'][4]['def_val_ignore'] = true;

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;


$zz['sql'] = 'SELECT tournaments_identifiers.*
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
		, category
	FROM tournaments_identifiers
	LEFT JOIN tournaments USING (tournament_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = tournaments_identifiers.identifier_category_id
';
$zz['sqlorder'] = ' ORDER BY date_begin, event ASC';

$zz['access'] = 'all';
