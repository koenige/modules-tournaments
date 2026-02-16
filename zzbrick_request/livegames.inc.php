<?php 

/**
 * tournaments module
 * Overview of live games
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2016, 2018-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht der Live-Partien
 *
 * @param array $params
 *		int [0]: Jahr
 *		string [1]: Terminkennung
 * @param array $settings
 * @param array $data
 * @return array
 */
function mod_tournaments_livegames($params, $settings, $data) {
	wrap_include('pgn', 'chess');

	if (count($params) !== 2) return false;

	// alle Turniere der Reihe ausgeben
	$sql = 'SELECT events.event_id, livebretter, events.identifier
			, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, (SELECT COUNT(*) FROM participations
			WHERE participations.event_id = tournaments.event_id
			AND usergroup_id = /*_ID usergroups spieler _*/) AS teilnehmer
			, (SELECT MAX(runde_no) FROM partien
			WHERE partien.event_id = tournaments.event_id) AS aktuelle_runde_no
			, IF((DATE_SUB(CURDATE(), INTERVAL /*_SETTING tournaments_live_games_show_for_days _*/ DAY) <= date_end), 1, NULL) AS current
		FROM tournaments
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE main_event_id = %d
		AND NOT ISNULL(livebretter)
		ORDER BY series.sequence';
	$sql = sprintf($sql, $data['event_id']);
	$data['tournaments'] = wrap_db_fetch($sql, 'event_id');
	if ($data['tournaments']) return mod_tournaments_livegames_series($data);
	
	// Einzelnes Turnier?
	$sql = 'SELECT event_id, livebretter
			, (SELECT COUNT(*) FROM participations
			WHERE participations.event_id = tournaments.event_id
			AND usergroup_id = /*_ID usergroups spieler _*/) AS teilnehmer
			, (SELECT MAX(runde_no) FROM partien
			WHERE partien.event_id = tournaments.event_id) AS aktuelle_runde_no
		FROM tournaments
		WHERE tournaments.event_id = %d
		AND NOT ISNULL(livebretter)';
	$sql = sprintf($sql, $data['event_id']);
	$tournament = wrap_db_fetch($sql);
	if (!$tournament) return false;
	
	$data += $tournament;
	return mod_tournaments_livegames_tournament($data);
}

/**
 * show live chess boards for a single tournament
 *
 * @param array $tournament
 * @return array
 */
function mod_tournaments_livegames_tournament($tournament) {
	$rounds = mod_tournaments_livegames_rounds([$tournament['event_id']]);
	unset($rounds[$tournament['event_id']][$tournament['aktuelle_runde_no']]['runde_no']);
	unset($rounds[$tournament['event_id']][$tournament['aktuelle_runde_no']]['main_event_id']);
	$tournament += $rounds[$tournament['event_id']][$tournament['aktuelle_runde_no']];
	unset($rounds);

	$tournament = mod_tournaments_livegames_bretter($tournament);
	$tournament['last_update'] = substr($tournament['last_update'], 11, 5);
	
	$page['breadcrumbs'][]['title'] = 'Livepartien';
	$page['title'] = 'Livepartien '.$tournament['event'].' '.$tournament['year'];
	$page['text'] = wrap_template('livegames-tournament', $tournament);
	return $page;
}

/**
 * show links to tournaments of a series with live games
 *
 * @param array $data
 * @return array
 */
function mod_tournaments_livegames_series($data) {
	$rounds = mod_tournaments_livegames_rounds(array_keys($data['tournaments']));

	$has_rounds = false;
	foreach ($data['tournaments'] AS $event_id => $turnier) {
		if (empty($turnier['aktuelle_runde_no'])) continue;
		$has_rounds = true;
		if (!$turnier['current']) {
			unset($data['tournaments'][$event_id]);
			continue;
		}
		unset($rounds[$event_id][$turnier['aktuelle_runde_no']]['runde_no']);
		unset($rounds[$event_id][$turnier['aktuelle_runde_no']]['main_event_id']);
		if (!empty($rounds[$event_id])) {
			$data['tournaments'][$event_id] += $rounds[$event_id][$turnier['aktuelle_runde_no']];
		}
	}
	if (!$has_rounds) return false;
	if (!$data['tournaments'])
		wrap_quit(410, wrap_text('The tournament is over. All live games are integrated into the <a href="../">tournament pages</a>.'));

	$data['tournaments']['last_update'] = '';
	foreach ($data['tournaments'] as $event_id => $turnier) {
		if (!is_numeric($event_id)) continue;
		$data['tournaments'][$event_id] = mod_tournaments_livegames_bretter($turnier);
		if ($data['tournaments'][$event_id]['last_update'] > $data['tournaments']['last_update'])
			$data['tournaments']['last_update'] = $data['tournaments'][$event_id]['last_update'];
	}
	$data['tournaments']['last_update'] = substr($data['tournaments']['last_update'], 11, 5);
	$page['breadcrumbs'][]['title'] = 'Livepartien';
	$page['title'] = 'Livepartien '.$data['main_series_long'].' '.$data['year'];
	$page['text'] = wrap_template('livegames-series', $data['tournaments']);
	return $page;
}

