<?php

/**
 * tournaments module
 * show team of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_team($vars, $settings, $data) {
	$sql = 'SELECT setzliste_no
			, platz_no
			, teams.identifier AS team_identifier
			, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
			, @laufende_partien:= (SELECT IF(COUNT(partie_id) = 0, NULL, 1) FROM partien
				WHERE partien.event_id = teams.event_id AND ISNULL(weiss_ergebnis)
			) AS zwischenstand
			, IF(ISNULL(@laufende_partien)
				AND tournaments.tabellenstand_runde_no = tournaments.runden, 1, NULL) AS endstand 
			, teams.team_status
		FROM teams
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_websites
			ON events_websites.event_id = teams.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN tabellenstaende
			ON tabellenstaende.team_id = teams.team_id
			AND (ISNULL(tabellenstaende.runde_no)
				OR tabellenstaende.runde_no = tournaments.tabellenstand_runde_no)
		WHERE teams.team_id = %d
		AND NOT ISNULL(events_websites.website_id)
	';
	$sql = sprintf($sql
		, wrap_get_setting('website_id')
		, $data['team_id']
	);
	$data = array_merge($data, wrap_db_fetch($sql));
	if (!$data) return false;

	$data = mf_tournaments_clubs_to_federations($data, 'contact_id');
	$data[str_replace('-', '_', $data['turnierform'])] = true;
	$data += mf_contacts_contactdetails($data['contact_id']);

	$sql = 'SELECT bretter_min, bretter_max, alter_max, alter_min
			, geschlecht, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste
			, pseudo_dwz
		FROM tournaments
		WHERE event_id = %d';
	$sql = sprintf($sql, $data['event_id']);
	$data = array_merge($data, wrap_db_fetch($sql));

	if ($data['parameters']) {
		parse_str($data['parameters'], $parameters);
		$data += $parameters;
	}

	$page['title'] = $data['event'].' '.$data['year'].': '.$data['team'].' '.$data['team_no'];
	$page['dont_show_h1'] = true;
	if ($data['team_status'] !== 'Teilnehmer') {
		switch ($data['team_status']) {
			case 'Löschung':
				$data['team_withdrawn'] = true;
				$page['status'] = 410;
				$page['text'] = wrap_template('team', $data);
				return $page;
			default:
				return false;
		}
	}

	if (!$data['teilnehmerliste']) {
		// Umleitung zur Terminübersicht
		return wrap_redirect(sprintf('/%s/', $data['event_identifier']));
	}

	// Einen Spielort auslesen
	$sql = 'SELECT contacts.contact_id AS place_id
			, latitude, longitude, contacts.contact AS veranstaltungsort
			, place, address, postcode
		FROM contacts
		LEFT JOIN addresses USING (contact_id)
		LEFT JOIN contacts_contacts
			ON contacts_contacts.contact_id = contacts.contact_id
		WHERE contacts_contacts.main_contact_id = %d
		AND contacts_contacts.published = "yes"
		ORDER BY contacts.contact_id LIMIT 1';
	$sql = sprintf($sql, $data['contact_id']);
	$data = array_merge($data, wrap_db_fetch($sql));

	$data['bilder'] = mf_mediadblink_media([$data['event_identifier'], 'Website'], [], 'group', $data['team_id']);

	// Prev/Next-Navigation
	$sql = 'SELECT team_id, identifier
		FROM teams
		WHERE event_id = %d
		AND team_status = "Teilnehmer"
		AND spielfrei = "nein"
		ORDER BY setzliste_no';
	$sql = sprintf($sql, $data['event_id']);
	$teams = wrap_db_fetch($sql, 'team_id');
	$data = array_merge($data, wrap_get_prevnext_flat($teams, $data['team_id'], true));

	$page['breadcrumbs'][] = $data['team'].' '.$data['team_no'];
	$page['link']['next'][0]['href'] = '../../../'.$data['_next_identifier'].'/';	
	$page['link']['next'][0]['title'] = 'Nächste/r in Setzliste';
	$page['link']['prev'][0]['href'] = '../../../'.$data['_prev_identifier'].'/';	
	$page['link']['prev'][0]['title'] = 'Vorherige/r in Setzliste';

	if (!empty($data['latitude'])) {
		$page['head'] = wrap_template('termin-map-head');
		$data['map'] = true;
	}

	if (!in_array($data['meldung'], ['komplett', 'teiloffen'])) {
		$page['text'] = wrap_template('team', $data);
		return $page;
	}

	// Spieler
	$data = array_merge($data, mf_tournaments_team_players($data['team_id'], $data));

	// Paarungen
	$sql = 'SELECT paarungen.runde_no, tisch_no
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS gegner
			, IF(spielfrei = "ja", bretter_min, 
				SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung))
			) AS punkte
			, IF(SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) < %s, 1, NULL) verloren
			, IF(SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) = %s, 1, NULL) unentschieden
			, IF(spielfrei = "ja" OR SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) > %s, 1, NULL) gewonnen
			, IF(spielfrei = "ja", 1, NULL) AS spielfrei
		FROM paarungen
		LEFT JOIN teams
			ON (IF(paarungen.heim_team_id = %d, paarungen.auswaerts_team_id, paarungen.heim_team_id) = teams.team_id)
		LEFT JOIN partien USING (paarung_id)
		LEFT JOIN tournaments
			ON paarungen.event_id = tournaments.event_id
		WHERE (heim_team_id = %d OR auswaerts_team_id = %d)
		AND paarungen.event_id = %d
		GROUP BY paarung_id, team_id
		ORDER BY runde_no';
	$sql = sprintf($sql, $data['team_id'], $data['team_id'], $data['bretter_min']/2
		, $data['team_id'], $data['bretter_min']/2, $data['team_id'], $data['bretter_min']/2, $data['team_id']
		, $data['team_id'], $data['team_id'], $data['event_id']);
	$data['paarungen'] = wrap_db_fetch($sql, 'runde_no');
	if ($data['paarungen']) {
		$data['summe_bp'] = 0;
		$data['summe_mp'] = 0;
		foreach ($data['paarungen'] AS $paarung_id => $paarung) {
			if ($paarung['spielfrei']) $data['spielfrei'] = true;
			$data['summe_bp'] += $paarung['punkte'];
			if ($paarung['gewonnen'])
				$data['summe_mp'] += 2;
			elseif ($paarung['unentschieden'])
				$data['summe_mp'] += 1;
			elseif ($paarung['unentschieden'])
				$data['summe_mp'] += 0; // + 0 damit es Zahl ist.
			$round = $data;
			$round['runde_no'] = $paarung['runde_no'];
			$round['identifier'] = $round['event_identifier'];
			$data['paarungen'][$paarung_id]['lineup'] = mf_tournaments_lineup($round);
		}
	}

	// Partien
	if (!empty($data['spieler'])) {
		$sql = 'SELECT partie_id
				, partien.runde_no, partien.brett_no, tisch_no
				, IF(ISNULL(weiss_status.t_vorname),
					white_contact.contact,
					CONCAT(weiss_status.t_vorname, " ", IFNULL(CONCAT(weiss_status.t_namenszusatz, " "), ""), weiss_status.t_nachname)
				) AS weiss
				, weiss_person_id
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
				, IF(ISNULL(schwarz_status.t_vorname),
					black_contact.contact,
					CONCAT(schwarz_status.t_vorname, " ", IFNULL(CONCAT(schwarz_status.t_namenszusatz, " "), ""), schwarz_status.t_nachname)
				) AS schwarz
				, schwarz_person_id
				, IF(partiestatus_category_id = %d, 1, NULL) AS haengepartie
				, category AS partiestatus
				, IF(NOT ISNULL(pgn), IF(partiestatus_category_id != %d, 1, NULL), NULL) AS pgn
				, schwarz_status.t_dwz AS schwarz_dwz
				, schwarz_status.t_elo AS schwarz_elo
				, weiss_status.t_dwz AS weiss_dwz
				, weiss_status.t_elo AS weiss_elo
			FROM partien
			LEFT JOIN categories
				ON partien.partiestatus_category_id = categories.category_id
			LEFT JOIN paarungen USING (paarung_id)
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
			AND (weiss_person_id IN (%s) OR schwarz_person_id IN (%s))';
		$sql = sprintf($sql
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_id('usergroups', 'spieler'), $data['event_id']
			, wrap_id('usergroups', 'spieler'), $data['event_id']
			, $data['event_id']
			, implode(',', array_keys($data['spieler']))
			, implode(',', array_keys($data['spieler']))
		);
		$partien = wrap_db_fetch($sql, 'partie_id');
		foreach ($data['spieler'] as $person_id => $spieler) {
			foreach (array_keys($data['paarungen']) as $runde) {
				$data['spieler'][$person_id]['partien'][$runde] = [];
				$data['spieler'][$person_id]['partien'][$runde]['ergebnis'] = '';
				if ($data['paarungen'][$runde]['spielfrei']) {
					$data['spieler'][$person_id]['partien'][$runde]['partie_spielfrei'] = true;
				}
			}
			$data['spieler'][$person_id]['summe_bp'] = 0;
			if (!$partien) continue;
			foreach ($partien as $partie_id => $partie) {
				if ($data['paarungen'][$partie['runde_no']]['lineup']) continue;
				$ergebnis = '';
				if (empty($data['spieler'][$person_id]['partien'][$partie['runde_no']])) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = [];
				}
				if ($partie['weiss_person_id'] == $person_id) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = array_merge(
						$data['spieler'][$person_id]['partien'][$partie['runde_no']], $partie, [
						'gegner' => $partie['schwarz'],
						'gegner_dwz' => $partie['schwarz_dwz'],
						'gegner_elo' => $partie['schwarz_elo'],
						'ergebnis' => $partie['weiss_ergebnis'],
						'farbe' => 'weiß',
						'farbe_kennung' => 'weiss',
						'live' => ($partie['pgn'] AND is_null($partie['weiss_ergebnis']) ? true : false)
					]);
					$ergebnis = $partie['weiss_ergebnis'];
					unset($partien[$partie_id]);
				} elseif ($partie['schwarz_person_id'] == $person_id) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = array_merge(
						$data['spieler'][$person_id]['partien'][$partie['runde_no']], $partie, [
						'gegner' => $partie['weiss'],
						'gegner_dwz' => $partie['weiss_dwz'],
						'gegner_elo' => $partie['weiss_elo'],
						'ergebnis' => $partie['schwarz_ergebnis'],
						'farbe' => 'schwarz',
						'farbe_kennung' => 'schwarz',
						'live' => ($partie['pgn'] AND is_null($partie['schwarz_ergebnis']) ? true : false)
					]);
					$ergebnis = $partie['schwarz_ergebnis'];
					unset($partien[$partie_id]);
				}
				if ($ergebnis === '+') $ergebnis = 1;
				elseif ($ergebnis === '=') $ergebnis = 0.5;
				elseif ($ergebnis === '-') $ergebnis = 0;
				if ($ergebnis !== '') {
					$data['spieler'][$person_id]['summe_bp'] += $ergebnis;
				}
			}
		}
	}

	$sql = 'SELECT event_link_id, link, link_text
		FROM events_links
		WHERE team_id = %d';
	$sql = sprintf($sql, $data['team_id']);
	$data['links'] = wrap_db_fetch($sql, 'event_link_id');

	$page['text'] = wrap_template('team', $data);
	return $page;
}

/**
 * Alle Spieler eines Teams / mehrerer Teams
 *
 * @param mixed $team_ids (int = eine Team-ID, array = mehrere Team-IDs)
 * @param array $event
 *		int bretter_min
 * @return array $daten
 */
