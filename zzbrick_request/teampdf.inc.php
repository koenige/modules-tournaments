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
	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	
	if (count($vars) !== 3) return false;
	$team_vars = implode('/', $vars);
	array_pop($vars);
	$event_vars = implode('/', $vars);

	$sql = 'SELECT event_id, event
			, bretter_min, bretter_max
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, hinweis_meldebogen
			, events.identifier AS event_identifier
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($event_vars));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$event = array_merge($event, my_event_accounts($event['event_id']));
	
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

	if (!my_team_access($event['teams'][0]['team_id'], ['Teilnehmer'])) wrap_quit(403);

	if ($event['teams'][0]['datum_anreise'] AND $event['teams'][0]['uhrzeit_anreise']
		AND $event['teams'][0]['datum_abreise'] AND $event['teams'][0]['uhrzeit_abreise']) {
		$event['teams'][0]['reisedaten_komplett'] = true;	
	}

	// Buchungen
	$event['teams'][0] = array_merge(
		$event['teams'][0], my_team_buchungen($event['teams'][0]['team_id'], $event)
	);
	
	// Team + Vereinsbetreuer auslesen
	$event['teams'][0] = array_merge(
		$event['teams'][0], my_team_teilnehmer([$event['teams'][0]['team_id'] => $event['teams'][0]['contact_id']], $event)
	);

	$event['teams'][0]['komplett'] = my_team_meldung_komplett($event['teams'][0]);
	return my_team_pdf($event);	
}
