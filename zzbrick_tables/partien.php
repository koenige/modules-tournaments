<?php 

/**
 * tournaments module
 * table script: games
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2015, 2017-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Games';
$zz['table'] = 'partien';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'partie_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Pairing';
$zz['fields'][2]['field_name'] = 'paarung_id';
$zz['fields'][2]['type'] = 'write_once';
$zz['fields'][2]['type_detail'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT paarung_id
	FROM paarungen';
$zz['fields'][2]['if']['where']['hide_in_list'] = true;
$zz['fields'][2]['if']['where']['hide_in_form'] = true;
$zz['fields'][2]['hide_in_list_if_empty'] = true;
$zz['fields'][2]['if'][2]['hide_in_form'] = true;

$zz['fields'][3]['field_name'] = 'event_id';
$zz['fields'][3]['type'] = 'write_once';
$zz['fields'][3]['type_detail'] = 'select';
$zz['fields'][3]['sql'] = wrap_sql_query('tournaments_zzform_event');
$zz['fields'][3]['display_field'] = 'event';
$zz['fields'][3]['if']['where']['hide_in_list'] = true;
$zz['fields'][3]['if']['where']['hide_in_form'] = true;

$zz['fields'][4]['title'] = 'Round';
$zz['fields'][4]['title_tab'] = 'Rd.';
$zz['fields'][4]['field_name'] = 'runde_no';
$zz['fields'][4]['type'] = 'write_once';
$zz['fields'][4]['type_detail'] = 'number';
$zz['fields'][4]['if']['where']['hide_in_list'] = true;
$zz['fields'][4]['if']['where']['hide_in_form'] = true;

$zz['fields'][5]['title'] = 'Board';
$zz['fields'][5]['field_name'] = 'brett_no';
$zz['fields'][5]['type'] = 'number';

$zz['fields'][6]['title'] = 'White';
$zz['fields'][6]['field_name'] = 'weiss_person_id';
$zz['fields'][6]['display_field'] = 'weiss';
$zz['fields'][6]['type'] = 'select';
$zz['fields'][6]['sql'] = 'SELECT person_id, contact
	FROM contacts
	LEFT JOIN persons USING (contact_id)
	ORDER BY identifier';
$zz['fields'][6]['search'] = 'CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname)';

$zz['fields'][7]['title_tab'] = 'Result';
$zz['fields'][7]['title'] = 'Result White';
$zz['fields'][7]['field_name'] = 'weiss_ergebnis';
$zz['fields'][7]['type'] = 'number';
$zz['fields'][7]['null'] = true;
$zz['fields'][7]['list_append_next'] = true;
$zz['fields'][7]['list_suffix'] = ' : ';

$zz['fields'][9]['title'] = 'Result Black';
$zz['fields'][9]['field_name'] = 'schwarz_ergebnis';
$zz['fields'][9]['type'] = 'number';
$zz['fields'][9]['null'] = true;

$zz['fields'][8]['title'] = 'Black';
$zz['fields'][8]['field_name'] = 'schwarz_person_id';
$zz['fields'][8]['display_field'] = 'schwarz';
$zz['fields'][8]['type'] = 'select';
$zz['fields'][8]['sql'] = 'SELECT person_id, contact
	FROM contacts
	LEFT JOIN persons USING (contact_id)
	ORDER BY identifier';
$zz['fields'][8]['search'] = 'CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname)';

$zz['fields'][10]['title'] = 'Color Home Player';
$zz['fields'][10]['field_name'] = 'heim_spieler_farbe';
$zz['fields'][10]['type'] = 'select';
$zz['fields'][10]['enum'] = ['weiß','schwarz'];
$zz['fields'][10]['enum_title'] = [wrap_text('White'), wrap_text('Black')];
$zz['fields'][10]['hide_in_list_if_empty'] = true;
$zz['fields'][10]['if'][2]['hide_in_form'] = true;

$zz['fields'][11]['title_tab'] = 'Team Result';
$zz['fields'][11]['title'] = 'Home Team Score';
$zz['fields'][11]['field_name'] = 'heim_wertung';
$zz['fields'][11]['type'] = 'number';
$zz['fields'][11]['null'] = true;
$zz['fields'][11]['list_append_next'] = true;
$zz['fields'][11]['list_suffix'] = ' : ';
$zz['fields'][11]['hide_in_list_if_empty'] = true;
$zz['fields'][11]['if'][2]['hide_in_form'] = true;

$zz['fields'][12]['title'] = 'Away Team Score';
$zz['fields'][12]['field_name'] = 'auswaerts_wertung';
$zz['fields'][12]['type'] = 'number';
$zz['fields'][12]['null'] = true;
$zz['fields'][12]['hide_in_list_if_empty'] = true;
$zz['fields'][12]['if'][2]['hide_in_form'] = true;

$zz['fields'][13]['title'] = 'Status';
$zz['fields'][13]['field_name'] = 'partiestatus_category_id';
$zz['fields'][13]['type']= 'select';
$zz['fields'][13]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories';
$zz['fields'][13]['display_field'] = 'category';
$zz['fields'][13]['show_hierarchy'] = 'main_category_id';
$zz['fields'][13]['show_hierarchy_subtree'] = wrap_category_id('partiestatus');

$zz['fields'][15]['title'] = 'PGN';
$zz['fields'][15]['field_name'] = 'pgn';
$zz['fields'][15]['hide_in_list'] = true;
$zz['fields'][15]['type'] = 'memo';

$zz['fields'][14]['title'] = 'Remarks';
$zz['fields'][14]['field_name'] = 'kommentar';
$zz['fields'][14]['type'] = 'memo';
$zz['fields'][14]['format'] = 'markdown';
$zz['fields'][14]['rows'] = 3;
$zz['fields'][14]['hide_in_list'] = true;

$zz['fields'][16]['title'] = 'ECO';
$zz['fields'][16]['field_name'] = 'eco';
$zz['fields'][16]['type'] = 'text';
$zz['fields'][16]['replace_values'] = ['*' => ''];
$zz['fields'][16]['hide_in_list_if_empty'] = true;

$zz['fields'][17]['title'] = 'Half moves';
$zz['fields'][17]['title_tab'] = ['HM', ['context' => 'Half moves']];
$zz['fields'][17]['field_name'] = 'halbzuege';
$zz['fields'][17]['type'] = 'number';
$zz['fields'][17]['hide_in_list_if_empty'] = true;

$zz['fields'][19]['title'] = 'Time White';
$zz['fields'][19]['field_name'] = 'weiss_zeit';
$zz['fields'][19]['type'] = 'time';
$zz['fields'][19]['time_format'] = 'H:i:s';
$zz['fields'][19]['hide_in_list_if_empty'] = true;

$zz['fields'][20]['title'] = 'Time Black';
$zz['fields'][20]['field_name'] = 'schwarz_zeit';
$zz['fields'][20]['type'] = 'time';
$zz['fields'][20]['time_format'] = 'H:i:s';
$zz['fields'][20]['hide_in_list_if_empty'] = true;

$zz['fields'][18]['title'] = 'PGN-Result?';
$zz['fields'][18]['field_name'] = 'block_ergebnis_aus_pgn';
$zz['fields'][18]['type'] = 'select';
$zz['fields'][18]['enum'] = ['ja', 'nein'];
$zz['fields'][18]['enum_title'] = [wrap_text('yes'), wrap_text('no')];
$zz['fields'][18]['default'] = 'nein';
$zz['fields'][18]['hide_in_list'] = true;
$zz['fields'][18]['explanation'] = 'If so, deviating results from PGN files must no longer be adopted.';

$zz['fields'][24]['title'] = 'Swapped Colors?';
$zz['fields'][24]['field_name'] = 'vertauschte_farben';
$zz['fields'][24]['type'] = 'select';
$zz['fields'][24]['enum'] = ['ja', 'nein'];
$zz['fields'][24]['enum_title'] = [wrap_text('yes'), wrap_text('no')];
$zz['fields'][24]['default'] = 'nein';
$zz['fields'][24]['hide_in_list'] = true;
$zz['fields'][24]['explanation'] = 'Was the game played incorrectly with the colors swapped?';

if (wrap_setting('tournaments_upload_pgn')) {
	$zz['fields'][23]['title'] = 'PGN File';
	$zz['fields'][23]['field_name'] = 'pgn';
	$zz['fields'][23]['type'] = 'upload_image';
	$zz['fields'][23]['path'] = [
		'root' => wrap_setting('tournaments_pgn_dir').'/',
		'field1' => 'event_identifier',
		'string2' => '/',
		'field2' => 'pgn_segment',
		'string3' => '.pgn'
	];
	$zz['fields'][23]['input_filetypes'] = ['pgn'];
	$zz['fields'][23]['path_web'] = [
		'area' => 'tournaments_pgns_raw',
		'fields' => ['event_identifier', 'pgn_segment']
	];
	$zz['fields'][23]['link'] = $zz['fields'][23]['path_web'];
	$zz['fields'][23]['optional_image'] = true;

	$zz['fields'][23]['image'][0]['title'] = 'gro&szlig;';
	$zz['fields'][23]['image'][0]['field_name'] = 'gross';
	$zz['fields'][23]['dont_show_missing'] = true;
	$zz['fields'][23]['hide_in_list_if_empty'] = true;
}

$zz['fields'][25]['title'] = 'URL';
$zz['fields'][25]['field_name'] = 'url';
$zz['fields'][25]['explanation'] = 'If played online, link to the server';
$zz['fields'][25]['type'] = 'url';
$zz['fields'][25]['hide_in_list'] = true;

$zz['fields'][98]['title'] = 'Result reported';
$zz['fields'][98]['field_name'] = 'ergebnis_gemeldet_um';
$zz['fields'][98]['type'] = 'hidden';
$zz['fields'][98]['type_detail'] = 'datetime';
$zz['fields'][98]['hide_in_list'] = true;

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT partien.*
		, event, events.identifier AS event_identifier, paarungen.tisch_no
		, CONCAT(partien.runde_no, IFNULL(CONCAT("-", paarungen.tisch_no), ""), "-", partien.brett_no) AS pgn_segment
		, CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname) AS weiss
		, CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname) AS schwarz
		, categories.category
	FROM partien
	LEFT JOIN events USING (event_id)
	LEFT JOIN tournaments USING (event_id)
	LEFT JOIN categories tournament_forms
		ON tournaments.turnierform_category_id = tournament_forms.category_id
	LEFT JOIN paarungen USING (paarung_id)
	LEFT JOIN categories
		ON categories.category_id = partien.partiestatus_category_id
	LEFT JOIN persons white_contact
		ON white_contact.person_id = partien.weiss_person_id
	LEFT JOIN persons black_contact
		ON black_contact.person_id = partien.schwarz_person_id
	LEFT JOIN participations weiss
		ON weiss.contact_id = white_contact.contact_id
		AND weiss.event_id = partien.event_id
		AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", paarungen.auswaerts_team_id, paarungen.heim_team_id))
		AND weiss.usergroup_id = /*_ID usergroups spieler _*/
	LEFT JOIN participations schwarz
		ON schwarz.contact_id = black_contact.contact_id
		AND schwarz.event_id = partien.event_id
		AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", paarungen.heim_team_id, paarungen.auswaerts_team_id))
		AND schwarz.usergroup_id = /*_ID usergroups spieler _*/';
