<?php 

/**
 * tournaments module
 * table script: tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2015, 2017, 2019-2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include_table('turniere');

$sql = 'SELECT tournament_id
	FROM tournaments
	LEFT JOIN events USING (event_id)
	WHERE series_category_id = %d
	AND event_id != %d
	ORDER BY date_begin DESC
	LIMIT 1';
$sql = sprintf($sql, $brick['data']['series_category_id'], $brick['data']['event_id']);
$data = wrap_db_fetch($sql, 'tournament_id');
$brick['data']['last_tournament_id'] = wrap_db_fetch($sql, '', 'single value');

if ($brick['data']['last_tournament_id']) {
	$zz['if']['add']['explanation'] = sprintf(
		'<ul><li>Statt Eingabe: <a href="./?add=%s">Übernahme der Daten vom letzten Turnier dieser Reihe</a></li></ul>',
		$brick['data']['last_tournament_id']
	);
}

if (!empty($zz['fields'][25]))
	$zz['fields'][25]['explanation'] = 'Hier bitte nur Gesamtdateien hochladen.
	<br>Für Runden gibt es die Möglichkeit zum Upload bei der <a href="../runde/">Rundenübersicht</a>.';

$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['access'] = 'add_then_edit';
$zz['add_from_source_id'] = true;

$zz_conf['referer'] = '../';
