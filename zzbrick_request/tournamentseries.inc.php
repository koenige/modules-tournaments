<?php 

/**
 * tournaments module
 * Tournament series overview
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournamentseries($vars, $settings, $event) {
	$internal = !empty($settings['internal']) ? true : false;
	// displayed on this website?
	if (!$internal and !$event['website_id']) return false;

	// @todo access_codes müssen mindestens einmal erstellt worden sein, sonst wird URL nicht verlinkt
	// ggf. anders lösen, via Turniereinstellungen, Kategorien o. ä.
	// sobald Download-Code etabliert und vielfältig einsetzbar
	$sql = 'SELECT 
			places.contact AS ort, takes_place, events.description
			, series.description AS series_description
			, SUBSTRING_INDEX(series.path, "/", -1) AS series_path
			, website_org.contact_abbr
			, (SELECT COUNT(*) FROM access_codes WHERE event_id = events.event_id) AS access_codes
		FROM events
		LEFT JOIN websites USING (website_id)
		LEFT JOIN contacts website_org USING (contact_id)
		LEFT JOIN events_websites
			ON events.event_id = events_websites.event_id
			AND events_websites.website_id = %d
		LEFT JOIN contacts places
			ON places.contact_id = events.place_contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE events.event_id = %d
		AND ISNULL(main_event_id)
	';
	$sql = sprintf($sql
		, wrap_get_setting('website_id')
		, $event['event_id']
	);
	$event = array_merge($event, wrap_db_fetch($sql));
	if ($internal) $event['intern'] = true;

	// Turniere auslesen
	$sql = 'SELECT events.event_id, events.identifier, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, date_begin, date_end, time_begin
			, IFNULL(place, places.contact) AS turnierort
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, (SELECT COUNT(*) FROM partien
				WHERE NOT ISNULL(pgn) AND partien.event_id = events.event_id) AS partien
			, (SELECT COUNT(*) FROM partien
				WHERE partien.event_id = events.event_id) AS turnierstart
			, (SELECT COUNT(*) FROM teams
				WHERE teams.event_id = events.event_id
				AND teams.team_status = "Teilnehmer") AS teams
			, (SELECT COUNT(*) FROM participations
				LEFT JOIN teams USING (team_id)
				WHERE participations.event_id = events.event_id
				AND participations.usergroup_id = %d
				AND participations.teilnahme_status = "Teilnehmer"
				AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
			) AS spieler
			, (SELECT COUNT(*) FROM participations
				LEFT JOIN teams USING (team_id)
				WHERE participations.event_id = events.event_id
				AND participations.usergroup_id = %d
				AND participations.teilnahme_status = "Teilnehmer"
				AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
				AND (NOT ISNULL(participations.club_contact_id))
			) AS spieler_mit_verein
			, (SELECT COUNT(*) FROM kontingente WHERE kontingente.event_id = events.event_id) AS kontingente
			, tournament_id, main_tournament_id
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_websites
			ON events.event_id = events_websites.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses
			ON addresses.contact_id = places.contact_id
		WHERE series.main_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d
		ORDER BY series.sequence, date_begin, events.identifier';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		wrap_get_setting('website_id'),
		$event['series_category_id'],
		$event['year']
	);
	$event['tournaments'] = wrap_db_fetch($sql, 'event_id');
	parse_str($event['series_parameter'], $parameter);
	$event['kontingente'] = !empty($parameter['kontingent']) ? true : (!empty($parameter['quotadvm'] ? true : false));

	$event['turnierstart'] = 0;
	foreach ($event['tournaments'] AS $turnier) {
		$event['turnierstart'] += $turnier['turnierstart'];
		if ($turnier['kontingente']) $event['kontingente'] = true;
		if ($turnier['partien']) $event['pgn'] = true;
		if ($turnier['spieler']) $event['spieler'] = true;
		if ($turnier['teams']) $event['teams'] = true;
		$event[$turnier['turnierform']] = true;
		if (!$turnier['teilnehmerliste'] AND empty($event['teilnehmerliste'])) {
			if ($turnier['turnierform'] !== 'e') continue;
			// Einzelturniere: Teilnehmerliste falls gemeldete Teilnehmer
			if (!$turnier['spieler_mit_verein']) continue;
		}
		// Mannschaftsturniere: Turnierkarte bei Reihe nur, falls auch schon
		// einzelne Spieler gemeldet sind, da Turnierkarte nur Spieler anzeigt
		if ($turnier['turnierform'] !== 'e') {
			if ($turnier['spieler_mit_verein']) $event['teilnehmerliste'] = true;
		} else {
			$event['teilnehmerliste'] = true;
		}
	}

	// Kontingente?
	$sql = 'SELECT COUNT(*) FROM regionalgruppen
		WHERE series_category_id = %d';
	$sql = sprintf($sql, $event['series_category_id']);
	$event['kontingent'] = wrap_db_fetch($sql, '', 'single value');
	$found = false;
	foreach ($event['tournaments'] as $turnier) {
		if (!$turnier['spieler']) continue;
		$found = true;
		break;
	}
	if (!$found) $event['kontingent'] = NULL;

	// Links?
	if ($event['tournaments']) {
		$sql = 'SELECT COUNT(*) FROM events_links
			WHERE event_id IN (%s)';
		$sql = sprintf($sql, implode(',', array_keys($event['tournaments'])));
		$event['event_links'] = wrap_db_fetch($sql, '', 'single value');
	}

	$tournament_ids = [];
	foreach ($event['tournaments'] as $event_id => $tournament) {
		$tournament_ids[$tournament['tournament_id']] = $event_id;
		if ($tournament['turnierort'] === $event['turnierort'])
			$event['tournaments'][$event_id]['place_equal'] = true;
	}
	foreach ($event['tournaments'] as $event_id => $tournament) {
		if (empty($tournament['main_tournament_id'])) continue;
		$t_event_id = $tournament_ids[$tournament['main_tournament_id']];
		$event['tournaments'][$t_event_id]['tournaments'][$event_id] = $tournament;
		unset($event['tournaments'][$event_id]);
	}

	// Terminkalender
	
	// Teamübersicht
	
	// Suche
	
	$page['title'] = $event['event'].' '.$event['year'];
	$page['breadcrumbs'][] = $event['event'];
	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('tournamentseries', $event);
	return $page;
}
