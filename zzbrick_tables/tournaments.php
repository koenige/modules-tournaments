<?php 

/**
 * tournaments module
 * table script: tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Turniere';
$zz['table'] = '/*_PREFIX_*/tournaments';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tournament_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'event_id';
$zz['fields'][2]['type'] = 'write_once';
$zz['fields'][2]['type_detail'] = 'select';
$zz['fields'][2]['sql'] = wrap_sql_query('tournaments_zzform_event');
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(/*_PREFIX_*/events.event, " ", IFNULL(event_year, YEAR(date_begin)))';
$zz['fields'][2]['unique'] = true;
$zz['fields'][2]['if']['where']['hide_in_form'] = true;
$zz['fields'][2]['link'] = [
	'area' => 'events_internal_event',
	'fields' => ['event_identifier']
];
$zz['fields'][2]['dont_show_where_class'] = true;

$zz['fields'][3]['title'] = 'Turnierform';
$zz['fields'][3]['title_tab'] = 'Form';
$zz['fields'][3]['field_name'] = 'turnierform_category_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM /*_PREFIX_*/categories
	ORDER BY category';
$zz['fields'][3]['if'][1]['sql'] = 'SELECT category_id, category, main_category_id
	FROM /*_PREFIX_*/categories
	WHERE parameters LIKE "%team=1%"
	ORDER BY category';
$zz['fields'][3]['if'][2]['sql'] = 'SELECT category_id, category, main_category_id
	FROM /*_PREFIX_*/categories
	WHERE parameters LIKE "%team=0%"
	ORDER BY category';
$zz['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz['fields'][3]['show_hierarchy_subtree'] = wrap_category_id('turnierformen');
$zz['fields'][3]['display_field'] = 'turnierform';
$zz['fields'][3]['search'] = 'turnierformen.category_short';
$zz['fields'][3]['character_set'] = 'utf8';

$zz['fields'][20]['title_tab'] = 'Rd.';
$zz['fields'][20]['title'] = 'Runden';
$zz['fields'][20]['field_name'] = 'runden';
$zz['fields'][20]['type'] = 'number';

$zz['fields'][4]['title'] = 'Modus';
$zz['fields'][4]['field_name'] = 'modus_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT category_id, category, main_category_id
	FROM /*_PREFIX_*/categories
	ORDER BY category';
$zz['fields'][4]['show_hierarchy'] = 'main_category_id';
$zz['fields'][4]['show_hierarchy_subtree'] = wrap_category_id('turniermodi');
$zz['fields'][4]['display_field'] = 'modus';
$zz['fields'][4]['search'] = 'modus.category_short';
$zz['fields'][4]['character_set'] = 'utf8';

$zz['fields'][49] = zzform_include('turniere-bedenkzeiten');
$zz['fields'][49]['title'] = 'Bedenkzeit';
$zz['fields'][49]['type'] = 'subtable';
$zz['fields'][49]['min_records'] = 1;
$zz['fields'][49]['max_records'] = 10;
$zz['fields'][49]['form_display'] = 'horizontal';
$zz['fields'][49]['hide_in_list'] = true;
$zz['fields'][49]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][49]['sql'] .= $zz['fields'][49]['sqlorder'];
$zz['fields'][49]['explanation'] = 'Zeit in Minuten, Bonus pro Zug in Sekunden';

$zz['fields'][5]['title_append'] = 'Alter';
$zz['fields'][5]['title_tab'] = 'Alter';
$zz['fields'][5]['field_name'] = 'alter_min';
$zz['fields'][5]['append_next'] = true;
$zz['fields'][5]['list_append_next'] = true;
$zz['fields'][5]['explanation'] = 'Alter am 01.01. des Jahres';

$zz['fields'][6]['field_name'] = 'alter_max';
$zz['fields'][6]['prefix'] = ' – ';
$zz['fields'][6]['list_prefix'] = ' – ';

