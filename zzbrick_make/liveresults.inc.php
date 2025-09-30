<?php 

/**
 * tournaments module
 * entering tournament results live
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Erik Kothe
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2008 Erik Kothe
 * @copyright Copyright © 2008, 2012, 2014, 2016-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_liveresults($params, $settings, $event) {
	wrap_setting('cache', false);
	if (count($params) !== 2) return false;

	$sql = 'SELECT tournament_id
		FROM tournaments
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$tournament_id = wrap_db_fetch($sql, '', 'single value');
	if ($tournament_id)
		return mod_tournament_make_liveresults_tournament($event);
	
	// Test, ob es eine Turnierreihe ist
	$sql = 'SELECT event_id, event, events.identifier
			, (SELECT MAX(runde_no) FROM partien
				WHERE event_id = events.event_id AND ISNULL(weiss_ergebnis)
			) AS runde_no
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE main_event_id = %d
		AND events.event_category_id = /*_ID categories event/event _*/
		ORDER BY series.sequence, events.identifier';
	$sql = sprintf($sql, $event['event_id']);
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) return false;

	$page['breadcrumbs'][]['title'] = 'Liveergebnisse';
	$page['text'] = wrap_template('liveresults-overview', $tournaments);
	$page['title'] = 'Liveergebnisse <br><a href="../">'.$event['event'].'</a>';
	return $page;
}

/**
 * Eingabe von Turnierergebnissen live
 *
 * @param array $event
 * @return array $page
 */
