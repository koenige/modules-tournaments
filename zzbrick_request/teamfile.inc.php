<?php 

/**
 * tournaments module
 * send a team registration file
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * send a team registration file
 *
 * @param array $params
 * @return array
 */
function mod_tournaments_teamfile($params) {
	if (count($params) !== 3) return [];
	$filename = implode('/', $params).'.pdf';
	$team = $params[2];
	$suffix = '';
	$sql = 'SELECT category, parameters
		FROM categories
		WHERE main_category_id = /*_ID categories tournament-uploads _*/';
	$categories = wrap_db_fetch($sql, 'category_id');
	foreach ($categories as $category) {
		if (empty($category['parameters'])) continue;
		parse_str($category['parameters'], $parameters);
		if (empty($parameters['tournaments_path_suffix'])) continue;
		if (!str_ends_with($team, $parameters['tournaments_path_suffix'])) continue;
		$team = substr($team, 0, -strlen($parameters['tournaments_path_suffix']));
		$suffix = sprintf(' (%s)', $category['category']);
		break;
	}
	$sql = 'SELECT team_id, team, event, IFNULL(event_year, YEAR(date_begin)) AS year
		FROM teams LEFT JOIN events USING (event_id) WHERE teams.identifier = "%d/%s/%s"';
	$sql = sprintf($sql, $params[0], $params[1], $team);
	$team = wrap_db_fetch($sql);
	if (!$team) return false;
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
	$file['name'] = wrap_setting('tournaments_teams_dir').'/'.$filename;
	wrap_send_file($file);
}