$zz['fields'][7]['field_name'] = 'geschlecht';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['set'] = ['m', 'w'];
$zz['fields'][7]['set_title'] = ['männlich', 'weiblich'];
$zz['fields'][7]['default'] = ['m', 'w'];
$zz['fields'][7]['hide_in_list'] = true;

$zz['fields'][8]['title_append'] = 'DWZ';
$zz['fields'][8]['title_tab'] = 'DWZ';
$zz['fields'][8]['type'] = 'number';
$zz['fields'][8]['field_name'] = 'dwz_min';
$zz['fields'][8]['append_next'] = true;
$zz['fields'][8]['list_append_next'] = true;
$zz['fields'][8]['hide_in_list'] = true;

$zz['fields'][9]['field_name'] = 'dwz_max';
$zz['fields'][9]['type'] = 'number';
$zz['fields'][9]['prefix'] = ' – ';
$zz['fields'][9]['list_prefix'] = ' – ';
$zz['fields'][9]['hide_in_list'] = true;

$zz['fields'][10]['title_append'] = 'Elo';
$zz['fields'][10]['title_tab'] = 'Elo';
$zz['fields'][10]['type'] = 'number';
$zz['fields'][10]['field_name'] = 'elo_min';
$zz['fields'][10]['append_next'] = true;
$zz['fields'][10]['list_append_next'] = true;
$zz['fields'][10]['hide_in_list'] = true;

$zz['fields'][11]['field_name'] = 'elo_max';
$zz['fields'][11]['type'] = 'number';
$zz['fields'][11]['prefix'] = ' – ';
$zz['fields'][11]['list_prefix'] = ' – ';
$zz['fields'][11]['hide_in_list'] = true;

$zz['fields'][53]['field_name'] = 'ratings_updated';
$zz['fields'][53]['type'] = 'date';
$zz['fields'][53]['hide_in_list'] = true;
$zz['fields'][53]['if']['add']['hide_in_form'] = true;

$zz['fields'][21] = zzform_include('turniere-wertungen');
$zz['fields'][21]['title'] = 'Wertungen';
$zz['fields'][21]['type'] = 'subtable';
$zz['fields'][21]['min_records'] = 3;
$zz['fields'][21]['max_records'] = 10;
$zz['fields'][21]['form_display'] = 'horizontal';
$zz['fields'][21]['hide_in_list'] = true;
$zz['fields'][21]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][21]['sql'] .= $zz['fields'][21]['sqlorder'];

$zz['fields'][24]['field_name'] = 'notationspflicht';
$zz['fields'][24]['type'] = 'select';
$zz['fields'][24]['enum'] = ['ja', 'nein'];
$zz['fields'][24]['default'] = 'ja';
$zz['fields'][24]['hide_in_list'] = true;

$zz['fields'][23]['field_name'] = 'livebretter';
$zz['fields'][23]['type'] = 'text';
$zz['fields'][23]['explanation'] = 'Bretter mit Liveübertragung, bspw. 1-6, 9, 11 oder 1.1-1.6 (Tisch.Brett)';
$zz['fields'][23]['hide_in_list'] = true;

$zz['fields'][28] = zzform_include('tournaments-identifiers');
$zz['fields'][28]['title'] = 'Kennungen';
$zz['fields'][28]['class'] = 'kennungen';
$zz['fields'][28]['type'] = 'subtable';
$zz['fields'][28]['min_records'] = 1;
$zz['fields'][28]['max_records'] = 10;
$zz['fields'][28]['form_display'] = 'lines';
$zz['fields'][28]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][28]['sql'] .= $zz['fields'][28]['sqlorder'];
$zz['fields'][28]['subselect']['sql'] = 'SELECT tournament_id, category_short
		, tournaments_identifiers.identifier
	FROM /*_PREFIX_*/tournaments_identifiers
	LEFT JOIN categories
		ON categories.category_id = tournaments_identifiers.identifier_category_id';

