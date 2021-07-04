<?php 

/**
 * tournaments module
 * Tournament series overview
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournamentseries($vars, $settings) {
	global $zz_setting;

	$intern = !empty($settings['intern']) ? true : false;
	if (count($vars) !== 2) return false;

	// @todo access_codes müssen mindestens einmal erstellt worden sein, sonst wird URL nicht verlinkt
	// ggf. anders lösen, via Turniereinstellungen, Kategorien o. ä.
	// sobald Download-Code etabliert und vielfältig einsetzbar
	$sql = 'SELECT events.event_id
			, event, date_begin, date_end, places.contact AS ort, takes_place, events.description, events.identifier
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, series.category_short, series.description AS series_description, series_category_id
			, SUBSTRING_INDEX(series.path, "/", -1) AS series_path
			, IFNULL(place, places.contact) AS turnierort
			, website_org.contact_abbr
			, (SELECT COUNT(code_id) FROM access_codes WHERE event_id = events.event_id) AS access_codes
			, series.parameters
			, (SELECT COUNT(*) FROM categories WHERE categories.main_category_id = series.category_id) AS sub_series
		FROM events
		LEFT JOIN websites USING (website_id)
		LEFT JOIN contacts website_org USING (org_id)
		JOIN events_websites
			ON events.event_id = events_websites.event_id
			AND events_websites.website_id = %d
		LEFT JOIN contacts places
			ON places.contact_id = events.place_contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE events.identifier = "%s/%s"
		AND ISNULL(main_event_id)
	';
	$sql = sprintf($sql
		, $zz_setting['website_id']
		, wrap_db_escape($vars[0])
		, wrap_db_escape($vars[1])
	);
	$event = wrap_db_fetch($sql);
	if ($event AND !$event['sub_series']) $event = [];
	$series = [];
	if (!$event) {
		$sql = 'SELECT category_id, category, category_short, description, parameters
			FROM categories
			WHERE path = "reihen/%s"';
		$sql = sprintf($sql, wrap_db_escape($vars[1]));
		$series = wrap_db_fetch($sql);
		if (!$series) return false;
		$event = [
			'series_category_id' => $series['category_id'],
			'year' => intval($vars[0]),
			'event' => $series['category'],
			'parameters' => $series['parameters'],
			'turnierort' => ''
		];
	}

	if ($intern) $event['intern'] = true;

	// Turniere auslesen
	$sql = 'SELECT events.event_id, events.identifier, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, date_begin, date_end, time_begin
			, IFNULL(place, places.contact) AS turnierort
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, (SELECT COUNT(partie_id) FROM partien
				WHERE NOT ISNULL(pgn) AND partien.event_id = events.event_id) AS partien
			, (SELECT COUNT(partie_id) FROM partien
				WHERE partien.event_id = events.event_id) AS turnierstart
			, (SELECT COUNT(team_id) FROM teams
				WHERE teams.event_id = events.event_id
				AND teams.team_status = "Teilnehmer") AS teams
			, (SELECT COUNT(teilnahme_id) FROM teilnahmen
				LEFT JOIN teams USING (team_id)
				WHERE teilnahmen.event_id = events.event_id
				AND teilnahmen.usergroup_id = %d
				AND teilnahmen.teilnahme_status = "Teilnehmer"
				AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
			) AS spieler
			, (SELECT COUNT(teilnahme_id) FROM teilnahmen
				LEFT JOIN teams USING (team_id)
				WHERE teilnahmen.event_id = events.event_id
				AND teilnahmen.usergroup_id = %d
				AND teilnahmen.teilnahme_status = "Teilnehmer"
				AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
				AND (NOT ISNULL(teilnahmen.club_contact_id))
			) AS spieler_mit_verein
			, (SELECT COUNT(kontingent_id) FROM kontingente WHERE kontingente.event_id = events.event_id) AS kontingente
			, tournament_id, main_tournament_id
		FROM events
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
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
		$zz_setting['website_id'],
		$event['series_category_id'],
		$event['year']
	);
	$event['tournaments'] = wrap_db_fetch($sql, 'event_id');
	if ($series AND !$event['tournaments']) return false;
	parse_str($event['parameters'], $parameter);
	$event['kontingente'] = !empty($parameter['kontingent']) ? true : false;

	$event['turnierstart'] = 0;
	foreach ($event['tournaments'] AS $turnier) {
		$event['turnierstart'] += $turnier['turnierstart'];
		if ($turnier['kontingente']) $event['kontingente'] = true;
		if ($turnier['partien']) $event['pgn'] = true;
		if ($series) {
			// Keine Reihe im Terminkalender, also Daten aus Turnieren auslesen
			if (empty($event['date_begin'])) {
				$event['date_begin'] = $turnier['date_begin'];
			} elseif ($turnier['date_begin'] AND $turnier['date_begin'] < $event['date_begin']) {
				$event['date_begin'] = $turnier['date_begin'];
			}
			if (empty($event['date_end'])) {
				$event['date_end'] = $turnier['date_end'];
			} elseif ($turnier['date_end'] > $event['date_end']) {
				$event['date_end'] = $turnier['date_end'];
			}
		}
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
		if (!$series) break;
	}
	if ($series) {
		$event['duration'] = !empty($event['date_begin']) ? $event['date_begin'] : '';
		$event['duration'] .= '/'.(!empty($event['date_end']) ? $event['date_end'] : '');
	}

	// Kontingente?
	$sql = 'SELECT COUNT(regionalgruppe_id) FROM regionalgruppen
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
		$sql = 'SELECT COUNT(event_link_id) FROM events_links
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
	$page['extra']['realm'] = 'sports';
	$page['breadcrumbs'][] = '<a href="../">'.$event['year'].'</a>';
	$page['breadcrumbs'][] = $event['event'];
	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('tournamentseries', $event);
	return $page;
}
