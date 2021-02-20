<?php 

/**
 * Zugzwang Project
 * import tournament data from SWT files
 *
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Importiert und synchronisiert Turnierdaten aus einer SWT-Datei
 * in ein vorher angelegtes Turnier
 *
 * @param array $vars
 *		int [0]: Jahr
 *		int [1]: Turnierkennung
 * @return array $page
 * @todo Einzelturniere unterstützen
 */
function mod_tournaments_make_swtimport($vars) {
	global $zz_setting;
	global $zz_conf;
	$zz_setting['cache'] = false;

	if (!brick_access_rights(['Webmaster'])) wrap_quit(403);
	ignore_user_abort(1);
	ini_set('max_execution_time', 180);

	$identifier = implode('/', $vars);
	$zz_setting['error_prefix'] = sprintf('SWT-Import (%s): ', $identifier);
	if (count($vars) !== 2) {
		wrap_error('= Falsche Zahl von Parametern.');
		$zz_setting['error_prefix'] = '';
		return false;
	}
	
	// @todo Einzel- oder Mannschaftsturnier aus Termine auslesen
	// Datenherkunft aus Turniere
	$sql = 'SELECT event_id, event, events.identifier, YEAR(date_begin) AS year
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, turniere.wertung_spielfrei
			, turniere.urkunde_parameter AS parameter
		FROM events
		LEFT JOIN turniere USING (event_id)
		LEFT JOIN categories turnierformen
			ON turnierformen.category_id = turniere.turnierform_category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($identifier));
	$event = wrap_db_fetch($sql);
	if (empty($event['event_id'])) {
		wrap_error('Kein Termin für diese Parameter in der Datenbank');
		$zz_setting['error_prefix'] = '';
		return false;
	}
	parse_str($event['parameter'], $parameter);
	if ($parameter) $event += $parameter;

	$page['breadcrumbs'][] = '<a href="/intern/termine/">Termine</a>';
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%d/">%d</a>',
		$event['year'], $event['year']
	);
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%s/">%s</a>',
		$event['identifier'], $event['event']
	);
	$page['breadcrumbs'][] = 'SWT-Import';

	$sql = 'SELECT category_id, categories.path
		FROM turniere_wertungen
		JOIN turniere USING (tournament_id)
		JOIN categories
			ON turniere_wertungen.wertung_category_id = categories.category_id
		WHERE event_id = %d
		ORDER BY turniere_wertungen.reihenfolge
	';
	$sql = sprintf($sql, $event['event_id']);
	$wertungen = wrap_db_fetch($sql, 'tw_id', 'key/value');

	// Direkt SWT-Datei auslesen
	$swt = $event['identifier'].'.swt';
	if (!file_exists($zz_setting['media_folder'].'/swt/'.$swt)) {
		wrap_error(sprintf('Datei swt/%s existiert nicht', $swt));
		$zz_setting['error_prefix'] = '';
		$page['text'] = '<p class="error">Die SWT-Datei für dieses Turnier existiert (noch) nicht. Bitte lade erst eine hoch.</p>';
		$page['status'] = 404;
		if ($_SESSION['username'] === $zz_setting['robot_username'])
			my_job_finish('swt', 0, $event['event_id']);
		return $page;
	}

	// SWT-Parser einbinden
	require_once $zz_setting['lib'].'/swtparser/swtparser.php';
	// @todo unterstütze Parameter für UTF-8-Codierung
	$tournament = swtparser($zz_setting['media_folder'].'/swt/'.$swt, $zz_conf['character_set']);
	$field_names = swtparser_get_field_names('de');

	if ($tournament['out'][35] === 1) {
		$form = 'mannschaftsturnier';
	} else {
		$form = 'einzelturnier';
	}

	// Check: richtiges Turnier?
	mod_tournaments_make_swtimport_turniercheck($event, $form, $tournament['out']);

	require_once $zz_conf['dir'].'/zzform.php';
	$zz_conf['user'] = sprintf('SWT-Import: %s', $identifier);
	$old_error_handling = $zz_conf['error_handling'];
	$zz_conf['error_handling'] = 'output';
	$ids = [];
	$ids['t'] = [];

	$import = [];
	// Datenintegrität prüfen, erst ab SWT mit Info4-Feld möglich
	if (empty($event['swisschess']['ignore_ids'])) {
		list($tournament, $import) = mod_tournaments_make_swtimport_integrity($tournament, $form);
	}

	// Team importieren
	if ($form === 'mannschaftsturnier') {
		$ids = mod_tournaments_make_swtimport_teams($event, $tournament['out']);
	}

	// Fehlende Personen ergänzen
	$ids = mod_tournaments_make_swtimport_personen($event, $tournament['out']['Spieler'], $ids, $import);

	// Aufstellungen importieren
	$ids = mod_tournaments_make_swtimport_teilnahmen($event, $tournament['out']['Spieler'], $ids);

	// Paarungen importieren
	// @todo prüfen, ob aktuelle Runde erforderlich (falls nur 1. Runde ausgelost 
	// ist, ergibt $aktuelle_runde false)
	if ($form === 'mannschaftsturnier') { // AND $aktuelle_runde) {
		$ids = mod_tournaments_make_swtimport_paarungen($event, $tournament['out'], $ids);
	}

	// Partien importieren
	$ids = mod_tournaments_make_swtimport_partien($event, $tournament['out'], $ids);

	// Überzählige Partien löschen
	mod_tournaments_make_swtimport_partien_loeschen($event['event_id'], $ids['partien']);

	$page['text'] = 'Import erfolgreich';
	$zz_setting['error_prefix'] = '';
	$zz_conf['error_handling'] = $old_error_handling;
	
	if ($form === 'mannschaftsturnier') {
		$ids = mod_tournaments_make_swtimport_delete($ids, $event['event_id'], 'teams');
	}
	$ids = mod_tournaments_make_swtimport_delete($ids, $event['event_id'], 'teilnahmen');

	foreach ($ids['t'] as $bereich => $anzahl) {
		$importiert = [
			'tabelle' => $bereich
		];
		foreach ($anzahl as $type => $count) {
			switch ($type) {
			case 'no_update':
				if ($count === 1) {
					$type = 'blieb unverändert';
				} else {
					$type = 'blieben unverändert';
				}
				break;
			case 'successful_update':
				if ($count === 1) {
					$type = 'wurde aktualisiert';
				} else {
					$type = 'wurden aktualisiert';
				}
				break;
			case 'successful_delete':
				if ($count === 1) {
					$type = 'wurde gelöscht';
				} else {
					$type = 'wurden gelöscht';
				}
				break;
			case 'successful_insert':
				if ($count === 1) {
					$type = 'wurde eingefügt';
				} else {
					$type = 'wurden eingefügt';
				}
				if (in_array($bereich, ['Teams', 'Personen'])) {
					$import['writer'] = true;
				}
				break;
			}	
			$importiert['eintraege'][] = [
				'typ' => $type,
				'count' => $count
			];
		}
		$import['importiert'][] = $importiert;
	}
	$import['identifier'] = $identifier;
	$page['text'] = wrap_template('swtimport', $import);
	if ($_SESSION['username'] === $zz_setting['robot_username'])
		my_job_finish('swt', 1, $event['event_id']);
	return $page;
}

