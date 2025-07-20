/**
 * tournaments module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- tournaments_event_id --
SELECT events.event_id, identifier
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
	LEFT JOIN events_categories t_ec
		ON t_ec.event_id = t.event_id
		AND t_ec.type_category_id = /*_ID categories events _*/
	LEFT JOIN categories t_eventtype ON t_ec.category_id = t_eventtype.category_id
	WHERE t_series.main_category_id = events.series_category_id
	AND IFNULL(t.event_year, YEAR(t.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
	AND t_eventtype.parameters LIKE "%%&tournaments_type_single=1%%"
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
LEFT JOIN events_categories
	ON events_categories.event_id = events.event_id
	AND events_categories.type_category_id = /*_ID categories events _*/
LEFT JOIN categories eventtype
	ON events_categories.category_id = eventtype.category_id
WHERE events.event_id = %d;

-- tournaments_event --
SELECT events.event_id, identifier
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
	LEFT JOIN events_categories t_ec
		ON t_ec.event_id = t.event_id
		AND t_ec.type_category_id = /*_ID categories events _*/
	LEFT JOIN categories t_eventtype ON t_ec.category_id = t_eventtype.category_id
	WHERE t_series.main_category_id = events.series_category_id
	AND IFNULL(t.event_year, YEAR(t.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
	AND t_eventtype.parameters LIKE "%%&tournaments_type_single=1%%"
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
LEFT JOIN events_categories
	ON events_categories.event_id = events.event_id
	AND events_categories.type_category_id = /*_ID categories events _*/
LEFT JOIN categories eventtype
	ON events_categories.category_id = eventtype.category_id
WHERE identifier = '%s';

-- /* @todo Punkte der Spieler berechnen (wie?) */ --
-- tournaments_games --
SELECT paarung_id, partie_id, partien.brett_no, partien.runde_no
	, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
		CASE IF(heim_spieler_farbe = "schwarz", schwarz_ergebnis, weiss_ergebnis)
		WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
		WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
		WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
		END
	) AS heim_ergebnis
	, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
		IF(heim_spieler_farbe = "schwarz", schwarz_ergebnis, weiss_ergebnis)
	) AS heim_ergebnis_numerisch
	, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
		CASE IF(heim_spieler_farbe = "schwarz", weiss_ergebnis, schwarz_ergebnis)
		WHEN 1.0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "+", 1)
		WHEN 0.5 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "=", 0.5)
		WHEN 0 THEN IF(partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/, "-", 0)
		END
	) AS auswaerts_ergebnis
	, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 0.5,
		IF(heim_spieler_farbe = "schwarz", weiss_ergebnis, schwarz_ergebnis)
	) AS auswaerts_ergebnis_numerisch
	, IF(weiss_ergebnis > schwarz_ergebnis, 1, NULL) AS weiss_gewinnt
	, IF(schwarz_ergebnis > weiss_ergebnis, 1, NULL) AS schwarz_gewinnt
	, @schwarz_spieler := IF(ISNULL(schwarz_status.t_vorname),
		black_contact.contact,
		CONCAT(schwarz_status.t_vorname, " ", IFNULL(CONCAT(schwarz_status.t_namenszusatz, " "), ""), schwarz_status.t_nachname)
	) AS player_black
	, @weiss_spieler := IF(ISNULL(weiss_status.t_vorname),
		white_contact.contact,
		CONCAT(weiss_status.t_vorname, " ", IFNULL(CONCAT(weiss_status.t_namenszusatz, " "), ""), weiss_status.t_nachname)
	) AS player_white
	, IFNULL(IF(heim_spieler_farbe = "schwarz", @schwarz_spieler, @weiss_spieler), "N. N.") AS heim_spieler
	, IFNULL(IF(heim_spieler_farbe = "schwarz", @weiss_spieler, @schwarz_spieler), "N. N.") AS auswaerts_spieler
	, weiss_person_id, schwarz_person_id
	, IF(heim_spieler_farbe = "schwarz",
		IF(schwarz_status.gastspieler = "ja", 1, NULL),
		IF(weiss_status.gastspieler = "ja", 1, NULL)
	) AS heim_gastspieler
	, IF(heim_spieler_farbe = "schwarz",
		IF(weiss_status.gastspieler = "ja", 1, NULL),
		IF(schwarz_status.gastspieler = "ja", 1, NULL)
	) AS auswaerts_gastspieler
	, IF(heim_spieler_farbe = "schwarz", "schwarz", "weiss") AS heim_farbe
	, IF(heim_spieler_farbe = "schwarz", "weiss", "schwarz") AS auswaerts_farbe
	, heim_wertung, auswaerts_wertung
	, IF(partiestatus_category_id = /*_ID categories partiestatus/haengepartie _*/, 1, NULL) AS haengepartie
	, categories.category AS partiestatus
	, IF(heim_spieler_farbe = "schwarz", schwarz_status.t_dwz, weiss_status.t_dwz) AS heim_dwz
	, IF(heim_spieler_farbe = "schwarz", weiss_status.t_dwz, schwarz_status.t_dwz) AS auswaerts_dwz
	, IF(heim_spieler_farbe = "schwarz", schwarz_status.t_elo, weiss_status.t_elo) AS heim_elo
	, IF(heim_spieler_farbe = "schwarz", weiss_status.t_elo, schwarz_status.t_elo) AS auswaerts_elo
	, IF(heim_spieler_farbe = "schwarz", schwarz_status.setzliste_no, weiss_status.setzliste_no) AS heim_setzliste_no
	, IF(heim_spieler_farbe = "schwarz", weiss_status.setzliste_no, schwarz_status.setzliste_no) AS auswaerts_setzliste_no
	, IF(NOT ISNULL(pgn), IF(partiestatus_category_id != /*_ID categories partiestatus/kampflos _*/, 1, NULL), NULL) AS partie
	, eco
	, (SELECT wertung FROM tabellenstaende
		LEFT JOIN tabellenstaende_wertungen USING (tabellenstand_id)
		WHERE runde_no = partien.runde_no - 1
		AND event_id = partien.event_id
		AND person_id = partien.schwarz_person_id
		AND wertung_category_id = /*_ID categories turnierwertungen/pkt _*/) AS schwarz_punkte
	, (SELECT wertung FROM tabellenstaende
		LEFT JOIN tabellenstaende_wertungen USING (tabellenstand_id)
		WHERE runde_no = partien.runde_no - 1
		AND event_id = partien.event_id
		AND person_id = partien.weiss_person_id
		AND wertung_category_id = /*_ID categories turnierwertungen/pkt _*/) AS weiss_punkte
	, partien.last_update
