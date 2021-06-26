<?php 

/**
 * tournaments module
 * PGN download and re-play games online
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005, 2012-2021 Gustaf Mossakowski
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
function mod_tournaments_games($vars) {
	global $zz_setting;
	
	$qs = mod_tournaments_games_check_qs();

	$zz_setting['brick_formatting_functions'][] = 'pgn_wordwrap';
	require_once __DIR__.'/../tournaments/pgn.inc.php';

	$typ = false;
	if (!empty($vars[2])) {
		if ($vars[2] === 'partien') unset($vars[2]);
		elseif ($vars[2] === 'pdt') {
			unset($vars[2]);
			$typ = 'pdt';
		}
	}
	if (count($vars) !== 3 AND count($vars) !== 4) return false;
	$request = array_pop($vars);

	// Reihe?
	$sql = 'SELECT events.event_id, main_series.category_short AS series_short
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, events.identifier, runden, events.event
			, place
			, addresses.*
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
		, $zz_setting['website_id']
		, $vars[0]
		, wrap_db_escape($vars[1])
		, wrap_id('countries', '--') // internet
	);
	$events = wrap_db_fetch($sql, 'event_id');
	if ($events AND in_array($request, ['gesamt.pgn', 'gesamt-utf8.pgn'])) {
		return mod_tournaments_games_series($events, $request);
	}

	$sql = 'SELECT events.event_id, series.category_short AS series_short
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, events.identifier, runden, events.event
			, place, tournament_id, livebretter
			, IF(bretter_min, bretter_min, (SELECT COUNT(teilnahme_id)/2 FROM teilnahmen
				WHERE event_id = events.event_id
				AND usergroup_id = %d)) AS bretter_max
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
		FROM events
		LEFT JOIN addresses
			ON events.place_contact_id = addresses.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE events.identifier = "%d/%s"
	';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		$zz_setting['website_id'],
		$vars[0], wrap_db_escape($vars[1])
	);
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	if (substr($request, -4) === '.pgn') {
		return mod_tournaments_games_file($event, substr($request, 0, -4), $typ, $qs);
	} elseif (substr($request, -5) === '.json') {
		return mod_tournaments_games_json($event, $request);
	} else {
		return mod_tournaments_games_html($event, $request, $typ);
	}
}

/**
 * Auslesen einer Live-Partie als JSON
 */
