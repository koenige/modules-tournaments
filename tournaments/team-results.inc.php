<?php 

/**
 * tournaments module
 * team match results for standings
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Team results per round (one row per team per pairing), cached per request
 *
 * @param int $event_id
 * @param int|null $round_no maximum round number inclusive; null = all rounds
 * @return array [team_id][runde_no] rows with keys event_id, paarung_id, team_id,
 *		gegner_team_id, runde_no, board_points, board_points_opponent,
 *		match_points, match_points_opponent, is_pairing_bye
 */
function mf_tournaments_team_results($event_id, $round_no = null) {
	static $cache = [];

	$key = $event_id.'-'.($round_no ?? '');
	if (array_key_exists($key, $cache)) return $cache[$key];

	$sql = 'SELECT bretter_min, pairing_bye_scoring
		FROM tournaments
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$tournament = wrap_db_fetch($sql, '', 'record');

	$sql = 'SELECT paarungen.paarung_id
			, paarungen.event_id
			, paarungen.runde_no
			, paarungen.heim_team_id
			, paarungen.auswaerts_team_id
			, IF(home_teams.spielfrei = "ja", 1, NULL) AS home_bye
			, IF(away_teams.spielfrei = "ja", 1, NULL) AS away_bye
			, SUM(partien.heim_wertung) AS home_board_points
			, SUM(partien.auswaerts_wertung) AS away_board_points
		FROM paarungen
		LEFT JOIN teams home_teams
			ON paarungen.heim_team_id = home_teams.team_id
		LEFT JOIN teams away_teams
			ON paarungen.auswaerts_team_id = away_teams.team_id
		LEFT JOIN partien
			ON partien.paarung_id = paarungen.paarung_id
		WHERE paarungen.event_id = %d';
	$sql = sprintf($sql, $event_id);
	if ($round_no !== null) {
		$sql .= sprintf(' AND paarungen.runde_no <= %d', $round_no);
	}
	$sql .= ' GROUP BY paarung_id ORDER BY paarungen.runde_no, paarung_id';
	$pairings = wrap_db_fetch($sql, 'paarung_id');
	if (!$pairings) {
		$cache[$key] = [];
		return [];
	}

	$results = [];
	foreach ($pairings as $pairing) {
		$rows = mf_tournaments_team_result_rows($pairing, $tournament);
		foreach ($rows as $row) {
			$results[$row['team_id']][$row['runde_no']] = $row;
		}
	}
	return $cache[$key] = $results;
}

/**
 * Result rows per team for one pairing (played match or pairing-allocated bye)
 *
 * @param array $pairing paarung row with board-point sums and home_bye / away_bye flags
 * @param array $tournament bretter_min, pairing_bye_scoring
 * @return array list of result rows (one for bye, two for a played pairing)
 */
function mf_tournaments_team_result_rows($pairing, $tournament) {
	if ($pairing['home_bye'] || $pairing['away_bye']) {
		if ($tournament['pairing_bye_scoring'] === 'none') return [];
		if ($pairing['home_bye']) {
			$team_id = $pairing['auswaerts_team_id'];
			$opponent_team_id = $pairing['heim_team_id'];
		} else {
			$team_id = $pairing['heim_team_id'];
			$opponent_team_id = $pairing['auswaerts_team_id'];
		}
		if ($tournament['pairing_bye_scoring'] === 'draw') {
			$board_points = $tournament['bretter_min'] / 2;
			$match_points = 1;
		} else {
			$board_points = $tournament['bretter_min'];
			$match_points = 2;
		}
		return [
			[
				'event_id' => $pairing['event_id'],
				'paarung_id' => $pairing['paarung_id'],
				'team_id' => $team_id,
				'gegner_team_id' => $opponent_team_id,
				'runde_no' => $pairing['runde_no'],
				'board_points' => $board_points,
				'board_points_opponent' => 0,
				'match_points' => $match_points,
				'match_points_opponent' => 0,
				'is_pairing_bye' => true,
			],
		];
	}

	$home_board_points = (float) ($pairing['home_board_points'] ?? 0);
	$away_board_points = (float) ($pairing['away_board_points'] ?? 0);
	$home_match_points = mf_tournaments_team_match_points(
		$home_board_points,
		$away_board_points
	);
	$away_match_points = mf_tournaments_team_match_points(
		$away_board_points,
		$home_board_points
	);

	return [
		[
			'event_id' => $pairing['event_id'],
			'paarung_id' => $pairing['paarung_id'],
			'team_id' => $pairing['heim_team_id'],
			'gegner_team_id' => $pairing['auswaerts_team_id'],
			'runde_no' => $pairing['runde_no'],
			'board_points' => $home_board_points,
			'board_points_opponent' => $away_board_points,
			'match_points' => $home_match_points,
			'match_points_opponent' => $away_match_points,
			'is_pairing_bye' => false,
		],
		[
			'event_id' => $pairing['event_id'],
			'paarung_id' => $pairing['paarung_id'],
			'team_id' => $pairing['auswaerts_team_id'],
			'gegner_team_id' => $pairing['heim_team_id'],
			'runde_no' => $pairing['runde_no'],
			'board_points' => $away_board_points,
			'board_points_opponent' => $home_board_points,
			'match_points' => $away_match_points,
			'match_points_opponent' => $home_match_points,
			'is_pairing_bye' => false,
		],
	];
}

