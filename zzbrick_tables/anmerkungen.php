<?php 

/**
 * Zugzwang Project
 * table script for remarks
 *
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012, 2014-2015, 2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Anmerkungen';
$zz['table'] = 'anmerkungen';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'anmerkung_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'anmerkung';
$zz['fields'][2]['type'] = 'memo';

$zz['fields'][3]['field_name'] = 'team_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT team_id, CONCAT(event, " ", YEAR(events.date_begin), ": ") AS event
		, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
	FROM teams
	LEFT JOIN events USING (event_id)
	ORDER BY team_id';
$zz['fields'][3]['display_field'] = 'teamname';
$zz['fields'][3]['search'] = 'teams.kennung';
if (!empty($_GET['where']['team_id'])) {
	$zz['fields'][3]['hide_in_list'] = true;	
} else {
	$zz['fields'][3]['list_append_next'] = true;

	$zz['fields'][8]['type'] = 'display';
	$zz['fields'][8]['field_name'] = 'event';
	$zz['fields'][8]['hide_in_form'] = true;
	$zz['fields'][8]['display_field'] = 'event';
	$zz['fields'][8]['list_prefix'] = '<br>';
}

$zz['fields'][9]['field_name'] = 'teilnahme_id';
$zz['fields'][9]['type'] = 'select';
$zz['fields'][9]['sql'] = 'SELECT teilnahme_id
		, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person, event
	FROM teilnahmen
	LEFT JOIN personen USING (person_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN events USING (event_id)
	ORDER BY contacts.identifier
';
$zz['fields'][9]['display_field'] = 'person';
$zz['fields'][9]['search'] = 'CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname)';

$zz['fields'][4]['title'] = 'Autor';
$zz['fields'][4]['field_name'] = 'autor_person_id';
$zz['fields'][4]['type'] = 'hidden';
$zz['fields'][4]['type_detail'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT person_id
		, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person
		, IFNULL(YEAR(geburtsdatum), "unbek.") AS geburtsjahr
		, identifier
	FROM personen
	LEFT JOIN contacts USING (contact_id)
	ORDER BY nachname, vorname, YEAR(geburtsdatum), identifier';
$zz['fields'][4]['unique_ignore'] = ['geburtsjahr', 'identifier'];
$zz['fields'][4]['display_field'] = 'person';
$zz['fields'][4]['key_field_name'] = 'person_id';
$zz['fields'][4]['search'] = 'CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname)';
if (!empty($_SESSION)) {
	$zz['fields'][4]['default'] = $_SESSION['person_id'];
}

$zz['fields'][5]['field_name'] = 'sichtbarkeit';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['set'] = ['Team', 'Organisator'];
$zz['fields'][5]['explanation'] = 'Keine Auswahl: nur für Veranstalter selbst sichtbar';

$zz['fields'][6]['title'] = 'Status';
$zz['fields'][6]['field_name'] = 'anmerkung_status';
$zz['fields'][6]['type'] = 'select';
$zz['fields'][6]['enum'] = ['offen', 'erledigt'];
$zz['fields'][6]['default'] = 'offen';

$zz['fields'][7]['field_name'] = 'erstellt';
$zz['fields'][7]['type'] = 'hidden';
$zz['fields'][7]['type_detail'] = 'datetime';
$zz['fields'][7]['default'] = date('Y-m-d H:i:s');
$zz['fields'][7]['display_field'] = 'erstellt_de';
$zz['fields'][7]['exclude_from_search'] = true;

$zz['fields'][10]['field_name'] = 'benachrichtigung';
$zz['fields'][10]['type'] = 'option';
$zz['fields'][10]['type_detail'] = 'select';
$zz['fields'][10]['enum'] = ['ja', 'nein'];
$zz['fields'][10]['default'] = 'nein';

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT anmerkungen.*
		, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person
		, teams.kennung AS team_identifier
		, DATE_FORMAT(erstellt, "%d.%m.%Y") AS erstellt_de
		, CONCAT(teams.team, IFNULL(CONCAT(" ", team_no), "")) AS teamname
		, event
	FROM anmerkungen
	LEFT JOIN teams USING (team_id)
	LEFT JOIN events USING (event_id)
	LEFT JOIN personen
		ON anmerkungen.autor_person_id = personen.person_id
';
$zz['sqlorder'] = ' ORDER BY erstellt DESC';

$zz['filter'][1]['title'] = 'Status';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'anmerkung_status';
$zz['filter'][1]['sql'] = 'SELECT DISTINCT anmerkung_status, anmerkung_status AS titel
	FROM anmerkungen
	ORDER BY anmerkung_status';
$zz['filter'][1]['default_selection'] = 'offen';

$zz['hooks']['after_insert'][] = 'mf_tournaments_remarks_mail';
$zz['hooks']['after_update'][] = 'mf_tournaments_remarks_mail';