/**
 * Check, ob SWT-Datei zu Turnier passt, falls nicht: Abbruch mit Fehlermeldung
 *
 * @param array $event
 * @param string $event
 * @param array $data
 * return bool (exit on error)
 */ 
function mod_tournaments_make_swtimport_turniercheck($event, $form, $data) {
	global $zz_setting;
	if ($form === 'einzelturnier' AND $event['turnierform'] !== 'e') {
		wrap_error(
			'Turnier wurde als Mannschaftsturnier angelegt, die SWT-Datei ist aber für ein Einzelturnier!',
			E_USER_ERROR
		);
	}
	if ($form === 'mannschaftsturnier' AND $event['turnierform'] === 'e') {
		wrap_error(
			'Turnier wurde als Einzelturnier angelegt, die SWT-Datei ist aber für ein Mannschaftsturnier!',
			E_USER_ERROR
		);
	}

	// no further check possible if IDs in Swiss Chess must not be used
	if (!empty($event['swisschess']['ignore_ids'])) return true;
	
	if ($form === 'einzelturnier') {
		$sql = 'SELECT person_id FROM teilnahmen
			WHERE usergroup_id = %d AND event_id = %d';
		$sql = sprintf($sql,
			wrap_id('usergroups', 'spieler'), $event['event_id']
		);
		$db_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
		$check_data = 'Spieler';
		$id_field_name = 'person_id';
		$field_key = 2038;
	} else {
		$sql = 'SELECT team_id
			FROM teams
			WHERE event_id = %d
			AND team_status = "Teilnehmer"
			ORDER BY fremdschluessel';
		$sql = sprintf($sql, $event['event_id']);
		$db_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
		$check_data = 'Teams';
		$id_field_name = 'team_id';
		$field_key = 1046;
	}
	$swt_ids = [];
	foreach ($data[$check_data] as $line) {
		// geht nur, wenn team_id oder person_id gesetzt ist
		if (empty($line[$field_key])) continue;
		parse_str($line[$field_key], $fields);
		if (empty($fields[$id_field_name])) continue;
		$swt_ids[] = $fields[$id_field_name];
	}
	$diff = array_diff($db_ids, $swt_ids);
	if ($db_ids AND $swt_ids AND $diff === $db_ids) {
		// Kein Team in Datenbank passt zu Teams aus SWT
		wrap_error('Turnierdatei passt nicht zum Turnier. Bitte lade die richtige Datei hoch!', E_USER_ERROR);
	}
	return true;
}

/**
 * Import der Teams
 *
 * @param array $event
 * @param array $tournament = $tournament['out']
 * @return array $ids
 * @todo prüfe vor Nutzung Fremdschlüssel $import['use_team_id'], noch nicht
 * implementiert, da nicht klar, ob der seltene Fall auftritt, dass nachträglich
 * Teams hinzugefügt werden
 */
function mod_tournaments_make_swtimport_teams($event, $tournament) {
	$ids['team_hex'] = [];
	$ids['team_dec'] = [];
	$ids['team_spielfrei'] = [];
	$ids['team_hex2dec'] = [];

	$sql = 'SELECT team_id, fremdschluessel
		FROM teams
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$teams = wrap_db_fetch($sql, 'team_id');

	// Team-IDs auslesen, falls vorhanden
	$team_ids = [];
	$t_teams_last = [];
	$t_teams_first = [];
	foreach ($tournament['Teams'] as $t_key => $team) {
		if (empty($event['swisschess']['ignore_ids'])) {
			$team_id = mod_tournaments_make_swtimport_team_id($team, $tournament['Spieler'], $event['event_id']);
		}
		$tournament['Teams'][$t_key]['team_id'] = $team_id;
		$team_ids[$t_key] = $team_id;
		if ($team_id) {
			// Liste ohne Teams ohne team_id
			$ids['team_hex2dec'][$t_key] = $team[1012];
			// Fremdschlüssel aktualisieren, falls geändert
			$teams[$team_id]['fremdschluessel'] = $team[1012];
			$t_teams_first[$t_key] = $team;
		} else {
			$t_teams_last[$t_key] = $team;
		}
	}
	$t_teams = $t_teams_first + $t_teams_last;
	
	foreach ($t_teams as $t_key => $team) {
		$values = [];
		$team_id = $team_ids[$t_key];
		if (!$team_id AND !in_array($team[1012], $ids['team_hex2dec'])) {
			// 2. Fremdschlüssel aus SWT in Datenbank?
			foreach ($teams as $team_id => $team_daten) {
				if ($team_daten['fremdschluessel'] != $team[1012]) {
					$team_id = false;
					continue;
				}
				break;
			}
		}
		if ($team_id) {
			$values['action'] = 'update';
			$values['POST']['team_id'] = $team_id;
		} else {
			$values['action'] = 'insert';
			// falls Teamname schon existiert, nicht ändern
			$values['POST']['team'] = trim($team[1000]);
			if ($team[1000] === 'spielfrei') {
				// Nur bei INSERT: spielfrei auf ja setzen, so kann man auch
				// Teams mit dem Namen 'spielfrei' erlauben.
				$values['POST']['spielfrei'] = 'ja';
			} elseif ($event['turnierform'] === 'm-v') {
				$verein = mod_tournaments_make_swtimport_verein($team, $tournament['Spieler']);
				if ($verein) {
					$values['POST']['team_no'] = mod_tournaments_make_swtimport_teamno($team[1000], $verein);
					if ($values['POST']['team_no']) {
						$values['POST']['team'] = trim(substr(
							$values['POST']['team'], 0, -strlen($values['POST']['team_no'])
						));
					}
					$values['POST']['verein_org_id'] = $verein['org_id'];
				}
			}
			$values['POST']['meldung'] = 'komplett'; // @todo evtl. auf NULL setzen
		}
		$values['POST']['event_id'] = $event['event_id'];
		$values['POST']['team_status'] = 'Teilnehmer';
		$values['POST']['setzliste_no'] = $team[1012];
		$values['POST']['fremdschluessel'] = $team[1012];
		$values['ids'] = ['event_id', 'verein_org_id', 'team_id'];
		$ops = zzform_multi('teams', $values);
		if (!$ops['id']) {
			wrap_error(sprintf('Team %s (Key: %s) konnte nicht hinzugefügt werden.',
				$team[1000], $t_key)
			);
		}
		if (!empty($ops['record_new']) AND $ops['record_new'][0]['spielfrei'] === 'ja') {
			$ids['team_spielfrei'][] = $team[1012];
		}
		if (!isset($ids['t']['Teams'][$ops['result']])) {
			$ids['t']['Teams'][$ops['result']] = 0;
		}
		$ids['t']['Teams'][$ops['result']]++;
		$ids['team_hex'][$t_key] = $ops['id'];
		$ids['team_dec'][$team[1012]] = $ops['id'];
		$ids['team_hex2dec'][$t_key] = $team[1012];
	}
	return $ids;
}

