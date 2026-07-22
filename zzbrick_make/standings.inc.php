<?php 

/**
 * tournaments module
 * calculate standings and write them to database
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @copyright Copyright © 2012-2026 Gustaf Mossakowski
 * @copyright Copyright © 2014 Erik Kothe
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Aktualisiert den Tabellenstand im Hintergrund
 * 
 * Wenn keine Runde angegeben, aktualisiere alle Runden
 * Wenn eine Runde angegeben ist, aktualisere aktuelle Runde und darauffolgende
 *
 * @param array $vars
 *		[0]: Jahr
 *		[1]: event identifier
 *		[2]: (optional) Runde, falls nicht angegeben = 1. Runde
 * @return void
 */
function mod_tournaments_make_standings($vars) {
	wrap_setting('cache', false);

	ignore_user_abort(1);
	ini_set('max_execution_time', 60);

	if (count($vars) === 3) {
		$first = reset($vars);
		if ($first === 'uebersicht') {
			array_shift($vars);
			return mod_tournaments_make_standings_overview($vars);
		} else {
			return mod_tournaments_make_standings_round($vars);
		}
	} elseif (count($vars) === 2) {
		$vars[] = 1; // 1. Runde
		return mod_tournaments_make_standings_round($vars);
	}
	return false;
}

/**
 * Übersicht der Runden eines Turniers, erlaubt Aktualisierung ab einzelnen
 * Runden
 *
 * @param array $vars
 *		[0]: Jahr
 *		[1]: event identifier
 * @return array $page
 */
function mod_tournaments_make_standings_overview($vars) {
	if (count($vars) !== 2) return false;

	$sql = 'SELECT event_id, event, identifier
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
		FROM events
		WHERE identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	wrap_setting('log_filename', $event['identifier']);

	$sql = 'SELECT events.event_id, events.runde_no
			, (SELECT COUNT(*) FROM partien
				WHERE partien.event_id = events.main_event_id
				AND partien.runde_no = events.runde_no) AS partien
		FROM events
		WHERE main_event_id = %d
		AND NOT ISNULL(events.runde_no)
		HAVING partien > 0';
	$sql = sprintf($sql, $event['event_id']);
	$event['runden'] = wrap_db_fetch($sql, 'event_id');
	
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][]['title'] = 'Tabellenstandupdate';
	$page['title'] = 'Tabellenstandupdates '.$event['event'].' '.$event['year'];
	$page['text'] = wrap_template('standings-update', $event);
	
	if (!empty($_POST) AND is_array($_POST)) {
		$round_no = key($_POST);
		if (substr($round_no, 0, 6) === 'runde_') {
			mod_tournaments_make_standings_trigger($event['identifier'].'/'.substr($round_no, 6));
			wrap_redirect_change();
		}
	}
	return $page;
}

function mod_tournaments_make_standings_trigger($identifier) {
	$url = wrap_path('tournaments_job_standings', $identifier, ['check_rights' => false]);
	wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/tabelle')]);
}

/**
 * Aktualisierung des Tabellenstands pro Runde
 *
 * @param array $vars
 * @return bool
 */
function mod_tournaments_make_standings_round($vars) {
	$time = microtime(true);

	$round_no = 1; // beginne bei Runde 1 oder bei angegebener Runde
	if (count($vars) === 3 AND is_numeric($vars[2])) $round_no = $vars[2];
	elseif (count($vars) !== 2) return false;

	$sql = 'SELECT events.event_id, events.identifier
			, runden, bretter_min
			, tournament_id
			, categories.parameters
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_categories
			ON events_categories.event_id = events.event_id
			AND events_categories.type_category_id = /*_ID categories events _*/
		LEFT JOIN categories
			ON events_categories.category_id = categories.category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	wrap_match_module_parameters('tournaments', $event['parameters']);
	$event['rounds_played'] = mf_tournaments_live_round($event['event_id']);
	
	wrap_setting('log_filename', $event['identifier']);

	if ($round_no > $event['runden']) {
		wrap_error([
			'Standings update: Round %d/%d not possible (date %d/%s)',
			['values' => [$round_no, $event['runden'], $vars[0], $vars[1]]]
		], E_USER_WARNING);
		$page['text'] = sprintf('<p>%s</p>', wrap_text('Attempted to update a round that is higher than the maximum number of rounds.'));
		$page['status'] = 503;
		return $page;
	}
	if ($round_no > $event['rounds_played']) {
		$page['text'] = wrap_text(
			'Standings update for round %d impossible: So far only %d rounds have been played.',
			['values' => [$round_no, $event['rounds_played']]]
		);
		$page['status'] = 404;
		return $page;
	}

	// check if there were games played in this round
	$sql = 'SELECT COUNT(*)
		FROM partien
		WHERE event_id = %d AND runde_no = %d
		AND partiestatus_category_id = /*_ID categories partiestatus/normal _*/';
	$sql = sprintf($sql, $event['event_id'], $round_no);
	$games_played_in_round = wrap_db_fetch($sql, '', 'single value');
	if (!$games_played_in_round) {
		$page['text'] = wrap_text(
			'Standings update for round %d impossible: No games were played in this round.',
			['values' => [$round_no, $event['rounds_played']]]
		);
		$page['status'] = 404;
		return $page;
	}

	$type = implode('/', $vars);
	wrap_setting('log_username', wrap_setting('default_robot_username'));

	$handle = mod_tournaments_make_standings_round_lock($vars, true);
	if (!$handle) {
		wrap_error(['Standings update: parallel run blocked for %s round %d', ['values' => [$event['identifier'], $round_no]]], E_USER_NOTICE);
		$page['text'] = wrap_text(
			'Standings update for tournament %s, round %d already in progress.',
			['values' => [$event['identifier'], $round_no]]
		);
		$page['status'] = 403;
		return mod_tournaments_make_standings_return($page, $time, $type);
	}
	$page = mod_tournaments_make_standings_round_run($event, $round_no, $time, $type);
	wrap_flock_release($handle);
	return $page;
}

