<?php 

/**
 * tournaments module
 * form script: tournament round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2017, 2019-2021 Gustaf Mossakowski
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
unset($zz['fields'][61]); // Teilnahmen
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

if (brick_access_rights(['Webmaster'])
	OR brick_access_rights('AK Spielbetrieb')
	OR brick_access_rights(['Schiedsrichter', 'Technik', 'Turnierleitung'], $brick['data']['event_rights'])) {
	if ($brick['data']['turnierform'] === 'e') {
		$zz['details'][0]['title'] = 'Partien';
	} else {
		$zz['details'][0]['title'] = 'Paarungen';
	}
	$zz['details'][0]['link'] = [
		'string0' => '/intern/termine/', 'field1' => 'identifier', 'string1' => '/'
	];
	$zz['details'][1]['title'] = 'Tabellenstand';
	$zz['details'][1]['link'] = [
		'string0' => '/intern/termine/', 'field1' => 'main_event_identifier',
		'string1' => '/tabelle/', 'field2' => 'runde_no', 'string2' => '/'
	];
	$zz['access'] = 'all';
} elseif (brick_access_rights(['Bulletin'], $brick['data']['event_rights'])) {
	$zz['access'] = '';
	$zz_conf['delete'] = false;
} else {
	$zz['access'] = '';
}