/**
 * Löschen von Teams
 *
 * @param array $ids
 * @int $event_id
 * @return array $ids
 */
function mod_tournaments_make_swtimport_delete($ids, $event_id, $type) {
	switch ($type) {
	case 'teams':
		$id_field = 'team_id';
		$table = 'teams';
		$id_source = 'team_hex';
		$key = 'Teams';
		$where = '';
		break;
	case 'teilnahmen':
		$id_field = 'teilnahme_id';
		$table = 'teilnahmen';
		$id_source = 'teilnahmen';
		$key = 'Teilnahmen (Spieler)';
		$where = sprintf(' AND usergroup_id = %d', wrap_id('usergroups', 'spieler'));
		break;
	default:
		wrap_error(sprintf('Löschen: Typ nicht unterstützt (%s)', $typ));
		return $ids;
	}
	if (empty($ids[$id_source])) $ids[$id_source] = [0];
	$sql = 'SELECT %s
		FROM %s
		WHERE event_id = %d
		AND %s NOT IN (%s)
		%s';
	$sql = sprintf($sql, $id_field, $table, $event_id, $id_field, implode(',', $ids[$id_source]), $where);
	$loeschen_ids = wrap_db_fetch($sql);

	foreach ($loeschen_ids as $id) {
		$values = [];
		$values['action'] = 'delete';
		$values['POST'][$id_field] = $id;
		$ops = zzform_multi($table, $values);
		if (!$ops['id']) {
			wrap_error(sprintf('ID %d (%s) konnte nicht gelöscht werden.', $id, $table));
		} else {
			if (!isset($ids['t'][$key][$ops['result']])) {
				$ids['t'][$key][$ops['result']] = 0;
			}
			$ids['t'][$key][$ops['result']]++;
		}
	}
	return $ids;
}

/**
 * Import der Personen
 *
 * @param array $event
 * @param array $spielerliste = $tournament['out']['Spieler']
 * @param array $ids
 * @param array $import
 * @return array $ids
 */
function mod_tournaments_make_swtimport_personen($event, $spielerliste, $ids, $import) {
	global $zz_setting;
	global $zz_conf;
	require_once $zz_conf['dir_custom'].'/editing.inc.php';
	require_once $zz_setting['custom_wrap_dir'].'/personen.inc.php';
	
	$ids['person_spielfrei'] = [];
	$ids['person'] = [];

	foreach ($spielerliste as $s_key => $spieler) {
		if (isset($ids['team_spielfrei']) AND in_array($spieler[2016], $ids['team_spielfrei'])) {
			$ids['person_spielfrei'][] = $s_key;
			$ids['person'][$s_key] = NULL;
			continue;
		}
// @todo: das geht nicht, da ein Spieler auch im Laufe des Turniers auf inaktiv
// gesetzt werden kann
//		if ($spieler[2013] === '*') {
			// Spieler ist inaktiv
//			$ids['person'][$s_key] = NULL;
//			continue;
//		}
		if (!strstr($spieler[2000], ',')) {
			// Spieler in anderer Form als Vorname, Nachname, möglicherweise NN
			wrap_error(sprintf('Person %s (Key: %s) wurde nicht hinzugefügt (nicht im Format Nachname,Vorname)', 
				$spieler[2000], $s_key), E_USER_WARNING
			);
			$ids['person'][$s_key] = NULL;
			continue;
		}
	
		$values = [];
		$person_id = false;
		if (array_key_exists(2038, $spieler) AND $spieler[2038] AND empty($event['swisschess']['ignore_ids'])) {
			// 2038 Spieler Info4
			parse_str($spieler[2038], $infos);
			if (array_key_exists('person_id', $infos)) {
				$person_id = $infos['person_id'];
				$sql = 'SELECT person_id
					FROM personen
					WHERE person_id = %d';
				$sql = sprintf($sql, $person_id);
				$person_id = wrap_db_fetch($sql, '', 'single value');
			}
		}
		if (!$person_id) {
			// Existiert Person? PKZ 2034 -- FIDE-ID 2033 -- ZPS-Mgl-Nr. 2010-2011
			$sql = 'SELECT DISTINCT person_id
				FROM contacts_identifiers pk
				LEFT JOIN personen USING (contact_id)
				WHERE (pk.identifier = "%s" AND identifier_category_id = %d)
				OR (pk.identifier = "%s" AND identifier_category_id = %d)
				OR (pk.identifier = "%s-%s" AND identifier_category_id = %d)
				OR (pk.identifier = "%s-%s" AND identifier_category_id = %d)
				OR (pk.identifier LIKE "%s-%%" AND identifier_category_id = %d
					AND (CONCAT(last_name, ",", first_name) = "%s"
						OR CONCAT(last_name, ", ", first_name) = "%s")
					AND (YEAR(geburtsdatum) = %d OR ISNULL(geburtsdatum)))
			';
			$sql = sprintf($sql
				, !empty($spieler[2034]) ? wrap_db_escape($spieler[2034]) : 0
				, wrap_category_id('kennungen/pkz')
				, $spieler[2033] ? $spieler[2033] : 0
				, wrap_category_id('kennungen/fide-id')

				, $spieler[2010] ? wrap_db_escape($spieler[2010]) : 0
				, $spieler[2011] ? wrap_db_escape($spieler[2011]) : 0
				, wrap_category_id('kennungen/zps')

				, $spieler[2010] ? wrap_db_escape($spieler[2010]) : 0
				, substr($spieler[2011], 0, 1) === '0' ? wrap_db_escape(substr($spieler[2011], 1)) : 0
				, wrap_category_id('kennungen/zps')

				, $spieler[2010] ? wrap_db_escape($spieler[2010]) : 0
				, wrap_category_id('kennungen/zps')

				, wrap_db_escape($spieler[2000])
				, wrap_db_escape($spieler[2000])
				, trim(substr($spieler[2008], 0, 4))
			);
			$person_id = wrap_db_fetch($sql, '', 'single value');
		}
		if (!$person_id AND empty($import['use_person_id'])) {
			// Wurde Spieler schon mal importiert?
			// Wichtig bei Spielern ohne ZPS, die werden sonst immer wieder
			// importiert.
			$sql = 'SELECT person_id
				FROM teilnahmen
				WHERE event_id = %d AND usergroup_id = %d
				AND fremdschluessel = "%s"';
			$sql = sprintf($sql,
				$event['event_id'],
				wrap_id('usergroups', 'spieler'),
				hexdec($spieler[2020])
			);
			$person_id = wrap_db_fetch($sql, '', 'single value');
		}
		$name = explode(',', $spieler[2000]);
		if (!$person_id) {
			// nur für dieses Turnier: existiert eine Spielerin oder ein Spieler
			// mit diesem Namen schon (darüberhinaus machen wir das nicht, da
			// es Spieler mit demselben Namen geben kann, die nicht identisch
			// sind ;-))
			// SUBOPTIMAL, vorgeschlagen wird, die SWT-Datei vom System schreiben
			// zu lassen
			$sql = 'SELECT person_id
				FROM teilnahmen
				WHERE event_id = %d AND usergroup_id = %d
				AND t_vorname = "%s" AND t_nachname = "%s"';
			$sql = sprintf($sql,
				$event['event_id'],
				wrap_id('usergroups', 'spieler'),
				!empty($name[1]) ? trim($name[1]) : '',
				trim($name[0])
			);
			$person_id = wrap_db_fetch($sql, '', 'single value');
		}
		if (!$person_id) {
			$person = [];
			$person['first_name'] = !empty($name[1]) ? trim($name[1]) : '';
			$person['last_name'] = trim($name[0]);
			$person['geburtsdatum'] = trim(substr($spieler[2008], 0, 4));
			$person['geschlecht'] = (strtolower($spieler[2013]) === 'w') ? 'weiblich' : 'männlich';
			list($person_id, $contact_id) = my_person_add($person);

			$kennungen = [];
			if ($spieler[2011] AND $spieler[2011] !== '***') {
				$kennungen['zps'] = $spieler[2010].'-'.sprintf('%03d', $spieler[2011]);
			}
			$kennungen['fide-id'] = $spieler[2033];
			// alte SWT-Versionen konnten keine PKZ speichern
			$kennungen['pkz'] = !empty($spieler[2034]) ? $spieler[2034] : '';
			my_person_kennungen_speichern($person_id, $kennungen);

			if (!isset($ids['t']['Personen']['successful_insert'])) {
				$ids['t']['Personen']['successful_insert'] = 0;
			}
			$ids['t']['Personen']['successful_insert']++;
		}
		$ids['person'][$s_key] = $person_id;
	}
	return $ids;
}

