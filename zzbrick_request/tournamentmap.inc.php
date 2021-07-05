<?php

/**
 * tournaments module
 * Output map with clubs of players of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2008, 2012, 2014-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Displays players on a Leaflet map
 *
 * @param array $vars
 *		[0]: Jahr
 *		[0]: event identifier
 * @return array $page
 */
function mod_tournaments_tournamentmap($vars) {
	global $zz_setting;
	
	$federation = count($vars) === 3 ? array_pop($vars) : '';
	$event = my_turniertermin($vars);
	if (!$event) return false;
	
	if ($federation) {
		$sql = 'SELECT contact_id
				, contact, identifier AS org_kennung, contact_short
				, country_id
			FROM contacts
			WHERE identifier = "%s"
			AND mother_contact_id = %d';
		$sql = sprintf($sql
			, wrap_db_escape($federation)
			, $zz_setting['contact_ids']['dsb']
		);
		$federation = wrap_db_fetch($sql);
		if (!$federation) return false;
		$contact_ids[] = $federation['contact_id'];
		$sql = 'SELECT contact_id
			FROM contacts
			WHERE mother_contact_id IN (%s)
		';
		$contact_ids = wrap_db_children($contact_ids, $sql);
		// no member organisations?
		if (count($contact_ids) === 1) return false;
	}

	// gibt es Teilnehmer?
	$sql = 'SELECT COUNT(teilnahme_id)
		FROM teilnahmen
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		LEFT JOIN contacts
			ON teilnahmen.club_contact_id = contacts.contact_id
		LEFT JOIN teams USING (team_id)
		WHERE IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		AND (ISNULL(teams.team_id) OR teams.meldung = "komplett" OR teams.meldung = "teiloffen")
		AND NOT ISNULL(teilnahmen.club_contact_id)
		AND categories.main_category_id = %d
		AND usergroup_id = %d
		%s';
	$sql = sprintf($sql,
		$event['year'], $event['series_category_id'],
		wrap_id('usergroups', 'spieler'),
		($federation ? sprintf(
			'AND (contact_id IN (%s) OR country_id = %d)',
			implode(',', $contact_ids), $federation['country_id']) : '')
	);
	$tn = wrap_db_fetch($sql, '', 'single value');
	if (!$tn) return false;
	
	if ($federation) {
		$event = array_merge($event, $federation);
	}

	$page['head'] = wrap_template('vereine-map-head');

	$page['title'] = 'Herkunftsorte der Spieler: '.$event['event'].' '.$event['year'];
	if ($federation) $page['title'] .= ' – '.$federation['contact'];
	$page['extra']['body_attributes'] = 'id="map"';
	$page['extra']['realm'] = 'vereine';
	$page['text'] = wrap_template('tournamentmap', $event);
	$page['dont_show_h1'] = true;
	if (!$federation) {
		$page['breadcrumbs'][] = sprintf('<a href="../../">%d</a>', $event['year']);
		$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $event['event']);
		$page['breadcrumbs'][] = 'Herkunftsorte';
	} else {
		$page['breadcrumbs'][] = sprintf('<a href="../../../">%d</a>', $event['year']);
		$page['breadcrumbs'][] = sprintf('<a href="../../">%s</a>', $event['event']);
		$page['breadcrumbs'][] = '<a href="../">Herkunftsorte</a>';
		$page['breadcrumbs'][] = $federation['contact'];
	}
	return $page;
}

