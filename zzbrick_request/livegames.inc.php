<?php 

/**
 * tournaments module
 * Overview of live games
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2016, 2018-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht der Live-Partien
 *
 * @param array $vars
 *		int [0]: Jahr
 *		string [1]: Terminkennung
 *		(string [2]: (optional) 'live')
 * @return array
 */
function mod_tournaments_livegames($vars) {
	global $zz_setting;
	require_once $zz_setting['modules_dir'].'/chess/chess/pgn.inc.php';

	if (count($vars) !== 2) return false;

	// alle Turniere der Reihe ausgeben
	$sql = 'SELECT events.event_id, livebretter, events.identifier
			, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, (SELECT COUNT(*) FROM participations
			WHERE participations.event_id = tournaments.event_id
			AND usergroup_id = %d) AS teilnehmer
			, (SELECT MAX(runde_no) FROM partien
			WHERE partien.event_id = tournaments.event_id) AS aktuelle_runde_no
			, main_series.category AS main_series
			, IF((DATE_SUB(CURDATE(), INTERVAL %d DAY) <= date_end), 1, NULL) AS current
		FROM tournaments
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(event_year, YEAR(date_begin)) = %d
		AND NOT ISNULL(livebretter)
		ORDER BY series.sequence';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_get_setting('live_games_show_for_days'),
		wrap_db_escape($vars[1]), $vars[0]
	);
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if ($tournaments) return mod_tournaments_livegames_series($tournaments);
	
	// Einzelnes Turnier?
	$sql = 'SELECT events.event_id, livebretter, events.identifier
			, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, (SELECT COUNT(*) FROM participations
			WHERE participations.event_id = tournaments.event_id
			AND usergroup_id = %d) AS teilnehmer
			, (SELECT MAX(runde_no) FROM partien
			WHERE partien.event_id = tournaments.event_id) AS aktuelle_runde_no
			, main_series.category AS main_series
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
		FROM tournaments
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE events.identifier = "%d/%s"
		AND NOT ISNULL(livebretter)';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		$vars[0], wrap_db_escape($vars[1])
	);
	$turnier = wrap_db_fetch($sql);
	if ($turnier) return mod_tournaments_livegames_turnier($turnier);
	return false;
}

function mod_tournaments_livegames_turnier($turnier) {
	$rundendaten = mod_tournaments_livegames_rundendaten([$turnier['event_id']]);
	unset($rundendaten[$turnier['event_id']][$turnier['aktuelle_runde_no']]['runde_no']);
	unset($rundendaten[$turnier['event_id']][$turnier['aktuelle_runde_no']]['main_event_id']);
	$turnier += $rundendaten[$turnier['event_id']][$turnier['aktuelle_runde_no']];
	unset($rundendaten);

	$turnier = mod_tournaments_livegames_bretter($turnier);
	$turnier['last_update'] = substr($turnier['last_update'], 11, 5);
	
	$page['breadcrumbs'][] = '<a href="../../">'.$turnier['year'].'</a>';
	if ($turnier['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$turnier['main_series_path'].'/">'.$turnier['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$turnier['event'].'</a>';
	$page['breadcrumbs'][] = 'Livepartien';
	$page['title'] = 'Livepartien '.$turnier['event'].' '.$turnier['year'];
	$page['head'] = wrap_template('livegames-head');
	$page['text'] = wrap_template('livegames-tournament', $turnier);
	return $page;
}

function mod_tournaments_livegames_series($tournaments) {
	$series = reset($tournaments);

	$rundendaten = mod_tournaments_livegames_rundendaten(array_keys($tournaments));
	$has_rounds = false;
	foreach ($tournaments AS $event_id => $turnier) {
		if (empty($turnier['aktuelle_runde_no'])) continue;
		$has_rounds = true;
		if (!$turnier['current']) {
			unset($tournaments[$event_id]);
			continue;
		}
		unset($rundendaten[$event_id][$turnier['aktuelle_runde_no']]['runde_no']);
		unset($rundendaten[$event_id][$turnier['aktuelle_runde_no']]['main_event_id']);
		if (!empty($rundendaten[$event_id])) {
			$tournaments[$event_id] += $rundendaten[$event_id][$turnier['aktuelle_runde_no']];
		}
	}
	if (!$has_rounds) return false;
	if (!$tournaments) wrap_quit(410, wrap_text('The tournament is over. All live games are integrated into the <a href="../">tournament pages</a>.'));

	$tournaments['last_update'] = '';
	foreach ($tournaments as $event_id => $turnier) {
		if (!is_numeric($event_id)) continue;
		$tournaments[$event_id] = mod_tournaments_livegames_bretter($turnier);
		if ($tournaments[$event_id]['last_update'] > $tournaments['last_update'])
			$tournaments['last_update'] = $tournaments[$event_id]['last_update'];
	}
	$tournaments['last_update'] = substr($tournaments['last_update'], 11, 5);
	$page['breadcrumbs'][] = '<a href="../../">'.$series['year'].'</a>';
	$page['breadcrumbs'][] = '<a href="../">'.$series['main_series'].'</a>';
	$page['breadcrumbs'][] = 'Livepartien';
	$page['title'] = 'Livepartien '.$series['main_series'].' '.$series['year'];
	$page['text'] = wrap_template('livegames-series', $tournaments);
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
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
			END) AS weiss_ergebnis
			, (CASE schwarz_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
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
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = %d
			AND weiss.event_id = partien.event_id
		LEFT JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = %d
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partien.runde_no = %d
		AND partien.brett_no IN (%s)
		ORDER BY brett_no
	';
	$sql = sprintf($sql
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_id('usergroups', 'spieler')
		, wrap_id('usergroups', 'spieler')
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

function mod_tournaments_livegames_rundendaten($tournament_ids) {
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
		AND event_category_id = %d';
	$sql = sprintf($sql, implode(',', $tournament_ids),
		wrap_category_id('zeitplan/runde'));
	$rundendaten = wrap_db_fetch($sql, ['main_event_id', 'runde_no']);
	return $rundendaten;
}
