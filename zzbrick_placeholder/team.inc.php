<?php 

/**
 * tournaments module
 * placeholder function for team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_placeholder_team($brick) {
	global $zz_setting;
	if (!empty($brick['parameter'])) {
		list($year, $identifier, $team_idf) = explode('/', $brick['parameter']);
	} else {
		list($year, $identifier, $team_idf) = [$brick['vars'][1], $brick['vars'][2]];
	}
	require_once $zz_setting['inc'].'/custom/zzwrap/team.inc.php';
	$status = !empty($brick['local_settings']['status']) ? $brick['local_settings']['status'] : ['offen', 'teiloffen'];
	$team = my_team_form([$year, $identifier, $team_idf], $status);
	if (!$team) wrap_quit(404);

	if (!empty($brick['local_settings']['internal'])) {
		$bc_template = '<a href="'.$zz_setting['events_internal_path'].'/%s/">%s</a>';
	} else {
		$bc_template = '<a href="/%s/">%s</a>';
	}

	$brick['page']['breadcrumbs'][] = sprintf(
		$bc_template, $team['year'], $team['year']
	);
	if ($team['main_series_path']) {
		$brick['page']['breadcrumbs'][] = sprintf(
			$bc_template, $team['year'].'/'.$team['main_series_path'], $team['main_series']
		);
	}
	$brick['page']['breadcrumbs'][] = sprintf(
		$bc_template, $team['event_identifier'], $team['event']
	);
	$brick['page']['breadcrumbs'][] = sprintf(
		$bc_template, implode('/', [$year, $identifier, $team_idf]), $team['team']
	);

	$brick['page']['dont_show_h1'] = true;
	$brick['page']['extra']['realm'] = 'sports';
	$brick['page']['title'] = sprintf('%s %s: %s %s – ', 
		$team['event'], $team['year'], $team['team'], $team['team_no']
	);
	
	$brick['data'] = $team;
	return $brick;
}
