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
			, YEAR(date_begin) AS year
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
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
	$page['breadcrumbs'][] = sprintf('<a href="../../">%d</a>', $event['year']);
	$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $event['event']);
	$page['breadcrumbs'][] = 'Tabellenstandupdate';
	$page['title'] = 'Tabellenstandupdates '.$event['event'].' '.$event['year'];
	$page['text'] = wrap_template('standings-update', $event);
	
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
		require_once __DIR__.'/standings-single.inc.php';
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
		require_once __DIR__.'/standings-team.inc.php';
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
 * Hole alle Turnierwertungen für ein Turnier
 * 
 * @param int $event_id
 * @return array
 */
function cms_tabellenstandupdate_wertungen($event_id) {
	$sql = 'SELECT category_id, category, category_short
			, SUBSTRING_INDEX(REPLACE(path, "-", "_"), "/", -1) AS path, anzeigen
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