/**
 * Import der Aufstellungen
 *
 * @param array $event
 * @param array $spielerliste = $tournament['out']['Spieler']
 * @param array $ids
 * @return array $ids
 */
function mod_tournaments_make_swtimport_teilnahmen($event, $spielerliste, $ids) {
	if ($event['turnierform'] !== 'e') {
		$sql = 'SELECT IF(gastspieler = "ja", 1, 0)
			FROM turniere WHERE event_id = %d';
		$sql = sprintf($sql, $event['event_id']);
		$gastspieler = wrap_db_fetch($sql, '', 'single value');

		$ids['spieler_in_teams'] = [];
	}
	
	foreach ($spielerliste as $s_key => $spieler) {
		$values = [];
		if ($event['turnierform'] !== 'e') {
			// wird immer gespeichert, auch für inaktive Spieler, daher
			// ganz vorne!
			$ids['spieler_in_teams'][hexdec($spieler[2020])] = $spieler[2016];
		}
		$person_id = $ids['person'][$s_key];
		if (is_null($person_id)) continue;
		if ($event['turnierform'] !== 'e') {
			// Eine Spielerin darf in mehreren Teams pro Turnier gemeldet sein
			// (2. Mannschaft)
			$sql = 'SELECT teilnahme_id
				FROM teilnahmen
				WHERE person_id = %d AND event_id = %d
				AND usergroup_id = %d AND team_id = %d';
			$sql = sprintf($sql,
				$person_id, $event['event_id'],
				wrap_id('usergroups', 'spieler'),
				$ids['team_dec'][$spieler[2016]]
			);
		} else {
			$sql = 'SELECT teilnahme_id
				FROM teilnahmen
				WHERE person_id = %d AND event_id = %d
				AND usergroup_id = %d';
			$sql = sprintf($sql,
				$person_id, $event['event_id'], wrap_id('usergroups', 'spieler'));
		}
		$teilnahme_id = wrap_db_fetch($sql, '', 'single value');
		if ($teilnahme_id) {
			$values['action'] = 'update';
			$values['POST']['teilnahme_id'] = $teilnahme_id;
		} else {
			$values['action'] = 'insert';
		}
		$spielername = explode(',', $spieler[2000]);
		$values['POST']['usergroup_id'] = wrap_id('usergroups', 'spieler');
		$values['POST']['event_id'] = $event['event_id'];
		if ($event['turnierform'] !== 'e') {
			$values['POST']['team_id'] = $ids['team_dec'][$spieler[2016]];
		}
		$values['POST']['person_id'] = $person_id;
		if (!empty($spielername[1])) {
			$values['POST']['t_vorname'] = $spielername[1];
		}
		$values['POST']['t_nachname'] = $spielername[0];
		$verein = [];
		if ($spieler[2010])
			$verein = mod_tournaments_make_swtimport_verein_zps($spieler[2010]);
		if (!$verein) {
			$verein = mod_tournaments_make_swtimport_verein_name($spieler[2001]);
		}
		if ($verein) {
			$values['POST']['verein_org_id'] = $verein['org_id'];
		} elseif (!$spieler[2010]) {
			// Notice senden, falls keine ZPS-Nr. für Verein gefunden wird;
			// kann korrekt sein bspw. bei Schulmannschaften
			// @todo Error abhängig machen von Turnierform (nur DSB-Mitglieder, alle)
			wrap_error(sprintf('Für Person %s (Key: %s) wurde kein Verein mit ZPS-Nr. angegeben.',
				$spieler[2000], $s_key), E_USER_NOTICE
			);
		}
		// @todo ggf. Team-No. löschen
		$values['POST']['t_verein'] = $spieler[2001];
		// @todo testen, ggf. nur am Anfang (SAbt) bzw. Ende (e.V., SAbt) löschen
		$replacements = [
			' e.V., Abt. Schach', ' e.V.', ' e.V', ' eV', 'SAbt ', ' Abt. Schach',
			' e. V.', 'Sabt', 'SABT'
		];
		foreach ($replacements as $replace) {
			if (!strstr($values['POST']['t_verein'], $replace)) continue;
			$values['POST']['t_verein'] = str_replace($replace, '', $values['POST']['t_verein']);
		}
		$values['POST']['t_dwz'] = $spieler[2004];
		$values['POST']['t_elo'] = $spieler[2003];
		$values['POST']['t_fidetitel'] = mod_tournaments_make_swtimport_titel($spieler[2002]);
		if ($event['turnierform'] !== 'e' AND $gastspieler) {
			$values['POST']['gastspieler'] = ($spieler[2006] === 'G' ? 'ja' : 'nein');
		}
		if ($event['turnierform'] !== 'e') {
			$values['POST']['brett_no'] = $spieler[2017];
		} else {
			$values['POST']['setzliste_no'] = hexdec($spieler[2020]);
		}
		$values['POST']['spielberechtigt'] = 'ja';
		$values['POST']['teilnahme_status'] = 'Teilnehmer';
		$values['POST']['fremdschluessel'] = hexdec($spieler[2020]);
		$values['ids'] = [
			'usergroup_id', 'event_id', 'team_id', 'person_id', 'verein_org_id'
		];
		$ops = zzform_multi('teilnahmen', $values);
		if (!$ops['id']) {
			wrap_error(sprintf('Aufstellung %s konnte nicht hinzugefügt werden.', $s_key));
		}
		$ids['teilnahmen'][] = $ops['id'];
		if (!isset($ids['t']['Teilnahmen (Spieler)'][$ops['result']])) {
			$ids['t']['Teilnahmen (Spieler)'][$ops['result']] = 0;
		}
		$ids['t']['Teilnahmen (Spieler)'][$ops['result']]++;
	}
	// @todo weitere Spieler, die nicht 
	return $ids;
}

