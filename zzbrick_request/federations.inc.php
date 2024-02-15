<?php 

/**
 * tournaments module
 * Overview of all federations
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2024 Gustaf Mossakowski
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
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, events.identifier
			, urkunde_parameter
			, tournaments.tabellenstaende
			, (SELECT MAX(ts.runde_no)
					FROM tabellenstaende ts WHERE ts.event_id = events.event_id) AS aktuelle_runde_no
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		JOIN categories series
			ON events.series_category_id = series.category_id
		JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(event_year, YEAR(date_begin)) = %d';
	$sql = sprintf($sql, wrap_setting('website_id'), wrap_db_escape($vars[1]), $vars[0]);
	$events = wrap_db_fetch($sql, 'event_id');
	if (!$events) return false;
	$event = reset($events);
	$turnierform = $event['turnierform'];

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
		if ($turnierform === 'e') {
			$lv[$id]['punktvergabe'] = true;
		}
	}
	
	if ($event['turnierform'] === 'e') {
		$sql = 'SELECT countries.country_id
				, COUNT(participations.participation_id) AS anzahl_spieler
			FROM events
			LEFT JOIN participations
				ON participations.event_id = events.event_id
				AND usergroup_id = %d
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
		$sql = sprintf($sql, wrap_id('usergroups', 'spieler'), implode(',', array_keys($events)));
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
		$sql = sprintf($sql, implode(',', array_keys($events)));
	}
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

	if ($turnierform === 'e') {
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
	$lv[$turnierform] = true;
	$page['text'] = wrap_template('federations', $lv);
	return $page;
}

/**
 * get all federations
 *
 * @return array
 */
function mf_tournaments_federations() {
	$sql = 'SELECT contact_id, contact, country
			, countries.identifier, country_id
		FROM contacts
		JOIN contacts_identifiers ok USING (contact_id)
		LEFT JOIN contacts_contacts USING (contact_id)
		JOIN countries USING (country_id)
		WHERE contact_category_id = %d
		AND ok.current = "yes"
		AND contacts_contacts.main_contact_id = %d
		AND contacts_contacts.relation_category_id = %d
		ORDER BY country';
	$sql = sprintf($sql
		, wrap_category_id('contact/federation')
		, wrap_setting('contact_ids[dsb]')
		, wrap_category_id('relation/member')
	);
	return wrap_db_fetch($sql, 'country_id');
}
