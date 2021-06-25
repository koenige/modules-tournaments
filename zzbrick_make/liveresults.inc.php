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
 * @copyright Copyright © 2008, 2012, 2014, 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_liveresults($params) {
	global $zz_setting;
	$zz_setting['cache'] = false;
	if (count($params) !== 2) return false;

	$sql = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
			, tournament_id
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE identifier = "%d/%s"
		AND takes_place = "yes"';
	$sql = sprintf($sql, $params[0], wrap_db_escape($params[1]));
	$event = wrap_db_fetch($sql);
	if (!empty($event['tournament_id'])) {
		return mod_tournament_make_liveresults_tournament($params);
	}
	// Test, ob es eine Turnierreihe ist
	$sql = 'SELECT event_id, event, events.identifier
			, (SELECT MAX(runde_no) FROM partien WHERE event_id = events.event_id AND ISNULL(weiss_ergebnis)) AS runde_no
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE IFNULL(event_year, YEAR(date_begin)) = %d
		AND main_series.path = "reihen/%s"';
	$sql = sprintf($sql, $params[0], wrap_db_escape($params[1]));
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) return false;
	if (!$event) {
		$event['year'] = intval($params[0]);
		$sql = 'SELECT category_id, category
			FROM categories
			WHERE SUBSTRING_INDEX(path, "/", -1) = "%s"';
		$sql = sprintf($sql, wrap_db_escape($params[1]));
		$series = wrap_db_fetch($sql);
		if (!$series) return false;
		$event['event'] = $series['category'];
	}
	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	$page['breadcrumbs'][] = '<a href="../">'.$event['event'].'</a>';
	$page['breadcrumbs'][] = 'Liveergebnisse';
	$page['text'] = wrap_template('liveresults-overview', $tournaments);
	$page['title'] = 'Liveergebnisse <br><a href="../">'.$event['event'].'</a>';
	return $page;
}

/**
 * Eingabe von Turnierergebnissen live
 *
 * @param array $params
 *		[0]: Altersklasse (Spalte dateiname aus turnier_status)
 * @return array $page
 */