function mod_tournaments_livegames_bretter($turnier) {
	$turnier['last_update'] = '';
	$turnier['bretter'] = floor($turnier['teilnehmer']/2);
	$turnier['livebretter_nos'] = mf_tournaments_live_boards(
		$turnier['livebretter'], $turnier['bretter']
	);
	$sql = 'SELECT partie_id, partien.runde_no, partien.brett_no, halbzuege
			, pgn
			, (CASE weiss_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
			END) AS weiss_ergebnis
			, (CASE schwarz_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
			END) AS schwarz_ergebnis
			, CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname) AS weiss
			, CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname) AS schwarz
			, IFNULL(weiss.t_elo, weiss.t_dwz) AS WhiteElo
			, IFNULL(schwarz.t_elo, schwarz.t_dwz) AS BlackElo
			, weiss.setzliste_no AS weiss_setzliste_no
			, schwarz.setzliste_no AS schwarz_setzliste_no
			, partien.last_update, events.identifier
		FROM partien
		LEFT JOIN events USING (event_id)
		LEFT JOIN persons white_persons
			ON partien.weiss_person_id = white_persons.person_id
		LEFT JOIN persons black_persons
			ON partien.schwarz_person_id = black_persons.person_id
		LEFT JOIN participations weiss
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = /*_ID usergroups spieler _*/
			AND weiss.event_id = partien.event_id
		LEFT JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = /*_ID usergroups spieler _*/
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partien.runde_no = %d
		AND partien.brett_no IN (%s)
		ORDER BY brett_no
	';
	$sql = sprintf($sql
		, $turnier['event_id']
		, $turnier['aktuelle_runde_no']
		, implode(',', $turnier['livebretter_nos'])
	);
	$turnier['livepaarungen'] = wrap_db_fetch($sql, 'partie_id');
	foreach ($turnier['livepaarungen'] as $partie_id => $partie) {
		if ($partie['last_update'] > $turnier['last_update']) {
			$turnier['last_update'] = $partie['last_update'];
		}
		if (!$partie['pgn']) continue;
		$pgn = preg_replace('/{\[\%clk \d+:\d+:\d+\]} /', '', $partie['pgn']);
		$pgn = preg_replace('/{\[\%emt \d+:\d+:\d+\]} /', '', $pgn);
		$pgn = explode(' ', $pgn);
		$aktuelle_zuege = array_slice($pgn, count($pgn) -10, count($pgn));
		foreach ($aktuelle_zuege as $index => $zug) {
			$aktuelle_zuege[$index] = mf_chess_pgn_translate_pieces($zug, 'de');
		}
		$turnier['livepaarungen'][$partie_id]['aktuelle_zuege'] 
			= implode(' ', $aktuelle_zuege);
	}
	return $turnier;
}

function mod_tournaments_livegames_rounds($tournament_ids) {
	// Aktuelle Runde, Daten
	$sql = 'SELECT event_id, runde_no, main_event_id,
			CASE DAYOFWEEK(events.date_begin) WHEN 1 THEN "So"
				WHEN 2 THEN "Mo"
				WHEN 3 THEN "Di"
				WHEN 4 THEN "Mi"
				WHEN 5 THEN "Do"
				WHEN 6 THEN "Fr"
				WHEN 7 THEN "Sa" END AS runde_wochentag
			, date_begin AS runde_beginn
			, TIME_FORMAT(time_begin, "%%H:%%i") AS runde_time_begin
			, TIME_FORMAT(time_end, "%%H:%%i") AS runde_time_end
		FROM events
		WHERE main_event_id IN (%s)
		AND event_category_id = /*_ID categories event/round _*/';
	$sql = sprintf($sql, implode(',', $tournament_ids));
	return wrap_db_fetch($sql, ['main_event_id', 'runde_no']);
}
