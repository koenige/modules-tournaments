<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2019 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turniere


$zz['title'] = 'Turniere';
$zz['table'] = 'turniere';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'turnier_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'termin_id';
$zz['fields'][2]['type'] = 'write_once';
$zz['fields'][2]['type_detail'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT termin_id, beginn, termin
	FROM termine
	WHERE ISNULL(haupt_termin_id)
	ORDER BY termin';
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(termine.termin, " ", YEAR(beginn))';
$zz['fields'][2]['unique'] = true;
$zz['fields'][2]['if']['where']['hide_in_form'] = true;
$zz['fields'][2]['link'] = [
	'string1' => '/intern/termine/',
	'field1' => 'kennung',
	'string2' => '/'
];
$zz['fields'][2]['dont_show_where_class'] = true;

$zz['fields'][3]['title'] = 'Turnierform';
$zz['fields'][3]['title_tab'] = 'Form';
$zz['fields'][3]['field_name'] = 'turnierform_category_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY category';
$zz['fields'][3]['if'][1]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	WHERE parameters LIKE "%team=1%"
	ORDER BY category';
$zz['fields'][3]['if'][2]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	WHERE parameters LIKE "%team=0%"
	ORDER BY category';
$zz['fields'][3]['show_hierarchy'] = 'main_category_id';
$zz['fields'][3]['show_hierarchy_subtree'] = $zz_setting['category_ids']['turnierformen'][0];
$zz['fields'][3]['display_field'] = 'turnierform';
$zz['fields'][3]['search'] = 'turnierformen.category_short';

$zz['fields'][20]['title_tab'] = 'Rd.';
$zz['fields'][20]['title'] = 'Runden';
$zz['fields'][20]['field_name'] = 'runden';
$zz['fields'][20]['type'] = 'number';

$zz['fields'][4]['title'] = 'Modus';
$zz['fields'][4]['field_name'] = 'modus_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY category';
$zz['fields'][4]['show_hierarchy'] = 'main_category_id';
$zz['fields'][4]['show_hierarchy_subtree'] = $zz_setting['category_ids']['turniermodi'][0];
$zz['fields'][4]['display_field'] = 'modus';
$zz['fields'][4]['search'] = 'modus.category_short';

$zz['fields'][49] = zzform_include_table('turniere-bedenkzeiten');
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

$zz['fields'][21] = zzform_include_table('turniere-wertungen');
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

$zz['fields'][42] = zzform_include_table('turniere-partien');
$zz['fields'][42]['title'] = 'Livepartien';
$zz['fields'][42]['type'] = 'subtable';
$zz['fields'][42]['form_display'] = 'lines';
$zz['fields'][42]['hide_in_list'] = true;
$zz['fields'][42]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][42]['sql'] .= $zz['fields'][42]['sqlorder'];
$zz['fields'][42]['explanation_top'] = $zz['fields'][42]['fields'][3]['explanation'];
unset($zz['fields'][42]['fields'][3]['explanation']);

$zz['fields'][26]['field_name'] = 'turnierkennung';
$zz['fields'][26]['explanation'] = 'Eigene Turnierkennung, wird z. B. für SWT-Dateiexport genutzt';
$zz['fields'][26]['hide_in_list'] = true;

$zz['fields'][28] = zzform_include_table('turniere-kennungen');
$zz['fields'][28]['title'] = 'Kennungen';
$zz['fields'][28]['class'] = 'kennungen';
$zz['fields'][28]['type'] = 'subtable';
$zz['fields'][28]['min_records'] = 1;
$zz['fields'][28]['max_records'] = 10;
$zz['fields'][28]['form_display'] = 'lines';
$zz['fields'][28]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][28]['sql'] .= $zz['fields'][28]['sqlorder'];
$zz['fields'][28]['subselect']['sql'] = 'SELECT turnier_id, category_short
		, turniere_kennungen.kennung
	FROM turniere_kennungen
	LEFT JOIN categories
		ON categories.category_id = turniere_kennungen.kennung_category_id';

