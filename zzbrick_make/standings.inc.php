<?php 

/**
 * tournaments module
 * calculate standings and write them to database
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @copyright Copyright © 2012-2022 Gustaf Mossakowski
 * @copyright Copyright © 2014 Erik Kothe
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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
			return mod_tournaments_make_standings_overview($vars);
		} else {
			return mod_tournaments_make_standings_round($vars);
		}
	} elseif (count($vars) === 2) {
		$vars[] = 1; // 1. Runde
		return mod_tournaments_make_standings_round($vars);
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
function mod_tournaments_make_standings_overview($vars) {
	global $zz_setting;
	if (count($vars) !== 2) return false;

	$sql = 'SELECT event_id, event, identifier
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
		FROM events
		WHERE identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$zz_setting['logfile_name'] = $event['identifier'];

	$sql = 'SELECT events.event_id, events.runde_no
			, (SELECT COUNT(*) FROM partien
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
		require_once __DIR__.'/../tournaments/cronjobs.inc.php';
		$runde = key($_POST);
		if (substr($runde, 0, 6) === 'runde_') {
			$runde = substr($runde, 6);
			mf_tournaments_job_create('tabelle', $event['event_id'], $runde);
			mf_tournaments_job_trigger();
			wrap_redirect_change();
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
function mod_tournaments_make_standings_round($vars) {
	global $zz_conf;
	$time = microtime(true);
	require_once __DIR__.'/../tournaments/cronjobs.inc.php';

	// Zugriffsberechtigt?
	if (!brick_access_rights(['Webmaster'])) wrap_quit(403);

	$runde = 1; // beginne bei Runde 1 oder bei angegebener Runde
	if (count($vars) === 3 AND is_numeric($vars[2])) $runde = $vars[2];
	elseif (count($vars) !== 2) return false;

	$sql = 'SELECT event_id, events.identifier
			, runden, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform, bretter_min
			, tournament_id
			, (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id) AS runden_gespielt
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$zz_setting['logfile_name'] = $event['identifier'];

	if ($runde > $event['runden_gespielt']) {
		mf_tournaments_job_finish('tabelle', 0, $event['event_id'], $runde);
		wrap_quit(404);
	}
	if ($runde > $event['runden']) {
		mf_tournaments_job_finish('tabelle', 0, $event['event_id'], $runde);
		wrap_error(sprintf('Tabellenstand-Update: Runde %d/%d nicht möglich (Termin %d/%s)',
			$runde, $event['runden'], $vars[0], $vars[1]), E_USER_ERROR);
	}

	// check if there were games played in this round
	$sql = 'SELECT COUNT(*)
		FROM partien
		WHERE event_id = %d AND runde_no = %d
		AND partiestatus_category_id = %d';
	$sql = sprintf($sql
		, $event['event_id']
		, $runde
		, wrap_category_id('partiestatus/normal')
	);
	$games_played_in_round = wrap_db_fetch($sql, '', 'single value');
	if (!$games_played_in_round) {
		mf_tournaments_job_finish('tabelle', 0, $event['event_id'], $runde);
		wrap_quit(404);
	}

	$type = implode('/', $vars);
	$zz_conf['user'] = 'Tabellenstand '.$type;
	if ($event['turnierform'] === 'e') {
		require_once __DIR__.'/standings-single.inc.php';
		$tabelle = mod_tournaments_make_standings_calculate_single($event, $runde);
		if (!$tabelle) {
			mf_tournaments_job_finish('tabelle', 0, $event['event_id'], $runde);
			return mod_tournaments_make_standings_return(false, $time, $type);
		}
		$success = mod_tournaments_make_standings_write_single($event['event_id'], $runde, $tabelle);
		if (!$success) {
			mf_tournaments_job_finish('tabelle', 0, $event['event_id'], $runde);
			return mod_tournaments_make_standings_return(false, $time, $type);
		}
	} else {
		$event['runde_no'] = $runde;
		require_once __DIR__.'/standings-team.inc.php';
		mod_tournaments_make_standings_team($event);
	}
	mf_tournaments_job_finish('tabelle', 1, $event['event_id'], $runde);
	if ($runde < $event['runden_gespielt']) {
		mf_tournaments_job_create('tabelle', $event['event_id'], $runde + 1);
		mf_tournaments_job_trigger();
	}
	
	// Aktuelle runde_no von Tabellenstand speichern
	// aus Performancegründen
	$sql = 'SELECT MAX(runde_no) FROM tabellenstaende WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$max_runde_no = wrap_db_fetch($sql, '', 'single value');
	$values = [];
	$values['action'] = 'update';
	$values['POST']['tournament_id'] = $event['tournament_id'];
	$values['POST']['tabellenstand_runde_no'] = $max_runde_no;
	$ops = zzform_multi('turniere', $values);

	return mod_tournaments_make_standings_return(true, $time, $type);
}

function mod_tournaments_make_standings_return($bool, $time, $type) {
	$time = microtime(true) - $time;
	if ($time < 1) return $bool; // do not log if it's fast enough
	wrap_log(sprintf('Tabellenstand %s in %s sec erstellt.', $type, $time));
	return $bool;
}

/**
 * Hole alle Turnierwertungen für ein Turnier
 * 
 * @param int $event_id
 * @return array
 */
