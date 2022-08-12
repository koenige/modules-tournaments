<?php 

/**
 * tournaments module
 * common functions for tournaments (not always included)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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
 * @global array $zz_conf
 */
function mf_tournaments_pgn_file_from_tournament($tournament_id) {
	global $zz_conf;

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
			$path = $zz_conf['root'].$path;
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
	global $zz_setting;
	
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
	', $zz_setting['contact_ids']['dsb']
		, wrap_category_id('contact/federation')
		, $zz_setting['contact_ids']['dsb']
		, implode(', ', $clubs)
	);
	$clubdata = wrap_db_fetch($sql, 'contact_id');
	foreach ($clubs as $id => $contact_id) {
		unset($clubdata[$contact_id]['contact_id']);
		if ($mode === 'multi') {
			$id = explode('-', $id);
			$data[$id[0]][$id[1]] += $clubdata[$contact_id];
		} else {
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
 * Prüfe Zugriffsrechte auf Team, nur eigenes Team erlaubt
 *
 * @param int $team_id
 * @return bool
 */
function mf_tournaments_team_access($team_id, $status = ['Teilnehmer', 'Teilnahmeberechtigt']) {
	global $zz_setting;
	if (brick_access_rights('Webmaster')) return true;
	if (brick_access_rights('AK Spielbetrieb')) return true;
	if (brick_access_rights('Geschäftsstelle')) return true;
	$sql = 'SELECT CONCAT("event_id:", events.event_id) AS event_rights
		FROM events
		LEFT JOIN teams USING (event_id)
		WHERE team_id = %d';
	$sql = sprintf($sql, $team_id);
	$event_rights = wrap_db_fetch($sql, '', 'single value');
	if (brick_access_rights(['Organisator', 'Technik'], $event_rights)) return true;

	$eigene_teams = mf_tournaments_team_own($status);
	if (!in_array($team_id, $eigene_teams)) return false;
	return true;
}

/**
 * read a list of teams that are user’s own teams
 *
 * @param array $status
 * @return array
 */
function mf_tournaments_team_own($status = ['Teilnehmer', 'Teilnahmeberechtigt']) {
	global $zz_setting;
	if (empty($_SESSION['usergroup'][wrap_id('usergroups', 'team-organisator')])) {
		return [];
	}

	$sql = 'SELECT team_id
		FROM participations
		LEFT JOIN teams USING (team_id)
		WHERE usergroup_id = %d
		AND person_id = %d
		AND team_status IN ("%s")
	';
	$sql = sprintf($sql
		, wrap_id('usergroups', 'team-organisator')
		, $_SESSION['person_id']
		, implode('","', $status)
	);
	$teams = wrap_db_fetch($sql, 'team_id', 'single value');
	return $teams;
}

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
	global $zz_setting;

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
			LEFT JOIN persons USING (person_id)
			LEFT JOIN contacts USING (contact_id)
			LEFT JOIN contactdetails USING (contact_id)
			LEFT JOIN usergroups USING (usergroup_id)
			LEFT JOIN categories
				ON categories.category_id = contactdetails.provider_category_id
			WHERE club_contact_id IN (%s)
			AND usergroup_id IN (%d, %d)
			AND (ISNULL(categories.parameters) OR categories.parameters LIKE "%%&type=phone%%")
			GROUP BY participation_id';
		$sql = sprintf($sql
			, wrap_category_id('provider/e-mail')
			, implode(',', $contact_ids)
			, wrap_id('usergroups', 'verein-jugend')
			, wrap_id('usergroups', 'verein-vorsitz')
		);
		$vereinsbetreuer = wrap_db_fetch($sql, ['club_contact_id', 'group_identifier', 'participation_id']);
	}

	$sql = 'SELECT team_id, participation_id, participations.person_id, contacts.contact_id
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
		LEFT JOIN persons USING (person_id)
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
			if ($event['gastspieler_status'])
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
