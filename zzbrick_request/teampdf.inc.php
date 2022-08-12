<?php

/**
 * tournaments module
 * printout of the team registration as a PDF
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ausdruck der Teammeldung als PDF
 *
 * @param array $vars
 * 		[0]: Jahr
 * 		[1]: event identifier
 * 		[2]: Teamkennung
 * @return array $page
 */
function mod_tournaments_teampdf($vars) {
	global $zz_setting;
	require_once __DIR__.'/../tournaments/pdf.inc.php';
	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	
	if (count($vars) !== 3) return false;
	$team_identifier = implode('/', $vars);
	array_pop($vars);

	$event = mf_tournaments_pdf_event($vars);
	if (!$event) return false;
	
	$params = [
		'team_identifier' => $team_identifier,
		'bookings' => true,
		'check_completion' => true
	];
	$event['teams'] = mf_tournaments_pdf_teams($event, $params);

	return my_team_pdf($event);	
}