$zz['fields'][27]['title'] = 'Tabellenstände';
$zz['fields'][27]['hide_in_list'] = true;
$zz['fields'][27]['field_name'] = 'tabellenstaende';
$zz['fields'][27]['explanation'] = 'Zusätzliche Tabellenstände als Filter, Eingabe als Liste mit Kommas
 (<a href="/hilfe/anleitung/#tabellenstaende">Anleitung</a>)';
$zz['fields'][27]['separator'] = true;

if (wrap_setting('tournaments_upload_pgn')) {
	$zz['fields'][25]['title'] = 'PGN-Datei';
	$zz['fields'][25]['field_name'] = 'pgnfile';
	$zz['fields'][25]['dont_show_missing'] = true;
	$zz['fields'][25]['type'] = 'upload_image';
	$zz['fields'][25]['path'] = [
		'root' => wrap_setting('media_folder').'/pgn/',
		'webroot' => wrap_setting('media_internal_path').'/pgn/',
		'field1' => 'event_identifier',
		'string2' => '/gesamt',
		'string3' => '.pgn'
	];
	$zz['fields'][25]['input_filetypes'] = ['pgn'];
	$zz['fields'][25]['link'] = [
		'string1' => wrap_setting('media_internal_path').'/pgn/',
		'field1' => 'event_identifier',
		'string2' => '/gesamt',
		'string3' => '.pgn'
	];
	$zz['fields'][25]['optional_image'] = true;
	$zz['fields'][25]['image'][0]['title'] = 'pgn';
	$zz['fields'][25]['image'][0]['field_name'] = 'pgn';
	$zz['fields'][25]['image'][0]['path'] = $zz['fields'][25]['path'];
	$zz['fields'][25]['title_tab'] = 'Dateien';
	$zz['fields'][25]['if']['add']['hide_in_form'] = true;
}

// field for uploads of external tournament software
// @todo show related files of a tournament in list view, automatically somehow
$zz['fields'][22] = [];

$zz['fields'][13]['title_tab'] = 'Bretter';
$zz['fields'][13]['title_append'] = 'Bretter';
$zz['fields'][13]['title'] = 'Bretter (min.)';
$zz['fields'][13]['field_name'] = 'bretter_min';
$zz['fields'][13]['type'] = 'number';
$zz['fields'][13]['append_next'] = true;
$zz['fields'][13]['list_append_next'] = true;
$zz['fields'][13]['hide_in_list'] = true;
$zz['fields'][13]['if'][2] = false;
$zz['fields'][13]['explanation'] = 'Spieler / Spieler + Ersatzspieler';

$zz['fields'][16]['title'] = 'Bretter (max.)';
$zz['fields'][16]['field_name'] = 'bretter_max';
$zz['fields'][16]['type'] = 'number';
$zz['fields'][16]['prefix'] = ' – ';
$zz['fields'][16]['list_prefix'] = ' – ';
$zz['fields'][16]['hide_in_list'] = true;
$zz['fields'][16]['if'][2] = false;

$zz['fields'][14]['title'] = 'Pseudo-DWZ';
$zz['fields'][14]['field_name'] = 'pseudo_dwz';
$zz['fields'][14]['type'] = 'number';
$zz['fields'][14]['explanation'] = 'Spieler, die keine DWZ haben, werden mit der 
Pseudozahl geführt, um einen realistischeren Mannschafts-DWZ-Schnitt zu berechnen.';
$zz['fields'][14]['hide_in_list'] = true;
$zz['fields'][14]['if'][2] = false;

$zz['fields'][52]['title'] = 'Teams (max.)';
$zz['fields'][52]['field_name'] = 'teams_max';
$zz['fields'][52]['type'] = 'number';
$zz['fields'][52]['hide_in_list'] = true;
$zz['fields'][52]['if'][2] = false;