/**
 * Prüfung FIDE-Titel auf Plausibilität
 *
 * @param string $titel
 * @return string
 */
function mod_tournaments_make_swtimport_titel($titel) {
	$valid_titles = [
		'GM', 'IM', 'FM', 'CM', 'WGM', 'WIM', 'WFM', 'WCM'
	];
	if (in_array($titel, $valid_titles)) return $titel;
	return '';
}

/**
 * Import der Teampaarungen
 *
 * @param array $event
 * @param array $tournament = $tournament['out']
 * @param array $ids
 * @return array $ids
 */
function mod_tournaments_make_swtimport_paarungen($event, $tournament, $ids) {
	$ids['paarungen'] = [];
	foreach ($tournament['Mannschaftspaarungen'] as $team_hex_id => $team) {
		foreach ($team as $runde => $paarung) {
			// maximal soviele Runden wie ausgelost
			if ($runde > $tournament[3]) continue;
			if ($paarung[3001] === '3001-0') {
				$team_dec_id = $ids['team_hex2dec'][$team_hex_id];
				if (in_array($ids['team_hex2dec'][$team_hex_id], $ids['team_spielfrei'])) {
					// keine Fehlermeldung bei spielfrei und keiner Ansetzung
					continue;
				}
				wrap_error(sprintf('Keine Ansetzung für Team %s in Runde %d', $tournament['Teams'][$team_hex_id]['1000'], $runde), E_USER_NOTICE);
				continue;
			}
			if ($event['wertung_spielfrei'] === 'keine') {
				// kein Import von 0:0-Paarungen
				if ($paarung['Gegner_lang'] === 'spielfrei') continue;
				if (in_array($ids['team_hex2dec'][$team_hex_id], $ids['team_spielfrei'])) continue;
			}
			$values = [];
			$sql = 'SELECT paarung_id
				FROM paarungen
				WHERE event_id = %d AND runde_no = %d AND tisch_no = %d';
			$sql = sprintf($sql,
				$event['event_id'], $runde, $paarung[3005]);
			$paarung_id = wrap_db_fetch($sql, '', 'single value');
			if ($paarung_id) {
				$values['action'] = 'update';
				$values['POST']['paarung_id'] = $paarung_id;
			} else {
				$values['action'] = 'insert';
			}
			$values['POST']['event_id'] = $event['event_id'];
			$values['POST']['runde_no'] = $runde;
			$values['POST']['tisch_no'] = $paarung[3005];
			switch ($paarung[3001]) {
			case '3001-1': // Heim
				$values['POST']['auswaerts_team_id'] = $ids['team_hex'][$paarung[3002]];
			case '3001-3': // Heim gegen spielfrei
				$values['POST']['heim_team_id'] = $ids['team_hex'][$team_hex_id];
				$ort = 'heim';
				break;
			case '3001-2':
				$values['POST']['heim_team_id'] = $ids['team_hex'][$paarung[3002]];
			case '3001-4':
				$values['POST']['auswaerts_team_id'] = $ids['team_hex'][$team_hex_id];
				$ort = 'auswaerts';
				break;
			default:
				wrap_error(
					sprintf(
						'Paarung %s/ Runde %s konnte nicht importiert werden: Angabe Heim/Auswärts fehlt',
						$tournament['Teams'][$team_hex_id]['1000'], $runde
					)
				);
				continue 2;
			}
			$values['ids'] = [
				'paarung_id', 'event_id', 'auswaerts_team_id', 'heim_team_id'
			];
			$ops = zzform_multi('paarungen', $values);
			if (!$ops['id']) {
				wrap_error(sprintf(
					'Paarung %s/ Runde %s konnte nicht hinzugefügt werden.', $team_hex_id, $runde
				));
			}
			// Paarung-ID speichern für Partien
			$ids['paarungen'][$ids['team_hex2dec'][$team_hex_id]][$runde] = [
				'id' => $ops['id'],
				'ort' => $ort,
				'tisch' => $values['POST']['tisch_no']
			];
			if (!isset($ids['t']['Teampaarungen'][$ops['result']])) {
				$ids['t']['Teampaarungen'][$ops['result']] = 0;
			}
			$ids['t']['Teampaarungen'][$ops['result']]++;
		}
	}
	return $ids;
}

/**
 * Import der Partien
 *
 * @param array $event
 * @param array $tournament = $tournament['out']
 * @param array $ids
 * @return array $ids
 */
