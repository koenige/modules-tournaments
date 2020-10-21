<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Copyright (c) 2014 Erik Kothe <erik@deutsche-schachjugend.de>
// Ergebnisse: Tabellenstand aus Views in Datenbank schreiben


/**
 * Aktualisiert den Tabellenstand im Hintergrund
 * 
 * Wenn keine Runde angegeben, aktualisiere alle Runden
 * Wenn eine Runde angegeben ist, aktualisere aktuelle Runde und darauffolgende
 *
 * @param array $vars
 *		[0]: Jahr
 *		[1]: event identifier
 *		[2]: (optional) Runde, falls nicht angegeben = 1. Runde
 * @return void
 */
function mod_tournaments_make_standings($vars) {
	global $zz_setting;
	$zz_setting['cache'] = false;

	ignore_user_abort(1);
	ini_set('max_execution_time', 60);

	if (count($vars) === 3) {
		$first = reset($vars);
		if ($first === 'uebersicht') {
			array_shift($vars);
			return cms_tabellenstandupdate_uebersicht($vars);
		} else {
			return cms_tabellenstandupdate_runde($vars);
		}
	} elseif (count($vars) === 2) {
		$vars[] = 1; // 1. Runde
		return cms_tabellenstandupdate_runde($vars);
	}
	return false;
}

/**
 * Übersicht der Runden eines Turniers, erlaubt Aktualisierung ab einzelnen
 * Runden
 *
 * @param array $vars
 *		[0]: Jahr
 *		[1]: event identifier
 * @return array $page
 */
function cms_tabellenstandupdate_uebersicht($vars) {
	global $zz_setting;
	if (count($vars) !== 2) return false;

	$sql = 'SELECT event_id, event
			, YEAR(date_begin) AS jahr
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS dauer
		FROM events
		WHERE identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	$sql = 'SELECT events.event_id, events.runde_no
			, (SELECT COUNT(partie_id) FROM partien
				WHERE partien.event_id = events.main_event_id
				AND partien.runde_no = events.runde_no) AS partien
		FROM events
		WHERE main_event_id = %d
		AND NOT ISNULL(events.runde_no)
		HAVING partien > 0';
	$sql = sprintf($sql, $event['event_id']);
	$event['runden'] = wrap_db_fetch($sql, 'event_id');
	
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = sprintf('<a href="../../">%d</a>', $event['jahr']);
	$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $event['event']);
	$page['breadcrumbs'][] = 'Tabellenstandupdate';
	$page['title'] = 'Tabellenstandupdates '.$event['event'].' '.$event['jahr'];
	$page['text'] = wrap_template('standings-make', $event);
	
	if (!empty($_POST) AND is_array($_POST)) {
		$runde = key($_POST);
		if (substr($runde, 0, 6) === 'runde_') {
			$runde = substr($runde, 6);
			my_job_create('tabelle', $event['event_id'], $runde);
			wrap_http_status_header(303);
			header('Location: '.$zz_setting['host_base'].$_SERVER['REQUEST_URI']);
			exit;
		}
	}
	return $page;
}

/**
 * Aktualisierung des Tabellenstands pro Runde
 *
 * @param array $vars
 * @return bool
 */
function cms_tabellenstandupdate_runde($vars) {
	global $zz_conf;
	$time = microtime(true);

	// Zugriffsberechtigt?
	if (!brick_access_rights(['Webmaster'])) wrap_quit(403);

	$runde = 1; // beginne bei Runde 1 oder bei angegebener Runde
	if (count($vars) === 3 AND is_numeric($vars[2])) $runde = $vars[2];
	elseif (count($vars) !== 2) return false;

	$sql = 'SELECT event_id, events.identifier
			, runden, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform, bretter_min
			, turnier_id
			, (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id) AS runden_gespielt
		FROM events
		LEFT JOIN turniere USING (event_id)
		LEFT JOIN categories turnierformen
			ON turniere.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	if ($runde > $event['runden_gespielt']) {
		my_job_finish('tabelle', 0, $event['event_id'], $runde);
		wrap_quit(404);
	}
	if ($runde > $event['runden']) {
		my_job_finish('tabelle', 0, $event['event_id'], $runde);
		wrap_error(sprintf('Tabellenstand-Update: Runde %d/%d nicht möglich (Termin %d/%s)',
			$runde, $event['runden'], $vars[0], $vars[1]), E_USER_ERROR);
	}

	require_once $zz_conf['dir'].'/zzform.php';
	$type = implode('/', $vars);
	$zz_conf['user'] = 'Tabellenstand '.$type;
	if ($event['turnierform'] === 'e') {
		$tabelle = cms_tabellenstand_calculate_einzel($event, $runde);
		if (!$tabelle) {
			my_job_finish('tabelle', 0, $event['event_id'], $runde);
			return cms_tabellenstandupdate_return(false, $time, $type);
		}
		$success = cms_tabellenstand_write_einzel($event['event_id'], $runde, $tabelle);
		if (!$success) {
			my_job_finish('tabelle', 0, $event['event_id'], $runde);
			return cms_tabellenstandupdate_return(false, $time, $type);
		}
	} else {
		$event['runde_no'] = $runde;
		cms_tabellenstand_write_mannschaft($event);
	}
	my_job_finish('tabelle', 1, $event['event_id'], $runde);
	if ($runde < $event['runden_gespielt']) {
		my_job_create('tabelle', $event['event_id'], $runde + 1);
	}
	
	// Aktuelle runde_no von Tabellenstand speichern
	// aus Performancegründen
	$sql = 'SELECT MAX(runde_no) FROM tabellenstaende WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$max_runde_no = wrap_db_fetch($sql, '', 'single value');
	$values = [];
	$values['action'] = 'update';
	$values['POST']['turnier_id'] = $event['turnier_id'];
	$values['POST']['tabellenstand_runde_no'] = $max_runde_no;
	$ops = zzform_multi('turniere', $values);

	return cms_tabellenstandupdate_return(true, $time, $type);
}

function cms_tabellenstandupdate_return($bool, $time, $type) {
	$time = microtime(true) - $time;
	if ($time < 1) return $bool; // do not log if it's fast enough
	wrap_error(sprintf('Tabellenstand %s in %s sec erstellt.', $type, $time), E_USER_NOTICE);
	return $bool;
}