$zz['fields'][15]['title_tab'] = 'Gast?';
$zz['fields'][15]['field_name'] = 'gastspieler';
$zz['fields'][15]['type'] = 'select';
$zz['fields'][15]['enum'] = ['ja', 'nein'];
$zz['fields'][15]['default'] = 'nein';
$zz['fields'][15]['explanation'] = 'Sind Gastspieler von anderen Vereinen erlaubt?';
$zz['fields'][15]['hide_in_list'] = true;
$zz['fields'][15]['if'][2] = false;

$zz['fields'][50]['title'] = 'Wertung bei spielfrei';
$zz['fields'][50]['field_name'] = 'wertung_spielfrei';
$zz['fields'][50]['type'] = 'select';
$zz['fields'][50]['enum'] = ['Sieg', 'keine']; // @todo: 'Unentschieden' umsetzen
$zz['fields'][50]['default'] = 'Sieg';
$zz['fields'][50]['explanation'] = 'Wertung von spielfreien Partien, keine = wird nicht importiert';
$zz['fields'][50]['hide_in_list'] = true;
$zz['fields'][50]['if'][2] = false;

$zz['fields'][29]['field_name'] = 'zimmerbuchung';
$zz['fields'][29]['type'] = 'select';
$zz['fields'][29]['enum'] = ['ja', 'nein'];
$zz['fields'][29]['default'] = 'ja';
$zz['fields'][29]['hide_in_list'] = true;
$zz['fields'][29]['explanation'] = 'Sollen für die Teams Zimmerbuchungen abgegeben werden?';
$zz['fields'][29]['if'][2] = false;

$zz['fields'][19]['title_tab'] = 'TN?';
$zz['fields'][19]['field_name'] = 'teilnehmerliste';
$zz['fields'][19]['type'] = 'select';
$zz['fields'][19]['enum'] = ['ja', 'nein'];
$zz['fields'][19]['default'] = 'nein';
$zz['fields'][19]['hide_in_list'] = true;
$zz['fields'][19]['explanation'] = 'Soll eine Teilnehmerliste angezeigt werden?';
$zz['fields'][19]['if'][2] = false;

$zz['fields'][44]['field_name'] = 'spielerphotos';
$zz['fields'][44]['type'] = 'select';
$zz['fields'][44]['enum'] = ['ja', 'nein'];
$zz['fields'][44]['hide_in_list'] = true;
$zz['fields'][44]['default'] = 'nein';

$zz['fields'][45]['field_name'] = 'teamphotos';
$zz['fields'][45]['type'] = 'select';
$zz['fields'][45]['enum'] = ['ja', 'nein'];
$zz['fields'][45]['hide_in_list'] = true;
$zz['fields'][45]['default'] = 'nein';
$zz['fields'][45]['if'][2] = false;

$zz['fields'][46]['field_name'] = 'spielernachrichten';
$zz['fields'][46]['type'] = 'select';
$zz['fields'][46]['enum'] = ['ja', 'nein'];
$zz['fields'][46]['default'] = 'nein';
$zz['fields'][46]['hide_in_list'] = true;
$zz['fields'][46]['separator'] = true;

$zz['fields'][36]['title'] = 'Parameter';
$zz['fields'][36]['field_name'] = 'urkunde_parameter';
$zz['fields'][36]['type'] = 'parameter';
$zz['fields'][36]['rows'] = 3;
$zz['fields'][36]['hide_in_list'] = true;
$zz['fields'][36]['explanation'] = 'Eingabe im query string-Format: parameter=wert&amp;parameter2=wert (<a href="/hilfe/anleitung/#parameter">Anleitung</a>)';
$zz['fields'][36]['separator'] = true;

$zz['fields'][40]['field_name'] = 'teams';
$zz['fields'][40]['type'] = 'display';
$zz['fields'][40]['type_detail'] = 'number';
$zz['fields'][40]['hide_in_form'] = true;
$zz['fields'][40]['hide_zeros'] = true;
$zz['fields'][40]['exclude_from_search'] = true;