$zz['fields'][27]['title'] = 'Tabellenstände';
$zz['fields'][27]['hide_in_list'] = true;
$zz['fields'][27]['field_name'] = 'tabellenstaende';
$zz['fields'][27]['explanation'] = 'Zusätzliche Tabellenstände als Filter, Eingabe als Liste mit Kommas
 (<a href="/intern/anleitung/#tabellenstaende">Anleitung</a>)';
$zz['fields'][27]['separator'] = true;

$zz['fields'][25]['title'] = 'PGN-Datei';
$zz['fields'][25]['field_name'] = 'pgnfile';
$zz['fields'][25]['dont_show_missing'] = true;
$zz['fields'][25]['type'] = 'upload_image';
$zz['fields'][25]['path'] = array (
	'root' => $zz_setting['media_folder'].'/pgn/',
	'webroot' => '/intern/dateien/pgn/',
	'field1' => 'termin_kennung',
	'string2' => '/gesamt',
	'string3' => '.pgn'
);
$zz['fields'][25]['input_filetypes'] = ['pgn'];
$zz['fields'][25]['link'] = [
	'string1' => '/intern/dateien/pgn/',
	'field1' => 'termin_kennung',
	'string2' => '/gesamt',
	'string3' => '.pgn'
];
$zz['fields'][25]['optional_image'] = true;
$zz['fields'][25]['image'][0]['title'] = 'pgn';
$zz['fields'][25]['image'][0]['field_name'] = 'pgn';
$zz['fields'][25]['image'][0]['path'] = $zz['fields'][25]['path'];
$zz['fields'][25]['list_append_next'] = true;
$zz['fields'][25]['list_suffix'] = '<br>';
$zz['fields'][25]['title_tab'] = 'Dateien';

$zz['fields'][22]['title'] = 'SWT-Datei';
$zz['fields'][22]['field_name'] = 'swt';
$zz['fields'][22]['dont_show_missing'] = true;
$zz['fields'][22]['type'] = 'upload_image';
$zz['fields'][22]['path'] = [
	'root' => $zz_setting['media_folder'].'/swt/',
	'webroot' => '/intern/dateien/swt/',
	'field1' => 'termin_kennung', 
	'string2' => '.swt'
];
$zz['fields'][22]['input_filetypes'] = ['swt'];
$zz['fields'][22]['link'] = [
	'string1' => '/intern/dateien/swt/',
	'field1' => 'termin_kennung',
	'string2' => '.swt'
];
$zz['fields'][22]['optional_image'] = true;
$zz['fields'][22]['image'][0]['title'] = 'gro&szlig;';
$zz['fields'][22]['image'][0]['field_name'] = 'gross';
$zz['fields'][22]['image'][0]['path'] = $zz['fields'][22]['path'];
$zz['fields'][22]['if'][1]['separator'] = 'text <div>Für Mannschaftsturniere</div>';
$zz['fields'][22]['if'][2]['separator'] = true;

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

$zz['fields'][17]['title'] = 'Hinweis Aufstellung';
$zz['fields'][17]['field_name'] = 'hinweis_aufstellung';
$zz['fields'][17]['type'] = 'memo';
$zz['fields'][17]['explanation'] = 'Hinweise, was bei der Aufstellung beachtet werden muß.';
$zz['fields'][17]['hide_in_list'] = true;
$zz['fields'][17]['format'] = 'markdown';
$zz['fields'][17]['if'][2] = false;

$zz['fields'][18]['title'] = 'Hinweis Meldebogen';
$zz['fields'][18]['field_name'] = 'hinweis_meldebogen';
$zz['fields'][18]['type'] = 'memo';
$zz['fields'][18]['explanation'] = 'Hinweis für die Meldung, der unten auf dem Meldebogen steht.';
$zz['fields'][18]['hide_in_list'] = true;
$zz['fields'][18]['format'] = 'markdown';
$zz['fields'][18]['if'][2] = false;

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