function mod_tournaments_make_swtimport_partien($event, $tournament, $ids) {
	$ids['partien'] = [];

	// Korrektur schwarz/weiß-Bretter
	// @todo stimmt nicht ganz, 78-0 ist Zufall, keine Ahnung wie man damit
	// arbeitet
	if ($event['turnierform'] !== 'e') {
		if (in_array($tournament[79], ['78-2', '78-3']))  {
			$erstes_heim_brett = 'schwarz';
		} else {
			$erstes_heim_brett = 'weiß';
		}
		$brett_key = 4006;
	} else {
		$brett_key = 4004;
	}

	$partien_provisorisch = [];
	$partien = [];
	$brett_reduzieren = [];
	// Einzelne Sichten auf eine Partie zusammenfügen
	foreach ($tournament['Einzelpaarungen'] as $s_key => $paarungen) {
		foreach ($paarungen as $runde => $paarung) {
			if (in_array($s_key, $ids['person_spielfrei'])) continue;
			if (in_array($paarung[4001], $ids['person_spielfrei'])) continue;
			// maximal soviele Runden wie ausgelost
			if ($runde > $tournament[3]) continue;
			if ($event['turnierform'] !== 'e') {
				if (!isset($ids['spieler_in_teams'][hexdec($s_key)])) {
					continue;
				}
				$team_id = $ids['spieler_in_teams'][hexdec($s_key)];
				if (!isset($ids['paarungen'][$team_id][$runde]['id'])) {
					continue;
				}
				$paarung['paarung_id'] = $ids['paarungen'][$team_id][$runde]['id'];
				if (in_array($paarung[4005], ['3006-2', '3006-3']) AND $paarung[4006]) {
					// nicht eingesetzt, also folgende Bretter falsch
					if (!isset($brett_reduzieren[$paarung['paarung_id']][$team_id])) {
						$brett_reduzieren[$paarung['paarung_id']][$team_id] = [];
					}
					$brett_reduzieren[$paarung['paarung_id']][$team_id][] = $paarung[4006];
				}
			}
			if ($paarung[4000] === '4000-0') continue;
			if ($paarung[4001] === '00') continue;
			$paarung['s_key'] = $s_key;
			$paarung['runde_no'] = $runde;
			if ($event['turnierform'] === 'e') {
				$p_key = $runde * 10000 * 10000;
				$p_key += $paarung[$brett_key];
				$partien[$p_key][] = $paarung;
			} else {
				$partien_provisorisch[] = $paarung;
			}
		}
	}
	// Mannschaftsturniere:
	// Doppelter Durchlauf, da man bei SwissChess lustige Sachen machen kann:
	// Bretter tauschen und Personen nicht aufstellen, so dass die Brettnummer
	// falsch ist
	foreach ($partien_provisorisch as $paarung) {
		$runde = $paarung['runde_no'];
		$p_key = $runde * 10000 * 10000;
		$team_id = $ids['spieler_in_teams'][hexdec($paarung['s_key'])];
		$p_key += $ids['paarungen'][$team_id][$runde]['tisch'] * 10000;
		$paarung['paarung_id'] = $ids['paarungen'][$team_id][$runde]['id'];
		$paarung['team_id'] = $team_id;
		$paarung['ort'] = $ids['paarungen'][$team_id][$runde]['ort'];
		// Brett korrigieren
		if (!empty($brett_reduzieren[$paarung['paarung_id']][$team_id])) {
			$decrease = 0;
			foreach ($brett_reduzieren[$paarung['paarung_id']][$team_id] as $brett) {
				if ($brett < $paarung[4006]) $decrease++;
			}
			$paarung[4006] -= $decrease;
		}
		$p_key += $paarung[$brett_key];
		$partien[$p_key][] = $paarung;
	}
	if (!$partien) return $ids;
	ksort($partien);

	// Check ob jede Partie auch den korrekten Gegner hat
	$last_p_key = 0;
	foreach ($partien as $p_key => $partie) {
		if (count($partie) < 2) continue;
		if (count($partie) > 2) {
			wrap_error(sprintf('SWT-Import: Zuviele Spieler für eine Partie. Daten: %s', json_encode($partie)), E_USER_ERROR);
		}
		if ($partie[0]['s_key'] != $partie[1][4001]) { // nicht !==
			// Fehlerhafte Paarung, ggf. Brettnummer falsch
			if (!$last_p_key) {
				wrap_error(sprintf('SWT-Import: Partiegegner passen nicht zusammen. Daten: %s', json_encode($partie)), E_USER_ERROR);
			}
			if (count($partien[$last_p_key]) !== 1) {
				wrap_error(sprintf('SWT-Import: Partiegegner passen nicht zusammen. Brettfehler ausgeschlossen. Daten: %s', json_encode($partie)), E_USER_ERROR);
			}
			if ($partien[$last_p_key][0]['s_key'] === $partie[1][4001]) {
				$partien[$last_p_key][1] = $partie[1];
				$partien[$last_p_key][1][$brett_key]--;
				unset($partien[$p_key][1]);
				wrap_error('SWT-Import: Brett falsch. Daten: %s', json_encode($partie));
			} elseif ($partien[$last_p_key][0]['s_key'] === $partie[0][4001]) {
				$partien[$last_p_key][1] = $partie[0];
				$partien[$last_p_key][1][$brett_key]--;
				unset($partien[$p_key][0]);
				wrap_error('SWT-Import: Brett falsch. Daten: %s', json_encode($partie));
			} else {
				wrap_error('SWT-Import: Partiegegner passen nicht zusammen. Unbekannter Fehler. Daten: %s', json_encode($partie));
			}
		}
		$last_p_key = $p_key;
	}
	
	foreach ($partien as $p_key => $partie) {
		$runde_no = $partie[0]['runde_no'];
		if ($event['turnierform'] !== 'e') {
			$brett_no = $partie[0][4006];
			if (!empty($partie[1][4006]) AND $brett_no !== $partie[1][4006]) {
				wrap_error(sprintf('SWT-Import: Brettnummern stimmen nicht überein (%d != %d)', $brett_no, $partie[1][4006]), E_USER_ERROR);
			}
			$paarung_id = $partie[0]['paarung_id'];
			if (!empty($partie[1]['paarung_id']) AND $paarung_id != $partie[1]['paarung_id']) { // !== geht nicht
				wrap_error(sprintf('SWT-Import: Paarung-IDs stimmen nicht überein (%d != %d)', $paarung_id, $partie[1]['paarung_id']), E_USER_ERROR);
			}
			$sql = 'SELECT partie_id
				FROM partien
				WHERE paarung_id = %d AND brett_no = %d';
			$sql = sprintf($sql, $paarung_id, $brett_no);
		} else {
			// Brett hier Tisch
			$brett_no = $partie[0][4004];
			$sql = 'SELECT partie_id
				FROM partien
				WHERE event_id = %d AND runde_no = %d AND brett_no = %d';
			$sql = sprintf($sql, $event['event_id'], $runde_no, $brett_no);
		}
		$partie_id = wrap_db_fetch($sql, '', 'single value');
		if (!$partie_id) $partie_id = '';
		$values = [];
		if ($partie_id) {
			$values['action'] = 'update';
			$values['POST']['partie_id'] = $partie_id;
		} else {
			$values['action'] = 'insert';
		}
		$values['POST']['event_id'] = $event['event_id'];
		$values['POST']['runde_no'] = $runde_no;
		$values['POST']['brett_no'] = $brett_no;
		if ($event['turnierform'] !== 'e') {
			$values['POST']['paarung_id'] = $paarung_id;
		}
		// Partiestatus
		switch ($partie[0][4005]) {
		case '3006-1':
		case '3006-4':
			$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/kampflos');
			break;
		case '3006-5':
		case '3006-6':
			$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/haengepartie');
			break;
		default:
			$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/normal');
			break;
		}

		foreach ($partie as $spieler) {
			switch ($spieler[4000]) {
				case '4000-1':
				case '4000-3':
					$farbe = 'weiß'; break;
				case '4000-2':
				case '4000-4':
					$farbe = 'schwarz'; break;
				default:
					$farbe = ''; break;
			}
			if ($event['turnierform'] !== 'e') {
				// Lustig lustig, SwissChess speichert die Info schwarz/weiß
				// woanders
				if ($spieler['ort'] === 'heim') {
					$values['POST']['heim_wertung'] = cms_swtparser_ergebnis($spieler[4003]);
					if ($brett_no & 1) {
						if ($erstes_heim_brett === 'weiß') $farbe = 'weiß';
						else $farbe = 'schwarz';
					} else {
						if ($erstes_heim_brett === 'weiß') $farbe = 'schwarz';
						else $farbe = 'weiß';
					}
					$values['POST']['heim_spieler_farbe'] = $farbe;
				} else {
					$values['POST']['auswaerts_wertung'] = cms_swtparser_ergebnis($spieler[4003]);
					if ($brett_no & 1) {
						if ($erstes_heim_brett === 'weiß') $farbe = 'schwarz';
						else $farbe = 'weiß';
					} else {
						if ($erstes_heim_brett === 'weiß') $farbe = 'weiß';
						else $farbe = 'schwarz';
					}
					if (!array_key_exists('heim_spieler_farbe', $values['POST'])) {
						if ($farbe === 'schwarz') $values['POST']['heim_spieler_farbe'] = 'weiß';
						else $values['POST']['heim_spieler_farbe'] = 'schwarz';
					}
				}
			}
			if ($farbe === 'weiß') {
				$values['POST']['weiss_person_id'] = $ids['person'][$spieler['s_key']];
				$values['POST']['weiss_ergebnis'] = cms_swtparser_ergebnis($spieler[4002]);
				if ($values['POST']['weiss_ergebnis'] === 1
					AND $values['POST']['partiestatus_category_id'] === wrap_category_id('partiestatus/kampflos')) {
					// kampflose Ergebnisse gegen NN, ggf. speichern
					$values['POST']['schwarz_ergebnis'] = 0;
					if ($event['turnierform'] !== 'e') {
						if ($spieler['ort'] === 'heim') {
							$values['POST']['auswaerts_wertung'] = 0;
						} else {
							$values['POST']['heim_wertung'] = 0;
						}
					}
				}
				// Partie noch nicht abgeschlossen?
				if (is_null($values['POST']['weiss_ergebnis'])) {
					$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/laufend');
				}
			} else {
				$values['POST']['schwarz_person_id'] = $ids['person'][$spieler['s_key']];
				$values['POST']['schwarz_ergebnis'] = cms_swtparser_ergebnis($spieler[4002]);
				if ($values['POST']['schwarz_ergebnis'] === 1
					AND $values['POST']['partiestatus_category_id'] === wrap_category_id('partiestatus/kampflos')) {
					// kampflose Ergebnisse gegen NN, ggf. speichern
					$values['POST']['weiss_ergebnis'] = 0;
					if ($event['turnierform'] !== 'e') {
						if ($spieler['ort'] === 'heim') {
							$values['POST']['auswaerts_wertung'] = 0;
						} else {
							$values['POST']['heim_wertung'] = 0;
						}
					}
				}
				// Partie noch nicht abgeschlossen?
				if (is_null($values['POST']['schwarz_ergebnis'])) {
					$values['POST']['partiestatus_category_id'] = wrap_category_id('partiestatus/laufend');
				}
			}
		}
		if (isset($values['POST']['schwarz_ergebnis'])
			OR isset($values['POST']['weiss_ergebnis'])) {
			// Keine Ergebnisse aus PGNs dürfen SWT-Ergebnisse überschreiben
			$values['POST']['block_ergebnis_aus_pgn'] = 'ja';
		}

		$values['ids'] = [
			'schwarz_person_id', 'weiss_person_id', 'paarung_id', 'partie_id',
			'event_id', 'partiestatus_category_id'
		];
		$ops = zzform_multi('partien', $values);
		if (!$ops['id']) {
			if ($values['action'] === 'insert') {
				wrap_error('Partie konnte nicht hinzugefügt werden: ', implode("\n", $ops['error']));
			} else {
				wrap_error(sprintf('Partie %s konnte nicht aktualisiert werden: ', $partie_id).implode("\n", $ops['error']));
			}
		}
		$ids['partien'][] = $ops['id'];
		if (!isset($ids['t']['Partien'][$ops['result']])) {
			$ids['t']['Partien'][$ops['result']] = 0;
		}
		$ids['t']['Partien'][$ops['result']]++;
		
	}

	return $ids;
}