function mod_tournaments_tournamentmap_json($params) {
	$federation = count($params) === 3 ? substr(array_pop($params), 0, -8) : '';
	if (count($params) !== 2) return false;

	if ($federation) {
		$sql = 'SELECT contact_id
			FROM contacts
			WHERE identifier = "%s"';
		$sql = sprintf($sql, wrap_db_escape($federation));
		$contact_ids = wrap_db_fetch($sql);
		$sql = 'SELECT contact_id
			FROM contacts
			WHERE mother_contact_id IN (%s)
		';
		$contact_ids = wrap_db_children($contact_ids, $sql);
		if (!$contact_ids) return false;
		
		$sql = 'SELECT contact_id FROM contacts
			WHERE contact_id IN (%s)';
		$sql = sprintf($sql, implode(',', $contact_ids));
		$contact_ids = wrap_db_fetch($sql, 'contact_id', 'single value');
	}

	$sql = 'SELECT contacts.contact_id
			, contacts.contact, contacts.website, longitude, latitude
			, ok.identifier AS zps_code, contacts.identifier
		FROM contacts
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = contacts.contact_id
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN contacts_identifiers ok
			ON ok.contact_id = contacts.contact_id AND current = "yes"
			AND identifier_category_id = %d
		WHERE NOT ISNULL(contacts.contact)
		AND contacts_contacts.published = "yes"
		ORDER BY ok.identifier
	';
	$sql = sprintf($sql,
		wrap_category_id('kennungen/zps')
	);
	$organisationen = wrap_db_fetch($sql, 'contact_id');

	$sql = 'SELECT teilnahmen.teilnahme_id AS tt_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS spieler
			, CONCAT(event, " ", IFNULL(events.event_year, YEAR(events.date_begin))) AS turniername
			, zps.identifier AS zps_code
			, IFNULL(teilnahmen.club_contact_id, teams.club_contact_id) AS club_contact_id
			, fide.identifier AS fide_id
			, t_verein AS verein
			, t_dwz AS dwz, t_elo AS elo
			, teilnahmen.setzliste_no AS teilnehmer_nr
			, events.identifier AS event_identifier
			, CONCAT(teams.team, IFNULL(CONCAT(" ", team_no), "")) AS team
			, teams.kennung AS team_identifier
			, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
		FROM teilnahmen
		LEFT JOIN personen USING (person_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN teams USING (team_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN contacts_identifiers zps
			ON personen.contact_id = zps.contact_id
			AND zps.identifier_category_id = %d
			AND zps.current = "yes"
		LEFT JOIN contacts_identifiers fide
			ON personen.contact_id = fide.contact_id
			AND fide.identifier_category_id = %d
			AND fide.current = "yes"
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		AND (ISNULL(teams.team_id) OR teams.meldung = "komplett" OR teams.meldung = "teiloffen")
		AND usergroup_id = %d
		%s
		ORDER BY t_nachname, t_vorname
	';
	$sql = sprintf($sql,
		wrap_category_id('kennungen/zps'),
		wrap_category_id('kennungen/fide-id'),
		wrap_db_escape($params[1]), $params[0],
		wrap_id('usergroups', 'spieler'),
		$federation ? sprintf(' AND (teilnahmen.club_contact_id IN (%s) OR teams.club_contact_id IN (%s)) ', implode(',', $contact_ids), implode(',', $contact_ids)) : ''
	);
	$spieler = wrap_db_fetch($sql, 'tt_id');

	foreach ($spieler as $id => $person) {
		if (!empty($organisationen[$person['club_contact_id']])) {
			$spieler[$id] = array_merge($person, $organisationen[$person['club_contact_id']]);
		}
		if (empty($spieler[$id]['latitude']) AND !empty($person['club_contact_id'])) {
			if ($person['year'] > date('Y') - 6) {
				// just log errors for players in the last 6 years
				wrap_log(sprintf(
					'Keine Koordinaten für Verein %s (Org-ID %s), Spieler %s beim Turnier %s.', 
					(isset($spieler[$id]['contact']) ? $spieler[$id]['contact'] : 'unbekannt'),
					$person['club_contact_id'], $spieler[$id]['spieler'], $spieler[$id]['turniername']
				));
			}
		}
	}
	$data = [];
	foreach ($spieler as $person) {
		if (empty($person['contact_id'])) continue;
		if (empty($data[$person['contact_id']])) {
			$data[$person['contact_id']] = [
				'title' => $person['contact'],
				'style' => 'verein',
				'website' => $person['website'],
				'identifier' => $person['identifier'],
				'longitude' => $person['longitude'],
				'latitude' => $person['latitude'],
				'altitude' => 0,
				'spieler' => []
			];
		}
		$data[$person['contact_id']]['spieler'][] = [
			'spieler' => $person['spieler'],
			'zps_code' => !empty($person['Mgl_Nr']) ? $person['zps']."-".$person['Mgl_Nr'] : $person['zps_code'],
			'dwz' => $person['dwz'],
			'fide_id' => $person['fide_id'],
			'elo' => $person['elo'],
			'teilnehmer_nr' => !empty($person['teilnehmer_nr']) ? $person['teilnehmer_nr'] : '',
			'turnier' => $person['turniername'],
			'event_identifier' => $person['event_identifier'],
			'team' => $person['team'],
			'team_identifier' => $person['team_identifier']
		];
	}
	unset($spieler);
	if (empty($data)) return false;
	$page['text'] = wrap_template('tournamentmap-json', $data);
	$page['content_type'] = 'js';
	return $page;
}
