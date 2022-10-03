<?php

/**
 * tournaments module
 * Line up for team tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2020-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * submit lineup for team after first round has been paired
 *
 * @param array $params
 * @return array
 */
function mod_tournaments_make_lineup($params) {
	$data = mod_tournaments_make_lineup_active($params);
	if (!$data['text']) return false;

	$data = json_decode($data['text'], true);
	if (!$data) return false;

	if (!empty($_GET['board'])) $data['board_saved'] = true;

	$data['board_count'] = 0;
	$data['player_count'] = 0;
	foreach ($data['players'] as $participation_id => $player) {
		if ($player['board_no']) $data['board_count']++;
		$data['player_count']++;
		$data['players'][$participation_id]['boards_max'] = $data['boards_max'];
	}
	if ($data['board_count'] < $data['boards_min']
		AND $data['board_count'] < $data['player_count']) {
	// 1. no board_no in team: it is a list of registered players, set correct
	// order of players
		$data = mod_tournaments_make_lineup_boards($data);
	} else {
	// 2. board_no? set current line up
	// allow byes at end of line up, or mark players as not coming
	// checkbox for using this line up for all rounds automatically if no other
	// data is posted
		$data = mod_tournaments_make_lineup_round($data);
	}
	
	$page['text'] = wrap_template('lineup', $data);
	$page['query_strings'][] = 'board';
	return $page;
}

/**
 * check if there is a possibility to set the line-up for the next round, i. e.
 * 1) a round is paired,
 * 2) round has not started, 
 * 3) timeframe for line-up is open
 *
 * @param array $params
 * @return array
 */
function mod_tournaments_make_lineup_active($params) {
	static $teamdata;
	$team_identifier = implode('/', $params);
	if (!empty($teamdata[$team_identifier])) {
		$page['text'] = $teamdata[$team_identifier];
		$page['content_type'] = 'json';
		return $page;
	}
	array_pop($params);
	$event_identifier = implode('/', $params);

	// get next round
	$current_round = mf_tournaments_current_round($event_identifier) + 1;

	// check: all rounds played?
	$sql = 'SELECT event_id, runden AS rounds, urkunde_parameter AS parameter
			, bretter_min AS boards_min, bretter_max AS boards_max
		FROM tournaments
		LEFT JOIN events USING (event_id)
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($event_identifier));
	$data = wrap_db_fetch($sql);
	if ($data['rounds'] < $current_round) return false;
	
	$sql = 'SELECT team_id FROM teams WHERE identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($team_identifier));
	$data['team_id'] = wrap_db_fetch($sql, '', 'single value');
	if (!$data['team_id']) return false;

	// 1) check: has current round already pairing for team?
	$sql = 'SELECT paarung_id
			, IF(heim_team_id = %d, 1, NULL) AS home_team
		FROM paarungen
		WHERE event_id = %d AND runde_no = %d
		AND (heim_team_id = %d OR auswaerts_team_id = %d)';
	$sql = sprintf($sql
		, $data['team_id']
		, $data['event_id']
		, $current_round
		, $data['team_id']
		, $data['team_id']
	);
	$pairing = wrap_db_fetch($sql);
	if (!$pairing) return false;
	$data += $pairing;

	if ($data['parameter']) {
		parse_str($data['parameter'], $parameter);
		$data += $parameter;
	}
	if (wrap_access('tournaments_lineup_until_begin_of_round'))
		$data['lineup_before_round_mins'] = 0;

	// 2, 3) change lineup until n minutes before start of round
	// @todo alternatively check if there are already games played in round
	$sql = 'SELECT date_begin, time_begin
			, IF(DATE_ADD(NOW(), INTERVAL %s MINUTE) > CONCAT(date_begin, " ", time_begin), NULL, 1) AS lineup_open
		FROM events
		WHERE runde_no = %d
		AND main_event_id = %d';
	$sql = sprintf($sql
		, !empty($data['lineup_before_round_mins']) ? $data['lineup_before_round_mins'] : 0
		, $current_round
		, $data['event_id']
	);
	$round = wrap_db_fetch($sql);
	if (!$round['lineup_open']) return false;

	$sql = 'SELECT participation_id, rang_no, brett_no AS board_no
			, CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS person
			, person_id
	    FROM participations
	    WHERE team_id = %d
	    AND usergroup_id = %d
	    AND teilnahme_status = "Teilnehmer"
	    AND (ISNULL(spielberechtigt) OR spielberechtigt = "ja")
	    ORDER BY brett_no, rang_no';
	$sql = sprintf($sql, $data['team_id'], wrap_id('usergroups', 'spieler'));
	$data['players'] = wrap_db_fetch($sql, 'participation_id');
	if (!$data['players']) return false;
	
	$data += $round;
	$data['current_round'] = $current_round;
	$page['text'] = $teamdata[$team_identifier] = json_encode($data);
	$page['content_type'] = 'json';
	return $page;
}

