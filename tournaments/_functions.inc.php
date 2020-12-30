<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Common functions for tournaments


function mf_tournaments_current_round($identifier) {
	$sql = 'SELECT MAX(tabellenstaende.runde_no)
		FROM events
		JOIN tabellenstaende USING (event_id)
		WHERE events.identifier = "%s"
		GROUP BY events.event_id';
	$sql = sprintf($sql, wrap_db_escape($identifier));
	$round = wrap_db_fetch($sql, '', 'single value');
	if (!$round) return 0;
	return $round;
}

/**
 * Wertet aus, ob Tisch/Brett-Kombination in Livebrettern ist
 *
 * @param string $livebretter
 *		1-10, 12, 17
 *		1.6-1.10, 2.6–4.10
 *		1.*-6.*
 * @param int $brett_no
 * @param int $tisch_no (optional)
 */
function mf_tournaments_live_round($livebretter, $brett_no, $tisch_no = false) {
	if ($livebretter === '*') return true;
	$livebretter = explode(',', $livebretter);
	foreach ($livebretter as $bretter) {
		$bretter = trim($bretter);
		if (strstr($bretter, '-')) {
			$brett_vb = explode('-', $bretter);
		} else {
			$brett_vb[0] = $bretter;
			$brett_vb[1] = $bretter;
		}
		if (strstr($brett_vb[0], '.')) {
			// Tische und Bretter
			$min_bt = explode('.', $brett_vb[0]);
			$max_bt = explode('.', $brett_vb[1]);
			if ($tisch_no < $min_bt[0]) continue;
			if ($tisch_no > $max_bt[0]) continue;
			if ($brett_no < $min_bt[1]) continue;
			if ($brett_no > $max_bt[1]) continue;
			return true;
		} else {
			//  nur Bretter
			if ($brett_no < $brett_vb[0]) continue;
			if ($brett_no > $brett_vb[1]) continue;
			return true;
		}
	}
	return false;
}

/**
 * Berechnet den DWZ-Schnitt eines Teams
 *
 * @param int $event_id
 * @param array $teams Liste der Teams, indiziert nach team_id
 * @param int $bretter_min
 * @return array
 *		int dwz_schnitt
 *		array $teams, Liste wie in params, nur mit Feld 'dwz_schnitt' pro Team
 */
function mf_tournaments_team_rating_average_dwz($event_id, $teams, $bretter_min, $pseudo_dwz) {
	// DWZ-Schnitt der Teams berechnen
	$sql = 'SELECT teilnahme_id, brett_no, rang_no, team_id, t_dwz
		FROM teilnahmen
		LEFT JOIN teams USING (team_id)
		WHERE teilnahmen.event_id = %d
		AND usergroup_id = %d
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND (ISNULL(spielberechtigt) OR spielberechtigt != "nein")
		AND teams.team_status = "Teilnehmer"
		ORDER BY team_id, ISNULL(brett_no), brett_no, t_dwz DESC, t_elo DESC, rang_no';
	$sql = sprintf($sql, $event_id, wrap_id('usergroups', 'spieler'));
	$dwz = wrap_db_fetch($sql, ['team_id', 'teilnahme_id']);
	
	$event_dwz_schnitt = 0;
	$dwz_personen = 0;
	foreach (array_keys($teams) as $team_id) {
		if (!is_numeric($team_id)) continue;
		$teams[$team_id]['dwz_schnitt'] = 'k. A.';
	}
	if (!$bretter_min) {
		wrap_error('Keine Mindestbrettzahl angegeben, kann keinen DWZ-Schnitt berechnen');
		return [$event_dwz_schnitt, $teams];
	}
	foreach ($dwz as $team_id => $spieler) {
		$i = $bretter_min;
		$teams[$team_id]['dwz_schnitt'] = 0;
		$dwz_team_personen = 0;
		foreach ($spieler as $person) {
			if (!$i) break;
			$i--;
			if ($person['t_dwz']) {
				$teams[$team_id]['dwz_schnitt'] += $person['t_dwz'];
				$dwz_team_personen++;
			} elseif ($pseudo_dwz) {
				$teams[$team_id]['dwz_schnitt'] += $pseudo_dwz;
				$dwz_team_personen++;
			}
		}
		$event_dwz_schnitt += $teams[$team_id]['dwz_schnitt'];
		if ($dwz_team_personen) {
			$teams[$team_id]['dwz_schnitt'] = round(($teams[$team_id]['dwz_schnitt'] / $dwz_team_personen), 0);
			$dwz_personen += $dwz_team_personen;
		} else {
			$teams[$team_id]['dwz_schnitt'] = '–';
		}
	}
	if ($dwz_personen) {
		$event_dwz_schnitt = round(($event_dwz_schnitt / $dwz_personen), 0);
	}
	return [$event_dwz_schnitt, $teams];
}