/**
 * Berechne den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param array $event
 * @param int $runde_no
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_calculate_einzel($event, $runde_no) {
	// gibt es überhaupt Partien in der Runde, die schon gespielt wurden?
	$sql = 'SELECT COUNT(partie_id)
		FROM partien
		WHERE event_id = %d AND runde_no = %d
		AND NOT ISNULL(weiss_ergebnis)';
	$sql = sprintf($sql, $event['event_id'], $runde_no);
	$anzahl_partien = wrap_db_fetch($sql, '', 'single value');
	if (!$anzahl_partien) return false;

	// Termin-ID setzen
	$sql = 'SELECT @event_id:=%d';
	$sql = sprintf($sql, $event['event_id']);
	wrap_db_query($sql);

	// Spieler auslesen
	$tabelleeinzeln = new cms_tabellenstand_einzel();
	$tabelleeinzeln->setAktRunde($runde_no);
	$tabelle = $tabelleeinzeln->getSpieler($event['event_id']);

	// Turnierwertungen
	$turnierwertungen = cms_tabellenstandupdate_wertungen($event['event_id']);
	if (in_array(wrap_category_id('turnierwertungen/3p'), array_keys($turnierwertungen))) {
		$tabelleeinzeln->setSieg(3);
		$tabelleeinzeln->setRemis(1);
	}
	foreach ($turnierwertungen as $id => $turnierwertung) {
		if (!function_exists($function = 'my_wertung_einzel_'.$turnierwertung['path'])) continue;
		$wertungen[$id] = $function($event['event_id'], $runde_no, $tabelle, $tabelleeinzeln);
	}

	$niedrig_besser = [
		wrap_category_id('turnierwertungen/rg'),
		wrap_category_id('turnierwertungen/p')
	];
	$null_punkte_bei_null = [
		wrap_category_id('turnierwertungen/p')
	];
	$null_komma_null_punkte_bei_null = [
		wrap_category_id('turnierwertungen/pkt')
	];

	if (empty($wertungen)) {
		wrap_error('Keine (möglichen) Wertungen in Turnierstand angegeben!', E_USER_ERROR);
	}
	foreach ($wertungen as $index => $values) {
		if (in_array($index, $null_punkte_bei_null)) {
			// auch wenn es noch keine gespielte Partie gibt: 0 Punkte!
			foreach (array_keys($tabelle) as $person_id) {
				if (isset($values[$person_id])) continue;
				$wertungen[$index][$person_id] = 0;
			}
		}
		if (in_array($index, $null_komma_null_punkte_bei_null)) {
			// auch wenn es noch keine gespielte Partie gibt: 0 Punkte!
			foreach (array_keys($tabelle) as $person_id) {
				if (isset($values[$person_id])) continue;
				$wertungen[$index][$person_id] = "0.0";
			}
		}
		if (in_array($index, $niedrig_besser)) {
			// höherer Wert = schlechter === nicht möglich
			asort($wertungen[$index]);
		} else {
			// höherer Wert = besser
			arsort($wertungen[$index]);
		}
	}

	$tabelle = cms_tabellenstand_wertungen($event, $tabelle, $wertungen, $turnierwertungen);
	return $tabelle;
}

/**
 * Aktualisiere den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle Daten, berechnet aus cms_tabellenstand_calculate_einzel()
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_write_einzel($event_id, $runde_no, $tabelle) {
	global $zz_setting;

	// Bestehenden Tabellenstand aus Datenbank auslesen
	$sql = 'SELECT person_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d
		AND runde_no = %d';
	$sql = sprintf($sql, $event_id, $runde_no);
	$tabellenstaende = wrap_db_fetch($sql, '_dummy_', 'key/value');

	// Werte für Partien gewonnen, unentschieden, verloren auslesen
	$sql = 'SELECT person_id
			, SUM(IF(schwarz_ergebnis = "1.0" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "1.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_g
			, SUM(IF(schwarz_ergebnis = "0.5" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "0.5" AND weiss_person_id = person_id, 1, 0))) AS spiele_u
			, SUM(IF(schwarz_ergebnis = "0.0" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "0.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_v
		FROM teilnahmen
		LEFT JOIN partien
			ON (partien.weiss_person_id = teilnahmen.person_id
			OR partien.schwarz_person_id = teilnahmen.person_id)
			AND partien.event_id = teilnahmen.event_id
		WHERE teilnahmen.event_id = %d
		AND runde_no <= %d
		AND teilnahme_status = "Teilnehmer"
		AND usergroup_id = %d
		GROUP BY person_id
	';
	$sql = sprintf($sql,
		$event_id, $runde_no, wrap_id('usergroups', 'spieler')
	);
	$guv = wrap_db_fetch($sql, 'person_id');
	$punktspalten = ['g', 'u', 'v'];

	// Daten in Datenbank schreiben
	foreach ($tabelle as $index => $stand) {
		$values = [];
		$values['ids'] = ['event_id', 'person_id'];
		// Hauptdatensatz
		// debug
		if (!array_key_exists('person_id', $stand)) {
			wrap_error('TABELLENSTAND '.json_encode($stand));
			continue;
		}
		if (array_key_exists($stand['person_id'], $tabellenstaende)) {
			$values['action'] = 'update';
			$values['POST']['tabellenstand_id'] = $tabellenstaende[$stand['person_id']];
		} else {
			$values['action'] = 'insert';
			$values['POST']['tabellenstand_id'] = '';
		}
		$values['POST']['event_id'] = $event_id;
		$values['POST']['runde_no'] = $runde_no;
		$values['POST']['person_id'] = $stand['person_id'];
		$values['POST']['platz_no'] = $stand['platz_no'];
		foreach ($punktspalten AS $ps) {
			$values['POST']['spiele_'.$ps] = isset($guv[$stand['person_id']]['spiele_'.$ps])
			? $guv[$stand['person_id']]['spiele_'.$ps] : 0;
		}

		// Feinwertungen, Detaildatensätze
		$values['POST']['wertungen'] = $stand['wertungen'];
		if ($values['action'] === 'update') {
			// überflüssige Feinwertungen löschen
			$sql = 'SELECT tsw_id, wertung_category_id FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $tabellenstaende[$stand['person_id']]);
			$feinwertungen = wrap_db_fetch($sql, 'tsw_id');
			foreach ($feinwertungen as $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$values['POST']['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
		}
		$ops = zzform_multi('tabellenstaende', $values);
		if (!$ops['id']) {
			wrap_error('Tabellenstand konnte nicht aktualisiert oder hinzugefügt werden.
			Termin-ID: '.$event_id.', Runde: '.$runde_no.'. Fehler: '.implode(', ', $ops['error']), E_USER_ERROR);
		}
	}
	return true;
}

/**
 * Aktualisiere den Tabellenstand einer Runde eines Mannschaftsturniers
 *
 * @param array $event [event_id, runde_no, bretter_min, identifier]
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_write_mannschaft($event) {
	$sql = 'SELECT @event_id:=%d';
	$sql = sprintf($sql, $event['event_id']);
	wrap_db_query($sql);
	
	$sql = 'SELECT teams.team_id
			, gewonnen AS spiele_g, unentschieden AS spiele_u, verloren AS spiele_v
			, events.event_id, tabellenstaende_guv_view.runde_no
			, (SELECT COUNT(website_id) FROM events_websites WHERE events_websites.event_id = events.event_id) AS veroeffentlicht
		FROM events
		JOIN teams USING (event_id)
		JOIN tabellenstaende_guv_view USING (team_id)
		WHERE events.event_id = %d
		AND tabellenstaende_guv_view.runde_no = %d
		AND spielfrei = "nein"
		AND (meldung = "komplett" OR meldung = "teiloffen")
		HAVING veroeffentlicht > 0
	';
	$sql = sprintf($sql,
		$event['event_id'], $event['runde_no']
	);
	$tabelle = wrap_db_fetch($sql, 'team_id');
	if (!$tabelle) return false;

	$turnierwertungen = cms_tabellenstandupdate_wertungen($event['event_id']);

	// Wertungen aus Datenbank auslesen
	$sql = 'SELECT tabellenstaende_view.wertung_category_id, team_id, wertung
		FROM tabellenstaende_view
		JOIN turniere USING (event_id)
		JOIN turniere_wertungen
			ON turniere_wertungen.turnier_id = turniere.turnier_id
			AND turniere_wertungen.wertung_category_id = tabellenstaende_view.wertung_category_id
		WHERE tabellenstaende_view.runde_no = %d
		AND wertung IS NOT NULL
		AND team_id IN (%s)
		ORDER BY turniere_wertungen.reihenfolge, wertung DESC';
	$sql = sprintf($sql, $event['runde_no'], implode(',', array_keys($tabelle)));
	$wertungen = wrap_db_fetch($sql, ['wertung_category_id', 'team_id', 'wertung'], 'key/value');

	// Weitere Wertungen ergänzen
	foreach ($turnierwertungen as $category_id => $turnierwertung) {
		switch ($category_id) {
		case wrap_category_id('turnierwertungen/bhz.2'):
			$erste_wertung = reset($turnierwertungen);
			if ($erste_wertung['category_id'] === wrap_category_id('turnierwertungen/bp')) {
				$wertungen[$category_id] = my_wertung_team_buchholz_bp(
					$event['event_id'], $event['runde_no']
				);
			}
			break;
		case wrap_category_id('turnierwertungen/dv'):
			// direkter Vergleich erst nach Auswertung der anderen Wertungen
			$wertungen[$category_id] = 1;
			break;
		case wrap_category_id('turnierwertungen/rg'):
			$sql = 'SELECT team_id, setzliste_no
				FROM teams
				WHERE team_id IN (%s)
				ORDER BY setzliste_no';
			$sql = sprintf($sql, implode(',', array_keys($tabelle)));
			$wertungen[$category_id] = wrap_db_fetch($sql, 'team_id', 'key/value');
			break;
		case wrap_category_id('turnierwertungen/sobo'):
			$wertungen[$category_id] = my_wertung_team_sonneborn_berger(
				$event['event_id'], $event['runde_no']
			);
			break;
		}
		if ($turnierwertung['anzeigen'] === 'immer') {
			// Vor der 1. Runde kann es sein, dass Mannschafts- und Brettpunkte
			// für einzelne Teams noch nicht gesetzt sind, da es noch keine Ergebnisse
			// gibt, dann auf 0 setzen
			// @todo Achtung: das ist nicht immer 100% korrekt, da theoretisch
			// auch Wertungen vorne stehen könnten, bei denen nicht 0 der geringste
			// Wert ist.
			if (!array_key_exists($category_id, $wertungen)) {
				$wertungen[$category_id] = [];
			}
			foreach (array_keys($tabelle) as $team_id) {
				if (!array_key_exists($team_id, $wertungen[$category_id])) {
					$wertungen[$category_id][$team_id] = 0;
				} elseif (empty($wertungen[$category_id][$team_id])) {
					$wertungen[$category_id][$team_id] = 0;
				}
			}
		}
	}

	$tabelle = cms_tabellenstand_wertungen($event, $tabelle, $wertungen, $turnierwertungen);

	$sql = 'SELECT team_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d AND runde_no = %d AND NOT ISNULL(team_id)';
	$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
	$vorhandene_daten = wrap_db_fetch($sql, '_dummy_', 'key/value');

	foreach ($tabelle as $stand) {
		$unwanted_keys = [
			'dwz_schnitt', 'eindeutig'
		];
		foreach ($unwanted_keys as $key) {
			unset($stand[$key]);
		}
		$values = [];
		$values['POST'] = $stand;
		$values['ids'] = ['team_id', 'event_id'];
		if (!empty($vorhandene_daten[$stand['team_id']])) {
			$values['POST']['tabellenstand_id'] = $vorhandene_daten[$stand['team_id']];
			// überflüssige Tabellenstände löschen
			// @todo irgendwann so etwas direkt in zzform mit Funktion lösen
			// (alle anderen Datensätze, die nicht aktualisiert werden, löschen)
			$sql = 'SELECT * FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $vorhandene_daten[$stand['team_id']]);
			$data = wrap_db_fetch($sql, 'tsw_id');
			foreach ($data as $tsw_id => $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$values['POST']['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
			$values['action'] = 'update';
		} else {
			$values['action'] = 'insert';
		}
		$ops = zzform_multi('tabellenstaende', $values);
		if (!$ops['id']) {
			wrap_error('Tabellenstand konnte nicht aktualisiert oder hinzugefügt werden.
			Termin: '.$event['identifier'].', Runde: '.$event['runde_no'].'. Fehler: '.implode(', ', $ops['error']), E_USER_ERROR);
		}
	}
}

/**
 * Hole alle Turnierwertungen für ein Turnier
 * 
 * @param int $event_id
 * @return array
 */