/**
 * Überzählige Partien nach SWT-Import löschen
 *
 * @param int $event_id
 * @param array $partie_ids
 * @return void
 */
function mod_tournaments_make_swtimport_partien_loeschen($event_id, $partie_ids) {
	if (!$partie_ids) return;

	$sql = 'SELECT partie_id FROM partien
		WHERE event_id = %d
		AND partie_id NOT IN (%s)';
	$sql = sprintf($sql, $event_id, implode(',', $partie_ids));
	$partie_ids = wrap_db_fetch($sql, 'partie_id', 'single value');
	if (!$partie_ids) return;

	$values = [
		'action' => 'delete'
	];
	foreach ($partie_ids as $partie_id) {
		$values['POST']['partie_id'] = $partie_id;
		$ops = zzform_multi('partien', $values);
		if (!$ops['id']) {
			wrap_error(sprintf('Partie %s konnte nicht gelöscht werden: ', $partie_id).implode("\n", $ops['error']));
		}
	}
}

/**
 * Liest Verein über ZPS-No. aus Team oder aus Aufstellungen der Spieler aus
 *
 * @param array $team
 * @param array $spielerliste
 * @return array
 */
function mod_tournaments_make_swtimport_verein($team, $spielerliste) {
	if ($team[1008]) {
		// Steht es direkt drin?
		$zps = $team[1008];
	} else {
		// Steht es bei den Spielern? (Gastspielerinnen beachten)
		$zps_codes = [];
		foreach ($spielerliste as $s_key => $spieler) {
			if ($spieler[2016] !== $team[1012]) continue;
			if (!empty($zps_codes[$spieler[2010]])) {
				$zps_codes[$spieler[2010]]++;
			} else {
				$zps_codes[$spieler[2010]] = 1;
			}
		}
		arsort($zps_codes);
		$zps_codes = array_flip($zps_codes);
		$zps = reset($zps_codes);
	}
	return mod_tournaments_make_swtimport_verein_zps($zps);
}

