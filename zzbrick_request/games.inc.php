<?php 

/**
 * tournaments module
 * PGN download and re-play games online
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005, 2012-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ausgabe Partien bzw. PGN-Dateien
 *
 * @param array $vars
 *		int [0]: Jahr
 *		string [1]: Turnierkennung
 *		string [2]: 1.pgn, gesamt.pgn, 1-2-4
 */
function mod_tournaments_games($vars, $settings = [], $event = []) {
	if (count($vars) !== 3 AND count($vars) !== 4) return false;
	// direct access via filemove
	if (empty($event)) $event = my_event($vars[0], $vars[1]);
	
	$settings['request'] = array_pop($vars);
	$settings = mod_tournaments_games_settings($settings);
	
	if (!$settings['type'] AND $event['sub_series'])
		return mod_tournaments_games_series($event, $settings);
	if ($settings['type'] AND $settings['content_type'] === 'pgn')
		return mod_tournaments_games_special($event, $settings);
	
	$sql = 'SELECT series.category_short AS series_short
			, runden, tournament_id, livebretter
			, IF(bretter_min, bretter_min, (SELECT COUNT(*)/2 FROM participations
				WHERE event_id = events.event_id
				AND usergroup_id = %d)) AS bretter_max
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE events.event_id = %d
	';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		$event['website_id'],
		$event['event_id']
	);
	$event = array_merge($event, wrap_db_fetch($sql));
	$settings['send_as'] = $event['year'].' '.($event['series_short'] ?? $event['event']);

	switch ($settings['content_type']) {
	case 'pgn':
		if ($settings['request'] === 'gesamt')
			return mod_tournaments_games_file_complete($event, $settings);
		if ($settings['request'] === 'current')
			return mod_tournaments_games_file_current($event, $settings);
		if ($settings['request'] === 'live')
			return mod_tournaments_games_file_live($event, $settings);
		if ($settings['request'] === 'live-raw')
			return mod_tournaments_games_file_liveraw($event, $settings);
		if (preg_match('/^(\d+)-live$/', $settings['request'], $matches))
			return mod_tournaments_games_file_live_round($event, $matches[1], $settings);
		if (preg_match('/^(\d+)-live-raw$/', $settings['request'], $matches))
			return mod_tournaments_games_file_liveraw_round($event, $matches[1], $settings);
		if (preg_match('/^(\d+)$/', $settings['request'], $matches))
			return mod_tournaments_games_file_round($event, $matches[1], $settings);
		if (preg_match('/^(\d+)-(\d+)$/', $settings['request'], $matches))
			return mod_tournaments_games_file_game_single($event, $matches[1], $matches[2], $settings);
		if (preg_match('/^(\d+)-(\d+)\-(\d+)$/', $settings['request'], $matches))
			return mod_tournaments_games_file_game_team($event, $matches[1], $matches[2], $matches[3], $settings);
		return false;
	case 'json':
		wrap_include_files('pgn', 'chess');
		return mod_tournaments_games_json($event, $settings);
	default:
		wrap_include_files('pgn', 'chess');
		if ($settings['type'])
			return mod_tournaments_games_special_html($event, $settings);
		return mod_tournaments_games_html($event, $settings);
	}
}

/**
 * check request, set content_type, character_set, pgn_path, query_strings
 *
 * @param array $settings
 * @return array $settings
 */
function mod_tournaments_games_settings($settings) {
	if (empty($settings['type'])) $settings['type'] = false;

	if (str_ends_with($settings['request'], '.pgn')) {
		$settings['content_type'] = 'pgn';
		$settings['request'] = substr($settings['request'], 0, -4);
	} elseif (str_ends_with($settings['request'], '.json')) {
		$settings['content_type'] = 'json';
		$settings['request'] = substr($settings['request'], 0, -5);
	} else {
		$settings['content_type'] = 'html';
	}
	if (str_ends_with($settings['request'], 'utf8')) {
		$settings['character_set'] = 'utf-8';
		$settings['request'] = substr($settings['request'], 0, -5);
	} else {
		$settings['character_set'] = 'iso-8859-1';
	}
	$settings['pgn_path'] = wrap_setting('media_folder').'/pgn/%s/%s.pgn';
	// ignore query strings behind .pgn
	$settings['qs'] = mod_tournaments_games_check_qs();
	return $settings;
}

