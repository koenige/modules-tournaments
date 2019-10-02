<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012, 2014, 2019 Gustaf Mossakowski <gustaf@koenige.org>
// Tabellenstände/Wertungen


$zz_sub['title'] = 'Tabellenstände/Wertungen';
$zz_sub['table'] = 'tabellenstaende_wertungen';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tsw_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'tabellenstand_id';
$zz_sub['fields'][2]['type'] = 'select';
$zz_sub['fields'][2]['sql'] = 'SELECT tabellenstand_id, tabellenstand_id
	FROM tabellenstaende
	ORDER BY tabellenstand_id';

$zz_sub['fields'][4]['field_name'] = 'wertung';
$zz_sub['fields'][4]['null'] = true;

$zz_sub['fields'][3]['title'] = 'Wertung';
$zz_sub['fields'][3]['field_name'] = 'wertung_category_id';
$zz_sub['fields'][3]['key_field_name'] = 'category_id';
$zz_sub['fields'][3]['type'] = 'select';
$zz_sub['fields'][3]['null'] = true;
$zz_sub['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence, category';
$zz_sub['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz_sub['fields'][3]['display_field'] = 'category';
$zz_sub['fields'][3]['show_hierarchy_subtree'] = $zz_setting['category_ids']['turnierwertungen'][0];

$zz_sub['unique'][] = array('tabellenstand_id', 'wertung_category_id');

$zz_sub['sql'] = 'SELECT tabellenstaende_wertungen.*
		, category
	FROM tabellenstaende_wertungen
	LEFT JOIN tabellenstaende USING (tabellenstand_id)
	LEFT JOIN categories
		ON categories.category_id = tabellenstaende_wertungen.wertung_category_id
';
$zz_sub['sqlorder'] = ' ORDER BY tabellenstand_id, category';
