<?php 

/**
 * tournaments module
 * calculate standings for team tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @copyright Copyright © 2012-2024, 2026 Gustaf Mossakowski
 * @copyright Copyright © 2014 Erik Kothe
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Aktualisiere den Tabellenstand einer Runde eines Mannschaftsturniers
 *
 * @param array $event [event_id, runde_no, bretter_min, identifier]
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function mod_tournaments_make_standings_team($event) {
	wrap_include('team-results', 'tournaments');

	$wdl = mf_tournaments_team_score_wdl($event['event_id'], $event['runde_no']);

	$sql = 'SELECT teams.team_id
			, events.event_id
			, (SELECT COUNT(website_id) FROM events_websites WHERE events_websites.event_id = events.event_id) AS veroeffentlicht
		FROM events
		JOIN teams USING (event_id)
		WHERE events.event_id = %d
		AND spielfrei = "nein"
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND team_status = "Teilnehmer"
		HAVING veroeffentlicht > 0
	';
	$sql = sprintf($sql, $event['event_id']);
	$standings = wrap_db_fetch($sql, 'team_id');
	if (!$standings) return false;

	foreach ($standings as $team_id => $standing) {
		$standings[$team_id]['games_won'] = $wdl[$team_id]['wins'] ?? 0;
		$standings[$team_id]['games_drawn'] = $wdl[$team_id]['draws'] ?? 0;
		$standings[$team_id]['games_lost'] = $wdl[$team_id]['losses'] ?? 0;
		$standings[$team_id]['runde_no'] = $event['runde_no'];
	}

	$score_categories = mod_tournaments_make_standings_score_categories($event['event_id']);
	$scores = [];

	// Wertungen aus Datenbank auslesen
	foreach ($score_categories as $category_id => $score_category) {
		if (str_starts_with($score_category['path'], 'bhz')) {
			$first_score_category = reset($score_categories);
			if ($first_score_category['path'] === 'bp')
				$score_category['path'] .= '_bp';
			else
				$score_category['path'] .= '_mp';
			if (mf_tournaments_make_fide_correction($event['event_id']) === 'fide-2012')
				$score_category['path'] .= '_fide2012';
			// @todo check if there’s a correction for board points as well
			// Swiss-Chess says no, so we remove it here:
			if (str_ends_with($score_category['path'], '_bp_fide2012'))
				$score_category['path'] = substr($score_category['path'], 0, -strlen('_fide2012'));
		}
		
		switch ($score_category['path']) {
		case 'dv':
			// direkter Vergleich erst nach Auswertung der anderen Wertungen
			$scores[$category_id] = 1;
			break;
		case 'rg':
			$sql = wrap_sql_query('tournaments_scores_team_rg', 'standings');
			$sql = sprintf($sql, implode(',', array_keys($standings)));
			$scores[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
			break;
		case 'sw':
		case 'mp':
		case 'bp':
		case 'bhz_mp':
		case 'bhz_bp':
		case 'bhz_bp_fide2012':
		case 'bhz_mp_fide2012':
		case 'sobo':
			$scores[$category_id] = mf_tournaments_team_score($event['event_id'], $event['runde_no'], $score_category['path']);
			break;
		case 'bw':
			$sql = wrap_sql_query('tournaments_scores_team_bw', 'standings');
			$sql = sprintf($sql, $event['event_id'], $event['event_id'], $event['runde_no']);
			$scores[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
			break;
		default:
			if ($sql = wrap_sql_query('tournaments_scores_team_'.$score_category['path'], 'standings')) {
				$sql = sprintf($sql, $event['runde_no']);
				$scores[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
				break;
			}
			wrap_error(wrap_text('Score %s not implemented.', ['values' => [$score_category['path']]]), E_USER_WARNING);
		}

		if ($score_category['display'] === 'always') {
			// Vor der 1. Runde kann es sein, dass Mannschafts- und Brettpunkte
			// für einzelne Teams noch nicht gesetzt sind, da es noch keine Ergebnisse
			// gibt, dann auf 0 setzen
			// @todo Achtung: das ist nicht immer 100% korrekt, da theoretisch
			// auch Wertungen vorne stehen könnten, bei denen nicht 0 der geringste
			// Wert ist.
			if (!array_key_exists($category_id, $scores)) {
				$scores[$category_id] = [];
			}
			foreach (array_keys($standings) as $team_id) {
				if (!is_array($scores[$category_id])) continue; // direct encounter
				if (!array_key_exists($team_id, $scores[$category_id])) {
					$scores[$category_id][$team_id] = 0;
				} elseif (empty($scores[$category_id][$team_id])) {
					$scores[$category_id][$team_id] = 0;
				}
			}
		}
	}

	$standings = mod_tournaments_make_standings_prepare($event, $standings, $scores, $score_categories);

	$sql = 'SELECT team_id, standing_id
		FROM standings
		WHERE event_id = %d AND runde_no = %d AND NOT ISNULL(team_id)';
	$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	$existing_standings = wrap_db_fetch($sql, '_dummy_', 'key/value');

	foreach ($standings as $stand) {
		if (!array_key_exists('team_id', $stand)) {
			wrap_error(wrap_text(
				'Tournament with event_id %d has invalid standings ID %d, field team_id is empty.',
				['values' => [$event['event_id'], $stand['standing_id']]]
			));
			continue;
		}
		$unwanted_keys = [
			'dwz_schnitt', 'eindeutig'
		];
		foreach ($unwanted_keys as $key)
			unset($stand[$key]);

		$line = $stand;
		$line['event_id'] = $event['event_id'];
		$line['runde_no'] = $event['runde_no'];
		if (!empty($existing_standings[$stand['team_id']])) {
			$line['standing_id'] = $existing_standings[$stand['team_id']];
			// überflüssige Tabellenstände löschen
			// @todo irgendwann so etwas direkt in zzform mit Funktion lösen
			// (alle anderen Datensätze, die nicht aktualisiert werden, löschen)
			$sql = 'SELECT * FROM
				standings_scores
				WHERE standing_id = %d';
			$sql = sprintf($sql, $existing_standings[$stand['team_id']]);
			$data = wrap_db_fetch($sql, 'standing_score_id');
			foreach ($data as $standing_score_id => $existing_score) {
				if (in_array($existing_score['score_category_id'], array_keys($stand['scores']))) continue;
				$line['scores'][] = [
					'standing_score_id' => $existing_score['standing_score_id'],
					'score_category_id' => '',
					'score' => ''
				];
			}
			zzform_update('standings', $line, E_USER_ERROR);
		} else {
			zzform_insert('standings', $line, E_USER_ERROR);
		}
	}
}

/**
 * Direkten Vergleich für Teams auswerten
 *
 * Direct Encounter
 * http://www.fide.com/component/handbook/?id=20&view=category
 *
 * If all the tied players have met each other, the sum of points from
 * these encounters is used. The player with the highest score is
 * ranked number 1 and so on. If some but not all have played each
 * other, the player with a score that could not be equalled by any
 * other player (if all such games had been played) is ranked number 1
 * and so on.
 *
 * @param array $event
 * @param array $standings
 * @param array $primary_score_category
 * @return array int team_id => string score
 */