function cms_tabellenstandupdate_wertungen($event_id) {
	$sql = 'SELECT category_id, category, category_short
			, REPLACE(path, "-", "_") AS path, anzeigen
		FROM turniere_wertungen
		JOIN turniere USING (turnier_id)
		JOIN categories
			ON turniere_wertungen.wertung_category_id = categories.category_id
		WHERE event_id = %d
		ORDER BY turniere_wertungen.reihenfolge, categories.sequence';
	$sql = sprintf($sql, $event_id);
	$turnierwertungen = wrap_db_fetch($sql, 'category_id');
	return $turnierwertungen;
}

/**
 * Schreibt Wertungen in Tabellen-Array und ermittelt Platz aus $wertungen
 * anzeige von Wertungen nur, wenn zur Differenzierung nötig
 *
 * @param array $tabelle
 * @param array $wertungen
 * @param array $turnierwertungen
 * @return array $tabelle
 */
function cms_tabellenstand_wertungen($event, $tabelle, $wertungen, $turnierwertungen) {
	$tsw = [];
	foreach (array_keys($turnierwertungen) as $category_id) {
		if (empty($wertungen[$category_id])) continue;
		if (!is_array($wertungen[$category_id])
			AND $category_id.'' === wrap_category_id('turnierwertungen/dv').'') {
			$wertungen[$category_id] = cms_tabellenstand_direkter_vergleich(
				$event, $tabelle, reset($turnierwertungen)
			);
		}
		$vorige_tn_id = [];
		$vorige_wertung = [];
		$increment = [];
		$increment[1] = 1;
		foreach ($wertungen[$category_id] as $tn_id => $wertung) {
			// Aktueller Stand in dieser Wertungsschleife?
			if (!isset($tabelle[$tn_id]['platz_no'])) {
				$tabelle[$tn_id]['platz_no'] = 1;
			}
			if ($turnierwertungen[$category_id]['anzeigen'] === 'immer') {
				$tabelle[$tn_id]['wertungen'][$category_id]['wertung'] = $wertung;
				$tabelle[$tn_id]['wertungen'][$category_id]['wertung_category_id'] = $category_id;
			}
			// Wertung nicht in allgemeines Array schreiben, da das nicht ausgegeben werden soll:
			$tsw[$tn_id][$category_id] = $wertung;
			if (!empty($tabelle[$tn_id]['eindeutig'])) {
				// Platz-Nr. bei eindeutigen Verhältnissen korrekt, keine weitere
				// Bearbeitung nötig
				continue;
			}
			$stand = $tabelle[$tn_id]['platz_no'];
			if (empty($vorige_tn_id[$stand])) {
				$tabelle[$tn_id]['eindeutig'] = true;
				if (!isset($increment[$stand])) $increment[$stand] = 1;
			} elseif (isset($tsw[$vorige_tn_id[$stand]][$category_id])
				AND $wertung === $tsw[$vorige_tn_id[$stand]][$category_id]) {
				$tabelle[$tn_id]['platz_no'] = $tabelle[$vorige_tn_id[$stand]]['platz_no'];
				$tabelle[$vorige_tn_id[$stand]]['eindeutig'] = false;
				$tabelle[$tn_id]['eindeutig'] = false;
				if ($turnierwertungen[$category_id]['anzeigen'] !== 'immer') {
					$tabelle[$tn_id]['wertungen'][$category_id]['wertung'] = $wertung;
					$tabelle[$tn_id]['wertungen'][$category_id]['wertung_category_id'] = $category_id;
					$tabelle[$vorige_tn_id[$stand]]['wertungen'][$category_id]['wertung'] = $vorige_wertung[$stand];
					$tabelle[$vorige_tn_id[$stand]]['wertungen'][$category_id]['wertung_category_id'] = $category_id;
				}
				if (!isset($increment[$stand])) $increment[$stand] = 1;
				else $increment[$stand]++;
			} else {
				if (!isset($increment[$stand])) $increment[$stand] = 1;
				$tabelle[$tn_id]['platz_no'] = $tabelle[$vorige_tn_id[$stand]]['platz_no'] + $increment[$stand];
				$increment[$stand] = 1;
				$tabelle[$tn_id]['eindeutig'] = true;
				if ($turnierwertungen[$category_id]['anzeigen'] !== 'immer') {
					$tabelle[$tn_id]['wertungen'][$category_id]['wertung'] = $wertung;
					$tabelle[$tn_id]['wertungen'][$category_id]['wertung_category_id'] = $category_id;
					$tabelle[$vorige_tn_id[$stand]]['wertungen'][$category_id]['wertung'] = $vorige_wertung[$stand];
					$tabelle[$vorige_tn_id[$stand]]['wertungen'][$category_id]['wertung_category_id'] = $category_id;
				}
			}
			$vorige_tn_id[$stand] = $tn_id;
			$vorige_wertung[$stand] = $wertung;
		}
	}
	$tabelle = cms_tabellenstand_sortieren($tabelle);
	return $tabelle;
}

