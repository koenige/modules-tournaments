<?php

/**
 * tournaments module
 * make script to update tournament ratings before tournament start
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Aktualisierung der Turnierwertungszahlen kurz vor Turnierbeginn
 * Alte Zahlen werden als Meldezahlen in m_dwz und m_elo gespeichert
 *
 * @param array $vars
 * @return array $page
 */
function mod_tournaments_make_turnierzahlen($vars, $settings, $event) {
	$sql = 'SELECT tournament_id
			, IF(NOT ISNULL(events.date_end),
				IF(events.date_end < CURDATE(), 1, NULL),
				IF(events.date_begin < CURDATE(), 1, NULL)
			) AS event_over
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event = array_merge($event, wrap_db_fetch($sql));

	$data['abweichungen'] = [];
	$data['fehler'] = [];
	$data['event'] = $event['event'];
	$data['year'] = $event['year'];
	$data['testlauf'] = true;
	if (!empty($_POST['update'])) $data['testlauf'] = false;

	$page['breadcrumbs'][]['title'] = 'Turnierzahlen';
	$page['title'] = sprintf('Aktualisierung der Wertungszahlen für %s %s', $event['event'], $event['year']);
	$page['dont_show_h1'] = true;

	if ($event['event_over']) {
		$data['event_over'] = true;
		$page['text'] = wrap_template('turnierzahlen', $data);
		return $page;
	}

	$sql = 'SELECT DISTINCT m_dwz, m_elo
		FROM participations
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
	
	$sql = 'SELECT participation_id, contacts.contact_id
			, contact, identifier
			, CONCAT(last_name, ", ", first_name) AS contact_last_first
			, t_dwz, t_elo
			, participations.remarks
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		WHERE event_id = %d
		AND usergroup_id = %d
	';
	$sql = sprintf($sql,
		$event['event_id'],
		wrap_id('usergroups', 'spieler')
	);
	$participations = wrap_db_fetch($sql, 'participation_id');

	$contact_ids = [];
	foreach ($participations as $participation) {
		$contact_ids[] = $participation['contact_id'];
	}

	$rating_systems = ['dwz', 'elo'];

	if ($contact_ids) {
		$ratings['DSB'] = mod_tournaments_make_turnierzahlen_dsb($contact_ids);
		$ratings['FIDE'] = mod_tournaments_make_turnierzahlen_fide($contact_ids);
	}

	wrap_setting('log_username', 'Turnierzahlen '.implode('/', $vars));

	$updated = false;
	foreach ($participations as $participation_id => $participation) {
		$values = [];
		$values['POST']['participation_id'] = $participation_id;
		if (!$data['meldezahlen_gespeichert']) {
			// Schreiben von m_dwz und m_elo nur, falls Meldezahlen noch nicht
			// gespeichert wurden. Bei wiederholter Aktualisierung der 
			// Turnierzahlen werden die Meldezahlen logischerweise nicht nochmal geschrieben
			foreach ($rating_systems as $system)
				$values['POST']['m_'.$system] = $participation['t_'.$system];
		}
		$status = 'not_found';
		foreach ($ratings as $federation => $ratings_per_sys) {
			if (!array_key_exists($participation['contact_id'], $ratings_per_sys)) {
				continue;
			}
			if ($status === 'not_found') $status = 'exists';
			foreach ($rating_systems as $system) {
				if (empty($ratings_per_sys[$participation['contact_id']][$system])) continue;
				$values['POST']['t_'.$system] = $ratings_per_sys[$participation['contact_id']][$system];
				if ($participation['t_'.$system].'' !== $values['POST']['t_'.$system].'') {
					$data['changes'][] = [
						'contact' => $participation['contact'],
						'system' => $system,
						'old_rating' => $participation['t_'.$system],
						'new_rating' => $values['POST']['t_'.$system],
						'link' => wrap_path('contacts_profile[person]', $participation['identifier'], false) // @todo remove ,false
					];
				}
				$status = 'found';
			}
			if (empty($ratings_per_sys[$participation['contact_id']]['contact_last_first'])) continue;
			if ($ratings_per_sys[$participation['contact_id']]['contact_last_first'] === $participation['contact_last_first']) continue;
			$data['abweichungen'][] = [
				'contact' => $participation['contact'],
				'federation' => $federation,
				'contact_id' => $participation['contact_id'],
				'contact_last_first' => $ratings_per_sys[$participation['contact_id']]['contact_last_first'],
				'link' => wrap_path('contacts_profile[person]', $participation['identifier'], false) // @todo remove ,false
			];
		}
		if ($status !== 'found') {
			// Nicht verifizierte Wertungen bleiben bestehen,
			// Update ggf. nur bei Speicherung m_dwz, m_elo
			$data['fehler'][] = [
				'contact' => $participation['contact'],
				'contact_id' => $participation['contact_id'],
				$status => 1,
				'link' => wrap_path('contacts_profile[person]', $participation['identifier'], false) // @todo remove ,false
			];
			$values['POST']['remarks'] = $participation['remarks']
				? $participation['remarks']."\n\n"
				: "";
			$values['POST']['remarks'] .= sprintf(wrap_text('No ratings found when updating on %s.'), wrap_date(date('Y-m-d')));
		}
		$values['action'] = 'update';
		if (!$data['testlauf']) {
			$ops = zzform_multi('participations', $values);
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

/**
 * get ratings for German Chess Federation (DSB) 
 *
 * @param array $contact_ids
 * @return array
 */
function mod_tournaments_make_turnierzahlen_dsb($contact_ids) {
	$sql = 'SELECT contact_id
			, DWZ AS dwz
			, FIDE_Elo AS elo
			, REPLACE(Spielername, ",", ", ") AS contact_last_first
		FROM dwz_spieler
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.identifier = CONCAT(ZPS, "-", Mgl_Nr)
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		WHERE contact_id IN (%s)';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, implode(',', $contact_ids)
	);
	return wrap_db_fetch($sql, 'contact_id');
}

/**
 * get ratings for FIDE
 *
 * @param array $contact_ids
 * @return array
 */
function mod_tournaments_make_turnierzahlen_fide($contact_ids) {
	$sql = 'SELECT contact_id
			, standard_rating AS elo
			, player AS contact_last_first
		FROM fide_players
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.identifier = player_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		WHERE contact_id IN (%s)';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/fide-id')
		, implode(',', $contact_ids)
	);
	return wrap_db_fetch($sql, 'contact_id');
}

