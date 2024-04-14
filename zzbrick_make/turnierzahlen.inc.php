<?php

/**
 * tournaments module
 * make script to update tournament ratings before tournament start
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2024 Gustaf Mossakowski
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
		wrap_package_activate('ratings');
		$ratings['DSB'] = mf_ratings_rating_dsb($contact_ids);
		$ratings['FIDE'] = mf_ratings_rating_fide($contact_ids);
	}

	wrap_setting('log_username', 'Turnierzahlen '.implode('/', $vars));

	$updated = false;
	foreach ($participations as $participation_id => $participation) {
		$line = [
			'participation_id' => $participation_id
		];
		if (!$data['meldezahlen_gespeichert']) {
			// Schreiben von m_dwz und m_elo nur, falls Meldezahlen noch nicht
			// gespeichert wurden. Bei wiederholter Aktualisierung der 
			// Turnierzahlen werden die Meldezahlen logischerweise nicht nochmal geschrieben
			foreach ($rating_systems as $system)
				$line['m_'.$system] = $participation['t_'.$system];
		}
		$status = 'not_found';
		foreach ($ratings as $federation => $ratings_per_sys) {
			if (!array_key_exists($participation['contact_id'], $ratings_per_sys)) {
				continue;
			}
			if ($status === 'not_found') $status = 'exists';
			foreach ($rating_systems as $system) {
				if (empty($ratings_per_sys[$participation['contact_id']][$system])) continue;
				$line['t_'.$system] = $ratings_per_sys[$participation['contact_id']][$system];
				if ($participation['t_'.$system].'' !== $line['t_'.$system].'') {
					$data['changes'][] = [
						'contact' => $participation['contact'],
						'system' => $system,
						'old_rating' => $participation['t_'.$system],
						'new_rating' => $line['t_'.$system],
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
			$line['remarks'] = $participation['remarks']
				? $participation['remarks']."\n\n"
				: "";
			$line['remarks'] .= sprintf(wrap_text('No ratings found when updating on %s.'), wrap_date(date('Y-m-d')));
		}
		if (!$data['testlauf']) {
			$participation_id = zzform_update('participations', $line);
			if (!$updated AND $participation_id) $updated = true;
		}
	}
	if ($updated) {
		$line = [
			'tournament_id' => $event['tournament_id'],
			'ratings_updated' => date('Y-m-d')
		];
		zzform_update('tournaments', $line);
	}
	$page['text'] = wrap_template('turnierzahlen', $data);
	return $page;
}
