<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Copyright (c) 2014 Erik Kothe <erik@deutsche-schachjugend.de>
// update standings for team tournaments


/**
 * Aktualisiere den Tabellenstand einer Runde eines Mannschaftsturniers
 *
 * @param array $event [event_id, runde_no, bretter_min, identifier]
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_write_mannschaft($event) {
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
		HAVING veroeffentlicht > 0
	';
	$sql = sprintf($sql,
		$event['event_id'], $event['runde_no']
	);
	$tabelle = wrap_db_fetch($sql, 'team_id');
	if (!$tabelle) return false;

	$turnierwertungen = cms_tabellenstandupdate_wertungen($event['event_id']);

	// Wertungen aus Datenbank auslesen
	$sql = 'SELECT tabellenstaende_view.wertung_category_id, team_id, wertung
		FROM tabellenstaende_view
		JOIN turniere USING (event_id)
		JOIN turniere_wertungen
			ON turniere_wertungen.turnier_id = turniere.turnier_id
			AND turniere_wertungen.wertung_category_id = tabellenstaende_view.wertung_category_id
		WHERE tabellenstaende_view.runde_no = %d
		AND wertung IS NOT NULL
		AND team_id IN (%s)
		ORDER BY turniere_wertungen.reihenfolge, wertung DESC';
	$sql = sprintf($sql, $event['runde_no'], implode(',', array_keys($tabelle)));
	$wertungen = wrap_db_fetch($sql, ['wertung_category_id', 'team_id', 'wertung'], 'key/value');

	// Weitere Wertungen ergänzen
	foreach ($turnierwertungen as $category_id => $turnierwertung) {
		switch ($category_id) {
		case wrap_category_id('turnierwertungen/bhz.2'):
			$erste_wertung = reset($turnierwertungen);
			if ($erste_wertung['category_id'] === wrap_category_id('turnierwertungen/bp')) {
				$wertungen[$category_id] = my_wertung_team_buchholz_bp(
					$event['event_id'], $event['runde_no']
				);
			}
			break;
		case wrap_category_id('turnierwertungen/dv'):
			// direkter Vergleich erst nach Auswertung der anderen Wertungen
			$wertungen[$category_id] = 1;
			break;
		case wrap_category_id('turnierwertungen/rg'):
			$sql = 'SELECT team_id, setzliste_no
				FROM teams
				WHERE team_id IN (%s)
				ORDER BY setzliste_no';
			$sql = sprintf($sql, implode(',', array_keys($tabelle)));
			$wertungen[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
			break;
		case wrap_category_id('turnierwertungen/sobo'):
			$wertungen[$category_id] = my_wertung_team_sonneborn_berger(
				$event['event_id'], $event['runde_no']
			);
			break;
		}
		if ($turnierwertung['anzeigen'] === 'immer') {
			// Vor der 1. Runde kann es sein, dass Mannschafts- und Brettpunkte
			// für einzelne Teams noch nicht gesetzt sind, da es noch keine Ergebnisse
			// gibt, dann auf 0 setzen
			// @todo Achtung: das ist nicht immer 100% korrekt, da theoretisch
			// auch Wertungen vorne stehen könnten, bei denen nicht 0 der geringste
			// Wert ist.
			if (!array_key_exists($category_id, $wertungen)) {
				$wertungen[$category_id] = [];
			}
			foreach (array_keys($tabelle) as $team_id) {
				if (!array_key_exists($team_id, $wertungen[$category_id])) {
					$wertungen[$category_id][$team_id] = 0;
				} elseif (empty($wertungen[$category_id][$team_id])) {
					$wertungen[$category_id][$team_id] = 0;
				}
			}
		}
	}

	$tabelle = cms_tabellenstand_wertungen($event, $tabelle, $wertungen, $turnierwertungen);

	$sql = 'SELECT team_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d AND runde_no = %d AND NOT ISNULL(team_id)';
	$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	$vorhandene_daten = wrap_db_fetch($sql, '_dummy_', 'key/value');

	foreach ($tabelle as $stand) {
		$unwanted_keys = [
			'dwz_schnitt', 'eindeutig'
		];
		foreach ($unwanted_keys as $key) {
			unset($stand[$key]);
		}
		$values = [];
		$values['POST'] = $stand;
		$values['ids'] = ['team_id', 'event_id'];
		if (!empty($vorhandene_daten[$stand['team_id']])) {
			$values['POST']['tabellenstand_id'] = $vorhandene_daten[$stand['team_id']];
			// überflüssige Tabellenstände löschen
			// @todo irgendwann so etwas direkt in zzform mit Funktion lösen
			// (alle anderen Datensätze, die nicht aktualisiert werden, löschen)
			$sql = 'SELECT * FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $vorhandene_daten[$stand['team_id']]);
			$data = wrap_db_fetch($sql, 'tsw_id');
			foreach ($data as $tsw_id => $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$values['POST']['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
			$values['action'] = 'update';
		} else {
			$values['action'] = 'insert';
		}
		$ops = zzform_multi('tabellenstaende', $values);
		if (!$ops['id']) {
			wrap_error('Tabellenstand konnte nicht aktualisiert oder hinzugefügt werden.
			Termin: '.$event['identifier'].', Runde: '.$event['runde_no'].'. Fehler: '.implode(', ', $ops['error']), E_USER_ERROR);
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
 * @param array $tabelle
 * @param array $hauptwertung
 * @return $teams int team_id => string Wertung
 */
