<?php 

/**
 * tournaments module
 * form script: supervisors and contact data of a team during a tournament
 * Skript: Betreuer und andere Kontaktdaten eines Teams eines Turniers
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');
if (!in_array($brick['data']['meldung'], ['offen', 'teiloffen']))
	wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Änderungen sind nicht mehr möglich.');

$zz = zzform_include('participations');

$zz['page']['title'] = $brick['page']['title'].'Kontaktdaten';
$zz['page']['breadcrumbs'][]['title'] = 'Kontaktdaten';

$zz['footer']['text'] = wrap_template('team-kontakt', $brick['data']);
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-kontakt', $brick['data']);

if ($brick['data']['turnierform'] === 'm-v') {
	$gruppen_ids = wrap_id('usergroups', 'team-organisator').','.
		wrap_id('usergroups', 'verein-jugend').','.
		wrap_id('usergroups', 'betreuer').','.
		wrap_id('usergroups', 'verein-vorsitz');
} else {
	// keine Vereinsmannschaft!
	$gruppen_ids = wrap_id('usergroups', 'team-organisator').','.
		wrap_id('usergroups', 'betreuer');
}
$zz['sql'] .= sprintf(' WHERE usergroup_id IN (%s)
	AND ((ISNULL(team_id) AND ISNULL(participations.event_id)) OR team_id = %d)', $gruppen_ids, $brick['data']['team_id']);
if ($brick['data']['turnierform'] === 'm-v') {
	$zz['sql'] .= sprintf(' AND (ISNULL(participations.club_contact_id) OR (participations.club_contact_id = %d))',
		$brick['data']['contact_id']);
}

$zz['fields'][6]['hide_in_form'] = true;
$zz['fields'][6]['hide_in_list'] = true;
$zz['fields'][6]['unless'][21] = false;
if ($brick['data']['turnierform'] === 'm-v') {
	$zz['fields'][6]['value'] = $brick['data']['contact_id'];
}

unset($zz['fields'][2]['add_details']);
$zz['fields'][2]['unless']['export_mode']['list_append_next'] = false;

$zz['fields'][3]['sql'] = sprintf('SELECT usergroup_id, usergroup
	FROM usergroups WHERE usergroup_id IN (%s) ORDER BY usergroup', $gruppen_ids);
$zz['fields'][3]['type'] = 'write_once';
$zz['fields'][3]['type_detail'] = 'select';
unset($zz['fields'][3]['if']['where']);


$sql = 'SELECT usergroup_id AS value, usergroup AS type, "usergroup_id" AS field_name
	FROM usergroups
	WHERE usergroup_id IN (%s)';
$sql = sprintf($sql, $gruppen_ids);
$zz['add'] = wrap_db_fetch($sql, 'value', 'numeric');
if (brick_access_rights('Webmaster')) {
	$zz['add'][] = [
		'type' => 'Freie Eingabe',
		'field_name' => 'frei',
		'value' => 'betreuer'
	];
}

$zz['fields'][4]['type'] = 'hidden';
$zz['fields'][4]['value'] = $brick['data']['event_id'];
$zz['fields'][4]['hide_in_list'] = true;

$zz['fields'][5]['type'] = 'hidden';
$zz['fields'][5]['hide_in_list'] = true;
$zz['fields'][5]['type_detail'] = 'select';
$zz['fields'][5]['value'] = $brick['data']['team_id'];
$zz['fields'][5]['if'][21]['value'] = false;

$zz['fields'][34]['hide_in_form'] = true;
$zz['fields'][35]['hide_in_form'] = true;
$zz['fields'][36]['hide_in_form'] = true;

$fields = [1, 2, 3, 4, 5, 6, 8, 34, 35, 36];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) unset($zz['fields'][$no]);
}

if ((empty($_GET['mode']) OR $_GET['mode'] !== 'delete')
	AND empty($_GET['insert']) AND empty($_GET['update']) AND empty($_GET['noupdate'])
	AND (empty($_GET['add']['frei']))) {

	// Test, um welche Organisationsform es sich handelt
	$sql = 'SELECT categories.path
		FROM contacts
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE contact_id = %d';
	$sql = sprintf($sql, $brick['data']['contact_id']);
	$org = wrap_db_fetch($sql);
	if (!brick_access_rights('Webmaster') AND $org['path'] === 'verein') {
		// Vereine haben Mitglieder, beschränke auf diese Mitglieder
		// Erlaube keine doppelten Einträge bei demselben Termin aus derselben Gruppe!
		$zz['fields'][2]['if']['insert']['sql'] = sprintf('SELECT CONCAT(ZPS, "-", Mgl_Nr), Spielername
			FROM dwz_spieler
			LEFT JOIN contacts_identifiers
				ON dwz_spieler.ZPS = contacts_identifiers.identifier
				AND contacts_identifiers.current = "yes"
				AND contacts_identifiers.identifier_category_id = %d
			LEFT JOIN contacts_identifiers
				ON contacts_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
				AND contacts_identifiers.current = "yes"
				AND contacts_identifiers.identifier_category_id = %d
			LEFT JOIN participations
				ON participations.contact_id = contacts_identifiers.contact_id
				AND participations.usergroup_id = %d
				AND participations.event_id = %d
			WHERE contact_id = %d
			AND ISNULL(participations.participation_id)
			ORDER BY Spielername',
			wrap_category_id('identifiers/zps'),
			wrap_category_id('identifiers/zps'),
			!empty($_GET['add']['usergroup_id']) ? $_GET['add']['usergroup_id'] : 0,
			$brick['data']['event_id'],
			$brick['data']['contact_id']
		);
	} else {
		// Webmaster, Auswahlmannschaften, Schulen etc.
		// erlaube auch die Auswahl von passiven Mitgliedern
		$zz['fields'][2]['if']['insert']['sql'] = sprintf('SELECT CONCAT(ZPS, "-", Mgl_Nr), Spielername, Geburtsjahr, Status, Vereinname
				, CONCAT(SUBSTRING_INDEX(Spielername, ",", -1), " ", SUBSTRING_INDEX(Spielername, ",", 1)) AS voller_name
			FROM dwz_spieler
			LEFT JOIN dwz_vereine USING (ZPS)
			LEFT JOIN contacts_identifiers
				ON contacts_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
				AND contacts_identifiers.current = "yes"
				AND contacts_identifiers.identifier_category_id = %d
			ORDER BY Spielername'
			, wrap_category_id('identifiers/zps')
		);
		$zz['fields'][2]['sql_ignore'][] = 'voller_name';
	}
	$zz['hooks']['before_insert'][] = 'my_dwzdaten_person';
}

$zz['fields'][24]['title'] = 'Geburt';
$zz['fields'][24]['type'] = 'subtable';
$zz['fields'][24]['class'] = 'number';
$zz['fields'][24]['hide_in_form'] = true;
$zz['fields'][24]['table'] = 'persons';
$zz['fields'][24]['table_name'] = 'persons_birth';
$zz['fields'][24]['exclude_from_search'] = true;
$zz['fields'][24]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][24]['fields'][2]['field_name'] = 'contact_id';
$zz['fields'][24]['fields'][2]['key_field_name'] = 'contact_id';
$zz['fields'][24]['subselect']['sql'] = 'SELECT contact_id, YEAR(date_of_birth)
	FROM persons';

$zz['fields'][23]['title'] = 'E-Mail';
$zz['fields'][23]['type'] = 'subtable';
$zz['fields'][23]['hide_in_form'] = true;
$zz['fields'][23]['table'] = 'contactdetails';
$zz['fields'][23]['table_name'] = 'persons_mail';
$zz['fields'][23]['exclude_from_search'] = true;
$zz['fields'][23]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][23]['fields'][2]['field_name'] = 'contact_id';
$zz['fields'][23]['fields'][2]['key_field_name'] = 'contact_id';
$zz['fields'][23]['subselect']['sql'] = 'SELECT contact_id, identification, CONCAT("(", category_short, ")")
	FROM contactdetails
	LEFT JOIN categories
		ON categories.category_id = contactdetails.provider_category_id
	WHERE categories.parameters LIKE "%mail%"
';

$zz['fields'][22]['title'] = 'Telefon';
$zz['fields'][22]['type'] = 'subtable';
$zz['fields'][22]['hide_in_form'] = true;
$zz['fields'][22]['table'] = 'contactdetails';
$zz['fields'][22]['class'] = 'number';
$zz['fields'][22]['exclude_from_search'] = true;
$zz['fields'][22]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][22]['fields'][2]['field_name'] = 'contact_id';
$zz['fields'][22]['fields'][2]['key_field_name'] = 'contact_id';
$zz['fields'][22]['subselect']['sql'] = 'SELECT contact_id, identification, CONCAT("(", category_short, ")")
	FROM contactdetails
	LEFT JOIN categories
		ON categories.category_id = contactdetails.provider_category_id
	WHERE categories.parameters LIKE "%phone%"
';

$zz['title'] = '';
$zz['access'] = 'add+delete';

$zz['record']['copy'] = false;
if (!brick_access_rights('Webmaster'))
	$zz['if'][22]['record']['delete'] = false; // User darf sich nicht selbst löschen!
$zz['setting']['zzform_max_select'] = 200;

$zz['details'][0]['title'] = 'Kontaktdaten';
$zz['details'][0]['link'] = [
	'string1' => '../kontaktdetails/?where[contact_id]=', 'field1' => 'contact_id'
];
unset($zz['subtitle']);
unset($zz['filter']);
