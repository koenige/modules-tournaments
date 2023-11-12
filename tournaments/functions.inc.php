<?php 

/**
 * tournaments module
 * common functions for tournaments (not always included)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once __DIR__.'/../zzbrick_rights/access.inc.php';

function mf_tournaments_current_round($identifier) {
	$sql = 'SELECT MAX(tabellenstaende.runde_no)
		FROM events
		JOIN tabellenstaende USING (event_id)
		WHERE %s
		GROUP BY events.event_id';
	$sql = sprintf($sql
		, is_numeric($identifier)
			? sprintf('events.event_id = %d', $identifier)
			: sprintf('events.identifier = "%s"', wrap_db_escape($identifier))
	);
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
	if (!$livebretter) return false;
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
	$sql = 'SELECT participation_id, brett_no, rang_no, team_id, t_dwz
		FROM participations
		LEFT JOIN teams USING (team_id)
		WHERE participations.event_id = %d
		AND usergroup_id = %d
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND (ISNULL(spielberechtigt) OR spielberechtigt != "nein")
		AND teams.team_status = "Teilnehmer"
		ORDER BY team_id, ISNULL(brett_no), brett_no, t_dwz DESC, t_elo DESC, rang_no';
	$sql = sprintf($sql, $event_id, wrap_id('usergroups', 'spieler'));
	$dwz = wrap_db_fetch($sql, ['team_id', 'participation_id']);
	if (!$dwz) return [NULL, $teams];
	
	$event_dwz_schnitt = 0;
	$dwz_personen = 0;
	foreach (array_keys($teams) as $team_id) {
		if (!is_numeric($team_id)) continue;
		$teams[$team_id]['dwz_schnitt'] = 'k. A.';
	}
	if (!$bretter_min) {
		wrap_log('Keine Mindestbrettzahl angegeben, kann keinen DWZ-Schnitt berechnen');
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
				black_contact.contact,
				CONCAT(schwarz_status.t_vorname, " ", IFNULL(CONCAT(schwarz_status.t_namenszusatz, " "), ""), schwarz_status.t_nachname)
			) AS player_black
			, @weiss_spieler := IF(ISNULL(weiss_status.t_vorname),
				white_contact.contact,
				CONCAT(weiss_status.t_vorname, " ", IFNULL(CONCAT(weiss_status.t_namenszusatz, " "), ""), weiss_status.t_nachname)
			) AS player_white
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
		LEFT JOIN persons weiss
			ON weiss.person_id = partien.weiss_person_id
		LEFT JOIN contacts white_contact
			ON weiss.contact_id = white_contact.contact_id
		LEFT JOIN participations weiss_status
			ON weiss_status.contact_id = weiss.contact_id
			AND weiss_status.usergroup_id = %d
			AND weiss_status.event_id = %d
		LEFT JOIN persons schwarz
			ON schwarz.person_id = partien.schwarz_person_id
		LEFT JOIN contacts black_contact
			ON schwarz.contact_id = black_contact.contact_id
		LEFT JOIN participations schwarz_status
			ON schwarz_status.contact_id = schwarz.contact_id
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
	if ($event['runde_no'] != mf_tournaments_current_round($event['event_id']) + 1) return false;

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

/**
 * convert hexadecimal colors to decimal
 * for use in PDF, #CC0000 to red = 204, green = 0, blue = 0
 *
 * @param string $color
 * @return array
 */
function mf_tournaments_colors_hex2dec($color) {
	$dec['red'] = hexdec(substr($color, 1, 2));
	$dec['green'] = hexdec(substr($color, 3, 2));
	$dec['blue'] = hexdec(substr($color, 5, 2));
	return $dec;
}

/**
 * get FIDE title in full
 * FIDE-Titel auslesen in Langform
 *
 * @param string $title
 * @return string
 */
function mf_tournaments_fide_title($title) {
	static $titles = [];
	if (!$titles) {
		$sql = 'SELECT category, category_short, description
			FROM categories
			WHERE main_category_id = %d';
		$sql = sprintf($sql, wrap_category_id('fide-title'));
		$titles = wrap_db_fetch($sql, 'category_short');
	}
	if (array_key_exists($title, $titles)) return $titles[$title]['category'];
	return '';
}

/**
 * add hyphens in long titles (for PDF export)
 *
 * @param string $title
 * @return string
 */
