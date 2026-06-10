<?php 

/**
 * tournaments module
 * certificate functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * tournament fields for certificates
 *
 * Hook `certificate` for the certificates PDF brick.
 *
 * @param array $event
 * @return array $event
 */
function mf_tournaments_certificate($event) {
	$sql = 'SELECT runden
			, tournaments.tabellenstaende, alter_max AS age_max
			, IF(tournaments.geschlecht = "w", 1, NULL) AS weiblich
		FROM tournaments
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event += wrap_db_fetch($sql);

	if (!empty($event['tournament_parameter'])) {
		parse_str($event['tournament_parameter'], $parameter);
		$event += $parameter;
	}
	unset($event['tournament_parameter']);
	return $event;
}

/**
 * check whether tournament certificate hooks apply to this event
 *
 * @param array $event unused; required for the certificate hook signature
 * @return bool
 */
function mf_tournaments_certificate_applies($event) {
	if (wrap_setting('tournaments_type_single')) return true;
	if (wrap_setting('tournaments_type_team')) return true;
	return false;
}

/**
 * certificate type slugs for tournaments
 *
 * @param array $event
 * @return array
 */
function mf_tournaments_certificate_types($event) {
	$possible_types = ['platz'];
	if (empty($event['tabellenstaende'])) return $possible_types;

	// @todo currently, only 'w' for female is supported
	$tabellenstaende = explode(',', $event['tabellenstaende']);
	foreach ($tabellenstaende as $tabellenstand) {
		if (!$tabellenstand) continue;
		$possible_types[] = 'platz-'.$tabellenstand;
	}
	return $possible_types;
}

/**
 * participant data for tournament certificates
 *
 * @param array $event
 * @param array $certificate
 * @return array [$data, $event] lines keyed by person_id or team_id, and updated $event
 */
function mf_tournaments_certificate_data($event, $certificate) {
	switch ($certificate['type_filter'] ?? '') {
		case 'w':
			if (wrap_setting('certificates_placement_count_female'))
				wrap_setting('certificates_placement_count', wrap_setting('certificates_placement_count_female'));
			$event['weiblich'] = true;
			break;
	}
	$filter = mf_tournaments_standings_filter($certificate['type_filter'] ?? false);
	if ($filter['error']) return [[], $event];
	$certificate['sql_where'] = array_merge($certificate['sql_where'], $filter['where']);

	if (wrap_setting('tournaments_type_team')) {
		$data = mf_tournaments_certificate_data_team($event, $certificate);
		$event['organisation_prefix'] = 'mit ';
	} else {
		$data = mf_tournaments_certificate_data_single($event, $certificate);
		$i = 1;
		foreach ($data as $person_id => $person) {
			if ($certificate['type'] === 'platz' AND !empty($filter['kennung'])) {
				$data[$person_id]['rang'] = $i;
				$i++;
			} else {
				$data[$person_id]['rang'] = $person['rank_no'];
			}
		}
	}
	
	return [$data, $event];
}

/**
 * participant data for team tournament certificates
 *
 * @param array $event
 * @param array $certificate
 * @return array teams keyed by team_id
 */
function mf_tournaments_certificate_data_team($event, $certificate) {
	$sql = 'SELECT teams.team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS spieler
			, (SELECT
				GROUP_CONCAT(CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) ORDER BY brett_no SEPARATOR ", ") AS spieler
				FROM participations
				WHERE participations.team_id = teams.team_id
				AND NOT ISNULL(brett_no)) AS verein
			, standings.rank_no
			, standings.rank_no AS rang
		FROM teams
		LEFT JOIN standings
			ON standings.team_id = teams.team_id
			AND standings.runde_no = %d
		WHERE teams.event_id = %d
		ORDER BY rank_no, team, team_no';
	$sql = sprintf($sql, $event['runden'], $event['event_id']);
	return wrap_db_fetch($sql, 'team_id');
	// @todo $certificate['sql_where']
	// @todo ORDER BY
}

/**
 * participant data for single tournament certificates
 *
 * @param array $event
 * @param array $certificate
 * @return array persons keyed by person_id
 */
function mf_tournaments_certificate_data_single($event, $certificate) {
	if ($certificate['type'] === 'platz') {
		$order_by_limit = 'ORDER BY rank_no, t_nachname, t_vorname
			LIMIT /*_SETTING certificates_placement_count _*/; ';
	} else {
		$order_by_limit = 'ORDER BY t_nachname, t_vorname, contact_id';
	}
	$sql = 'SELECT persons.person_id
			, CONCAT(participations.t_vorname, " ", IFNULL(CONCAT(participations.t_namenszusatz, " "), ""), participations.t_nachname) AS spieler
			, CONCAT(participations.t_vorname, " ", IFNULL(CONCAT(participations.t_namenszusatz, " "), "")) AS vorname
			, participations.t_nachname AS nachname
			, t_verein AS verein
			, urkundentext
			, standings.rank_no
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN standings
			ON standings.person_id = persons.person_id
			AND standings.event_id = participations.event_id
			AND standings.runde_no = %d
		WHERE participations.event_id = %d AND usergroup_id = /*_ID usergroups spieler _*/
		AND NOT ISNULL(participations.contact_id)
		AND participations.status_category_id = /*_ID categories participation-status/participant _*/
		%s
		%s
	';
	$sql = sprintf($sql, $event['runden']
		, $event['event_id']
		, $certificate['sql_where'] ? ' AND '.implode(' AND ', $certificate['sql_where']) : ''
		, $order_by_limit
	);
	return wrap_db_fetch($sql, 'person_id');
}