/**
 * Verein nach ZPS auslesen
 *
 * @param string $zps
 * @return array
 *		int 'org_id', string 'organisation'
 */
function mod_tournaments_make_swtimport_verein_zps($zps) {
	$sql = 'SELECT org_id, organisation
		FROM organisationen
		LEFT JOIN organisationen_kennungen ok
			USING (org_id)
		WHERE ok.identifier = "%s"
		AND current = "yes"';
	$sql = sprintf($sql, wrap_db_escape($zps));
	$verein = wrap_db_fetch($sql);
	if (!$verein) {
		$sql = 'SELECT org_id, organisation
			FROM organisationen
			LEFT JOIN organisationen_kennungen ok
				USING (org_id)
			WHERE ok.identifier = "%s"';
		$sql = sprintf($sql, wrap_db_escape($zps));
		$verein = wrap_db_fetch($sql);
		if (!$verein) {
			// Kann auch leer bleiben, z. B. bei DLMs
			wrap_error(sprintf(
				'SWT-Import: Konnte Verein mit ZPS-Code %s nicht finden.', $zps
			), E_USER_NOTICE);
		}
	}
	return $verein;
}

/**
 * Verein nach Vereinsname (= Mannschaft) auslesen
 *
 * @param string $verein
 * @return array
 */
function mod_tournaments_make_swtimport_verein_name($verein) {
	$sql = 'SELECT org_id, organisation
		FROM organisationen
		WHERE organisation LIKE "%%%s%%"';
	$sql = sprintf($sql, wrap_db_escape($verein));
	$verein = wrap_db_fetch($sql);
	if (!$verein) return false;
	return $verein;
}

/**
 * Versucht, Mannschaftsnr. über Teamnamen herauszubekommen
 *
 * @param string $teamname
 * @param array $verein
 * @return string
 */
function mod_tournaments_make_swtimport_teamno($teamname, $verein) {
	if ($teamname === $verein['organisation']) return '';
	$teamname = explode(' ', $teamname);
	$vereinsname = explode(' ', $verein['organisation']);
	$team_no = end($teamname);
	if (!is_numeric($team_no)) return '';
	if ($team_no === end($vereinsname)) return '';
	if (strlen($team_no) > 3) return ''; // zu lang = Vereinsjahr!
	return $team_no;
}

/**
 * Liest für Synchronisation Fremdschlüssel entweder aus Datenbank oder
 * aus SWT-Datei aus
 *
 * @param array $team
 * @param array $spielerliste
 * @return string
 */
function mod_tournaments_make_swtimport_team_id($team, $spielerliste, $event_id) {
	// 1. Fremdschlüssel aus Datenbank in SWT?
	$info4 = false;
	if ($team[1046]) {
		// 1a. Team-ID in SWT?
		// team_id direkt pro Team gespeichert (SWT Writer)
		$info4 = trim($team[1046]);
	} else {
		// 1b. Team-ID bei Spielern?
		// team_id bei Spielern gespeichert (.lst-Export)
		foreach ($spielerliste as $s_key => $spieler) {
			if ($spieler[2016] !== $team[1012]) continue;
			if ($spieler[2038]) {
				$info4 = trim($spieler[2038]);
				break;
			}
		}
	}
	if ($info4) {
		parse_str($info4, $infos);
		if (empty($infos['team_id'])) return false;
		$sql = 'SELECT team_id FROM teams WHERE event_id = %d AND team_id = %d';
		$sql = sprintf($sql, $event_id, $infos['team_id']);
		$team_id = wrap_db_fetch($sql, '', 'single value');
		if ($team_id) return $team_id;
		return false;
	}
}

function cms_swtparser_ergebnis($ergebnis) {
	switch ($ergebnis) {
	case '4002-1':
	case '4002-5':
	case '4002-9':
	case '4002-13':
	case '3004-4':
	case '3004-5':
	case '3004-6':
	case '3004-7':
		return 0;
	case '4002-2':
	case '4002-6':
	case '4002-10':
	case '4002-14':
	case '3004-8':
	case '3004-9':
	case '3004-10':
	case '3004-11':
		return 0.5;
	case '4002-3':
	case '4002-7':
	case '4002-11':
	case '4002-15':
	case '3004-12':
	case '3004-13':
	case '3004-14':
	case '3004-15':
		return 1;
	}
	return NULL;
}

/**
 * Prüfe, ob Dateneingabe plausibel, insbesondere Info4
 *
 * @param array $tournament
 * @param string $form
 * @return array
 * 	array $tournament
 *	array $import
 */
function mod_tournaments_make_swtimport_integrity($tournament, $form) {
	// Info4 gibt es in alten Versionen nicht, prüfen
	$first_player = reset($tournament['out']['Spieler']);
	if (!array_key_exists(2038, $tournament['out']['Spieler'])) {
		return [$tournament, []];
	}
	$import = [];
	if ($form === 'mannschaftsturnier') {
		list($tournament['out']['Teams'], $import_settings)
			= mod_tournaments_make_swtimport_duplicate_id($tournament['out']['Teams'], 'team_id');
		$import = array_merge($import, $import_settings);
	}
	list($tournament['out']['Spieler'], $import_settings)
		= mod_tournaments_make_swtimport_duplicate_id($tournament['out']['Spieler'], 'person_id');
	$import = array_merge($import, $import_settings);
	return [$tournament, $import];
}

/**
 * Prüft für Personen oder Teams auf Info4-Feld
 *
 * @param array $data
 * @param string $field
 * @return array
 */
function mod_tournaments_make_swtimport_duplicate_id($data, $field) {
	$existing_ids = [];
	$error_ids = [];
	$import = [];
	switch ($field) {
		case 'person_id': $info_key = 2038; break;
		case 'team_id': $info_key = 1046; break;
		default: $info_key = ''; break;
	}
	
	foreach ($data as $key => $record) {
		parse_str($record[$info_key], $info4);
		if (empty($info4[$field])) continue;
		if (in_array($info4[$field], $existing_ids)) {
			$error_ids[] = $info4[$field];
		} else {
			$existing_ids[] = $info4[$field];
		}
	}
	if ($error_ids) {
		foreach ($data as $key => $record) {
			parse_str($record[$info_key], $info4);
			if (empty($info4[$field])) continue;
			if (!in_array($info4[$field], $error_ids)) continue;
			unset($info4[$field]);
			$data[$key][$info_key] = http_build_query($info4);
			$import['writer'] = true;
		}
	}
	if ($existing_ids) {
		$import['use_'.$field] = true;
	}
	return [$data, $import];
}
