<?php 

/**
 * Zugzwang Project
 * Standings
 *
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Gibt Tabellenstand aus
 * (teilweise aus Datenbank, größtenteils selbst berechnet)
 *
 * @param array $vars
 *		int [0]: Jahr
 *		string [1]: Terminkennung
 *		(string [2]: (optional) 'tabelle')
 *		int [2]: (optional) Runde
 * @return array
 */
function mod_tournaments_standings($vars) {
	global $zz_setting;

	// 'tabelle' entfernen, brauchen wir nicht.
	if (count($vars) >= 3 AND $vars[2] === 'tabelle') {
		unset($vars[2]);
	}
	$filter_kennung = false;
	if (count($vars) === 4) {
		$filter_kennung = array_pop($vars);
		$runde = array_pop($vars);
		$pfad = '../../';
	} elseif (count($vars) === 3) {
		// Runde steht in URL
		$runde = array_pop($vars);
		if (!is_numeric($runde)) {
			$filter_kennung = $runde;
			$runde = mf_tournaments_current_round($vars[0].'/'.$vars[1]);
		} else {
			if (sprintf('%d', $runde) !== $runde.'') return false;
		}
		$pfad = '../';
	} elseif (count($vars) === 2) {
		// Letzte Runde
		$runde = mf_tournaments_current_round($vars[0].'/'.$vars[1]);
		$pfad = '';
	} else {
		return false;
	}

	$filter = mf_tournaments_standings_filter($filter_kennung);
	if ($filter['error']) return false;

	$sql = 'SELECT events.event_id, event, tournaments.runden, bretter_min, pseudo_dwz
			, YEAR(events.date_begin) AS year
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, (SELECT COUNT(partie_id) FROM partien
				WHERE partien.event_id = events.event_id
				AND partien.runde_no = %d
			) AS partien
			, @live := (SELECT IF(COUNT(partie_id), 1, NULL) FROM partien
				WHERE partien.event_id = events.event_id
				AND ISNULL(weiss_ergebnis)
				AND partien.runde_no = %d
			) AS live
			, IF(ISNULL(@live) AND tournaments.runden = %d, 1, NULL) AS endstand
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, IFNULL(place, places.contact) AS turnierort
		FROM events
		JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN categories turnierformen
			ON turnierformen.category_id = tournaments.turnierform_category_id
		WHERE events.identifier = "%d/%s"
	';
	$sql = sprintf($sql, $runde, $runde, $runde, $zz_setting['website_id'], $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$event['runde_no'] = $runde;
	mf_tournaments_cache($event);

	if ($event['turnierform'] !== 'e') {
		$sql = 'SELECT tabellenstand_id, platz_no
				, spiele_g, spiele_u, spiele_v
				, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
				, teams.kennung AS team_identifier, team_id
				, SUBSTRING_INDEX(teams.kennung, "/", -1) AS team_identifier_short
				, countries.country
				, IFNULL(landesverbaende.kennung, landesverbaende_rueckwaerts.kennung) AS lv_kennung
				, IFNULL(landesverbaende.org_abk, landesverbaende_rueckwaerts.org_abk) AS lv_kurz
				, team_status AS status
			FROM tabellenstaende
			JOIN teams USING (team_id)
			LEFT JOIN organisationen
				ON teams.verein_org_id = organisationen.org_id
			LEFT JOIN organisationen_kennungen v_ok
				ON v_ok.org_id = organisationen.org_id
				AND v_ok.current = "yes"
			LEFT JOIN organisationen_kennungen lv_ok
				ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
				AND lv_ok.current = "yes"
			LEFT JOIN organisationen landesverbaende
				ON lv_ok.org_id = landesverbaende.org_id
				AND landesverbaende.mutter_org_id = %d
			LEFT JOIN countries
				ON IFNULL(landesverbaende.country_id, organisationen.country_id) 
					= countries.country_id
			LEFT JOIN organisationen landesverbaende_rueckwaerts
				ON countries.country_id = landesverbaende_rueckwaerts.country_id
				AND landesverbaende_rueckwaerts.category_id = %d
				AND landesverbaende_rueckwaerts.mutter_org_id = %d
			WHERE teams.event_id = %d
			AND tabellenstaende.runde_no = %d
			AND spielfrei = "nein"
			ORDER BY platz_no, team, team_no
		';
		$sql = sprintf($sql
			, $zz_setting['org_ids']['dsb']
			, wrap_category_id('organisationen/verband')
			, $zz_setting['org_ids']['dsb']
			, $event['event_id'], $runde
		);
	} else {
		$sql = 'SELECT tabellenstand_id, tabellenstaende.platz_no
				, spiele_g, spiele_u, spiele_v
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, countries.country
				, IFNULL(landesverbaende.kennung, landesverbaende_rueckwaerts.kennung) AS lv_kennung
				, IFNULL(landesverbaende.org_abk, landesverbaende_rueckwaerts.org_abk) AS lv_kurz
				, teilnahmen.setzliste_no
				, t_verein, tabellenstaende.person_id
				, teilnahme_status AS status
			FROM tabellenstaende
			JOIN tournaments USING (event_id)
			JOIN events USING (event_id)
			LEFT JOIN teilnahmen
				ON teilnahmen.person_id = tabellenstaende.person_id
				AND teilnahmen.event_id = tabellenstaende.event_id
				AND teilnahmen.usergroup_id = %d
			LEFT JOIN personen
				ON tabellenstaende.person_id = personen.person_id
			LEFT JOIN organisationen
				ON teilnahmen.verein_org_id = organisationen.org_id
			LEFT JOIN organisationen_kennungen v_ok
				ON v_ok.org_id = organisationen.org_id
				AND v_ok.current = "yes"
			LEFT JOIN organisationen_kennungen lv_ok
				ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
				AND lv_ok.current = "yes"
			LEFT JOIN organisationen landesverbaende
				ON lv_ok.org_id = landesverbaende.org_id
				AND landesverbaende.mutter_org_id = %d
			LEFT JOIN countries
				ON IFNULL(landesverbaende.country_id, organisationen.country_id) 
					= countries.country_id
			LEFT JOIN organisationen landesverbaende_rueckwaerts
				ON countries.country_id = landesverbaende_rueckwaerts.country_id
				AND landesverbaende_rueckwaerts.category_id = %d
				AND landesverbaende_rueckwaerts.mutter_org_id = %d
			WHERE tabellenstaende.event_id = %d
			AND tabellenstaende.runde_no = %d
			%s
			ORDER BY platz_no
		';
		$sql = sprintf($sql
			, wrap_id('usergroups', 'spieler')
			, $zz_setting['org_ids']['dsb']
			, wrap_category_id('organisationen/verband')
			, $zz_setting['org_ids']['dsb']
			, $event['event_id'], $runde
			, $filter['where'] ? ' AND '.implode(' AND ', $filter['where']) : ''
		);
	}
	$id = $event['turnierform'] !== 'e' ? 'team_id' : 'person_id';
	$tabelle = wrap_db_fetch($sql, 'tabellenstand_id');
	if (!$tabelle) return false;

	if ($event['live'] AND $event['turnierform'] !== 'e') {
		$sql = 'SELECT paarung_id, heim_team_id, auswaerts_team_id
			FROM partien
			LEFT JOIN paarungen USING (paarung_id) 
			WHERE ISNULL(weiss_ergebnis)
			AND partien.event_id = %d AND partien.runde_no = %d';
		$sql = sprintf($sql, $event['event_id'], $runde);
		$laufende_begegnungen = wrap_db_fetch($sql, 'paarung_id');
	} else {
		$sql = 'SELECT partie_id, weiss_person_id, schwarz_person_id
			FROM partien
			WHERE ISNULL(weiss_ergebnis)
			AND partien.event_id = %d AND partien.runde_no = %d';
		$sql = sprintf($sql, $event['event_id'], $runde);
		$laufende_begegnungen = wrap_db_fetch($sql, 'partie_id');
	}

	if ($event['turnierform'] !== 'e') {
		$team_ids = [];
	}
	$i = 1;
	$last_id = false;
	$guv = NULL;
	$tabelle_ids = [];
	foreach (array_keys($tabelle) as $tabellenstand_id) {
		$tabelle_ids[] = $tabelle[$tabellenstand_id][$id];
		if (!$guv) {
			if ($tabelle[$tabellenstand_id]['spiele_g']) $guv = true;
			elseif ($tabelle[$tabellenstand_id]['spiele_u']) $guv = true;
			elseif ($tabelle[$tabellenstand_id]['spiele_v']) $guv = true;
		}
		$tabelle[$tabellenstand_id]['pfad'] = $pfad;
		$tabelle[$tabellenstand_id][str_replace('-', '_', $event['turnierform'])] = true;
		$tabelle[$tabellenstand_id]['main_series_path'] = $event['main_series_path'];
		if ($filter_kennung) {
			$tabelle[$tabellenstand_id]['no'] = $i;
			$i++;
		}
		if ($last_id AND $tabelle[$last_id]['platz_no'] === $tabelle[$tabellenstand_id]['platz_no']) {
			$tabelle[$tabellenstand_id]['platz_no_identisch'] = true;
		}
		// Teams mit nicht gespielten Partien: live markiert
		if ($event['turnierform'] !== 'e') {
			foreach ($laufende_begegnungen as $begegnung) {
				if ($begegnung['heim_team_id'] === $tabelle[$tabellenstand_id]['team_id']) {
					$tabelle[$tabellenstand_id]['live'] = true;
				} elseif ($begegnung['auswaerts_team_id'] === $tabelle[$tabellenstand_id]['team_id']) {
					$tabelle[$tabellenstand_id]['live'] = true;
				}
			}
			$team_ids[$tabelle[$tabellenstand_id]['team_id']] = [
				'team_id' => $tabelle[$tabellenstand_id]['team_id']
			];
		} else {
			foreach ($laufende_begegnungen as $begegnung) {
				if ($begegnung['weiss_person_id'] === $tabelle[$tabellenstand_id]['person_id']) {
					$tabelle[$tabellenstand_id]['live'] = true;
				} elseif ($begegnung['schwarz_person_id'] === $tabelle[$tabellenstand_id]['person_id']) {
					$tabelle[$tabellenstand_id]['live'] = true;
				}
			}
		}
		$last_id = $tabellenstand_id;
	}

	if ($event['turnierform'] !== 'e') {
		list($dwz_schnitt, $team_ids) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $team_ids, $event['bretter_min'], $event['pseudo_dwz']);
	}
	
	$k_a = true;
	foreach ($tabelle as $tabellenstand_id => $tabellenstand) {
		if ($event['turnierform'] !== 'e' AND $team_ids[$tabellenstand['team_id']]['dwz_schnitt']) {
			$tabelle[$tabellenstand_id]['dwz_schnitt'] = $team_ids[$tabellenstand['team_id']]['dwz_schnitt'];
			// Zeige DWZ-Schnitt in Überschrift abhängig von Werten in Spalte an
			if ($tabelle[$tabellenstand_id]['dwz_schnitt'] !== 'k. A.') {
				$event['dwz_schnitt'] = true;
				$k_a = true;
			} elseif (empty($event['dwz_schnitt'])) {
				$k_a = false;
			}
		}
		// Zeige Bundesland in Überschrift abhängig von Werten in Spalte an
		if ($tabellenstand['country']) $event['country'] = true;
	}
	$tabellen_keys = array_keys($tabelle);
	$decrease = 0;
	foreach ($tabelle as $tabellenstand_id => $tabellenstand) {
		if ($decrease) {
			$tabelle[$tabellenstand_id]['platz_no'] -= $decrease;
		}
		if (!$k_a) $tabelle[$tabellenstand_id]['dwz_schnitt'] = false;
		if ($event['teilnehmerliste']) $tabelle[$tabellenstand_id]['aktiv'] = 1;
		if (empty($tabellenstand['country']) AND !empty($event['country']))
			$tabelle[$tabellenstand_id]['country'] = '–';
		if ($tabellenstand['spiele_g']) $tabelle['zeige_guv'] = true;
		if (!empty($tabellenstand['setzliste_no'])) $tabelle['zeige_setzliste'] = true;
		if ($tabellenstand['status'] === 'disqualifiziert') {
			$decrease++;
			unset($tabelle[$tabellenstand_id]);
		}
	}
	foreach ($tabelle as $tabellenstand_id => $tabellenstand) {
		if (!is_numeric($tabellenstand_id)) continue;
		if (!empty($tabelle['zeige_guv'])) $tabelle[$tabellenstand_id]['zeige_guv'] = true;
		if (!empty($tabelle['zeige_setzliste'])) $tabelle[$tabellenstand_id]['zeige_setzliste'] = true;
	}

	$sql = 'SELECT tsw_id, tabellenstand_id, wertung_category_id, wertung
		FROM tabellenstaende_wertungen
		WHERE tabellenstand_id IN (%s)';
	$sql = sprintf($sql, implode(',', $tabellen_keys));
	$wertungen = wrap_db_fetch($sql, ['tabellenstand_id', 'wertung_category_id']);

	$sql = 'SELECT DISTINCT category_id, category, category_short
			, tw.reihenfolge, categories.sequence
		FROM tabellenstaende_wertungen tsw
		LEFT JOIN tabellenstaende USING (tabellenstand_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN turniere_wertungen tw
			ON tw.wertung_category_id = tsw.wertung_category_id
			AND tw.tournament_id = tournaments.tournament_id
		LEFT JOIN categories
			ON tsw.wertung_category_id = categories.category_id
		WHERE tabellenstand_id IN (%s)
		ORDER BY tw.reihenfolge, categories.sequence';
	$sql = sprintf($sql, implode(',', $tabellen_keys));
	$tabelle['wertungen'] = wrap_db_fetch($sql, 'category_id');

	foreach ($tabelle as $tabellenstand_id => $tabellenstand) {
		if (!is_numeric($tabellenstand_id)) continue;
		$tabelle[$tabellenstand_id]['guv'] = $guv;
		foreach (array_keys($tabelle['wertungen']) as $category_id) {
			if (!isset($wertungen[$tabellenstand_id][$category_id]['wertung'])) {
				$value = '';
			} else {
				$value = $wertungen[$tabellenstand_id][$category_id]['wertung'];
			}
			$tabelle[$tabellenstand_id]['wertungen'][] = [
				'wertung' => $value
			];
		}
	}
	
	// Vorige und nächste Runde?
	$sql = 'SELECT DISTINCT runde_no FROM tabellenstaende
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$runden = wrap_db_fetch($sql, 'runde_no', 'single value');
	
	if ($event['turnierform'] !== 'e') {
		// Paarungen aktuelle Runde? Falls nur Tabelle eingegen wurde
		$sql = 'SELECT DISTINCT runde_no FROM paarungen
			WHERE event_id = %d AND runde_no = %d';
		$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	} else {
		// Partien aktuelle Runde? Falls nur Tabelle eingegen wurde
		$sql = 'SELECT DISTINCT runde_no FROM partien
			WHERE event_id = %d AND runde_no = %d';
		$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	}
	$tabelle['paarungen'] = wrap_db_fetch($sql, '', 'single value');

	// Vorherige Runde
	if ($runde > 1) {
		$sql = 'SELECT platz_no, person_id, team_id
			FROM tabellenstaende
			WHERE runde_no = %d
			AND event_id = %d
			AND %s IN (%s)';
		$sql = sprintf($sql, $runde - 1, $event['event_id'], $id, implode(',', $tabelle_ids));
		$vorige_runde = wrap_db_fetch($sql, $id);
		foreach ($tabelle as $ts_id => $stand) {
			if (!is_numeric($ts_id)) continue;
			$tabelle_id = $stand[$id];
			if (empty($vorige_runde[$tabelle_id])) continue;
			if ($stand['platz_no'] > $vorige_runde[$tabelle_id]['platz_no']) {
				$tabelle[$ts_id]['platz_wechsel'] = 'schlechter';
				$tabelle[$ts_id]['platz_symbol'] = '-';
			} elseif ($stand['platz_no'] < $vorige_runde[$tabelle_id]['platz_no']) {
				$tabelle[$ts_id]['platz_wechsel'] = 'besser';
				$tabelle[$ts_id]['platz_symbol'] = '+';
			} else {
				$tabelle[$ts_id]['platz_wechsel'] = 'gleich';
				$tabelle[$ts_id]['platz_symbol'] = '=';
			}
		}
	}

	$tabelle['guv'] = $guv;
	$tabelle['event'] = $event['event'];
	$tabelle['year'] = $event['year'];
	$tabelle['runde_no'] = $event['runde_no'];
	$tabelle['country'] = !empty($event['country']) ? 1 : NULL;
	$tabelle['dwz_schnitt'] = !empty($event['dwz_schnitt']) ? 1 : NULL;
	$tabelle['vorige_runde_no'] = in_array($event['runde_no'] - 1, $runden) ? $event['runde_no'] - 1 : '';
	if ($tabelle['vorige_runde_no']) {
		$page['link']['prev'][0]['href'] = $pfad.$tabelle['vorige_runde_no'].'/';
		$page['link']['prev'][0]['title'] = $tabelle['vorige_runde_no'].'. Runde';
	}
	$tabelle['naechste_runde_no'] = in_array($event['runde_no'] + 1, $runden) ? $event['runde_no'] + 1 : '';
	if ($tabelle['naechste_runde_no']) {
		$page['link']['next'][0]['href'] = $pfad.$tabelle['naechste_runde_no'].'/';
		$page['link']['next'][0]['title'] = $tabelle['naechste_runde_no'].'. Runde';
	}
	
	$tabelle['partien'] = $event['partien'] ? $event['partien'] : NULL;
	$tabelle['duration'] = $event['duration'];
	$tabelle['turnierort'] = $event['turnierort'];
	
	$tabelle['endstand'] = $event['endstand'] ? 1 : NULL;
	$tabelle['live'] = $event['live'] ? 1 : NULL;
	$tabelle['pfad'] = $pfad;
	$tabelle[str_replace('-', '_', $event['turnierform'])] = true;

	$page['title'] = $event['event'].' '. $event['year'].', Tabellenstand nach der '.$event['runde_no'].'. Runde';
	$page['dont_show_h1'] = true;
	$page['extra']['realm'] = 'sports';
	$page['breadcrumbs'][] = '<a href="../../'.$pfad.'">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$pfad.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../'.$pfad.'">'.$event['event'].'</a>';
	if (!empty($filter['untertitel'])) {
		$page['title'] .= ' ('.$filter['untertitel'].')';
		$page['breadcrumbs'][] = '<a href="../">'.sprintf('Tabelle %s. Runde', $event['runde_no']).'</a>';
		$page['breadcrumbs'][] = $filter['untertitel'];
		$tabelle['untertitel'] = $filter['untertitel'];
		$tabelle['filter'] = $filter_kennung;
		if (!in_array($filter_kennung, ['w']))
			$page['meta'][] = ['name' => 'robots', 'content' => 'noindex'];
	} else {
		$page['breadcrumbs'][] = sprintf('Tabelle %s. Runde', $event['runde_no']);
	}
	if ($event['turnierform'] !== 'e') {
		$page['text'] = wrap_template('standings-team', $tabelle);
	} else {
		$page['text'] = wrap_template('standings-single', $tabelle);
	}
	return $page;
}
