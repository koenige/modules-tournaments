<?php 

/**
 * tournaments module
 * common functions for tournaments (not always included)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2025 Gustaf Mossakowski
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
		AND usergroup_id = /*_ID usergroups spieler _*/
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND (ISNULL(spielberechtigt) OR spielberechtigt != "nein")
		AND teams.team_status = "Teilnehmer"
		ORDER BY team_id, ISNULL(brett_no), brett_no, t_dwz DESC, t_elo DESC, rang_no';
	$sql = sprintf($sql, $event_id);
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
 * get last update for a round
 *
 * @param array $data
 * @param string $last_update
 * @return string
 */
function mf_tournaments_last_update($data, $last_update = '') {
	foreach ($data as $line)
		if ($line['last_update'] > $last_update) $last_update = $line['last_update'];
	return $last_update;
}

/**
 * sende anderen Cache-Control-Header während Turnier
 *
 * @param string $duration
 * @return void
 */
function mf_tournaments_cache($duration) {
	$duration = explode('/', $duration);
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
			WHERE main_category_id = /*_ID categories fide-title _*/';
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
 * @return array
 */
function mf_tournaments_person_identifiers($players) {
	if (!$players) return $players;
	$sql = 'SELECT person_id
			, contacts_identifiers.identifier
			, CONCAT("player_"
				, IFNULL(
					SUBSTRING_INDEX(SUBSTRING_INDEX(categories.parameters, "&alias=identifiers/", -1), "&", 1),
					SUBSTRING_INDEX(categories.path, "/", -1)
				)
			) AS category
		FROM contacts_identifiers
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN categories
			ON contacts_identifiers.identifier_category_id = categories.category_id
		WHERE person_id IN (%s) AND current = "yes"';
	$sql = sprintf($sql, implode(',', array_keys($players)));
	$identifiers = wrap_db_fetch($sql, ['person_id', 'category', 'identifier'], 'key/value');
	foreach ($identifiers as $person_id => $identifiers_per_person)
		$players[$person_id] = array_merge($players[$person_id], $identifiers_per_person);
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
	
	// get federations
	$sql = 'SELECT contacts.contact_id AS federation_contact_id
			, SUBSTRING(contacts_identifiers.identifier, 1, 1) AS federation_code
			, contacts.identifier AS federation_identifier
			, contact_abbr AS federation_abbr
			, country_id, country
			, regionalgruppe
	    FROM contacts
	    LEFT JOIN contacts_contacts USING (contact_id)
	    LEFT JOIN countries USING (country_id)
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = contacts.contact_id
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
	    WHERE contacts_contacts.main_contact_id = /*_SETTING clubs_confederation_contact_id _*/
	    AND contacts_contacts.relation_category_id = /*_ID categories relation/member _*/
	    AND contacts.contact_category_id = /*_ID categories contact/federation _*/';
	$federations = wrap_db_fetch($sql, 'federation_contact_id');
	$federations_by_zps = [];
	$federations_by_country = [];
	foreach ($federations as $federation) {
		$federations_by_zps[$federation['federation_code']] = $federation;
		$federations_by_country[$federation['country_id']] = $federation;
	}

	// get country and federation per contact
	$sql = 'SELECT contacts.contact_id
			, country_id
			, contacts_identifiers.identifier AS zps_code
	    FROM contacts
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
	    WHERE contacts.contact_id IN (%s)';
	$sql = sprintf($sql
		, implode(', ', $clubs)
	);
	$contacts = wrap_db_fetch($sql, 'contact_id');

	// merge contacts and federations
	foreach ($contacts as $contact_id => $contact) {
		$fed_code = $contact['zps_code'] ? substr($contact['zps_code'], 0, 1) : '';
		if ($fed_code AND array_key_exists($fed_code, $federations_by_zps)) {
			$contacts[$contact_id] = array_merge($contact, $federations_by_zps[$fed_code]);
		} elseif ($contact['country_id'] AND array_key_exists($contact['country_id'], $federations_by_country)) {
			$contacts[$contact_id] = array_merge($contact, $federations_by_country[$contact['country_id']]);
		} else {
			wrap_error(wrap_text('Unable to get federation for contact ID %d', ['values' => $contact_id]));
		}
		unset($contacts[$contact_id]['contact_id']);
	}

	// merge with $data
	foreach ($clubs as $id => $contact_id) {
		if ($mode === 'multi') {
			$id = explode('-', $id);
			$data[$id[0]][$id[1]] += $contacts[$contact_id];
		} else {
			foreach (array_keys($contacts[$contact_id]) as $field) {
				if (empty($data[$id][$field])) unset($data[$id][$field]);
			}
			$data[$id] += $contacts[$contact_id];
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
			, IFNULL(product, buchung) AS booking
			, price, currency
			, betrag, betrag_waehrung
			, anmerkungen
			, (CASE WHEN kosten_status = "offen" THEN "status-open"
				WHEN kosten_status = "gelöscht" THEN "status-no"
				WHEN kosten_status = "befreit" THEN "status-exempt"
				WHEN kosten_status = "bestätigt" THEN "status-yes"
				END) AS kosten_status
			, categories.path AS buchungskategorie
		FROM buchungen
		LEFT JOIN costs USING (cost_id)
		LEFT JOIN categories
			ON categories.category_id = buchungen.buchung_category_id
		WHERE team_id IN (%s)
		ORDER BY categories.sequence, category, gruppe';
	$sql = sprintf($sql, is_array($team_ids) ? implode(',', $team_ids) : $team_ids);
	$bookings = wrap_db_fetch($sql, ['team_id', 'buchung_id']);
	if (!$bookings) return [];
	$teams = [];
	foreach ($bookings as $team_id => $bookings_per_team) {
		$teams[$team_id]['betrag'] = 0;
		$teams[$team_id]['tage_teilnehmer'] = 0;
		$teams[$team_id]['tage_betreuer'] = 0;
		$teams[$team_id]['bookings'] = $bookings_per_team;
		foreach ($bookings_per_team as $booking_id => $booking) {
			// Bedingung für komplett:
			// min. min_spieler * dauer_tage für Teilnehmer
			// min. dauer_tage für Betreuer
			if ($booking['kosten_status'] === 'status-no' OR $booking['kosten_status'] === 'status-exempt') {
				$teams[$team_id]['bookings'][$booking_id]['betrag'] = 0;
				$teams[$team_id]['bookings'][$booking_id]['gelöscht'] = true;
				continue;
			}
			$tage = $booking['anzahl_tage'] * ($booking['anzahl_maennlich'] + $booking['anzahl_weiblich']);
			if ($booking['gruppe'] === 'Teilnehmer') {
				$teams[$team_id]['tage_teilnehmer'] += $tage;
			} elseif ($booking['gruppe'] === 'Betreuer') {
				$teams[$team_id]['tage_betreuer'] += $tage;
			}
			$teams[$team_id]['betrag'] += $booking['betrag'];
			$teams[$team_id]['betrag_waehrung'] = $booking['betrag_waehrung'];
		}
		if ($teams[$team_id]['tage_betreuer'] >= $event['dauer_tage']
			AND $teams[$team_id]['tage_teilnehmer'] >= ($event['dauer_tage'] * $event['bretter_min'])) {
			$teams[$team_id]['buchung_komplett'] = true;	
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
	$club_event = mf_tournaments_team_club_event($event);
	if ($club_event)
		$club_board_members = mf_tournaments_team_club_board_members($contact_ids);

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
				AND provider_category_id = /*_ID categories provider/e-mail _*/
				LIMIT 1
			) AS e_mail
			, GROUP_CONCAT(category_short, ": ", identification SEPARATOR "<br>") AS telefon
			, (CASE WHEN spielberechtigt = "vorläufig nein" THEN "status-maybe"
				WHEN spielberechtigt = "nein" THEN "status-no"
				WHEN spielberechtigt = "ja" THEN "status-yes"
				ELSE NULL
				END) AS status, spielberechtigt
			, contacts_identifiers.identifier AS player_pass_dsb
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
			AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
		WHERE team_id IN (%s)
		GROUP BY participation_id, contact_identifier_id
		ORDER BY %s';
	$sql = sprintf($sql
		, implode(',', array_keys($team_ids))
		, $order_by
	);
	$participations = wrap_db_fetch($sql, ['team_id', 'group_identifier', 'participation_id']);

	foreach ($team_ids as $team_id => $club_contact_id) {
		if (!empty($club_board_members[$club_contact_id])) {
			if (empty($participations[$team_id])) $participations[$team_id] = [];
			$participations[$team_id] = array_merge($participations[$team_id], $club_board_members[$club_contact_id]);
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
		$participations[$id]['player_passes_dsb'] = [];
		$aeltester_spieler = 0;
		foreach (array_keys($participations[$id]['spieler']) as $spieler_id) {
			$i++;
			$participations[$id]['spieler'][$spieler_id]['pflicht'] = ($i <= $event['bretter_min']) ? true : false;
			$participations[$id]['spieler'][$spieler_id]['position'] = $i;
			if (!empty($event['guest_players_allowed']))
				$participations[$id]['spieler'][$spieler_id]['guest_players_allowed'] = 1;
			if (!empty($participations[$id]['spieler'][$spieler_id]['player_pass_dsb']))
				$participations[$id]['player_passes_dsb'][] = $participations[$id]['spieler'][$spieler_id]['player_pass_dsb'];
			if (empty($participations[$id]['spieler'][$spieler_id]['geburtsjahr'])) continue;
			if ($participations[$id]['spieler'][$spieler_id]['geburtsjahr'] > $aeltester_spieler) {
				$aeltester_spieler = $participations[$id]['spieler'][$spieler_id]['geburtsjahr'];
			}
		}
		if ($contact_ids AND $club_event) {
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
 * check if event is a club event
 *
 * @param array $event
 * @return bool
 */
function mf_tournaments_team_club_event($event) {
	if (empty($event['tournament_form_parameters']['tournaments_contact_categories']))
		return false;
	if (in_array('club', $event['tournament_form_parameters']['tournaments_contact_categories']))
		return true;
	if (in_array('chess-department', $event['tournament_form_parameters']['tournaments_contact_categories']))
		return true;
	return false;
}

/**
 * get relevant board members of a club for a team
 *
 * @param array $contact_ids
 * @return array
 */
function mf_tournaments_team_club_board_members($contact_ids) {
	if (!$contact_ids) return [];
	
	$sql = 'SELECT club_contact_id, participation_id, person_id, contacts.contact_id
			, usergroups.usergroup
			, usergroups.identifier AS group_identifier
			, contact AS person
			, YEAR(date_of_birth) AS geburtsjahr
			, (SELECT identification FROM contactdetails
				WHERE contactdetails.contact_id = contacts.contact_id
				AND provider_category_id = /*_ID categories provider/e-mail _*/
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
		AND usergroup_id IN (/*_ID usergroups verein-jugend _*/, /*_ID usergroups verein-vorsitz _*/)
		GROUP BY participation_id';
	$sql = sprintf($sql, implode(',', $contact_ids));
	return wrap_db_fetch($sql, ['club_contact_id', 'group_identifier', 'participation_id']);
}


/**
 * team registration complete?
 *
 * @param array $data
 * @return bool
 */
function mf_tournaments_team_registration_complete($data) {
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