/**
 * create a PGN file from data
 *
 * @param array $data
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_pgnfile($data, $settings) {
	wrap_package_activate('chess'); // pgn_wordwrap

	$page['text'] = '';
	// it is faster to use template per game than to format all games at once
	foreach ($data as $line)
		$page['text'] .= wrap_template('pgn', [$line]);

	return mod_tournaments_games_pgn_send($page, $settings);
}

/**
 * send PGN data to browser, convert according to chosen character set
 *
 * @param array $page
 * @param array $settings
 */
function mod_tournaments_games_pgn_send($page, $settings) {
	$page['text'] = str_replace("\n", "\r\n", $page['text']);
	$page['headers']['filename'] = sprintf('%s.pgn', $settings['send_as']);
	if ($settings['character_set'] !== 'iso-8859-1') {
		$page['text'] = mb_convert_encoding($page['text'], $settings['character_set'], 'ISO-8859-1');
		$page['headers']['filename'] = mb_convert_encoding($page['headers']['filename'], $settings['character_set'], 'ISO-8859-1');
	}
	wrap_setting('character_set', $settings['character_set']);
	$page['query_strings'] = $settings['qs'] ?? [];
	$page['content_type'] = 'pgn';
	return $page;
}

//
// --- OUTPUT ---
//

/**
 * return all games for a tournament series
 *
 * @param array $event
 * @param array $settings
 * @return array
 */