/**
 * Teams nach ihrem aktuellen Tabellenstand sortieren
 *
 * @param array $tabelle
 * @return array $tabelle (sortiert nach platz_no)
 */
function cms_tabellenstand_sortieren($tabelle) {
	foreach ($tabelle as $tn_id => $values) {
		if (!is_numeric($tn_id)) continue;
		$plaetze[$tn_id] = $values['platz_no'];
	}
	array_multisort($plaetze, SORT_ASC, $tabelle);
	return $tabelle;
}

/**
 * Direkten Vergleich für Teams auswerten
 *
 * Direct Encounter
 * http://www.fide.com/component/handbook/?id=20&view=category
 *
 * If all the tied players have met each other, the sum of points from
 * these encounters is used. The player with the highest score is
 * ranked number 1 and so on. If some but not all have played each
 * other, the player with a score that could not be equalled by any
 * other player (if all such games had been played) is ranked number 1
 * and so on.
 *
 * @param array $event
 * @param array $tabelle
 * @param array $hauptwertung
 * @return $teams int team_id => string Wertung
 */
function cms_tabellenstand_direkter_vergleich($event, $tabelle, $hauptwertung) {
	// Welches ist die Hauptwertung?
	switch ($hauptwertung['category_id']) {
	case wrap_category_id('turnierwertungen/mp'):
		$tw = 'mp';
		break;
	default:
	case wrap_category_id('turnierwertungen/bp'):
		$tw = 'bp';
		break;
	}

	$teams = [];
	$unklar = [];
	
	foreach ($tabelle as $team_id => $wertung) {
		if (!empty($wertung['eindeutig'])) continue;
		$index = isset($wertung['platz_no']) ? $wertung['platz_no'] : 0;
		$unklar[$index][] = $team_id;
	}
	if (!$unklar) return [];

	$unentschieden = $event['bretter_min'] / 2;

	foreach ($unklar as $team_ids) {
		$sql = 'SELECT paarung_id
				, heim_team_id, auswaerts_team_id
				, SUM(heim_wertung) AS heim_bp
				, SUM(auswaerts_wertung) AS auswaerts_bp
				, IF(SUM(heim_wertung) > %1.1f, 2, IF(SUM(heim_wertung) = %1.1f, 1, 0)) AS heim_mp
				, IF(SUM(auswaerts_wertung) > %1.1f, 2, IF(SUM(auswaerts_wertung) = %1.1f, 1, 0)) AS auswaerts_mp
			FROM paarungen
			LEFT JOIN partien USING (paarung_id)
			WHERE heim_team_id IN (%s)
			AND auswaerts_team_id IN (%s)
			AND paarungen.runde_no <= %d
			GROUP BY paarung_id
		';
		$sql = sprintf($sql,
			$unentschieden, $unentschieden, $unentschieden, $unentschieden,
			implode(',', $team_ids), implode(',', $team_ids), $event['runde_no']
		);
		$paarungen = wrap_db_fetch($sql, 'paarung_id');
		$punkte = [];

		// Tatsächliche Punkte
		foreach ($team_ids as $team_id) {
			$punkte[$team_id]['paarungen'] = 0;
			$punkte[$team_id]['punkte'] = 0;
			foreach ($paarungen as $paarung) {
				if ($team_id == $paarung['heim_team_id']) {
					if (!empty($paarung['heim_'.$tw]))
						$punkte[$team_id]['punkte'] += $paarung['heim_'.$tw];
					$punkte[$team_id]['paarungen']++;
				} elseif ($team_id == $paarung['auswaerts_team_id']) {
					if (!empty($paarung['auswaerts_'.$tw]))
						$punkte[$team_id]['punkte'] += $paarung['auswaerts_'.$tw];
					$punkte[$team_id]['paarungen']++;
				}
			}
		}

		// Maximal mögliche Punkte
		$moegliche_punkte = [];
		$tatsaechliche_punkte = [];
		foreach ($punkte as $team_id => $tp) {
			$tatsaechliche_punkte[$team_id] = $tp['punkte'];
			if ($tp['paarungen'] < count($team_ids) - 1) {
				$diff = count($team_ids) - 1 - $tp['paarungen'];
				if ($tw === 'mp') {
					$punkte[$team_id]['punkte_max'] = $tp['punkte'] + $diff * 2;
				} else {
					$punkte[$team_id]['punkte_max'] = $tp['punkte'] + $diff * $event['bretter_min'];
				}
				$moegliche_punkte[$team_id] = $punkte[$team_id]['punkte_max'];
			}
		}
		asort($tatsaechliche_punkte);
		if (empty($moegliche_punkte)) {
			// jeder hat untereinander gegen jede gespielt
			$teams += $tatsaechliche_punkte;
		} else {
			// nicht alle haben gegeneinander gespielt
			// Auswertung, wird so interpretiert, dass nur von oben weg platziert
			// werden kann, sobald der erste Platz uneindeutig ist, wird nicht
			// mehr weitergewertet
			asort($moegliche_punkte);
			$betrachtete_teams = $tatsaechliche_punkte;
			$letztes_team = false;
			$letzte_punkte = false;
			$stop = false;
			foreach ($betrachtete_teams as $team_id => $punkte) {
				if ($stop) {
					$teams[$letztes_team] = 'n. a.';
				} elseif ($letzte_punkte === '') {
					$teams[$letztes_team] = '-';
				} elseif ($letzte_punkte !== false) {
					// falls punktgleich, wird hier abgebrochen
					if ($punkte === $letzte_punkte) {
						$stop = true;
						$teams[$letztes_team] = 'n. a.';
					} else {
						// check, ob jemand mehr Punkte erreichen kann
						foreach ($moegliche_punkte as $m_team_id => $m_punkte) {
							if ($m_punkte >= $letzte_punkte) {
								$stop = true;
								$teams[$letztes_team] = 'n. a.';
							}
						}
						if (!$stop) {
							$teams[$letztes_team] = '('.$letzte_punkte.')';
						}
					}
				}
				$letztes_team = $team_id;
				$letzte_punkte = $punkte;
				unset($moegliche_punkte[$team_id]);
			}
			if ($stop) {
				$teams[$letztes_team] = 'n. a.';
			} elseif ($letzte_punkte === '') {
				$teams[$letztes_team] = '-';
			} else {
				$teams[$letztes_team] = '('.$letzte_punkte.')';
			}
		}
	}
	arsort($teams);
	return $teams;
}