$zz['fields'][41]['field_name'] = 'spieler';
$zz['fields'][41]['type'] = 'display';
$zz['fields'][41]['type_detail'] = 'number';
$zz['fields'][41]['hide_in_form'] = true;
$zz['fields'][41]['hide_zeros'] = true;
$zz['fields'][41]['exclude_from_search'] = true;

$zz['fields'][43] = zzform_include('turniere-status');
$zz['fields'][43]['title'] = 'Status';
$zz['fields'][43]['type'] = 'subtable';
$zz['fields'][43]['min_records'] = 1;
$zz['fields'][43]['max_records'] = 10;
$zz['fields'][43]['form_display'] = 'set';
$zz['fields'][43]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][43]['sql'] .= $zz['fields'][43]['sqlorder'];
$zz['fields'][43]['subselect']['sql'] = 'SELECT tournament_id, category, category_short
	FROM /*_PREFIX_*/turniere_status
	LEFT JOIN /*_PREFIX_*/categories
		ON /*_PREFIX_*/categories.category_id = /*_PREFIX_*/turniere_status.status_category_id';
$zz['fields'][43]['subselect']['field_prefix'][0] = '<abbr title="';
$zz['fields'][43]['subselect']['field_suffix'][0] = '">';
$zz['fields'][43]['subselect']['field_suffix'][1] = '</abbr>';
$zz['fields'][43]['subselect']['concat_rows'] = ' ';
$zz['fields'][43]['if']['add']['hide_in_form'] = true;

$zz['fields'][47]['field_name'] = 'fehler';
$zz['fields'][47]['type'] = 'memo';
$zz['fields'][47]['rows'] = 4;
$zz['fields'][47]['format'] = 'markdown';
$zz['fields'][47]['hide_in_list'] = true;
$zz['fields'][47]['if']['add']['hide_in_form'] = true;

$zz['fields'][48]['title_tab'] = 'K?';
$zz['fields'][48]['field_name'] = 'komplett';
$zz['fields'][48]['type'] = 'select';
$zz['fields'][48]['dont_copy'] = true;
$zz['fields'][48]['enum'] = ['ja', 'nein'];
$zz['fields'][48]['default'] = 'nein';
$zz['fields'][48]['explanation'] = 'Turnier abgeschlossen, gesperrt für Änderungen';
$zz['fields'][48]['if']['add']['hide_in_form'] = true;

$zz['fields'][51]['field_name'] = 'tabellenstand_runde_no';
$zz['fields'][51]['hide_in_list'] = true;
$zz['fields'][51]['hide_in_form'] = true;
$zz['fields'][51]['type'] = 'number';

$zz['fields'][54]['field_name'] = 'main_tournament_id';
$zz['fields'][54]['type'] = 'select';
$zz['fields'][54]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS tournament, identifier
	FROM /*_PREFIX_*/tournaments
	LEFT JOIN /*_PREFIX_*/events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][54]['exclude_from_search'] = true;
$zz['fields'][54]['hide_in_list'] = true;


