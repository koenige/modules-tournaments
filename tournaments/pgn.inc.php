<?php 

/**
 * tournaments module
 * PGN functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005, 2012-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Auslesen der Daten für eine PGN aus der Datenbank
 *
 * @param int $event_id
 * @param int $round_no (optional)
 * @param int $brett_no (optional)
 * @param int $tisch_no (optional, nur bei Mannschaftsturnieren)
 * @return array
 */
function mf_tournaments_pgn_db($event_id, $round_no = false, $brett_no = false, $tisch_no = false) {
	$where = [];
	if ($round_no) $where[] = sprintf('partien.runde_no = %d', $round_no);
	if ($brett_no) $where[] = sprintf('partien.brett_no = %d', $brett_no);
	if ($tisch_no) $where[] = sprintf('paarungen.tisch_no = %d', $tisch_no);
	
	wrap_db_charset('latin1');

	$sql = 'SELECT partien.partie_id
			, events.event, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
			, DATE_FORMAT(events.date_begin, "%%Y.%%m.%%d") AS EventDate
			, DATE_FORMAT(runden.date_begin, "%%Y.%%m.%%d") AS Date
			, IF(ISNULL(url), IF(LOCATE("&virtual=1", place_categories.parameters), (SELECT identification FROM eventdetails WHERE eventdetails.event_id = events.event_id AND active = "yes" LIMIT 1), place), url) AS Site
			, countries.ioc_code AS EventCountry
			, partien.runde_no AS Round
			, partien.brett_no AS Board
			, CONCAT(IFNULL(CONCAT(weiss.t_namenszusatz, " "), ""), weiss.t_nachname, ", ", weiss.t_vorname) AS White
			, CONCAT(IFNULL(CONCAT(schwarz.t_namenszusatz, " "), ""), schwarz.t_nachname, ", ", schwarz.t_vorname) AS Black
			, weiss_zeit AS WhiteClock
			, schwarz_zeit AS BlackClock
			, IFNULL(weiss.t_elo, weiss.t_dwz) AS WhiteElo
			, IFNULL(schwarz.t_elo, schwarz.t_dwz) AS BlackElo
			, halbzuege AS PlyCount
			, partien.ECO AS ECO
			, pgn AS moves
			, weiss.t_fidetitel AS WhiteTitle
			, schwarz.t_fidetitel AS BlackTitle
			, IF(ISNULL(weiss_ergebnis) AND ISNULL(schwarz_ergebnis), "*",
				CONCAT(CASE(weiss_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END,
				"-", CASE(schwarz_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END)) AS Result
			, partien.kommentar
			, IF(heim_spieler_farbe = "schwarz"
				, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), ""))
				, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), ""))
			) AS WhiteTeam
			, IF(heim_spieler_farbe = "schwarz"
				, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), ""))
				, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), ""))
			) AS BlackTeam
			, IF(vertauschte_farben = "ja", 1, NULL) AS vertauschte_farben
			, IF(vertauschte_farben = "ja", IF(ISNULL(weiss_ergebnis) AND ISNULL(schwarz_ergebnis), "*",
				CONCAT(CASE(schwarz_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END,
				"-", CASE(weiss_ergebnis) WHEN 1.0 THEN 1 WHEN 0.5 THEN "1/2" WHEN 0 THEN 0 END)), NULL) AS Result_vertauscht
			, weiss_fide_id.identifier AS WhiteFideId
			, schwarz_fide_id.identifier AS BlackFideId
			, tournaments.runden AS EventRounds
			, CONCAT(
				IF (LOCATE("pgn=", turnierformen.parameters),
					CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(turnierformen.parameters, "pgn=", -1), "&", 1), "-"), ""
				),
				SUBSTRING_INDEX(SUBSTRING_INDEX(modi.parameters, "pgn=", -1), "&", 1)
			) AS EventType
			, IF (LOCATE("pgn=", partiestatus.parameters),
				SUBSTRING_INDEX(SUBSTRING_INDEX(partiestatus.parameters, "pgn=", -1), "&", 1), ""
			) AS Termination
			, paarungen.tisch_no AS `Table`
		FROM partien
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories modi
			ON tournaments.modus_category_id = modi.category_id
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories partiestatus
			ON partien.partiestatus_category_id = partiestatus.category_id
		LEFT JOIN events runden
			ON events.event_id = runden.main_event_id
			AND runden.runde_no = partien.runde_no
		LEFT JOIN events_contacts events_places
			ON events.event_id = events_places.event_id
			AND events_places.role_category_id = /*_ID categories roles/location _*/
			AND events_places.sequence = 1
		LEFT JOIN contacts places
			ON events_places.contact_id = places.contact_id
		LEFT JOIN categories place_categories
			ON places.contact_category_id = place_categories.category_id
		LEFT JOIN addresses
			ON events_places.contact_id = addresses.contact_id
		LEFT JOIN countries
			ON addresses.country_id = countries.country_id
		LEFT JOIN paarungen USING (paarung_id)
		LEFT JOIN teams heim_teams
			ON paarungen.heim_team_id = heim_teams.team_id
		LEFT JOIN teams auswaerts_teams
			ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
		LEFT JOIN persons white_persons
			ON white_persons.person_id = partien.weiss_person_id
		LEFT JOIN persons black_persons
			ON black_persons.person_id = partien.schwarz_person_id
		JOIN participations weiss
			ON white_persons.contact_id = weiss.contact_id AND weiss.usergroup_id = /*_ID usergroups spieler _*/
			AND (ISNULL(weiss.team_id) OR weiss.team_id = IF(heim_spieler_farbe = "schwarz", auswaerts_teams.team_id, heim_teams.team_id))
			AND weiss.event_id = partien.event_id
		JOIN participations schwarz
			ON black_persons.contact_id = schwarz.contact_id AND schwarz.usergroup_id = /*_ID usergroups spieler _*/
			AND (ISNULL(schwarz.team_id) OR schwarz.team_id = IF(heim_spieler_farbe = "schwarz", heim_teams.team_id, auswaerts_teams.team_id))
			AND schwarz.event_id = partien.event_id
		LEFT JOIN contacts_identifiers weiss_fide_id
			ON weiss_fide_id.contact_id = white_persons.contact_id
			AND weiss_fide_id.current = "yes"
			AND weiss_fide_id.identifier_category_id = /*_ID categories identifiers/id_fide _*/
		LEFT JOIN contacts_identifiers schwarz_fide_id
			ON schwarz_fide_id.contact_id = black_persons.contact_id
			AND schwarz_fide_id.current = "yes"
			AND schwarz_fide_id.identifier_category_id = /*_ID categories identifiers/id_fide _*/
		WHERE events.event_id = (%d)
		%s
		ORDER BY events.identifier, partien.runde_no, paarungen.tisch_no, partien.brett_no
	';
	$sql = sprintf($sql,
		$event_id,
		$where ? ' AND '.implode(' AND ', $where) : ''
	);
	$games = wrap_db_fetch($sql, 'partie_id');
	$games = mf_tournaments_pgn_cleanup($games);
	return $games;
}

function mf_tournaments_pgn_cleanup($games) {
	return $games;

	// @disabled
	foreach ($games as $partie_id => $partie) {
		if (empty($partie['moves'])) continue;
		$games[$partie_id]['moves'] = preg_replace('/{\[\%clk \d+:\d+:\d+\]} /', '', $partie['moves']);
		$games[$partie_id]['moves'] = preg_replace('/{\[\%emt \d+:\d+:\d+\]} /', '', $partie['moves']);
	}
	return $games;
}
