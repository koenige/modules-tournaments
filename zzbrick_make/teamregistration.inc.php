<?php

/**
 * tournaments module
 * registration of the line-up of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2017, 2019-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Editing of the team registration
 *
 * @param array $vars
 * 		[0]: year
 * 		[1]: event identifier
 * 		[2]: team identifier
 * @return array $page
 */
function mod_tournaments_make_teamregistration($vars, $settings, $data) {
	wrap_include('validate', 'zzform');
	wrap_include('zzform/editing', 'ratings');

	if ($data['meldung'] !== 'offen') wrap_quit(403,
		'Das Team wurde bereits abschließend gemeldet. Eine Änderung der Aufstellung ist nicht mehr möglich.'
	);
	
	$sql = 'SELECT v_ok.identifier AS zps_code
			, geschlecht, alter_min, alter_max, bretter_min, bretter_max
			, IF(gastspieler = "ja", 1, NULL) AS guest_players_allowed
			, (SELECT eventtext FROM eventtexts
				WHERE eventtexts.event_id = tournaments.event_id
				AND eventtexts.eventtext_category_id = /*_ID categories event-texts/note-lineup _*/
			) AS hinweis_aufstellung
		FROM teams
		LEFT JOIN contacts organisationen
			ON teams.club_contact_id = organisationen.contact_id
		LEFT JOIN contacts_identifiers v_ok
			ON v_ok.contact_id = organisationen.contact_id AND v_ok.current = "yes"
		LEFT JOIN tournaments USING (event_id)
		WHERE teams.team_id = %d';
	$sql = sprintf($sql, $data['team_id']);
	$data = array_merge($data, wrap_db_fetch($sql));

	$data['geschlecht'] = explode(',', strtoupper($data['geschlecht']));

	// Team + Vereinsbetreuer auslesen
	$data = array_merge($data, mf_tournaments_team_participants([$data['team_id'] => $data['contact_id']], $data));

	// Aktuelle Mitglieder auslesen
	// besser als nichts, eigentlich werden vergangene Mitglieder gesucht
	$data['vereinsspieler'] = mod_tournaments_make_teamregistration_club_players($data);
	foreach ($data['vereinsspieler'] as $player)
		if ($player['player_pass_dsb'])
			$data['player_passes_dsb'][] = $player['player_pass_dsb'];
	
	$data['add'] = true;
	if (!empty($data['spielerzahl']) AND $data['bretter_max'] <= $data['spielerzahl']) {
		$data['add'] = false;
	}
	if (!$data['add']) unset($data['vereinsspieler']);
	
	$data['redirect'] = true;
	$changed = false; // es kann sein, dass zuviele Spieler angegeben werden
	if (!empty($_POST)) {
		$postdata = $_POST; // wird von zzform ggf. überschrieben
		foreach ($postdata['rang'] as $code => $rangliste_no) {
			// Nur Integer werden akzeptiert (warum auch immer da Leute was anderes eingeben)
			if ($rangliste_no) {
				$rangliste_no = trim($rangliste_no);
				if (substr($rangliste_no, -1) === '.') {
					$rangliste_no = substr($rangliste_no, 0, -1);
				}
				if (!wrap_is_int($rangliste_no)) {
					$data['error_board_no_numbers'] = true;
					continue;
				}
				if ($rangliste_no < 0) {
					$data['error_board_no_bigger_zero'] = true;
					continue;
				}
			}
			if ($code === 'neu' AND $data['add']) {
				if ($rangliste_no) $data['post_rang'] = $rangliste_no;
				$data['post_guest_player'] = mf_tournaments_guest_player($data, $postdata, $code, false);
				// Neuer Spieler nicht aus Vereinsliste wird ergänzt
				if (!empty($postdata['auswahl']) AND $rangliste_no) {
					$player = mf_ratings_player_data_dsb($postdata['auswahl']);
					if ($player) {
						$player['date_of_birth'] = zz_check_date($postdata['date_of_birth']);
						$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
						$ops = mf_tournaments_team_player_insert($player, $data, $rangliste_no);
						if ($ops) $changed = true;
					}
					continue;
				} elseif (!empty($postdata['auswahl']) AND empty($postdata['abbruch'])) {
					$player = mf_ratings_player_data_dsb($postdata['auswahl']);
					$data['neu_treffer_ohne_rang'] = true;
					$data['neu_ZPS'] = $player['ZPS'];
					$data['neu_Mgl_Nr'] = $player['Mgl_Nr'];
					$data['new_first_name'] = $player['first_name'];
					$data['new_last_name'] = $player['last_name'];
					$data['neu_Geschlecht'] = $player['Geschlecht'];
					$data['new_birth_year'] = $player['Geburtsjahr'];
					$data['new_dwz_dsb'] = $player['DWZ'];
					$data['redirect'] = false;
					continue;
				}
				if (empty($postdata['first_name']) AND empty($postdata['last_name'])) {
					// Fehler: mindestens ein Namensteil muß angegeben werden
				} elseif (!empty($postdata['ergaenzen'])) {
					// Spieler ohne DSB-Mitgliedschaft wird ergänzt
					$player = [];
					$player['first_name'] = $postdata['first_name'];
					$player['last_name'] = $postdata['last_name'];
					$player['date_of_birth'] = zz_check_date($postdata['date_of_birth']);
					$player['Geschlecht'] = strtoupper($postdata['geschlecht']);
					$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
					$ops = mf_tournaments_team_player_insert($player, $data, $rangliste_no);
					if ($ops) $changed = true;
					// Spieler in eigener Personentabelle suchen
					// Falls nicht vorhanden, ergänzen
					// Teilnahme ergänzen
				} elseif (empty($postdata['abbruch'])) {
					// Suche in dwz_spieler
					// first_name, last_name, sex, date_of_birth
					$data['new_matches'] = cms_team_spielersuche($data, $postdata);
					$data['redirect'] = false;
					$data['post_date_of_birth'] = $postdata['date_of_birth'];
					if (!count($data['new_matches'])) {
						$data['post_vorname'] = $postdata['first_name'];
						$data['post_nachname'] = $postdata['last_name'];
						if ($postdata['geschlecht'] === 'm')
							$data['post_geschlecht_m'] = true;
						elseif ($postdata['geschlecht'] === 'w')
							$data['post_geschlecht_w'] = true;
						elseif ($postdata['geschlecht'] === 'd')
							$data['post_geschlecht_d'] = true;
						// Keinen Spieler gefunden
						if (wrap_setting('tournaments_player_pool')) {
							// DSB-Mitgliedschaft erforderlich
							$data['new_player_not_found'] = true;
						} elseif (!empty($postdata['date_of_birth']) AND !zz_check_date($postdata['date_of_birth'])) {
							$data['date_of_birth_wrong'] = true;
						} else {
							// Turniere ohne erforderliche DSB-Mitgliedschaft:
							// Option, Spieler hinzuzufügen
							$required_fields = ['first_name', 'last_name', 'geschlecht', 'date_of_birth'];
							$complete = true;
							foreach ($required_fields as $required_field)
								if (empty($postdata[$required_field])) $complete = false;
							if ($complete)
								$data['new_player_add'] = true;
							else
								$data['new_player_more_data'] = true;
						}
					}
				}
			} elseif (substr($code, 0, 4) === 'zps_' AND $rangliste_no) {
				$id = substr($code, 4);
				if (empty($data['vereinsspieler'][$id])) continue;
				$player = mf_ratings_player_data_dsb([
					$data['vereinsspieler'][$id]['player_pass_dsb']
				]);
				if ($player) {
					$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
					$ops = mf_tournaments_team_player_insert($player, $data, $rangliste_no);
					if ($ops) $changed = true;
				}
			} elseif (substr($code, 0, 4) === 'tln_') {
				$participation_id = substr($code, 4); 
				if ($rangliste_no) {
					// Rangliste geändert
					$line = [
						'participation_id' => $participation_id,
						'rang_no' => $rangliste_no,
						'gastspieler' => mf_tournaments_guest_player($data, $postdata, $code)
					];
					$result = zzform_update('participations', $line, E_USER_ERROR);
					if ($result) $changed = true;
				} else {
					// Aus Rangliste gelöscht
					$ids = zzform_delete('participations', $participation_id);
					if ($ids) $changed = true;
				}
			}
		}
	}
	if ($changed AND $data['redirect'])
		return wrap_redirect_change();

	// Daten m/w? Nur ein Geschlecht, dann keine Auswahl nötig
	if (count($data['geschlecht']) === 1) {
		if ($data['geschlecht'][0] === 'W') {
			$data['geschlecht_nur_w'] = true;
		} else {
			$data['geschlecht_nur_m'] = true;
		}
	}

	$page['title'] = $data['event'].' '.$data['year'].': '.$data['team'].' '.$data['team_no'];
	$page['title'] .= ' – Aufstellung';
	$page['breadcrumbs'][]['title'] = 'Aufstellung';

	$page['text'] = wrap_template('team-registration', $data);
	return $page;
}

