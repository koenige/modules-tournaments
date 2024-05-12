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
 * @copyright Copyright © 2012-2024 Gustaf Mossakowski
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
	$sql = 'SELECT @event_id:=%d';
	$sql = sprintf($sql, $event['event_id']);
	wrap_db_query($sql);
	
	$sql = 'SELECT teams.team_id
			, gewonnen AS spiele_g, unentschieden AS spiele_u, verloren AS spiele_v
			, events.event_id, tabellenstaende_guv_view.runde_no
			, (SELECT COUNT(website_id) FROM events_websites WHERE events_websites.event_id = events.event_id) AS veroeffentlicht
		FROM events
		JOIN teams USING (event_id)
		JOIN tabellenstaende_guv_view USING (team_id)
		WHERE events.event_id = %d
		AND tabellenstaende_guv_view.runde_no = %d
		AND spielfrei = "nein"
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND team_status = "Teilnehmer"
		HAVING veroeffentlicht > 0
	';
	$sql = sprintf($sql,
		$event['event_id'], $event['runde_no']
	);
	$standings = wrap_db_fetch($sql, 'team_id');
	if (!$standings) return false;

	$scorings = mod_tournaments_make_standings_get_scoring($event['event_id']);
	$scores = [];

	// Wertungen aus Datenbank auslesen
	foreach ($scorings as $category_id => $scoring) {
		if (str_starts_with($scoring['path'], 'bhz')) {
			$erste_wertung = reset($scorings);
			if ($erste_wertung['path'] === 'bp')
				$scoring['path'] .= '_bp';
			else
				$scoring['path'] .= '_mp';
			if (mf_tournaments_make_fide_correction($event['event_id']) === 'fide-2012')
				$scoring['path'] .= '_fide2012';
			// @todo check if there’s a correction for board points as well
			// Swiss-Chess says no, so we remove it here:
			if (str_ends_with($scoring['path'], '_bp_fide2012'))
				$scoring['path'] = substr($scoring['path'], 0, -strlen('_fide2012'));
		}
		
		switch ($scoring['path']) {
		case 'dv':
			// direkter Vergleich erst nach Auswertung der anderen Wertungen
			$scores[$category_id] = 1;
			break;
		case 'rg':
			$sql = wrap_sql_query('tournaments_scores_team_rg', 'standings');
			$sql = sprintf($sql, implode(',', array_keys($standings)));
			$scores[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
			break;
		case 'sobo':
			$scores[$category_id] = mf_tournaments_make_team_sobo($event['runde_no']); break;
			break;
		default:
			if ($sql = wrap_sql_query('tournaments_scores_team_'.$scoring['path'], 'standings')) {
				$sql = sprintf($sql, $event['runde_no']);
				$scores[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
				break;
			}
			wrap_error(sprintf('Rating %s not implemented.', $scoring['path']), E_USER_WARNING);
		}

		if ($scoring['anzeigen'] === 'immer') {
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

	$standings = mod_tournaments_make_standings_prepare($event, $standings, $scores, $scorings);

	$sql = 'SELECT team_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d AND runde_no = %d AND NOT ISNULL(team_id)';
	$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	$existing_standings = wrap_db_fetch($sql, '_dummy_', 'key/value');

	foreach ($standings as $stand) {
		$unwanted_keys = [
			'dwz_schnitt', 'eindeutig'
		];
		foreach ($unwanted_keys as $key)
			unset($stand[$key]);

		$line = $stand;
		$line['event_id'] = $event['event_id'];
		$line['runde_no'] = $event['runde_no'];
		if (!empty($existing_standings[$stand['team_id']])) {
			$line['tabellenstand_id'] = $existing_standings[$stand['team_id']];
			// überflüssige Tabellenstände löschen
			// @todo irgendwann so etwas direkt in zzform mit Funktion lösen
			// (alle anderen Datensätze, die nicht aktualisiert werden, löschen)
			$sql = 'SELECT * FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $existing_standings[$stand['team_id']]);
			$data = wrap_db_fetch($sql, 'tsw_id');
			foreach ($data as $tsw_id => $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$line['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
			zzform_update('tabellenstaende', $line, E_USER_ERROR);
		} else {
			zzform_insert('tabellenstaende', $line, E_USER_ERROR);
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
 * @param array $hauptwertung
 * @return $teams int team_id => string Wertung
 */
function mf_tournaments_make_team_direct_encounter($event, $standings, $hauptwertung) {
	// Welches ist die Hauptwertung?
	switch ($hauptwertung['category_id']) {
	case wrap_category_id('turnierwertungen/mp'):
		$tw = 'mp';
		break;
	default:
	case wrap_category_id('turnierwertungen/bp'):
		$tw = 'bp';
		break;
	}

	$teams = [];
	$unklar = [];
	
	foreach ($standings as $team_id => $wertung) {
		if (!empty($wertung['eindeutig'])) continue;
		$index = isset($wertung['platz_no']) ? $wertung['platz_no'] : 0;
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
					if (!empty($paarung['heim_'.$tw]))
						$punkte[$team_id]['punkte'] += $paarung['heim_'.$tw];
					$punkte[$team_id]['paarungen']++;
				} elseif ($team_id == $paarung['auswaerts_team_id']) {
					if (!empty($paarung['auswaerts_'.$tw]))
						$punkte[$team_id]['punkte'] += $paarung['auswaerts_'.$tw];
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
				if ($tw === 'mp') {
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

/**
 * Sonneborn-Berger für Mannschaftsturniere berechnen
 * = Erzielte Brettpunkte x Mannschaftspunktzahl der Gegner nach der aktuellen Runde
 *
 * @param int $round_no
 * @return array Liste team_id => value
 */
function mf_tournaments_make_team_sobo($round_no) {
	// @deprecated, second query does not work in old MySQL/MariaDB databases
	// because of GROUP BY
	$deprecated = true;
	if ($deprecated) {
		$sql = 'SELECT team_id, (brettpunkte * (SELECT SUM(mannschaftspunkte)
					FROM paarungen_ergebnisse_view opponents
					WHERE opponents.team_id = paarungen_ergebnisse_view.gegner_team_id
					AND opponents.runde_no <= %d
				)) AS points
				, runde_no
			FROM paarungen_ergebnisse_view
			LEFT JOIN teams USING (team_id)
			WHERE paarungen_ergebnisse_view.runde_no <= %d
			AND team_status = "Teilnehmer"
			AND spielfrei = "nein"
			ORDER BY runde_no
		';
		$sql = sprintf($sql, $round_no, $round_no);
		$board_points = wrap_db_fetch($sql, ['team_id', 'runde_no', 'points'], 'key/value');
		foreach ($board_points as $team_id => $points)
			$data[$team_id] = array_sum($points);
		arsort($data);
	} else {
		// paarungen_ergebnisse_view gibt bei Gewinn 2 MP, bei Unentschieden 1 MP aus
		// daher MP / 2 * gegnerische MP
		$sql = 'SELECT team_id
				, SUM(brettpunkte * 
					(SELECT SUM(mp.mannschaftspunkte)
					FROM paarungen_ergebnisse_view mp
					WHERE mp.team_id = paarungen_ergebnisse_view.gegner_team_id
					AND mp.runde_no <= %d)
				) AS sb
			FROM paarungen_ergebnisse_view
			LEFT JOIN teams USING (team_id)
			WHERE paarungen_ergebnisse_view.runde_no <= %d
			AND team_status = "Teilnehmer"
			AND spielfrei = "nein"
			GROUP BY team_id
			ORDER BY sb DESC, team_id
		';
		$sql = sprintf($sql
			, $round_no
			, $round_no
		);
		$data = wrap_db_fetch($sql, 'team_id', 'key/value');
	}
	return $data;
}

