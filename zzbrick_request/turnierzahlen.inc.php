<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Aktualisierung der Turnierwertungszahlen kurz vor Turnierbeginn


/**
 * Aktualisierung der Turnierwertungszahlen kurz vor Turnierbeginn
 * Alte Zahlen werden als Meldezahlen in m_dwz und m_elo gespeichert
 *
 * @param array $vars
 * @return array $page
 */
function mod_tournaments_turnierzahlen($vars) {
	global $zz_setting;
	global $zz_conf;

	$sql = 'SELECT termin_id, turnier_id
			, IF(NOT ISNULL(termine.ende),
				IF(termine.ende < CURDATE(), 1, NULL),
				IF(termine.beginn < CURDATE(), 1, NULL)
			) AS termin_vergangen
			, YEAR(beginn) AS jahr
			, termin, kennung
		FROM termine
		LEFT JOIN turniere USING (termin_id)
		WHERE kennung = "%s"';
	$sql = sprintf($sql, wrap_db_escape(implode('/', $vars)));
	$termin = wrap_db_fetch($sql);
	if (!$termin) return false;

	$data['abweichungen'] = [];
	$data['fehler'] = [];
	$data['termin'] = $termin['termin'];
	$data['jahr'] = $termin['jahr'];
	$data['testlauf'] = true;
	if (!empty($_POST['update'])) $data['testlauf'] = false;

	$page['breadcrumbs'][] = '<a href="/intern/termine/">Termine</a>';
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%d/">%d</a>',
		$termin['jahr'], $termin['jahr']
	);
	$page['breadcrumbs'][] = sprintf(
		'<a href="/intern/termine/%s/">%s</a>',
		$termin['kennung'], $termin['termin']
	);
	$page['breadcrumbs'][] = 'Turnierzahlen';
	$page['title'] = sprintf('Aktualisierung der Wertungszahlen für %s %s', $termin['termin'], $termin['jahr']);
	$page['dont_show_h1'] = true;

	if ($termin['termin_vergangen']) {
		$data['termin_vergangen'] = true;
		$page['text'] = wrap_template('turnierzahlen', $data);
		return $page;
	}

	$sql = 'SELECT DISTINCT m_dwz, m_elo
		FROM teilnahmen
		WHERE termin_id = %d';
	$sql = sprintf($sql, $termin['termin_id']);
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
			, CONCAT(vorname, " ", IFNULL(CONCAT(namenszusatz, " "), ""), nachname) AS person
			, CONCAT(nachname, ",", vorname) AS dwz_person
			, anmerkung
		FROM teilnahmen
		LEFT JOIN personen USING (person_id)
		LEFT JOIN contacts_identifiers
			ON personen.contact_id = contacts_identifiers.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		WHERE termin_id = %d
		AND usergroup_id = %d
	';
	$sql = sprintf($sql,
		wrap_category_id('kennungen/zps'),
		$termin['termin_id'],
		$zz_setting['gruppen_ids']['spieler']
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
		$values['POST']['turnier_id'] = $termin['turnier_id'];
		$values['POST']['ratings_updated'] = date('Y-m-d');
		$ops = zzform_multi('turniere', $values);
		if (empty($ops['id'])) {
			wrap_error(sprintf('Unable to set `ratings_updated` for tournament %s', $termin['kennung']));
		}
	}
	$page['text'] = wrap_template('turnierzahlen', $data);
	return $page;
}