function mod_tournaments_games_json($event, $request) {
	global $zz_conf;
	global $zz_setting;
	$zz_setting['cache'] = false;

	$request = substr($request, 0, -5);
	if (!strstr($request, '-')) return false;
	if (substr_count($request, '-') === 1) {
		list($runde_no, $brett_no) = explode('-', $request);
		$tisch_no = 0;
	} elseif (substr_count($request, '-') === 2) {
		list($runde_no, $tisch_no, $brett_no) = explode('-', $request);
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
	$sql = sprintf($sql, $event['event_id'], $runde_no, $brett_no, $tisch_no);
	$partie = wrap_db_fetch($sql);
	if (!$partie) return false;
	if (!$partie['Live']) $partie['Live'] = false;
	$pgn = mod_tournaments_games_pgn($event['event_id'], $runde_no, $brett_no);

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

	$partie['PGN'] = wrap_template('pgn', $pgn);
	$pgn = [
		'moves' => $partie['Moves']
	];
	$data = pgn_to_html($pgn);
	$partie['PgnMoveText'] = $data['html'];
	if ($zz_conf['character_set'] === 'utf-8') {
		// PGN = Latin1
		$partie['PgnMoveText'] = utf8_encode($partie['PgnMoveText']);
		if (!empty($partie['PGN']))
			$partie['PGN'] = utf8_encode($partie['PGN']);
	}

	$page['content_type'] = 'json';
	$page['headers']['filename'] = $event['year'].' '.$event['series_short'].' Runde '.$runde_no.' Brett '.$brett_no.'.json';
	$page['headers']['filename'] = '';
	$page['text'] = json_encode($partie);
	return $page;
}

/**
 * Auslesen aller PGN-Daten einer Reihe
 *
 * @param array $events
 * @param string $request
 */
function mod_tournaments_games_series($events, $request) {
	switch ($request) {
		case 'gesamt.pgn': $character_encoding = 'iso-8859-1'; break;
		case 'gesamt-utf8.pgn': $character_encoding = 'utf-8'; break;
		default: return false;
	}
	$page['text'] = '';
	foreach ($events as $event) {
		$partien = mod_tournaments_games_pgn($event['event_id']);
		$page['text'] .= wrap_template('pgn', $partien);
	}
	$event = array_shift($events);
	$page['headers']['filename'] = $event['year'].' '.$event['series_short'].'.pgn';
	$page['text'] = str_replace("\n", "\r\n", $page['text']);
	if (!$page['text']) return wrap_quit(404);
	$page['content_type'] = 'pgn';
	if ($character_encoding === 'utf-8')
		$page['text'] = utf8_encode($page['text']);
	$zz_conf['character_set'] = $character_encoding;
	return $page;
}

/**
 * Ausgabe der Partien als PGN-Datei
 *
 * @param array $event
 * @param string $request
 *		gesamt.pgn = alle Dateien eines Turniers oder einer Turnierreihe
 *		live.pgn = aktuelle Runde, nur Livebretter, Kopf aus Datenbank
 *			(vor Runde: nur Partienköpfe)
 *		live-raw.pgn = aktuelle Runde, nur Livebretter, 1:1 von Brettern
 *		1-live.pgn = 1. Runde, nur Livebretter, Kopf aus Datenbank
 *		1-live-raw.pgn = 1. Runde, nur Livebretter, 1:1 von Brettern
 *		current.pgn = aktuelle Runde
 *		1.pgn = Runde 1
 *		1-1.pgn = Runde 1, Brett 1 (Einzelturnier)
 *		1-1-1.pgn = Runde 1, Tisch 1, Brett 1 (Mannschaftsturnier)
 * @param string $typ (optional)
 * @param array $qs (optional)
 * @return void
 */
function mod_tournaments_games_file($event, $request, $typ = false, $qs = []) {
	global $zz_setting;
	global $zz_conf;
	
	// ignore query strings behind .pgn
	if ($qs) $page['query_strings'] = $qs;
	
	$events = [];
	if ($event AND !array_key_exists('identifier', $event)) {
		// Liste von Terminen, nur für Gesamt-PGN erlaubt
		if (!in_array($request, ['gesamt', 'gesamt-utf8'])) return false;
		$events = $event;
	} else {
		$pgn_path = $zz_setting['media_folder'].'/pgn/'.$event['identifier'].'/%s.pgn';
	}
	if ($typ) {
		// separate file, e.g. pdt
		// @todo allow utf8 export of latin1 file
		$file['name'] = sprintf($pgn_path, $typ.'-'.$request);
		if (!file_exists($file['name'])) return false;
		$file['send_as'] = $event['year'].' '.$typ.' Runde '.$request.'.pgn';
		$file['etag_generate_md5'] = true;
		$zz_conf['character_set'] = 'iso-8859-1';
		wrap_file_send($file);
	}

	$zz_conf['character_set'] = 'iso-8859-1';
	if (substr($request, -5) === '-utf8') {
		$zz_conf['character_set'] = 'utf-8';
		$request = substr($request, 0, -5);
	}
	$file = [];
	if ($request === 'gesamt') {
		// "2012 DVM U20w.pgn"
		$partien = mod_tournaments_games_pgn($event['event_id']);
		$page['headers']['filename'] = '';

	} elseif ($request === 'current') {
		$sql = 'SELECT MAX(runde_no) FROM partien WHERE event_id = %d';
		$sql = sprintf($sql, $event['event_id']);
		$runde_no = wrap_db_fetch($sql, '', 'single value');
		$partien = mod_tournaments_games_pgn($event['event_id'], $runde_no);
		$page['headers']['filename'] = ' Runde '.$runde_no;

	} elseif ($request === 'live') {
		$sql = 'SELECT MAX(runde_no) FROM partien WHERE event_id = %d';
		$sql = sprintf($sql, $event['event_id']);
		$runde_no = wrap_db_fetch($sql, '', 'single value');
		$partien = mod_tournaments_games_pgn($event['event_id'], $runde_no);
		$partien = mod_tournaments_games_liveonly($partien, $event);
		$page['headers']['filename'] = ' Runde '.$runde_no.' (Live)';
		$zz_setting['cache_age'] = 1;
		wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_get_setting('live_cache_control_age')));

	} elseif ($request === 'live-raw') {
		// output of raw live games, current round, directly how they come from the boards
		// check for local file and source from external server
		for ($i = 1; $i <= $event['runden']; $i++) {
			$pgn = sprintf($pgn_path, $i.'-live');
			if (!file_exists($pgn)) continue;
			$runde_no = $i;
			$file['name'] = $pgn;
		}
		if (empty($file)) {
			// maybe there's some path on the server / on an external server?
			// read turniere_partien
			$page['text'] = pgn_file_from_tournament($event['tournament_id']);
			if (!$page['text']) return false;
			$page['headers']['filename'] =  ' (Live)';
		} else {
			$page['headers']['filename'] =  ' Runde '.$runde_no.' (Live)';
		}
		wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_get_setting('live_cache_control_age')));

	} elseif (preg_match('/^(\d+)-live$/', $request, $matches)) {
		$runde_no = $matches[1];
		$partien = mod_tournaments_games_pgn($event['event_id'], $runde_no);
		$partien = mod_tournaments_games_liveonly($partien, $event);
		$page['headers']['filename'] = ' Runde '.$runde_no.' (Live)';
		$zz_setting['cache'] = false;

	} elseif (preg_match('/^(\d+)-live-raw$/', $request, $matches)) {
		// output of raw live games per round, directly how they come from the boards
		$runde_no = $matches[1];
		$pgn = sprintf($pgn_path, $runde_no.'-live');
		if (!file_exists($pgn)) return false;
		$file['name'] = $pgn;
		$page['headers']['filename'] = ' Runde '.$runde_no.' (Live)';

	} elseif (preg_match('/^(\d+)$/', $request, $matches)) {
		// "2012 DVM U20w Runde 1.pgn"
		$partien = mod_tournaments_games_pgn($event['event_id'], $matches[1]);
		$page['headers']['filename'] = ' Runde '.$matches[1];

	} elseif (preg_match('/^(\d+)-(\d+)$/', $request, $matches)) {
		// 1-1.pgn = Runde 1 Brett 1, Einzelturnier
		$partien = mod_tournaments_games_pgn($event['event_id'], $matches[1], $matches[2]);
		$page['headers']['filename'] = ' Runde '.$matches[1].' Brett '.$matches[2];

	} elseif (preg_match('/^(\d+)-(\d+)\-(\d+)$/', $request, $matches)) {
		// 1-4-1.pgn = Runde 1 Tisch 4 Brett 1, Mannschaftsturnier
		$partien = mod_tournaments_games_pgn($event['event_id'], $matches[1], $matches[3], $matches[2]);
		$page['headers']['filename'] = ' Runde '.$matches[1].' Tisch '.$matches[2].' Brett '.$matches[3];

	} else {
		return false;
	}

	$page['headers']['filename'] = $event['year'].' '.$event['series_short'].$page['headers']['filename'].'.pgn';
	
	if (!empty($file)) {
		if ($zz_conf['character_set'] === 'iso-8859-1') {
			// output file directly
			$file['send_as'] = $page['headers']['filename'];
			$file['etag_generate_md5'] = true;
			wrap_file_send($file);
			exit;
		} else {
			// convert file
			$page['text'] = file_get_contents($file['name']);
		}
	} else {
		// create PGN file
		$page['text'] = wrap_template('pgn', $partien);
	}

	$page['text'] = str_replace("\n", "\r\n", $page['text']);
	if (!$page['text']) {
		$zz_conf['character_set'] = 'utf-8';
		return false;
	}
	$page['content_type'] = 'pgn';
	if ($zz_conf['character_set'] === 'utf-8')
		$page['text'] = utf8_encode($page['text']);
	return $page;
}

