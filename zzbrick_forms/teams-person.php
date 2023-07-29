<?php 

/**
 * tournaments module
 * form script: contact data of a person of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2017, 2019-2020, 2022-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');
if (!in_array($brick['data']['meldung'], ['offen', 'teiloffen']))
	wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Änderungen sind nicht mehr möglich.');

$data = $brick['data'];
// Team + Vereinsbetreuer auslesen
$data = array_merge($data, mf_tournaments_team_participants([$data['team_id'] => $data['contact_id']], $data, false));

$zz = zzform_include('persons', [], 'forms');

$zz['page']['title'] = $brick['page']['title'].'Details';
$zz['page']['breadcrumbs'][] = '<a href="../kontakt/">Kontaktdaten</a>';
$zz['page']['breadcrumbs'][]['title'] = 'Details';

// Person-ID notwendig
if (empty($_GET['where']['contact_id'])) wrap_quit(403);
$id_found = false;
$bearbeitbar = [
	'verein-vorsitz', 'verein-jugend', 'team-organisator', 'betreuer'
];
foreach ($bearbeitbar as $usergroup) {
	if (!array_key_exists($usergroup, $data)) continue;
	foreach ($data[$usergroup] as $person) {
		if (!empty($person['contact_id']) AND $person['contact_id'] == $_GET['where']['contact_id'])
			$id_found = true;
	}
}
if (!$id_found) wrap_quit(403);

$zz['footer']['text'] = wrap_template('team-kontaktdetails');
$data['head'] = true;
$zz['explanation'] = wrap_template('team-kontaktdetails', $data);

// contact
$zz['fields'][2]['title'] = 'Name';
$zz['fields'][2]['type'] = 'display';
$zz['fields'][2]['hide_in_form'] = false;
$zz['fields'][2]['field_sequence'] = 1;

foreach ($zz['fields'][9]['fields'] as $no => $field) {
	if (empty($field['field_name'])) continue;
	switch ($field['field_name']) {
	case 'first_name':
	case 'name_particle':
	case 'last_name':
		// parts of name have to appear for hook script
		$zz['fields'][9]['fields'][$no]['type'] = 'display';
		$zz['fields'][9]['fields'][$no]['class'] = 'hidden';
		break;
	case 'birth_name':
	case 'title_prefix':
	case 'title_suffix':
		$zz['fields'][9]['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][9]['fields'][$no]['hide_in_form'] = true;
		break;
	case 'date_of_birth':
		$zz['fields'][9]['fields'][$no]['explanation'] = '(Es reicht auch Geburtsjahr)';
		break;
	case 't_shirt':
		$zz['fields'][9]['fields'][$no]['hide_in_form'] = true;
		break;
	}
}

unset($zz['fields'][35]['separator']);

$zz['fields'][3]['hide_in_form'] = true; // identifier
$zz['fields'][13]['hide_in_form'] = true; // remarks
$zz['fields'][97]['hide_in_form'] = true; // created
if (!empty($zz['fields'][27]))
	$zz['fields'][27]['hide_in_form'] = true; // change identifier
$zz['fields'][19]['hide_in_form'] = true; // contacts_identifiers

$zz['title'] = '';
$zz['access'] = 'edit_only';
$zz['record']['no_ok'] = true;
