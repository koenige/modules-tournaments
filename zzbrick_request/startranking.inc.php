<?php 

/**
 * tournaments module
 * Starting ranking
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_startranking($vars, $settings, $event) {
	if (empty($event)) return false;
	
	$sql = 'SELECT latitude, longitude
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste, pseudo_dwz
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses USING (contact_id)
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event += wrap_db_fetch($sql);

	wrap_setting('logfile_name', $event['identifier']);
	$event[str_replace('-', '_', $event['turnierform'])] = true;

	$page['dont_show_h1'] = true;

	$meldeliste = false;
	if ($event['turnierform'] === 'e') {
		$event = mod_tournaments_startranking_single($event);
		if (!$event['spieler']) return false;
		foreach ($event['spieler'] as $spieler) {
			if ($spieler['status_category_id'].'' !== wrap_category_id('participation-status/verified').'') continue;
			$meldeliste = true;
			break;
		}
		if ($meldeliste) {
			foreach (array_keys($event['spieler']) as $person_id) {
				$event['spieler'][$person_id]['meldeliste'] = true;
			}
		}
		if ($event['latitude']) {
			$page['head'] = wrap_template('termin-map-head');
			$event['map'] = my_teilnehmerkarte($event);
		}
	} else {
		$event = mod_tournaments_startranking_team($event);
	}
	if ($meldeliste) {
		$page['title'] = $event['event'].' '.$event['year'].': Meldeliste';
		$page['breadcrumbs'][]['title'] = 'Meldeliste';
		$event['meldeliste'] = true;
		$event['meldungen'] = !empty($event['spieler']) ? count($event['spieler']) : count($event['teams']);
	} else {
		$page['title'] = $event['event'].' '.$event['year'].': Startrangliste';
	}

	if ($event['turnierform'] === 'e') {
		$page['text'] = wrap_template('startranking-single', $event);
	} else {
		$page['text'] = wrap_template('startranking-team', $event);
	}
	return $page;
}

function mod_tournaments_startranking_single($event) {
	// @todo Sortierung nach DWZ oder Elo, je nach Turniereinstellung
	$sql = 'SELECT person_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
			, CONCAT(t_nachname, ", ", t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), "")) AS nachname_vorname
			, t_extra
			, t_verein
			, t_dwz, t_elo, t_fidetitel
			, setzliste_no
			, latitude, longitude
			, status_category_id
			, DATE_FORMAT(entry_date, "%%d.%%m %%H:%%i") AS entry_date
			, entry_date AS entry_date_raw
			, IF(SUBSTRING(qualification.event, 1, LENGTH(events.event)) = events.event
				, SUBSTRING(qualification.event, LENGTH(events.event)+1), qualification.event
			) AS qualification_event
			, YEAR(qualification.date_begin) AS qualification_year
			, qualification.identifier AS qualification_event_identifier
			, qualification
			, participations.club_contact_id
		FROM participations
		JOIN persons USING (contact_id)
		LEFT JOIN contacts organisationen
			ON participations.club_contact_id = organisationen.contact_id
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = organisationen.contact_id
			AND contacts_contacts.relation_category_id = %d
			AND contacts_contacts.published = "yes"
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN events qualification
			ON participations.qualification_event_id = qualification.event_id
		WHERE events.event_id = %d
		AND usergroup_id = %d
		AND status_category_id IN (%s%d, %d, %d)
		ORDER BY setzliste_no, IFNULL(t_dwz, t_elo) DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql
		, wrap_category_id('relation/venue')
		, $event['event_id']
		, wrap_id('usergroups', 'spieler')
		, ($event['date_end'] >= date('Y-m-d')) ? sprintf('%d, ', wrap_category_id('participation-status/verified')) : ''
		, wrap_category_id('participation-status/participant')
		, wrap_category_id('participation-status/disqualified')
		, wrap_category_id('participation-status/blocked')
	);
	$event['spieler'] = wrap_db_fetch($sql, 'person_id');
	if (!$event['spieler']) return $event;
	$event['spieler'] = mf_tournaments_clubs_to_federations($event['spieler'], 'club_contact_id');
	$event['spieler'] = mf_tournaments_person_identifiers($event['spieler'], ['fide-id', 'zps']);
	$event['zeige_dwz'] = false;
	$event['zeige_elo'] = false;
	$event['zeige_titel'] = false;
	foreach ($event['spieler'] as $person_id => $spieler) {
		$event['spieler'][$person_id]['main_event_path'] = $event['main_event_path'];
		if ($spieler['qualification_event']) $event['qualification_col'] = true;
		if ($spieler['t_fidetitel']) $event['zeige_titel'] = true;
		if ($spieler['t_elo']) $event['zeige_elo'] = true;
		if ($spieler['t_dwz']) $event['zeige_dwz'] = true;
		if (!$spieler['t_fidetitel']) continue;
		$event['spieler'][$person_id]['fidetitel_lang'] = mf_tournaments_fide_title($spieler['t_fidetitel']);
	}
	foreach ($event['spieler'] as $person_id => $spieler) {
		if ($event['zeige_dwz']) $event['spieler'][$person_id]['zeige_dwz'] = true;
		if ($event['zeige_elo']) $event['spieler'][$person_id]['zeige_elo'] = true;
		if ($event['zeige_titel']) $event['spieler'][$person_id]['zeige_titel'] = true;
		if (!empty($event['qualification_col'])) $event['spieler'][$person_id]['qualification_col'] = true;
	}
	return $event;
}
	
function mod_tournaments_startranking_team($event) {
	$sql = 'SELECT team_id
			, team, team_no
			, IF(NOT ISNULL(teams.setzliste_no), teams.identifier, "") AS team_identifier, team_status
			, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
			, latitude, longitude, setzliste_no
			, eintrag_datum
			, teams.club_contact_id
		FROM teams
		LEFT JOIN contacts organisationen
			ON teams.club_contact_id = organisationen.contact_id
		LEFT JOIN contacts_contacts
			ON contacts_contacts.main_contact_id = organisationen.contact_id
			AND contacts_contacts.relation_category_id = %d
			AND contacts_contacts.published = "yes"
		LEFT JOIN contacts places
			ON contacts_contacts.contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE event_id = %d
		AND team_status = "Teilnehmer"
		AND spielfrei = "nein"
		ORDER BY setzliste_no, place, team';
	$sql = sprintf($sql
		, wrap_category_id('relation/venue')
		, $event['event_id']
	);
	// @todo Klären, was passiert wenn mehr als 1 Ort zu Verein in Datenbank! (Reihenfolge-Feld einführen)
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	if (!$event['teams']) wrap_quit(404); // es liegt noch keine Rangliste vor.
	$event['teams'] = mf_tournaments_clubs_to_federations($event['teams'], 'club_contact_id');
	$event['meldeliste'] = false;
	foreach (array_keys($event['teams']) AS $team_id) {
		$event['teams'][$team_id]['main_event_path'] = $event['main_event_path'];
		// Meldelistestatus nur bei Terminen, die noch nicht zuende sind
		if (empty($event['teams'][$team_id]['setzliste_no']) AND $event['date_end'] > date('Y-m-d')) $event['meldeliste'] = true;
		$event['teams'][$team_id][str_replace('-', '_', $event['turnierform'])] = true;
		if (!empty($event['teams'][$team_id]['country'])) $event['country'] = true;
	}

	$dwz_sortierung = false;
	if ($event['teilnehmerliste']) {
		$dwz_sortierung = true;
		$erstes_team = current($event['teams']);
		if ($erstes_team['setzliste_no']) $dwz_sortierung = false;

		list($event['dwz_schnitt'], $event['teams']) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $event['teams'], $event['bretter_min'], $event['pseudo_dwz']);
	}

	foreach ($event['teams'] AS $key => $row) {
		if ($event['meldeliste']) $event['teams'][$key]['meldeliste'] = true;
		if ($dwz_sortierung) {
			$teamname[$key] = $row['place'];
			$verband[$key] = $row['country'] ?? '';
			$schnitt[$key] = $row['dwz_schnitt'] ?? NULL;
			if ($schnitt[$key]) $event['dwz_schnitt'] = true;
		}
	}
	if ($dwz_sortierung) {
		//Nach DWZ-Schnitt absteigend, danach nach Teamname aufsteigend sortieren
		array_multisort($schnitt, SORT_DESC, $teamname, SORT_ASC, $event['teams']);
	}

	return $event;
}
