<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017, 2019 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierdetails


$termin = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$termin) wrap_quit(404);

$zz = zzform_include_table('turniere');

$sql = 'SELECT turnier_id
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	WHERE reihe_category_id = %d
	AND termin_id != %d
	ORDER BY beginn DESC
	LIMIT 1';
$sql = sprintf($sql, $termin['reihe_category_id'], $termin['termin_id']);
$data = wrap_db_fetch($sql, 'turnier_id');
$termin['letztes_turnier_id'] = wrap_db_fetch($sql, '', 'single value');

if ($termin['letztes_turnier_id']) {
	$zz['if']['add']['explanation'] = sprintf(
		'<ul><li>Statt Eingabe: <a href="./?add=%s">Übernahme der Daten vom letzten Turnier dieser Reihe</a></li></ul>',
		$termin['letztes_turnier_id']
	);
}

$zz['fields'][25]['explanation'] = 'Hier bitte nur Gesamtdateien hochladen.
<br>Für Runden gibt es die Möglichkeit zum Upload bei der <a href="../runde/">Rundenübersicht</a>.';

$zz['where']['termin_id'] = $termin['termin_id'];
$zz['access'] = 'add_then_edit';
$zz['add_from_source_id'] = true;

$zz_conf['referer'] = '../';

my_event_breadcrumbs($termin);
$zz_conf['breadcrumbs'][] = ['linktext' => $zz['title']];
