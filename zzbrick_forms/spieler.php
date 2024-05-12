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
	case 'club_contact_id':
	case 'entry_date':
	case 'verification_hash':
	case 'entry_contact_id':
	case 't_verein':
		$zz['fields'][$no]['merge_ignore'] = true;
		break;
	
	case 'contact_id':
		$zz['fields'][$no]['display_field'] = 'person';
		// if an import error occured
		if (wrap_access('tournaments_players_change', $brick['data']['event_rights']))
			$zz['fields'][$no]['type'] = 'select';
		break;

	case 'team_id':
		$zz['fields'][$no]['merge_ignore'] = true;
		$zz['fields'][$no]['display_field'] = 'team';
		if (wrap_setting('tournaments_type_team')) {
			$zz['fields'][$no]['sql'] = sprintf('SELECT team_id
					, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
				FROM teams
				WHERE event_id = %d
				ORDER BY team_id', $brick['data']['event_id']);
			if (empty($_GET['filter']['team']))
				$zz['fields'][$no]['group_in_list'] = true;
			else
				$zz['fields'][$no]['hide_in_list'] = true;
		} else {
			$zz['fields'][$no]['hide_in_form'] = true;
		}
		break;
	
	case 'setzliste_no':
	case 'qualification':
	case 'qualification_event_id':
	case 'urkundentext':
		if (!empty($brick['data']['event_team'])) {
			$zz['fields'][$no]['hide_in_form'] = true;
		}
		break;

	case 'brett_no':
	case 'rang_no':
		if (!wrap_setting('tournaments_type_team')) {
			$zz['fields'][$no]['hide_in_form'] = true;
			$zz['fields'][$no]['hide_in_list'] = true;
		}
		break;

	case 'gastspieler':
		if (!$brick['data']['gastspieler'])
			unset($zz['fields'][$no]);
		break;

	case 'buchungen':
	case 'usergroup_category':
	case 'series_parameters':
	case 'parameters':
		unset($zz['fields'][$no]);
		break;

	}
}

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


$zz['fields'][52]['field_name'] = 'date_of_birth';
$zz['fields'][52]['type'] = 'display';
$zz['fields'][52]['type_detail'] = 'date';
$zz['fields'][52]['title'] = 'Geb. Dat.';
$zz['fields'][52]['search'] = 'persons.date_of_birth';
$zz['fields'][52]['hide_in_form'] = true;

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
			AND current = "yes"
		) AS player_id_fide
		, IFNULL(
			TIMESTAMPDIFF(YEAR, date_of_birth, IFNULL(CAST(IF(
				SUBSTRING(date_of_death, -6) = "-00-00",
				CONCAT(YEAR(date_of_death), "-01-01"), date_of_death) AS DATE
			), CURDATE())),
			YEAR(IFNULL(date_of_death, CURDATE())) - YEAR(date_of_birth)
		) AS age
		, participation_status.category_short
		, IFNULL(participation_status.description, participation_status.category) AS status_category
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
	LEFT JOIN categories participation_status
		ON participations.status_category_id = participation_status.category_id
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

if (!wrap_access('tournaments_players_edit', $brick['data']['event_rights']))
	$zz['access'] = 'none';

unset($zz['add']); // just add player here, no other groups
