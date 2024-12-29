<?php 

/**
 * tournaments module
 * functions that are called before or after changing a record
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


//
// ---- Tabellenstand ----
//

/**
 * Tabellenstand neu berechnen, einzelne Aufrufe dauern ca. 4 sec, besser
 * das im Hintergrund machen
 *
 * @param array $ops
 * @return void
 */
function mf_tournaments_standings_update($ops) {
	$update = false;
	$tournament_ids = [];
	$event_ids = [];
	$runde_nos = [];
	$priority = 0;
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		switch ($table['table']) {
		case 'tournaments':
			// nur bei Aktualisierung bretter_min
			// Achtung, Einzelturniere: keine Bretterzahl
			if ($table['action'] !== 'update') break;
			if (!array_key_exists('bretter_min', $ops['record_diff'][$index])) break;
			if ($ops['record_diff'][$index]['bretter_min'] === 'same') break;
			$update = true;
			$tournament_ids[] = $ops['record_new'][$index]['tournament_id'];
			break;
		case 'turniere_wertungen':
			// Bei Aktualisierung, Einfügen und Löschen immer, auch bei
			// Änderung der Anzeige!
			foreach ($ops['record_diff'][$index] as $field => $action) {
				if ($action === 'same') continue;
				$update = true;
			}
			if ($ops['record_new'][$index] AND isset($ops['record_new'][$index]['tournament_id'])) {
				if ($ops['record_new'][$index]['tournament_id']) {
					$tournament_ids[] = $ops['record_new'][$index]['tournament_id'];
				}
			} elseif (empty($ops['record_new'][$index])) {
				if ($ops['record_old'][$index]['tournament_id']) {
					$tournament_ids[] = $ops['record_old'][$index]['tournament_id'];
				}
			} else {
				// Hauptdatensatz Turnier, bei Hinzufügen von einer Wertung
				if ($ops['record_old'][$index]['tournament_id']) {
					$tournament_ids[] = $ops['record_old'][0]['tournament_id'];
				}
			}
			break;
		case 'teams':
			if ($table['action'] !== 'update') break;
			if ($ops['record_diff'][$index]['spielfrei'] !== 'same') $update = true;
			$event_ids[] = $ops['record_new'][$index]['event_id'];
			break;
		case 'paarungen':
			// Falls komplette Paarungen gelöscht werden, werden auch Partien gelöscht
			if ($table['action'] !== 'delete') break;
			$runde_nos[] = $ops['record_old'][$index]['runde_no'];
			$event_ids[] = $ops['record_old'][$index]['event_id'];
			$update = true;
			break;
		case 'partien':
			$priority = 5;
			foreach ($ops['record_diff'][$index] as $field => $action) {
				if ($action === 'same') continue;
				if (!in_array($field, [
					'weiss_ergebnis', 'schwarz_ergebnis', 'weiss_person_id',
					'schwarz_person_id', 'heim_spieler_farbe', 'heim_wertung'.
					'auswaerts_wertung', 'partiestatus_category_id', 'event_id'
				])) continue;
				$update = true;
			}
			if ($update) {
				if (!empty($ops['record_old'][$index])) {
					$runde_nos[] = $ops['record_old'][$index]['runde_no'];
					$event_ids[] = $ops['record_old'][$index]['event_id'];
				}
				if (!empty($ops['record_new'][$index])) {
					$runde_nos[] = $ops['record_new'][$index]['runde_no'];
					$event_ids[] = $ops['record_new'][$index]['event_id'];
				}
			}
			break;
		}
	}
	if (!$update) return [];
	$where = [];
	if ($tournament_ids) {
		$tournament_ids = array_unique($tournament_ids);
		$where[] = sprintf('tournament_id IN (%s)', implode(',', $tournament_ids));
	}
	if ($event_ids) {
		$event_ids = array_unique($event_ids);
		$where[] = sprintf('event_id IN (%s)', implode(',', $event_ids));
	}
	if (!$where) return [];
	$event_ids = array_unique($event_ids);
	$sql = 'SELECT event_id, events.identifier
		FROM tournaments
		JOIN events USING (event_id)
		WHERE %s';
	$sql = sprintf($sql, implode(' OR ', $where));
	$events = wrap_db_fetch($sql, '_dummy_', 'key/value');
	$runde_nos = array_unique($runde_nos);
	foreach ($events as $event_id => $identifier) {
		if ($runde_nos) {
			foreach ($runde_nos as $runde) {
				$url = wrap_path('tournaments_job_standings', $identifier.'/'.$runde, false);
				wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/tabelle'), 'priority' => $priority]);
			}
		} else {
			// Start in der 1. Runde
			$url = wrap_path('tournaments_job_standings', $identifier.'/1', false);
			wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/tabelle'), 'priority' => $priority]);
		}
	}
	return [];
}