/**
 * Fügt Spieler als Meldung zu Teilnahmen hinzu
 *
 * @param array $player
 * @param array $data
 * @param int $rangliste_no
 * @return bool
 */
function mf_tournaments_team_player_insert($player, $data, $rangliste_no) {
	wrap_include('zzform/editing', 'custom');
	
	// Test, ob Spieler noch hinzugefügt werden darf
	if ($data['bretter_max']) {
		$sql = 'SELECT COUNT(*) FROM participations
			WHERE usergroup_id = /*_ID usergroups spieler _*/ AND team_id = %d';
		$sql = sprintf($sql, $data['team_id']);
		$gemeldet = wrap_db_fetch($sql, '', 'single value');
		if ($gemeldet AND $gemeldet >= $data['bretter_max']) {
			return false; // nicht mehr als bretter_max melden!
		}
	}

	// Speicherung in Personen
	// 1. Abgleich: gibt es schon Paßnr.? Alles andere zu unsicher
	$contact_id = mf_ratings_person_add($player);

	// direkte Speicherung in participations
	$line = [
		'usergroup_id' => wrap_id('usergroups', 'spieler'),
		'event_id' => $data['event_id'],
		'team_id' => $data['team_id'],
		'contact_id' => $contact_id,
		'rang_no' => $rangliste_no,
		't_vorname' => $player['first_name'],
		't_nachname' => $player['last_name'],
		// bei Nicht-DSB-Mitgliedern nicht vorhandene Daten
		'club_contact_id' => $player['club_contact_id'] ?? '',
		't_verein' => $player['club_contact'] ?? '',
		't_dwz' => $player['DWZ'] ?? '',
		't_elo' => $player['FIDE_Elo'] ?? '',
		't_fidetitel' => $player['FIDE_Titel'] ?? '',
		'gastspieler' => $player['guest_player']
	];
	if (wrap_category_id('participations/registration', 'check')) {
		$line['participations_categories_'.wrap_category_id('participations/registration')][]['category_id']
			= wrap_category_id('participations/registration/team');
	}
	$participation_id = zzform_insert('participations', $line, E_USER_ERROR);
	return true;
}