function mod_tournaments_games_series($event, $settings) {
	if ($settings['request'] !== 'gesamt') return false;
	if ($settings['content_type'] !== 'pgn') return false;

	$identifier = explode('/', $event['identifier']);
	$sql = 'SELECT events.event_id
		FROM events
		LEFT JOIN addresses
			ON addresses.contact_id = events.place_contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE IFNULL(event_year, YEAR(events.date_begin)) = %d
		AND main_series.path = "reihen/%s"
		AND (tournaments.notationspflicht = "ja" OR addresses.country_id = %d)
	';
	$sql = sprintf($sql
		, $event['website_id']
		, $identifier[0]
		, wrap_db_escape($identifier[1])
		, wrap_id('countries', '--') // internet
	);
	$events = wrap_db_fetch($sql, 'event_id');
	if (!$events) return false;

	$games = [];
	foreach ($events as $sub_event)
		$games = array_merge($games, mod_tournaments_games_pgn($sub_event['event_id']));
	if (!$games) return false;

	$settings['send_as'] = $event['year'].' '.$event['series_short'];
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * export separate file, e.g. pdt
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_special($event, $settings) {
	$file['name'] = sprintf($settings['pgn_path']
		, $event['identifier'], $settings['type'].'-'.$settings['request']
	);
	if (!file_exists($file['name'])) return false;

	$page['text'] = file_get_contents($file['name']);
	$settings['send_as'] = $event['year'].' '.$settings['type'].' Runde '.$settings['request'];
	
	return mod_tournaments_games_pgn_send($page, $settings);
}

/**
 * export PGN for a complete tournament
 * gesamt = alle Dateien eines Turniers oder einer Turnierreihe
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_file_complete($event, $settings) {
	$games = mod_tournaments_games_pgn($event['event_id']);
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * export PGN for current round of tournament
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_file_current($event, $settings) {
	$sql = 'SELECT MAX(runde_no) FROM partien WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$round_no = wrap_db_fetch($sql, '', 'single value');
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no);
	$settings['send_as'] .= ' Runde '.$round_no;
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * export PGN for live games of tournament
 * live = aktuelle Runde, nur Livebretter, Kopf aus Datenbank
 * (vor Runde: nur Partienköpfe)
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_file_live($event, $settings) {
	$sql = 'SELECT MAX(runde_no) FROM partien WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$round_no = wrap_db_fetch($sql, '', 'single value');
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no);
	$games = mod_tournaments_games_liveonly($games, $event);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	wrap_setting('cache_age', 1);
	wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_setting('live_cache_control_age')));
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * export raw PGN from board for live games of tournament
 *
 * output of raw live games, current round, directly how they come from the boards
 * check for local file and source from external server
 * live-raw = aktuelle Runde, nur Livebretter, 1:1 von Brettern
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_games_file_liveraw($event, $settings) {
	wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_setting('live_cache_control_age')));
	for ($i = 1; $i <= $event['runden']; $i++) {
		$pgn = sprintf($settings['pgn_path'], $event['identifier'], $i.'-live');
		if (!file_exists($pgn)) continue;
		$settings['send_as'] .= ' Runde '.$I.' (Live)';
		$page['text'] = file_get_contents($pgn);
		return mod_tournaments_games_pgn_send($page, $settings);
	}

	// maybe there's some path on the server / on an external server?
	$page['text'] = mf_tournaments_pgn_file_from_tournament($event['tournament_id']);
	if (!$page['text']) return false;
	$settings['send_as'] .= ' (Live)';
	return mod_tournaments_games_pgn_send($page, $settings);
}

/**
 * export PGN for live games of tournament, specific round
 * 1-live = 1. Runde, nur Livebretter, Kopf aus Datenbank
 *
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_games_file_live_round($event, $round_no, $settings) {
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no);
	$games = mod_tournaments_games_liveonly($games, $event);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	wrap_setting('cache', false);
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * output of raw live games per round, directly how they come from the boards
 *
 * 1-live-raw = 1. Runde, nur Livebretter, 1:1 von Brettern
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_games_file_liveraw_round($event, $round_no, $settings) {
	$pgn = sprintf($settings['pgn_path'], $event['identifier'], $round_no.'-live');
	if (!file_exists($pgn)) return false;
	$page['text'] = file_get_contents($pgn);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	return mod_tournaments_games_pgn_send($page, $settings);
}

/**
 * output of all games in one round of a tournament
 *
 * 1 = Runde 1
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_games_file_round($event, $round_no, $settings) {
	// "2012 DVM U20w Runde 1.pgn"
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no;
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * output game of a board in a tournament (single)
 * 
 * 1-1 = Runde 1, Brett 1 (Einzelturnier)
 * @param array $event
 * @param int $round_no
 * @param int $board_no
 * @param array $settings
 */
function mod_tournaments_games_file_game_single($event, $round_no, $board_no, $settings) {
	// 1-1.pgn = Runde 1 Brett 1, Einzelturnier
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no, $board_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no.' Brett '.$board_no;
	return mod_tournaments_games_pgnfile($games, $settings);
}

/*
 * output game of a board on a table in a tournament (teams)
 *
 * 1-1-1 = Runde 1, Tisch 1, Brett 1 (Mannschaftsturnier)
 * @param array $event
 * @param int $round_no
 * @param int $table_no
 * @param int $board_no
 * @param array $settings
 */
function mod_tournaments_games_file_game_team($event, $round_no, $table_no, $board_no, $settings) {
	// 1-4-1.pgn = Runde 1 Tisch 4 Brett 1, Mannschaftsturnier
	$games = mod_tournaments_games_pgn($event['event_id'], $round_no, $board_no, $table_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no.' Tisch '.$table_no.' Brett '.$board_no;
	return mod_tournaments_games_pgnfile($games, $settings);
}

/**
 * Auslesen einer Live-Partie als JSON
 */
function mod_tournaments_games_json($event, $settings) {
	wrap_setting('cache', false);

	if (!strstr($settings['request'], '-')) return false;
	if (substr_count($settings['request'], '-') === 1) {
		list($round_no, $brett_no) = explode('-', $settings['request']);
		$tisch_no = 0;
	} elseif (substr_count($settings['request'], '-') === 2) {
		list($round_no, $tisch_no, $brett_no) = explode('-', $settings['request']);
	} else {
		return false;
	}
	
	$sql = 'SELECT CONCAT("partie_", partie_id) AS ID
			, pgn AS Moves
			, halbzuege as PlyCount
			, IF(SUBSTRING(pgn, -1) = "*", 1, "") AS Live
			, weiss_zeit AS WhiteClock
			, schwarz_zeit AS BlackClock
			, eco AS ECO
			, DATE_FORMAT(partien.last_update, "%%H:%%i") AS LastUpdate
			, partien.last_update AS Timestamp
		FROM partien
		LEFT JOIN paarungen USING (paarung_id)
		WHERE partien.event_id = %d
		AND partien.runde_no = %d
		AND brett_no = %d
		AND (ISNULL(tisch_no) OR tisch_no = %d)';
	$sql = sprintf($sql, $event['event_id'], $round_no, $brett_no, $tisch_no);
	$partie = wrap_db_fetch($sql);
	if (!$partie) return false;
	if (!$partie['Live']) $partie['Live'] = false;
	$pgn = mod_tournaments_games_pgn($event['event_id'], $round_no, $brett_no);

/* Test */
//	$id = key($pgn);
//	$moves = explode(' ', $pgn[$id]['moves']);
//	$result = array_pop($moves);
//	$array_length = count($moves);
//	$sec = floor((date('i') * 60 + date('s')) / 30);
//	$moves = array_slice($moves, 0, $sec * 3);
//	if (count($moves) < $array_length) {
//		$moves[] = "*";
//		$pgn[$id]['Result'] = '*';
//	} else {
//		$moves[] = $result;
//	}
//	$pgn[$id]['moves'] = implode(' ', $moves);

	wrap_package_activate('chess');
	$partie['PGN'] = wrap_template('pgn', $pgn);
	$pgn = [
		'moves' => $partie['Moves']
	];
	$data = mf_chess_pgn_to_html($pgn);
	$partie['PgnMoveText'] = $data['html'];
	if (wrap_setting('character_set') === 'utf-8') {
		// PGN = Latin1
		$partie['PgnMoveText'] = mb_convert_encoding($partie['PgnMoveText'], 'UTF-8', 'ISO-8859-1');
		if (!empty($partie['PGN']))
			$partie['PGN'] = mb_convert_encoding($partie['PGN'], 'UTF-8', 'ISO-8859-1');
	}

	$page['content_type'] = 'json';
	$page['headers']['filename'] = $event['year'].' '.$event['series_short'].' Runde '.$round_no.' Brett '.$brett_no.'.json';
	$page['headers']['filename'] = '';
	$page['text'] = json_encode($partie);
	return $page;
}

/**
 * Auslesen der Daten für eine PGN aus der Datenbank
 *
 * @param int $event_id
 * @param int $round_no (optional)
 * @param int $brett_no (optional)
 * @param int $tisch_no (optional, nur bei Mannschaftsturnieren)
 * @return array
 */
function mod_tournaments_games_pgn($event_id, $round_no = false, $brett_no = false, $tisch_no = false) {
	$where = [];
	if ($round_no) $where[] = sprintf('partien.runde_no = %d', $round_no);
	if ($brett_no) $where[] = sprintf('partien.brett_no = %d', $brett_no);
	if ($tisch_no) $where[] = sprintf('paarungen.tisch_no = %d', $tisch_no);
	
	wrap_db_query('SET NAMES latin1');
	wrap_setting('character_set', 'iso-8859-1');

	$sql = 'SELECT partien.partie_id
			, events.event, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
			, DATE_FORMAT(events.date_begin, "%%Y.%%m.%%d") AS EventDate
			, DATE_FORMAT(runden.date_begin, "%%Y.%%m.%%d") AS Date
			, IF(ISNULL(url), IF(LOCATE("&virtual=1", place_categories.parameters), (SELECT identification FROM eventdetails WHERE eventdetails.event_id = events.event_id AND active = "yes" LIMIT 1), place), url) AS Site
			, countries.ioc_code AS EventCountry
			, partien.runde_no AS Round
			, partien.brett_no AS Board
			, CONCAT(IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname, ", ", weiss.t_vorname) AS White
			, CONCAT(IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname, ", ", schwarz.t_vorname) AS Black
			, weiss_zeit AS WhiteClock
			, schwarz_zeit AS BlackClock
			, IFNULL(weiss.t_elo, weiss.t_dwz) AS WhiteElo
			, IFNULL(schwarz.t_elo, schwarz.t_dwz) AS BlackElo
			, halbzuege AS PlyCount
			, partien.ECO AS ECO
			, pgn AS moves
			, weiss.t_fidetitel AS WhiteTitle
			, schwarz.t_fidetitel AS BlackTitle
			, IF(ISNULL(weiss_ergebnis) AND ISNULL(schwarz_ergebnis), "*",
				CONCAT(CASE(weiss_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END,
				"-", CASE(schwarz_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END)) AS Result
			, partien.kommentar
			, IF(heim_spieler_farbe = "schwarz"
				, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), ""))
				, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), ""))
			) AS WhiteTeam
			, IF(heim_spieler_farbe = "schwarz"
				, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), ""))
				, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), ""))
			) AS BlackTeam
			, IF(vertauschte_farben = "ja", 1, NULL) AS vertauschte_farben
			, IF(vertauschte_farben = "ja", IF(ISNULL(weiss_ergebnis) AND ISNULL(schwarz_ergebnis), "*",
				CONCAT(CASE(schwarz_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END,
				"-", CASE(weiss_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END)), NULL) AS Result_vertauscht
			, weiss_fide_id.identifier AS WhiteFideId
			, schwarz_fide_id.identifier AS BlackFideId
			, tournaments.runden AS EventRounds
			, CONCAT(
				IF (LOCATE("pgn=", turnierformen.parameters),
					CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(turnierformen.parameters, "pgn=", -1), "&", 1), "-"), ""
				),
				SUBSTRING_INDEX(SUBSTRING_INDEX(modi.parameters, "pgn=", -1), "&", 1)
			) AS EventType
			, IF (LOCATE("pgn=", partiestatus.parameters),
				SUBSTRING_INDEX(SUBSTRING_INDEX(partiestatus.parameters, "pgn=", -1), "&", 1), ""
			) AS Termination
			, paarungen.tisch_no AS `Table`
		FROM partien
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories modi
			ON tournaments.modus_category_id = modi.category_id
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories partiestatus
			ON partien.partiestatus_category_id = partiestatus.category_id
		LEFT JOIN events runden
			ON events.event_id = runden.main_event_id
			AND runden.runde_no = partien.runde_no
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN categories place_categories
			ON places.contact_category_id = place_categories.category_id
		LEFT JOIN addresses
			ON events.place_contact_id = addresses.contact_id
		LEFT JOIN countries
			ON addresses.country_id = countries.country_id
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		LEFT JOIN persons white_persons
			ON white_persons.person_id = partien.weiss_person_id
		LEFT JOIN persons black_persons
			ON black_persons.person_id = partien.schwarz_person_id
		JOIN participations weiss
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = %d
			AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
			AND weiss.event_id = partien.event_id
		JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = %d
			AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", heim_teams.team_id, auswaerts_teams.team_id))
			AND schwarz.event_id = partien.event_id
		LEFT JOIN contacts_identifiers weiss_fide_id
			ON weiss_fide_id.contact_id = white_persons.contact_id
			AND weiss_fide_id.current = "yes"
			AND weiss_fide_id.identifier_category_id = %d
		LEFT JOIN contacts_identifiers schwarz_fide_id
			ON schwarz_fide_id.contact_id = black_persons.contact_id
			AND schwarz_fide_id.current = "yes"
			AND schwarz_fide_id.identifier_category_id = %d
		WHERE events.event_id = (%d)
		%s
		ORDER BY events.identifier, partien.runde_no, paarungen.tisch_no, partien.brett_no
	';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		wrap_category_id('identifiers/fide-id'),
		wrap_category_id('identifiers/fide-id'),
		$event_id,
		$where ? ' AND '.implode(' AND ', $where) : ''
	);
	$games = wrap_db_fetch($sql, 'partie_id');
	$games = mod_tournaments_games_cleanup($games);
	return $games;
}