/**
 * generiert einen Tabellenstand für ein Einzelturnier
 *
 * @author Erik Kothe
 * @author Gustaf Mossakowski
 */
class cms_tabellenstand_einzel {
	var $buchholz = [];
	var $buchholzSpieler = [];
	var $runde_no = 0;
	var $sieg = 1;
	var $remis = 0.5;

	function setAktRunde($runde) {
		$this->runde_no = $runde;
	}

	function setSieg($punkte) {
		$this->sieg = $punkte;
	}

	function setRemis($punkte) {
		$this->remis = $punkte;
	}

	function getSpieler($event_id) {
		global $zz_setting;
		$sql = 'SELECT event_id, person_id, t_vorname, t_nachname, setzliste_no
			FROM teilnahmen
			WHERE event_id = %d
			AND usergroup_id = %d
			AND teilnahme_status = "Teilnehmer"';
		$sql = sprintf($sql, $event_id, wrap_id('usergroups', 'spieler'));
		$spieler = wrap_db_fetch($sql, 'person_id');
		return $spieler;
	}
	
	/**
	 * Buchholzsumme berechnen
	 *
	 * @param int $event_id
	 * @param int $person_id
	 * @param string $variante
	 * @return array
	 */
	function getBuchholzsumme($event_id, $person_id, $variante) {
		static $rundenergebnisse;
		if (empty($rundenergebnisse)) $rundenergebnisse = [];
		$buchholzsumme = [];

		// Welche Regelung wird angewendet?
		$korrektur = my_fide_wertungskorrektur($event_id);

		if (empty($rundenergebnisse)) {
			$sql = 'SELECT person_id, runde_no, partiestatus_category_id, gegner_id
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse
				WHERE runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $this->runde_no);
			$rundenergebnisse = wrap_db_fetch($sql, ['person_id', 'runde_no']);
		}
		$runden = $rundenergebnisse[$person_id];
		foreach ($runden as $runde) {
			if ($runde['gegner_id'] == NULL) continue;
			$buchholzsumme[$runde['gegner_id']] = $this->getBuchholz($event_id, $runde['gegner_id'], $korrektur, $variante);
		}