function mf_tournaments_event_title_wrap($title) {
	$title = explode(' ', $title);
	foreach ($title as $pos => &$word) {
		if (strlen($word) < 21) continue;
		if (strstr($word, '-')) {
			$word = str_replace('-', "- ", $word);
			continue;
		}
		if (strstr($word, 'meisterschaft')) {
			$word = str_replace('meisterschaft', '- meisterschaft', $word);
			continue;
		}
	}
	$title = implode(' ', $title);
	return $title;
}

/**
 * read chess PGN file from URL
 *
 * @param int $tournament_id
 * @return string
 */
function mf_tournaments_pgn_file_from_tournament($tournament_id) {
	$sql = 'SELECT urkunde_parameter
		FROM tournaments
		WHERE tournament_id = %d';
	$sql = sprintf($sql, $tournament_id);
	$parameters = wrap_db_fetch($sql, '', 'single value');
	if (!$parameters) return '';

	parse_str($parameters, $parameters);
	if (empty($parameters['tournaments_pgn_paths'])) return '';

	$pgn = '';
	foreach ($parameters['tournaments_pgn_paths'] as $path) {
		if (in_array(substr($path, 0, 1), ['/', '.'])) {
			// local path
			$path = wrap_setting('root_dir').$path;
			if (!file_exists($path)) continue;
		}
		if ($content = file_get_contents($path)) {
			$pgn .= $content;
		}
	}
	return $pgn;
}

/**
 * read identifiers of persons per federation
 * Kennungen einzelner Verbände auslesen
 *
 * @param array $players indexed by person_id
 * @param array $categories
 * @return array
 */
function mf_tournaments_person_identifiers($players, $categories) {
	if (!$players) return $players;
	$sql = 'SELECT person_id
			, contacts_identifiers.identifier
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
		FROM contacts_identifiers
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN categories
			ON contacts_identifiers.identifier_category_id = categories.category_id
		WHERE person_id IN (%s) AND current = "yes"';
	$sql = sprintf($sql, implode(',', array_keys($players)));
	$identifiers = wrap_db_fetch($sql, ['person_id', 'category', 'identifier'], 'key/value');
	foreach ($identifiers as $person_id => $pk) {
		foreach ($categories as $category) {
			$key = str_replace('-', '_', $category);
			if (!array_key_exists($category, $pk)) continue;
			$players[$person_id][$key] = $pk[$category];
		}
	}
	return $players;
}

/**
 * get federation per club
 *
 * @param array $data
 * @param string $field_name (optional)
 * @return array
 */
function mf_tournaments_clubs_to_federations($data, $field_name = 'club_contact_id') {
	$mode = 'list';
	if (!is_numeric(key($data))) {
		$mode = 'single';
		$data = [$data];
	}

	$clubs = [];
	foreach ($data as $id => $line) {
		if (is_numeric(key($line))) {
			$mode = 'multi';
			foreach ($line as $sub_id => $sub_line) {
				if (!$sub_line[$field_name]) continue;
				$clubs[$id.'-'.$sub_id] = $sub_line[$field_name];
			}
		} elseif ($line[$field_name]) {
			$clubs[$id] = $line[$field_name];
		}
	}
	if (!$clubs) {
		if ($mode === 'single')
			$data = reset($data);
		return $data;
	}

	$sql = sprintf('SELECT organisationen.contact_id
			, countries.country
			, IFNULL(landesverbaende.identifier, landesverbaende_rueckwaerts.identifier) AS federation_identifier
			, IFNULL(landesverbaende.contact_abbr, landesverbaende_rueckwaerts.contact_abbr) AS federation_abbr
			, v_ok.identifier AS zps_code
			, regionalgruppe
		FROM contacts organisationen
		LEFT JOIN contacts_identifiers v_ok
			ON v_ok.contact_id = organisationen.contact_id
			AND v_ok.current = "yes"
		LEFT JOIN contacts_identifiers lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
			AND lv_ok.current = "yes"
		LEFT JOIN contacts landesverbaende
			ON lv_ok.contact_id = landesverbaende.contact_id
			AND landesverbaende.mother_contact_id = %d
		LEFT JOIN countries
			ON IFNULL(landesverbaende.country_id, organisationen.country_id) 
				= countries.country_id
		LEFT JOIN contacts landesverbaende_rueckwaerts
			ON countries.country_id = landesverbaende_rueckwaerts.country_id
			AND landesverbaende_rueckwaerts.contact_category_id = %d
			AND landesverbaende_rueckwaerts.mother_contact_id = %d
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = landesverbaende.contact_id
		WHERE organisationen.contact_id IN (%s)
	', wrap_setting('contact_ids[dsb]')
		, wrap_category_id('contact/federation')
		, wrap_setting('contact_ids[dsb]')
		, implode(', ', $clubs)
	);
	$clubdata = wrap_db_fetch($sql, 'contact_id');
	foreach ($clubs as $id => $contact_id) {
		unset($clubdata[$contact_id]['contact_id']);
		if ($mode === 'multi') {
			$id = explode('-', $id);
			$data[$id[0]][$id[1]] += $clubdata[$contact_id];
		} else {
			foreach (array_keys($clubdata[$contact_id]) as $field) {
				if (empty($data[$id][$field])) unset($data[$id][$field]);
			}
			$data[$id] += $clubdata[$contact_id];
		}
	}
	if ($mode === 'single')
		$data = reset($data);
	
	return $data;
}


