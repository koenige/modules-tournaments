/**
 * tournaments module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- tournaments_event_id --
SELECT event_id, identifier
, IF(date_begin > CURDATE(), 1, NULL) AS future_event
, IF(date_begin <= CURDATE() AND date_end >= CURDATE(), 1, NULL) AS running_event
, IF(IFNULL(date_end, date_begin) < CURDATE(), 1, NULL) AS past_event
, IF(IFNULL(date_end, date_begin) >= CURDATE(), 1, NULL) AS future_or_running_event
, tournament_id
, series.parameters AS series_parameters
, eventtype.parameters AS eventtype_parameters
, (SELECT COUNT(*) FROM access_codes WHERE event_id = events.event_id) AS access_codes
, (SELECT COUNT(*) FROM categories c WHERE main_category_id = series.category_id) AS series
, (SELECT COUNT(*) FROM events t
	LEFT JOIN categories t_series ON t.series_category_id = t_series.category_id
	LEFT JOIN categories t_eventtype ON t.event_category_id = t_eventtype.category_id
	WHERE t_series.main_category_id = events.series_category_id
	AND IFNULL(t.event_year, YEAR(t.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
	AND t_eventtype.parameters LIKE "%%&single=1%%"
) AS includes_single_tournaments
, (SELECT COUNT(*) FROM participations
	LEFT JOIN events p_events USING (event_id)
	LEFT JOIN categories p_series
		ON p_events.series_category_id = p_series.category_id
	LEFT JOIN events main_p_events
	ON main_p_events.series_category_id = p_series.main_category_id
	AND IFNULL(main_p_events.event_year, YEAR(main_p_events.date_begin)) = IFNULL(p_events.event_year, YEAR(p_events.date_begin))
	WHERE usergroup_id = /*_ID usergroups bewerber _*/
	AND main_p_events.event_id = events.event_id
) AS applicants
, (SELECT COUNT(*) FROM teams WHERE teams.event_id = events.event_id) AS teams
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
LEFT JOIN categories eventtype
	ON events.event_category_id = eventtype.category_id
WHERE event_id = %d;

-- tournaments_event --
SELECT event_id, identifier
, IF(date_begin > CURDATE(), 1, NULL) AS future_event
, IF(date_begin <= CURDATE() AND date_end >= CURDATE(), 1, NULL) AS running_event
, IF(IFNULL(date_end, date_begin) < CURDATE(), 1, NULL) AS past_event
, IF(IFNULL(date_end, date_begin) >= CURDATE(), 1, NULL) AS future_or_running_event
, tournament_id
, series.parameters AS series_parameters
, eventtype.parameters AS eventtype_parameters
, (SELECT COUNT(*) FROM access_codes WHERE event_id = events.event_id) AS access_codes
, (SELECT COUNT(*) FROM categories c WHERE main_category_id = series.category_id) AS series
, (SELECT COUNT(*) FROM events t
	LEFT JOIN categories t_series ON t.series_category_id = t_series.category_id
	LEFT JOIN categories t_eventtype ON t.event_category_id = t_eventtype.category_id
	WHERE t_series.main_category_id = events.series_category_id
	AND IFNULL(t.event_year, YEAR(t.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
	AND t_eventtype.parameters LIKE "%%&single=1%%"
) AS includes_single_tournaments
, (SELECT COUNT(*) FROM participations
	WHERE participations.event_id = events.event_id
	AND usergroup_id = /*_ID usergroups bewerber _*/
) AS applicants
, (SELECT COUNT(*) FROM teams WHERE teams.event_id = events.event_id) AS teams
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
LEFT JOIN categories eventtype
	ON events.event_category_id = eventtype.category_id
WHERE identifier = '%s';

-- tournaments_team_id --
SELECT team_id
, IF(meldung = 'komplett', 1, NULL) AS team_application_complete
, IF(meldung IN ('offen','teiloffen','komplett'), 1, NULL) AS team_application_active
FROM teams
WHERE team_id = %d;
