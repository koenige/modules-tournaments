<?php 

/**
 * tournaments module
 * table script: standings scores
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012, 2014, 2019-2021, 2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Scores';
$zz['table'] = 'standings_scores';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'standing_score_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'standing_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT standing_id, standing_id
	FROM standings
	ORDER BY standing_id';

$zz['fields'][4]['field_name'] = 'score';
$zz['fields'][4]['null'] = true;

$zz['fields'][3]['title'] = 'Score';
$zz['fields'][3]['field_name'] = 'score_category_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['null'] = true;
$zz['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence, category';
$zz['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz['fields'][3]['display_field'] = 'category';
$zz['fields'][3]['show_hierarchy_subtree'] = wrap_category_id('turnierwertungen');

$zz['unique'][] = ['standing_id', 'score_category_id'];

$zz['sql'] = 'SELECT standings_scores.*
		, category
	FROM standings_scores
	LEFT JOIN standings USING (standing_id)
	LEFT JOIN categories
		ON categories.category_id = standings_scores.score_category_id
';
$zz['sqlorder'] = ' ORDER BY standing_id, category';