$zz['sql'] = 'SELECT /*_PREFIX_*/tournaments.*
		, CONCAT(/*_PREFIX_*/events.event, " ", IFNULL(event_year, YEAR(date_begin))) AS turnier
		, /*_PREFIX_*/events.identifier AS event_identifier
		, modus.category_short AS modus
		, turnierformen.category_short AS turnierform
		, (SELECT COUNT(*) FROM /*_PREFIX_*/teams
			WHERE teams.event_id = tournaments.event_id
			AND team_status = "Teilnehmer"
			AND spielfrei = "nein"
		) AS teams
		, (SELECT COUNT(*) FROM /*_PREFIX_*/participations
			WHERE participations.event_id = tournaments.event_id
			AND status_category_id = /*_ID categories participation-status/participant _*/
			AND usergroup_id = /*_ID usergroups spieler _*/
		) AS spieler
	FROM /*_PREFIX_*/tournaments
	LEFT JOIN /*_PREFIX_*/events USING (event_id)
	LEFT JOIN /*_PREFIX_*/categories series
		ON /*_PREFIX_*/events.series_category_id = series.category_id
	LEFT JOIN /*_PREFIX_*/categories modus
		ON /*_PREFIX_*/tournaments.modus_category_id = modus.category_id
	LEFT JOIN /*_PREFIX_*/categories turnierformen
		ON /*_PREFIX_*/tournaments.turnierform_category_id = turnierformen.category_id
	LEFT JOIN /*_PREFIX_*/events_categories
		ON /*_PREFIX_*/events_categories.event_id = /*_PREFIX_*/tournaments.event_id
		AND /*_PREFIX_*/events_categories.type_category_id = /*_ID categories events _*/
';
$zz['sqlorder'] = ' ORDER BY /*_PREFIX_*/events.date_begin DESC, /*_PREFIX_*/events.time_begin DESC,
	/*_PREFIX_*/events.identifier';

$zz['subtitle']['event_id']['sql'] = 'SELECT event
	, CONCAT(/*_PREFIX_*/events.date_begin, IFNULL(CONCAT("/", /*_PREFIX_*/events.date_end), "")) AS duration
	FROM /*_PREFIX_*/events';
$zz['subtitle']['event_id']['var'] = ['event', 'duration'];
$zz['subtitle']['event_id']['format'][1] = 'wrap_date';
$zz['subtitle']['event_id']['link'] = '../';
$zz['subtitle']['event_id']['link_no_append'] = true;

$zz['filter'][2]['sql'] = 'SELECT DISTINCT main_series.category_id
		, main_series.category_short, date_begin
	FROM /*_PREFIX_*/tournaments
	LEFT JOIN /*_PREFIX_*/events USING (event_id)
	LEFT JOIN /*_PREFIX_*/categories series
		ON /*_PREFIX_*/events.series_category_id = series.category_id
	LEFT JOIN /*_PREFIX_*/categories main_series
		ON series.main_category_id = main_series.category_id
	WHERE NOT ISNULL(main_series.category_id)
	ORDER BY date_begin DESC';
$zz['filter'][2]['title'] = 'Reihe';
$zz['filter'][2]['identifier'] = 'reihe';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['where'] = 'series.main_category_id';

$zz['filter'][1]['sql'] = 'SELECT DISTINCT IFNULL(event_year, YEAR(date_begin)) AS year_idf
		, IFNULL(event_year, YEAR(date_begin)) AS year
	FROM /*_PREFIX_*/tournaments
	LEFT JOIN /*_PREFIX_*/events USING (event_id)
	LEFT JOIN /*_PREFIX_*/categories series
		ON /*_PREFIX_*/events.series_category_id = series.category_id
	LEFT JOIN /*_PREFIX_*/categories main_series
		ON series.main_category_id = main_series.category_id
	ORDER BY IFNULL(event_year, YEAR(date_begin)) DESC';
$zz['filter'][1]['title'] = 'Jahr';
$zz['filter'][1]['identifier'] = 'year';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'IFNULL(event_year, YEAR(date_begin))';
$zz['filter'][1]['depends_on'] = 2;

$zz['conditions'][1]['scope'] = 'record';
$zz['conditions'][1]['where'] = '/*_PREFIX_*/events_categories.category_id = /*_ID categories events/team _*/';

$zz['conditions'][2]['scope'] = 'record';
$zz['conditions'][2]['where'] = '/*_PREFIX_*/events_categories.category_id = /*_ID categories events/single _*/';

$zz['record']['copy'] = true;

$zz['hooks']['after_update'][] = 'mf_tournaments_standings_update';
$zz['hooks']['after_upload'][] = 'mf_tournaments_games_update';
