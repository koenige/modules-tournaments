<?php 

/**
 * tournaments module
 * search for participants (teams and names of people)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * search for participants (teams and names of people)
 *
 * @param array $params
 * @param array $settings
 */
function mod_tournaments_participantsearch($params, $settings, $event) {
	global $zz_setting;
	if (!empty($_GET['q'])) $zz_setting['cache'] = false;

	$internal = !empty($settings['internal']) ? true : false;
	if (count($params) === 3 AND $params[2] === 'suche') unset($params[2]);
	if (count($params) !== 2) return false;
	
	// Termin
	$sql = 'SELECT events.event_id
			, IF(ISNULL(main_series.category) OR main_series.category = "", series.category, main_series.category) AS main_series
			, IFNULL(main_series.category_short, series.category_short) AS main_series_short
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, SUBSTRING_INDEX(event_categories.path, "/", -1) AS event_category
		FROM events
		JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
			AND main_series.path != "reihen"
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories event_categories
			ON event_categories.category_id = events.event_category_id
		WHERE (main_series.path = "reihen/%s" OR SUBSTRING_INDEX(series.path, "/", -1) = "%s")
		AND IFNULL(event_year, YEAR(date_begin)) = %d
	';
	$sql = sprintf($sql, $zz_setting['website_id'], wrap_db_escape($params[1]), wrap_db_escape($params[1]), $params[0]);
	$events = wrap_db_fetch($sql, 'event_id');
	if (!$events) return false;
	$event = reset($events);
	$event['year'] = $params[0];
	if ($internal) $event['intern'] = true;
	
	// Mannschafts- oder Einzelturnier?
	$einzel = false;
	$mannschaft = false;
	$event['teilnehmer'] = false;
	foreach ($events as $einzeltermin) {
		if ($einzeltermin['teilnehmerliste']) $event['teilnehmer'] = true;
		switch ($einzeltermin['event_category']) {
		case 'einzel':
			$einzel = true;
			$event['teilnehmer'] = true;
			break;
		case 'mannschaft':
			$mannschaft = true;
			break;
		default:
			break;
		}
	}
	
	if (!empty($_GET['q']) AND $mannschaft) {
		$sql = 'SELECT team_id
				, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
				, teams.identifier AS team_identifier
				, event
				, events.identifier AS event_identifier
				, IF(teilnehmerliste = "ja", IF(team_status = "Teilnehmer", 1, NULL), NULL) AS teilnehmerliste
			FROM teams
			JOIN events USING (event_id)
			LEFT JOIN tournaments USING (event_id)
			WHERE event_id IN (%s)
			AND team_status IN ("Teilnehmer", "Teilnahmeberechtigt")
			AND team LIKE "%%%s%%"';
		$sql = sprintf($sql, implode(',', array_keys($events)), wrap_db_escape($_GET['q']));
		$event['teams'] = wrap_db_fetch($sql, 'team_id');
		if ($internal AND brick_access_rights('AK Spielbetrieb')) {
			foreach ($event['teams'] as $team_id => $team) {
				$event['teams'][$team_id]['teilnehmerliste'] = true;
				$event['teams'][$team_id]['intern'] = true;
			}
		}
		
		if ($event['teilnehmer']) {
			$sql = 'SELECT DISTINCT person_id
					, IF(ISNULL(t_vorname),
						contact,
						CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname)
					) AS person
					, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
					, teams.identifier AS team_identifier
					, events.identifier AS event_identifier
					, event
				FROM participations
				JOIN teams USING (team_id)
				JOIN events
					ON teams.event_id = events.event_id
				JOIN persons USING (person_id)
				JOIN contacts USING (contact_id)
				WHERE participations.event_id IN (%s)
				AND usergroup_id = %d
				AND NOT ISNULL(brett_no)
				AND (
					IF(ISNULL(t_vorname),
						contact,
						CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname)
					) LIKE "%%%s%%"
					OR IF(ISNULL(t_vorname), CONCAT(last_name, ",", first_name), CONCAT(t_nachname, ",", t_vorname)) LIKE "%%%s%%"
					OR IF(ISNULL(t_vorname), CONCAT(last_name, " ", first_name), CONCAT(t_nachname, " ", t_vorname)) LIKE "%%%s%%"
					OR contacts.identifier LIKE _latin1"%%%s%%"
				)
				AND teilnahme_status IN ("Teilnehmer", "angemeldet", "disqualifiziert")';
			$sql = sprintf($sql, implode(',', array_keys($events)), wrap_id('usergroups', 'spieler'),
				wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q'])
			);
			$event['spieler'] = wrap_db_fetch($sql, 'person_id');
		}
	}
	if (!empty($_GET['q']) AND $einzel) {
		$sql = 'SELECT DISTINCT participation_id, person_id
				, IF(ISNULL(t_vorname),
					contact,
					CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname)
				) AS person
				, events.identifier AS event_identifier
				, event
				, participations.setzliste_no
			FROM participations
			JOIN events USING (event_id)
			JOIN persons USING (person_id)
			JOIN contacts USING (contact_id)
			WHERE participations.event_id IN (%s)
			AND usergroup_id = %d
			AND (
				IF(ISNULL(t_vorname),
					contact,
					CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname)
				) LIKE "%%%s%%"
				OR IF(ISNULL(t_vorname), CONCAT(last_name, ",", first_name), CONCAT(t_nachname, ",", t_vorname)) LIKE "%%%s%%"
				OR IF(ISNULL(t_vorname), CONCAT(last_name, " ", first_name), CONCAT(t_nachname, " ", t_vorname)) LIKE "%%%s%%"
				OR contacts.identifier LIKE _latin1"%%%s%%"
			)
			AND teilnahme_status IN ("Teilnehmer", "angemeldet", "disqualifiziert")';
		$sql = sprintf($sql, implode(',', array_keys($events)), wrap_id('usergroups', 'spieler'),
			wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q']), wrap_db_escape($_GET['q'])
		);
		$event['spieler'] = wrap_db_fetch($sql, 'participation_id');
	}
	$event['q'] = isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '';
	$event['teamsuche'] = $mannschaft;

	$page['query_strings'] = ['q'];
	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	$page['breadcrumbs'][] = '<a href="../">'.$event['main_series'].'</a>';
	$page['breadcrumbs'][] = 'Suche';
	$page['dont_show_h1'] = true;
	$page['title'] = $event['main_series_short'].' '.$event['year'].', Suche nach Spielerinnen, Spielern oder Teams';
	$page['text'] = wrap_template('participantsearch', $event);
	return $page;
}
