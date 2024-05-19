<?php 

/**
 * tournaments module
 * import PGN files to database
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Schreibt PGN-Dateien in Datenbank
 *
 * @param array $vars
 *	[0]: Jahr
 *	[1]: Kennung Termin
 * 	[2]: (optional) Runde
 *  [3]: (optional) Brett oder Tisch.Brett (5, 5.6)
 */
function mod_tournaments_make_games($vars, $settings, $event) {
	wrap_setting('cache', false);
	if (count($vars) < 2) return false;

	ignore_user_abort(1);
	ini_set('max_execution_time', 60);

	$tisch_no = false;
	$brett_no = false;
	$runde_no = false;
	$where = false;
	$live = false;

	// Variablen prüfen
	switch (count($vars)) {
	case 4:
		$runde_no = $vars[2];
		if (!preg_match('/[0-9]+\.*[0-9]*/', $vars[3])) return false;
		if (strstr($vars[3], '.')) {
			// Mannschaftsturnier mit Tisch/Brett
			$brett = explode('.', $vars[3]);
			$tisch_no = $brett[0];
			$brett_no = $brett[1];
			$where = sprintf(
				'AND partien.brett_no = %d AND paarungen.tisch_no = %d AND partien.runde_no = %d',
				$brett_no, $tisch_no, $runde_no
			);
			$pgn_filename = sprintf('%d-%d-%d', $runde_no, $tisch_no, $brett_no);
		} else {
			// Einzelturnier nur mit Brett
			$brett_no = $vars[3];
			if (!is_numeric($brett_no)) return false;
			$where = sprintf(
				'AND partien.brett_no = %d AND partien.runde_no = %d',
				$brett_no, $runde_no
			);
			$pgn_filename = sprintf('%d-%d', $runde_no, $brett_no);
		}
		break;
	case 3:
		$runde_no = $vars[2];
		if (substr($runde_no, -5) === '-live') {
			$pgn_filename = sprintf('%s', $runde_no);
			$live = true;
			$runde_no = substr($runde_no, 0, -5);
			$pgn_filename_not_live = sprintf('%s', $runde_no);
		} else {
			$pgn_filename = sprintf('%d', $runde_no);
		}
		$where = sprintf('AND partien.runde_no = %d', $runde_no);
		break;
	case 2:
		$pgn_filename = 'gesamt';
		break;
	}

	$error_msg = sprintf('Termin %s/%s',
		wrap_html_escape($vars[0]),
		wrap_html_escape($vars[1])
	);
	if ($runde_no) $error_msg .= sprintf(', Runde %s', wrap_html_escape($runde_no));
	if ($tisch_no) $error_msg .= sprintf(', Tisch %s', wrap_html_escape($tisch_no));
	if ($brett_no) $error_msg .= sprintf(', Brett %s', wrap_html_escape($brett_no));

	// Termin, Partien in Datenbank vorhanden?
	$sql = 'SELECT COUNT(partie_id) AS partien
			, tournament_id
		FROM events
		JOIN partien USING (event_id)
		JOIN tournaments USING (event_id)
		LEFT JOIN paarungen USING (paarung_id)
		JOIN categories
			ON events.event_category_id = categories.category_id
		WHERE events.event_id = %d
		%s
		GROUP BY events.event_id
	';
	$sql = sprintf($sql, $event['event_id'], $where);
	$event = array_merge($event, wrap_db_fetch($sql));
	if (!$event['partien']) {
		$page['text'] = sprintf(
			'PGN-Import: Keine Partien vorhanden (%s).', $error_msg
		);
		$page['status'] = 404;
		return $page;
	}

	// PGN-Datei vorhanden?
	$pgn_path = wrap_setting('media_folder').'/pgn/'.$event['identifier'].'/%s.pgn';
	$pgn_filename = sprintf($pgn_path, $pgn_filename);
	if (file_exists($pgn_filename)) {
		$pgn = file($pgn_filename);
	} elseif ($live) {
		// Gibt es Live-Links in Tabelle?
		$pgn = explode("\n", mf_tournaments_pgn_file_from_tournament($event['tournament_id']));
	} else {
		$pgn = false;
	}
	if (!$pgn) {
		$page['text'] = sprintf(
			'PGN-Import: PGN-Datei nicht vorhanden (%s).', $error_msg
		);
		$page['status'] = 404;
		return $page;
	}

	// Partien aus Datenbank abfragen
	$sql = 'SELECT partie_id
			, CONCAT(IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname, ", ", weiss.t_vorname) AS White
			, CONCAT(IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname, ", ", schwarz.t_vorname) AS Black
			, IF((ISNULL(weiss_ergebnis) OR ISNULL(schwarz_ergebnis)), "*",
				CONCAT(
					CASE weiss_ergebnis WHEN 0.0 THEN 0 WHEN 0.5 THEN "1/2" WHEN 1.0 THEN 1 END,
					"-",
					CASE schwarz_ergebnis WHEN 0.0 THEN 0 WHEN 0.5 THEN "1/2" WHEN 1.0 THEN 1 END
				)
			) AS Result
			, IF(block_ergebnis_aus_pgn = "ja", 1, NULL) AS block_ergebnis_aus_pgn
			, weiss_ergebnis
			, schwarz_ergebnis
			, heim_spieler_farbe
			, IF(vertauschte_farben = "ja", 1, NULL) AS vertauschte_farben
			, partien.runde_no
			, partien.kommentar
			, CONCAT (partien.runde_no, ".", IFNULL(CONCAT(paarungen.tisch_no, "."), ""), partien.brett_no) AS Round_With_Board
		FROM partien
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN persons white_person
			ON partien.weiss_person_id = white_person.person_id
		LEFT JOIN participations weiss
			ON weiss.contact_id = white_person.contact_id
			AND weiss.usergroup_id = /*_ID usergroups spieler _*/
			AND weiss.event_id = partien.event_id
		LEFT JOIN persons black_person
			ON partien.schwarz_person_id = black_person.person_id
		LEFT JOIN participations schwarz
			ON schwarz.contact_id = black_person.contact_id
			AND schwarz.usergroup_id = /*_ID usergroups spieler _*/
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partiestatus_category_id != /*_ID categories partiestatus/kampflos _*/
		%s';
	$sql = sprintf($sql,
		$event['event_id'],
		$where
	);
	$partien = wrap_db_fetch($sql, 'partie_id');

	// Datei Partie für Partie auswerten
	wrap_include_files('pgn', 'chess');
	$games = mf_chess_pgn_parse($pgn, $pgn_filename);
	if ($function = wrap_setting('pgn_preparation_function'))
		$games = $function($games, $event['event_id']);

	if (!empty($pgn_filename_not_live)) {
		$pgn_not_live = sprintf($pgn_path, $pgn_filename_not_live);
		if (file_exists($pgn_not_live)) {
			$games_not_live = mf_chess_pgn_parse(file($pgn_not_live), $pgn_not_live);
		}
	}

	wrap_include_files('functions', 'zzform');

	$old_error_handling = wrap_setting('error_handling');
	wrap_setting('error_handling', 'output');

	$event['db_errors'] = 0;
	$event['updates'] = 0;
	$event['no_updates'] = 0;
	$event['not_found'] = 0;
	$event['wrong_pgn'] = 0;

	if (!empty($games_not_live))
		$games_not_live = cms_partienupdate_pgn_index($games_not_live);
	$games = cms_partienupdate_pgn_index($games);

	foreach ($partien as $partie_id => $partie) {
		if (!empty($games_not_live)) {
			$not_live_partie = cms_partienupdate_pgnfind($games_not_live, $partie);
			// check if game in saved PGN export is unfinished, then prefer live game
			if (!empty($not_live_partie['moves']) AND substr(trim($not_live_partie['moves']), -1) !== '*') {
				$partien[$partie_id] = cms_partienupdate_pgnfind($games, $partie);
				continue;
			}
		}
		$partien[$partie_id] = cms_partienupdate_pgnfind($games, $partie);
		if (!empty($partien[$partie_id]['head'])) {
			// - Falls Partie vorhanden, PGN importieren
			$partien[$partie_id]['moves'] = trim($partien[$partie_id]['moves']);
			if ($partien[$partie_id]['moves'] === $partien[$partie_id]['head']['Result']) {
				if ($partien[$partie_id]['moves'] === $partie['Result']) continue;
				if ($partien[$partie_id]['moves'] === '*') continue;
			}
			$line = [
				'partie_id' => $partie_id
			];
			// @todo check if it's only a comment
			if ($comment = mf_chess_pgn_only_comment($partien[$partie_id]['moves'], $partien[$partie_id]['head']['Result'])) {
				if (!empty($partien[$partie_id]['kommentar'])) continue;
				if (!empty($partien[$partie_id]['pgn'])) continue;
				$line['kommentar'] = $comment;
			} elseif (!in_array($partien[$partie_id]['moves'], ['*', '0-1', '1-0', '1/2-1/2'])) {
				$line['pgn'] = $partien[$partie_id]['moves'];
			}
			$ergebnis = mf_tournaments_pgn_result($partien[$partie_id]['moves'], $partien[$partie_id]['head']['Result']);
			if ($ergebnis) {
				if ($partie['vertauschte_farben']) {
					$schwarz = $ergebnis['schwarz'];
					$ergebnis['schwarz'] = $ergebnis['weiss'];
					$ergebnis['weiss'] = $schwarz;
				}
				if (!$partie['block_ergebnis_aus_pgn']) {
					$line['weiss_ergebnis'] = $ergebnis['weiss'];
					$line['schwarz_ergebnis'] = $ergebnis['schwarz'];
					if (wrap_setting('tournaments_type_team')) {
						switch ($partie['heim_spieler_farbe']) {
						case 'schwarz':
							$line['heim_wertung'] = $ergebnis['schwarz'];
							$line['auswaerts_wertung'] = $ergebnis['weiss'];
							break;
						case 'weiß':
							$line['heim_wertung'] = $ergebnis['weiss'];
							$line['auswaerts_wertung'] = $ergebnis['schwarz'];
							break;
						}
					}
				} else {
					$fehler = false;
					if (mf_tournaments_pgn_result_dec($ergebnis['weiss']) !== mf_tournaments_pgn_result_dec($partie['weiss_ergebnis'])) {
						$fehler = true;
					} elseif (mf_tournaments_pgn_result_dec($ergebnis['schwarz']) !== mf_tournaments_pgn_result_dec($partie['schwarz_ergebnis'])) {
						$fehler = true;
					}
					if ($fehler) {
						wrap_log(sprintf(
							'Ergebnis in der PGN-Datei weicht ab. %s %d %s. Runde: %s - %s Datenbank %s, PGN %s-%s',
							$event['event'], $event['year'], $partie['runde_no'],
							$partie['White'], $partie['Black'], $partie['Result'],
							$ergebnis['weiss'], $ergebnis['schwarz']
						));
					}
				}
			}
			$moves = mf_chess_pgn_to_html($partien[$partie_id]);
			if ($partien[$partie_id]['moves'] !== '*') {
				$line['eco'] = $partien[$partie_id]['head']['ECO'] ?? '';
				if ($line['eco'] === '*') $line['eco'] = '';
			}
			$line['halbzuege'] = $moves['move'];
			$line['vertauschte_farben'] = isset($partien[$partie_id]['vertauschte_farben']) ? 'ja' : 'nein';
			if (!empty($moves['BlackClock']))
				$line['schwarz_zeit'] = $moves['BlackClock'];
			if (!empty($moves['WhiteClock']))
				$line['weiss_zeit'] = $moves['WhiteClock'];
			$game_id = zzform_update('partien', $line);
			if (is_null($game_id)) {
				wrap_log(sprintf(
					'PGN-Import: Partie %s-%s, %s %d, Runde %d konnte nicht importiert werden.',
					$partie['White'], $partie['Black'], $event['event'], $event['year'], $partie['runde_no']
				));
				$event['db_errors']++;
			} elseif ($game_id) {
				$event['updates']++;
			} elseif ($game_id === 0) {
				$event['no_updates']++;
			}
		} else {
			// - Falls nicht, PGN in Fehlerlog oder Fehler-PGN-Datei
			if (!wrap_setting('background_job')) {
				// Fehler in PGNs nur angeben, wenn direkt jemand vor Rechner sitzt
				// nicht bei automatischem Sync
				wrap_log(sprintf(
					'PGN-Import: Partie %s-%s, %s %d, Runde %d nicht gefunden.',
					$partie['White'], $partie['Black'], $event['event'], $event['year'], $partie['runde_no']
				));
			}
			$event['not_found']++;
			/*
			// @todo Sollte eine Partie gelöscht werden, falls vorhanden?
			$line = [
				'partie_id' => $partie_id,
				'pgn' => '',
				'eco' => ''
			];
			zzform_update('partien', $line);
			*/
		}
	}

	// - Fehlende Partien ohne PGN in Fehlerlog
	foreach ($games as $index => $game) {
		$head = '';
		foreach ($game['head'] as $index => $value) $head .= sprintf('[%s "%s"]', $index, $value)."\n";
		if (!wrap_setting('background_job') AND count($games) < 100) {
			// Fehlerlog nur bei einzelnen Partien, sonst zuviele
			// z. B. bei Upload einer einzelnen DEM-PGN für alle Meisterschaften
			wrap_log('PGN-Import: Für diese PGN konnte keine Partie gefunden werden: '.$head);
		}
		$event['wrong_pgn']++;
	}

	if ($runde_no) $event['runde_no'] = $runde_no;
	if ($tisch_no) $event['tisch_no'] = $tisch_no;
	if ($brett_no) $event['brett_no'] = $brett_no;
	wrap_setting('error_handling', $old_error_handling);
	$page['text'] = wrap_template('games-update', $event);
	return $page;
}

