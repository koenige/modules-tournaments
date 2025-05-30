<?php 

/**
 * tournaments module
 * table script: tournament status
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2017, 2019-2021, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Turnierstatus';
$zz['table'] = 'turniere_status';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'turnier_status_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS turnier
	FROM tournaments
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin)))';

$zz['fields'][4]['title'] = 'Kategorie';
$zz['fields'][4]['field_name'] = 'status_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT category_id, category
	FROM categories
	WHERE main_category_id = /*_ID categories turnierstatus _*/';
$zz['fields'][4]['display_field'] = 'category';


$zz['sql'] = 'SELECT turniere_status.*
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS turnier
		, category
	FROM turniere_status
	LEFT JOIN tournaments USING (tournament_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_status.status_category_id
';
$zz['sqlorder'] = ' ORDER BY date_begin, event ASC';

$zz['access'] = 'all';
