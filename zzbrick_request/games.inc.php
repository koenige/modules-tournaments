<?php 

/**
 * tournaments module
 * Re-play games online
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005, 2012-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Re-play games online
 *
 * @param array $vars
 *		int [0]: year
 *		string [1]: event_identifier
 *		string [2]: 1-2-4
 */
function mod_tournaments_games($vars, $settings = [], $event = []) {
	if (count($vars) !== 3 AND count($vars) !== 4) return false;
	// direct access via filemove
	if (empty($event)) $event = my_event($vars[0], $vars[1]);
	
	$settings['request'] = array_pop($vars);
	$settings = mod_tournaments_games_settings($settings);
	
	$sql = 'SELECT series.category_short AS series_short
			, runden, tournament_id, livebretter
			, IF(bretter_min, bretter_min, (SELECT COUNT(*)/2 FROM participations
				WHERE event_id = events.event_id
				AND usergroup_id = /*_ID usergroups spieler _*/)) AS bretter_max
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE events.event_id = %d
	';
	$sql = sprintf($sql, $event['website_id'], $event['event_id']);
	$event = array_merge($event, wrap_db_fetch($sql));
	$settings['send_as'] = $event['year'].' '.($event['series_short'] ?? $event['event']);

	switch ($settings['content_type']) {
	case 'json':
		wrap_include('pgn', 'chess');
		return mod_tournaments_games_json($event, $settings);
	default:
		wrap_include('pgn', 'chess');
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

	if (str_ends_with($settings['request'], '.json')) {
		$settings['content_type'] = 'json';
		$settings['request'] = substr($settings['request'], 0, -5);
	} else {
		$settings['content_type'] = 'html';
	}
	$settings['pgn_path'] = wrap_setting('pgn_dir').'/%s/%s.pgn';
	return $settings;
}

//
// --- OUTPUT ---
//

/**
 * Auslesen einer Live-Partie als JSON
 */
function mod_tournaments_games_json($event, $settings) {
	if (!$event['running']) // JSON files are only used while tournament is running
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
	wrap_include('pgn', 'tournaments');
	$pgn = mf_tournaments_pgn_db($event['event_id'], $round_no, $brett_no);

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
			, IF(partiestatus_category_id NOT IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/laufend _*/), partiestatus.category, "") AS partiestatus
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
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = /*_ID usergroups spieler _*/
			AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
			AND weiss.event_id = partien.event_id
		LEFT JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = /*_ID usergroups spieler _*/
			AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", heim_teams.team_id, auswaerts_teams.team_id))
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partien.runde_no = %d
		AND (tisch_no = %d OR ISNULL(tisch_no))
		AND partien.brett_no = %d';
	$sql = sprintf($sql, $event['event_id'], $runde, $tisch, $brett);
	$partie = wrap_db_fetch($sql);
	$copy_fields = ['main_series_path', 'main_series', 'duration', 'place'];
	foreach ($copy_fields as $copy_field)
		$partie[$copy_field] = $event[$copy_field];
	if (!$partie) return false;
	$pgn = ['moves' => $partie['pgn']];
	if (!$partie['weiss_ergebnis'] AND !$partie['schwarz_ergebnis'])
		$partie['live'] = mf_tournaments_live_board($partie['livebretter'], $partie['brett_no'], $partie['tisch_no']);

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
		$page['breadcrumbs'][] = ['title' => $partie['runde_no'].'. Runde', 'url_path' => '../../runde/'.$partie['runde_no'].'/'];
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
	$event['breadcrumbs'][]['title'] = 'Partie des Tages';
	$event['breadcrumbs'][]['title'] = 'Tag '.$event['tag'].', Partie '.$request[1];

	$event += mf_chess_pgn_to_html($pgn);
	return mod_tournaments_games_htmlout($event);
}