/**
 * Indiziert PGN-Partienarray nach 'Weiss/Schwarz', ohne Leerzeichen und Umlaute
 * bspw. 'duchampsmarcel/groeninggeorg' zur einfacheren Suche
 *
 * @param array $games
 * @return array
 * @todo PGN-Index bereits bei erstem Auslesen so schreiben? Spart Zeit
 */
function cms_partienupdate_pgn_index($games) {
	$new_games = [];
	foreach ($games as $game) {
		if (empty($game['head'])) continue;
		$white = cms_partienupdate_normalize_name($game['head']['White']);
		$black = cms_partienupdate_normalize_name($game['head']['Black']);
		$new_games[$white.'/'.$black] = $game;
	}
	return $new_games;
} 

/**
 * Suche eine Partie nach White und Black in PGN-Array
 * Namen werden normalisiert, Leerzeichen spielen keine Rolle
 *
 * @param array $games (gefundene Partien werden hier entfernt)
 * @param array $game
 *		string 'White', string 'Black'
 * @return array $pgn
 */
function cms_partienupdate_pgnfind(&$games, $partie) {
	$white = cms_partienupdate_normalize_name($partie['White']);
	$black = cms_partienupdate_normalize_name($partie['Black']);

	$index = sprintf('%s/%s', $white, $black);
	if (array_key_exists($index, $games)) {
		$pgn['head'] = $games[$index]['head'];
		$pgn['moves'] = $games[$index]['moves'];
		unset($games[$index]);
		return $pgn;
	}

	// vertauschte Farben?
	$index = sprintf('%s/%s', $black, $white);
	if (array_key_exists($index, $games)) {
		$pgn['head'] = $games[$index]['head'];
		$pgn['moves'] = $games[$index]['moves'];
		$pgn['vertauschte_farben'] = true;
		unset($games[$index]);
		return $pgn;
	}
	
	// Round with Board?
	if (wrap_setting('pgn_match_round_table_board') AND !empty($partie['Round_With_Board'])) {
		foreach ($games as $index => $game) {
			if (empty($game['head']['Round'])) continue;
			if ($game['head']['Round'] !== $partie['Round_With_Board']) continue;
			$pgn['head'] = $games[$index]['head'];
			$pgn['moves'] = $games[$index]['moves'];
			unset($games[$index]);
			return $pgn;
		}
	}
	
	return [];
}