/**
 * Sucht Spieler/in nach Benutzereingaben in DWZ-Datenbank
 *
 * @param array $data
 * @param array $post (via POST übergebene Daten)
 *		sex, date_of_birth, last_name, first_name
 * @return array
 */
function cms_team_spielersuche($data, $post) {
	// Suchparameter zusammenbauen
	$where = '';
	if ($post['geschlecht'])
		$where .= sprintf(' AND Geschlecht = "%s"', wrap_db_escape(strtoupper($post['geschlecht'])));
	if ($post['date_of_birth'] AND $date = zz_check_date($post['date_of_birth']))
		$where .= sprintf(' AND Geburtsjahr = %d', substr($date, 0, 4));
	else
		$where .= sprintf(' AND Geburtsjahr <= %d AND Geburtsjahr >= %d', 
			date('Y') - $data['alter_min'], date('Y') - $data['alter_max']
		);
	$playername = $post['last_name'] ? $post['last_name'].'%,' : '%,';
	$playername .= $post['first_name'] ? $post['first_name'].'%' : '%';
	// Für die Doofies auch falsch herum:
	$playername_r = $post['first_name'] ? $post['first_name'].'%,' : '%,';
	$playername_r .= $post['last_name'] ? $post['last_name'].'%' : '%';
	
	//	Suche starten
	$sql = 'SELECT CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr)) AS unique_id
			, ZPS, Mgl_Nr, Spielername, Geschlecht, Geburtsjahr, DWZ, FIDE_Elo
			, contact
			, SUBSTRING_INDEX(Spielername, ",", 1) AS last_name
			, SUBSTRING_INDEX(SUBSTRING_INDEX(Spielername, ",", 2), ",", -1) AS first_name
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers ok
			ON dwz_spieler.ZPS = ok.identifier 
		LEFT JOIN contacts USING (contact_id)
		WHERE (ISNULL(Status) OR Status != "P")
		AND (Spielername LIKE _latin1"%s" OR Spielername LIKE _latin1"%s")
		AND CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr)) NOT IN ("%s")
		AND ok.current = "yes"
		%s
		ORDER BY Spielername';
	$sql = sprintf($sql
		, wrap_db_escape($playername)
		, wrap_db_escape($playername_r)
		, !empty($data['player_passes_dsb']) ? implode('","', $data['player_passes_dsb']) : ''
		, $where
	);
	return wrap_db_fetch($sql, 'unique_id');
}