/**
 * Rechnet Angaben zu Livebrettern in tatsächliche Bretter um
 *
 * @param string $livebretter
 *		4, 5-7, *
 * @param int $brett_max
 * @param int $tisch_max (optional)
 * @return array
 * @todo support für Mannschaftsturniere mit Tisch_no
 */
function mf_tournaments_live_boards($livebretter, $brett_max, $tisch_max = false) {
	if ($livebretter === '*') {
		if ($tisch_max) { // @todo
//			$data = range(1, $tisch_max);
//			return $data;
		} else {
			return range(1, $brett_max);
		}
	}
	$data = [];
	$livebretter = explode(',', $livebretter);
	if (!is_array($livebretter)) $livebretter = [$livebretter];
	foreach ($livebretter as $bretter) {
		$bretter = trim($bretter);
		if (strstr($bretter, '-')) {
			$bretter_von_bis = explode('-', $bretter);
			$bretter_von = $bretter_von_bis[0];
			$bretter_bis = $bretter_von_bis[1];
		} else {
			$bretter_von = $bretter;
			$bretter_bis = $bretter;
		}
		
		if (strstr($bretter_von, '.')) {
			// Tische und Bretter
			$tisch_von = explode('.', $bretter_von);
			$tisch_bis = explode('.', $bretter_bis);
			$brett_von = $tisch_von[1];
			$brett_bis = $tisch_bis[1];
			$tisch_von = $tisch_von[0];
			$tisch_bis = $tisch_bis[0];
			for ($i = $tisch_von; $i <= $tisch_bis; $i++) {
				if ($i === $tisch_von) {
					$range = range($brett_von, $brett_max);
				} elseif ($i === $tisch_bis) {
					$range = range(1, $brett_bis);
				} else {
					$range = range(1, $brett_max);
				}
				foreach ($range as $brett) {
					$data[] = $i.'.'.$brett;
				}
			}
		} else {
			$data = array_merge($data, range($bretter_von, $bretter_bis));
		}
	}
	return $data;
}

/**
 * Ausgabe SQL-Abfrage für Partien
 *
 * @param array $event
 * @param string $where
 * @return string $sql
 */
