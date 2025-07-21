<?php

/**
 * tournaments module
 * Output map with clubs of players of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2008, 2012, 2014-2024 Gustaf Mossakowski
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
function mod_tournaments_tournamentmap($vars, $settings, $event) {
	wrap_package_activate('clubs'); // CSS, fullscreen #map

	$federation = count($vars) === 3 ? array_pop($vars) : '';
	if ($federation) {
		$contact_ids = mod_tournaments_tournamentmap_federation($federation);
		// no member organisations?
		if (count($contact_ids) < 2) return false;
		$event = array_merge($event, reset($contact_ids));
	}

	// do we have players?
	$sql = 'SELECT COUNT(*)
		FROM participations
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		LEFT JOIN contacts
			ON participations.club_contact_id = contacts.contact_id
		LEFT JOIN teams USING (team_id)
		WHERE IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		AND (ISNULL(teams.team_id) OR teams.meldung = "komplett" OR teams.meldung = "teiloffen")
		AND NOT ISNULL(participations.club_contact_id)
		AND categories.main_category_id = %d
		AND usergroup_id = %d
		%s';
	$sql = sprintf($sql,
		$event['year'], $event['series_category_id'],
		wrap_id('usergroups', 'spieler'),
		($federation ? sprintf(
			'AND (contacts.contact_id IN (%s) OR country_id = %d)',
			implode(',', array_keys($contact_ids)), $event['country_id']) : '')
	);
	$players = wrap_db_fetch($sql, '', 'single value');
	if (!$players) return false;
	
	wrap_setting('leaflet_markercluster', true);
	$page['head'] = wrap_template('leaflet-head');

	$page['title'] = 'Herkunftsorte der Spieler: '.$event['event'].' '.$event['year'];
	if ($federation) $page['title'] .= ' – '.$event['contact'];
	$page['extra']['id'] = 'map';
	$page['text'] = wrap_template('tournamentmap', $event);
	$page['dont_show_h1'] = true;
	if (!$federation) {
		$page['breadcrumbs'][]['title'] = 'Herkunftsorte';
	} else {
		$page['breadcrumbs'][] = ['title' => 'Herkunftsorte', 'url_path' => '../'];
		$page['breadcrumbs'][]['title'] = $event['contact'];
	}
	return $page;
}

function mod_tournaments_tournamentmap_json($params, $setting, $event) {
	$federation = count($params) === 3 ? array_pop($params) : '';
	if (count($params) !== 2) return false;
	
	if ($federation) {
		$contact_ids = mod_tournaments_tournamentmap_federation($federation);
		if (!$contact_ids) return false;
	}

	// @todo fix query, some schools might have more than one address, won’t be shown here
	$sql = 'SELECT contacts.contact_id
			, contacts.contact, longitude, latitude
			, ok.identifier AS zps_code, contacts.identifier
			, (SELECT identification FROM contactdetails
				WHERE contactdetails.contact_id = contacts.contact_id
				AND provider_category_id = /*_ID categories provider/website _*/
				LIMIT 1) AS website
		FROM contacts
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = contacts.contact_id
			AND contacts_contacts.relation_category_id = /*_ID categories relation/venue _*/
			AND contacts_contacts.published = "yes"
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		LEFT JOIN addresses
			ON IFNULL(places.contact_id, contacts.contact_id) = addresses.contact_id
		LEFT JOIN contacts_identifiers ok
			ON ok.contact_id = contacts.contact_id AND current = "yes"
			AND identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
		LEFT JOIN categories
			ON contacts.contact_category_id = categories.category_id
		WHERE NOT ISNULL(contacts.contact)
		AND categories.parameters LIKE "%&organisation=1%"
		ORDER BY ok.identifier';
	$organisationen = wrap_db_fetch($sql, 'contact_id');

	$sql = 'SELECT participations.participation_id AS tt_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS spieler
			, CONCAT(event, " ", IFNULL(events.event_year, YEAR(events.date_begin))) AS turniername
			, zps.identifier AS player_pass_dsb
			, IFNULL(participations.club_contact_id, teams.club_contact_id) AS club_contact_id
			, fide.identifier AS player_id_fide
			, t_verein AS verein
			, t_dwz AS dwz, t_elo AS elo
			, participations.setzliste_no AS teilnehmer_nr
			, events.identifier AS event_identifier
			, CONCAT(teams.team, IFNULL(CONCAT(" ", team_no), "")) AS team
			, teams.identifier AS team_identifier
			, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
		FROM participations
		LEFT JOIN events USING (event_id)
		LEFT JOIN teams USING (team_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN contacts_identifiers zps
			ON participations.contact_id = zps.contact_id
			AND zps.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
			AND zps.current = "yes"
		LEFT JOIN contacts_identifiers fide
			ON participations.contact_id = fide.contact_id
			AND fide.identifier_category_id = /*_ID categories identifiers/id_fide _*/
			AND fide.current = "yes"
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		AND (ISNULL(teams.team_id) OR teams.meldung = "komplett" OR teams.meldung = "teiloffen")
		AND usergroup_id = /*_ID usergroups spieler _*/
		%s
		ORDER BY t_nachname, t_vorname
	';
	$sql = sprintf($sql,
		wrap_db_escape($params[1]), $params[0],
		$federation ? sprintf(
			' AND (participations.club_contact_id IN (%s) OR teams.club_contact_id IN (%s)) '
			, implode(',', array_keys($contact_ids))
			, implode(',', array_keys($contact_ids))
		) : ''
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
			'player_pass_dsb' => !empty($person['Mgl_Nr']) ? $person['zps']."-".$person['Mgl_Nr'] : $person['player_pass_dsb'],
			'dwz' => $person['dwz'],
			'player_id_fide' => $person['player_id_fide'],
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

/**
 * get contact IDs for a federation
 *
 * @param string $federation
 * @return array
 */
function mod_tournaments_tournamentmap_federation($federation) {
	$sql = 'SELECT contact_id
			, contact, identifier AS federation_identifier, contact_short
			, country_id
		FROM contacts
		LEFT JOIN contacts_contacts USING (contact_id)
		WHERE identifier = "%s"
		AND contacts_contacts.relation_category_id = /*_ID categories relation/member _*/
		AND contacts_contacts.main_contact_id = /*_SETTING clubs_confederation_contact_id _*/';
	$sql = sprintf($sql, wrap_db_escape($federation));
	$federation = wrap_db_fetch($sql);
	if (!$federation) return [];

	$sql = 'SELECT contact_id
		FROM contacts_contacts
		WHERE main_contact_id IN (%s)
	';
	$contact_ids = wrap_db_children([$federation['contact_id'] => $federation['contact_id']], $sql);
	$contact_ids[$federation['contact_id']] = $federation;
	return $contact_ids;
}