/**
 * Auslesen der Daten für eine PGN aus der Datenbank
 *
 * @param int $event_id
 * @param int $runde_no (optional)
 * @param int $brett_no (optional)
 * @param int $tisch_no (optional, nur bei Mannschaftsturnieren)
 * @return array $partien
 */
function mod_tournaments_games_pgn($event_id, $runde_no = false, $brett_no = false, $tisch_no = false) {
	$where = [];
	if ($runde_no) $where[] = sprintf('partien.runde_no = %d', $runde_no);
	if ($brett_no) $where[] = sprintf('partien.brett_no = %d', $brett_no);
	if ($tisch_no) $where[] = sprintf('paarungen.tisch_no = %d', $tisch_no);
	
	wrap_db_query('SET NAMES latin1');
	$zz_conf['character_set'] = 'iso-8859-1';

	$sql = 'SELECT partien.partie_id
			, events.event, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
			, DATE_FORMAT(events.date_begin, "%%Y.%%m.%%d") AS EventDate
			, DATE_FORMAT(runden.date_begin, "%%Y.%%m.%%d") AS Date
			, IF(ISNULL(url), IF(LOCATE("&virtual=1", place_categories.parameters), events.direct_link, place), url) AS Site
			, countries.ioc_code AS EventCountry
			, partien.runde_no AS Round
			, partien.brett_no AS Board
			, CONCAT(weiss.t_nachname, ", ", weiss.t_vorname, IFNULL(CONCAT(" ", weiss.t_namenszusatz), "")) AS White
			, CONCAT(schwarz.t_nachname, ", ", schwarz.t_vorname, IFNULL(CONCAT(" ", schwarz.t_namenszusatz), "")) AS Black
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
		LEFT JOIN countries USING (country_id)
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		JOIN teilnahmen weiss
			ON partien.weiss_person_id = weiss.person_id AND weiss.usergroup_id = %d
			AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
			AND weiss.event_id = partien.event_id
		JOIN teilnahmen schwarz
			ON partien.schwarz_person_id = schwarz.person_id AND schwarz.usergroup_id = %d
			AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", heim_teams.team_id, auswaerts_teams.team_id))
			AND schwarz.event_id = partien.event_id
		LEFT JOIN personen weiss_personen
			ON weiss_personen.person_id = weiss.person_id
		LEFT JOIN personen schwarz_personen
			ON schwarz_personen.person_id = schwarz.person_id
		LEFT JOIN contacts_identifiers weiss_fide_id
			ON weiss_fide_id.contact_id = weiss_personen.contact_id
			AND weiss_fide_id.current = "yes"
			AND weiss_fide_id.identifier_category_id = %d
		LEFT JOIN contacts_identifiers schwarz_fide_id
			ON schwarz_fide_id.contact_id = schwarz_personen.contact_id
			AND schwarz_fide_id.current = "yes"
			AND schwarz_fide_id.identifier_category_id = %d
		WHERE events.event_id = (%d)
		%s
		ORDER BY events.identifier, partien.runde_no, paarungen.tisch_no, partien.brett_no
	';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		wrap_category_id('kennungen/fide-id'),
		wrap_category_id('kennungen/fide-id'),
		$event_id,
		$where ? ' AND '.implode(' AND ', $where) : ''
	);
	$partien = wrap_db_fetch($sql, 'partie_id');
	$partien = mod_tournaments_games_cleanup($partien);
	return $partien;
}

