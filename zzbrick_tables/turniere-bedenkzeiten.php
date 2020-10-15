<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2015, 2017, 2020 Gustaf Mossakowski <gustaf@koenige.org>
// Turniere/Bedenkzeit


$zz_sub['title'] = 'Turniere/Bedenkzeit';
$zz_sub['table'] = 'turniere_bedenkzeiten';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tb_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'turnier_id';
$zz_sub['fields'][2]['type'] = 'select';
$zz_sub['fields'][2]['sql'] = 'SELECT turnier_id, event
	FROM turniere
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz_sub['fields'][2]['display_field'] = 'event';

$zz_sub['fields'][3]['field_name'] = 'phase';
$zz_sub['fields'][3]['type'] = 'number';
$zz_sub['fields'][3]['auto_value'] = 'increment';
$zz_sub['fields'][3]['def_val_ignore'] = true;

$zz_sub['fields'][4]['title'] = 'Zeit';
$zz_sub['fields'][4]['field_name'] = 'bedenkzeit_sec';
$zz_sub['fields'][4]['type'] = 'number';
if (empty($zz_conf['multi'])) {
	$zz_sub['fields'][4]['factor'] = 60;
}

$zz_sub['fields'][5]['title'] = 'Bonus';
$zz_sub['fields'][5]['field_name'] = 'zeitbonus_sec';
$zz_sub['fields'][5]['type'] = 'number';

$zz_sub['fields'][6]['title'] = 'Züge';
$zz_sub['fields'][6]['field_name'] = 'zuege';
$zz_sub['fields'][6]['type'] = 'number';


$zz_sub['sql'] = 'SELECT turniere_bedenkzeiten.*
		, event
	FROM turniere_bedenkzeiten
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN events USING (event_id)
';
$zz_sub['sqlorder'] = ' ORDER BY events.date_begin, phase';
