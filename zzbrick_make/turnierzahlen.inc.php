<?php

/**
 * Zugzwang Project
 * make script to update tournament ratings before tournament start
 *
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Aktualisierung der Turnierwertungszahlen kurz vor Turnierbeginn
 * Alte Zahlen werden als Meldezahlen in m_dwz und m_elo gespeichert
 *
 * @param array $vars
 * @return array $page
 */
function mod_tournaments_make_turnierzahlen($vars) {
	global $zz_conf;

	$sql = 'SELECT event_id, tournament_id
			, IF(NOT ISNULL(events.date_end),
				IF(events.date_end < CURDATE(), 1, NULL),
				IF(events.date_begin < CURDATE(), 1, NULL)
			) AS event_over
			, YEAR(date_begin) AS year
			, event, identifier
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape(implode('/', $vars)));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	$data['abweichungen'] = [];
	$data['fehler'] = [];
	$data['event'] = $event['event'];
	$data['year'] = $event['year'];
	$data['testlauf'] = true;
	if (!empty($_POST['update'])) $data['testlauf'] = false;

	$page['breadcrumbs'][] = '<a href="/intern/termine/">Termine</a>';
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%d/">%d</a>',
		$event['year'], $event['year']
	);
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%s/">%s</a>',
		$event['identifier'], $event['event']
	);
	$page['breadcrumbs'][] = 'Turnierzahlen';
	$page['title'] = sprintf('Aktualisierung der Wertungszahlen für %s %s', $event['event'], $event['year']);
	$page['dont_show_h1'] = true;

	if ($event['event_over']) {
		$data['event_over'] = true;
		$page['text'] = wrap_template('turnierzahlen', $data);
		return $page;
	}

	$sql = 'SELECT DISTINCT m_dwz, m_elo
		FROM teilnahmen
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$meldezahlen = wrap_db_fetch($sql);
	if (!$meldezahlen) {
		// $meldezahlen ergibt exakt einen Datensatz zurück, wenn es
		// gemeldete Teilnehmer gibt, aber keiner davon einen Wert in
		// m_dwz oder m_elo stehen hat
		$data['meldezahlen_gespeichert'] = true;
	} elseif ($meldezahlen['m_dwz'] OR $meldezahlen['m_elo']) {
		// Daneben kann natürlich noch exakt eine m_dwz oder m_elo gespeichert sein
		$data['meldezahlen_gespeichert'] = true;
	} else {
		$data['meldezahlen_gespeichert'] = false;
	}
	
	$sql = 'SELECT teilnahme_id, teilnahmen.person_id, t_dwz, t_elo
			, contacts_identifiers.identifier AS zps_code
			, contact AS person
			, CONCAT(last_name, ",", first_name) AS dwz_person
			, anmerkung
		FROM teilnahmen
		LEFT JOIN personen USING (person_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN contacts_identifiers
			ON contacts.contact_id = contacts_identifiers.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		WHERE event_id = %d
		AND usergroup_id = %d
	';
	$sql = sprintf($sql,
		wrap_category_id('kennungen/zps'),
		$event['event_id'],
		wrap_id('usergroups', 'spieler')
	);
	$teilnahmen = wrap_db_fetch($sql, 'teilnahme_id');
	$zps_codes = [];
	foreach ($teilnahmen as $teilnahme) {
		$zps_codes[] = $teilnahme['zps_code'];
	}
	
	$sql = 'SELECT ZPS, Mgl_Nr, DWZ, FIDE_Elo
			, CONCAT(ZPS, "-", Mgl_Nr) AS zps_code, Spielername
		FROM dwz_spieler
		WHERE CONCAT(ZPS, "-", Mgl_Nr) IN ("%s")';
	$sql = sprintf($sql, implode('","', $zps_codes));
	$wertungszahlen = wrap_db_fetch($sql, 'zps_code');
	
	require_once $zz_conf['dir'].'/zzform.php';
	$zz_conf['user'] = 'Turnierzahlen '.implode('/', $vars);

	$updated = false;
	foreach ($teilnahmen as $teilnahme_id => $teilnahme) {
		$values = [];
		$values['POST']['teilnahme_id'] = $teilnahme_id;
		if (!$data['meldezahlen_gespeichert']) {
			// Schreiben von m_dwz und m_elo nur, falls Meldezahlen noch nicht
			// gespeichert wurden. Bei wiederholter Aktualisierung der 
			// Turnierzahlen werden die Meldezahlen logischerweise nicht nochmal geschrieben
			$values['POST']['m_dwz'] = $teilnahme['t_dwz'];
			$values['POST']['m_elo'] = $teilnahme['t_elo'];
		}
		if (!empty($wertungszahlen[$teilnahme['zps_code']])) {
			$values['POST']['t_dwz'] = $wertungszahlen[$teilnahme['zps_code']]['DWZ'];
			$values['POST']['t_elo'] = $wertungszahlen[$teilnahme['zps_code']]['FIDE_Elo'];
			if ($teilnahme['dwz_person'] != $wertungszahlen[$teilnahme['zps_code']]['Spielername']) {
				$data['abweichungen'][] = [
					'person' => $teilnahme['person'],
					'zps_code' => $teilnahme['zps_code'],
					'spielername' => $wertungszahlen[$teilnahme['zps_code']]['Spielername']
				];
			}
		} else {
			// Nicht verifizierte Wertungen bleiben bestehen,
			// Update ggf. nur bei Speicherung m_dwz, m_elo
			$data['fehler'][] = [
				'person' => $teilnahme['person'],
				'zps_code' => $teilnahme['zps_code']
			];
			$values['POST']['anmerkung'] = $teilnahme['anmerkung']
				? $teilnahme['anmerkung']."\n\n"
				: "";
			$values['POST']['anmerkung'] .= sprintf('ZPS bei Wertungsupdate am %s nicht gefunden', date('d.m.Y'));
		}
		$values['action'] = 'update';
		if (!$data['testlauf']) {
			$ops = zzform_multi('teilnahmen', $values);
			if (!$updated AND $ops['result'] === 'successful_update') $updated = true;
		}
	}
	if ($updated) {
		$values = [];
		$values['action'] = 'update';
		$values['POST']['tournament_id'] = $event['tournament_id'];
		$values['POST']['ratings_updated'] = date('Y-m-d');
		$ops = zzform_multi('turniere', $values);
		if (empty($ops['id'])) {
			wrap_log(sprintf('Unable to set `ratings_updated` for tournament %s', $event['identifier']));
		}
	}
	$page['text'] = wrap_template('turnierzahlen', $data);
	return $page;
}
