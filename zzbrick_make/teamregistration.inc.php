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
	$data['club_players'] = mod_tournaments_make_teamregistration_club_players($data);
	
	$data['add'] = true;
	if (!empty($data['spielerzahl']) AND $data['bretter_max'] <= $data['spielerzahl']) {
		$data['add'] = false;
	}
	if (!$data['add']) unset($data['club_players']);
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST')
		mod_tournaments_make_teamregistration_change($_POST, $data);

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
 * Applies POSTed changes to a team's lineup registration.
 *
 * Parses and validates board numbers from $postdata['rank'], then:
 * - 'new': add a new player (by DSB match via matching_id, or manual add if allowed)
 * - 'dsb_id_<id>': add an existing club player by DSB id
 * - 'tln_<id>': update rank of an existing participation or delete it when rank is empty
 *
 * Updates the database via helpers (insert/update/delete) and augments $data with
 * UI/control flags for the template (e.g. new_matches, error_*). If changes were
 * made and no further user interaction is required, returns a redirect response.
 *
 * Expected $postdata (subset):
 * - rank[code]: string|int board number per code ('new'|'dsb_id_<id>'|'tln_<id>')
 * - guest_player[code]: 'on'|'off'
 * - matching_id: string|int (DSB player id when a match is chosen)
 * - first_name, last_name, sex, date_of_birth
 * - ergaenzen: truthy to add a non-DSB player
 * - cancel: truthy to abort current 'new' flow
 *
 * Possible flags set on $data (subset):
 * - error_board_no_numbers, error_board_no_bigger_zero
 * - new_matches, new_match_without_rank, new_player_not_found, new_player_more_data, new_player_add
 * - post_* helper values (e.g. post_rank, post_date_of_birth, post_guest_player)
 *
 * @param array $postdata Raw POST payload (must include 'rank' array for processing).
 * @param array &$data Team/event context, mutated and augmented by this function.
 *
 * @return void
 */
 function mod_tournaments_make_teamregistration_change($postdata, &$data) {
	if (empty($postdata['rank'])) return false;
	if (!is_array($postdata['rank'])) return false;
	
	$redirect = true;
	$changed = false; // es kann sein, dass zuviele Spieler angegeben werden
	foreach ($postdata['rank'] as $code => $rank_no) {
		// Nur Integer werden akzeptiert (warum auch immer da Leute was anderes eingeben)
		if ($rank_no) {
			$rank_no = trim($rank_no);
			if (substr($rank_no, -1) === '.') {
				$rank_no = substr($rank_no, 0, -1);
			}
			if (!wrap_is_int($rank_no)) {
				$data['error_board_no_numbers'] = true;
				continue;
			}
			if ($rank_no < 0) {
				$data['error_board_no_bigger_zero'] = true;
				continue;
			}
		}
		if ($code === 'new' AND $data['add']) {
			if ($rank_no) $data['post_rank'] = $rank_no;
			$data['post_guest_player'] = mf_tournaments_guest_player($data, $postdata, $code, false);
			// Neuer Spieler nicht aus Vereinsliste wird ergänzt
			if (!empty($postdata['matching_id']) AND $rank_no) {
				$player = mf_ratings_players_dsb(['player_id_dsb' => $postdata['matching_id']]);
				if ($player) {
					$player['date_of_birth'] = zz_check_date($postdata['date_of_birth']);
					$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
					$success = mf_tournaments_team_player_insert($player, $data, $rank_no);
					if ($success) $changed = true;
				}
				continue;
			} elseif (!empty($postdata['matching_id']) AND empty($postdata['cancel'])) {
				$player = mf_ratings_players_dsb(['player_id_dsb' => $postdata['matching_id']]);
				$data['new_match_without_rank'] = true;
				$data['new_player_pass_dsb'] = $player['player_pass_dsb'];
				$data['new_player_id_dsb'] = $player['player_id_dsb'];
				$data['new_first_name'] = $player['first_name'];
				$data['new_last_name'] = $player['last_name'];
				$data['new_sex'] = $player['sex'];
				$data['new_birth_year'] = $player['birth_year'];
				$data['new_dsb_dwz'] = $player['dsb_dwz'];
				$redirect = false;
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
				$player['sex'] = $postdata['sex'];
				$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
				$success = mf_tournaments_team_player_insert($player, $data, $rank_no);
				if ($success) $changed = true;
				// Spieler in eigener Personentabelle suchen
				// Falls nicht vorhanden, ergänzen
				// Teilnahme ergänzen
			} elseif (empty($postdata['cancel'])) {
				// search for
				// first_name, last_name, sex, date_of_birth
				$data['new_matches'] = mod_tournaments_make_teamregistration_playersearch($data, $postdata);
				$redirect = false;
				$data['post_date_of_birth'] = $postdata['date_of_birth'];
				if (!count($data['new_matches'])) {
					$data['post_first_name'] = $postdata['first_name'];
					$data['post_last_name'] = $postdata['last_name'];
					$data['post_sex'] = $postdata['sex'];
					// Keinen Spieler gefunden
					if (wrap_setting('tournaments_player_pool')) {
						// DSB-Mitgliedschaft erforderlich
						$data['new_player_not_found'] = true;
					} elseif (!empty($postdata['date_of_birth']) AND !zz_check_date($postdata['date_of_birth'])) {
						$data['date_of_birth_wrong'] = true;
					} else {
						// Turniere ohne erforderliche DSB-Mitgliedschaft:
						// Option, Spieler hinzuzufügen
						$required_fields = ['first_name', 'last_name', 'sex', 'date_of_birth'];
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
		} elseif (str_starts_with($code, 'dsb_id_') AND $rank_no) {
			$player_id_dsb = substr($code, 7);
			if (!array_key_exists($player_id_dsb, $data['club_players'])) continue;
			$player = mf_ratings_players_dsb(['player_id_dsb' => $player_id_dsb]);
			if ($player) {
				$player['guest_player'] = mf_tournaments_guest_player($data, $postdata, $code);
				$success = mf_tournaments_team_player_insert($player, $data, $rank_no);
				if ($success) $changed = true;
			}
		} elseif (substr($code, 0, 4) === 'tln_') {
			$participation_id = substr($code, 4); 
			if ($rank_no) {
				// rankings changed
				$line = [
					'participation_id' => $participation_id,
					'rang_no' => $rank_no,
					'gastspieler' => mf_tournaments_guest_player($data, $postdata, $code)
				];
				$result = zzform_update('participations', $line, E_USER_ERROR);
				if ($result) $changed = true;
			} else {
				// deleted from rankings
				$ids = zzform_delete('participations', $participation_id);
				if ($ids) $changed = true;
			}
		}
	}
	if ($changed AND $redirect)
		return wrap_redirect_change();
}

/**
 * Fügt Spieler als Meldung zu Teilnahmen hinzu
 *
 * @param array $player
 * @param array $data
 * @param int $rank_no
 * @return bool
 */
function mf_tournaments_team_player_insert($player, $data, $rank_no) {
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

	$line = [
		'usergroup_id' => wrap_id('usergroups', 'spieler'),
		'event_id' => $data['event_id'],
		'team_id' => $data['team_id'],
		'contact_id' => $contact_id,
		'rang_no' => $rank_no,
		't_vorname' => $player['first_name'],
		't_nachname' => $player['last_name'],
		// bei Nicht-DSB-Mitgliedern nicht vorhandene Daten
		'club_contact_id' => $player['club_contact_id'] ?? '',
		't_verein' => $player['club_contact'] ?? '',
		't_dwz' => $player['dsb_dwz'] ?? '',
		't_elo' => $player['elo_fide'] ?? '',
		't_fidetitel' => $player['fide_title'] ?? '',
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
function mod_tournaments_make_teamregistration_playersearch($data, $post) {
	$filters = [];
	if ($data['alter_min']) $filters['min_age'] = $data['alter_min'];
	if ($data['alter_max']) $filters['max_age'] = $data['alter_max'];
	$filters['sex'] = $post['sex'];
	if ($post['date_of_birth'] AND $date = zz_check_date($post['date_of_birth']))
		$filters['date_of_birth'] = $date;
	if ($data['club_players'])
		$filters['player_id_dsb_excluded'] = array_keys($data['club_players']);
	$filters['last_name'] = $post['last_name'];
	$filters['first_name'] = $post['first_name'];
	
	return mf_ratings_players_dsb($filters);
}

/**
 * get all players of a club that are eligible to participate in this tournament
 *
 * @param array $data
 * @return array
 */
function mod_tournaments_make_teamregistration_club_players($data) {
	if (!$data['zps_code']) return [];

	$filters = [];
	$filters['club_code_dsb'] = $data['zps_code'];
	$filters['player_id_dsb_excluded'] = [];
	foreach ($data['spieler'] as $player) {
		if (empty($player['player_id_dsb'])) continue;
		$filters['player_id_dsb_excluded'][] = $player['player_id_dsb'];
	}
	if ($data['alter_min']) $filters['min_age'] = $data['alter_min'];
	if ($data['alter_max']) $filters['max_age'] = $data['alter_max'];
	if ($data['geschlecht'] === ['W']) $filters['sex'] = 'female';
	if ($data['geschlecht'] === ['M']) $filters['sex'] = 'male';

	return mf_ratings_players_dsb($filters);
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
