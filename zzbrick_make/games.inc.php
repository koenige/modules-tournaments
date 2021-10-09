<?php 

/**
 * tournaments module
 * import PGN files to database
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2021 Gustaf Mossakowski
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
function mod_tournaments_make_games($vars) {
	global $zz_setting;
	global $zz_conf;
	$zz_setting['cache'] = false;
	if (empty($vars)) return false;
	require_once __DIR__.'/../tournaments/cronjobs.inc.php';

	// Zugriffsberechtigt?
	if (!brick_access_rights(['Webmaster'])) wrap_quit(403);
	ignore_user_abort(1);
	ini_set('max_execution_time', 60);

	// Fehler in PGNs nur angeben, wenn direkt jemand vor Rechner sitzt
	// nicht bei automatischem Sync
	$robot_zugriff = $_SESSION['username'] === $zz_setting['robot_username'] ? true : false;
	
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
	case 1:
		if ($vars[0] === 'update') return cms_partienupdate_trigger();
		return false;
	}
	$runde_url = $runde_no.($live ? '-live' : '');

	$error_msg = sprintf('Termin %s/%s',
		wrap_html_escape($vars[0]),
		wrap_html_escape($vars[1])
	);
	if ($runde_no) $error_msg .= sprintf(', Runde %s', wrap_html_escape($runde_no));
	if ($tisch_no) $error_msg .= sprintf(', Tisch %s', wrap_html_escape($tisch_no));
	if ($brett_no) $error_msg .= sprintf(', Brett %s', wrap_html_escape($brett_no));

	// Termin, Partien in Datenbank vorhanden?
	$sql = 'SELECT event_id, events.identifier, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, COUNT(partie_id) AS partien
			, SUBSTRING_INDEX(categories.path, "/", -1) AS event_category
			, tournament_id
			, urkunde_parameter AS parameter
		FROM events
		JOIN partien USING (event_id)
		JOIN tournaments USING (event_id)
		JOIN categories
			ON events.event_category_id = categories.category_id
		WHERE events.identifier = "%d/%s"
		%s
		GROUP BY event_id
	';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]), $where);
	$event = wrap_db_fetch($sql);
	if (!$event OR !$event['partien']) {
		$page['text'] = sprintf(
			'PGN-Import: Keine Partien vorhanden (%s).', $error_msg
		);
		$page['status'] = 404;
		$sql = 'SELECT event_id FROM events
			WHERE events.identifier = "%d/%s"';
		$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
		$event = wrap_db_fetch($sql);
		if ($event) {
			mf_tournaments_job_finish('partien', 0, $event['event_id'], $runde_url);
		}
		return $page;
	}
	parse_str($event['parameter'], $parameter);
	$event += $parameter;

	// PGN-Datei vorhanden?
	$pgn_path = $zz_setting['media_folder'].'/pgn/'.$event['identifier'].'/%s.pgn';
	$pgn_filename = sprintf($pgn_path, $pgn_filename);
	if (file_exists($pgn_filename)) {
		$pgn = file($pgn_filename);
	} elseif ($live) {
		// Gibt es Live-Links in Tabelle?
		require_once $zz_setting['modules_dir'].'/chess/chess/pgn.inc.php';
		$pgn = explode("\n", mf_chess_pgn_file_from_tournament($event['tournament_id']));
	} else {
		$pgn = false;
	}
	if (!$pgn) {
		$page['text'] = sprintf(
			'PGN-Import: PGN-Datei nicht vorhanden (%s).', $error_msg
		);
		$page['status'] = 404;
		mf_tournaments_job_finish('partien', 0, $event['event_id'], $runde_url);
		return $page;
	}

	// Partien aus Datenbank abfragen
	$sql = 'SELECT partie_id
			, CONCAT(weiss.t_nachname, ", ", weiss.t_vorname) AS White
			, CONCAT(schwarz.t_nachname, ", ", schwarz.t_vorname) AS Black
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
		LEFT JOIN teilnahmen weiss
			ON partien.weiss_person_id = weiss.person_id
			AND weiss.usergroup_id = %d
			AND weiss.event_id = partien.event_id
		LEFT JOIN teilnahmen schwarz
			ON partien.schwarz_person_id = schwarz.person_id
			AND schwarz.usergroup_id = %d
			AND schwarz.event_id = partien.event_id
		WHERE partien.event_id = %d
		AND partiestatus_category_id != %d
		%s';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		$event['event_id'],
		wrap_category_id('partiestatus/kampflos'),
		$where
	);
	$partien = wrap_db_fetch($sql, 'partie_id');

	// Datei Partie für Partie auswerten
	require_once $zz_setting['modules_dir'].'/chess/chess/pgn.inc.php';
	$games = mf_chess_pgn_parse($pgn, $pgn_filename);
	if (!empty($event['pgn_preparation_function']))
		$games = $event['pgn_preparation_function']($games, $event['event_id']);

	if (!empty($pgn_filename_not_live)) {
		$pgn_not_live = sprintf($pgn_path, $pgn_filename_not_live);
		if (file_exists($pgn_not_live)) {
			$games_not_live = mf_chess_pgn_parse(file($pgn_not_live), $pgn_not_live);
		}
	}

	require_once $zz_conf['dir'].'/zzform.php';
	require_once $zz_conf['dir'].'/functions.inc.php';

	$old_error_handling = $zz_conf['error_handling'];
	$zz_conf['error_handling'] = 'output';

	$event['db_errors'] = 0;
	$event['updates'] = 0;
	$event['no_updates'] = 0;
	$event['not_found'] = 0;
	$event['wrong_pgn'] = 0;

	if (!empty($games_not_live)) {
		$games_not_live = cms_partienupdate_pgn_index($games_not_live);
	}
	$games = cms_partienupdate_pgn_index($games);

	foreach ($partien as $partie_id => $partie) {
		if (!empty($games_not_live)) {
			$not_live_partie = cms_partienupdate_pgnfind($games_not_live, $partie, $event);
			// check if game in saved PGN export is unfinished, then prefer live game
			if (!empty($not_live_partie['moves']) AND substr(trim($not_live_partie['moves']), -1) !== '*') {
				$partien[$partie_id] = cms_partienupdate_pgnfind($games, $partie, $event);
				continue;
			}
		}
		$partien[$partie_id] = cms_partienupdate_pgnfind($games, $partie, $event);
		if (!empty($partien[$partie_id]['head'])) {
			// - Falls Partie vorhanden, PGN importieren
			$partien[$partie_id]['moves'] = trim($partien[$partie_id]['moves']);
			if ($partien[$partie_id]['moves'] === $partien[$partie_id]['head']['Result']) {
				if ($partien[$partie_id]['moves'] === $partie['Result']) continue;
				if ($partien[$partie_id]['moves'] === '*') continue;
			}
			$values = [];
			$values['action'] = 'update';
			$values['POST']['partie_id'] = $partie_id;
			// @todo check if it's only a comment
			if ($comment = mf_chess_pgn_only_comment($partien[$partie_id]['moves'], $partien[$partie_id]['head']['Result'])) {
				if (!empty($partien[$partie_id]['kommentar'])) continue;
				if (!empty($partien[$partie_id]['pgn'])) continue;
				$values['POST']['kommentar'] = $comment;
			} elseif (!in_array($partien[$partie_id]['moves'], ['*', '0-1', '1-0', '1/2-1/2'])) {
				$values['POST']['pgn'] = $partien[$partie_id]['moves'];
			}
			$ergebnis = mf_tournaments_pgn_result($partien[$partie_id]['moves'], $partien[$partie_id]['head']['Result']);
			if ($ergebnis) {
				if ($partie['vertauschte_farben']) {
					$schwarz = $ergebnis['schwarz'];
					$ergebnis['schwarz'] = $ergebnis['weiss'];
					$ergebnis['weiss'] = $schwarz;
				}
				if (!$partie['block_ergebnis_aus_pgn']) {
					$values['POST']['weiss_ergebnis'] = $ergebnis['weiss'];
					$values['POST']['schwarz_ergebnis'] = $ergebnis['schwarz'];
					if ($event['event_category'] === 'mannschaft') {
						switch ($partie['heim_spieler_farbe']) {
						case 'schwarz':
							$values['POST']['heim_wertung'] = $ergebnis['schwarz'];
							$values['POST']['auswaerts_wertung'] = $ergebnis['weiss'];
							break;
						case 'weiß':
							$values['POST']['heim_wertung'] = $ergebnis['weiss'];
							$values['POST']['auswaerts_wertung'] = $ergebnis['schwarz'];
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
				$values['POST']['eco'] = isset($partien[$partie_id]['head']['ECO']) ? $partien[$partie_id]['head']['ECO'] : '';
				if ($values['POST']['eco'] === '*') $values['POST']['eco'] = '';
			}
			$values['POST']['halbzuege'] = $moves['move'];
			$values['POST']['vertauschte_farben'] = isset($partien[$partie_id]['vertauschte_farben']) ? 'ja' : 'nein';
			if (!empty($moves['BlackClock'])) {
				$values['POST']['schwarz_zeit'] = $moves['BlackClock'];
			}
			if (!empty($moves['WhiteClock'])) {
				$values['POST']['weiss_zeit'] = $moves['WhiteClock'];
			}
			$ops = zzform_multi('partien', $values);
			if (!$ops['id']) {
				wrap_log(sprintf(
					'PGN-Import: Partie %s-%s, %s %d, Runde %d konnte nicht importiert werden. Fehler: ',
					$partie['White'], $partie['Black'], $event['event'], $event['year'], $partie['runde_no']
				).implode(', ', $ops['error']));
				$event['db_errors']++;
			} elseif ($ops['result'] === 'successful_update') {
				$event['updates']++;
			} elseif ($ops['result'] === 'no_update') {
				$event['no_updates']++;
			}
		} else {
			// - Falls nicht, PGN in Fehlerlog oder Fehler-PGN-Datei
			if (!$robot_zugriff) {
				wrap_log(sprintf(
					'PGN-Import: Partie %s-%s, %s %d, Runde %d nicht gefunden.',
					$partie['White'], $partie['Black'], $event['event'], $event['year'], $partie['runde_no']
				));
			}
			$event['not_found']++;
			/*
			// @todo Sollte eine Partie gelöscht werden, falls vorhanden?
			$values = [];
			$values['action'] = 'update';
			$values['POST']['partie_id'] = $partie_id;
			$values['POST']['pgn'] = '';
			$values['POST']['eco'] = '';
			$ops = zzform_multi('partien', $values);
			*/
		}
	}

	// - Fehlende Partien ohne PGN in Fehlerlog
	foreach ($games as $index => $game) {
		$head = '';
		foreach ($game['head'] as $index => $value) $head .= sprintf('[%s "%s"]', $index, $value)."\n";
		if (!$robot_zugriff AND count($games) < 100) {
			// Fehlerlog nur bei einzelnen Partien, sonst zuviele
			// z. B. bei Upload einer einzelnen DEM-PGN für alle Meisterschaften
			wrap_log('PGN-Import: Für diese PGN konnte keine Partie gefunden werden: '.$head);
		}
		$event['wrong_pgn']++;
	}

	if ($runde_no) $event['runde_no'] = $runde_no;
	if ($tisch_no) $event['tisch_no'] = $tisch_no;
	if ($brett_no) $event['brett_no'] = $brett_no;
	$zz_conf['error_handling'] = $old_error_handling;
	$page['text'] = wrap_template('games-update', $event);
	mf_tournaments_job_finish('partien', 1, $event['event_id'], $runde_url);
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
 * @param array $partie
 *		string 'White', string 'Black'
 * @param array $event
 * @return array $pgn
 */
