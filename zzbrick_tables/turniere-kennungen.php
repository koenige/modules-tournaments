<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017-2021 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierkennungen


$zz['title'] = 'Turnierkennungen';
$zz['table'] = 'turniere_kennungen';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tk_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'turnier_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT turnier_id
		, CONCAT(event, " ", YEAR(date_begin)) AS turnier
	FROM turniere
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", YEAR(date_begin))';

$zz['fields'][3]['field_name'] = 'kennung';
$zz['fields'][3]['dont_copy'] = true;

$zz['fields'][4]['title'] = 'Kategorie';
$zz['fields'][4]['field_name'] = 'kennung_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = sprintf('SELECT category_id, category
	FROM categories
	WHERE main_category_id = %d
	AND parameters LIKE "%%turnier=1%%"', wrap_category_id('kennungen'));
$zz['fields'][4]['display_field'] = 'category';
$zz['fields'][4]['key_field_name'] = 'category_id';
$zz['fields'][4]['def_val_ignore'] = true;

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;


$zz['sql'] = 'SELECT turniere_kennungen.*
		, CONCAT(event, " ", YEAR(date_begin)) AS turnier
		, category
	FROM turniere_kennungen
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_kennungen.kennung_category_id
';
$zz['sqlorder'] = ' ORDER BY date_begin, event ASC';

$zz['access'] = 'all';
