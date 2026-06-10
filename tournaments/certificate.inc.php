<?php 

/**
 * tournaments module
 * certificate functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * tournament data for certificates
 *
 * @param array $event
 * @return array merged $event
 */
function mf_tournaments_certificate($event) {
	$sql = 'SELECT runden
			, tournaments.tabellenstaende, alter_max AS age_max
			, IF(tournaments.geschlecht = "w", 1, NULL) AS weiblich
		FROM tournaments
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event += wrap_db_fetch($sql);

	if (!empty($event['tournament_parameter'])) {
		parse_str($event['tournament_parameter'], $parameter);
		$event += $parameter;
	}
	unset($event['tournament_parameter']);
	return $event;
}

/**
 * check if event is a tournament
 *
 * @param array $event
 * @return bool
 */
function mf_tournaments_certificate_applies($event) {
	if (wrap_setting('tournaments_type_single')) return true;
	if (wrap_setting('tournaments_type_team')) return true;
	return false;
}

/**
 * add to list of possible certificate types
 *
 * @param array $event
 * @return array
 */
function mf_tournaments_certificate_types($event) {
	$possible_types = ['platz'];
	if (!$event['tabellenstaende']) return $possible_types;

	// @todo currently, only 'w' for female is supported
	$tabellenstaende = explode(',', $event['tabellenstaende']);
	foreach ($tabellenstaende as $tabellenstand) {
		if (!$tabellenstand) continue;
		$possible_types[] = 'platz-'.$tabellenstand;
	}
	return $possible_types;
}
