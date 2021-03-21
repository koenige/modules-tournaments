<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2017, 2019-2021 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Einzelner Termin bearbeiten


$event = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$event) wrap_quit(404);

$zz = zzform_include_table('events');

$zz['title'] = 'Runden';
$zz['where']['main_event_id'] = $event['event_id'];
$zz['where']['event_category_id'] = wrap_category_id('zeitplan/runde');

$zz['fields'][4]['default'] = $event['zeitplan_max'];

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
$zz['fields'][35]['value'] = $event['website_id'];

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

my_event_breadcrumbs($event);
$zz_conf['breadcrumbs'][] = ['linktext' => $zz['title']];
$zz_conf['referer'] = '../';

if (brick_access_rights(['Webmaster'])
	OR brick_access_rights('AK Spielbetrieb')
	OR brick_access_rights(['Schiedsrichter', 'Technik', 'Turnierleitung'], $event['event_rights'])) {
	if ($event['turnierform'] === 'e') {
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
} elseif (brick_access_rights(['Bulletin'], $event['event_rights'])) {
	$zz['access'] = '';
	$zz_conf['delete'] = false;
} else {
	$zz['access'] = '';
}
