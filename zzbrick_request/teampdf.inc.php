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
	$team_vars = implode('/', $vars);
	array_pop($vars);

	$event = mf_tournaments_pdf_event($vars);
	if (!$event) return false;
	
	$sql = 'SELECT teams.team_id, team, team_no
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, contacts.contact_id
			, teams.identifier AS team_identifier
			, meldung_datum
			, meldung
		FROM teams
		LEFT JOIN contacts
			ON teams.club_contact_id = contacts.contact_id
		WHERE teams.identifier = "%s"
		AND spielfrei = "nein"
	';
	$sql = sprintf($sql
		, wrap_db_escape($team_vars)
	);
	$event['teams'][0] = wrap_db_fetch($sql);
	if (!$event['teams'][0]) return false;
	$event['teams'][0] = mf_tournaments_clubs_to_federations($event['teams'][0], 'contact_id');

	if (!mf_tournaments_team_access($event['teams'][0]['team_id'], ['Teilnehmer'])) wrap_quit(403);

	if ($event['teams'][0]['datum_anreise'] AND $event['teams'][0]['uhrzeit_anreise']
		AND $event['teams'][0]['datum_abreise'] AND $event['teams'][0]['uhrzeit_abreise']) {
		$event['teams'][0]['reisedaten_komplett'] = true;	
	}

	// Buchungen
	$event['teams'][0] = array_merge(
		$event['teams'][0], mf_tournaments_team_bookings($event['teams'][0]['team_id'], $event)
	);
	
	// Team + Vereinsbetreuer auslesen
	$event['teams'][0] = array_merge(
		$event['teams'][0], mf_tournaments_team_participants([$event['teams'][0]['team_id'] => $event['teams'][0]['contact_id']], $event)
	);

	$event['teams'][0]['komplett'] = mf_tournaments_team_application_complete($event['teams'][0]);
	return my_team_pdf($event);	
}
