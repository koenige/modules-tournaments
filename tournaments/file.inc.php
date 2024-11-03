<?php 

/**
 * tournaments module
 * file functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * send a team registration PDF file
 *
 * @param array $params
 * @return array
 */
function mf_tournaments_sendfile_teampdfs($params) {
	if (count($params) !== 4) wrap_quit(404);
	$filename = implode('/', $params);
	$team = substr($params[3], 0, strrpos($params[3], '.'));
	switch (substr($team, strrpos($team, '-') + 1)) {
		case 'ehrenkodex':
			$team = substr($team, 0, strrpos($team, '-'));
			$suffix = ' (Ehrenkodex)';
			break;
		case 'gast':
			$team = substr($team, 0, strrpos($team, '-'));
			$suffix = ' (Gast)';
			break;
		default:
			$suffix = '';
			break;
	}
	$sql = 'SELECT team_id, team, event, IFNULL(event_year, YEAR(date_begin)) AS year
		FROM teams LEFT JOIN events USING (event_id) WHERE teams.identifier = "%d/%s/%s"';
	$sql = sprintf($sql, $params[1], $params[2], $team);
	$team = wrap_db_fetch($sql);
	$file['send_as'] = 'Meldebogen '.$team['event'].' '.$team['year'].' '.$team['team'].$suffix.'.pdf';

	$rights = sprintf('event:%d/%s', $params[1], $params[2]);
	$access = false;
	if (wrap_access('tournaments_team_pdfs', $rights)) {
		$access = true;
	} else {
		wrap_include('zzbrick_rights/access', 'tournaments');
		$own_teams = mf_tournaments_team_own();
		if (in_array($team['team_id'], $own_teams)) $access = true;;
	}
	if (!$access) wrap_quit(403);

	$file['caching'] = false;
	$file['name'] = wrap_setting('media_folder').'/'.$filename;
	wrap_send_file($file);
}
