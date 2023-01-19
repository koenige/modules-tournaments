/**
 * events module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/events
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- tournaments_event_id --
SELECT event_id, identifier
, IF(date_begin > CURDATE(), 1, NULL) AS future_event
, IF(date_begin <= CURDATE() AND date_end >= CURDATE(), 1, NULL) AS running_event
, IF(IFNULL(date_end, date_begin) < CURDATE(), 1, NULL) AS past_event
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
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
LEFT JOIN categories eventtype
	ON events.event_category_id = eventtype.category_id
WHERE event_id = %d

-- tournaments_event --
SELECT event_id, identifier
, IF(date_begin > CURDATE(), 1, NULL) AS future_event
, IF(date_begin <= CURDATE() AND date_end >= CURDATE(), 1, NULL) AS running_event
, IF(IFNULL(date_end, date_begin) < CURDATE(), 1, NULL) AS past_event
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
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
LEFT JOIN categories eventtype
	ON events.event_category_id = eventtype.category_id
WHERE identifier = '%s'
