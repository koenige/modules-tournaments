<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014, 2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierstatus


$zz_sub['title'] = 'Turnierstatus';
$zz_sub['table'] = 'turniere_status';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'turnier_status_id';
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

$zz_sub['fields'][4]['title'] = 'Kategorie';
$zz_sub['fields'][4]['field_name'] = 'status_category_id';
$zz_sub['fields'][4]['type'] = 'select';
$zz_sub['fields'][4]['sql'] = sprintf('SELECT category_id, category
	FROM categories
	WHERE main_category_id = %d', wrap_category_id('turnierstatus'));
$zz_sub['fields'][4]['display_field'] = 'category';
$zz_sub['fields'][4]['key_field_name'] = 'category_id';


$zz_sub['sql'] = 'SELECT turniere_status.*
		, CONCAT(termin, " ", YEAR(beginn)) AS turnier
		, category
	FROM turniere_status
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories
		ON categories.category_id = turniere_status.status_category_id
';
$zz_sub['sqlorder'] = ' ORDER BY beginn, termin ASC';

$zz_sub['access'] = 'all';