//
// ---- Partien ----
//

/**
 * Löst Trigger nach Upload einer PGN-Datei aus
 *
 * @param array $ops
 */
function mf_tournaments_games_update($ops) {
	// Wurde eine Datei hochgeladen?
	if (empty($ops['file_upload'])) return false;

	// Von welchem Formular aus wurde Datei hochgeladen?
	$event_id = '';
	foreach ($ops['return'] as $index => $table) {
		switch ($table['table']) {
		case 'events':
			if (!$ops['record_new'][$index]['runde_no']) return false;
			$event_id = $ops['record_new'][$index]['main_event_id'];
			$runde_no = $ops['record_new'][$index]['runde_no'];
			break 2;
		case 'tournaments':
			$event_id = $ops['record_new'][$index]['event_id'];
			$runde_no = '';
			break 2;
		case 'partien':
			$event_id = $ops['record_new'][$index]['event_id'];
			if (!empty($ops['record_old'][$index]['tisch_no']))
				$runde_no = sprintf('%s/%s.%s'
					, $ops['record_new'][$index]['runde_no']
					, $ops['record_old'][$index]['tisch_no']
					, $ops['record_new'][$index]['brett_no']
				);
			else
				$runde_no = sprintf('%s/%s'
					, $ops['record_new'][$index]['runde_no']
					, $ops['record_new'][$index]['brett_no']
				);
			break 2;
		}
	}
	
	if (!$event_id) return false;
	$sql = 'SELECT identifier FROM events WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$identifier = wrap_db_fetch($sql, '', 'single value');
	$url = wrap_path('tournaments_job_games', $identifier.'/'.$runde_no, false);
	wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/partien')]);
}

/**
 * Partien-Update: ggf. leer gelassene Teamwertung setzen
 * 
 * @param array $ops
 * @return array
 */
function mf_tournaments_team_points($ops) {
	static $settings = [];
	if (!$settings) {
		// @todo solve via tournament settings object
		$sql = 'SELECT urkunde_parameter AS parameter FROM tournaments WHERE event_id = %d';
		$sql = sprintf($sql, $ops['record_new'][0]['event_id']);
		$parameter = wrap_db_fetch($sql, '', 'single value');
		if ($parameter) parse_str($parameter, $settings);
	}
	
	// set colour for first board
	$colour_uneven_board = 'schwarz';
	$colour_even_board = 'weiß'; 
	if (!empty($settings['home_team_first_board'])) {
		if ($settings['home_team_first_board'] === 'white') {
			$colour_uneven_board = 'weiß';
			$colour_even_board = 'schwarz'; 
		}
	}

	$changes = [];
	foreach ($ops['planned'] as $index => $table) {
		if ($table['table'] !== 'partien') continue;
		if ($table['action'] !== 'insert') return [];
		// Mannschaftsturnier?
		$rec_new = $ops['record_new'][$index];
		if (empty($rec_new['paarung_id'])) return [];

		if ($rec_new['schwarz_ergebnis'] === '' AND $rec_new['weiss_ergebnis'] !== '') {
			$rec_new['schwarz_ergebnis'] = mf_tournaments_other_result($rec_new['weiss_ergebnis']);
		} elseif ($rec_new['weiss_ergebnis'] === '' AND $rec_new['schwarz_ergebnis'] !== '') {
			$rec_new['weiss_ergebnis'] = mf_tournaments_other_result($rec_new['schwarz_ergebnis']);
		}

		// Farbe leer?
		if (!$rec_new['heim_spieler_farbe']) {
			if ($rec_new['brett_no'] & 1) {
				$changes['record_replace'][$index]['heim_spieler_farbe'] = $colour_uneven_board;
				$rec_new['heim_spieler_farbe'] = $colour_uneven_board;
			}  else {
				$changes['record_replace'][$index]['heim_spieler_farbe'] = $colour_even_board;
				$rec_new['heim_spieler_farbe'] = $colour_even_board;
			}
		}
		// Heim-Wertung leer?
		if (!$rec_new['heim_wertung']) {
			if ($rec_new['heim_spieler_farbe'] === 'schwarz') {
				$changes['record_replace'][$index]['heim_wertung'] = $rec_new['schwarz_ergebnis'];
			} else {
				$changes['record_replace'][$index]['heim_wertung'] = $rec_new['weiss_ergebnis'];
			}
		}
		// Auswärts-Wertung leer?
		if (!$rec_new['auswaerts_wertung']) {
			if ($rec_new['heim_spieler_farbe'] === 'schwarz') {
				$changes['record_replace'][$index]['auswaerts_wertung'] = $rec_new['weiss_ergebnis'];
			} else {
				$changes['record_replace'][$index]['auswaerts_wertung'] = $rec_new['schwarz_ergebnis'];
			}
		}
	}
	return $changes;
}

