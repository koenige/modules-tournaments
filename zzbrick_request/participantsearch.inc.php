<?php 

/**
 * tournaments module
 * search for participants (teams and names of people)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * search for participants (teams and names of people)
 *
 * @param array $params
 * @param array $settings
 * @param array $data
 * @return array
 */
function mod_tournaments_participantsearch($params, $settings, $data) {
	if (!empty($_GET['q'])) {
		wrap_setting('cache', false);
		if (!empty($_SERVER['HTTP_USER_AGENT']) AND strstr($_SERVER['HTTP_USER_AGENT'], 'bot'))
			wrap_quit(403, wrap_text('Bots are not allowed to perform searches.'));
	}

	$internal = $settings['internal'] ?? false;
	if (count($params) === 3 AND $params[2] === 'suche') unset($params[2]);
	if (count($params) !== 2) return false;
	
	$sql = 'SELECT events.event_id
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, events_categories.category_id
		FROM events
		JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = /*_SETTING website_id _*/
		LEFT JOIN events_categories
			ON events_categories.event_id = events.event_id
			AND events_categories.type_category_id = /*_ID categories events _*/
		WHERE events.event_id = %d OR main_event_id = %d
		AND events.event_category_id = /*_ID categories event/event _*/
	';
	$sql = sprintf($sql, $data['event_id'], $data['event_id']);
	$events = wrap_db_fetch($sql, 'event_id');
	if (!$events) return false;
	if ($internal) $data['intern'] = true;
	
	// Mannschafts- oder Einzelturnier?
	$einzel = false;
	$mannschaft = false;
	$data['teilnehmer'] = false;
	foreach ($events as $event) {
		if ($event['teilnehmerliste']) $data['teilnehmer'] = true;
		switch ($event['category_id']) {
		case wrap_category_id('events/single'):
			$einzel = true;
			$data['teilnehmer'] = true;
			break;
		case wrap_category_id('events/team'):
			$mannschaft = true;
			break;
		default:
			break;
		}
	}
	$data['q'] = (isset($_GET['q']) AND !is_array($_GET['q'])) ? htmlspecialchars($_GET['q']) : '';
	$data['q'] = trim($data['q']);
	
	if ($data['q'] AND $mannschaft) {
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
			AND spielfrei = "nein"
			AND team LIKE "%%%s%%"';
		$sql = sprintf($sql, implode(',', array_keys($events)), wrap_db_escape($data['q']));
		$data['teams'] = wrap_db_fetch($sql, 'team_id');
		if ($internal AND wrap_access('tournaments_teams')) {
			foreach ($data['teams'] as $team_id => $team) {
				$data['teams'][$team_id]['teilnehmerliste'] = true;
				$data['teams'][$team_id]['intern'] = true;
			}
		}
		
		if ($data['teilnehmer']) {
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
				JOIN persons USING (contact_id)
				JOIN contacts USING (contact_id)
				WHERE participations.event_id IN (%s)
				AND usergroup_id = /*_ID usergroups spieler _*/
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
				AND status_category_id IN (
					/*_ID categories participation-status/participant _*/,
					/*_ID categories participation-status/verified _*/,
					/*_ID categories participation-status/disqualified _*/
				)';
			$sql = sprintf($sql
				, implode(',', array_keys($events))
				, wrap_db_escape($data['q'])
				, wrap_db_escape($data['q'])
				, wrap_db_escape($data['q'])
				, wrap_db_escape($data['q'])
			);
			$data['spieler'] = wrap_db_fetch($sql, 'person_id');
		}
	}
	if ($data['q'] AND $einzel) {
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
			JOIN persons USING (contact_id)
			JOIN contacts USING (contact_id)
			WHERE participations.event_id IN (%s)
			AND usergroup_id = /*_ID usergroups spieler _*/
			AND (
				IF(ISNULL(t_vorname),
					contact,
					CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname)
				) LIKE "%%%s%%"
				OR IF(ISNULL(t_vorname), CONCAT(last_name, ",", first_name), CONCAT(t_nachname, ",", t_vorname)) LIKE "%%%s%%"
				OR IF(ISNULL(t_vorname), CONCAT(last_name, " ", first_name), CONCAT(t_nachname, " ", t_vorname)) LIKE "%%%s%%"
				OR contacts.identifier LIKE _latin1"%%%s%%"
			)
			AND status_category_id IN (
				/*_ID categories participation-status/participant _*/,
				/*_ID categories participation-status/verified _*/,
				/*_ID categories participation-status/disqualified _*/
			)';
		$sql = sprintf($sql
			, implode(',', array_keys($events))
			, wrap_db_escape($data['q'])
			, wrap_db_escape($data['q'])
			, wrap_db_escape($data['q'])
			, wrap_db_escape($data['q'])
		);
		$data['spieler'] = wrap_db_fetch($sql, 'participation_id');
	}
	$data['teamsuche'] = $mannschaft;

	$page['query_strings'] = ['q'];
	$page['breadcrumbs'][]['title'] = 'Suche';
	$page['dont_show_h1'] = true;
	$page['title'] = $data['series_short'].' '.$data['year'].', Suche nach Spielerinnen, Spielern oder Teams';
	$page['text'] = wrap_template('participantsearch', $data);
	return $page;
}
