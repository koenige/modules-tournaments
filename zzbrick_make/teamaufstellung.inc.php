<?php

/**
 * tournaments module
 * registration of the line-up of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2017, 2019-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Bearbeiten der Mannschaftsaufstellung
 *
 * @param array $vars
 * 		[0]: Jahr
 * 		[1]: event identifier
 * 		[2]: Teamkennung
 * @return array $page
 */
function mod_tournaments_make_teamaufstellung($vars, $settings, $data) {
	wrap_include_files('validate', 'zzform');
	wrap_include_files('zzform/editing', 'ratings');

	if ($data['meldung'] !== 'offen') wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Eine Änderung der Aufstellung ist nicht mehr möglich.');
	
	$sql = 'SELECT v_ok.identifier AS zps_code
			, geschlecht, alter_min, alter_max, bretter_min, bretter_max
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, (SELECT eventtext FROM eventtexts
				WHERE eventtexts.event_id = tournaments.event_id
				AND eventtexts.eventtext_category_id = %d
			) AS hinweis_aufstellung
		FROM teams
		LEFT JOIN contacts organisationen
			ON teams.club_contact_id = organisationen.contact_id
		LEFT JOIN contacts_identifiers v_ok
			ON v_ok.contact_id = organisationen.contact_id AND v_ok.current = "yes"
		LEFT JOIN tournaments USING (event_id)
		WHERE teams.team_id = %d';
	$sql = sprintf($sql
		, wrap_category_id('event-texts/note-lineup')
		, $data['team_id']
	);
	$data = array_merge($data, wrap_db_fetch($sql));

	$data['geschlecht'] = explode(',', strtoupper($data['geschlecht']));

	// Team + Vereinsbetreuer auslesen
	$data = array_merge($data, mf_tournaments_team_participants([$data['team_id'] => $data['contact_id']], $data));

	// Aktuelle Mitglieder auslesen
	// besser als nichts, eigentlich werden vergangene Mitglieder gesucht
	$sql = 'SELECT ZPS, Mgl_Nr, Spielername, Geschlecht, Geburtsjahr
			, DWZ, FIDE_Elo, contacts.contact_id, contact
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers ok
			ON dwz_spieler.ZPS = ok.identifier 
		LEFT JOIN contacts USING (contact_id)
		WHERE ZPS = "%s"
		AND (ISNULL(Status) OR Status != "P")
		AND Geschlecht IN("%s")
		AND Geburtsjahr <= %d AND Geburtsjahr >= %d
		AND ok.current = "yes"
		ORDER BY Spielername';
	$sql = sprintf($sql, $data['zps_code'], implode('","', $data['geschlecht'])
		, date('Y') - $data['alter_min'], date('Y') - $data['alter_max']);
	$data['vereinsspieler'] = wrap_db_fetch($sql, 'Mgl_Nr');
	foreach ($data['vereinsspieler'] as $id => $spieler) {
		if ($spieler['Mgl_Nr'])
			$data['player_passes_dsb'][] = $spieler['ZPS'].'-'.$spieler['Mgl_Nr'];
		foreach ($data['spieler'] AS $gemeldete_spieler) {
			if (empty($gemeldete_spieler['player_pass_dsb'])) continue;
			if ($gemeldete_spieler['player_pass_dsb'] !== $spieler['ZPS'].'-'.$spieler['Mgl_Nr']) continue;
			unset($data['vereinsspieler'][$id]);
			continue 2;
		}
		$spielername = explode(',', $spieler['Spielername']);
		$data['vereinsspieler'][$id]['last_name'] = $spielername[0];
		$data['vereinsspieler'][$id]['first_name'] = $spielername[1];
		if ($data['gastspieler_status']) {
			$data['vereinsspieler'][$id]['gastspieler_status'] = 1;
		}
	}
	
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
					$data['error_msg'] = 'Es können als Brettnummern nur Zahlen (1–…) eingegeben werden.';
					continue;
				}
				if ($rangliste_no < 0) {
					$data['error_msg'] = 'Es können als Brettnummern nur Zahlen größer Null eingegben werden.';
					continue;				
				}
			}
			// Gastspieler prüfen
			if (!empty($postdata['gastspieler'][$code]) AND $postdata['gastspieler'][$code] !== 'off') {
				$gastspieler = true;
			} else {
				$gastspieler = false;
			}
			if ($code === 'neu' AND $data['add']) {
				if ($rangliste_no) $data['post_rang'] = $rangliste_no;
				if (isset($postdata['gastspieler'][$code]))
					$data['post_gastspieler'] = $postdata['gastspieler'][$code] !== 'off' ? 1 : 0;
				// Neuer Spieler nicht aus Vereinsliste wird ergänzt
				if (!empty($postdata['auswahl']) AND $rangliste_no) {
					$spieler = mf_ratings_player_data_dsb($postdata['auswahl']);
					if ($spieler) {
						$spieler['date_of_birth'] = zz_check_date($postdata['date_of_birth']);
						$ops = cms_team_spieler_insert($spieler, $data, $rangliste_no, $gastspieler);
						if ($ops) $changed = true;
					}
					continue;
				} elseif (!empty($postdata['auswahl']) AND empty($postdata['abbruch'])) {
					$spieler = mf_ratings_player_data_dsb($postdata['auswahl']);
					$data['neu_treffer_ohne_rang'] = true;
					$data['neu_ZPS'] = $spieler['ZPS'];
					$data['neu_Mgl_Nr'] = $spieler['Mgl_Nr'];
					$data['neu_vorname'] = $spieler['first_name'];
					$data['neu_nachname'] = $spieler['last_name'];
					$data['neu_Geschlecht'] = $spieler['Geschlecht'];
					$data['neu_Geburtsjahr'] = $spieler['Geburtsjahr'];
					$data['neu_DWZ'] = $spieler['DWZ'];
					$data['redirect'] = false;
					continue;
				}
				if (empty($postdata['first_name']) AND empty($postdata['last_name'])) {
					// Fehler: mindestens ein Namensteil muß angegeben werden
				} elseif (!empty($postdata['ergaenzen'])) {
					// Spieler ohne DSB-Mitgliedschaft wird ergänzt
					$spieler = [];
					$spieler['first_name'] = $postdata['first_name'];
					$spieler['last_name'] = $postdata['last_name'];
					$spieler['date_of_birth'] = zz_check_date($postdata['date_of_birth']);
					$spieler['Geschlecht'] = strtoupper($postdata['geschlecht']);
					$ops = cms_team_spieler_insert($spieler, $data, $rangliste_no, $gastspieler);
					if ($ops) $changed = true;
					// Spieler in eigener Personentabelle suchen
					// Falls nicht vorhanden, ergänzen
					// Teilnahme ergänzen
				} elseif (empty($postdata['abbruch'])) {
					// Suche in dwz_spieler
					// first_name, last_name, sex, date_of_birth
					$data['neu_treffer'] = cms_team_spielersuche($data, $postdata);
					$data['redirect'] = false;
					$data['post_date_of_birth'] = $postdata['date_of_birth'];
					if (!count($data['neu_treffer'])) {
						$data['post_vorname'] = $postdata['first_name'];
						$data['post_nachname'] = $postdata['last_name'];
						if ($postdata['geschlecht'] === 'm')
							$data['post_geschlecht_m'] = true;
						elseif ($postdata['geschlecht'] === 'w')
							$data['post_geschlecht_w'] = true;
						elseif ($postdata['geschlecht'] === 'd')
							$data['post_geschlecht_d'] = true;
						// Keinen Spieler gefunden
						if (!empty($data['tournament_form_parameters']['mitglied'])) {
							// DSB-Mitgliedschaft erforderlich
							$data['neu_spieler_nicht_gefunden'] = true;
						} elseif (!empty($postdata['date_of_birth']) AND !zz_check_date($postdata['date_of_birth'])) {
							$data['date_of_birth_falsch'] = true;
						} else {
							// Turniere ohne erforderliche DSB-Mitgliedschaft:
							// Option, Spieler hinzuzufügen
							$required_fields = ['first_name', 'last_name', 'geschlecht', 'date_of_birth'];
							$complete = true;
							foreach ($required_fields as $required_field)
								if (empty($postdata[$required_field])) $complete = false;
							if ($complete)
								$data['neu_spieler_hinzufuegen'] = true;
							else
								$data['new_player_more_data'] = true;
						}
					}
				}
			} elseif (substr($code, 0, 4) === 'zps_' AND $rangliste_no) {
				$id = substr($code, 4);
				if (empty($data['vereinsspieler'][$id])) continue;
				$spieler = mf_ratings_player_data_dsb([
					$data['vereinsspieler'][$id]['ZPS'],
					$data['vereinsspieler'][$id]['Mgl_Nr']
				]);
				if ($spieler) {
					$ops = cms_team_spieler_insert($spieler, $data, $rangliste_no, $gastspieler);
					if ($ops) $changed = true;
				}
			} elseif (substr($code, 0, 4) === 'tln_') {
				$id = substr($code, 4); 
				if ($rangliste_no) {
					// Rangliste geändert
					$line = [
						'participation_id' => $id,
						'rang_no' => $rangliste_no,
						'gastspieler' =>  $data['gastspieler_status'] ?
							($gastspieler ? 'ja' : 'nein') : NULL
					];
					$result = zzform_update('participations', $line, E_USER_ERROR);
					if ($result) $changed = true;
				} else {
					// Aus Rangliste gelöscht
					$ids = zzform_delete('participations', $id);
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

	$page['text'] = wrap_template('team-aufstellung', $data);
	return $page;
}

/**
 * Fügt Spieler als Meldung zu Teilnahmen hinzu
 *
 * @param array $spieler
 * @param array $data
 * @param int $data
 * @param bool $rangliste_no
 * @return bool
 */
function cms_team_spieler_insert($spieler, $data, $rangliste_no, $gastspieler) {
	wrap_include_files('zzform/editing', 'custom');
	
	// Test, ob Spieler noch hinzugefügt werden darf
	if ($data['bretter_max']) {
		$sql = 'SELECT COUNT(*) FROM participations
			WHERE usergroup_id = %d AND team_id = %d';
		$sql = sprintf($sql
			, wrap_id('usergroups', 'spieler')
			, $data['team_id']
		);
		$gemeldet = wrap_db_fetch($sql, '', 'single value');
		if ($gemeldet AND $gemeldet >= $data['bretter_max']) {
			return false; // nicht mehr als bretter_max melden!
		}
	}

	// Speicherung in Personen
	// 1. Abgleich: gibt es schon Paßnr.? Alles andere zu unsicher
	$contact_id = my_person_speichern($spieler);

	// direkte Speicherung in participations
	$line = [
		'usergroup_id' => wrap_id('usergroups', 'spieler'),
		'event_id' => $data['event_id'],
		'team_id' => $data['team_id'],
		'contact_id' => $contact_id,
		'rang_no' => $rangliste_no,
		't_vorname' => $spieler['first_name'],
		't_nachname' => $spieler['last_name'],
		// bei Nicht-DSB-Mitgliedern nicht vorhandene Daten
		'club_contact_id' => $spieler['club_contact_id'] ?? '',
		't_verein' => $spieler['club_contact'] ?? '',
		't_dwz' => $spieler['DWZ'] ?? '',
		't_elo' => $spieler['FIDE_Elo'] ?? '',
		't_fidetitel' => $spieler['FIDE_Titel'] ?? '',
		'gastspieler' => $data['gastspieler_status'] ? ($gastspieler ? 'ja' : 'nein') : NULL
	];
	zzform_insert('participations', $line, E_USER_ERROR);
	return true;
}

/**
 * Sucht Spieler/in nach Benutzereingaben in DWZ-Datenbank
 *
 * @param array $data
 * @param array $postdata (via POST übergebene Daten)
 *		sex, date_of_birth, last_name, first_name
 * @return array
 */
function cms_team_spielersuche($data, $postdata) {
	// Suchparameter zusammenbauen
	$where = '';
	if ($postdata['geschlecht'])
		$where .= sprintf(' AND Geschlecht = "%s"', wrap_db_escape(strtoupper($postdata['geschlecht'])));
	if ($postdata['date_of_birth'] AND $date = zz_check_date($postdata['date_of_birth']))
		$where .= sprintf(' AND Geburtsjahr = %d', substr($date, 0, 4));
	else
		$where .= sprintf(' AND Geburtsjahr <= %d AND Geburtsjahr >= %d', 
			date('Y') - $data['alter_min'], date('Y') - $data['alter_max']
		);
	$spielername = $postdata['last_name'] ? $postdata['last_name'].'%,' : '%,';
	$spielername .= $postdata['first_name'] ? $postdata['first_name'].'%' : '%';
	// Für die Doofies auch falsch herum:
	$spielername_r = $postdata['first_name'] ? $postdata['first_name'].'%,' : '%,';
	$spielername_r .= $postdata['last_name'] ? $postdata['last_name'].'%' : '%';
	
	//	Suche starten
	$sql = 'SELECT CONCAT(ZPS, "-", Mgl_Nr) AS unique_id
			, ZPS, Mgl_Nr, Spielername, Geschlecht, Geburtsjahr, DWZ, FIDE_Elo
			, contact
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers ok
			ON dwz_spieler.ZPS = ok.identifier 
		LEFT JOIN contacts USING (contact_id)
		WHERE (ISNULL(Status) OR Status != "P")
		AND (Spielername LIKE "%s" OR Spielername LIKE "%s")
		AND CONCAT(ZPS, "-", Mgl_Nr) NOT IN ("%s")
		AND ok.current = "yes"
		%s
		ORDER BY Spielername';
	$sql = sprintf($sql
		, wrap_db_escape($spielername)
		, wrap_db_escape($spielername_r)
		, !empty($data['player_passes_dsb']) ? implode('","', $data['player_passes_dsb']) : ''
		, $where
	);
	$treffer = wrap_db_fetch($sql, 'unique_id');
	foreach (array_keys($treffer) as $id) {
		$name = explode(',', $treffer[$id]['Spielername']);
		$treffer[$id]['first_name'] = $name[1];
		$treffer[$id]['last_name'] = $name[0];
	}
	return $treffer;
}
