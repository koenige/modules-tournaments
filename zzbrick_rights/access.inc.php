<?php 

/**
 * tournaments module
 * Access functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Prüfe Zugriffsrechte auf Team, nur eigenes Team erlaubt
 *
 * @param int $team_id
 * @return bool
 */
function mf_tournaments_team_access($team_id, $status = ['Teilnehmer', 'Teilnahmeberechtigt']) {
	if (brick_access_rights('Webmaster')) return true;
	if (brick_access_rights('AK Spielbetrieb')) return true;
	if (brick_access_rights('Geschäftsstelle')) return true;
	$sql = 'SELECT CONCAT("event_id:", events.event_id) AS event_rights
		FROM events
		LEFT JOIN teams USING (event_id)
		WHERE team_id = %d';
	$sql = sprintf($sql, $team_id);
	$event_rights = wrap_db_fetch($sql, '', 'single value');
	if (brick_access_rights(['Organisator', 'Technik', 'Turnierleitung'], $event_rights)) return true;

	$teams = mf_tournaments_team_own($status);
	if (!in_array($team_id, $teams)) return false;
	return true;
}

/**
 * read a list of teams that are user’s own teams
 *
 * @param array $status
 * @return array
 */
function mf_tournaments_team_own($status = ['Teilnehmer', 'Teilnahmeberechtigt']) {
	if (empty($_SESSION['usergroup'][wrap_id('usergroups', 'team-organisator')])
		AND empty($_SESSION['usergroup'][wrap_id('usergroups', 'betreuer')])
	) {
		return [];
	}

	$sql = 'SELECT participation_id, team_id, team_status, usergroup_id, meldung
		FROM participations
		LEFT JOIN teams USING (team_id)
		WHERE usergroup_id IN (%d, %d)
		AND person_id = %d
		AND team_status IN ("%s")
	';
	$sql = sprintf($sql
		, wrap_id('usergroups', 'team-organisator')
		, wrap_id('usergroups', 'betreuer')
		, $_SESSION['person_id']
		, implode('","', $status)
	);
	$participations = wrap_db_fetch($sql, 'participation_id');
	$teams = [];
	foreach ($participations as $participation) {
		if ($participation['usergroup_id'] === wrap_id('usergroups', 'betreuer')) {
			if ($participation['meldung'] !== 'komplett') continue;
			if ($participation['team_status'] !== 'Teilnehmer') continue;
		}
		$teams[] = $participation['team_id'];
	}
	$teams = array_unique($teams);
	return $teams;
}
