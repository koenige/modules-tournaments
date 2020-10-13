<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierdetails


$event = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$event) wrap_quit(404);

$zz = zzform_include_table('turniere');

$sql = 'SELECT turnier_id
	FROM turniere
	LEFT JOIN events USING (event_id)
	WHERE series_category_id = %d
	AND event_id != %d
	ORDER BY beginn DESC
	LIMIT 1';
$sql = sprintf($sql, $event['series_category_id'], $event['event_id']);
$data = wrap_db_fetch($sql, 'turnier_id');
$event['letztes_turnier_id'] = wrap_db_fetch($sql, '', 'single value');

if ($event['letztes_turnier_id']) {
	$zz['if']['add']['explanation'] = sprintf(
		'<ul><li>Statt Eingabe: <a href="./?add=%s">Übernahme der Daten vom letzten Turnier dieser Reihe</a></li></ul>',
		$event['letztes_turnier_id']
	);
}

$zz['fields'][25]['explanation'] = 'Hier bitte nur Gesamtdateien hochladen.
<br>Für Runden gibt es die Möglichkeit zum Upload bei der <a href="../runde/">Rundenübersicht</a>.';

$zz['where']['event_id'] = $event['event_id'];
$zz['access'] = 'add_then_edit';
$zz['add_from_source_id'] = true;

$zz_conf['referer'] = '../';

my_event_breadcrumbs($event);
$zz_conf['breadcrumbs'][] = ['linktext' => $zz['title']];