/**
 * set board nos for all players that are participating
 *
 * @param array $data
 * @return array
 */
function mod_tournaments_make_lineup_boards($data) {
	$data['set_board_order'] = true;

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $data;
	if (empty($_POST['board'])) return $data;

	$existing_boards = [];
	foreach ($data['players'] as $participation_id => $player) {
		if (empty($_POST['board'][$participation_id])) continue;
		if (!is_numeric($_POST['board'][$participation_id])) {
			$data['error_no_numeric_values'] = true;
			continue;
		}
		if (strstr($_POST['board'][$participation_id], '.')) {
			$data['error_no_fractions'] = true;
			continue;
		}
		if ($_POST['board'][$participation_id] > $data['boards_max']) {
			$data['error_board_no_too_high'] = true;
			continue;
		}
		if ($_POST['board'][$participation_id] < 1) {
			$data['error_board_no_too_low'] = true;
			continue;
		}
		if (in_array($_POST['board'][$participation_id], $existing_boards)) {
			$data['error_board_no_duplicate'] = true;
			continue;
		}
		$existing_boards[] = $_POST['board'][$participation_id];
		$data['board_count']++;
		$data['players'][$participation_id]['board_no'] = $_POST['board'][$participation_id];
	}
	if ($data['board_count'] < $data['boards_min']
		AND $data['board_count'] < $data['player_count']) {
		$data['not_enough_players'] = true;
	} else {
		$values = [];
		$values['action'] = 'update';
		$values['ids'] = ['participation_id'];
		foreach ($data['players'] as $player) {
			$values['POST']['participation_id'] = $player['participation_id'];
			$values['POST']['brett_no'] = $player['board_no'];
			$ops = zzform_multi('teilnahmen', $values);
			if (!$ops['id']) {
				wrap_error(sprintf(
					'Konnte Brettnummer nicht festlegen (Teilnahme-ID %d, Brett-Nr: %d)'
					, $player['participation_id'], $player['board_no']
				), E_USER_ERROR);
			}
		}
		wrap_redirect_change('?board=1');
	}
	return $data;	
}