function mf_tournaments_team_players($team_ids, $event) {
	$sql = 'SELECT team_id, person_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
			, t_verein, t_dwz, t_elo, t_fidetitel, spielberechtigt, rang_no, brett_no
			, IF(participations.gastspieler = "ja", 1, NULL) AS gastspieler
			, IF(tournaments.gastspieler = "ja", 1, NULL) AS gastspieler_status
			, YEAR(date_of_birth) AS geburtsjahr
			, pseudo_dwz
		FROM participations
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN teams USING (team_id)
		LEFT JOIN persons USING (contact_id)
		WHERE usergroups.identifier = "spieler"
		AND (ISNULL(spielberechtigt) OR spielberechtigt = "ja")
		AND participations.teilnahme_status = "Teilnehmer"
		AND team_id IN (%s)
		ORDER BY ISNULL(brett_no), brett_no, rang_no, t_dwz DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql, is_array($team_ids) ? implode(',', $team_ids) : $team_ids);
	$alle_spieler = wrap_db_fetch($sql, ['team_id', 'person_id']);
	if (!$alle_spieler) return [];
	foreach ($alle_spieler as $id => $team_spieler) {
		$team_spieler = mf_tournaments_person_identifiers($team_spieler, ['fide-id', 'zps']);

		$teams[$id]['spielberechtigt'] = true;
		$teams[$id]['dwz_schnitt'] = 0;
		$bretter_min = $event['bretter_min'];
		$bretter_dwz = 0;
		$brett_no = false;
		// Gibt es Spieler mit Brett-No.?
		foreach ($team_spieler as $person_id => $spieler) {
			if (!$brett_no) $brett_no = $spieler['brett_no'];
			else break;
		}
		// Spieler- und Teamdaten ergänzen
		foreach ($team_spieler as $person_id => $spieler) {
			if ($spieler['t_fidetitel']) {
				$team_spieler[$person_id]['fidetitel_lang'] = mf_tournaments_fide_title($spieler['t_fidetitel']);
			}
			if ($brett_no AND !$spieler['brett_no']) {
				// Es gibt Spieler mit Brettnummern, also blenden wir die ohne aus,
				// die wurden nur gemeldet, aber nicht eingesetzt
				unset($team_spieler[$person_id]);
			}
			if (!$spieler['spielberechtigt'] OR $spieler['spielberechtigt'] !== 'ja') {
				$teams[$id]['spielberechtigt'] = false;
			}
		}
		$teams[$id]['spieler'] = $team_spieler;
		// DWZ-Schnitt
		if (!$brett_no) {
			$team_spieler = mf_tournaments_team_sort_rating($team_spieler);
		}
		foreach ($team_spieler as $person_id => $spieler) {
			if ($bretter_min > 0) {
				if ($spieler['t_dwz']) {
					$teams[$id]['dwz_schnitt'] += $spieler['t_dwz'];
					$bretter_dwz++;
				} elseif ($event['pseudo_dwz']) {
					$teams[$id]['dwz_schnitt'] += $spieler['pseudo_dwz'];
					$bretter_dwz++;
				}
			}
			$bretter_min--;
		}
		if ($bretter_dwz) {
			$teams[$id]['dwz_schnitt'] /= $bretter_dwz;
			$teams[$id]['dwz_schnitt'] = round($teams[$id]['dwz_schnitt'], 0);
		}
	}
	if (is_array($team_ids)) return $teams;
	else return $teams[$team_ids];
}

/**
 * Sortierung von Spielern nach DWZ und Elo, falls keine Brett-No. gesetzt
 *
 * @param array $spieler
 * @return array
 */
function mf_tournaments_team_sort_rating($spieler) {
	foreach ($spieler as $key => $person) {
		$t_dwz[$key] = $person['t_dwz'];
		$t_elo[$key] = $person['t_elo'];
	}
	array_multisort($t_dwz, SORT_DESC, $t_elo, SORT_DESC, $spieler);
	return $spieler;
}
