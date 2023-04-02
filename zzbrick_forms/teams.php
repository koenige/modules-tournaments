<?php 

/**
 * tournaments module
 * form script: teams of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include_table('teams');

$zz['details'][0]['title'] = 'Spieler';
$zz['details'][0]['link'] = [
	'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'event_identifier',
	'string1' => '/spieler/?filter[team]=', 'field2' => 'team_id'
];

$zz['details'][1]['title'] = 'Betreuer';
$zz['details'][1]['link'] = [
	'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'event_identifier',
	'string1' => '/teilnahmen/?filter[team]=', 'field2' => 'team_id'
];

$zz['details'][2]['title'] = 'Buchungen';
$zz['details'][2]['link'] = [
	'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'event_identifier',
	'string1' => '/buchungen/?filter[team]=', 'field2' => 'team_id'
];

$zz['details'][3]['title'] = 'Links';
$zz['details'][3]['link'] = [
	'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'event_identifier',
	'string1' => '/links/?filter[team]=', 'field2' => 'team_id'
];

$zz['if'][1]['details'] = false;

$zz['where']['event_id'] = $brick['data']['event_id'];

$zz['fields'][1]['show_id'] = true;

$zz['fields'][2]['class'] = 'hidden';
$zz['fields'][2]['hide_in_list'] = true;

switch ($brick['data']['turnierform']) {
case 'm-s':
	$zz['fields'][3]['title'] = 'Schule';
	$zz['fields'][4]['explanation'] = 'Falls leer, wird hier Name der Schule genommen.';
	$zz['fields'][10] = false; // Regionalgruppe
	$zz['fields'][11] = false; // Verband
	break;
case 'm-v':
	$zz['fields'][3]['title'] = 'Verein';
	$zz['fields'][4]['explanation'] = 'Falls leer, wird hier Name des Vereins genommen.';
	break;
case 'm-a':
	$zz['fields'][3]['title'] = 'Verband';
	$zz['fields'][4]['explanation'] = 'Falls leer, wird hier Name des Verbands genommen.';
	$zz['fields'][10] = false; // Regionalgruppe
	$zz['fields'][11] = false; // Verband
	break;
}

$zz['fields'][13]['default'] = 'offen';

$zz['fields'][8]['hide_in_list'] = true;

$zz['fields'][34]['hide_in_list'] = false;
$zz['fields'][14]['hide_in_list'] = false;
$zz['fields'][15]['hide_in_list'] = false;
$zz['fields'][35]['hide_in_list'] = false;

if (!$brick['data']['gastspieler']) unset($zz['fields'][30]); // keine Gastspielgenehmigung

// Einschränkungen, Admin + Gremien + Webmaster dürfen alles
if (!brick_access_rights('Gremien') AND brick_access_rights(['Technik', 'Organisator'], $brick['data']['event_rights'])) {
	$zz['fields'][21]['subselect']['sql'] .= 
		' AND FIND_IN_SET(sichtbarkeit, "Organisator")';
	$zz['fields'][21]['sql'] .= 
		' WHERE FIND_IN_SET(sichtbarkeit, "Organisator")';

} elseif (!brick_access_rights('Gremien') AND brick_access_rights(['Turnierleitung', 'Schiedsrichter'], $brick['data']['event_rights'])) {
	$zz['fields'][21]['subselect']['sql'] .= 
		' AND FIND_IN_SET(sichtbarkeit, "Organisator")';
	$zz['fields'][21]['sql'] .= 
		' WHERE FIND_IN_SET(sichtbarkeit, "Organisator")';
	unset($zz['details'][0]); // Spieler
	unset($zz['details'][2]); // Buchungen
	unset($zz['details'][3]); // Links

	unset($zz['fields'][4]['link']);
	$zz_conf['delete'] = false;
	$zz_conf['add'] = false;
	$zz['fields'][4]['type'] = 'display';
	$zz['fields'][5]['type'] = 'display';
	$hide_in_form = [2, 3, 6, 11, 10, 7, 8, 9, 13, 32, 33, 40, 17]; // , 12
	foreach ($hide_in_form as $no) {
		if (empty($zz['fields'][$no])) continue;
		$zz['fields'][$no]['hide_in_form'] = true;
	}
	unset($zz['fields'][32]);
	$zz['fields'][13]['list_append_next'] = false;
}

if (!empty($zz['filter'][1])) {
	$zz['filter'][1]['sql'] = sprintf('SELECT meldung, CONCAT(meldung, " (", COUNT(meldung), ")") AS titel
		FROM teams
		WHERE event_id = %d
		AND team_status = "Teilnehmer"
		GROUP BY meldung
		ORDER BY meldung', $brick['data']['event_id']);
}
$zz_conf['limit'] = 40;
$zz_conf['export'][] = 'CSV Excel';
$zz_conf['export'][] = 'PDF Tischkarten';
$zz['page']['event'] = $brick['data'];

$zz['filter'][2]['title'] = 'Status';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['field'] = 'team_status';
$zz['filter'][2]['where'] = 'team_status';
$zz['filter'][2]['default_selection'] = 'Teilnehmer';
$zz['filter'][2]['sql'] = sprintf('SELECT team_status, CONCAT(team_status, " (", COUNT(team_status), ")") AS titel
	FROM teams
	WHERE event_id = %d
	GROUP BY team_status
	ORDER BY team_status', $brick['data']['event_id']);