		if (count($buchholzsumme) < $this->runde_no) {
			if ($korrektur === 'fide-2012') {
			/* Für den Fall das nicht gepaart wurde */
				$rundenSumme = 1; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]) || $runden[$runde]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/kampflos').'') {
						$buchholzsumme['aktRunde'.$runde] = $rundenSumme + ($this->runde_no - $runde) * 0.5; // $this->remis?
					}
					if (!empty($runden[$runde])) {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				// Wichtig für Streichergebnisse!
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]['gegner_id'])) {
						$buchholzsumme['runde'.$runde] = 0;
					}
				}
			}
		}

		$buchholz = my_buchholz_varianten($buchholzsumme);
		return $buchholz[$variante];
	}

	/**
	 * Buchholz für Buchholzsumme auswerten
	 */
	function getBuchholz($event_id, $person_id, $korrektur, $variante) {
		if (isset($this->buchholzSpielerFein[$event_id][$person_id])) {
			return $this->buchholzSpielerFein[$event_id][$person_id][$variante];
		}

		$gegner_punkte = $this->getBuchholzGegnerPunkte($event_id, $person_id, $korrektur);

		if (count($gegner_punkte) < $this->runde_no) {
			$sql = 'SELECT runde_no, partiestatus_category_id
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse 
				WHERE person_id = %d
				AND runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $person_id, $this->runde_no);
			$runden = wrap_db_fetch($sql, 'runde_no');
			if ($korrektur === 'fide-2012') {
				$rundenSumme = 0; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]) || $runden[$runde]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/kampflos').'') {
						$gegner_punkte["runde".$runde] = $rundenSumme + ($this->runde_no-$runde) * $this->remis;
					}
					if (!empty($runden[$runde])) {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				// Wichtig für Streichergebnisse!
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde])) {
						$gegner_punkte['runde'.$runde] = 0;
					}
				}
			}
		}

		$buchholz = my_buchholz_varianten($gegner_punkte);
		$this->buchholzSpielerFein[$event_id][$person_id] = $buchholz;
		return $buchholz[$variante];
	}

	/**
	 * Buchholz auswerten
	 */
	function getBuchholzSpieler($event_id, $person_id) {
		static $kampflose_turnier;
		static $berechnet;
		if (empty($kampflose_turnier)) $kampflose_turnier = [];
		if (isset($this->buchholzSpieler[$event_id][$person_id])) {
			return $this->buchholzSpieler[$event_id][$person_id];
		}
		// Welche Regelung wird angewendet?
		$korrektur = my_fide_wertungskorrektur($event_id);

		// Hat Spieler eine Partie kampflos gewonnen?
		if (empty($berechnet)) {
			$sql = 'SELECT person_id, CONCAT(IFNULL(gegner_id, "freilos"), "-", runde_no) AS _index
					, gegner_id, runde_no
				FROM partien_einzelergebnisse 
				WHERE partiestatus_category_id = %d
				AND ergebnis = "1.0"
				AND runde_no <= %d';
			$sql = sprintf($sql, wrap_category_id('partiestatus/kampflos'), $this->runde_no);
			$kampflose_turnier = wrap_db_fetch($sql, ['person_id', '_index']);
			$berechnet = true; // zweite Variable, da Ergebnis leer falls keine kampflose
		}
		if (array_key_exists($person_id, $kampflose_turnier)) {
			$kampflose = $kampflose_turnier[$person_id];
		} else {
			$kampflose = [];
		}

		$gegner_punkte = $this->getBuchholzGegnerPunkte($event_id, $person_id, $korrektur, $kampflose);

		/* Testen ob nicht gepaart wurde */
		if (count($gegner_punkte) < $this->runde_no) {
			$sql = 'SELECT runde_no
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse
				WHERE person_id = %d
				AND runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $person_id, $this->runde_no);
			$runden = wrap_db_fetch($sql, 'runde_no');
			if ($korrektur === 'fide-2012') {

				$rundenSumme = 1; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde])) {
						$gegner_punkte["aktRunde".$runde] = $rundenSumme + ($this->runde_no-$runde) * $this->remis;
					} else {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					$gegner_punkte['runde'.$runde] = 0;
				}
			}
		}

		$buchholz = my_buchholz_varianten($gegner_punkte);
		$this->buchholzSpieler[$event_id][$person_id] = $buchholz;
		return $buchholz;
	}

	/**
	 * Lese Punkte der Gegner aus
	 * Kampflose Partien werden unabhängig vom tatsächlichen Ergebnis mit 0.5 gewertet
	 * Runden ohne Paarung werden ebenfalls mit 0.5 gewertet
	 *
	 * @param int $event_id
	 * @param int $person_id
	 * @param string $korrektur
	 * @param array $kampflose (optional)
	 * @return array 
	 */
	function getBuchholzGegnerPunkte($event_id, $person_id, $korrektur, $kampflose = []) {
		static $gegnerpunkte;
		// Punkte pro Runde auslesen
		// Liste, bspw. [2005-1] => [1 => 0.5, 2 => 0.0 ...], [2909-2] => ()
		// fide-2009, fide-2012: kampflose Partien werden mit 0.5 gewertet
		$kampflos_als_remis = 0;
		if (in_array($korrektur, ['fide-2009', 'fide-2012'])) $kampflos_als_remis = 1;

		if (empty($gegnerpunkte)) {
			// Einmal pro Turnier berechnen, damit die teure Abfrage
			// nicht öfter gestellt werden muß
			$sql = 'SELECT person_id
					, CONCAT(gegner_id, "-", runde_no) AS _index
					, IF(partiestatus_category_id = %d AND %d = 1, %s, CASE punkte WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS buchholz
					, runde_gegner
				FROM buchholz_einzel_mit_kampflosen_view
				WHERE runde_no <= %d
				AND runde_gegner <= %d
				ORDER BY runde_no, gegner_id, runde_gegner';
			$sql = sprintf($sql
				, wrap_category_id('partiestatus/kampflos')
				, $kampflos_als_remis,
				$this->remis, $this->sieg, $this->remis, $this->runde_no, $this->runde_no
			);
			$gegnerpunkte = wrap_db_fetch($sql, ['person_id', '_index', 'runde_gegner', 'buchholz'], 'key/value');
		}
		$gegner_punkte_pro_runde = $gegnerpunkte[$person_id];

		if ($korrektur === 'fide-2012') {
			// Kampflose Siege?
			foreach ($kampflose as $gegner => $kampflos) {
				$freilos = !in_array($gegner, array_keys($gegner_punkte_pro_runde)) ? true : false;
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if ($runde > $kampflos['runde_no']) {
						// Runden nach kampfloser Paarung: 0.5 Punkte
						$punkte = $this->remis;
					} elseif ($freilos OR $runde == $kampflos['runde_no']) {
						// Bei Freilos: Runden vor kampfloser Paarung: 0 Punkte
						// Partie selbst wird ebenfalls mit 0 Punkten gewertet
						$punkte = 0;
					} else {
						// Gegner existiert, Partie kampflos
						// Tatsächliche Punkte bis zur Runde mit kampflosem Verlust,
						continue;
					}
					$gegner_punkte_pro_runde[$gegner][$runde] = $punkte;
				}
			}
		}

		// Punkte zusammenfassen pro Gegner
		$gegner_punkte = [];
		foreach ($gegner_punkte_pro_runde as $gegner => $punkte_pro_runde) {
			if ($korrektur === 'fide-2012') {
				// Falls weniger Runden als aktuelle Runde, pro Runde 0.5 Punkte addieren
				if (count($punkte_pro_runde) < $this->runde_no) {
					$punkte_pro_runde[] = ($this->runde_no - count($punkte_pro_runde)) * $this->remis;
				}
			}
			$gegner_punkte[$gegner] = array_sum($punkte_pro_runde);
		}
		return $gegner_punkte;
	}
}

