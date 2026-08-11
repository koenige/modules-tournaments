<?php 

/**
 * tournaments module
 * Categories per tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Categories per Tournament';
$zz['table'] = 'tournaments_categories';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tournament_category_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS tournament
	FROM tournaments
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'tournament';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin)))';

$zz['fields'][6]['title_tab'] = 'Seq.';
$zz['fields'][6]['field_name'] = 'sequence';
$zz['fields'][6]['type'] = 'number';
$zz['fields'][6]['hide_in_list_if_empty'] = true;

$zz['fields'][3]['field_name'] = 'category_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT category_id, category, description, main_category_id
	FROM categories
	ORDER BY sequence';
$zz['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz['fields'][3]['show_hierarchy_subtree'] = wrap_category_id('tournaments');
$zz['fields'][3]['display_field'] = 'category';
$zz['fields'][3]['hide_in_list'] = true;

$zz['fields'][4]['field_name'] = 'property';

$zz['fields'][5]['title'] = 'Type';
$zz['fields'][5]['field_name'] = 'type_category_id';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['sql'] = 'SELECT category_id, category
	FROM categories
	WHERE main_category_id = /*_ID categories tournaments _*/
	ORDER BY sequence, category';
$zz['fields'][5]['display_field'] = 'category';
$zz['fields'][5]['exclude_from_search'] = true;

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['hide_in_list'] = true;


$zz['sql'] = 'SELECT tournaments_categories.*
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS tournament
		, categories.category
		, type_cat.category AS type_category
	FROM tournaments_categories
	LEFT JOIN tournaments USING (tournament_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories USING (category_id)
	LEFT JOIN categories type_cat
		ON tournaments_categories.type_category_id = type_cat.category_id
';
$zz['sqlorder'] = ' ORDER BY date_begin, identifier DESC, categories.sequence, categories.category';

$zz['subselect']['sql'] = 'SELECT tournament_id, tournament_category_id
		, category_id, category, category_short, property
	FROM tournaments_categories
	LEFT JOIN categories USING (category_id)
';
$zz['subselect']['sql_translate'] = ['category_id' => 'categories', 'tournament_category_id' => 'tournaments_categories'];
$zz['subselect']['sql_ignore'] = ['tournament_category_id', 'category_id'];
$zz['subselect']['concat_fields'] = ' ';
$zz['subselect']['concat_rows'] = ', ';
$zz['unless']['export_mode']['subselect']['prefix'] = '<br><em>'.wrap_text('Category').': ';
$zz['unless']['export_mode']['subselect']['suffix'] = '</em>';
$zz['export_no_html'] = true;