/**
 * Match points from board points (team tournament: 2 / 1 / 0)
 *
 * @param float $board_points
 * @param float $opponent_board_points
 * @return int 0, 1, or 2
 */
function mf_tournaments_team_match_points($board_points, $opponent_board_points) {
	if ($board_points < $opponent_board_points) return 0;
	if ($board_points > $opponent_board_points) return 2;
	return 1;
}

/**
 * Tiebreak rating per active team (sum of per-pairing values up to a round)
 *
 * Dispatches to separate helper function per pairing row, then sorts.
 *
 * @param int $event_id
 * @param int $round_no maximum round number inclusive
 * @param string $path
 * @return array team_id => rating, sorted rating DESC, team_id ASC
 */
function mf_tournaments_team_score($event_id, $round_no, $path) {
	$team_ids = mf_tournaments_active_team_ids($event_id);
	$results = mf_tournaments_team_results($event_id, $round_no);
	$ratings = [];
	foreach (array_keys($team_ids) as $team_id) {
		$ratings[$team_id] = 0;
	}
	$function = sprintf('mf_tournaments_team_score_%s', $path);
	foreach ($results as $team_id => $rounds) {
		if (!isset($ratings[$team_id])) continue;
		foreach ($rounds as $row) {
			$ratings[$team_id] += $function($row);
		}
	}
	return mf_tournaments_team_score_sort($ratings);
}

/**
 * Match points from one team result row (mf_tournaments_team_score() helper)
 *
 * @param array $row team result row from mf_tournaments_team_results()
 * @return int|float match_points for that pairing
 */
function mf_tournaments_team_score_mp($row) {
	return $row['match_points'];
}

/**
 * Board points from one team result row (mf_tournaments_team_score() helper)
 *
 * @param array $row team result row from mf_tournaments_team_results()
 * @return int|float board_points for that pairing
 */
function mf_tournaments_team_score_bp($row) {
	return $row['board_points'];
}

/**
 * Match wins / draws / losses per participant team up to a round
 *
 * @param int $event_id
 * @param int $round_no
 * @return array [team_id => ['wins' => int, 'draws' => int, 'losses' => int]]
 */
function mf_tournaments_team_score_wdl($event_id, $round_no) {
	$team_ids = mf_tournaments_active_team_ids($event_id);
	$results = mf_tournaments_team_results($event_id, $round_no);
	$wdl = [];
	foreach (array_keys($team_ids) as $team_id) {
		$wdl[$team_id] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
	}
	foreach ($results as $team_id => $rounds) {
		if (!isset($wdl[$team_id])) {
			continue;
		}
		foreach ($rounds as $row) {
			if ($row['match_points'] === 2) {
				$wdl[$team_id]['wins']++;
			} elseif ($row['match_points'] === 1) {
				$wdl[$team_id]['draws']++;
			} else {
				$wdl[$team_id]['losses']++;
			}
		}
	}
	return $wdl;
}

/**
 * @param int $event_id
 * @param int $round_no
 * @return array team_id => wins
 */
function mf_tournaments_team_score_wins($event_id, $round_no) {
	$wdl = mf_tournaments_team_score_wdl($event_id, $round_no);
	$wins = [];
	foreach ($wdl as $team_id => $record) {
		$wins[$team_id] = $record['wins'];
	}
	return mf_tournaments_team_score_sort($wins);
}

/**
 * Active team ids for an event (Teilnehmer, not spielfrei), cached per request
 *
 * @param int $event_id
 * @return array team_id => team_id
 */
function mf_tournaments_active_team_ids($event_id) {
	static $cache = [];

	if (!array_key_exists($event_id, $cache)) {
		$sql = 'SELECT team_id
			FROM teams
			WHERE event_id = %d
			AND team_status = "Teilnehmer"
			AND spielfrei = "nein"';
		$sql = sprintf($sql, $event_id);
		$cache[$event_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
	}
	return $cache[$event_id];
}

/**
 * Sort team ratings for tiebreak queries (rating DESC, team_id ASC)
 *
 * @param array $ratings team_id => rating
 * @return array same array, reordered
 */
function mf_tournaments_team_score_sort($ratings) {
	uksort($ratings, function ($team_a, $team_b) use ($ratings) {
		if ($ratings[$team_a] != $ratings[$team_b]) {
			return $ratings[$team_b] <=> $ratings[$team_a];
		}
		return $team_a <=> $team_b;
	});
	return $ratings;
}