/*
 * -------------------------
 * Teams
 * -------------------------
 */

/**
 * Buchungen zu einem Team / mehreren Teams
 *
 * @param mixed $team_ids (int = eine Team-ID, array = mehrere Team-IDs)
 * @param array $event
 *		int dauer_tage, int bretter_min
 * @return array $daten
 */
function mf_tournaments_team_bookings($team_ids, $event) {
	$sql = 'SELECT team_id, buchung_id
			, gruppe, anzahl_tage, anzahl_maennlich, anzahl_weiblich
			, IFNULL(kosten, buchung) AS kosten
			, kosten_betrag, kosten_waehrung
			, betrag, betrag_waehrung
			, anmerkungen
			, (CASE WHEN kosten_status = "offen" THEN "vielleicht"
				WHEN kosten_status = "gelöscht" THEN "nein"
				WHEN kosten_status = "befreit" THEN "befreit"
				WHEN kosten_status = "bestätigt" THEN "ja"
				END) AS kosten_status
			, categories.path AS buchungskategorie
		FROM buchungen
		LEFT JOIN costs USING (cost_id)
		LEFT JOIN categories
			ON categories.category_id = buchungen.buchung_category_id
		WHERE team_id IN (%s)
		ORDER BY categories.sequence, category, gruppe';
	$sql = sprintf($sql, is_array($team_ids) ? implode(',', $team_ids) : $team_ids);
	$alle_kosten = wrap_db_fetch($sql, ['team_id', 'buchung_id']);
	if (!$alle_kosten) return [];
	$teams = [];
	foreach ($alle_kosten as $id => $team_kosten) {
		$teams[$id]['betrag'] = 0;
		$teams[$id]['tage_teilnehmer'] = 0;
		$teams[$id]['tage_betreuer'] = 0;
		$teams[$id]['kosten'] = $team_kosten;
		foreach ($team_kosten as $k_id => $kosten) {
			// Bedingung für komplett:
			// min. min_spieler * dauer_tage für Teilnehmer
			// min. dauer_tage für Betreuer
			if ($kosten['kosten_status'] === 'nein' OR $kosten['kosten_status'] === 'befreit') {
				$teams[$id]['kosten'][$k_id]['betrag'] = 0;
				$teams[$id]['kosten'][$k_id]['gelöscht'] = true;
				continue;
			}
			$tage = $kosten['anzahl_tage'] * ($kosten['anzahl_maennlich'] + $kosten['anzahl_weiblich']);
			if ($kosten['gruppe'] === 'Teilnehmer') {
				$teams[$id]['tage_teilnehmer'] += $tage;
			} elseif ($kosten['gruppe'] === 'Betreuer') {
				$teams[$id]['tage_betreuer'] += $tage;
			}
			$teams[$id]['betrag'] += $kosten['betrag'];
			$teams[$id]['betrag_waehrung'] = $kosten['betrag_waehrung'];
		}
		if ($teams[$id]['tage_betreuer'] >= $event['dauer_tage']
			AND $teams[$id]['tage_teilnehmer'] >= ($event['dauer_tage'] * $event['bretter_min'])) {
			$teams[$id]['buchung_komplett'] = true;	
		}
	}
	if (is_array($team_ids)) return $teams;
	else return $teams[$team_ids];
}


