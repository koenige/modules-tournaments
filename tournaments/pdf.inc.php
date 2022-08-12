<?php 

/**
 * tournaments module
 * common functions for PDFs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get event for PDF
 *
 * fields just used by some functions:
 * - teampdfs: hinweis_meldebogen, dateiname, pseudo_dwz
 * - teampdf: hinweis_meldebogen
 * - teampdfsarrival: date_begin, ratings_updated, dateiname, pseudo_dwz
 * @param array $event_params
 * @return array
 */
function mf_tournaments_pdf_event($event_params) {
	$sql = 'SELECT event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, events.identifier AS event_identifier
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, bretter_min, bretter_max
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS dateiname
			, pseudo_dwz
			, date_begin
			, ratings_updated
			, hinweis_meldebogen
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $event_params[0], wrap_db_escape($event_params[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	$event = array_merge($event, mf_tournaments_pdf_event_accounts($event['event_id']));
	return $event;
}

/**
 * Liest Konten zu Termin aus
 *
 * @param int $event_id
 * @return array
 *		array 'konten_veranstalter',
 *		array 'konten_ausrichter'
 * @todo in Termin-Funktionsskript verschieben
 */
function mf_tournaments_pdf_event_accounts($event_id) {
	$sql = 'SELECT account_id, kontotyp
			, IFNULL(inhaber, contact) AS inhaber, iban, bic, institut
		FROM events_accounts
		LEFT JOIN accounts USING (account_id)
		LEFT JOIN contacts
			ON contacts.contact_id = accounts.owner_contact_id
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$konten = wrap_db_fetch($sql, 'account_id');
	$event = [];
	if (!$konten) return $event;
	foreach ($konten as $id => $konto) {
		$event['konten_'.strtolower($konto['kontotyp'])][$id] = $konto;
	}
	return $event;
}

/**
 * read teams per event for PDF
 *
 * @param array $params
 *		int event_id
 * 		string team_identifier
 * @return array
 */
function mf_tournaments_pdf_teams($params) {
	// check parameters, team_identifier is more specific
	if (!empty($params['team_identifier']))
		$where = sprintf('teams.identifier = "%s"', wrap_db_escape($params['team_identifier']));
	elseif (!empty($params['event_id']))
		$where = sprintf('event_id = %d', $params['event_id']);
	else
		return [];

	$sql = 'SELECT team_id, team, team_no, club_contact_id
			, teams.identifier AS team_identifier
			, meldung_datum
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, IF(datum_anreise AND uhrzeit_anreise AND datum_abreise AND uhrzeit_abreise, 1, NULL) AS reisedaten_komplett
			, meldung
		FROM teams
		WHERE %s
		AND spielfrei = "nein"
		AND team_status = "Teilnehmer"
		ORDER BY teams.identifier
	';
	$sql = sprintf($sql, $where);
	$teams = wrap_db_fetch($sql, 'team_id');
	$teams = mf_tournaments_clubs_to_federations($teams);
	return $teams;
}