function cms_partienupdate_pgnfind(&$games, $partie, $event) {
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
	if (!empty($event['pgn_match_round_table_board']) AND !empty($partie['Round_With_Board'])) {
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
	static $names;
	if (empty($names)) $names = [];
	if (in_array($name, array_keys($names))) return $names[$name];
	$names[$name] = strtolower(wrap_filename(str_replace('-', '', $name), ''));
	return $names[$name];
}

/**
 * Trigger-Funktion, die aktuell laufende Termine mit Liveübetragung sucht
 * und hier automatisch die PGNs importiert
 *
 * @param void
 * @return function
 */
function cms_partienupdate_trigger() {
	$sql = 'SELECT DISTINCT tournaments.event_id, runden.runde_no,
			IF(runden.date_begin >= CURDATE() AND runden.time_begin > CURTIME(), NULL, 1) AS laufend
		FROM tournaments
		JOIN events USING (event_id)
		LEFT JOIN events runden
			ON events.event_id = runden.main_event_id
			AND runden.event_category_id = %d
		LEFT JOIN partien
			ON events.event_id = partien.event_id
			AND runden.runde_no = partien.runde_no
		WHERE NOT ISNULL(livebretter)
		AND events.date_begin <= CURDATE() AND events.date_end >= CURDATE()
		AND NOT ISNULL(partien.partie_id)
		ORDER BY tournaments.event_id, runden.runde_no
	';
	$sql = sprintf($sql, wrap_category_id('zeitplan/runde'));
	// in SQL-Abfrage werden alle Runden ausgegeben, wrap_db_fetch() speichert
	// aber nach event_id und durch die Sortierung wird nur die letzte Runde
	// gespeichert
	$tournaments = wrap_db_fetch($sql, 'event_id');

	foreach ($tournaments as $event_id => $turnier) {
		if (!$turnier['laufend']) continue;
		// @todo maybe disable next two lines to reduce server load
		mf_tournaments_job_create('partien', $turnier['event_id'], $turnier['runde_no']);
		mf_tournaments_job_create('partien', $turnier['event_id'], $turnier['runde_no'].'-live', -5);
		mf_tournaments_job_trigger();
	}
	$page['text'] = 'Update in progress';
	return $page;
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