/**
 * get all players of a club that are eligible to participate in this tournament
 *
 * @param array $data
 * @return array
 */
function mod_tournaments_make_teamregistration_club_players($data) {
	if (!$data['zps_code']) return [];

	$sql = 'SELECT ZPS, IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr) AS Mgl_Nr
			, Geschlecht, Geburtsjahr, DWZ, FIDE_Elo
			, contacts.contact_id, contact
			, CONCAT(ZPS, "-", IF(Mgl_Nr < 100, LPAD(Mgl_Nr, 3, "0"), Mgl_Nr)) AS player_pass_dsb
			, SUBSTRING_INDEX(Spielername, ",", 1) AS last_name
			, SUBSTRING_INDEX(SUBSTRING_INDEX(Spielername, ",", 2), ",", -1) AS first_name
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers ok
			ON dwz_spieler.ZPS = ok.identifier 
		LEFT JOIN contacts USING (contact_id)
		WHERE ZPS = "%s"
		AND (ISNULL(Status) OR Status != "P")
		AND Geschlecht IN ("%s")
		AND Geburtsjahr <= %d AND Geburtsjahr >= %d
		AND ok.current = "yes"
		ORDER BY Spielername';
	$sql = sprintf($sql
		, $data['zps_code']
		, implode('","', $data['geschlecht'])
		, date('Y') - $data['alter_min']
		, date('Y') - $data['alter_max']
	);
	$players = wrap_db_fetch($sql, 'Mgl_Nr');
	foreach ($players as $id => $player) {
		// remove registered players from list
		foreach ($data['spieler'] AS $registered_players) {
			if (empty($registered_players['player_pass_dsb'])) continue;
			if ($registered_players['player_pass_dsb'] !== $player['player_pass_dsb']) continue;
			unset($players[$id]);
		}
	}
	return $players;
}

/**
 * get guest player status for database
 *
 * @param array $event
 * @param array $post
 * @param string $code
 * @param string $return_false
 * @return string
 */
function mf_tournaments_guest_player($event, $post, $code, $return_false = 'nein') {
	if (empty($event['guest_players_allowed'])) return NULL;
	if (empty($post['guest_player'][$code])) return $return_false;
	if ($post['guest_player'][$code] === 'off') return $return_false;
	return 'ja';
}
