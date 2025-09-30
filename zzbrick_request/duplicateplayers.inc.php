<?php 

/**
 * tournaments module
 * find players that play in multiple tournaments of a series
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2016, 2019-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Doppelte Spieler finden
 *
 * @param array $params
 * @param array $settings
 * @param array $event
 * @return array $page
 */
function mod_tournaments_duplicateplayers($params, $settings, $event) {
	$sql = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE main_event_id = %d
		AND event_category_id = /*_ID categories event/event _*/
		ORDER BY series.sequence, events.identifier
	';
	$sql = sprintf($sql, $event['event_id']);
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) return false;

	$sql = 'SELECT contact_id
		FROM participations
		WHERE usergroup_id = /*_ID usergroups spieler _*/
		AND event_id IN (%s)
		GROUP BY contact_id
		HAVING COUNT(contact_id) > 1
	';
	$sql = sprintf($sql, implode(',', array_keys($tournaments)));
	$duplicates = wrap_db_fetch($sql, '_dummy_', 'single value');
	if (!$duplicates) {
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
	 		, implode(',', $duplicates)
			, implode(',', array_keys($tournaments))
	 	);
	 	$data = wrap_db_fetch($sql, ['contact_id', 'participation_id'], 'list contact_id turniere');
	 	foreach ($data as $id => $spieler) {
	 		foreach ($spieler['turniere'] as $participation_id => $teilnahme) {
	 			$data[$id]['spieler'] = $teilnahme['spieler'];
	 		}
	 	}
	}
	
	$page['breadcrumbs'][]['title'] = 'Doppelt gemeldete Spieler';
	$page['title'] = $event['main_series_long'].' '.$event['year'].':<br> Doppelt gemeldete Spieler';
	$page['text'] = wrap_template('duplicateplayers', $data);
	return $page;
}
