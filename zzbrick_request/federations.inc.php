<?php 

/**
 * tournaments module
 * Overview of all federations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht der der Landesverbände
 *
 * @param array $vars
 *		int [0]: Jahr
 *		string [1]: Kennung Reihe
 *		string [2]: 'lv'
 */
function mod_tournaments_federations($vars) {
	if (count($vars) !== 2) return false;

	// Event
	$sql = 'SELECT events.event_id, IFNULL(event_year, YEAR(date_begin)) AS year
			, main_series.category AS main_series
			, main_series.category_short AS main_series_short
			, events.identifier
			, urkunde_parameter
			, tournaments.tabellenstaende
			, (SELECT MAX(ts.runde_no)
					FROM tabellenstaende ts WHERE ts.event_id = events.event_id) AS aktuelle_runde_no
		FROM events
		LEFT JOIN tournaments USING (event_id)
		JOIN categories series
			ON events.series_category_id = series.category_id
		JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = /*_SETTING website_id _*/
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(event_year, YEAR(date_begin)) = %d';
	$sql = sprintf($sql, wrap_db_escape($vars[1]), $vars[0]);
	$events = wrap_db_fetch($sql, 'event_id');
	if (!$events) return false;
	$event = reset($events);

	// Landesverbände ohne Sonderverbände
	$lv = mf_tournaments_federations();
	if (!$lv) return false;
	$lv['year'] = intval($vars[0]);

	$lv['main_series'] = $event['main_series'];
	$lv['main_series_short'] = $event['main_series_short'];

	$standard_platzurkunden = wrap_setting('platzurkunden');

	foreach ($lv as $id => $verband) {
		if (!is_numeric($id)) continue;
		$lv[$id]['punkte'] = 0;
		for ($i = 1; $i <= $standard_platzurkunden; $i++) {
			$lv[$id]['plaetze'][$i]['platz'] = $i;
			$lv[$id]['plaetze'][$i]['anzahl'] = 0;
		}
		if (wrap_setting('tournaments_type_single')) {
			$lv[$id]['punktvergabe'] = true;
		}
	}
	
	if (wrap_setting('tournaments_type_single')) {
		$sql = 'SELECT countries.country_id
				, COUNT(participations.participation_id) AS anzahl_spieler
			FROM events
			LEFT JOIN participations
				ON participations.event_id = events.event_id
				AND usergroup_id = /*_ID usergroups spieler _*/
			LEFT JOIN contacts
				ON participations.club_contact_id = contacts.contact_id
			LEFT JOIN contacts_identifiers ok
				ON ok.contact_id = contacts.contact_id AND ok.current = "yes"
			LEFT JOIN contacts_identifiers lvk
				ON CONCAT(SUBSTRING(ok.identifier, 1, 1), "00") = lvk.identifier
			LEFT JOIN contacts landesverbaende
				ON lvk.contact_id = landesverbaende.contact_id AND lvk.current = "yes"
			LEFT JOIN countries
				ON IFNULL(landesverbaende.country_id, contacts.country_id) 
					= countries.country_id
			WHERE events.event_id IN (%s)
			AND NOT ISNULL(countries.country_id)
			GROUP BY countries.country_id
		';
	} else {
		$sql = 'SELECT countries.country_id
				, COUNT(teams.team_id) AS anzahl_teams
			FROM events
			LEFT JOIN teams
				ON teams.event_id = events.event_id
				AND team_status IN ("Teilnehmer", "Teilnahmeberechtigt")
				AND spielfrei = "nein"
			LEFT JOIN contacts
				ON teams.club_contact_id = contacts.contact_id
			LEFT JOIN contacts_identifiers ok
				ON ok.contact_id = contacts.contact_id AND ok.current = "yes"
			LEFT JOIN contacts_identifiers lvk
				ON CONCAT(SUBSTRING(ok.identifier, 1, 1), "00") = lvk.identifier
			LEFT JOIN contacts landesverbaende
				ON lvk.contact_id = landesverbaende.contact_id AND lvk.current = "yes"
			LEFT JOIN countries
				ON IFNULL(landesverbaende.country_id, contacts.country_id) 
					= countries.country_id
			WHERE events.event_id IN (%s)
			AND NOT ISNULL(countries.country_id)
			GROUP BY countries.country_id
		';
	}
	$sql = sprintf($sql, implode(',', array_keys($events)));
	$tn = wrap_db_fetch($sql, 'country_id');
	foreach ($tn as $country_id => $anzahl) {
		if (!empty($anzahl['anzahl_teams'])) {
			$lv[$country_id]['teams'] = $anzahl['anzahl_teams'];
		}
		if (!empty($anzahl['anzahl_spieler'])) {
			$lv[$country_id]['spieler'] = $anzahl['anzahl_spieler'];
		}
	}
	
	// Medaillenspiegel
	// Termine: Plätze auslesen, weiblich ja/nein auslesen
	// 1. Platz 10 Pkt bis 5. Platz 6 Pkt., bei mehr Plätzen jeweils 1 Pkt weniger
	// vor Beendigung jedes Turniers: nur vorläufig
	foreach ($events as $event_id => $event) {
		if (!$event['urkunde_parameter']) continue;
		parse_str($event['urkunde_parameter'], $parameter);
		if (array_key_exists('medaillenspiegel', $parameter) AND !$parameter['medaillenspiegel']) {
			unset($events[$event_id]);
			continue;
		}
		$events[$event_id] = array_merge($event, $parameter);
	}

	if (wrap_setting('tournaments_type_single')) {
		$lv['punktvergabe'] = true;
		foreach ($events as $event) {
			$tabellenstaende = !empty($event['tabellenstaende'])
				? explode(',', $event['tabellenstaende']) : [];
			$tabellenstaende[] = '';
			foreach ($tabellenstaende as $ts) {
				$filter = mf_tournaments_standings_filter($ts);
				if ($filter['error']) return false;
				$sql = 'SELECT participation_id, platz_no, tabellenstaende.event_id,
						landesverbaende.contact_id, landesverbaende.country_id
					FROM tabellenstaende
					LEFT JOIN persons
						ON persons.person_id = tabellenstaende.person_id
					LEFT JOIN participations
						ON participations.contact_id = persons.contact_id
						AND participations.event_id = tabellenstaende.event_id
					LEFT JOIN contacts organisationen
						ON participations.club_contact_id = organisationen.contact_id
					LEFT JOIN contacts_identifiers ok
						ON ok.contact_id = organisationen.contact_id AND ok.current = "yes"
					LEFT JOIN contacts_identifiers lvk
						ON CONCAT(SUBSTRING(ok.identifier, 1, 1), "00") = lvk.identifier
					LEFT JOIN contacts landesverbaende
						ON lvk.contact_id = landesverbaende.contact_id AND lvk.current = "yes"
					WHERE runde_no = %d
					AND tabellenstaende.event_id = %d
					AND NOT ISNULL(landesverbaende.country_id)
					%s
					ORDER BY platz_no
					LIMIT %d
				';
				$sql = sprintf($sql,
					$event['aktuelle_runde_no'], $event['event_id'],
					(!empty($filter['where']) ? ' AND '.implode(' AND ', $filter['where']) : ''),
					$standard_platzurkunden
				);
				$tabellenstand = wrap_db_fetch($sql, 'participation_id');
				$punkte = 10;
				$platz = 1;
				foreach ($tabellenstand as $stand) {
					$lv[$stand['country_id']]['punkte'] += $punkte; 
					$lv[$stand['country_id']]['plaetze'][$platz]['platz'] = $platz;
					if (empty($lv[$stand['country_id']]['plaetze'][$platz]['anzahl']))
						$lv[$stand['country_id']]['plaetze'][$platz]['anzahl'] = 0;
					$lv[$stand['country_id']]['plaetze'][$platz]['anzahl']++;
					$punkte--;
					$platz++;
				}
			}
		}
	}
	
	$page['breadcrumbs'][]['title'] = 'Landesverbände';
	$page['dont_show_h1'] = true;
	$page['title'] = $lv['main_series_short'].' '.$lv['year'].', Landesverbände';
	$page['text'] = wrap_template('federations', $lv);
	return $page;
}