/**
 * Vereinsbetreuer zu einem Team 
 *
 * @param array $team_ids
 *		array team_id => contact_id
 * @param array $event
 * @param bool $check true (default): check Anzahl, Berechtigungen etc.
 * @return array
 */
function mf_tournaments_team_participants($team_ids, $event, $check = true, $order_by = 'ISNULL(brett_no), brett_no, rang_no, last_name, first_name') {
	// Nur Teams, die Organisationen zugeordnet sind
	// (bspw. bei Schulschach nicht zwingend nötig)
	$contact_ids = $team_ids;
	foreach ($contact_ids as $team_id => $contact_id) {
		if (!$contact_id) unset($contact_ids[$team_id]);
	}
	if ($contact_ids) {
		$sql = 'SELECT club_contact_id, participation_id, person_id, contacts.contact_id
				, usergroups.usergroup
				, usergroups.identifier AS group_identifier
				, contact AS person
				, YEAR(date_of_birth) AS geburtsjahr
				, (SELECT identification FROM contactdetails
					WHERE contactdetails.contact_id = contacts.contact_id
					AND provider_category_id = %d
					LIMIT 1
				) AS e_mail
				, GROUP_CONCAT(category_short, ": ", identification SEPARATOR "<br>") AS telefon
			FROM participations
			LEFT JOIN persons USING (contact_id)
			LEFT JOIN contacts USING (contact_id)
			LEFT JOIN contactdetails USING (contact_id)
			LEFT JOIN usergroups USING (usergroup_id)
			LEFT JOIN categories
				ON categories.category_id = contactdetails.provider_category_id
				AND (ISNULL(categories.parameters) OR categories.parameters LIKE "%%&type=phone%%")
			WHERE club_contact_id IN (%s)
			AND usergroup_id IN (%d, %d)
			GROUP BY participation_id';
		$sql = sprintf($sql
			, wrap_category_id('provider/e-mail')
			, implode(',', $contact_ids)
			, wrap_id('usergroups', 'verein-jugend')
			, wrap_id('usergroups', 'verein-vorsitz')
		);
		$vereinsbetreuer = wrap_db_fetch($sql, ['club_contact_id', 'group_identifier', 'participation_id']);
	}

	$sql = 'SELECT team_id, participation_id, persons.person_id, contacts.contact_id
			, usergroups.usergroup
			, usergroups.identifier AS group_identifier
			, contact AS person
			, (CASE WHEN sex = "female" THEN "W"
				WHEN sex = "male" THEN "M"
				WHEN sex = "diverse" THEN "D"
				ELSE NULL END
			) AS geschlecht
			, YEAR(date_of_birth) AS geburtsjahr
			, t_dwz, t_elo, t_fidetitel, rang_no, brett_no
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler
			, (SELECT identification FROM contactdetails
				WHERE contactdetails.contact_id = contacts.contact_id
				AND provider_category_id = %d
				LIMIT 1
			) AS e_mail
			, GROUP_CONCAT(category_short, ": ", identification SEPARATOR "<br>") AS telefon
			, (CASE WHEN spielberechtigt = "vorläufig nein" THEN "vielleicht"
				WHEN spielberechtigt = "nein" THEN "nein"
				WHEN spielberechtigt = "ja" THEN "ja"
				ELSE NULL
				END) AS status, spielberechtigt
			, contacts_identifiers.identifier AS zps_code
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN contactdetails USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN categories
			ON categories.category_id = contactdetails.provider_category_id
			AND (ISNULL(categories.parameters) OR categories.parameters LIKE "%%&type=phone%%")
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = persons.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		WHERE team_id IN (%s)
		GROUP BY participation_id, contact_identifier_id
		ORDER BY %s';
	$sql = sprintf($sql
		, wrap_category_id('provider/e-mail')
		, wrap_category_id('identifiers/zps')
		, implode(',', array_keys($team_ids))
		, $order_by
	);
	$participations = wrap_db_fetch($sql, ['team_id', 'group_identifier', 'participation_id']);

	foreach ($team_ids as $team_id => $club_contact_id) {
		if (!empty($vereinsbetreuer[$club_contact_id])) {
			$participations[$team_id] = array_merge($participations[$team_id], $vereinsbetreuer[$club_contact_id]);
		}
	}
	if (!$check) {
		if (count($team_ids) === 1) {
			$participations = reset($participations);
			if (!$participations) $participations = [];
		}
		return $participations;
	}

	foreach (array_keys($participations) as $id) {
		if (!isset($participations[$id]['spieler'])) $participations[$id]['spieler'] = [];
		$participations[$id]['spielerzahl'] = count($participations[$id]['spieler']);
		if ($participations[$id]['spielerzahl'] >= $event['bretter_min'])
			$participations[$id]['aufstellung_komplett'] = true;
		while (count($participations[$id]['spieler']) < $event['bretter_max']) {
			$participations[$id]['spieler'][] = [
				'person' => '--',
				'add' => 1,
			];
		}
		$i = 0;
		$participations[$id]['zps_codes'] = [];
		$aeltester_spieler = 0;
		foreach (array_keys($participations[$id]['spieler']) as $spieler_id) {
			$i++;
			$participations[$id]['spieler'][$spieler_id]['pflicht'] = ($i <= $event['bretter_min']) ? true : false;
			$participations[$id]['spieler'][$spieler_id]['position'] = $i;
			if (!empty($event['gastspieler_status']))
				$participations[$id]['spieler'][$spieler_id]['gastspieler_status'] = 1;
			if (!empty($participations[$id]['spieler'][$spieler_id]['zps_code']))
				$participations[$id]['zps_codes'][] = $participations[$id]['spieler'][$spieler_id]['zps_code'];
			if (empty($participations[$id]['spieler'][$spieler_id]['geburtsjahr'])) continue;
			if ($participations[$id]['spieler'][$spieler_id]['geburtsjahr'] > $aeltester_spieler) {
				$aeltester_spieler = $participations[$id]['spieler'][$spieler_id]['geburtsjahr'];
			}
		}
		if ($contact_ids) {
			if (!isset($participations[$id]['verein-vorsitz'])) {
				$participations[$id]['verein-vorsitz'][] = [
					'person' => '--',
					'add' => 1
				];
			}
		}
		if (!isset($participations[$id]['betreuer'])) {
			$participations[$id]['betreuer'][] = [
				'person' => '--',
				'add' => 1
			];
		} else {
			$participations[$id]['betreuer_komplett'] = true;
			$aeltester_betreuer = 3000; // im Jahr 3000 müssen wir hier neu ran!
			foreach ($participations[$id]['betreuer'] as $betreuer) {
				if ($betreuer['geburtsjahr'] AND $betreuer['geburtsjahr'] < $aeltester_betreuer) {
					$aeltester_betreuer = $betreuer['geburtsjahr'];
				}
			}
			if ($aeltester_betreuer > date('Y') - 18) {
				// muss volljährig sein
				$participations[$id]['betreuer_komplett'] = false;
				$participations[$id]['betreuer_nicht_18'] = true;
			}
			if ($aeltester_betreuer > $aeltester_spieler - 3) {
				// muss drei Jahre älter als ältester Spieler sein
				$participations[$id]['betreuer_komplett'] = false;
				$participations[$id]['betreuer_nicht_plus_3_jahre'] = true;
			}
		}
	}
	if (count($team_ids) === 1) {
		$participations = reset($participations);
		if (!$participations) $participations = [];
	}
	return $participations;
}

/**
 * Team-Meldung komplett?
 *
 * @param array $data
 * @return bool
 */
function mf_tournaments_team_application_complete($data) {
	if ((!empty($data['betreuer_komplett']) OR !empty($data['virtual']))
		AND (!empty($data['reisedaten_komplett']) OR !empty($data['virtual']))
		AND !empty($data['aufstellung_komplett'])
		AND (empty($data['zimmerbuchung']) OR !empty($data['buchung_komplett']))
	) {
		return true;
	} elseif ($data['meldung'] === 'komplett') {
		// Falls Voraussetzungen nicht erfüllt werden (z. B. Teilnehmer
		// in der U20 gleichzeitig Betreuer) kann Veranstalter noch
		// auf komplett umstellen und man kriegt trotzdem ein PDF ohne
		// Vorschau-Hintergrund raus.
		return true;
	}
	return false;
}
