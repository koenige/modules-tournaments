<?php 

/**
 * tournaments module
 * trigger PGN import
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Trigger-Funktion, die aktuell laufende Termine mit Liveübetragung sucht
 * und hier automatisch die PGNs importiert
 *
 * @param void
 * @return function
 */
function mod_tournaments_make_gamestrigger() {
	$sql = 'SELECT DISTINCT tournaments.event_id, rounds.runde_no,
			IF(rounds.date_begin >= CURDATE() AND rounds.time_begin > CURTIME(), NULL, 1) AS running
			, events.identifier
		FROM tournaments
		JOIN events USING (event_id)
		LEFT JOIN events rounds
			ON events.event_id = rounds.main_event_id
			AND rounds.event_category_id = /*_ID categories event/round _*/
		LEFT JOIN partien
			ON events.event_id = partien.event_id
			AND rounds.runde_no = partien.runde_no
		WHERE NOT ISNULL(livebretter)
		AND events.date_begin <= CURDATE() AND events.date_end >= CURDATE()
		AND NOT ISNULL(partien.partie_id)
		ORDER BY tournaments.event_id, rounds.runde_no
	';
	// in SQL-Abfrage werden alle Runden ausgegeben, wrap_db_fetch() speichert
	// aber nach event_id und durch die Sortierung wird nur die letzte Runde
	// gespeichert
	$tournaments = wrap_db_fetch($sql, 'event_id');

	foreach ($tournaments as $event_id => $tournament) {
		if (!$tournament['running']) continue;
		// @todo maybe disable next two lines to reduce server load
		$url = wrap_path('tournaments_job_games', $tournament['identifier'].'/'.$tournament['runde_no'], false);
		wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/partien')]);
		$url = wrap_path('tournaments_job_games', $tournament['identifier'].'/'.$tournament['runde_no'].'-live', false);
		wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/partien'), 'priority' => -5]);
	}
	$page['text'] = 'Update in progress';
	return $page;
}