function mf_tournaments_make_team_direct_encounter($event, $standings, $primary_score_category) {
	switch ($primary_score_category['category_id']) {
	case wrap_category_id('scores/mp'):
		$score_kind = 'mp';
		break;
	default:
	case wrap_category_id('scores/bp'):
		$score_kind = 'bp';
		break;
	}

	$teams = [];
	$unklar = [];
	
	foreach ($standings as $team_id => $standing) {
		if (!empty($standing['eindeutig'])) continue;
		$index = isset($standing['rank_no']) ? $standing['rank_no'] : 0;
		$unklar[$index][] = $team_id;
	}
	if (!$unklar) return [];

	$unentschieden = $event['bretter_min'] / 2;

	foreach ($unklar as $team_ids) {
		$sql = 'SELECT paarung_id
				, heim_team_id, auswaerts_team_id
				, SUM(heim_wertung) AS heim_bp
				, SUM(auswaerts_wertung) AS auswaerts_bp
				, IF(SUM(heim_wertung) > %1.1f, 2, IF(SUM(heim_wertung) = %1.1f, 1, 0)) AS heim_mp
				, IF(SUM(auswaerts_wertung) > %1.1f, 2, IF(SUM(auswaerts_wertung) = %1.1f, 1, 0)) AS auswaerts_mp
			FROM paarungen
			LEFT JOIN partien USING (paarung_id)
			WHERE heim_team_id IN (%s)
			AND auswaerts_team_id IN (%s)
			AND paarungen.runde_no <= %d
			GROUP BY paarung_id
		';
		$sql = sprintf($sql,
			$unentschieden, $unentschieden, $unentschieden, $unentschieden,
			implode(',', $team_ids), implode(',', $team_ids), $event['runde_no']
		);
		$paarungen = wrap_db_fetch($sql, 'paarung_id');
		$punkte = [];

		// Tatsächliche Punkte
		foreach ($team_ids as $team_id) {
			$punkte[$team_id]['paarungen'] = 0;
			$punkte[$team_id]['punkte'] = 0;
			foreach ($paarungen as $paarung) {
				if ($team_id == $paarung['heim_team_id']) {
					if (!empty($paarung['heim_'.$score_kind]))
						$punkte[$team_id]['punkte'] += $paarung['heim_'.$score_kind];
					$punkte[$team_id]['paarungen']++;
				} elseif ($team_id == $paarung['auswaerts_team_id']) {
					if (!empty($paarung['auswaerts_'.$score_kind]))
						$punkte[$team_id]['punkte'] += $paarung['auswaerts_'.$score_kind];
					$punkte[$team_id]['paarungen']++;
				}
			}
		}

		// Maximal mögliche Punkte
		$moegliche_punkte = [];
		$tatsaechliche_punkte = [];
		foreach ($punkte as $team_id => $tp) {
			$tatsaechliche_punkte[$team_id] = $tp['punkte'];
			if ($tp['paarungen'] < count($team_ids) - 1) {
				$diff = count($team_ids) - 1 - $tp['paarungen'];
				if ($score_kind === 'mp') {
					$punkte[$team_id]['punkte_max'] = $tp['punkte'] + $diff * 2;
				} else {
					$punkte[$team_id]['punkte_max'] = $tp['punkte'] + $diff * $event['bretter_min'];
				}
				$moegliche_punkte[$team_id] = $punkte[$team_id]['punkte_max'];
			}
		}
		asort($tatsaechliche_punkte);
		if (empty($moegliche_punkte)) {
			// jeder hat untereinander gegen jede gespielt
			$teams += $tatsaechliche_punkte;
		} else {
			// nicht alle haben gegeneinander gespielt
			// Auswertung, wird so interpretiert, dass nur von oben weg platziert
			// werden kann, sobald der erste Platz uneindeutig ist, wird nicht
			// mehr weitergewertet
			asort($moegliche_punkte);
			$betrachtete_teams = $tatsaechliche_punkte;
			$letztes_team = false;
			$letzte_punkte = false;
			$stop = false;
			foreach ($betrachtete_teams as $team_id => $punkte) {
				if ($stop) {
					$teams[$letztes_team] = 'n. a.';
				} elseif ($letzte_punkte === '') {
					$teams[$letztes_team] = '-';
				} elseif ($letzte_punkte !== false) {
					// falls punktgleich, wird hier abgebrochen
					if ($punkte === $letzte_punkte) {
						$stop = true;
						$teams[$letztes_team] = 'n. a.';
					} else {
						// check, ob jemand mehr Punkte erreichen kann
						foreach ($moegliche_punkte as $m_team_id => $m_punkte) {
							if ($m_punkte >= $letzte_punkte) {
								$stop = true;
								$teams[$letztes_team] = 'n. a.';
							}
						}
						if (!$stop) {
							$teams[$letztes_team] = '('.$letzte_punkte.')';
						}
					}
				}
				$letztes_team = $team_id;
				$letzte_punkte = $punkte;
				unset($moegliche_punkte[$team_id]);
			}
			if ($stop) {
				$teams[$letztes_team] = 'n. a.';
			} elseif ($letzte_punkte === '') {
				$teams[$letztes_team] = '-';
			} else {
				$teams[$letztes_team] = '('.$letzte_punkte.')';
			}
		}
	}
	arsort($teams);
	return $teams;
}