function mf_tournaments_games_sql($event, $where) {
	// @todo Punkte der Spieler berechnen (wie?)
	$sql = 'SELECT paarung_id, partie_id, partien.brett_no, partien.runde_no
			, IF(partiestatus_category_id = %d, 0.5,
				CASE IF(heim_spieler_farbe = "schwarz", schwarz_ergebnis, weiss_ergebnis)
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
				END
			) AS heim_ergebnis
			, IF(partiestatus_category_id = %d, 0.5,
				IF(heim_spieler_farbe = "schwarz", schwarz_ergebnis, weiss_ergebnis)
			) AS heim_ergebnis_numerisch
			, IF(partiestatus_category_id = %d, 0.5,
				CASE IF(heim_spieler_farbe = "schwarz", weiss_ergebnis, schwarz_ergebnis)
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
				END
			) AS auswaerts_ergebnis
			, IF(partiestatus_category_id = %d, 0.5,
				IF(heim_spieler_farbe = "schwarz", weiss_ergebnis, schwarz_ergebnis)
			) AS auswaerts_ergebnis_numerisch
			, IF(weiss_ergebnis > schwarz_ergebnis, 1, NULL) AS weiss_gewinnt
			, IF(schwarz_ergebnis > weiss_ergebnis, 1, NULL) AS schwarz_gewinnt
			, @schwarz_spieler := IF(ISNULL(schwarz_status.t_vorname),
				CONCAT(schwarz.vorname, " ", IFNULL(CONCAT(schwarz.namenszusatz, " "), ""), schwarz.nachname),
				CONCAT(schwarz_status.t_vorname, " ", IFNULL(CONCAT(schwarz_status.t_namenszusatz, " "), ""), schwarz_status.t_nachname)
			)
			, @weiss_spieler := IF(ISNULL(weiss_status.t_vorname),
				CONCAT(weiss.vorname, " ", IFNULL(CONCAT(weiss.namenszusatz, " "), ""), weiss.nachname),
				CONCAT(weiss_status.t_vorname, " ", IFNULL(CONCAT(weiss_status.t_namenszusatz, " "), ""), weiss_status.t_nachname)
			)
			, IFNULL(IF(heim_spieler_farbe = "schwarz", @schwarz_spieler, @weiss_spieler), "N. N.") AS heim_spieler
			, IFNULL(IF(heim_spieler_farbe = "schwarz", @weiss_spieler, @schwarz_spieler), "N. N.") AS auswaerts_spieler
			, weiss_person_id, schwarz_person_id
			, IF(heim_spieler_farbe = "schwarz",
				IF(schwarz_status.gastspieler = "ja", 1, NULL),
				IF(weiss_status.gastspieler = "ja", 1, NULL)
			) AS heim_gastspieler
			, IF(heim_spieler_farbe = "schwarz",
				IF(weiss_status.gastspieler = "ja", 1, NULL),
				IF(schwarz_status.gastspieler = "ja", 1, NULL)
			) AS auswaerts_gastspieler
			, IF(heim_spieler_farbe = "schwarz", "schwarz", "weiss") AS heim_farbe
			, IF(heim_spieler_farbe = "schwarz", "weiss", "schwarz") AS auswaerts_farbe
			, heim_wertung, auswaerts_wertung
			, IF(partiestatus_category_id = %d, 1, NULL) AS haengepartie
			, categories.category AS partiestatus
			, IF(heim_spieler_farbe = "schwarz", schwarz_status.t_dwz, weiss_status.t_dwz) AS heim_dwz
			, IF(heim_spieler_farbe = "schwarz", weiss_status.t_dwz, schwarz_status.t_dwz) AS auswaerts_dwz
			, IF(heim_spieler_farbe = "schwarz", schwarz_status.t_elo, weiss_status.t_elo) AS heim_elo
			, IF(heim_spieler_farbe = "schwarz", weiss_status.t_elo, schwarz_status.t_elo) AS auswaerts_elo
			, IF(heim_spieler_farbe = "schwarz", schwarz_status.setzliste_no, weiss_status.setzliste_no) AS heim_setzliste_no
			, IF(heim_spieler_farbe = "schwarz", weiss_status.setzliste_no, schwarz_status.setzliste_no) AS auswaerts_setzliste_no
			, IF(NOT ISNULL(pgn), IF(partiestatus_category_id != %d, 1, NULL), NULL) AS partie
			, eco
			, (SELECT wertung FROM tabellenstaende
				LEFT JOIN tabellenstaende_wertungen USING (tabellenstand_id)
				WHERE runde_no = partien.runde_no - 1
				AND event_id = partien.event_id
				AND person_id = partien.schwarz_person_id
				AND wertung_category_id = %d) AS schwarz_punkte
			, (SELECT wertung FROM tabellenstaende
				LEFT JOIN tabellenstaende_wertungen USING (tabellenstand_id)
				WHERE runde_no = partien.runde_no - 1
				AND event_id = partien.event_id
				AND person_id = partien.weiss_person_id
				AND wertung_category_id = %d) AS weiss_punkte
		FROM partien
		LEFT JOIN categories
			ON partien.partiestatus_category_id = categories.category_id
		LEFT JOIN personen weiss
			ON weiss.person_id = partien.weiss_person_id
		LEFT JOIN teilnahmen weiss_status
			ON weiss_status.person_id = weiss.person_id
			AND weiss_status.usergroup_id = %d
			AND weiss_status.event_id = %d
		LEFT JOIN personen schwarz
			ON schwarz.person_id = partien.schwarz_person_id
		LEFT JOIN teilnahmen schwarz_status
			ON schwarz_status.person_id = schwarz.person_id
			AND schwarz_status.usergroup_id = %d
			AND schwarz_status.event_id = %d
		WHERE partien.event_id = %d
		AND %s
		ORDER BY runde_no, IF(ISNULL(partien.brett_no), 1, 0)
			, partien.brett_no, (schwarz_punkte + weiss_punkte) DESC
			, IF(ISNULL(schwarz_status.setzliste_no), 1, NULL)
			, IF(ISNULL(weiss_status.setzliste_no), 1, NULL)
			, schwarz_status.setzliste_no + weiss_status.setzliste_no';
	$sql = sprintf($sql
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('turnierwertungen/pkt')
		, wrap_category_id('turnierwertungen/pkt')
		, wrap_id('usergroups', 'spieler'), $event['event_id']
		, wrap_id('usergroups', 'spieler'), $event['event_id']
		, $event['event_id'], $where
	);
	return $sql;
}