function mod_tournament_make_liveresults_tournament($params) {
	global $zz_conf;
	require_once $zz_conf['dir'].'/zzform.php';
	
	if (count($params) !== 2) return false;
	
	// @todo return false wenn Runde komplett (aber wann ist Runde komplett?)
	$sql = 'SELECT event_id, event, events.identifier, IFNULL(event_year, YEAR(date_begin)) AS year
			, (SELECT MAX(runde_no) FROM partien WHERE event_id = events.event_id) AS runde_no
			, (SELECT COUNT(partie_id) FROM partien WHERE event_id = events.event_id) AS partien
			, series_category_id
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform_kennung
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON turnierformen.category_id = tournaments.turnierform_category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $params[0], wrap_db_escape($params[1]));
	$turnier = wrap_db_fetch($sql);
	if (!$turnier) return false;
	
	$sql = 'SELECT IFNULL(events.identifier
			, CONCAT("%d", SUBSTRING(series.path, LENGTH(SUBSTRING_INDEX(series.path, "/", 1)) + 1))
		) AS identifier
		FROM categories series
		LEFT JOIN categories sub_series
			ON sub_series.main_category_id = series.category_id
		LEFT JOIN events
			ON series.category_id = events.series_category_id
			AND IFNULL(event_year, YEAR(events.date_begin)) = %d
		WHERE sub_series.category_id = %d
	';
	$sql = sprintf($sql, $params[0], $params[0], $turnier['series_category_id']);
	$series_identifier = wrap_db_fetch($sql, '', 'single value');

	$sql = 'SELECT partien.partie_id, partien.brett_no
			, IF(partiestatus_category_id = %d, 0.5,
				CASE weiss_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
				END
			) AS weiss_ergebnis
			, IF(partiestatus_category_id = %d, 0.5,
				CASE schwarz_ergebnis
				WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
				WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
				WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
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
		LEFT JOIN teilnahmen weiss
			ON weiss.person_id = partien.weiss_person_id
			AND weiss.event_id = partien.event_id
			AND weiss.usergroup_id = %d
		LEFT JOIN teilnahmen schwarz
			ON schwarz.person_id = partien.schwarz_person_id
			AND schwarz.event_id = partien.event_id
			AND schwarz.usergroup_id = %d
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		WHERE partien.event_id = %d
			AND partien.runde_no = %d
		ORDER by paarungen.tisch_no, partien.brett_no';
	$sql = sprintf($sql
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/haengepartie')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_category_id('partiestatus/kampflos')
		, wrap_id('usergroups', 'spieler')
		, wrap_id('usergroups', 'spieler')
		, $turnier['event_id'], $turnier['runde_no']
	);
	$turnier['ergebnisse'] = wrap_db_fetch($sql, ['paarung_id', 'partie_id'], 'list paarung_id partien');
	$partien = [];
	foreach ($turnier['ergebnisse'] as $paarung_id => $paarungen) {
		$partien += $paarungen['partien'];
		if ($paarungen['paarung_id']) {
			$erste_paarung = reset($paarungen['partien']);
			$turnier['ergebnisse'][$paarung_id]['tisch_no'] = $erste_paarung['tisch_no'];
			$turnier['ergebnisse'][$paarung_id]['heim_team'] = $erste_paarung['heim_team'];
			$turnier['ergebnisse'][$paarung_id]['auswaerts_team'] = $erste_paarung['auswaerts_team'];
			$turnier['ergebnisse'][$paarung_id]['heim_ergebnis'] = 0;
			$turnier['ergebnisse'][$paarung_id]['auswaerts_ergebnis'] = 0;
			foreach ($paarungen['partien'] as $ergebnisse) {
				$turnier['ergebnisse'][$paarung_id]['heim_ergebnis'] += $ergebnisse['heim_wertung'];
				$turnier['ergebnisse'][$paarung_id]['auswaerts_ergebnis'] += $ergebnisse['auswaerts_wertung'];
			}
		}
	}

	// Falcoify Titel
	$turnier_titel = explode(' ', $turnier['event']);
	$turnier_titel = array_reverse($turnier_titel);
	$turnier_titel = implode(' ', $turnier_titel);

	$page['title'] = $turnier_titel.' Liveergebnisse'.($turnier['runde_no'] ? ', '. $turnier['runde_no'].'. Runde' : '');

	$updated = false;
	if (!empty($_POST) AND $turnier['partien']) {
		if (empty($_POST['runde_no'])) {
			// falls altes Formular von voriger Runde gepostet wird:
			// keine Eintragungen übernehmen!
			$turnier['falsche_runde'] = true;
		} elseif ($_POST['runde_no'] != $turnier['runde_no']) {
			$turnier['falsche_runde'] = true;
		} else {
			unset($_POST['runde_no']);
			// Datenbank speichern vorbereiten
			$values = [];
			$values['action'] = 'update';
			foreach ($_POST as $partie_id => $ergebnis) {
				if ($ergebnis === '') continue;
				if (!in_array($partie_id, array_keys($partien))) {
					$turnier['falsche_runde'] = true;
					continue;
				}
				$values['POST']['partie_id'] = $partie_id;
				switch ($ergebnis) {
					case 'r': case 'R': case '5': case '0.5':
						$weiss = 0.5; $schwarz = 0.5;
						$partiestatus = 'normal';
						break;
					case '1':
						$weiss = 1; $schwarz = 0;
						$partiestatus = 'normal';
						break;
					case '+':
						$weiss = 1; $schwarz = 0;
						$partiestatus = 'kampflos';
						break;
					case '-':
						$weiss = 0; $schwarz = 1;
						$partiestatus = 'kampflos';
						break;
					case '=':
						$weiss = 0.5; $schwarz = 0.5;
						$partiestatus = 'kampflos';
						break;
					// Werte nur löschen, wenn explizit so gewollt
					case 'D': case 'd':
						$weiss = ''; $schwarz = '';
						$partiestatus = 'laufend';
						break;
					case '0':
						$weiss = 0; $schwarz = 1;
						$partiestatus = 'normal';
						break;
					default:
						continue 2;
				}
				$values['POST']['weiss_ergebnis'] = $weiss;
				$values['POST']['schwarz_ergebnis'] = $schwarz;
				$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/'.$partiestatus);
				$values['POST']['block_ergebnis_aus_pgn'] = 'ja';
				if ($turnier['turnierform_kennung'] !== 'e') {
					if ($partien[$partie_id]['heim_spieler_farbe'] === 'weiß') {
						$values['POST']['heim_wertung'] = $weiss;
						$values['POST']['auswaerts_wertung'] = $schwarz;
					} else {
						$values['POST']['heim_wertung'] = $schwarz;
						$values['POST']['auswaerts_wertung'] = $weiss;
					}
				}
				$ops = zzform_multi('partien', $values);
				if (!$ops['id']) {
					wrap_log(
						sprintf(
							'Livergebnis wurde nicht gespeichert, ID: %d, Ergebnis: %s',
							$partie_id, $ergebnis
						).implode(', ', $ops['error'])
					);
				} else {
					$updated = true;
				}
			}
		}
	}
	if ($updated) return wrap_redirect_change();

	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = '<a href="../../">'.$turnier['year'].'</a>';
	if ($series_identifier AND substr($series_identifier, -7) !== '/reihen') {
		$page['breadcrumbs'][] = '<a href="../../../'.$series_identifier.'/liveergebnisse/">Liveergebnisse</a>';
		$page['breadcrumbs'][] = $turnier['event'];
	} else {
		$page['breadcrumbs'][] =  '<a href="../">'.$turnier['event'].'</a>';
		$page['breadcrumbs'][] = 'Liveergebnisse';
	}
	$page['text'] = wrap_template('liveresults', $turnier);
	return $page;
}