$zz['fields'][31]['title'] = 'Urkunde';
$zz['fields'][31]['field_name'] = 'urkunde_id';
$zz['fields'][31]['type'] = 'select';
$zz['fields'][31]['sql'] = 'SELECT urkunde_id, urkunde_titel FROM urkunden ORDER BY urkunde_titel';
$zz['fields'][31]['display_field'] = 'urkunde_titel';
$zz['fields'][31]['hide_in_list'] = true;
$zz['fields'][31]['suffix'] = ' – <a href="/intern/urkunden/" target="_new">Galerie aller Urkunden</a>';

$zz['fields'][32]['title'] = 'Urkunde: Ort';
$zz['fields'][32]['field_name'] = 'urkunde_ort';
$zz['fields'][32]['hide_in_list'] = true;

$zz['fields'][33]['title'] = 'Urkunde: Datum';
$zz['fields'][33]['field_name'] = 'urkunde_datum';
$zz['fields'][33]['dont_copy'] = true;
$zz['fields'][33]['hide_in_list'] = true;
$zz['fields'][33]['type'] = 'date';

$zz['fields'][34]['title'] = 'Unterschrift links';
$zz['fields'][34]['field_name'] = 'urkunde_unterschrift1';
$zz['fields'][34]['hide_in_list'] = true;

$zz['fields'][35]['title'] = 'Unterschrift rechts';
$zz['fields'][35]['field_name'] = 'urkunde_unterschrift2';
$zz['fields'][35]['hide_in_list'] = true;

$zz['fields'][36]['title'] = 'Parameter';
$zz['fields'][36]['field_name'] = 'urkunde_parameter';
$zz['fields'][36]['type'] = 'parameter';
$zz['fields'][36]['rows'] = 3;
$zz['fields'][36]['hide_in_list'] = true;
$zz['fields'][36]['explanation'] = 'Eingabe im query string-Format: parameter=wert&amp;parameter2=wert (<a href="/intern/anleitung/#parameter">Anleitung</a>)';
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

$zz['fields'][43] = zzform_include_table('turniere-status');
$zz['fields'][43]['title'] = 'Status';
$zz['fields'][43]['type'] = 'subtable';
$zz['fields'][43]['min_records'] = 1;
$zz['fields'][43]['max_records'] = 10;
$zz['fields'][43]['form_display'] = 'set';
$zz['fields'][43]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][43]['sql'] .= $zz['fields'][43]['sqlorder'];
$zz['fields'][43]['subselect']['sql'] = 'SELECT turnier_id, category, category_short
	FROM turniere_status
	LEFT JOIN categories
		ON categories.category_id = turniere_status.status_category_id';
$zz['fields'][43]['subselect']['field_prefix'][0] = '<abbr title="';
$zz['fields'][43]['subselect']['field_suffix'][0] = '">';
$zz['fields'][43]['subselect']['field_suffix'][1] = '</abbr>';
$zz['fields'][43]['subselect']['concat_rows'] = ' ';

$zz['fields'][47]['field_name'] = 'fehler';
$zz['fields'][47]['type'] = 'memo';
$zz['fields'][47]['rows'] = 4;
$zz['fields'][47]['format'] = 'markdown';
$zz['fields'][47]['hide_in_list'] = true;

$zz['fields'][48]['title_tab'] = 'K?';
$zz['fields'][48]['field_name'] = 'komplett';
$zz['fields'][48]['type'] = 'select';
$zz['fields'][48]['dont_copy'] = true;
$zz['fields'][48]['enum'] = ['ja', 'nein'];
$zz['fields'][48]['default'] = 'nein';
$zz['fields'][48]['explanation'] = 'Turnier abgeschlossen, gesperrt für Änderungen';

