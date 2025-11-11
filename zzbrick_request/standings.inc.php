<?php 

/**
 * tournaments module
 * Standings
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2024 Gustaf Mossakowski
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
 * @param array $settings
 * @param array $event
 * @return array
 */
function mod_tournaments_standings($vars, $settings, $event) {
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
			$runde = mf_tournaments_current_round($event['event_id']);
		} else {
			if (sprintf('%d', $runde) !== $runde.'') return false;
		}
		$pfad = '../';
	} elseif (count($vars) === 2) {
		// Letzte Runde
		$runde = mf_tournaments_current_round($event['event_id']);
		$pfad = '';
	} else {
		return false;
	}

	$filter = mf_tournaments_standings_filter($filter_kennung);
	if ($filter['error']) return false;

	$sql = 'SELECT tournaments.runden, pseudo_dwz
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, (SELECT COUNT(*) FROM partien
				WHERE partien.event_id = events.event_id
				AND partien.runde_no = %d
			) AS partien
			, @live := (SELECT IF(COUNT(*), 1, NULL) FROM partien
				WHERE partien.event_id = events.event_id
				AND ISNULL(weiss_ergebnis)
				AND partien.runde_no = %d
			) AS live
			, IF(ISNULL(@live) AND tournaments.runden = %d, 1, NULL) AS endstand
		FROM events
		JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = /*_SETTING website_id _*/
		WHERE events.event_id = %d';
	$sql = sprintf($sql, $runde, $runde, $runde, $event['event_id']);
	$event = array_merge($event, wrap_db_fetch($sql));
	if (!$event['runden']) return false;
	$event['runde_no'] = $runde;
	mf_tournaments_cache($event['duration']);

	if (wrap_setting('tournaments_type_team')) {
		$sql = 'SELECT tabellenstand_id, platz_no
				, spiele_g, spiele_u, spiele_v
				, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
				, teams.identifier AS team_identifier, team_id
				, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
				, team_status AS status
				, teams.club_contact_id
			FROM tabellenstaende
			JOIN teams USING (team_id)
			WHERE teams.event_id = %d
			AND tabellenstaende.runde_no = %d
			AND spielfrei = "nein"
			ORDER BY platz_no, team, team_no
		';
		$sql = sprintf($sql, $event['event_id'], $runde);
		$id = 'team_id';
	} else {
		$sql = 'SELECT tabellenstand_id, tabellenstaende.platz_no
				, spiele_g, spiele_u, spiele_v
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, participations.setzliste_no
				, t_verein, tabellenstaende.person_id
				, status_category_id AS status
				, participations.club_contact_id
			FROM tabellenstaende
			JOIN tournaments USING (event_id)
			JOIN events USING (event_id)
			LEFT JOIN persons
				ON tabellenstaende.person_id = persons.person_id
			LEFT JOIN participations
				ON participations.contact_id = persons.contact_id
				AND participations.event_id = tabellenstaende.event_id
				AND participations.usergroup_id = /*_ID usergroups spieler _*/
			WHERE tabellenstaende.event_id = %d
			AND tabellenstaende.runde_no = %d
			%s
			ORDER BY platz_no
		';
		$sql = sprintf($sql
			, $event['event_id'], $runde
			, $filter['where'] ? ' AND '.implode(' AND ', $filter['where']) : ''
		);
		$id = 'person_id';
	}
	$tabelle = wrap_db_fetch($sql, 'tabellenstand_id');
	if (!$tabelle) return false;
	$tabelle = mf_tournaments_clubs_to_federations($tabelle, 'club_contact_id');

	if ($event['live'] AND wrap_setting('tournaments_type_team')) {
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

	if (wrap_setting('tournaments_type_team'))
		$team_ids = [];
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
		$tabelle[$tabellenstand_id]['main_event_path'] = $event['main_event_path'];
		if ($filter_kennung) {
			$tabelle[$tabellenstand_id]['no'] = $i;
			$i++;
		}
		if ($last_id AND $tabelle[$last_id]['platz_no'] === $tabelle[$tabellenstand_id]['platz_no']) {
			$tabelle[$tabellenstand_id]['platz_no_identisch'] = true;
		}
		// Teams mit nicht gespielten Partien: live markiert
		if (wrap_setting('tournaments_type_team')) {
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

	if (wrap_setting('tournaments_type_team')) {
		wrap_include('team', 'tournaments');
		list($dwz_schnitt, $team_ids) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $team_ids, $event['bretter_min'], $event['pseudo_dwz']);
	}
	
	$k_a = true;
	foreach ($tabelle as $tabellenstand_id => $tabellenstand) {
		if (wrap_setting('tournaments_type_team') AND !empty($team_ids[$tabellenstand['team_id']]['dwz_schnitt'])) {
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
		if (!empty($tabellenstand['country'])) $event['country'] = true;
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
		if ($tabellenstand['status'].'' === wrap_category_id('participation-status/disqualified').'') {
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
	
	if (wrap_setting('tournaments_type_team')) {
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
				$tabelle[$ts_id]['platz_wechsel'] = 'worse';
				$tabelle[$ts_id]['platz_symbol'] = '-';
			} elseif ($stand['platz_no'] < $vorige_runde[$tabelle_id]['platz_no']) {
				$tabelle[$ts_id]['platz_wechsel'] = 'better';
				$tabelle[$ts_id]['platz_symbol'] = '+';
			} else {
				$tabelle[$ts_id]['platz_wechsel'] = 'equal';
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
	$tabelle['place'] = $event['place'];
	
	$tabelle['endstand'] = $event['endstand'] ? 1 : NULL;
	$tabelle['live'] = $event['live'] ? 1 : NULL;
	$tabelle['pfad'] = $pfad;
	$tabelle[str_replace('-', '_', $event['turnierform'])] = true;

	$page['title'] = $event['event'].' '. $event['year'].', Tabellenstand nach der '.$event['runde_no'].'. Runde';
	$page['dont_show_h1'] = true;
	if (!empty($filter['untertitel'])) {
		$page['title'] .= ' ('.$filter['untertitel'].')';
		$page['breadcrumbs'][] = ['title' => sprintf('Tabelle %s. Runde', $event['runde_no']), 'url_path' => '../'];
		$page['breadcrumbs'][]['title'] = $filter['untertitel'];
		$tabelle['untertitel'] = $filter['untertitel'];
		$tabelle['filter'] = $filter_kennung;
		if (!in_array($filter_kennung, ['w']))
			$page['meta'][] = ['name' => 'robots', 'content' => 'noindex'];
	} else {
		$page['breadcrumbs'][]['title'] = sprintf('Tabelle %s. Runde', $event['runde_no']);
	}
	if (wrap_setting('tournaments_type_team')) {
		$page['text'] = wrap_template('standings-team', $tabelle);
	} else {
		$page['text'] = wrap_template('standings-single', $tabelle);
	}
	return $page;
}