function mod_tournaments_games_cleanup($games) {
	return $games;

	// @disabled
	foreach ($games as $partie_id => $partie) {
		if (empty($partie['moves'])) continue;
		$games[$partie_id]['moves'] = preg_replace('/{\[\%clk \d+:\d+:\d+\]} /', '', $partie['moves']);
		$games[$partie_id]['moves'] = preg_replace('/{\[\%emt \d+:\d+:\d+\]} /', '', $partie['moves']);
	}
	return $games;
}

/**
 * Ausgabe der Partien als HTML-Seite
 *
 * @param array $event
 * @param array $settings
 * @return array
 */
function mod_tournaments_games_html($event, $settings) {
	if (preg_match('/^(\d+)-(\d+)$/', $settings['request'], $matches)) {
		// 1-2-4
		$runde = $matches[1];
		$tisch = 0;
		$brett = $matches[2];
	} elseif (preg_match('/^(\d+)-(\d+)-(\d+)$/', $settings['request'], $matches)) {
		// 1-2-4
		$runde = $matches[1];
		$tisch = $matches[2];
		$brett = $matches[3];
	} else {
		return false;
	}
	$sql = 'SELECT partien.partie_id
			, CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname) AS weiss
			, CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname) AS schwarz
			, weiss_ergebnis, schwarz_ergebnis
			, partien.runde_no, tisch_no, partien.brett_no
			, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), "")) AS heim_team
			, heim_teams.identifier AS heim_team_identifier
			, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), "")) AS auswaerts_team
			, auswaerts_teams.identifier AS auswaerts_team_identifier
			, weiss.t_dwz AS weiss_dwz
			, weiss.t_elo AS weiss_elo
			, schwarz.t_dwz AS schwarz_dwz
			, schwarz.t_elo AS schwarz_elo
			, pgn, eco
			, IF(ISNULL(paarung_id), weiss.setzliste_no, NULL) AS weiss_teilnehmer_nr
			, IF(ISNULL(paarung_id), schwarz.setzliste_no, NULL) AS schwarz_teilnehmer_nr
			, partien.kommentar
			, weiss_zeit AS WhiteClock, schwarz_zeit AS BlackClock
			, DATE_FORMAT(partien.last_update, "%%H:%%i") AS last_update
			, tournaments.livebretter
			, IF(vertauschte_farben = "ja", 1, NULL) AS vertauschte_farben
			, IF(partiestatus_category_id NOT IN (%d, %d), partiestatus.category, "") AS partiestatus
			, url
		FROM partien
		LEFT JOIN categories partiestatus
			ON partiestatus.category_id = partien.partiestatus_category_id
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		LEFT JOIN persons white_persons
			ON partien.weiss_person_id = white_persons.person_id
		LEFT JOIN persons black_persons
			ON partien.schwarz_person_id = black_persons.person_id
		LEFT JOIN participations weiss
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = %d
			AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
			AND weiss.event_id = partien.event_id
		LEFT JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = %d
			AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", heim_teams.team_id, auswaerts_teams.team_id))
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partien.runde_no = %d
		AND (tisch_no = %d OR ISNULL(tisch_no))
		AND partien.brett_no = %d';
	$sql = sprintf($sql,
		wrap_category_id('partiestatus/normal'),
		wrap_category_id('partiestatus/laufend'),
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		$event['event_id'], $runde, $tisch, $brett
	);
	$partie = wrap_db_fetch($sql);
	$copy_fields = ['main_series_path', 'main_series', 'duration', 'turnierort'];
	foreach ($copy_fields as $copy_field)
		$partie[$copy_field] = $event[$copy_field];
	if (!$partie) return false;
	$pgn = ['moves' => $partie['pgn']];
	if (!$partie['weiss_ergebnis'] AND !$partie['schwarz_ergebnis'])
		$partie['live'] = mf_tournaments_live_round($partie['livebretter'], $partie['brett_no'], $partie['tisch_no']);

	$partie = array_merge($event, $partie);
	$partie = array_merge($partie, mf_chess_pgn_to_html($pgn));
	return mod_tournaments_games_htmlout($partie);
}

