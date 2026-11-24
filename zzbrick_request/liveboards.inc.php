<?php 

/**
 * tournaments module
 * interactive boards with live games of the current round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * interactive boards with live games of all tournaments of an event
 *
 * %%% request liveboards * *=event %%%
 * @param array $params
 *		int [0]: year
 *		string [1]: event identifier
 * @param array $settings
 * @param array $data
 * @return array
 */
function mod_tournaments_liveboards($params, $settings, $data) {
	if (count($params) !== 2) return false;

	$sql = 'SELECT events.event_id, events.identifier
			, IF((DATE_SUB(CURDATE(), INTERVAL /*_SETTING tournaments_live_games_show_for_days _*/ DAY) <= events.date_end), 1, NULL) AS current
		FROM tournaments
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE main_event_id = %d
		AND NOT ISNULL(livebretter)
		ORDER BY series.sequence';
	$sql = sprintf($sql, $data['event_id']);
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) {
		$sql = 'SELECT events.event_id, events.identifier
				, IF((DATE_SUB(CURDATE(), INTERVAL /*_SETTING tournaments_live_games_show_for_days _*/ DAY) <= events.date_end), 1, NULL) AS current
			FROM tournaments
			LEFT JOIN events USING (event_id)
			WHERE tournaments.event_id = %d
			AND NOT ISNULL(livebretter)';
		$sql = sprintf($sql, $data['event_id']);
		$tournaments = wrap_db_fetch($sql, 'event_id');
	}
	if (!$tournaments) return false;

	foreach ($tournaments as $event_id => $tournament) {
		if (mf_tournaments_live_current($tournament)) continue;
		unset($tournaments[$event_id]);
	}
	if (!$tournaments) mf_tournaments_live_over();

	$urls = [];
	foreach ($tournaments as $event_id => $tournament) {
		// without a live round, live.pgn would contain all games
		if (!mf_tournaments_live_round($event_id)) continue;
		$url = wrap_path('tournaments_pgns', [$tournament['identifier'], 'live'], ['check_rights' => false]);
		if (!$url) continue;
		$urls[] = $url;
	}
	if (!$urls) return false;

	wrap_package_activate('chess');
	wrap_include('zzbrick_request/pgnviewer', 'chess');
	return mod_chess_pgnviewer($urls);
}