$zz['sqlorder'] = ' ORDER BY events.date_begin, events.identifier, runde_no, brett_no';

$zz['subtitle']['event_id']['sql'] = 'SELECT event_id, event FROM events';
$zz['subtitle']['event_id']['var'] = ['event'];

$zz['subtitle']['runde_no']['value'] = true;
$zz['subtitle']['runde_no']['prefix'] = 'Runde ';

$zz['subtitle']['paarung_id']['sql'] = 'SELECT tisch_no
		, CONCAT(heimteams.team, IFNULL(CONCAT(" ", heimteams.team_no), "")) AS heimteam
		, CONCAT(auswaertsteams.team, IFNULL(CONCAT(" ", auswaertsteams.team_no), "")) AS auswaertsteam
	FROM paarungen
	LEFT JOIN teams heimteams
		ON heimteams.team_id = paarungen.heim_team_id
	LEFT JOIN teams auswaertsteams
		ON auswaertsteams.team_id = paarungen.auswaerts_team_id
';
$zz['subtitle']['paarung_id']['var'] = ['tisch_no', 'heimteam', 'auswaertsteam'];
$zz['subtitle']['paarung_id']['prefix'] = 'Tisch ';
$zz['subtitle']['paarung_id']['concat'] = [' <br><small>', ' – '];
$zz['subtitle']['paarung_id']['suffix'] = '</small>';

$zz['hooks']['after_insert'][] = 'mf_tournaments_standings_update';
$zz['hooks']['after_update'][] = 'mf_tournaments_standings_update';
$zz['hooks']['after_delete'][] = 'mf_tournaments_standings_update';
$zz['hooks']['after_upload'][] = 'mf_tournaments_games_update';

$zz['hooks']['before_insert'][] = 'mf_tournaments_result_reported';
$zz['hooks']['before_update'][] = 'mf_tournaments_result_reported';

$zz['hooks']['before_insert'][] = 'mf_tournaments_team_points';

$zz['conditions'][1]['scope'] = 'record';
$zz['conditions'][1]['where'] = 'ISNULL(tisch_no)';

$zz['conditions'][2]['scope'] = 'record';
$zz['conditions'][2]['where'] = 'tournament_forms.parameters LIKE "%&single=1%"';
