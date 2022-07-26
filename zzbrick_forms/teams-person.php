<?php 

/**
 * tournaments module
 * form script: contact data of a person of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2017, 2019-2020, 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
$data = my_team_form($brick['vars']);
// Team + Vereinsbetreuer auslesen
$data = array_merge($data, my_team_teilnehmer([$data['team_id'] => $data['contact_id']], $data, false));

require_once __DIR__.'/persons.php';

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

$zz_conf['footer_text'] = wrap_template('team-kontaktdetails');
$data['head'] = true;
$zz['explanation'] = wrap_template('team-kontaktdetails', $data);
$zz['page'] = my_team_form_page($data, 'Details');

// contact
$zz['fields'][2]['title'] = 'Name';
$zz['fields'][2]['type'] = 'display';
$zz['fields'][2]['hide_in_form'] = false;
$zz['fields'][2]['field_sequence'] = 1;

// parts of name have to appear for hook script
$zz['fields'][9]['fields'][2]['type'] = 'display';
$zz['fields'][9]['fields'][2]['class'] = 'hidden';

$zz['fields'][9]['fields'][3]['type'] = 'display';
$zz['fields'][9]['fields'][3]['class'] = 'hidden';

$zz['fields'][9]['fields'][4]['type'] = 'display';
$zz['fields'][9]['fields'][4]['class'] = 'hidden';

$zz['fields'][9]['fields'][5]['hide_in_form'] = true; // birth_name
$zz['fields'][9]['fields'][6]['hide_in_form'] = true; // title_prefix

$zz['fields'][9]['fields'][8]['explanation'] = '(Es reicht auch Geburtsjahr)';

if (!empty($zz['fields'][9]['fields'][33]))
	$zz['fields'][9]['fields'][33]['hide_in_form'] = true; // t_shirt
unset($zz['fields'][35]['separator']);

$zz['fields'][3]['hide_in_form'] = true; // identifier
$zz['fields'][13]['hide_in_form'] = true; // remarks
$zz['fields'][97]['hide_in_form'] = true; // created
if (!empty($zz['fields'][27]))
	$zz['fields'][27]['hide_in_form'] = true; // change identifier
$zz['fields'][65]['hide_in_form'] = true; // contacts_identifiers

$zz['title'] = '';
$zz['access'] = 'edit_only';
$zz_conf['no_ok'] = true;
