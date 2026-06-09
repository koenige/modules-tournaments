<?php 

/**
 * tournaments module
 * Standings
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2024, 2026 Gustaf Mossakowski
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
	if (empty($event['runden'])) return false;
	$event['runde_no'] = $runde;
	mf_tournaments_cache($event['duration']);

	if (wrap_setting('tournaments_type_team')) {
		$sql = 'SELECT standing_id, rank_no
				, games_won, games_drawn, games_lost
				, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
				, teams.identifier AS team_identifier, team_id
				, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
				, team_status AS status
				, teams.club_contact_id
			FROM standings
			JOIN teams USING (team_id)
			WHERE teams.event_id = %d
			AND standings.runde_no = %d
			AND spielfrei = "nein"
			ORDER BY rank_no, team, team_no
		';
		$sql = sprintf($sql, $event['event_id'], $runde);
		$id = 'team_id';
	} else {
		$sql = 'SELECT standing_id, standings.rank_no
				, games_won, games_drawn, games_lost
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, participations.setzliste_no
				, t_verein, standings.person_id
				, status_category_id AS status
				, participations.club_contact_id
			FROM standings
			JOIN tournaments USING (event_id)
			JOIN events USING (event_id)
			LEFT JOIN persons
				ON standings.person_id = persons.person_id
			LEFT JOIN participations
				ON participations.contact_id = persons.contact_id
				AND participations.event_id = standings.event_id
				AND participations.usergroup_id = /*_ID usergroups spieler _*/
			WHERE standings.event_id = %d
			AND standings.runde_no = %d
			%s
			ORDER BY rank_no
		';
		$sql = sprintf($sql
			, $event['event_id'], $runde
			, $filter['where'] ? ' AND '.implode(' AND ', $filter['where']) : ''
		);
		$id = 'person_id';
	}
	$tabelle = wrap_db_fetch($sql, 'standing_id');
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
	foreach (array_keys($tabelle) as $standing_id) {
		$tabelle_ids[] = $tabelle[$standing_id][$id];
		if (!$guv) {
			if ($tabelle[$standing_id]['games_won']) $guv = true;
			elseif ($tabelle[$standing_id]['games_drawn']) $guv = true;
			elseif ($tabelle[$standing_id]['games_lost']) $guv = true;
		}
		$tabelle[$standing_id]['pfad'] = $pfad;
		$tabelle[$standing_id][str_replace('-', '_', $event['turnierform'])] = true;
		$tabelle[$standing_id]['main_event_path'] = $event['main_event_path'];
		if ($filter_kennung) {
			$tabelle[$standing_id]['no'] = $i;
			$i++;
		}
		if ($last_id AND $tabelle[$last_id]['rank_no'] === $tabelle[$standing_id]['rank_no']) {
			$tabelle[$standing_id]['rank_tied'] = true;
		}
		// Teams mit nicht gespielten Partien: live markiert
		if (wrap_setting('tournaments_type_team')) {
			foreach ($laufende_begegnungen as $begegnung) {
				if ($begegnung['heim_team_id'] === $tabelle[$standing_id]['team_id']) {
					$tabelle[$standing_id]['live'] = true;
				} elseif ($begegnung['auswaerts_team_id'] === $tabelle[$standing_id]['team_id']) {
					$tabelle[$standing_id]['live'] = true;
				}
			}
			$team_ids[$tabelle[$standing_id]['team_id']] = [
				'team_id' => $tabelle[$standing_id]['team_id']
			];
		} else {
			foreach ($laufende_begegnungen as $begegnung) {
				if ($begegnung['weiss_person_id'] === $tabelle[$standing_id]['person_id']) {
					$tabelle[$standing_id]['live'] = true;
				} elseif ($begegnung['schwarz_person_id'] === $tabelle[$standing_id]['person_id']) {
					$tabelle[$standing_id]['live'] = true;
				}
			}
		}
		$last_id = $standing_id;
	}

	if (wrap_setting('tournaments_type_team')) {
		wrap_include('team-ratings', 'tournaments');
		list($dwz_schnitt, $team_ids) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $team_ids, $event['bretter_min'], $event['pseudo_dwz']);
	}
	
	$k_a = true;
	foreach ($tabelle as $standing_id => $standing) {
		if (wrap_setting('tournaments_type_team') AND !empty($team_ids[$standing['team_id']]['dwz_schnitt'])) {
			$tabelle[$standing_id]['dwz_schnitt'] = $team_ids[$standing['team_id']]['dwz_schnitt'];
			// Zeige DWZ-Schnitt in Überschrift abhängig von Werten in Spalte an
			if ($tabelle[$standing_id]['dwz_schnitt'] !== 'k. A.') {
				$event['dwz_schnitt'] = true;
				$k_a = true;
			} elseif (empty($event['dwz_schnitt'])) {
				$k_a = false;
			}
		}
		// Zeige Bundesland in Überschrift abhängig von Werten in Spalte an
		if (!empty($standing['country'])) $event['country'] = true;
	}
	$tabellen_keys = array_keys($tabelle);
	$decrease = 0;
	foreach ($tabelle as $standing_id => $standing) {
		if ($decrease) {
			$tabelle[$standing_id]['rank_no'] -= $decrease;
		}
		if (!$k_a) $tabelle[$standing_id]['dwz_schnitt'] = false;
		if ($event['teilnehmerliste']) $tabelle[$standing_id]['aktiv'] = 1;
		if (empty($standing['country']) AND !empty($event['country']))
			$tabelle[$standing_id]['country'] = '–';
		if ($standing['games_won']) $tabelle['zeige_guv'] = true;
		if (!empty($standing['setzliste_no'])) $tabelle['zeige_setzliste'] = true;
		if ($standing['status'].'' === wrap_category_id('participation-status/disqualified').'') {
			$decrease++;
			unset($tabelle[$standing_id]);
		}
	}
	foreach ($tabelle as $standing_id => $standing) {
		if (!is_numeric($standing_id)) continue;
		if (!empty($tabelle['zeige_guv'])) $tabelle[$standing_id]['zeige_guv'] = true;
		if (!empty($tabelle['zeige_setzliste'])) $tabelle[$standing_id]['zeige_setzliste'] = true;
	}

	$sql = 'SELECT standing_score_id, standing_id, score_category_id, score
		FROM standings_scores
		WHERE standing_id IN (%s)';
	$sql = sprintf($sql, implode(',', $tabellen_keys));
	$scores = wrap_db_fetch($sql, ['standing_id', 'score_category_id']);

	$sql = 'SELECT DISTINCT category_id, category, category_short
			, ts.sequence, categories.sequence
		FROM standings_scores
		LEFT JOIN standings USING (standing_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN tournaments_scores ts
			ON ts.score_category_id = standings_scores.score_category_id
			AND ts.tournament_id = tournaments.tournament_id
		LEFT JOIN categories
			ON standings_scores.score_category_id = categories.category_id
		WHERE standing_id IN (%s)
		ORDER BY ts.sequence, categories.sequence';
	$sql = sprintf($sql, implode(',', $tabellen_keys));
	$tabelle['scores'] = wrap_db_fetch($sql, 'category_id');

	foreach ($tabelle as $standing_id => $standing) {
		if (!is_numeric($standing_id)) continue;
		$tabelle[$standing_id]['guv'] = $guv;
		foreach (array_keys($tabelle['scores']) as $category_id) {
			if (!isset($scores[$standing_id][$category_id]['score'])) {
				$value = '';
			} else {
				$value = $scores[$standing_id][$category_id]['score'];
			}
			$tabelle[$standing_id]['scores'][] = [
				'score' => $value
			];
		}
	}
	
	// Vorige und nächste Runde?
	$sql = 'SELECT DISTINCT runde_no FROM standings
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
		$sql = 'SELECT rank_no, person_id, team_id
			FROM standings
			WHERE runde_no = %d
			AND event_id = %d
			AND %s IN (%s)';
		$sql = sprintf($sql, $runde - 1, $event['event_id'], $id, implode(',', $tabelle_ids));
		$vorige_runde = wrap_db_fetch($sql, $id);
		foreach ($tabelle as $ts_id => $stand) {
			if (!is_numeric($ts_id)) continue;
			$tabelle_id = $stand[$id];
			if (empty($vorige_runde[$tabelle_id])) continue;
			if ($stand['rank_no'] > $vorige_runde[$tabelle_id]['rank_no']) {
				$tabelle[$ts_id]['rank_change'] = 'worse';
				$tabelle[$ts_id]['rank_symbol'] = '-';
			} elseif ($stand['rank_no'] < $vorige_runde[$tabelle_id]['rank_no']) {
				$tabelle[$ts_id]['rank_change'] = 'better';
				$tabelle[$ts_id]['rank_symbol'] = '+';
			} else {
				$tabelle[$ts_id]['rank_change'] = 'equal';
				$tabelle[$ts_id]['rank_symbol'] = '=';
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