/**
 * Setzt Parameter für Filter für Tabellenstand
 * (Geschlecht, Alter, Wertung)
 *
 * @param string $filter_kennung
 * @return array
 */
function mf_tournaments_standings_filter($filter_kennung = false) {
	$filter = [];
	$filter['where'] = [];
	$filter['error'] = false;
	$filter['kennung'] = $filter_kennung;
	switch ($filter_kennung) {
	// @todo nur Filter erlauben, die auch in turniere.tabellenstand eingetragen sind
	case 'w':
		$filter['where'][] = 'personen.geschlecht = "weiblich"';
		$filter['untertitel'] = 'weiblich';
		break;
	case 'm':
		$filter['where'][] = 'personen.geschlecht = "männlich"';
		$filter['untertitel'] = 'männlich';
		break;
	case 'alt':
		$filter['where'][] = 'YEAR(personen.geburtsdatum) = (YEAR(events.date_begin) - alter_max)';
		$filter['untertitel'] = 'ältester Jahrgang';
		break;
	case 'jung':
		$filter['where'][] = 'YEAR(personen.geburtsdatum) > (YEAR(events.date_begin) - alter_max)';
		$filter['untertitel'] = 'jüngere Jahrgänge';
		break;
	// @todo u12, u10, u...
	// @todo 60+, 65+
	// @todo dwz<1200, dwz>1200, elo<1200 etc
	default:
		if ($filter_kennung) $filter['error'] = true;
		break;
	}
	return $filter;
}

/**
 * Gibt Endtabelle (Platz 1-3) pro Turnier aus
 *
 * @param mixed int or array Liste von Termin-IDs
 * @return array
 * @todo move to separate request script with own template
 */
