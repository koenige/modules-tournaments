<?php 

/**
 * tournaments module
 * form script: tournament round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include_table('events');

$zz['title'] = 'Runden';
$zz['where']['main_event_id'] = $brick['data']['event_id'];
$zz['where']['event_category_id'] = wrap_category_id('zeitplan/runde');

$zz['fields'][4]['default'] = $brick['data']['zeitplan_max'];

$zz['fields'][2]['fields'] = [
	'main_event_id[identifier]', 'runde_no', 'identifier'
];
$zz['fields'][2]['conf_identifier']['concat'] = [
	'/', '/runde/'
];

if (wrap_get_setting('tournaments_upload_pgn')) {
	$zz['fields'][23]['title'] = 'PGN-Datei';
	$zz['fields'][23]['field_name'] = 'pgn';
	$zz['fields'][23]['dont_show_missing'] = true;
	$zz['fields'][23]['type'] = 'upload_image';
	$zz['fields'][23]['path'] = [
		'root' => $zz_setting['media_folder'].'/pgn/',
		'webroot' => $zz_setting['media_internal_path'].'/pgn/',
		'field1' => 'main_event_identifier', 
		'string2' => '/',
		'field2' => 'runde_no',
		'string3' => '.pgn'
	];
	$zz['fields'][23]['input_filetypes'] = ['pgn'];
	$zz['fields'][23]['link'] = [
		'string1' => $zz_setting['media_internal_path'].'/pgn/',
		'field1' => 'main_event_identifier',
		'string2' => '/',
		'field2' => 'runde_no',
		'string3' => '.pgn'
	];
	$zz['fields'][23]['optional_image'] = true;
	$zz['fields'][23]['image'][0]['title'] = 'gro&szlig;';
	$zz['fields'][23]['image'][0]['field_name'] = 'gross';
	$zz['fields'][23]['image'][0]['path'] = $zz['fields'][23]['path'];
	$zz['fields'][23]['unless']['export_mode']['list_prefix'] = '<br>';
}

$zz['fields'][26]['hide_in_list'] = true;
$zz['fields'][26]['hide_in_form'] = true;

$zz['fields'][35]['type'] = 'hidden';
$zz['fields'][35]['hide_in_form'] = true;
$zz['fields'][35]['value'] = $brick['data']['website_id'];

unset($zz['fields'][15]); // Reihe
unset($zz['fields'][24]); // Termine/Kategorien
unset($zz['fields'][31]); // Termine/Websites
unset($zz['fields'][12]); // Ort
unset($zz['fields'][13]); // Ort, frei
unset($zz['fields'][8]); // Anreisser
unset($zz['fields'][14]); // Beschreibung
unset($zz['fields'][33]); // Anmerkungen
unset($zz['fields'][34]); // Offen?
unset($zz['fields'][10]); // Termine/Organisationen
unset($zz['fields'][64]); // Termine/Links
unset($zz['fields'][65]); // Termine/Teilnehmerzahlen
unset($zz['fields'][61]); // participations
unset($zz['fields'][68]); // Termine/Bankkonten
unset($zz['fields'][17]); // Anmeldeinfo

unset($zz['fields'][16]); // direct link
unset($zz['fields'][39]); // Berechtigung
unset($zz['fields'][40]); // Berechtigung
unset($zz['fields'][41]); // Berechtigung
unset($zz['fields'][56]); // Veranstaltungsjahr

// Bedingung für Runde immer wahr
$zz['conditions'][1]['where'] = '';

$zz['hooks']['before_insert'][] = 'mf_tournaments_round_event';
$zz['hooks']['before_update'][] = 'mf_tournaments_round_event';
$zz['hooks']['after_upload'][] = 'mf_tournaments_games_update';

$zz_conf['referer'] = '../';

if (wrap_access('tournaments_games', $brick['data']['event_rights']) AND !wrap_access('tournaments_pairings', $brick['data']['event_rights'])) {
	// just allow to upload PGN files
	$fields = [4, 5, 54, 55, 22, 20];
	foreach ($fields as $no) {
		$zz['fields'][$no]['type_detail'] = $zz['fields'][$no]['type'];
		$zz['fields'][$no]['type'] = 'display';
		unset($zz['fields'][$no]['explanation']);
	}
	$zz['fields'][2]['hide_in_form'] = true; // identifier
	$zz['fields'][6]['hide_in_form'] = true; // event
	unset($zz['fields'][5]['display_field']); // date_end
	$zz_conf['delete'] = false;
	$zz_conf['add'] = false;
}
if (wrap_access('tournaments_games', $brick['data']['event_rights']) OR wrap_access('tournaments_pairings', $brick['data']['event_rights'])) {
	if ($brick['data']['turnierform'] === 'e')
		$zz['details'][0]['title'] = 'Partien';
	else
		$zz['details'][0]['title'] = 'Paarungen';
	$zz['details'][0]['link'] = [
	// @todo use area
		'string0' => $zz_setting['events_internal_path'].'/', 'field1' => 'identifier', 'string1' => '/'
	];
}
if (wrap_access('tournaments_standings', $brick['data']['event_rights'])) {
	$zz['details'][1]['title'] = 'Tabellenstand';
	$zz['details'][1]['link'] = [
	// @todo use area
	//	'area' => 'tournaments_standings',
	//	'fields' => ['main_event_identifier', 'runde_no']
		'string0' => $zz_setting['events_internal_path'].'/', 'field1' => 'main_event_identifier',
		'string1' => '/tabelle/', 'field2' => 'runde_no', 'string2' => '/'
	];
}