/**
 * output single game on HTML page
 *
 * @param array $partie
 * @return array
 */
function mod_tournaments_games_htmlout($partie) {
	if (!$partie['pgn']) $page['status'] = 404;
	$page['query_strings'][] = 'minimal';
	if (isset($_GET['minimal'])) $page['template'] = 'dem-minimal';
	$page['dont_show_h1'] = true;
	$page['title'] = $partie['event'].' '.$partie['year']
		.(!empty($partie['runde_no']) ? ', Runde '.$partie['runde_no'].': ' : '')
		.(!empty($partie['tag']) ? ', Tag '.$partie['tag'].': ' : '')
		.$partie['weiss'].'–'.$partie['schwarz'];
	if (!empty($partie['breadcrumbs'])) {
		$page['breadcrumbs'] = $partie['breadcrumbs'];
	} else {
		$page['breadcrumbs'][] = '<a href="../../runde/'.$partie['runde_no'].'/">'.$partie['runde_no'].'. Runde</a>';
		if (!empty($partie['tisch_no'])) {
			$page['breadcrumbs'][]['title'] = sprintf('Partie Tisch %s, Brett %s', $partie['tisch_no'], $partie['brett_no']);
		} elseif (!empty($partie['brett_no'])) {
			$page['breadcrumbs'][]['title'] = sprintf('Partie Brett %s', $partie['brett_no']);
		}
	}
	$page['meta'][] = ['name' => 'robots', 'content' => 'noindex, follow, noarchive'];
	$page['text'] = wrap_template('game', $partie);
	return $page;
}