function mf_tournaments_final_standings($event_ids) {
	$single = false;
	if (!is_array($event_ids)) {
		$single = $event_ids;
		$event_ids = [$event_ids];
	}
	// Wir gehen davon aus, dass bei beendeten Turnieren der Tabellenstand = Endstand ist
	$sql = 'SELECT event_id, runden, events.identifier
			, (SELECT COUNT(team_id) FROM teams
				WHERE spielfrei = "nein"
				AND team_status = "Teilnehmer"
				AND teams.event_id = events.event_id) AS teams
			, (SELECT COUNT(teilnahme_id) FROM teilnahmen
				LEFT JOIN personen USING (person_id)
				WHERE teilnahme_status = "Teilnehmer"
				AND usergroup_id = %d
				AND geschlecht = "männlich"
				AND teilnahmen.event_id = events.event_id) AS spieler
			, (SELECT COUNT(teilnahme_id) FROM teilnahmen
				LEFT JOIN personen USING (person_id)
				WHERE teilnahme_status = "Teilnehmer"
				AND usergroup_id = %d
				AND geschlecht = "weiblich"
				AND teilnahmen.event_id = events.event_id) AS spielerinnen
			, turniere.tabellenstaende
		FROM events
		LEFT JOIN turniere USING (event_id)
		WHERE event_id IN (%s)
		AND ((ISNULL(events.date_end) AND events.date_begin < CURDATE()) OR events.date_end < CURDATE())';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		implode(',', $event_ids)
	);
	$turniere = wrap_db_fetch($sql, 'event_id');
	$tabellenstaende = [];
	foreach ($turniere as $event_id => $turnier) {
		$tabellenstaende['gesamt'][] = $event_id;
		if (!$turnier['tabellenstaende']) continue;
		$staende = explode(',', $turnier['tabellenstaende']);
		foreach ($staende as $stand) {
			$tabellenstaende[$stand][] = $event_id;
		}
	}

	foreach ($tabellenstaende as $fkennung => $ids) {
		if ($fkennung === 'gesamt') {
			$filter[$fkennung]['where'][] = 'platz_no <= 3';
		} else {
			$filter[$fkennung] = mf_tournaments_standings_filter($fkennung);
		}

		$sql = 'SELECT tabellenstaende.event_id
				, tabellenstand_id, runde_no, platz_no
				, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
				, IF(turniere.teilnehmerliste = "ja", teams.kennung, "") AS team_identifier
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, teilnahmen.setzliste_no
				, t_verein AS verein
				, personen.geschlecht
			FROM tabellenstaende
			LEFT JOIN turniere USING (event_id)
			LEFT JOIN teams USING (team_id)
			LEFT JOIN teilnahmen
				ON teilnahmen.person_id = tabellenstaende.person_id
				AND teilnahmen.event_id = tabellenstaende.event_id
				AND ISNULL(teilnahmen.team_id)
			LEFT JOIN personen
				ON tabellenstaende.person_id = personen.person_id 
			WHERE tabellenstaende.event_id IN (%s)
			AND (ISNULL(teilnahmen.teilnahme_status) OR teilnahmen.teilnahme_status = "Teilnehmer")
			AND (%s)
			ORDER BY platz_no';
		$sql = sprintf($sql,
			implode(',', $ids),
			implode(') AND (', $filter[$fkennung]['where'])
		);
		$tabellen[$fkennung] = wrap_db_fetch($sql, ['event_id', 'tabellenstand_id']);
		foreach ($tabellen[$fkennung] as $event_id => $tabellenstand) {
			foreach ($tabellenstand as $ts_id => $platzierung) {
				if ($platzierung['runde_no'] !== $turniere[$event_id]['runden']) {
					unset($tabellen[$fkennung][$event_id][$ts_id]);
				} elseif (!$platzierung['team'] AND !$platzierung['person']) {
					// Brettrangliste in Mannschaftsturnier nicht ausgeben!
					unset($tabellen[$fkennung][$event_id][$ts_id]);
				}
			}
			if ($fkennung AND $tabellen[$fkennung][$event_id]) {
				$tabellen[$fkennung][$event_id] = array_slice($tabellen[$fkennung][$event_id], 0, 3);
				$tabellen[$fkennung][$event_id][0]['rang_no'] = 1;
				$tabellen[$fkennung][$event_id][1]['rang_no'] = 2;
				$tabellen[$fkennung][$event_id][2]['rang_no'] = 3;
			}
		}
	}
	if (empty($tabellen['gesamt'])) return [];

	foreach ($event_ids as $event_id) {
		$events[$event_id]['tabellen'] = [];
		if (!array_key_exists($event_id, $tabellen['gesamt'])) continue;
		$events[$event_id]['tabelle'] = $tabellen['gesamt'][$event_id];
		$events[$event_id]['runden'] = $turniere[$event_id]['runden'];
		$events[$event_id]['teams'] = $turniere[$event_id]['teams']
			? $turniere[$event_id]['teams'] : NULL;
		$events[$event_id]['spieler'] = $turniere[$event_id]['spieler']
			? $turniere[$event_id]['spieler'] :  NULL;
		$events[$event_id]['spielerinnen'] = $turniere[$event_id]['spielerinnen']
			? $turniere[$event_id]['spielerinnen'] :  NULL;
		foreach (array_keys($tabellen) as $ts) {
			if ($ts === 'gesamt') continue;
			if (!array_key_exists($event_id, $tabellen[$ts])) continue;
			$events[$event_id]['tabellen'][] = [
				'untertitel' => $filter[$ts]['untertitel'],
				'kennung_tab' => $filter[$ts]['kennung'],
				'weitere_tabelle' => $tabellen[$ts][$event_id],
				'identifier' => $turniere[$event_id]['identifier']
			];
		}
	}
	if ($single) return $events[$single];
	return $events;
}

/**
 * sende anderen Cache-Control-Header während Turnier
 *
 * @param array $event
 * @return void
 */
function mf_tournaments_cache($event) {
	$duration = explode('/', $event['duration']);
	$today = date('Y-m-d');
	if ($today < $duration[0]) return;
	if (empty($duration[1])) return;
	if ($today > $duration[1]) return;
	wrap_cache_header('Cache-Control: max-age=0');
}

/**
 * check if submitting a line-up is available for current round
 * i. e. round has not begun or lineup_before_round_mins has a negative value
 *
 * @param array $event
 * @return bool
 */
function mf_tournaments_lineup($event) {
	if ($event['runde_no'] != mf_tournaments_current_round($event['identifier']) + 1) return false;

	$sql = 'SELECT IF(DATE_ADD(NOW(), INTERVAL %d MINUTE) > CONCAT(date_begin, " ", time_begin), NULL, 1) AS lineup_open
		FROM events
		WHERE main_event_id = %d
		AND runde_no = %d';
	$sql = sprintf($sql
		 , (!empty($event['lineup_before_round_mins']) ? $event['lineup_before_round_mins'] : 0)
		 , $event['event_id']
		 , $event['runde_no']
	);
	$lineup = wrap_db_fetch($sql, '', 'single value');
	return $lineup;
}
