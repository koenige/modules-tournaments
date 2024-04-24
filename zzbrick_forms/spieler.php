<?php 

/**
 * tournaments module
 * form script: players per tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include('participations');

$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['usergroup_id'] = wrap_id('usergroups', 'spieler');

$zz['page']['dont_show_title_as_breadcrumb'] = true;
$zz['page']['breadcrumbs'][] = ['title' => 'Spieler'];
$zz['page']['referer'] = '../';

foreach ($zz['fields'] as $no => $field) {
	if (empty($field['field_name'])) continue;
	switch ($field['field_name']) {
	case 'team_id':
	case 'club_contact_id':
	case 'entry_date':
	case 'verification_hash':
	case 'entry_contact_id':
	case 't_verein':
		$zz['fields'][$no]['merge_ignore'] = true;
		break;
	}
}

$zz['fields'][2]['display_field'] = 'person';
if (brick_access_rights()) {
	// @todo warum ist das write_once?
	$zz['fields'][2]['type'] = 'select';
}

$zz['fields'][5]['display_field'] = 'team';

if ($brick['data']['turnierform'] !== 'e') {
	$zz['fields'][14]['hide_in_form'] = true; // Setzliste
	$zz['fields'][39]['hide_in_form'] = true; // Qualifkation
	$zz['fields'][40]['hide_in_form'] = true; // Qualifikation über Termin
	$zz['fields'][38]['hide_in_form'] = true; // Urkundentext

	$zz['fields'][5]['sql'] = sprintf('SELECT team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
		FROM teams
		WHERE event_id = %d
		ORDER BY team_id', $brick['data']['event_id']);

} else {
	$zz['fields'][50]['hide_in_form'] = true; // Brett
	$zz['fields'][50]['hide_in_list'] = true; // Brett
	$zz['fields'][13]['hide_in_form'] = true; // Gastspieler
	$zz['fields'][10]['hide_in_form'] = true; // Rang
	$zz['fields'][10]['hide_in_list'] = true; // Rang
	$zz['fields'][5]['hide_in_form'] = true; // Team
}

if (brick_access_rights(['AK Spielbetrieb'])) {
	$zz['fields'][31]['title'] = 'Nation';
	$zz['fields'][31]['type'] = 'subtable';
	$zz['fields'][31]['table'] = 'participations';
	$zz['fields'][31]['table_name'] = 'participations_nation';
	$zz['fields'][31]['fields'] = [];
	$zz['fields'][31]['fields'][2]['type'] = 'foreign_key';
	$zz['fields'][31]['fields'][2]['field_name'] = 'participation_id';
	$zz['fields'][31]['class'] = 'block960';
	$zz['fields'][31]['hide_in_form'] = true;
	$zz['fields'][31]['hide_in_list_if_empty'] = true;
	$zz['fields'][31]['export_no_html'] = true;
	unset($zz['fields'][31]['subselect']['list_field_format']);
	$zz['fields'][31]['subselect']['sql'] = sprintf('SELECT participation_id
			, federation
		FROM participations
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = participations.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		LEFT JOIN fide_players
			ON contacts_identifiers.identifier = fide_players.player_id
	', wrap_category_id('identifiers/id_fide'));
}

$zz['fields'][52]['field_name'] = 'date_of_birth';
$zz['fields'][52]['type'] = 'display';
$zz['fields'][52]['type_detail'] = 'date';
$zz['fields'][52]['title'] = 'Geb. Dat.';
$zz['fields'][52]['search'] = 'persons.date_of_birth';
$zz['fields'][52]['hide_in_form'] = true;

$zz['fields'][53]['field_name'] = 'sex';
$zz['fields'][53]['title_tab'] = 'S.';
$zz['fields'][53]['type'] = 'display';
$zz['fields'][53]['search'] = 'persons.sex';
$zz['fields'][53]['hide_in_form'] = true;
$zz['fields'][53]['character_set'] = 'latin1';

if (brick_access_rights(['AK Spielbetrieb'])) {
	$zz['fields'][66]['title'] = 'Verb.';
	$zz['fields'][66]['field_name'] = 'verband';
	$zz['fields'][66]['search'] = 'verbaende.contact_abbr';
	$zz['fields'][66]['character_set'] = 'utf8';
	$zz['fields'][66]['type'] = 'display';
	$zz['fields'][66]['hide_in_list_if_empty'] = true;

	$zz['fields'][69]['title'] = 'FIDE-ID';
	$zz['fields'][69]['field_name'] = 'player_id_fide';
	$zz['fields'][69]['exclude_from_search'] = true;
	$zz['fields'][69]['type'] = 'display';
	$zz['fields'][69]['hide_in_list_if_empty'] = true;
	$zz['fields'][69]['hide_in_form'] = true;
}

if (!$brick['data']['gastspieler']) {
	unset($zz['fields'][13]);
}

$zz['sql'] = 'SELECT participations.*
		, IF(gastspieler = "ja", "ja", "") AS gastspieler_display
		, IF(
			contacts.contact
			= CONCAT(
				IFNULL(CONCAT(t_vorname, " "), ""),
				IFNULL(CONCAT(t_namenszusatz, " "), ""),
				IFNULL(CONCAT(t_nachname, " "), "")
			),
			contacts.contact,
			CONCAT(contacts.contact, 
				" / T: ", CONCAT(
					IFNULL(CONCAT(t_vorname, " "), ""),
					IFNULL(CONCAT(t_namenszusatz, " "), ""),
					IFNULL(CONCAT(t_nachname, " "), "")
				), ""
			)
		) AS person
		, (SELECT identification FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND provider_category_id = %d
			LIMIT 1
		) AS e_mail
		, SUBSTRING(sex, 1, 1) AS sex
		, CONCAT(events.event, " ", IFNULL(events.event_year, IFNULL(YEAR(events.date_begin), YEAR(events.date_end)))) AS event
		, events.identifier AS event_identifier
		, teams.identifier AS team_identifier
		, CONCAT(teams.team, IFNULL(CONCAT(" ", team_no), "")) AS team
		, organisationen.contact AS contact
		, verbaende.contact_abbr AS verband
		, usergroup
		, contacts.identifier AS personen_kennung
		, contacts_categories.parameters AS contact_parameters
		, persons.date_of_birth
		, landesverbaende.contact_abbr AS landesverband
		, (SELECT identifier FROM contacts_identifiers
			WHERE contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.identifier_category_id = %d
			AND current = "yes") AS player_id_fide
	FROM participations
	LEFT JOIN persons USING (contact_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN categories contacts_categories
		ON contacts.contact_category_id = contacts_categories.category_id
	LEFT JOIN usergroups USING (usergroup_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN teams USING (team_id)
	LEFT JOIN contacts organisationen
		ON organisationen.contact_id = participations.club_contact_id
	LEFT JOIN contacts_identifiers ok
		ON ok.contact_id = organisationen.contact_id AND current = "yes"
		AND ok.identifier_category_id = %d
	LEFT JOIN contacts_identifiers vk
		ON SUBSTRING(ok.identifier, 1, 3) = vk.identifier
		AND vk.current = "yes"
		AND vk.identifier_category_id = %d
	LEFT JOIN contacts verbaende
		ON verbaende.contact_id = vk.contact_id
	LEFT JOIN contacts landesverbaende
		ON landesverbaende.contact_id = participations.federation_contact_id
';
$zz['sql'] = sprintf($zz['sql']
	, wrap_category_id('provider/e-mail')
	, wrap_category_id('identifiers/id_fide')
	, wrap_category_id('identifiers/pass_dsb')
	, wrap_category_id('identifiers/pass_dsb')
);

$zz['sqlorder'] = ' ORDER BY team, team_no, ISNULL(brett_no), brett_no, rang_no, last_name, first_name';

$zz['record']['copy'] = false;
$zz['record']['edit'] = true;

$zz['filter'][2]['title'] = 'Team';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['where'] = 'team_id';
$zz['filter'][2]['field_name'] = 'team_id';
$zz['filter'][2]['sql'] = sprintf('SELECT team_id
	, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
	FROM teams
	WHERE event_id = %d
	AND team_status = "Teilnehmer"
	ORDER BY team, team_no', $brick['data']['event_id']);
if ($brick['data']['turnierform'] !== 'e') {
	if (empty($_GET['filter']['team'])) {
		$zz['fields'][5]['group_in_list'] = true;
	} else {
		$zz['fields'][5]['hide_in_list'] = true;
	}
}

$zz['filter'][3]['title'] = 'Spielberechtigt';
$zz['filter'][3]['type'] = 'list';
$zz['filter'][3]['where'] = 'spielberechtigt';
$zz['filter'][3]['sql'] = sprintf('SELECT DISTINCT spielberechtigt, spielberechtigt AS titel
	FROM participations
	WHERE event_id = %d
	ORDER BY spielberechtigt', $brick['data']['event_id']);

$zz['filter'][4]['title'] = 'Status';
$zz['filter'][4]['type'] = 'list';
$zz['filter'][4]['where'] = 'status_category_id';
$zz['filter'][4]['sql'] = sprintf('SELECT DISTINCT status_category_id, category AS titel
	FROM participations
	LEFT JOIN categories ON participations.status_category_id = categories.category_id
	WHERE event_id = %d
	ORDER BY category', $brick['data']['event_id']);

// $zz['list']['multi_delete'] = true;

$zz['export'][] = 'CSV Excel';

$zz['page']['extra']['wide'] = true;
if (wrap_access('tournaments_merge'))
	$zz['list']['merge'] = true;

if (!brick_access_rights(['AK Spielbetrieb'])
	AND !brick_access_rights(['Technik', 'Organisator', 'Turnierleiter'], $brick['data']['event_rights'])
) {
	$zz['access'] = 'none';
}

unset($zz['add']); // just add player here, no other groups