/**
 * output special games
 *
 * @param array $event
 * @param array $settings
 * @return array
 */
function mod_tournaments_games_special_html($event, $settings) {
	$request = explode('-', $settings['request']);
	if (count($request) !== 2) return false;

	$filename = sprintf($settings['pgn_path'], $event['identifier'], $settings['type'].'-'.$request[0]);
	if (!file_exists($filename)) return false;
	$pgn = mf_chess_pgn_parse(file($filename), $filename);
	$partie_no = $request[1] - 1;
	if (!array_key_exists($partie_no, $pgn)) return false;
	$pgn = $pgn[$partie_no];
	// PGN from file, Latin 1
	if (wrap_setting('character_set') === 'utf-8') {
		if (!mb_detect_encoding($pgn['moves'], 'UTF-8', true))
			$pgn['moves'] = mb_convert_encoding($pgn['moves'], 'UTF-8', 'ISO-8859-1');
	}

	$event['tag'] = $request[0];
	$event['weiss'] = $pgn['head']['White'];
	$event['schwarz'] = $pgn['head']['Black'];
	$event['Annotator'] = isset($pgn['head']['Annotator']) ? $pgn['head']['Annotator'] : '';
	$event['breadcrumbs'][] = 'Partie des Tages';
	$event['breadcrumbs'][]['title'] = 'Tag '.$event['tag'].', Partie '.$request[1];

	$event += mf_chess_pgn_to_html($pgn);
	return mod_tournaments_games_htmlout($event);
}

