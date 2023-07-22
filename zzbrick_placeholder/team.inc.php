<?php 

/**
 * tournaments module
 * placeholder function for team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2023 Gustaf Mossakowski
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
			, event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS event_idf
			, IFNULL(place, places.contact) AS turnierort
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, date_begin
			, events.identifier AS event_identifier
			, series_category_id
			, main_series.category_short AS main_series
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, club_contact_id AS contact_id, clubs.contact
			, clubs.identifier AS contact_identifier
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, IF(tournaments.gastspieler = "ja", 1, NULL) AS gastspieler
			, CONCAT("event_id:", events.event_id) AS event_rights
			, CONCAT("team_id:", team_id) AS team_rights
			, place_categories.parameters
		FROM teams
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN contacts places
			ON places.contact_id = events.place_contact_id
		LEFT JOIN addresses USING (contact_id)
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
	$sql = sprintf($sql, $year, wrap_db_escape($identifier), wrap_db_escape($team_idf));
	$team = wrap_db_fetch($sql);
	if (!$team) wrap_quit(404);

	if (!empty($brick['local_settings']['internal'])) {
		$zz_page['access'] = []; // remove rights from event placeholder
		$zz_page['access'][] = $team['team_rights'].'+'.$team['event_rights']; // first team, then event, for condition
		wrap_access_page(!empty($brick['local_settings']['access']) ? $brick['local_settings'] : $zz_page['db']['parameters'], $zz_page['access']);
	}

	if ($team['parameters']) {
		parse_str($team['parameters'], $parameters);
		$team += $parameters;
	}

	if (!empty($brick['local_settings']['internal']) AND empty($brick['local_settings']['no_team_breadcrumbs'])) {
		$bc_template = '<a href="'.wrap_setting('events_internal_path').'/%s/">%s</a>';

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
	}

	$brick['page']['dont_show_h1'] = true;
	$brick['page']['title'] = sprintf('%s %s: %s %s – ', 
		$team['event'], $team['year'], $team['team'], $team['team_no']
	);
	
	$brick['data'] = $team;
	return $brick;
}