/**
 * Schreibt nach Ergebnismeldung timestamp in Datenbank, dass Ergebnis gerade
 * gemeldet wurde
 *
 * @param array $ops
 * @param return $change
 */
function mf_tournaments_result_reported($ops) {
	$change = [];
	foreach ($ops['planned'] as $index => $table) {
		if ($table['table'] !== 'partien') continue;
		if ($ops['record_new'][$index]['schwarz_ergebnis'] !== '' AND $ops['record_new'][$index]['weiss_ergebnis'] === '') {
			$ops['record_diff'][$index]['schwarz_ergebnis'] = 'insert';
			$change['record_replace'][$index]['weiss_ergebnis'] = mf_tournaments_other_result($ops['record_new'][$index]['schwarz_ergebnis']);
		} elseif ($ops['record_new'][$index]['weiss_ergebnis'] !== '' AND $ops['record_new'][$index]['schwarz_ergebnis'] === '') {
			$ops['record_diff'][$index]['schwarz_ergebnis'] = 'insert';
			$change['record_replace'][$index]['schwarz_ergebnis'] = mf_tournaments_other_result($ops['record_new'][$index]['weiss_ergebnis']);
		}
		switch ($ops['record_diff'][$index]['schwarz_ergebnis']) {
		case 'insert':
			$change['record_replace'][$index]['ergebnis_gemeldet_um'] = date('Y-m-d H:i:s');
			if (empty($ops['record_new'][$index]['partiestatus_category_id'])) {
				$change['record_replace'][$index]['partiestatus_category_id'] = wrap_category_id('partiestatus/normal');
			}
			break;
		case 'diff':
			if ($ops['record_new'][$index]['schwarz_ergebnis'] !== '') {
				$change['record_replace'][$index]['ergebnis_gemeldet_um'] = date('Y-m-d H:i:s');
				if (empty($ops['record_new'][$index]['partiestatus_category_id'])
					OR $ops['record_new'][$index]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/laufend').'') {
					$change['record_replace'][$index]['partiestatus_category_id'] = wrap_category_id('partiestatus/normal');
				}
			} else {
				$change['record_replace'][$index]['ergebnis_gemeldet_um'] = '';
				if (empty($ops['record_new'][$index]['partiestatus_category_id']) 
					OR $ops['record_new'][$index]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/normal').'') {
					$change['record_replace'][$index]['partiestatus_category_id'] = wrap_category_id('partiestatus/laufend');
				}
			}
			break;
		default:
			// keine Änderung, uninteressant
			break;
		}
	}
	return $change;
}

function mf_tournaments_other_result($result) {
	switch ($result) {
		case '1': case '1.0': return 0;
		case '0.5': case '.5': return 0.5;
		case '0': case '0.0': return 1;
	}
	return '';
}

//
// ---- Turnierdaten ----
//

/**
 * Vereinfachen der Rundeneingabe: automatische Ergänzung von . Runde als Termin
 *
 * @param array $ops
 * @return array
 */
