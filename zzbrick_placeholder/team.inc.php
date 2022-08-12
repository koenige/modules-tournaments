<?php 

/**
 * tournaments module
 * placeholder function for team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_placeholder_team($brick) {
	global $zz_setting;
	if (!empty($brick['parameter'])) {
		list($year, $identifier, $team_idf) = explode('/', $brick['parameter']);
	} else {
		list($year, $identifier, $team_idf) = [$brick['vars'][1], $brick['vars'][2]];
	}

	$sql = 'SELECT team_id, team, team_no, meldung
			, event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year
			, IFNULL(place, places.contact) AS turnierort
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, events.identifier AS event_identifier
			, series_category_id
			, main_series.category_short AS main_series
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, club_contact_id AS contact_id, clubs.contact
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, IF(tournaments.gastspieler = "ja", 1, NULL) AS gastspieler
			, CONCAT("event_id:", events.event_id) AS event_rights
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
		WHERE teams.identifier = "%d/%s/%s"';
	$sql = sprintf($sql, $year, wrap_db_escape($identifier), wrap_db_escape($team_idf));
	$team = wrap_db_fetch($sql);
	if (!$team) wrap_quit(404);

	$status = !empty($brick['local_settings']['status']) ? $brick['local_settings']['status'] : ['offen', 'teiloffen'];
	if (!in_array($team['meldung'], $status)) wrap_quit(403);

	if (!mf_tournaments_team_access($team['team_id'], ['Teilnehmer'])) wrap_quit(403);

	if ($team['parameters']) {
		parse_str($team['parameters'], $parameters);
		$team += $parameters;
	}

	if (!empty($brick['local_settings']['internal'])) {
		$bc_template = '<a href="'.$zz_setting['events_internal_path'].'/%s/">%s</a>';
	} else {
		$bc_template = '<a href="/%s/">%s</a>';
	}

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
	$brick['page']['breadcrumbs'][] = sprintf(
		$bc_template, implode('/', [$year, $identifier, $team_idf]), $team['team']
	);

	$brick['page']['dont_show_h1'] = true;
	$brick['page']['extra']['realm'] = 'sports';
	$brick['page']['title'] = sprintf('%s %s: %s %s – ', 
		$team['event'], $team['year'], $team['team'], $team['team_no']
	);
	
	$brick['data'] = $team;
	return $brick;
}
