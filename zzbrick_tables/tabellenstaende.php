<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2015, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Tabellenstände


$zz['title'] = 'Tabellenstände';
$zz['table'] = 'tabellenstaende';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tabellenstand_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Termin';
$zz['fields'][2]['field_name'] = 'event_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT event_id, termin, kennung
	FROM events
	WHERE ISNULL(main_event_id)
	ORDER BY beginn, kennung
';
$zz['fields'][2]['display_field'] = 'termin';
$zz['fields'][2]['sql_ignore'] = 'kennung';
$zz['fields'][2]['if']['where']['hide_in_form'] = true;
$zz['fields'][2]['if']['where']['hide_in_list'] = true;

$zz['fields'][3]['title'] = 'Runde';
$zz['fields'][3]['field_name'] = 'runde_no';
$zz['fields'][3]['type'] = 'number';
$zz['fields'][3]['if']['where']['hide_in_list'] = true;
$zz['fields'][3]['if']['where']['hide_in_form'] = true;

$zz['fields'][6]['title'] = 'Platz';
$zz['fields'][6]['field_name'] = 'platz_no';
$zz['fields'][6]['type'] = 'number';
$zz['fields'][6]['suffix'] = '.';
$zz['fields'][6]['list_suffix'] = '.';

$zz['fields'][11]['title'] = 'Platz (Brett)';
$zz['fields'][11]['field_name'] = 'platz_brett_no';
$zz['fields'][11]['type'] = 'number';
$zz['fields'][11]['suffix'] = '.';
$zz['fields'][11]['list_suffix'] = '.';
$zz['fields'][11]['hide_in_list'] = true;

$zz['fields'][4]['title_tab'] = 'Team / Spieler';
$zz['fields'][4]['field_name'] = 'team_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT team_id, CONCAT(termin, ": ") AS termin
		, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
	FROM teams
	LEFT JOIN events USING (event_id)
	ORDER BY team, team_no';
$zz['fields'][4]['display_field'] = 'team';
$zz['fields'][4]['search'] = 'teams.kennung';
$zz['fields'][4]['list_append_next'] = true;

$zz['fields'][5]['field_name'] = 'person_id';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['sql'] = 'SELECT person_id
		, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person
		, IFNULL(YEAR(geburtsdatum), "unbek.") AS geburtsjahr
		, identifier
	FROM personen
	LEFT JOIN contacts USING (contact_id)
	ORDER BY nachname, vorname';
$zz['fields'][5]['display_field'] = 'person';
$zz['fields'][5]['search'] = 'CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname)';
$zz['fields'][5]['unique_ignore'] = ['geburtsjahr', 'identifier'];

$zz['fields'][7]['title'] = 'Gewonnen';
$zz['fields'][7]['title_tab'] = 'G';
$zz['fields'][7]['field_name'] = 'spiele_g';
$zz['fields'][7]['type'] = 'number';
$zz['fields'][7]['null'] = true;

$zz['fields'][8]['title'] = 'Unentschieden';
$zz['fields'][8]['title_tab'] = 'U';
$zz['fields'][8]['field_name'] = 'spiele_u';
$zz['fields'][8]['type'] = 'number';
$zz['fields'][8]['null'] = true;

$zz['fields'][9]['title'] = 'Verloren';
$zz['fields'][9]['title_tab'] = 'V';
$zz['fields'][9]['field_name'] = 'spiele_v';
$zz['fields'][9]['type'] = 'number';
$zz['fields'][9]['null'] = true;

$zz['fields'][10] = zzform_include_table('tabellenstaende-wertungen');
$zz['fields'][10]['title'] = 'Wertungen';
$zz['fields'][10]['table_name'] = 'wertungen';
$zz['fields'][10]['type'] = 'subtable';
$zz['fields'][10]['min_records'] = 3;
$zz['fields'][10]['max_records'] = 10;
$zz['fields'][10]['form_display'] = 'lines';
$zz['fields'][10]['hide_in_list'] = true;
$zz['fields'][10]['fields'][2]['type'] = 'foreign_key';


$zz['sql'] = 'SELECT tabellenstaende.*
		, events.termin
		, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
		, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person
	FROM tabellenstaende
	LEFT JOIN events USING (event_id)
	LEFT JOIN teams USING (team_id)
	LEFT JOIN personen USING (person_id)
';
$zz['sqlorder'] = ' ORDER BY events.beginn, events.kennung, runde_no, platz_no';

$zz['subtitle']['event_id']['sql'] = 'SELECT termin FROM events';
$zz['subtitle']['event_id']['var'] = ['termin'];

$zz['subtitle']['runde_no']['value'] = true;
$zz['subtitle']['runde_no']['prefix'] = 'Runde ';

$zz['filter'][1]['title'] = 'Typ';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'team_id';
$zz['filter'][1]['selection']['NULL'] = 'Spieler';
$zz['filter'][1]['selection']['!NULL'] = 'Teams';
$zz['filter'][1]['default_selection'] = '!NULL';
