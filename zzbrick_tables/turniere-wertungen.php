<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012, 2014-2015, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Turniere/Wertungen


$zz_sub['title'] = 'Turniere/Wertungen';
$zz_sub['table'] = 'turniere_wertungen';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tw_id';
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

$zz_sub['fields'][4]['title'] = 'Folge';
$zz_sub['fields'][4]['title_tab'] = 'Folge';
$zz_sub['fields'][4]['field_name'] = 'reihenfolge';
$zz_sub['fields'][4]['type'] = 'number';
$zz_sub['fields'][4]['auto_value'] = 'increment';
$zz_sub['fields'][4]['def_val_ignore'] = true;

$zz_sub['fields'][3]['title'] = 'Wertung';
$zz_sub['fields'][3]['field_name'] = 'wertung_category_id';
$zz_sub['fields'][3]['type'] = 'select';
$zz_sub['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence, category';
$zz_sub['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz_sub['fields'][3]['display_field'] = 'category';
$zz_sub['fields'][3]['show_hierarchy_subtree'] = wrap_category_id('turnierwertungen');

$zz_sub['fields'][5]['title'] = 'Anzeigen';
$zz_sub['fields'][5]['field_name'] = 'anzeigen';
$zz_sub['fields'][5]['type'] = 'select';
$zz_sub['fields'][5]['enum'] = array('immer', 'bei Gleichstand');
$zz_sub['fields'][5]['default'] = 'immer';
$zz_sub['fields'][5]['def_val_ignore'] = true;

$zz_sub['sql'] = 'SELECT turniere_wertungen.*
		, CONCAT(termine.termin, " ", YEAR(beginn)) AS turnier
		, category
	FROM turniere_wertungen
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_wertungen.wertung_category_id
';
$zz_sub['sqlorder'] = ' ORDER BY termine.beginn, turniere_wertungen.reihenfolge, category';

$zz_sub['hooks']['after_insert'] = 
$zz_sub['hooks']['after_update'] = 
$zz_sub['hooks']['after_delete'] = 'my_tabellenstand_aktualisieren';