function mod_tournament_make_liveresults_tournament($event) {
	// @todo return false wenn Runde komplett (aber wann ist Runde komplett?)
	$sql = 'SELECT MAX(runde_no) FROM partien WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['runde_no'] = wrap_db_fetch($sql, '', 'single value');

	$sql = 'SELECT COUNT(*) FROM partien WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['partien'] = wrap_db_fetch($sql, '', 'single value');
		
	$sql = 'SELECT partien.partie_id, partien.brett_no
			, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
				CASE weiss_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
				END
			) AS weiss_ergebnis
			, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
				CASE schwarz_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
				END
			) AS schwarz_ergebnis
			, IF(NOT ISNULL(weiss_ergebnis), 1, NULL) AS gespeichert
			, CONCAT(weiss.t_vorname, " ", IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname) AS weiss
			, CONCAT(schwarz.t_vorname, " ", IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname) AS schwarz
			, paarung_id
			, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), "")) AS heim_team
			, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), "")) AS auswaerts_team
			, paarungen.tisch_no
			, IF(heim_spieler_farbe = "schwarz", 1, NULL) AS heim_schwarz
			, heim_wertung, auswaerts_wertung, heim_spieler_farbe
		FROM partien
		LEFT JOIN persons white_persons
			ON partien.weiss_person_id = white_persons.person_id
		LEFT JOIN participations weiss
			ON weiss.contact_id = white_persons.contact_id
			AND weiss.event_id = partien.event_id
			AND weiss.usergroup_id = /*_ID usergroups spieler _*/
		LEFT JOIN persons black_persons
			ON partien.schwarz_person_id = black_persons.person_id
		LEFT JOIN participations schwarz
			ON schwarz.contact_id = black_persons.contact_id
			AND schwarz.event_id = partien.event_id
			AND schwarz.usergroup_id = /*_ID usergroups spieler _*/
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		WHERE partien.event_id = %d
			AND partien.runde_no = %d
		ORDER by paarungen.tisch_no, partien.brett_no';
	$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	$event['ergebnisse'] = wrap_db_fetch($sql, ['paarung_id', 'partie_id'], 'list paarung_id partien');
	$games = [];
	foreach ($event['ergebnisse'] as $paarung_id => $paarungen) {
		$games += $paarungen['partien'];
		if ($paarungen['paarung_id']) {
			$erste_paarung = reset($paarungen['partien']);
			$event['ergebnisse'][$paarung_id]['tisch_no'] = $erste_paarung['tisch_no'];
			$event['ergebnisse'][$paarung_id]['heim_team'] = $erste_paarung['heim_team'];
			$event['ergebnisse'][$paarung_id]['auswaerts_team'] = $erste_paarung['auswaerts_team'];
			$event['ergebnisse'][$paarung_id]['heim_ergebnis'] = 0;
			$event['ergebnisse'][$paarung_id]['auswaerts_ergebnis'] = 0;
			foreach ($paarungen['partien'] as $ergebnisse) {
				$event['ergebnisse'][$paarung_id]['heim_ergebnis'] += $ergebnisse['heim_wertung'];
				$event['ergebnisse'][$paarung_id]['auswaerts_ergebnis'] += $ergebnisse['auswaerts_wertung'];
			}
		}
	}

	// Falcoify Titel
	$event_title = explode(' ', $event['event']);
	$event_title = array_reverse($event_title);
	$event_title = implode(' ', $event_title);

	$page['title'] = $event_title.' Liveergebnisse'.($event['runde_no'] ? ', '. $event['runde_no'].'. Runde' : '');

	$updated = false;
	if (!empty($_POST) AND $event['partien']) {
		if (empty($_POST['runde_no'])) {
			// falls altes Formular von voriger Runde gepostet wird:
			// keine Eintragungen übernehmen!
			$event['falsche_runde'] = true;
		} elseif ($_POST['runde_no'] != $event['runde_no']) {
			$event['falsche_runde'] = true;
		} else {
			unset($_POST['runde_no']);
			// Datenbank speichern vorbereiten
			foreach ($_POST as $game_id => $ergebnis) {
				if ($ergebnis === '') continue;
				if (!in_array($game_id, array_keys($games))) {
					$event['falsche_runde'] = true;
					continue;
				}
				switch ($ergebnis) {
					case 'r': case 'R': case '5': case '0.5':
						$white = 0.5; $black = 0.5;
						$status = 'normal';
						break;
					case '1':
						$white = 1; $black = 0;
						$status = 'normal';
						break;
					case '+':
						$white = 1; $black = 0;
						$status = 'kampflos';
						break;
					case '-':
						$white = 0; $black = 1;
						$status = 'kampflos';
						break;
					case '=':
						$white = 0.5; $black = 0.5;
						$status = 'kampflos';
						break;
					// Werte nur löschen, wenn explizit so gewollt
					case 'D': case 'd':
						$white = ''; $black = '';
						$status = 'laufend';
						break;
					case '0':
						$white = 0; $black = 1;
						$status = 'normal';
						break;
					default:
						continue 2;
				}
				$line = [
					'partie_id' => $game_id,
					'weiss_ergebnis' => $white,
					'schwarz_ergebnis' => $black,
					'partiestatus_category_id' => wrap_category_id('partiestatus/'.$status),
					'block_ergebnis_aus_pgn' => 'ja'
				];
				if (wrap_setting('tournaments_type_team')) {
					if ($games[$game_id]['heim_spieler_farbe'] === 'weiß') {
						$line['heim_wertung'] = $white;
						$line['auswaerts_wertung'] = $black;
					} else {
						$line['heim_wertung'] = $black;
						$line['auswaerts_wertung'] = $white;
					}
				}
				$success = zzform_update('partien', $line, E_USER_NOTICE, ['msg' => wrap_text('Live result was not saved')]);
				if ($success) $updated = true;
			}
		}
	}
	if ($updated) return wrap_redirect_change();

	$page['dont_show_h1'] = true;
	if ($event['main_event_path']) {
		$page['breadcrumbs'][] = ['title' => 'Liveergebnisse', 'url_path' => wrap_path('tournaments_liveresults', $event['main_event_path'])];
		$page['breadcrumbs'][]['title'] = $event['event'];
	} else {
		$page['breadcrumbs'][]['title'] = 'Liveergebnisse';
	}
	$page['text'] = wrap_template('liveresults', $event);
	return $page;
}