/**
 * Normalisiert einen Namen (diakritische Zeichen werden gestrichen, Umlaute
 * ersetzt, Groß-/Kleinschreibung ignoriert, Leerzeichen ignoriert)
 * um ihn eindeutig in einer PGN-Datei finden zu können
 *
 * Daten werden aus Performancegründen statisch zwischengespeichert
 *
 * @param string $name
 * @return string
 */
function cms_partienupdate_normalize_name($name) {
	static $names = [];
	if (!$name) return '';
	if (in_array($name, array_keys($names))) return $names[$name];
	$names[$name] = strtolower(wrap_filename(str_replace('-', '', $name), ''));
	return $names[$name];
}

/**
 * Auswertung eines PGN-Strings, ob ein Ergebnis am Ende steht
 *
 * @param string $pgn
 * @param string $result_Tag
 * @return array
 */
function mf_tournaments_pgn_result($pgn, $result_tag) {
	$moves = explode(' ', trim($pgn));
	$result = array_pop($moves);
	if ($result === '*') {
		$result = $result_tag;
		if ($result === '*') return false;
	}
	if (!strstr($result, '-')) return false;
	$result = explode('-', $result);
	$ergebnis['weiss'] = mf_tournaments_pgn_result_dec($result[0]);
	$ergebnis['schwarz'] = mf_tournaments_pgn_result_dec($result[1]);
	return $ergebnis;
}

function mf_tournaments_pgn_result_dec($ergebnis) {
	switch ($ergebnis) {
		case '1/2': return 0.5; 
		default: return $ergebnis.'.0';
	}
}