function mod_tournaments_make_lineup_round($data) {
	// remove players without board_no
	foreach ($data['players'] as $participation_id => $player) {
		if (empty($player['board_no'])) unset($data['players'][$participation_id]);
	}

	// lineup for this round already sent?
	$person_ids = [];
	foreach ($data['players'] as $player)
		$person_ids[] = $player['person_id'];	

	$sql = 'SELECT partie_id, weiss_person_id, schwarz_person_id
			, partiestatus_category_id, brett_no
		FROM partien
		WHERE paarung_id = %d
		AND (weiss_person_id IN (%s) OR schwarz_person_id IN (%s))
		ORDER BY brett_no';
	$sql = sprintf($sql
		, $data['paarung_id']
		, implode(',', $person_ids)
		, implode(',', $person_ids)
	);
	$games = wrap_db_fetch($sql, 'partie_id');
	if ($games) {
		$data['lineup_complete'] = true;
		foreach ($data['players'] as $participation_id => $player) {
			foreach ($games as $game) {
				if ($player['person_id'] === $game['weiss_person_id']) {
					$data['players'][$participation_id]['white'] = true;
				} elseif ($player['person_id'] === $game['schwarz_person_id']) {
					$data['players'][$participation_id]['black'] = true;
				} else {
					continue;
				}
				$data['players'][$participation_id]['board_no_round'] = $game['brett_no'];
				if ($game['partiestatus_category_id'] === wrap_category_id('partiestatus/kampflos')) {
					$data['players'][$participation_id]['bye'] = true;
				}
			}
		}
		return $data;
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $data;
	if (empty($_POST['lineup'])) return $data;

	$data['selected_players'] = 0;
	foreach ($data['players'] as $participation_id => $player) {
		if (empty($_POST['lineup'][$participation_id])) continue;
		if ($_POST['lineup'][$participation_id] !== 'on') continue;
		$data['players'][$participation_id]['lineup'] = true;
		$data['selected_players']++;
		if (!empty($_POST['bye'][$participation_id]) AND $_POST['bye'][$participation_id] === 'on')
			$data['players'][$participation_id]['bye'] = true;
	}
	if ($data['selected_players'] < $data['boards_min']
		AND $data['selected_players'] < $data['player_count']) {
		if (empty($_POST['selection_ok']) OR $_POST['selection_ok'] !== 'on')
			$data['reselect_not_enough_players'] = true;
	}
	if ($data['selected_players'] > $data['boards_min']) {
		$data['reselect_too_many_players'] = true;
	}
	if (empty($data['reselect_not_enough_players'])
		AND empty($data['reselect_too_many_players'])) {
		$board_no = 0;

		$sql = 'SELECT partie_id, brett_no
			FROM partien
			WHERE paarung_id = %d
			ORDER BY brett_no';
		$sql = sprintf($sql, $data['paarung_id']);
		$games = wrap_db_fetch($sql, 'brett_no');

		$values = [];
		$values['ids'] = [
			'event_id', 'paarung_id', 'weiss_person_id', 'schwarz_person_id',
			'partiestatus_category_id'
		];
		
		$home_team_first_board = 'schwarz';
		$away_team_first_board = 'weiss';
		if (!empty($data['home_team_first_board'])) {
			if ($data['home_team_first_board'] === 'white') {
				$home_team_first_board = 'weiss';
				$away_team_first_board = 'schwarz';
			}
		}
		
		foreach ($data['players'] as $player) {
			if (empty($player['lineup'])) continue;
			$board_no++;
			if ($board_no & 1) {
				if ($data['home_team']) $colour = $home_team_first_board;
				else $colour = $away_team_first_board;
			} else {
				if ($data['home_team']) $colour = $away_team_first_board;
				else $colour = $home_team_first_board;
			}
			$other_colour = ($colour === 'weiss') ? 'schwarz' : 'weiss';

			$values['POST'] = [];
			if (empty($games[$board_no])) {
				$values['action'] = 'insert';
				$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/laufend');
				$values['POST']['event_id'] = $data['event_id'];
				$values['POST']['runde_no'] = $data['current_round'];
				$values['POST']['paarung_id'] = $data['paarung_id'];
				$values['POST']['brett_no'] = $board_no;
			} else {
				$values['action'] = 'update';
				$values['POST']['partie_id'] = $games[$board_no]['partie_id'];
			}
			$values['POST'][$colour.'_person_id'] = $player['person_id'];
			if (!empty($player['bye'])) {
				$values['POST'][$colour.'_ergebnis'] = 0;
				$values['POST'][$other_colour.'_ergebnis'] = 1;
				$values['POST']['heim_wertung'] = $data['home_team'] ? 0 : 1;
				$values['POST']['auswaerts_wertung'] = $data['home_team'] ? 1 : 0;
				$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/kampflos');
			}
			$ops = zzform_multi('partien', $values);
			if (!$ops['id']) {
				$error = true;
				if ($values['action'] === 'insert') {
					// if violation of UNIQUE: if insert then update
					$sql = 'SELECT partie_id
						FROM partien
						WHERE paarung_id = %d AND brett_no = %d';
					$sql = sprintf($sql, $data['paarung_id'], $board_no);
					$partie_id = wrap_db_fetch($sql, '', 'single value');
					if ($partie_id) {
						$values['action'] = 'update';
						$values['POST']['partie_id'] = $games[$board_no]['partie_id'];
						if ($values['POST']['partiestatus_category_id'] === wrap_category_id('partiestatus/laufend')) {
							unset($values['POST']['partiestatus_category_id']);
						}
						$ops = zzform_multi('partien', $values);
						if ($ops['id']) $error = false;
					}
				}	
				if ($error) {
					wrap_error(sprintf(
						'Konnte Partie nicht festlegen (Paarung-ID %d, Brett-Nr: %d)'
						, $data['paarung_id'], $board_no
					), E_USER_ERROR);
				}
			}
		}
		wrap_redirect_change();
	}

	return $data;
}
