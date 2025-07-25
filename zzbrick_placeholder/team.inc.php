<?php 

/**
 * tournaments module
 * placeholder function for team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_placeholder_team($brick) {
	global $zz_page;

	if (!empty($brick['parameter'])) {
		if (substr_count($brick['parameter'], '/') !== 2) wrap_quit(404);
		list($year, $identifier, $team_idf) = explode('/', $brick['parameter']);
	} else {
		list($year, $identifier, $team_idf) = [$brick['vars'][1], $brick['vars'][2], $brick['vars'][3]];
	}

	$sql = 'SELECT team_id, team, team_no, meldung
			, events.event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS event_idf
			, IFNULL(place, places.contact) AS place
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS duration_days
			, date_begin
			, events.identifier AS event_identifier
			, series_category_id
			, main_series.category_short AS main_series
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, IF(LENGTH(main_series.path) > 7, CONCAT(IFNULL(events.event_year, YEAR(events.date_begin)), "/", SUBSTRING_INDEX(main_series.path, "/", -1)), NULL) AS main_event_path
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, turnierformen.parameters AS tournament_form_parameters
			, club_contact_id AS contact_id, clubs.contact
			, clubs.identifier AS contact_identifier
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, IF(tournaments.gastspieler = "ja", 1, NULL) AS gastspieler
			, CONCAT("event_id:", events.event_id) AS event_rights
			, CONCAT("team_id:", team_id) AS team_rights
			, place_categories.parameters
			, tournaments.urkunde_parameter AS tournament_parameters
			, series.parameters AS series_parameters
		FROM teams
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_contacts events_places
			ON events.event_id = events_places.event_id
			AND events_places.role_category_id = /*_ID categories roles/location _*/
			AND events_places.sequence = 1
		LEFT JOIN contacts places
			ON places.contact_id = events_places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN contacts clubs
			ON clubs.contact_id = teams.club_contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories place_categories
			ON places.contact_category_id = place_categories.category_id
		WHERE teams.identifier = "%d/%s/%s"
		AND spielfrei = "nein"';
	$sql_team = sprintf($sql, $year, wrap_db_escape($identifier), wrap_db_escape($team_idf));
	$team = wrap_db_fetch($sql_team);
	if (!$team) {
		if (!str_ends_with($team_idf, '.2')) {
			wrap_quit(404);
		}
		$team_idf_old = $team_idf;
		$team_idf = substr($team_idf, 0, -2);
		$sql_team = sprintf($sql, $year, wrap_db_escape($identifier), wrap_db_escape($team_idf));
		$team = wrap_db_fetch($sql_team);
		if (!$team) wrap_quit(404);
		$url = str_replace($team_idf_old, $team_idf, wrap_setting('request_uri'));
		return wrap_redirect($url, 301);
	}

	if (!empty($brick['local_settings']['internal'])) {
		$zz_page['access'] = []; // remove rights from event placeholder
		$zz_page['access'][] = $team['team_rights'].'+'.$team['event_rights']; // first team, then event, for condition
		wrap_access_page(!empty($brick['local_settings']['access']) ? $brick['local_settings'] : $zz_page['db']['parameters'], $zz_page['access']);
	}

	if ($team['parameters']) {
		parse_str($team['parameters'], $parameters);
		$team += $parameters;
		wrap_match_module_parameters('events', $team['parameters'], false);
	}
	if ($team['tournament_form_parameters']) {
		parse_str($team['tournament_form_parameters'], $team['tournament_form_parameters']);
		wrap_match_module_parameters('tournaments', $team['tournament_form_parameters']);
	}
	if ($team['series_parameters'])
		wrap_match_module_parameters('tournaments', $team['series_parameters'], false);
	if ($team['tournament_parameters'])
		wrap_match_module_parameters('tournaments', $team['tournament_parameters'], false);


	$bc_template = '<a href="'.wrap_setting('events_internal_path').'/%s/">%s</a>';
	if (!empty($brick['local_settings']['internal']) AND empty($brick['local_settings']['no_team_breadcrumbs'])) {
		$brick['page']['breadcrumbs'][] = sprintf(
			$bc_template, $team['year'], $team['year']
		);
		if ($team['main_series_path']) {
			$brick['page']['breadcrumbs'][] = sprintf(
				$bc_template, $team['year'].'/'.$team['main_series_path'], $team['main_series']
			);
		}
		$brick['page']['breadcrumbs'][] = sprintf(
			$bc_template, $team['event_identifier'], $team['event']
		);
		if (!empty($brick['vars'][4]) OR $brick['parameter'])
			$brick['page']['breadcrumbs'][] = sprintf(
				$bc_template, implode('/', [$year, $identifier, $team_idf]), $team['team']
			);
	} else {
		$brick['page']['breadcrumbs'][] = sprintf(
			$bc_template, $team['event_identifier'], $team['event']
		);
	}

	$brick['page']['dont_show_h1'] = true;
	$brick['page']['title'] = sprintf('%s %s: %s %s – ', 
		$team['event'], $team['year'], $team['team'], $team['team_no']
	);
	
	$brick['data'] = $team;
	return $brick;
}