FROM partien
LEFT JOIN categories
	ON partien.partiestatus_category_id = categories.category_id
LEFT JOIN persons weiss
	ON weiss.person_id = partien.weiss_person_id
LEFT JOIN contacts white_contact
	ON weiss.contact_id = white_contact.contact_id
LEFT JOIN participations weiss_status
	ON weiss_status.contact_id = weiss.contact_id
	AND weiss_status.usergroup_id = /*_ID usergroups spieler _*/
	AND weiss_status.event_id = partien.event_id
LEFT JOIN persons schwarz
	ON schwarz.person_id = partien.schwarz_person_id
LEFT JOIN contacts black_contact
	ON schwarz.contact_id = black_contact.contact_id
LEFT JOIN participations schwarz_status
	ON schwarz_status.contact_id = schwarz.contact_id
	AND schwarz_status.usergroup_id = /*_ID usergroups spieler _*/
	AND schwarz_status.event_id = partien.event_id
WHERE partien.event_id = %d
AND %s
ORDER BY runde_no, IF(ISNULL(partien.brett_no), 1, 0)
	, partien.brett_no, (schwarz_punkte + weiss_punkte) DESC
	, IF(ISNULL(schwarz_status.setzliste_no), 1, NULL)
	, IF(ISNULL(weiss_status.setzliste_no), 1, NULL)
	, schwarz_status.setzliste_no + weiss_status.setzliste_no;

-- tournaments_team_id --
SELECT team_id
, IF(meldung = 'komplett', 1, NULL) AS team_application_complete
, IF(meldung IN ('offen','teiloffen','komplett'), 1, NULL) AS team_application_active
FROM teams
WHERE team_id = %d;

-- tournaments_zzform_event --
SELECT /*_PREFIX_*/events.event_id
	, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
	, identifier
FROM /*_PREFIX_*/events
LEFT JOIN /*_PREFIX_*/events_categories
	ON /*_PREFIX_*/events_categories.event_id = /*_PREFIX_*/events.event_id
	AND /*_PREFIX_*/events_categories.type_category_id = /*_ID categories events _*/
LEFT JOIN /*_PREFIX_*/categories
	ON /*_PREFIX_*/events_categories.category_id = /*_PREFIX_*/categories.category_id
WHERE /*_PREFIX_*/categories.parameters LIKE "%&tournament=1%"
ORDER BY identifier;