function mod_tournaments_make_standings_get_scoring($event_id) {
	$sql = 'SELECT category_id, category, category_short
			, SUBSTRING_INDEX(REPLACE(path, "-", "_"), "/", -1) AS path, anzeigen
		FROM turniere_wertungen
		JOIN tournaments USING (tournament_id)
		JOIN categories
			ON turniere_wertungen.wertung_category_id = categories.category_id
		WHERE event_id = %d
		ORDER BY turniere_wertungen.reihenfolge, categories.sequence';
	$sql = sprintf($sql, $event_id);
	return wrap_db_fetch($sql, 'category_id');
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
function mod_tournaments_make_standings_prepare($event, $tabelle, $wertungen, $turnierwertungen) {
	$tsw = [];
	foreach (array_keys($turnierwertungen) as $category_id) {
		if (empty($wertungen[$category_id])) continue;
		if (!is_array($wertungen[$category_id])
			AND $category_id.'' === wrap_category_id('turnierwertungen/dv').'') {
			$wertungen[$category_id] = mf_tournaments_make_team_direct_encounter(
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
	$tabelle = mod_tournaments_make_standings_sort($tabelle);
	return $tabelle;
}

/**
 * Teams nach ihrem aktuellen Tabellenstand sortieren
 *
 * @param array $standings
 * @return array (sortiert nach platz_no)
 */
function mod_tournaments_make_standings_sort($standings) {
	foreach ($standings as $participant_id => $values) {
		if (!is_numeric($participant_id)) continue;
		$places[$participant_id] = $values['platz_no'];
	}
	array_multisort($places, SORT_ASC, $standings);
	return $standings;
}

/**
 * Setze FIDE-Regelung für nicht gespielte Partien nach Datum des Beginns
 * eines Turniers
 *
 * @param int $event_id
 * @return string
 *		NULL => Punkte werden als Punkte gewertet
 *		'fide-2009' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/a
 *			für Turnier nach FIDE-Kongreß (?) = 2009-10-18
 *		'fide-2012' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/b
 *			für Turniere nach 2012-07-01
 */
function mf_tournaments_make_fide_correction($event_id) {
	static $correction;
	if (empty($correction)) $correction = [];
	if (array_key_exists($event_id, $correction)) return $correction[$event_id];
	$sql = 'SELECT
			IF(date_begin >= "2012-07-01", "fide-2012",
				IF(date_begin >= "2009-10-18", "fide-2009", NULL))
		FROM events
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$correction[$event_id] = wrap_db_fetch($sql, '', 'single value');
	return $correction[$event_id];
}
