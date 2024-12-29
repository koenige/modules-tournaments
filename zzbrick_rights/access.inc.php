<?php 

/**
 * tournaments module
 * Access functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022, 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


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
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN teams USING (team_id)
		WHERE usergroup_id IN (
			/*_ID usergroups team-organisator _*/, /*_ID usergroups betreuer _*/
		)
		AND person_id = %d
		AND team_status IN ("%s")
	';
	$sql = sprintf($sql
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