/**
 * reduce game list to live games from this tournament
 *
 * @param array $games
 * @param array $event
 * @return array
 */
function mod_tournaments_games_liveonly($games, $event) {
	$livebretter = mf_tournaments_live_boards($event['livebretter'], $event['bretter_max']);
	if (str_starts_with($event['turnierform'], 'mannschaft-')) {
		$livebretter_old = $livebretter;
		$livebretter = [];
		foreach ($livebretter_old as $brett) {
			if (strstr($brett, '.')) $livebretter[] = $brett;
			else {
				for ($i = 1; $i <= $event['bretter_max']; $i++) {
					$livebretter[] = sprintf('%d.%d', $brett, $i);
				}
			}
		}
	}
	foreach ($games as $index => $partie) {
		if ($partie['Table']) $board = $partie['Table'].'.'.$partie['Board'];
		else $board = $partie['Board'];
		if (!in_array($board, $livebretter)) unset($games[$index]);
	}
	return $games;
}

/**
 * check for query string attached to .pgn-requests
 *
 * some clever programmers thought this is a good idea to disable caching
 * but this of course disables 304 requests, too, not so clever
 *
 * @return array
 */
function mod_tournaments_games_check_qs() {
	global $zz_page;

	$url = parse_url(wrap_setting('request_uri'));
	if (!str_ends_with($url['path'], '.pgn')) return [];
	if (empty($url['query'])) return [];
	parse_str($url['query'], $qs);
	$zz_page['url']['full']['query'] = false;
	if (wrap_setting('cache_age'))
		wrap_send_cache(wrap_setting('cache_age'));
	return array_keys($qs);
}
