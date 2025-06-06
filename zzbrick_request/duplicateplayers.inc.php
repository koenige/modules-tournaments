<?php 

/**
 * tournaments module
 * find players that play in multiple tournaments of a series
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2016, 2019-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Doppelte Spieler finden
 *
 * @param array $vars
 *	[0]: Jahr des Turniers
 *	[1]: Kennung des Turniers
 * @return array $page
 */
function mod_tournaments_duplicateplayers($vars) {
	$sql = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year, main_series.category AS series
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(event_year, YEAR(date_begin)) = %d
	';
	$sql = sprintf($sql, wrap_db_escape($vars[1]), $vars[0]);
	$turniere = wrap_db_fetch($sql, 'event_id');
	if (!$turniere) return false;

	$sql = 'SELECT contact_id
		FROM participations
		WHERE usergroup_id = /*_ID usergroups spieler _*/
		AND event_id IN (%s)
		GROUP BY contact_id
		HAVING COUNT(contact_id) > 1
	';
	$sql = sprintf($sql, implode(',', array_keys($turniere)));
	$doppelte = wrap_db_fetch($sql, '_dummy_', 'single value');
	if (!$doppelte) {
		$data['keine_doppelten'] = true;
	} else {
		$sql = 'SELECT participation_id, contact_id
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), IFNULL(t_nachname, "")) AS spieler
				, event, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
				, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
				, participations.brett_no
				, participations.rang_no
				, events.identifier AS event_identifier
				, teams.identifier AS team_identifier
	 		FROM participations
	 		LEFT JOIN events USING (event_id)
	 		LEFT JOIN teams USING (team_id)
	 		WHERE contact_id IN (%s)
	 		AND usergroup_id = /*_ID usergroups spieler _*/
	 		AND events.event_id IN (%s)
	 		ORDER BY t_nachname, t_namenszusatz, t_vorname, team, team_no
	 	';
	 	$sql = sprintf($sql
	 		, implode(',', $doppelte)
			, implode(',', array_keys($turniere))
	 	);
	 	$data = wrap_db_fetch($sql, ['contact_id', 'participation_id'], 'list contact_id turniere');
	 	foreach ($data as $id => $spieler) {
	 		foreach ($spieler['turniere'] as $participation_id => $teilnahme) {
	 			$data[$id]['spieler'] = $teilnahme['spieler'];
	 		}
	 	}
	}
	
	$turnier = reset($turniere);
	
	$page['breadcrumbs'][]['title'] = 'Doppelt gemeldete Spieler';
	$page['title'] = $turnier['series'].' '.$turnier['year'].':<br> Doppelt gemeldete Spieler';
	$page['text'] = wrap_template('duplicateplayers', $data);
	return $page;
}