/**
 * calculate and write standings for one round (caller holds the lock)
 *
 * @param array $event
 * @param int $round_no
 * @param float $time microtime(true) at start of request
 * @param string $type log label
 * @return array|false
 */
function mod_tournaments_make_standings_round_run($event, $round_no, $time, $type) {
	if (wrap_setting('tournaments_type_single')) {
		require_once __DIR__.'/standings-single.inc.php';
		$tabelle = mod_tournaments_make_standings_calculate_single($event, $round_no);
		if (!$tabelle)
			return mod_tournaments_make_standings_return(false, $time, $type);
		$success = mod_tournaments_make_standings_write_single($event['event_id'], $round_no, $tabelle);
		if (!$success)
			return mod_tournaments_make_standings_return(false, $time, $type);
		$changes = 1;
	} else {
		$event['runde_no'] = $round_no;
		require_once __DIR__.'/standings-team.inc.php';
		$changes = mod_tournaments_make_standings_team($event);
	}
	if ($round_no < $event['rounds_played'])
		mod_tournaments_make_standings_trigger($event['identifier'].'/'.($round_no + 1));
	
	// Aktuelle runde_no von Tabellenstand speichern
	// aus Performancegründen
	$sql = 'SELECT MAX(runde_no) FROM standings WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$max_runde_no = wrap_db_fetch($sql, '', 'single value');
	$line = [
		'tournament_id' => $event['tournament_id'],
		'tabellenstand_runde_no' => $max_runde_no
	];
	zzform_update('tournaments', $line);

	if ($changes)
		$page['text'] = wrap_text('Standings for tournament %s, round %d have been successfully updated.', ['values' => [$event['identifier'], $round_no]]);
	else
		$page['text'] = wrap_text('Standings for tournament %s, round %d: no updates necessary.', ['values' => [$event['identifier'], $round_no]]);
	return mod_tournaments_make_standings_return($page, $time, $type);
}

/**
 * exclusive lock for one tournament round standings update (calculate + write)
 *
 * @param array $vars year, event identifier, round no
 * @param bool $non_blocking true: LOCK_NB (return false if already locked)
 * @return resource|false handle on success, false if another run holds the lock
 */
function mod_tournaments_make_standings_round_lock($vars, $non_blocking = true) {
	$module_lock_dir = sprintf('%s/tournaments', wrap_setting('tmp_dir'));
	wrap_mkdir($module_lock_dir);
	$lock_path = sprintf(
		'%s/standings-%s.lock',
		$module_lock_dir,
		preg_replace('/[^a-zA-Z0-9._-]+/', '-', implode('-', $vars))
	);
	return wrap_flock_acquire($lock_path, $non_blocking);
}

function mod_tournaments_make_standings_return($page, $time, $type) {
	$time = microtime(true) - $time;
	if ($time < 1) return $page; // do not log if it's fast enough
	wrap_log(sprintf('Tabellenstand %s in %s sec erstellt.', $type, $time));
	return $page;
}

/**
 * Get all score categories for a tournament
 * 
 * @param int $event_id
 * @return array
 */
function mod_tournaments_make_standings_score_categories($event_id) {
	$sql = 'SELECT category_id, category, category_short
			, SUBSTRING_INDEX(REPLACE(path, "-", "_"), "/", -1) AS path, display
		FROM tournaments_scores
		JOIN tournaments USING (tournament_id)
		JOIN categories
			ON tournaments_scores.score_category_id = categories.category_id
		WHERE event_id = %d
		ORDER BY tournaments_scores.sequence, categories.sequence';
	$sql = sprintf($sql, $event_id);
	return wrap_db_fetch($sql, 'category_id');
}

/**
 * Schreibt Wertungen in Tabellen-Array und ermittelt Platz aus $scores
 * anzeige von Wertungen nur, wenn zur Differenzierung nötig
 *
 * @param array $event
 * @param array $standings
 * @param array $scores
 * @param array $score_categories
 * @return array
 */
