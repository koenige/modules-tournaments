<?php 

/**
 * tournaments module
 * table script: pairings
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2015, 2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Paarungen';
$zz['table'] = 'paarungen';

if (!isset($values['where'])) $values['where'] = '';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'paarung_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Termin';
$zz['fields'][2]['field_name'] = 'event_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT event_id, event
	FROM events
	WHERE ISNULL(main_event_id)
	ORDER BY date_begin, identifier';
$zz['fields'][2]['key_field_name'] = 'events.event_id';
$zz['fields'][2]['display_field'] = 'event';
$zz['fields'][2]['if']['where']['hide_in_list'] = true;
$zz['fields'][2]['if']['where']['hide_in_form'] = true;

$zz['fields'][15]['title'] = 'Runde';
$zz['fields'][15]['title_tab'] = 'Rd.';
$zz['fields'][15]['field_name'] = 'runde_no';
$zz['fields'][15]['type'] = 'number';
$zz['fields'][15]['if']['where']['hide_in_list'] = true;
$zz['fields'][15]['if']['where']['hide_in_form'] = true;

$zz['fields'][3]['title'] = 'Ort';
$zz['fields'][3]['field_name'] = 'place_contact_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = sprintf('SELECT contact_id, contact
	FROM contacts
	WHERE contact_category_id = %d', wrap_category_id('kontakte/veranstaltungsort'));
$zz['fields'][3]['key_field_name'] = 'contact_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['hide_in_list'] = true;

$zz['fields'][4]['field_name'] = 'spielbeginn';
$zz['fields'][4]['type'] = 'time';
$zz['fields'][4]['hide_in_list'] = true;

$zz['fields'][5]['title'] = 'Tisch';
$zz['fields'][5]['field_name'] = 'tisch_no';
$zz['fields'][5]['type'] = 'number';
$zz['fields'][5]['auto_value'] = 'increment';

$zz['fields'][6]['title'] = 'Heimteam';
$zz['fields'][6]['field_name'] = 'heim_team_id';
$zz['fields'][6]['type'] = 'select';
$zz['fields'][6]['sql'] = 'SELECT team_id, CONCAT(event, ": ") AS event
		, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
	FROM teams
	LEFT JOIN events USING (event_id)
	'.$values['where'].'
	ORDER BY team_id';
if ($values['where']) $zz['fields'][6]['sql_ignore'] = 'event';
$zz['fields'][6]['display_field'] = 'heimteam';
$zz['fields'][6]['search'] = 'heimteams.identifier';
$zz['fields'][6]['character_set'] = 'latin1';

$zz['fields'][7]['title'] = 'Auswärtsteam';
$zz['fields'][7]['field_name'] = 'auswaerts_team_id';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['sql'] = 'SELECT team_id, CONCAT(event, ": ") AS event
		, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
	FROM teams
	LEFT JOIN events USING (event_id)
	'.$values['where'].'
	ORDER BY team_id';
if ($values['where']) $zz['fields'][7]['sql_ignore'] = 'event';
$zz['fields'][7]['display_field'] = 'auswaertsteam';
$zz['fields'][7]['search'] = 'auswaertsteams.identifier';
$zz['fields'][7]['character_set'] = 'latin1';

$zz['fields'][8]['field_name'] = 'kommentar';
$zz['fields'][8]['type'] = 'memo';
$zz['fields'][8]['hide_in_list'] = true;
$zz['fields'][8]['separator'] = true;

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;


$zz['sql'] = 'SELECT paarungen.*
		, events.event
		, events.identifier AS event_identifier
		, CONCAT(heimteams.team, IFNULL(CONCAT(" ", heimteams.team_no), "")) AS heimteam
		, CONCAT(auswaertsteams.team, IFNULL(CONCAT(" ", auswaertsteams.team_no), "")) AS auswaertsteam
	FROM paarungen
	LEFT JOIN events USING (event_id)
	LEFT JOIN teams heimteams
		ON heimteams.team_id = paarungen.heim_team_id
	LEFT JOIN teams auswaertsteams
		ON auswaertsteams.team_id = paarungen.auswaerts_team_id
	ORDER BY events.date_begin, events.identifier, paarungen.runde_no, tisch_no
';

$zz['subtitle']['event_id']['sql'] = 'SELECT event FROM events';
$zz['subtitle']['event_id']['var'] = ['event'];

$zz['subtitle']['runde_no']['value'] = true;
$zz['subtitle']['runde_no']['prefix'] = 'Runde ';

$zz['details'][0]['title'] = 'Partien';
$zz['details'][0]['link'] = [
	'string1' => '/intern/termine/', 'field1' => 'event_identifier',
	'string2' => '/runde/', 'field2' => 'runde_no',
	'string3' => '/', 'field3' => 'tisch_no',
	'string4' => '/'
];

$zz['hooks']['after_delete'] = 'mf_tournaments_standings_update';