function mod_tournaments_games_cleanup($partien) {
	return $partien;

	// @disabled
	foreach ($partien as $partie_id => $partie) {
		if (empty($partie['moves'])) continue;
		$partien[$partie_id]['moves'] = preg_replace('/{\[\%clk \d+:\d+:\d+\]} /', '', $partie['moves']);
		$partien[$partie_id]['moves'] = preg_replace('/{\[\%emt \d+:\d+:\d+\]} /', '', $partie['moves']);
	}
	return $partien;
}

/**
 * Ausgabe der Partien als HTML-Seite
 *
 * @param array $event
 * @param string $request
 * @return array
 */
function mod_tournaments_games_html($event, $request, $typ) {
	global $zz_setting;
	global $zz_conf;

	if ($typ === 'pdt') {
		$request = explode('-', $request);
		if (count($request) !== 2) return false;
		$partie = [];
		$partie['tag'] = $request[0];
		$partie['partie_no'] = $request[1];
		$filename = $zz_setting['media_folder'].'/pgn/'.$event['identifier'].'/'.$typ.'-'.$request[0].'.pgn';
		if (!file_exists($filename)) {
			return false;
		}
		$pgn = pgn_parse(file($filename), $filename);
		if (!array_key_exists(($partie['partie_no'] - 1), $pgn)) return false;
		$pgn = $pgn[$partie['partie_no'] - 1];
		$partie['weiss'] = $pgn['head']['White'];
		$partie['schwarz'] = $pgn['head']['Black'];
		$partie['Annotator'] = isset($pgn['head']['Annotator']) ? $pgn['head']['Annotator'] : '';
		$partie['breadcrumbs'] = 'Partie des Tages';
		$partie['breadcrumbs'] = 'Tag '.$partie['tag'].', Partie '.$request[1];
		$db = false;
	} elseif (preg_match('/^(\d+)-(\d+)$/', $request, $matches)) {
		// 1-2-4
		$runde = $matches[1];
		$tisch = 0;
		$brett = $matches[2];
		$db = true;
	} elseif (preg_match('/^(\d+)-(\d+)-(\d+)$/', $request, $matches)) {
		// 1-2-4
		$runde = $matches[1];
		$tisch = $matches[2];
		$brett = $matches[3];
		$db = true;
	} else {
		return false;
	}
	if ($db) {
		$sql = 'SELECT partien.partie_id
				, CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname) AS weiss
				, CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname) AS schwarz
				, weiss_ergebnis, schwarz_ergebnis
				, partien.runde_no, tisch_no, partien.brett_no
				, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), "")) AS heim_team
				, heim_teams.kennung AS heim_team_identifier
				, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), "")) AS auswaerts_team
				, auswaerts_teams.kennung AS auswaerts_team_identifier
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
				, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
				, main_series.category_short AS main_series
				, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
				, IFNULL(place, places.contact) AS turnierort
				, IF(partiestatus_category_id NOT IN (%d, %d), partiestatus.category, "") AS partiestatus
				, url
			FROM partien
			LEFT JOIN categories partiestatus
				ON partiestatus.category_id = partien.partiestatus_category_id
			LEFT JOIN tournaments USING (event_id)
			LEFT JOIN events USING (event_id)
			LEFT JOIN contacts places
				ON events.place_contact_id = places.contact_id
			LEFT JOIN addresses
				ON places.contact_id = addresses.contact_id
			LEFT JOIN categories series
				ON events.series_category_id = series.category_id
			LEFT JOIN categories main_series
				ON main_series.category_id = series.main_category_id
			LEFT JOIN paarungen USING (paarung_id)
			LEFT JOIN teams heim_teams
				ON paarungen.heim_team_id = heim_teams.team_id
			LEFT JOIN teams auswaerts_teams
				ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
			LEFT JOIN teilnahmen weiss
				ON partien.weiss_person_id = weiss.person_id AND weiss.usergroup_id = %d
				AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
				AND weiss.event_id = partien.event_id
			LEFT JOIN teilnahmen schwarz
				ON partien.schwarz_person_id = schwarz.person_id AND schwarz.usergroup_id = %d
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
		if (!$partie) return false;
		$pgn = ['moves' => $partie['pgn']];
		if (!$partie['weiss_ergebnis'] AND !$partie['schwarz_ergebnis']) {
			$partie['live'] = mf_tournaments_live_round($partie['livebretter'], $partie['brett_no'], $partie['tisch_no']);
		}
	} else {
		// PGN from file, Latin 1
		if ($zz_conf['character_set'] === 'utf-8') {
			$pgn['moves'] = utf8_encode($pgn['moves']);
		}
	}
	$partie = array_merge($event, $partie);

	$partie = array_merge($partie, pgn_to_html($pgn));
	if (!$partie['pgn']) $page['status'] = 404;
	
	$page['query_strings'][] = 'minimal';
	if (isset($_GET['minimal'])) $page['template'] = 'dem-minimal';
	$page['dont_show_h1'] = true;
	$page['title'] = $partie['event'].' '.$partie['year']
		.(!empty($partie['runde_no']) ? ', Runde '.$partie['runde_no'].': ' : '')
		.(!empty($partie['tag']) ? ', Tag '.$partie['tag'].': ' : '')
		.$partie['weiss'].'–'.$partie['schwarz'];
	$page['breadcrumbs'][] = '<a href="../../../">'.$partie['year'].'</a>';
	if (!empty($partie['main_series'])) {
		$page['breadcrumbs'][] = '<a href="../../../'.$partie['main_series_path'].'/">'.$partie['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../../">'.$partie['event'].'</a>';
	if (!empty($partie['breadcrumbs'])) {
		$page['breadcrumbs'][] = $partie['breadcrumbs'];
	} else {
		$page['breadcrumbs'][] = '<a href="../../runde/'.$partie['runde_no'].'/">'.$partie['runde_no'].'. Runde</a>';
		if (!empty($partie['tisch_no'])) {
			$page['breadcrumbs'][] = sprintf('Partie Tisch %s, Brett %s', $partie['tisch_no'], $partie['brett_no']);
		} elseif (!empty($partie['brett_no'])) {
			$page['breadcrumbs'][] = sprintf('Partie Brett %s', $partie['brett_no']);
		}
	}
	$page['extra']['realm'] = 'sports';
	$page['meta'][] = ['name' => 'robots', 'content' => 'noindex, follow, noarchive'];
	$page['text'] = wrap_template('game', $partie);
	return $page;
}

/**
 * reduce game list to live games from this tournament
 *
 * @param array $partien
 * @param array $event
 * @return array $partien
 */
function mod_tournaments_games_liveonly($partien, $event) {
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
	foreach ($partien as $index => $partie) {
		if ($partie['Table']) $board = $partie['Table'].'.'.$partie['Board'];
		else $board = $partie['Board'];
		if (!in_array($board, $livebretter)) unset($partien[$index]);
	}
	return $partien;
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
	global $zz_setting;
	global $zz_page;

	$url = parse_url($zz_setting['request_uri']);
	if (!str_ends_with($url['path'], '.pgn')) return [];
	if (empty($url['query'])) return [];
	parse_str($url['query'], $qs);
	$zz_page['url']['full']['query'] = false;
	if (!empty($zz_setting['cache_age'])) {
		wrap_send_cache($zz_setting['cache_age']);
	}
	return array_keys($qs);
}
