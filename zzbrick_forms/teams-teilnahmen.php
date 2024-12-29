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
 * @copyright Copyright © 2014-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');
if (!in_array($brick['data']['meldung'], ['offen', 'teiloffen']))
	wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Änderungen sind nicht mehr möglich.');

$zz = zzform_include('participations');

$zz['page']['title'] = $brick['page']['title'].'Kontaktdaten';
$zz['page']['breadcrumbs'][]['title'] = 'Kontaktdaten';

$brick['data']['usergroups'] = mf_tournaments_team_usergroups($brick['data']['turnierform']);
$sql = 'SELECT usergroup_id AS value, usergroup AS type, "usergroup_id" AS field_name
	FROM usergroups
	WHERE usergroup_id IN (%s)';
$sql = sprintf($sql, implode(',', array_keys($brick['data']['usergroups'])));
$zz['add'] = wrap_db_fetch($sql, 'value', 'numeric');
if (wrap_access('tournaments_teams_registrations', $brick['data']['event_rights'])) {
	$zz['add'][] = [
		'type' => 'Freie Eingabe',
		'field_name' => 'frei',
		'value' => 'betreuer'
	];
	if (!empty($_GET['add']['frei']))
		$_GET['add']['usergroup_id'] = wrap_id('usergroups', $_GET['add']['frei']);
}

$zz['footer']['text'] = wrap_template('team-kontakt', $brick['data']);
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-kontakt', $brick['data']);

$zz['sql'] .= sprintf(' WHERE usergroup_id IN (%s)
	AND ((ISNULL(team_id) AND ISNULL(participations.event_id)) OR team_id = %d)', 
	implode(',', array_keys($brick['data']['usergroups'])), $brick['data']['team_id']
);
if (wrap_setting('tournaments_player_pool') === 'club')
	$zz['sql'] .= sprintf(' AND (ISNULL(participations.club_contact_id) OR (participations.club_contact_id = %d))',
		$brick['data']['contact_id']);

foreach ($zz['fields'] as $no => $field) {
	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
	case 'participation_id':
		break;

	case 'contact_id':
		$zz['fields'][$no]['unless']['export_mode']['list_append_next'] = false;
		if (!empty($_GET['add']['frei'])) break; // allow here to add people from contacts
		if (wrap_setting('tournaments_player_pool') === 'club'
			AND !wrap_access('tournaments_teams_registrations', $brick['data']['event_rights'])
		) {
			// Vereine haben Mitglieder, beschränke auf diese Mitglieder
			// Erlaube keine doppelten Einträge bei demselben Termin aus derselben Gruppe!
			$zz['fields'][$no]['if']['insert']['sql'] = sprintf('SELECT
					IFNULL(contacts.contact_id, CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr))) AS contact_id
					, CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr)) AS pass_dsb, Spielername
				FROM dwz_spieler
				LEFT JOIN contacts_identifiers club_identifiers
					ON dwz_spieler.ZPS = club_identifiers.identifier
					AND club_identifiers.current = "yes"
					AND club_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
				LEFT JOIN contacts_identifiers player_identifiers
					ON player_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
					AND player_identifiers.current = "yes"
					AND player_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
				LEFT JOIN contacts
					ON player_identifiers.contact_id = contacts.contact_id
				LEFT JOIN participations
					ON participations.contact_id = player_identifiers.contact_id
					AND participations.usergroup_id = %d
					AND participations.event_id = %d
				WHERE club_identifiers.contact_id = %d
				AND ISNULL(participations.participation_id)
				ORDER BY Spielername',
				$_GET['add']['usergroup_id'] ?? 0,
				$brick['data']['event_id'],
				$brick['data']['contact_id']
			);
			$zz['fields'][$no]['key_field_name'] = 'contacts.contact_id';
		} else {
			// tournaments_teams_registrations, Auswahlmannschaften, Schulen etc.
			// erlaube auch die Auswahl von passiven Mitgliedern
			$zz['fields'][$no]['if']['insert']['sql'] = 'SELECT
					IFNULL(contacts_identifiers.contact_id
						, CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr))
					) AS contact_id
					, CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr)) AS pass_dsb
					, Spielername, Geburtsjahr, Status, Vereinname
					, CONCAT(SUBSTRING_INDEX(Spielername, ",", -1), " ", SUBSTRING_INDEX(Spielername, ",", 1)) AS voller_name
				FROM dwz_spieler
				LEFT JOIN dwz_vereine USING (ZPS)
				LEFT JOIN contacts_identifiers
					ON contacts_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
					AND contacts_identifiers.current = "yes"
					AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
				ORDER BY Spielername';
			$zz['fields'][$no]['sql_ignore'][] = 'voller_name';
		}
		$zz['fields'][$no]['sql_ignore'][] = 'pass_dsb';
		break;

	case 'usergroup_id':
		$zz['fields'][$no]['sql'] = sprintf(
			'SELECT usergroup_id, usergroup
			FROM usergroups WHERE usergroup_id IN (%s) ORDER BY usergroup',
			implode(',', array_keys($brick['data']['usergroups']))
		);
		$zz['fields'][$no]['type'] = 'write_once';
		$zz['fields'][$no]['type_detail'] = 'select';
		unset($zz['fields'][$no]['if']['where']);
		break;
		
	case 'event_id':
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['value'] = $brick['data']['event_id'];
		$zz['fields'][$no]['hide_in_list'] = true;
		$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'team_id':
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['hide_in_list'] = true;
		$zz['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][$no]['type_detail'] = 'select';
		$zz['fields'][$no]['value'] = $brick['data']['team_id'];
		$zz['fields'][$no]['if'][21]['value'] = false;
		break;

	case 'club_contact_id':
		$zz['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][$no]['hide_in_list'] = true;
		$zz['fields'][$no]['unless'][21] = false;
		if (wrap_setting('tournaments_player_pool') === 'club')
			$zz['fields'][$no]['value'] = $brick['data']['contact_id'];
		break;

	case 'verification_hash':
		break;

	case 'entry_date':
	case 'entry_contact_id':
	case 'status_category_id':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;
	
	default:
		unset($zz['fields'][$no]);
		break;
	}
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

$zz['hooks']['before_insert'][] = 'mf_ratings_person_hook';
$zz['record']['copy'] = false;
if (!wrap_access('tournaments_teams_registrations', $brick['data']['event_rights']))
	$zz['if'][22]['record']['delete'] = false; // User darf sich nicht selbst löschen!
$zz['setting']['zzform_max_select'] = 200;

$zz['details'][0]['title'] = 'Kontaktdaten';
$zz['details'][0]['link'] = [
	'string1' => '../kontaktdetails/?where[contact_id]=', 'field1' => 'contact_id'
];
unset($zz['subtitle']);
unset($zz['filter']);


function mf_tournaments_team_usergroups($tournament_form) {
	$usergroups[] = 'team-organisator';
	$usergroups[] = 'betreuer';
	if ($tournament_form === 'm-v') {
		$usergroups[] = 'verein-jugend';
		$usergroups[] = 'verein-vorsitz';
	}
	$ids = [];
	foreach ($usergroups as $usergroup)
		$ids[] = wrap_id('usergroups', $usergroup);

	$sql = 'SELECT usergroup_id, usergroup
		FROM usergroups
	    WHERE usergroup_id IN (%s)';
	$sql = sprintf($sql, implode(',', $ids));
	return wrap_db_fetch($sql, 'usergroup_id');
}

