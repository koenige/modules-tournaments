<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017-2019 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierkennungen


$zz_sub['title'] = 'Turnierkennungen';
$zz_sub['table'] = 'turniere_kennungen';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tk_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'turnier_id';
$zz_sub['fields'][2]['type'] = 'select';
$zz_sub['fields'][2]['sql'] = 'SELECT turnier_id
		, CONCAT(termin, " ", YEAR(beginn)) AS turnier
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	ORDER BY beginn, kennung DESC';
$zz_sub['fields'][2]['display_field'] = 'turnier';
$zz_sub['fields'][2]['search'] = 'CONCAT(termin, " ", YEAR(beginn))';

$zz_sub['fields'][3]['field_name'] = 'kennung';
$zz_sub['fields'][3]['dont_copy'] = true;

$zz_sub['fields'][4]['title'] = 'Kategorie';
$zz_sub['fields'][4]['field_name'] = 'kennung_category_id';
$zz_sub['fields'][4]['type'] = 'select';
$zz_sub['fields'][4]['sql'] = sprintf('SELECT category_id, category
	FROM categories
	WHERE main_category_id = %d
	AND parameters LIKE "%%turnier=1%%"', $zz_setting['category_ids']['kennungen'][0]);
$zz_sub['fields'][4]['display_field'] = 'category';
$zz_sub['fields'][4]['key_field_name'] = 'category_id';
$zz_sub['fields'][4]['def_val_ignore'] = true;

$zz_sub['fields'][20]['title'] = 'Stand';
$zz_sub['fields'][20]['field_name'] = 'letzte_aenderung';
$zz_sub['fields'][20]['type'] = 'timestamp';
$zz_sub['fields'][20]['hide_in_list'] = true;


$zz_sub['sql'] = 'SELECT turniere_kennungen.*
		, CONCAT(termin, " ", YEAR(beginn)) AS turnier
		, category
	FROM turniere_kennungen
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_kennungen.kennung_category_id
';
$zz_sub['sqlorder'] = ' ORDER BY beginn, termin ASC';

$zz_sub['access'] = 'all';
