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
	if (brick_access_rights(['Organisator', 'Technik'], $event_rights)) return true;

	$eigene_teams = mf_tournaments_team_own($status);
	if (!in_array($team_id, $eigene_teams)) return false;
	return true;
}

/**
 * read a list of teams that are user’s own teams
 *
 * @param array $status
 * @return array
 */
function mf_tournaments_team_own($status = ['Teilnehmer', 'Teilnahmeberechtigt']) {
	if (empty($_SESSION['usergroup'][wrap_id('usergroups', 'team-organisator')])) {
		return [];
	}

	$sql = 'SELECT team_id
		FROM participations
		LEFT JOIN teams USING (team_id)
		WHERE usergroup_id = %d
		AND person_id = %d
		AND team_status IN ("%s")
	';
	$sql = sprintf($sql
		, wrap_id('usergroups', 'team-organisator')
		, $_SESSION['person_id']
		, implode('","', $status)
	);
	$teams = wrap_db_fetch($sql, 'team_id', 'single value');
	return $teams;
}
