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
, (SELECT COUNT(*) FROM access_codes WHERE event_id = events.event_id) AS access_codes
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
WHERE event_id = %d

-- tournaments_event --
SELECT event_id, identifier
, IF(date_begin > CURDATE(), 1, NULL) AS future_event
, IF(date_begin <= CURDATE() AND date_end >= CURDATE(), 1, NULL) AS running_event
, IF(IFNULL(date_end, date_begin) < CURDATE(), 1, NULL) AS past_event
, tournament_id
, series.parameters AS series_parameters
, (SELECT COUNT(*) FROM access_codes WHERE event_id = events.event_id) AS access_codes
FROM events
LEFT JOIN tournaments USING (event_id)
LEFT JOIN categories series
	ON events.series_category_id = series.category_id
WHERE identifier = '%s'