function cms_tabellenstand_direkter_vergleich($event, $tabelle, $hauptwertung) {
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
	
	foreach ($tabelle as $team_id => $wertung) {
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
					$teams[$letztes_team] = 'n. a.';
				} elseif ($letzte_punkte === '') {
					$teams[$letztes_team] = '-';
				} elseif ($letzte_punkte !== false) {
					// falls punktgleich, wird hier abgebrochen
					if ($punkte === $letzte_punkte) {
						$stop = true;
						$teams[$letztes_team] = 'n. a.';
					} else {
						// check, ob jemand mehr Punkte erreichen kann
						foreach ($moegliche_punkte as $m_team_id => $m_punkte) {
							if ($m_punkte >= $letzte_punkte) {
								$stop = true;
								$teams[$letztes_team] = 'n. a.';
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
				$teams[$letztes_team] = 'n. a.';
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
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste team_id => value
 */
function my_wertung_team_sonneborn_berger($event_id, $runde_no) {
	// paarungen_ergebnisse_view gibt bei Gewinn 2 MP, bei Unentschieden 1 MP aus
	// daher MP / 2 * gegnerische MP
	$sql = 'SELECT paarungen_ergebnisse_view.team_id
			, SUM(paarungen_ergebnisse_view.brettpunkte * tabellenstaende_view.wertung) AS sb
		FROM paarungen_ergebnisse_view
		LEFT JOIN tabellenstaende_view
			ON paarungen_ergebnisse_view.gegner_team_id = tabellenstaende_view.team_id
		WHERE paarungen_ergebnisse_view.event_id = %d
		AND tabellenstaende_view.runde_no = %d
		AND paarungen_ergebnisse_view.runde_no <= %d
		AND tabellenstaende_view.wertung_category_id = %d
		GROUP BY paarungen_ergebnisse_view.team_id
		ORDER BY sb DESC
	';
	$sql = sprintf($sql
		, $event_id
		, $runde_no, $runde_no
		, wrap_category_id('turnierwertungen/mp')
	);
	$wertungen = wrap_db_fetch($sql, 'team_id', 'key/value');
	return $wertungen;
}

/**
 * Buchholz für Mannschaftsturniere berechnen bei Erstwertung Brettpunkte
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste team_id => value
 */
function my_wertung_team_buchholz_bp($event_id, $runde_no) {
	// @todo
	// check if there's a correction here as well
	//			, SUM(IF((gegners_paarungen.kampflos = 1), 1, gegners_paarungen.brettpunkte))
	//			AS buchholz_mit_korrektur
	// Swiss-Chess says no

	$sql = 'SELECT tabellenstaende_termine_view.team_id
			, SUM(gegners_paarungen.brettpunkte) AS buchholz
		FROM paarungen_ergebnisse_view
		LEFT JOIN tabellenstaende_termine_view USING (team_id)
		LEFT JOIN paarungen_ergebnisse_view gegners_paarungen
			ON gegners_paarungen.team_id = paarungen_ergebnisse_view.gegner_team_id
		WHERE paarungen_ergebnisse_view.runde_no <= tabellenstaende_termine_view.runde_no
		AND tabellenstaende_termine_view.runde_no = %d
		GROUP BY tabellenstaende_termine_view.team_id
		ORDER BY buchholz DESC';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}