$zz['fields'][51]['field_name'] = 'tabellenstand_runde_no';
$zz['fields'][51]['hide_in_list'] = true;
$zz['fields'][51]['hide_in_form'] = true;
$zz['fields'][51]['type'] = 'number';

$zz['sql'] = 'SELECT turniere.*
		, CONCAT(termine.termin, " ", YEAR(beginn)) AS turnier
		, termine.kennung AS termin_kennung
		, modus.category_short AS modus
		, turnierformen.category_short AS turnierform
		, urkunden.urkunde_titel
		, termine.kennung
		, (SELECT COUNT(team_id) FROM teams
			WHERE teams.termin_id = turniere.termin_id
			AND team_status = "Teilnehmer"
			AND spielfrei = "nein"
		) AS teams
		, (SELECT COUNT(teilnahme_id) FROM teilnahmen
			WHERE teilnahmen.termin_id = turniere.termin_id
			AND teilnahme_status = "Teilnehmer"
			AND gruppe_id = %d
		) AS spieler
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories reihen
		ON termine.reihe_category_id = reihen.category_id
	LEFT JOIN categories modus
		ON turniere.modus_category_id = modus.category_id
	LEFT JOIN categories turnierformen
		ON turniere.turnierform_category_id = turnierformen.category_id
	LEFT JOIN urkunden USING (urkunde_id)
';
$zz['sql'] = sprintf($zz['sql'], $zz_setting['gruppen_ids']['spieler']);
$zz['sqlorder'] = ' ORDER BY termine.beginn DESC, termine.uhrzeit_beginn DESC,
	termine.kennung';

$zz['subtitle']['termin_id']['sql'] = 'SELECT termin
	, CONCAT(termine.beginn, IFNULL(CONCAT("/", termine.ende), "")) AS dauer
	FROM termine';
$zz['subtitle']['termin_id']['var'] = ['termin', 'dauer'];
$zz['subtitle']['termin_id']['format'][1] = 'wrap_date';
$zz['subtitle']['termin_id']['link'] = '../';
$zz['subtitle']['termin_id']['link_no_append'] = true;

$zz['filter'][2]['sql'] = 'SELECT DISTINCT hauptreihen.category_id
		, hauptreihen.category_short, beginn
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories reihen
		ON termine.reihe_category_id = reihen.category_id
	LEFT JOIN categories hauptreihen
		ON reihen.main_category_id = hauptreihen.category_id
	WHERE !ISNULL(hauptreihen.category_id)
	ORDER BY beginn DESC';
$zz['filter'][2]['title'] = 'Reihe';
$zz['filter'][2]['identifier'] = 'reihe';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['where'] = 'reihen.main_category_id';

$zz['filter'][1]['sql'] = 'SELECT DISTINCT YEAR(beginn) AS jahr_idf
		, YEAR(beginn) AS jahr
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	LEFT JOIN categories reihen
		ON termine.reihe_category_id = reihen.category_id
	LEFT JOIN categories hauptreihen
		ON reihen.main_category_id = hauptreihen.category_id
	ORDER BY YEAR(beginn) DESC';
$zz['filter'][1]['title'] = 'Jahr';
$zz['filter'][1]['identifier'] = 'jahr';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'YEAR(beginn)';
$zz['filter'][1]['depends_on'] = 2;

$zz['conditions'][1]['scope'] = 'record';
$zz['conditions'][1]['where'] = sprintf(
	'termine.termin_category_id = %d', $zz_setting['category_ids']['termine']['mannschaft']
);

$zz['conditions'][2]['scope'] = 'record';
$zz['conditions'][2]['where'] = sprintf(
	'termine.termin_category_id = %d', $zz_setting['category_ids']['termine']['einzel.2']
);

$zz_conf['copy'] = true;

$zz['hooks']['after_update'][] = 'my_tabellenstand_aktualisieren';
$zz['hooks']['after_upload'][] = 'my_partienupdate_nach_upload';
