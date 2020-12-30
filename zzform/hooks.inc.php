<?php 

/**
 * Zugzwang Project
 * functions that are called before or after changing a record
 *
 * http://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2020 Gustaf Mossakowski
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
function my_tabellenstand_aktualisieren($ops) {
	$update = false;
	$turnier_ids = [];
	$event_ids = [];
	$runde_nos = [];
	$prioritaet = 0;
	foreach ($ops['return'] as $index => $table) {
		if ($table['action'] === 'nothing') continue;
		switch ($table['table']) {
		case 'turniere':
			// nur bei Aktualisierung bretter_min
			// Achtung, Einzelturniere: keine Bretterzahl
			if ($table['action'] !== 'update') break;
			if (!array_key_exists('bretter_min', $ops['record_diff'][$index])) break;
			if ($ops['record_diff'][$index]['bretter_min'] === 'same') break;
			$update = true;
			$turnier_ids[] = $ops['record_new'][$index]['turnier_id'];
			break;
		case 'turniere_wertungen':
			// Bei Aktualisierung, Einfügen und Löschen immer, auch bei
			// Änderung der Anzeige!
			foreach ($ops['record_diff'][$index] as $field => $action) {
				if ($action === 'same') continue;
				$update = true;
			}
			if ($ops['record_new'][$index] AND isset($ops['record_new'][$index]['turnier_id'])) {
				if ($ops['record_new'][$index]['turnier_id']) {
					$turnier_ids[] = $ops['record_new'][$index]['turnier_id'];
				}
			} elseif (empty($ops['record_new'][$index])) {
				if ($ops['record_old'][$index]['turnier_id']) {
					$turnier_ids[] = $ops['record_old'][$index]['turnier_id'];
				}
			} else {
				// Hauptdatensatz Turnier, bei Hinzufügen von einer Wertung
				if ($ops['record_old'][$index]['turnier_id']) {
					$turnier_ids[] = $ops['record_old'][0]['turnier_id'];
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
			$prioritaet = 5;
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
	if ($update) {
		$where = [];
		if ($turnier_ids) {
			$turnier_ids = array_unique($turnier_ids);
			$where[] = sprintf('turnier_id IN (%s)', implode(',', $turnier_ids));
		}
		if ($event_ids) {
			$event_ids = array_unique($event_ids);
			$where[] = sprintf('event_id IN (%s)', implode(',', $event_ids));
		}
		if (!$where) return [];
		$event_ids = array_unique($event_ids);
		$sql = 'SELECT event_id, events.identifier
			FROM turniere
			JOIN events USING (event_id)
			WHERE %s';
		$sql = sprintf($sql, implode(' OR ', $where));
		$events = wrap_db_fetch($sql, '_dummy_', 'key/value');
		$runde_nos = array_unique($runde_nos);
		foreach ($events as $event_id => $event_identifier) {
			if ($runde_nos) {
				foreach ($runde_nos as $runde) {
					my_job_create('tabelle', $event_id, $runde, $prioritaet);
				}
			} else {
				// Start in der 1. Runde
				my_job_create('tabelle', $event_id, 1, $prioritaet);
			}
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
function my_partienupdate_nach_upload($ops) {
	// Wurde eine Datei hochgeladen?
	if (empty($ops['file_upload'])) return false;

	// Von welchem Formular aus wurde Datei hochgeladen?
	$event_id = '';
	foreach ($ops['return'] as $index => $table) {
		if ($table['table'] === 'events') {
			if (!$ops['record_new'][$index]['runde_no']) return false;
			$event_id = $ops['record_new'][$index]['main_event_id'];
			$runde_no = $ops['record_new'][$index]['runde_no'];
		} elseif ($table['table'] === 'turniere') {
			$event_id = $ops['record_new'][$index]['event_id'];
			$runde_no = '';
		}
	}
	
	// PGN-Import in Datenbank anstoßen
	if ($event_id) {
		my_job_create('partien', $event_id, $runde_no);
	}
}

/**
 * Partien-Update: ggf. leer gelassene Teamwertung setzen
 * 
 * @param array $ops
 * @return array
 */
function my_update_teamwertung($ops) {
	$changes = [];
	foreach ($ops['planned'] as $index => $table) {
		if ($table['table'] !== 'partien') continue;
		if ($table['action'] !== 'insert') return [];
		// Mannschaftsturnier?
		$rec_new = $ops['record_new'][$index];
		if (empty($rec_new['paarung_id'])) return [];

		if ($rec_new['schwarz_ergebnis'] === '' AND $rec_new['weiss_ergebnis'] !== '') {
			$rec_new['schwarz_ergebnis'] = my_other_result($rec_new['weiss_ergebnis']);
		} elseif ($rec_new['weiss_ergebnis'] === '' AND $rec_new['schwarz_ergebnis'] !== '') {
			$rec_new['weiss_ergebnis'] = my_other_result($rec_new['schwarz_ergebnis']);
		}

		// Farbe leer?
		// 1. Brett = schwarz für Heim @todo in Turniereinstellungen einstellbar
		if (!$rec_new['heim_spieler_farbe']) {
			if ($rec_new['brett_no'] & 1) {
				$changes['record_replace'][$index]['heim_spieler_farbe'] = 'schwarz';
				$rec_new['heim_spieler_farbe'] = 'schwarz';
			}  else {
				$changes['record_replace'][$index]['heim_spieler_farbe'] = 'weiß';
				$rec_new['heim_spieler_farbe'] = 'weiß';
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
function my_ergebnis_gemeldet($ops) {
	$change = [];
	foreach ($ops['planned'] as $index => $table) {
		if ($table['table'] !== 'partien') continue;
		if ($ops['record_new'][$index]['schwarz_ergebnis'] !== '' AND $ops['record_new'][$index]['weiss_ergebnis'] === '') {
			$ops['record_diff'][$index]['schwarz_ergebnis'] = 'insert';
			$change['record_replace'][$index]['weiss_ergebnis'] = my_other_result($ops['record_new'][$index]['schwarz_ergebnis']);
		} elseif ($ops['record_new'][$index]['weiss_ergebnis'] !== '' AND $ops['record_new'][$index]['schwarz_ergebnis'] === '') {
			$ops['record_diff'][$index]['schwarz_ergebnis'] = 'insert';
			$change['record_replace'][$index]['schwarz_ergebnis'] = my_other_result($ops['record_new'][$index]['weiss_ergebnis']);
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

function my_other_result($result) {
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

function my_swtimport($ops) {
	if (empty($ops['record_new'][0]['event_id'])) return [];
	
	my_job_create('swt', $ops['record_new'][0]['event_id']);
	return [];
}