/**
 * Brettpunkte für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_punkte($event_id, $runde_no) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Sonneborn-Berger für Einzelturniere berechnen
 * = Ergebnis x Punktzahl der Gegner nach der aktuellen Runde
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_sonneborn_berger($event_id, $runde_no) {
	$sql = 'SELECT pe.person_id, SUM(punkte * ergebnis) AS sb
		FROM partien_einzelergebnisse pe
		LEFT JOIN buchholz_einzel_mit_kampflosen_view bhe
			ON pe.person_id = bhe.person_id
			AND pe.gegner_id = bhe.gegner_id
		WHERE runde_gegner <= %d
		AND pe.runde_no <= %d
		GROUP BY pe.person_id
		ORDER BY sb DESC
	';
	$sql = sprintf($sql, $runde_no, $runde_no);
	$wertungen = wrap_db_fetch($sql, ['person_id', 'sb'], 'key/value');
	return $wertungen;
}

/**
 * Sonneborn-Berger für Mannschaftsturniere berechnen
 * = Erzielte Brettpunkte x Mannschaftspunktzahl der Gegner nach der aktuellen Runde
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste team_id => value
 */
function my_wertung_team_sonneborn_berger($event_id, $runde_no) {
	// paarungen_ergebnisse_view gibt bei Gewinn 2 MP, bei Unentschieden 1 MP aus
	// daher MP / 2 * gegnerische MP
	$sql = 'SELECT paarungen_ergebnisse_view.team_id
			, SUM(paarungen_ergebnisse_view.brettpunkte * tabellenstaende_view.wertung) AS sb
		FROM paarungen_ergebnisse_view
		LEFT JOIN tabellenstaende_view
			ON paarungen_ergebnisse_view.gegner_team_id = tabellenstaende_view.team_id
		WHERE paarungen_ergebnisse_view.event_id = %d
		AND tabellenstaende_view.runde_no = %d
		AND paarungen_ergebnisse_view.runde_no <= %d
		AND tabellenstaende_view.wertung_category_id = %d
		GROUP BY paarungen_ergebnisse_view.team_id
		ORDER BY sb DESC
	';
	$sql = sprintf($sql
		, $event_id
		, $runde_no, $runde_no
		, wrap_category_id('turnierwertungen/mp')
	);
	$wertungen = wrap_db_fetch($sql, 'team_id', 'key/value');
	return $wertungen;
}

/**
 * Buchholz für Mannschaftsturniere berechnen bei Erstwertung Brettpunkte
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste team_id => value
 */