function mod_tournaments_make_standings_prepare($event, $standings, $scores, $score_categories) {
	$tsw = [];
	foreach (array_keys($score_categories) as $category_id) {
		if (empty($scores[$category_id])) continue;
		if (!is_array($scores[$category_id])
			AND $category_id.'' === wrap_category_id('scores/dv').'') {
			$scores[$category_id] = mf_tournaments_make_team_direct_encounter(
				$event, $standings, reset($score_categories)
			);
		}
		$last_tn_id = [];
		$last_score = [];
		$increment = [];
		$increment[1] = 1;
		foreach ($scores[$category_id] as $tn_id => $score) {
			// Aktueller Stand in dieser Wertungsschleife?
			if (!isset($standings[$tn_id]['rank_no'])) {
				$standings[$tn_id]['rank_no'] = 1;
			}
			if ($score_categories[$category_id]['display'] === 'always') {
				$standings[$tn_id]['scores'][$category_id]['score'] = $score;
				$standings[$tn_id]['scores'][$category_id]['score_category_id'] = $category_id;
			}
			// Wertung nicht in allgemeines Array schreiben, da das nicht ausgegeben werden soll:
			$tsw[$tn_id][$category_id] = $score;
			if (!empty($standings[$tn_id]['eindeutig'])) {
				// Platz-Nr. bei eindeutigen Verhältnissen korrekt, keine weitere
				// Bearbeitung nötig
				continue;
			}
			$stand = $standings[$tn_id]['rank_no'];
			if (empty($last_tn_id[$stand])) {
				$standings[$tn_id]['eindeutig'] = true;
				if (!isset($increment[$stand])) $increment[$stand] = 1;
			} elseif (isset($tsw[$last_tn_id[$stand]][$category_id])
				AND $score === $tsw[$last_tn_id[$stand]][$category_id]) {
				$standings[$tn_id]['rank_no'] = $standings[$last_tn_id[$stand]]['rank_no'];
				$standings[$last_tn_id[$stand]]['eindeutig'] = false;
				$standings[$tn_id]['eindeutig'] = false;
				if ($score_categories[$category_id]['display'] !== 'always') {
					$standings[$tn_id]['scores'][$category_id]['score'] = $score;
					$standings[$tn_id]['scores'][$category_id]['score_category_id'] = $category_id;
					$standings[$last_tn_id[$stand]]['scores'][$category_id]['score'] = $last_score[$stand];
					$standings[$last_tn_id[$stand]]['scores'][$category_id]['score_category_id'] = $category_id;
				}
				if (!isset($increment[$stand])) $increment[$stand] = 1;
				else $increment[$stand]++;
			} else {
				if (!isset($increment[$stand])) $increment[$stand] = 1;
				$standings[$tn_id]['rank_no'] = $standings[$last_tn_id[$stand]]['rank_no'] + $increment[$stand];
				$increment[$stand] = 1;
				$standings[$tn_id]['eindeutig'] = true;
				if ($score_categories[$category_id]['display'] !== 'always') {
					$standings[$tn_id]['scores'][$category_id]['score'] = $score;
					$standings[$tn_id]['scores'][$category_id]['score_category_id'] = $category_id;
					$standings[$last_tn_id[$stand]]['scores'][$category_id]['score'] = $last_score[$stand];
					$standings[$last_tn_id[$stand]]['scores'][$category_id]['score_category_id'] = $category_id;
				}
			}
			$last_tn_id[$stand] = $tn_id;
			$last_score[$stand] = $score;
		}
	}
	$standings = mod_tournaments_make_standings_sort($standings);
	return $standings;
}

/**
 * Teams nach ihrem aktuellen Tabellenstand sortieren
 *
 * @param array $standings
 * @return array (sortiert nach rank_no)
 */
function mod_tournaments_make_standings_sort($standings) {
	foreach ($standings as $participant_id => $values) {
		if (!is_numeric($participant_id)) continue;
		$places[$participant_id] = $values['rank_no'];
	}
	array_multisort($places, SORT_ASC, $standings);
	return $standings;
}

/**
 * Setze FIDE-Regelung für nicht gespielte Partien nach Datum des Beginns
 * eines Turniers
 *
 * @param int $event_id
 * @return string
 *		NULL => Punkte werden als Punkte gewertet
 *		'fide-2009' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/a
 *			für Turnier nach FIDE-Kongreß (?) = 2009-10-18
 *		'fide-2012' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/b
 *			für Turniere nach 2012-07-01
 */
function mf_tournaments_make_fide_correction($event_id) {
	static $correction = [];
	if (array_key_exists($event_id, $correction)) return $correction[$event_id];
	$sql = 'SELECT
			IF(date_begin >= "2012-07-01", "fide-2012",
				IF(date_begin >= "2009-10-18", "fide-2009", NULL))
		FROM events
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$correction[$event_id] = wrap_db_fetch($sql, '', 'single value');
	return $correction[$event_id];
}