function mf_tournaments_round_event($ops) {
	if (!empty($ops['record_new'][0]['event'])) return [];
	if (empty($ops['record_new'][0]['runde_no'])) return [];
	$change['record_replace'][0]['event'] = $ops['record_new'][0]['runde_no'].'. Runde';
	return $change;
}

/**
 * Falls Anmerkung zu Turnier erstellt, die offen ist, per Mail versenden
 *
 * @param array $ops
 * @return void
 */
function mf_tournaments_remarks_mail($ops) {
	foreach ($ops['return'] as $index => $table) {
		if ($table['table'] !== 'anmerkungen') continue;
		$record = $ops['record_new'][$index];
		if (empty($record['benachrichtigung'])) continue;
		if ($record['benachrichtigung'] !== 'ja') continue;

		$sql = 'SELECT event
				, places.contact AS place_contact
				, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
				, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
				, events.identifier AS event_identifier
				, team, team_no
			FROM teams
			LEFT JOIN events USING (event_id)
			LEFT JOIN events_contacts events_places
				ON events.event_id = events_places.event_id
				AND events_places.role_category_id = /*_ID categories roles/location _*/
				AND events_places.sequence = 1
			LEFT JOIN contacts places
				ON events_places.contact_id = places.contact_id
			WHERE team_id = %d
		';
		$sql = sprintf($sql, $record['team_id']); // @todo participation_id
		$record = array_merge($record, wrap_db_fetch($sql));
		$record['event_link'] = wrap_path('events_internal_event', $record['event_identifier']);

		$sql = 'SELECT contact, identification AS e_mail
			FROM persons
			LEFT JOIN contacts USING (contact_id)
			LEFT JOIN contactdetails USING (contact_id)
			WHERE person_id = %d
			AND provider_category_id = /*_ID categories provider/e-mail _*/
			LIMIT 1';
		$sql = sprintf($sql, $record['autor_person_id']);
		$record = array_merge($record, wrap_db_fetch($sql));

		$msg = wrap_template('team-remarks-mail', $record);
		$mail['message'] = $msg;
		$mail['headers']['From']['name'] = $record['contact'];
		$mail['headers']['From']['e_mail'] = $record['e_mail'];
		$mail['to']['name'] = wrap_setting('project');
		// @todo read from tournament settings, not general settings
		$mail['to']['e_mail'] = wrap_setting('tournaments_remarks_mail_to');
		$success = wrap_mail($mail);
	}
	return;
}


//
// ---- Team ----
//

/**
 * Bei Meldungen wird oft das Jahr weggelassen, daher hier ggf. ergänzen
 *
 * @param array $ops
 * @return array $change
 * @todo check if end year shouldn't be -1 or +1 if over new year's eve
 */
function mf_tournaments_complete_date($ops) {
	$change = [];
	foreach ($ops['not_validated'] as $index => $table) {
		if ($table['table'] !== 'teams') continue;
		$check = false;
		if (!zz_check_date($ops['record_new'][$index]['datum_anreise'])) $check = true;
		if (!zz_check_date($ops['record_new'][$index]['datum_abreise'])) $check = true;
		if (!$check) continue;
		$sql = 'SELECT YEAR(date_begin) as date_begin, YEAR(date_end) as date_end FROM events WHERE event_id = %d';
		$sql = sprintf($sql, $ops['record_old'][$index]['event_id']);
		$reisedaten = wrap_db_fetch($sql);
		// add year
		if ($ops['record_new'][$index]['datum_anreise'] AND !zz_check_date($ops['record_new'][$index]['datum_anreise'])) {
			$anreise = $ops['record_new'][$index]['datum_anreise'];
			if (substr($anreise, -1) !== '.') $anreise .= '.';
			$anreise .= $reisedaten['date_begin'];
			if (zz_check_date($anreise))
				$change['record_replace'][$index]['datum_anreise'] = $anreise;
		}
		if ($ops['record_new'][$index]['datum_abreise'] AND !zz_check_date($ops['record_new'][$index]['datum_abreise'])) {
			$abreise = $ops['record_new'][$index]['datum_abreise'];
			if (substr($abreise, -1) !== '.') $abreise .= '.';
			$abreise .= $reisedaten['date_begin'];
			if (zz_check_date($abreise))
				$change['record_replace'][$index]['datum_abreise'] = $abreise;
		}
	}
	return $change;
}