function my_wertung_team_buchholz_bp($event_id, $runde_no) {
	// @todo
	// check if there's a correction here as well
	//			, SUM(IF((gegners_paarungen.kampflos = 1), 1, gegners_paarungen.brettpunkte))
	//			AS buchholz_mit_korrektur
	// Swiss-Chess says no

	$sql = 'SELECT tabellenstaende_termine_view.team_id
			, SUM(gegners_paarungen.brettpunkte) AS buchholz
		FROM paarungen_ergebnisse_view
		LEFT JOIN tabellenstaende_termine_view USING (team_id)
		LEFT JOIN paarungen_ergebnisse_view gegners_paarungen
			ON gegners_paarungen.team_id = paarungen_ergebnisse_view.gegner_team_id
		WHERE paarungen_ergebnisse_view.runde_no <= tabellenstaende_termine_view.runde_no
		AND tabellenstaende_termine_view.runde_no = %d
		GROUP BY tabellenstaende_termine_view.team_id
		ORDER BY buchholz DESC';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Drei-Punkte-Regelung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_3_punkte($event_id, $runde_no) {
	$sql = 'SELECT person_id, SUM(IF(ergebnis = 1, 3, IF(ergebnis = 0.5, 1, 0))) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Fortschrittswertung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_fortschritt($event_id, $runde_no, $tabelle) {
	$sql = 'SELECT person_id, SUM((%d - runde_no + 1) * ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no, $runde_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach ($tabelle as $person_id => $stand) {
		if (array_key_exists($person_id, $wertungen)) continue;
		$wertungen[$person_id] = 0;
	}
	return $wertungen;
}

/**
 * Gegnerschnitt für Einzelturniere berechnen
 * Elo vor DWZ
 *
 * Schnitt nur über Ergebnisse gegen einen Gegner, falls Freilos wird Runde 
 * nicht gewertet! = NOT ISNULL(partien_einzelergebnisse.gegner_id)
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_performance($event_id, $runde_no) {
	$sql = 'SELECT partien_einzelergebnisse.person_id, ROUND(SUM(IFNULL(IFNULL(t_elo, t_dwz), 0))/COUNT(partie_id)) AS wertung
		FROM partien_einzelergebnisse
		LEFT JOIN teilnahmen
			ON partien_einzelergebnisse.event_id = teilnahmen.event_id
			AND partien_einzelergebnisse.gegner_id = teilnahmen.person_id
		WHERE runde_no <= %d
		AND NOT ISNULL(partien_einzelergebnisse.person_id)
		AND NOT ISNULL(partien_einzelergebnisse.gegner_id)
		GROUP BY partien_einzelergebnisse.person_id
	';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Gewinnpartien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_gewinnpartien($event_id, $runde_no, $tabelle) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE ergebnis = 1
		AND runde_no <= %d
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach ($tabelle as $person_id => $stand) {
		if (array_key_exists($person_id, $wertungen)) continue;
		$wertungen[$person_id] = 0;
	}
	return $wertungen;
}

/**
 * gespielte Partien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_gespielte_partien($event_id, $runde_no) {
	global $zz_setting;
	$sql = 'SELECT person_id, COUNT(partie_id) AS partien
		FROM teilnahmen
		LEFT JOIN partien
			ON (teilnahmen.person_id = partien.schwarz_person_id
			OR teilnahmen.person_id = partien.weiss_person_id)
			AND partien.event_id = teilnahmen.event_id
		WHERE teilnahmen.event_id = %d
		AND partien.runde_no <= %d
		AND teilnahmen.usergroup_id = %d
		GROUP BY person_id
		ORDER BY COUNT(partie_id)';
	$sql = sprintf($sql, $event_id, $runde_no, wrap_id('usergroups', 'spieler'));
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Platz in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_startrangliste($event_id, $runde_no, $tabelle) {
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $stand['setzliste_no'];
	}
	return $wertungen;
}

/**
 * Buchholz mit Korrektur in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_buchholz_korrektur($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz'];
	}
	return $wertungen;	
}

/**
 * Buchholz mit einer Streichwertung in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_buchholz_1_streichwertung($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz Cut 1'];
	}
	return $wertungen;	
}

/**
 * Buchholz mit zwei Streichwertungen in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_buchholz_2_streichwertungen($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz Cut 2'];
	}
	return $wertungen;	
}

/**
 * Buchholz gemittelt in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_buchholz_gemittelt($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Median Buchholz'];
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_verfeinerte_buchholz($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, eine Streichwertung, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_verfeinerte_buchholz_1($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 1');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, zwei Streichwertungen, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_verfeinerte_buchholz_2($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 2');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, gemittelt, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_verfeinerte_buchholz_gemittelt($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Median Buchholz');
	}
	return $wertungen;	
}

/**
 * Buchholz-Varianten berechnen
 *
 * @param array $gegner_punkte Liste der Punkte der Gegner bzw. Buchholz
 * @return array
 */
function my_buchholz_varianten($gegner_punkte) {
	// Reine Buchholz
	$buchholz['Buchholz'] = array_sum($gegner_punkte);

	// Cut 1: schlechteste Wertung streichen
	arsort($gegner_punkte);
	array_pop($gegner_punkte);
	$buchholz['Buchholz Cut 1'] = array_sum($gegner_punkte);

	// Cut 2: zwei schlechteste Wertungen streichen
	$cut2 = $gegner_punkte;
	array_pop($cut2);
	$buchholz['Buchholz Cut 2'] = array_sum($cut2);

	// Median: schlechteste und beste Wertung streichen
	array_shift($gegner_punkte);
	$buchholz['Median Buchholz'] = array_sum($gegner_punkte);

	// Median 2: zwei schlechteste und zwei beste Wertungen streichen
	array_shift($cut2);
	array_shift($cut2);
	$buchholz['Median Buchholz 2'] = array_sum($cut2);
	
	return $buchholz;
}

/**
 * Setze FIDE-Regelung für nicht gespielte Partien nach Datum des Beginns
 * eines Turniers
 *
 * @param int $event_id
 * @return string
 *		'ohne' => Punkte werden als Punkte gewertet
 *		'fide-2009' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/a
 *			für Turnier nach FIDE-Kongreß (?) = 2009-10-18
 *		'fide-2012' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/b
 *			für Turniere nach 2012-07-01
 */
function my_fide_wertungskorrektur($event_id) {
	static $korrektur;
	if (empty($korrektur)) $korrektur = [];
	if (array_key_exists($event_id, $korrektur)) return $korrektur[$event_id];
	$sql = 'SELECT
			IF(date_begin >= "2012-07-01", "fide-2012",
				IF(date_begin >= "2009-10-18", "fide-2009", "ohne")) AS regelung
		FROM events
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$korrektur[$event_id] = wrap_db_fetch($sql, '', 'single value');
	return $korrektur[$event_id];
}
