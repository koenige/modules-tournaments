<?php 

/**
 * tournaments module
 * write data chunks to SWT files
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2013-2016, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Schreiben von einzelnen Daten in eine SWT-Datei
 * insbesondere der person_id und team_id in das Feld 'Info4'
 *
 * @param array $vars
 *		int [0]: Jahr
 *		int [1]: Turnierkennung
 * @return array $page
 * @todo gemeinsame Elemente mit SWT-Import abstrahieren
 */
function mod_tournaments_make_swtwriter($vars) {
	global $zz_setting;
	global $zz_conf;

	ignore_user_abort(1);
	ini_set('max_execution_time', 120);

	$writer = [];
	$writer['identifier'] = implode('/', $vars);
	if (count($vars) !== 2) {
		wrap_error(sprintf('SWT-Import: Falsche Zahl von Parametern: %s', $writer['identifier']));
		return false;
	}
	$zz_setting['active_module_for_log'] = $writer['identifier'];
	
	// @todo Einzel- oder Mannschaftsturnier aus Termine auslesen
	// Datenherkunft aus Turniere
	$sql = 'SELECT event_id, event, events.identifier, IFNULL(event_year, YEAR(date_begin)) AS year
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON turnierformen.category_id = tournaments.turnierform_category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($writer['identifier']));
	$event = wrap_db_fetch($sql);
	if (empty($event['event_id'])) {
		wrap_log('SWT-Import: Kein Termin für diese Parameter in der Datenbank');
		return false;
	}
	
	$page['query_strings'] = ['delete'];
	
	$page['breadcrumbs'][] = '<a href="/intern/termine/">Termine</a>';
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%d/">%d</a>',
		$event['year'], $event['year']
	);
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%s/">%s</a>',
		$event['identifier'], $event['event']
	);
	$page['breadcrumbs'][] = 'SWT-Writer';

	// Variante 1: Direkt SWT-Datei auslesen
	$swt = $event['identifier'].'.swt';
	$filename = $zz_setting['media_folder'].'/swt/'.$swt;
	if (!file_exists($filename)) {
		wrap_log(sprintf('Datei swt/%s existiert nicht', $swt));
		$zz_setting['error_prefix'] = '';
		return false;
	}
	
	// Ein paar Sekunden warten, bevor nach Upload die Datei geschrieben
	// werden kann (kann sonst zu schnell sein, neue IDs noch nicht in DB)
	$last_changed = filemtime($filename);
	$time_diff = time() - $last_changed;
	$seconds_to_wait = 45;
	$time_diff -= $seconds_to_wait;
	if ($time_diff < 0) {
		sleep(-$time_diff);
	}

	if (!is_writable($filename)) {
		wrap_log(sprintf('Datei swt/%s ist nicht schreibbar', $swt));
		$zz_setting['error_prefix'] = '';
		return false;
	}

    if (!$handle = fopen($filename, "r+b")) {
		wrap_log(sprintf('Datei swt/%s ist nicht öffenbar', $swt));
		$zz_setting['error_prefix'] = '';
		return false;
    }

	// SWT-Parser einbinden
	require_once $zz_setting['lib'].'/swtparser/swtparser.php';
	// @todo unterstütze Parameter für UTF-8-Codierung
	$tournament = swtparser($filename, $zz_conf['character_set']);
	$field_names = swtparser_get_field_names('de');

	if (isset($_GET['delete'])) {
		switch ($_GET['delete']) {
			// Spieler und Team Info4
			case 'teams': $to_delete = [1046]; break;
			case 'spieler': $to_delete = [2038]; break;
			default: $to_delete = [1046, 2038]; break;
		}
		$writer['deletions'] = 0;
		foreach ($tournament['bin'] as $index => $token) {
			if (in_array($token['content'], $to_delete)) {
				$result = mod_tournaments_make_swtwriter_delete($handle, $token);
				if ($result) $writer['deletions']++;
			}
		}
	} else {
		$writer['changes_team_id'] = 0;
		$writer['changes_person_id'] = 0;
		foreach ($tournament['bin'] as $index => $token) {
			// 1046, 2038
			if ($token['content'] == 1012) {
				// 1012 MNr. Rangliste
				$team_id = mod_tournaments_make_swtwriter_read_id($handle, $token, 'team_id', 'teams', $event['event_id']);
			} elseif ($token['content'] == 1046) {
				// 1046 Team Info4
				$result = mod_tournaments_make_swtwriter_write($handle, $token, 'team_id', $team_id);
				if ($result) $writer['changes_team_id']++;
			} elseif ($token['content'] == 2020) {
				// 2020 Spieler TNr.-ID hex
				$person_id = mod_tournaments_make_swtwriter_read_id($handle, $token, 'person_id', 'teilnahmen', $event['event_id']);
			} elseif ($token['content'] == 2038) {
				// 2038 Spieler Info4
				$result = mod_tournaments_make_swtwriter_write($handle, $token, 'person_id', $person_id);
				if ($result) $writer['changes_person_id']++;
			}
		}
		if ($writer['changes_team_id'] OR $writer['changes_person_id']) {
			$writer['changes'] = true;
		}
	}
    fclose($handle);
	
	$zz_setting['error_prefix'] = '';
	if (!empty($writer['changes'])) {
		wrap_log(sprintf('SWT-Writer für %s: %d Personen, %d Teams geschrieben.',
			$swt, $writer['changes_person_id'], $writer['changes_team_id']
		));
	}
	$page['text'] = wrap_template('swtwriter', $writer);
	return $page;
}

/**
 * Liest ID aus der SWT-Datei
 *
 * @param resource $handle
 * @param array $token
 * @param string $field
 * @param string $table
 * @param int $event_id
 * @return int $id
 */
function mod_tournaments_make_swtwriter_read_id($handle, $token, $field, $table, $event_id) {
	fseek($handle, $token['begin']);
	$string = fread($handle, $token['end'] - $token['begin'] + 1);
	$sql = 'SELECT %s
		FROM %s
		WHERE event_id = %d AND fremdschluessel = %d';
	$sql = sprintf($sql, $field, $table, $event_id, hexdec(bin2hex(strrev($string))));
	$id = wrap_db_fetch($sql, '', 'single value');
	return $id;
}

/**
 * Schreibe Daten in die SWT-Datei
 *
 * @param resource $handle
 * @param array $token
 * @param string $field
 * @param int $id
 * @return bool true: something was written, false: nothing was written
 */
function mod_tournaments_make_swtwriter_write($handle, $token, $field, $id) {
	fseek($handle, $token['begin']);
	$string = fread($handle, $token['end'] - $token['begin'] + 1);
	parse_str($string, $info4);
	if ($id) {
		// nur, wenn wir schon eine ID haben
		$code = sprintf('%s=%d', $field, $id);
	} else {
		$code = '';
	}
	if (isset($info4[$field]) AND trim($info4[$field]) == $id) {
		return false;
	} elseif (!$id AND empty($info4[$field])) {
		return false;
	}
	// schreibe Wert + 00 für alle weiteren Felder
	fseek($handle, $token['begin']);
	fwrite($handle, $code);
	$repeat = $token['end'] - $token['begin'] + 1 - strlen($code);
	fwrite($handle, str_repeat(chr(0), $repeat));

	$string = fread($handle, $token['end'] - $token['begin'] + 1);

	return true;
}

function mod_tournaments_make_swtwriter_delete($handle, $token) {
	fseek($handle, $token['begin']);
	$repeat = $token['end'] - $token['begin'] + 1;
	fwrite($handle, str_repeat(chr(0), $repeat));
	return true;
}
