<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012, 2014-2015, 2019-2021 Gustaf Mossakowski <gustaf@koenige.org>
// Turniere/Wertungen


$zz['title'] = 'Turniere/Wertungen';
$zz['table'] = 'turniere_wertungen';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tw_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", YEAR(date_begin)) AS turnier
	FROM tournaments
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", YEAR(date_begin))';

$zz['fields'][4]['title'] = 'Folge';
$zz['fields'][4]['title_tab'] = 'Folge';
$zz['fields'][4]['field_name'] = 'reihenfolge';
$zz['fields'][4]['type'] = 'number';
$zz['fields'][4]['auto_value'] = 'increment';
$zz['fields'][4]['def_val_ignore'] = true;

$zz['fields'][3]['title'] = 'Wertung';
$zz['fields'][3]['field_name'] = 'wertung_category_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence, category';
$zz['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz['fields'][3]['display_field'] = 'category';
$zz['fields'][3]['show_hierarchy_subtree'] = wrap_category_id('turnierwertungen');

$zz['fields'][5]['title'] = 'Anzeigen';
$zz['fields'][5]['field_name'] = 'anzeigen';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['enum'] = array('immer', 'bei Gleichstand');
$zz['fields'][5]['default'] = 'immer';
$zz['fields'][5]['def_val_ignore'] = true;

$zz['sql'] = 'SELECT turniere_wertungen.*
		, CONCAT(events.event, " ", YEAR(date_begin)) AS turnier
		, category
	FROM turniere_wertungen
	LEFT JOIN tournaments USING (tournament_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_wertungen.wertung_category_id
';
$zz['sqlorder'] = ' ORDER BY events.date_begin, turniere_wertungen.reihenfolge, category';

$zz['hooks']['after_insert'] = 
$zz['hooks']['after_update'] = 
$zz['hooks']['after_delete'] = 'mf_tournaments_standings_update';
