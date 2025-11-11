<?php 

/**
 * tournaments module
 * team functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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
 * check if submitting a line-up is available for current round
 * i. e. round has not begun or lineup_before_round_mins has a negative value
 *
 * @param array $event
 * @return bool
 */
function mf_tournaments_team_lineup($event) {
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
 * Buchungen zu einem Team / mehreren Teams
 *
 * @param mixed $team_ids (int = eine Team-ID, array = mehrere Team-IDs)
 * @param array $event
 *		int duration_days, int bretter_min
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
			// min. min_spieler * duration_days für Teilnehmer
			// min. duration_days für Betreuer
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
		if ($teams[$team_id]['tage_betreuer'] >= $event['duration_days']
			AND $teams[$team_id]['tage_teilnehmer'] >= ($event['duration_days'] * $event['bretter_min'])) {
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

	// @deprecated use sex instead of geschlecht
	// @deprecated use birth_year instead of geburtsjahr
	$sql = 'SELECT team_id, participation_id, persons.person_id, contacts.contact_id
			, usergroups.usergroup
			, usergroups.identifier AS group_identifier
			, contact AS person
			, (CASE WHEN sex = "female" THEN "W"
				WHEN sex = "male" THEN "M"
				WHEN sex = "diverse" THEN "D"
				ELSE NULL END
			) AS geschlecht
			, sex
			, YEAR(date_of_birth) AS geburtsjahr
			, YEAR(date_of_birth) AS birth_year
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
			, (SELECT identifier FROM contacts_identifiers
				WHERE contacts_identifiers.contact_id = contacts.contact_id
				AND contacts_identifiers.current = "yes"
				AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
			) AS player_pass_dsb
			, (SELECT identifier FROM contacts_identifiers
				WHERE contacts_identifiers.contact_id = contacts.contact_id
				AND contacts_identifiers.current = "yes"
				AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/id_dsb _*/
			) AS player_id_dsb
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN contactdetails USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN categories
			ON categories.category_id = contactdetails.provider_category_id
			AND (ISNULL(categories.parameters) OR categories.parameters LIKE "%%&type=phone%%")
		WHERE team_id IN (%s)
		GROUP BY participation_id, contacts.contact_id
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
