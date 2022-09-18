<?php 

/**
 * tournaments module
 * Output tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournament($vars, $settings) {
	global $zz_setting;

	if (!empty($settings['intern'])) {
		$intern = true;
		$sql_condition = '';
	} else {
		$intern = false;
		$sql_condition = ' AND NOT ISNULL(event_website_id) ';
	}

	$sql = 'SELECT events.event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, date_begin
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, places.contact AS veranstaltungsort
			, address, postcode, place, places.description
			, latitude, longitude, place_contact_id
			, series.category_id AS series_category_id, events.identifier
			, IF(offen = "ja", IF(date_begin < CURDATE(), 0, 1), 0) AS offen
			, IF(LOCATE("meldung=1", series.parameters), 1, NULL) AS online_meldung
			, IF(ISNULL(teams_max), 1, 
				IF((SELECT COUNT(*) FROM teams WHERE teams.event_id = events.event_id) < tournaments.teams_max, 1, NULL)
			) AS meldung_moeglich
			, (SELECT COUNT(*) FROM forms WHERE forms.event_id = events.event_id AND forms.form_category_id = %d) AS freiplatz
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, IFNULL(place, places.contact) AS turnierort
			, pseudo_dwz, bretter_min
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, SUBSTRING_INDEX(event_categories.path, "/", -1) AS event_category
			, tournament_id
			, tabellenstaende
			, IF(NOT ISNULL(events.date_end),
				IF(events.date_end < CURDATE(), 1, NULL),
				IF(events.date_begin < CURDATE(), 1, NULL)
			) AS event_over
			, series.category AS series, series.description AS series_description
			, series.parameters AS series_parameter
			, SUBSTRING_INDEX(series.path, "/", -1) AS series_path
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, runden, modus.category AS modus
			, IF(spielerphotos = "ja", IF((SELECT COUNT(person_id) FROM participations
				WHERE participations.event_id = events.event_id AND usergroup_id = %d AND NOT ISNULL(setzliste_no)), 1, NULL), NULL) AS spielerphotos
			, registration
			, livebretter
			, (SELECT wertung_category_id FROM turniere_wertungen
				WHERE turniere_wertungen.tournament_id = tournaments.tournament_id
				AND turniere_wertungen.reihenfolge = 1) AS haupt_wertung_category_id
			, website_org.contact_abbr
			, (SELECT setting_value FROM _settings
				WHERE setting_key = "canonical_hostname" AND _settings.website_id = events.website_id
			) AS canonical_hostname
			, IF(NOT ISNULL(IFNULL(events.description, series.description)), 1, NULL) AS ausschreibung
			, main_tournament_id
		FROM events
		LEFT JOIN websites USING (website_id)
		LEFT JOIN contacts website_org USING (contact_id)
		LEFT JOIN categories event_categories
			ON events.event_category_id = event_categories.category_id
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories modus
			ON tournaments.modus_category_id = modus.category_id
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses
			ON addresses.contact_id = places.contact_id
		WHERE events.identifier = "%s"
		%s
	';
	$sql = sprintf($sql
		, wrap_category_id('formulare/freiplatzantrag')
		, wrap_id('usergroups', 'spieler')
		, $zz_setting['website_id']
		, wrap_db_escape(implode('/', $vars))
		, $sql_condition
	);
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$zz_setting['logfile_name'] = $event['identifier'];
	if (!$intern AND !$event['tournament_id']) {
		return wrap_redirect(
			sprintf('%s/%s/', $zz_setting['events_path'], implode('/', $vars)), 307);
	}
	if ($event['series_parameter']) {
		parse_str($event['series_parameter'], $series_parameter);
		$event += $series_parameter;
	}
	mf_tournaments_cache($event);
	$event['intern'] = $intern ? true : false;
	if ($event['turnierform'])
		$event[str_replace('-', '_', $event['turnierform'])] = true;
	$event[str_replace('-', '_', $event['event_category'])] = true;
	// @todo im Grunde überflüssig, da Auswahlskript hier eh nur Turniere ankommen läßt
	$event['turnier'] = true;
	
	if (!empty($event['show_main_tournament_archive'])) {
		// series, series_path
		$sql = 'SELECT category_id
				, series.category AS series
				, SUBSTRING_INDEX(series.path, "/", -1) AS series_path
			FROM tournaments
			LEFT JOIN events USING (event_id)
			LEFT JOIN categories series
				ON events.series_category_id = series.category_id
			WHERE tournament_id = %d';
		$sql = sprintf($sql, $event['main_tournament_id']);
		$archive_series = wrap_db_fetch($sql);
		if ($archive_series) {
			$event['series'] = $archive_series['series'];
			$event['series_path'] = $archive_series['series_path'];
		}
	}

	// Kontaktdetails
	$details = mf_contacts_contactdetails($event['place_contact_id']);
	$event += $details;

	// Auswertungen
	$sql = 'SELECT REPLACE(SUBSTRING_INDEX(categories.path, "/", -1), "-", "_") AS category
			, turniere_kennungen.kennung AS turnierkennung
		FROM turniere_kennungen
		LEFT JOIN categories
			ON turniere_kennungen.kennung_category_id = categories.category_id
		WHERE tournament_id = %d';
	$sql = sprintf($sql, $event['tournament_id']);
	$event = array_merge($event, wrap_db_fetch($sql, '_dummy_', 'key/value'));
	if ($event['year'] < 2011 AND array_key_exists('dwz', $event)) {
		$event['dwz_db_archiv'] = true;
	}
	
	// Bedenkzeit?
	$sql = 'SELECT tb_id, phase, bedenkzeit_sec/60 AS bedenkzeit, zeitbonus_sec AS zeitbonus, zuege
		FROM turniere_bedenkzeiten
		WHERE tournament_id = %d
		ORDER BY phase';
	$sql = sprintf($sql, $event['tournament_id']);
	$event['bedenkzeit'] = wrap_db_fetch($sql, 'tb_id');

	require_once $zz_setting['custom'].'/zzbrick_request/termin.inc.php';
	$event['organisationen'] = cms_event_organisations($event['event_id']);

	$sql = 'SELECT events.event_id, event
			, CONCAT(IFNULL(date_begin, ""), IFNULL(CONCAT("/", date_end), "")) AS duration
			, TIME_FORMAT(time_begin, "%%H.%%i") AS time_begin
			, TIME_FORMAT(time_end, "%%H.%%i") AS time_end
			, event_category_id, date_begin, date_end, events.runde_no
			, IF((SELECT COUNT(*) FROM paarungen
		   		WHERE event_id = events.main_event_id AND runde_no = events.runde_no), 1, NULL) AS paarungen
			, IF((SELECT COUNT(*) FROM partien
		   		WHERE event_id = events.main_event_id AND runde_no = events.runde_no), 1, NULL) AS partien
			, IF((SELECT COUNT(*) FROM tabellenstaende
				WHERE event_id = events.main_event_id AND runde_no = events.runde_no), 1, NULL) AS tabelle
			, IF(takes_place = "no", 1, NULL) as faellt_aus
			, (SELECT COUNT(*) FROM partien
				WHERE event_id = events.main_event_id
				AND runde_no = events.runde_no
				AND NOT ISNULL(pgn)) AS pgn
			, %s AS intern
		FROM events
		WHERE main_event_id = %d
		AND event_category_id IN (%d, %d, %d)
		ORDER BY IFNULL(date_begin, date_end) ASC, IFNULL(time_begin, time_end) ASC, runde_no
	';
	$sql = sprintf($sql, $intern ? 1 : 'NULL', $event['event_id']
		, wrap_category_id('zeitplan/runde')
		, wrap_category_id('zeitplan/meldefrist')
		, wrap_category_id('zeitplan/zahlungsfrist')
	);
	$event['events'] = wrap_db_fetch($sql, 'event_id');
	foreach ($event['events'] as $event_id => $my_datum) {
		if ($event['offen'] OR $event['freiplatz']) {
			if ($my_datum['event_category_id'] !== wrap_category_id('zeitplan/meldefrist')) continue;
			if ($my_datum['date_begin'] > date('Y-m-d')) {
				$event['offen'] = false;
				$event['freiplatz'] = false;
			}
			if ($my_datum['date_end'] < date('Y-m-d')) {
				$event['offen'] = false;
				$event['freiplatz'] = false;
			}
			if ($event['registration']) {
				$event['offen'] = false;
				$event['freiplatz'] = false;
			}
		}
	}
	if ($event['date_begin'] <= date('Y-m-d')) {
		foreach ($event['events'] as $id => $timetable) {
			if (in_array($timetable['event_category_id'], [
				wrap_category_id('zeitplan/meldefrist'),
				wrap_category_id('zeitplan/zahlungsfrist')
			]) AND $timetable['date_begin'] <= date('Y-m-d'))
			unset($event['events'][$id]);
		}
	}
	$letzte_dauer = '';
	foreach ($event['events'] as $event_id => $my_datum) {
		if ($event['livebretter']) $event['events'][$event_id]['livebretter'] = true;
		if ($my_datum['pgn']) $event['partien'] = true;
		if ($my_datum['duration'] === $letzte_dauer) {
			$event['events'][$event_id]['dauer_gleich'] = true;
		} else {
			$letzte_dauer = $my_datum['duration'];
		}
		if ($intern 
			AND (!brick_access_rights(['Webmaster']) 
			AND !brick_access_rights(['Schiedsrichter', 'Technik', 'Turnierleitung'], 'event:'.$event['identifier']))
		) {
			$event['events'][$event_id]['tabelle'] = false;
			$event['events'][$event_id]['paarungen'] = false;
		}
	}
	
	// qualification tournaments?
	$sql = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year, identifier
			, CONCAT(IFNULL(date_begin, ""), IFNULL(CONCAT("/", date_end), "")) AS duration
			, TIME_FORMAT(time_begin, "%%H.%%i") AS time_begin
			, TIME_FORMAT(time_end, "%%H.%%i") AS time_end
			, date_begin, date_end
		FROM tournaments
		LEFT JOIN events USING (event_id)
		WHERE (tournament_id = %d OR main_tournament_id = %d)
		AND tournament_id != %d';
	$sql = sprintf($sql
		, $event['main_tournament_id'] ? $event['main_tournament_id']: $event['tournament_id']
		, $event['main_tournament_id'] ? $event['main_tournament_id']: $event['tournament_id']
		, $event['tournament_id']
	);
	$event['events'] += wrap_db_fetch($sql, 'event_id');
	$dates = [];
	foreach ($event['events'] as $sub_event) {
		$dates[] = ($sub_event['date_begin'] ? $sub_event['date_begin'] : $sub_event['date_end'])
			.'T'.($sub_event['time_begin'] ? $sub_event['time_begin'] : $sub_event['time_end']);
	}
	array_multisort($dates, SORT_ASC, $event['events']);
	
	$sql = 'SELECT event_link_id, link, link_text
		FROM events_links
		WHERE event_id = %d
		AND ISNULL(team_id)
	';
	$sql = sprintf($sql, $event['event_id']);
	$event['links'] = wrap_db_fetch($sql, 'event_link_id');

	$runde = mf_tournaments_current_round($event['identifier']);
	if ($runde AND !$intern) $event['tabelle'] = true;

	if ($event['turnierform'] === 'e') {
		$sql = 'SELECT participation_id, platz_no
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS spieler
				, setzliste_no
				, tabellenstaende_wertungen.wertung
				, participations.club_contact_id
			FROM participations
			LEFT JOIN tabellenstaende
				ON participations.person_id = tabellenstaende.person_id
				AND tabellenstaende.event_id = participations.event_id
				AND tabellenstaende.runde_no = %d
			LEFT JOIN tabellenstaende_wertungen
				ON tabellenstaende_wertungen.tabellenstand_id = tabellenstaende.tabellenstand_id
				AND tabellenstaende_wertungen.wertung_category_id = %d
			WHERE participations.event_id = %d
			AND usergroup_id = %d
			AND teilnahme_status = "Teilnehmer"
			AND NOT ISNULL(platz_no)
			ORDER BY platz_no';
		$sql = sprintf($sql
			, $runde
			, $event['haupt_wertung_category_id']
			, $event['event_id']
			, wrap_id('usergroups', 'spieler')
		);
		$event['spieler'] = wrap_db_fetch($sql, 'participation_id');
		if ($event['spieler'])
			$event['spieler'] = mf_tournaments_clubs_to_federations($event['spieler']);
		if (count($event['spieler']) > 25) {
			$event['mehr_spieler'] = count($event['spieler']) - 20;
			$event['spieler'] = array_slice($event['spieler'], 0, 20);
		}
	} else {
		$sql = 'SELECT teams.team_id
				, team, team_no, teams.identifier AS team_identifier, team_status
				, places.contact AS veranstaltungsort, place, latitude, longitude, setzliste_no
				, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
				, platz_no, tabellenstand_id
				, teams.club_contact_id
			FROM teams
			LEFT JOIN contacts organisationen
				ON teams.club_contact_id = organisationen.contact_id
			LEFT JOIN contacts_contacts
				ON contacts_contacts.contact_id = organisationen.contact_id
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
			LEFT JOIN tabellenstaende
				ON teams.team_id = tabellenstaende.team_id
				AND (tabellenstaende.runde_no = %d OR ISNULL(tabellenstaende.runde_no))
			WHERE teams.event_id = %d
			AND team_status IN ("Teilnehmer", "Teilnahmeberechtigt")
			AND spielfrei = "nein"
			ORDER BY platz_no, setzliste_no, place, team, team_no
		';
		$sql = sprintf($sql
			, $runde
			, $event['event_id']
		);
		// @todo Klären, was passiert wenn mehr als 1 Ort zu Verein in Datenbank! 
		// (Reihenfolge-Feld einführen)
		$event['teams'] = wrap_db_fetch($sql, 'team_id');
		if ($event['teams'])
			$event['teams'] = mf_tournaments_clubs_to_federations($event['teams']);
		if ($runde AND $event['teams']) {
			foreach ($event['teams'] as $team) {
				if (empty($team['tabellenstand_id'])) continue;
				$tabelle[$team['tabellenstand_id']] = $team['team_id'];
			}
			// format 0 points as 0.00 to get points displayed
			$sql = 'SELECT tsw_id, tabellenstand_id, wertung_category_id
					, IF(wertung = "0", "0.00", wertung) AS wertung
				FROM tabellenstaende_wertungen
				WHERE tabellenstand_id IN (%s)';
			$sql = sprintf($sql, implode(',', array_keys($tabelle)));
			$wertungen = wrap_db_fetch($sql, ['tabellenstand_id', 'wertung_category_id']);

			$sql = 'SELECT DISTINCT category_id, category, category_short
					, tw.reihenfolge, categories.sequence
				FROM tabellenstaende_wertungen tsw
				LEFT JOIN tabellenstaende USING (tabellenstand_id)
				LEFT JOIN tournaments USING (event_id)
				LEFT JOIN turniere_wertungen tw
					ON tw.wertung_category_id = tsw.wertung_category_id
					AND tw.tournament_id = tournaments.tournament_id
				LEFT JOIN categories
					ON tsw.wertung_category_id = categories.category_id
				WHERE tabellenstand_id IN (%s)
				ORDER BY tw.reihenfolge, categories.sequence
				LIMIT 1';
			$sql = sprintf($sql, implode(',', array_keys($tabelle)));
			$wertungskategorie = wrap_db_fetch($sql);
			foreach ($wertungen as $ts_id => $wertung) {
				$event['teams'][$tabelle[$ts_id]]['wertung'] 
					= $wertung[$wertungskategorie['category_id']]['wertung'];
			}
		}
	}

	// Organisatoren
	if ($intern) {
		$sql_fields = sprintf('
		, GROUP_CONCAT(category, ": ", identification SEPARATOR "<br>") AS telefon
		, (SELECT identification FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND provider_category_id = %d
			LIMIT 1
		) AS e_mail'
			, wrap_category_id('provider/e-mail')
		);
		$sql_join = '
		LEFT JOIN contactdetails USING (contact_id)
		LEFT JOIN categories
			ON categories.category_id = contactdetails.provider_category_id
			AND categories.parameters LIKE "%&type=phone%"
		';
	} else {
		$sql_fields = '';
		$sql_join = '';
	}
	$sql = 'SELECT person_id
			, contact AS person
			, usergroup, usergroups.identifier AS group_identifier
			%s
		FROM participations
		LEFT JOIN persons USING (person_id)
		LEFT JOIN contacts USING (contact_id)
		%s
		LEFT JOIN usergroups USING (usergroup_id)
		WHERE event_id = %d
		AND usergroup_id IN (%d, %d, %d)
		GROUP BY participations.person_id, usergroups.usergroup_id
		ORDER BY last_name, first_name
	';
	$sql = sprintf($sql, $sql_fields, $sql_join, $event['event_id'],
		wrap_id('usergroups', 'organisator'), wrap_id('usergroups', 'schiedsrichter'),
		wrap_id('usergroups', 'turnierleitung')
	);
	$event = array_merge($event, wrap_db_fetch($sql, ['group_identifier', 'person_id']));

	$dwz_sortierung = false;
	if ($event['teilnehmerliste'] AND !empty($event['teams'])) {
		$dwz_sortierung = true;
		$erstes_team = current($event['teams']);
		if ($erstes_team['setzliste_no']) $dwz_sortierung = false;

		list($event['dwz_schnitt'], $event['teams']) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $event['teams'], $event['bretter_min'], $event['pseudo_dwz']);
	}
	if ($dwz_sortierung AND $event['teams'] AND !$runde) {
		// Sortierung für Jacob nach DWZ-Schnitt
		foreach ($event['teams'] AS $key => $row) {
			$teamname[$key] = $row['place'];
			$verband[$key] = $row['country'];
			$schnitt[$key] = $row['dwz_schnitt'];
		}
		
		// Nach DWZ-Schnitt absteigend, danach nach Teamname aufsteigend sortieren
		array_multisort($schnitt, SORT_DESC, $teamname, SORT_ASC, $event['teams']);
	}

	// eigener Verein
	if ($event['turnierform'] !== 'e') {
		$eigene_teams = mf_tournaments_team_own();
		foreach ($event['teams'] as $id => $team) {
			if ($event['teilnehmerliste'] AND $team['team_status'] === 'Teilnehmer') $event['teams'][$id]['aktiv'] = 1;
			elseif (in_array($id, $eigene_teams) AND $intern) $event['teams'][$id]['aktiv'] = 1;
			elseif (brick_access_rights('Webmaster') AND $event['intern']) $event['teams'][$id]['aktiv'] = 1;
			if (!empty($event['turnierform']))
				$event['teams'][$id][str_replace('-', '_', $event['turnierform'])] = true;
		}
	}

	$page['title'] = $event['event'].', '.wrap_date($event['duration']);
	$page['breadcrumbs'][] = '<a href="../">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = $event['event'];
	$page['dont_show_h1'] = true;
	if ($intern) {
		$page['query_strings'][] = 'absage';
		if (array_key_exists('absage', $_GET)) {
			$event['team_abgesagt'] = true;
		}
	}

	if (empty($event['einzel'])) {
		if ($event['latitude'] AND !$intern) {
			$page['head'] = wrap_template('termin-map-head');
			$event['map'] = my_teilnehmerkarte($event);
		}
	}

	if (!empty($event['einzel'])) {
		$sql = 'SELECT COUNT(*) FROM participations
			WHERE usergroup_id = %d AND event_id = %d';
		$sql = sprintf($sql, wrap_id('usergroups', 'spieler'), $event['event_id']);
		$event['einzelteilnehmerliste'] = wrap_db_fetch($sql, '', 'single value');
	}
	if ($intern) $event['tabellenstaende'] = [];
	if ($event['tabellenstaende']) {
		$ts = explode(',', $event['tabellenstaende']);
		$event['tabellenstaende'] = [];
		foreach ($ts as $stand) {
			$event['tabellenstaende'][] = [
				'tabelle' => trim($stand)
			];
		}
	}

	if ($event['spielerphotos']) {
		$event['photouebersicht'] = $event['year'] >= wrap_get_setting('dem_spielerphotos_aus_mediendb') ? true : false;
	}

	$page['text'] = wrap_template('tournament', $event);
	return $page;
}
