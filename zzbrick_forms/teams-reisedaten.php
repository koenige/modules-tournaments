<?php 

/**
 * tournaments module
 * form script: Travel data of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2018, 2021-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');
if (!in_array($brick['data']['meldung'], ['offen', 'teiloffen']))
	wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Änderungen sind nicht mehr möglich.');

$zz = zzform_include('teams');

$brick['page']['title'] .= 'Reisedaten';
$brick['page']['breadcrumbs'][] = 'Reisedaten';

$zz_conf['footer_text'] = wrap_template('team-reisedaten', $brick['data']);
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-reisedaten', $brick['data']);
$zz['title'] = '';
$zz['where']['team_id'] = $brick['data']['team_id'];

$fields = [1, 14, 15, 34, 35, 20];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) unset($zz['fields'][$no]);
}
unset($zz['fields'][35]['separator']);

$zz['fields'][20]['class'] = 'hidden';

$zz['access'] = 'edit_only';
$zz_conf['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);

// Daten ggf. korrigieren
$zz['hooks']['before_upload'][] = 'my_complete_date';
