<?php 

/**
 * tournaments module
 * Output tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournament($vars, $settings, $event) {
	wrap_package_activate('events');

	if (!empty($settings['internal'])) {
		$internal = true;
		$sql_condition = '';
	} else {
		$internal = false;
		$sql_condition = ' AND NOT ISNULL(event_website_id) ';
	}
	
	$sql = 'SELECT IF(offen = "ja", IF(date_begin < CURDATE(), 0, 1), NULL) AS offen
			, IF(LOCATE("meldung=1", series.parameters), 1, NULL) AS online_meldung
			, IF(ISNULL(teams_max), 1, 
				IF((SELECT COUNT(*) FROM teams WHERE teams.event_id = events.event_id) < tournaments.teams_max, 1, NULL)
			) AS meldung_moeglich
			, (SELECT COUNT(*) FROM forms WHERE forms.event_id = events.event_id AND forms.form_category_id = /*_ID categories formulare/freiplatzantrag _*/) AS freiplatz
			, pseudo_dwz
			, tournament_id
			, tabellenstaende
			, series.category AS series, series.description AS series_description
			, SUBSTRING_INDEX(series.path, "/", -1) AS series_path
			, runden, modus.category AS modus
			, IF(spielerphotos = "ja", IF((SELECT COUNT(contact_id) FROM participations
				WHERE participations.event_id = events.event_id AND usergroup_id = /*_ID usergroups spieler _*/ AND NOT ISNULL(setzliste_no)), 1, NULL), NULL) AS spielerphotos
			, registration
			, livebretter
			, website_org.contact_abbr
			, (SELECT setting_value FROM _settings
				WHERE setting_key = "canonical_hostname" AND _settings.website_id = events.website_id
			) AS canonical_hostname
			, (SELECT setting_value FROM _settings
				WHERE setting_key = "events_path" AND _settings.website_id = events.website_id
			) AS events_path
			, IF(NOT ISNULL(IFNULL(events.description, series.description)), 1, NULL) AS ausschreibung
			, main_tournament_id
		FROM events
		LEFT JOIN websites USING (website_id)
		LEFT JOIN contacts website_org USING (contact_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = /*_SETTING website_id _*/
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories modus
			ON tournaments.modus_category_id = modus.category_id
		WHERE events.event_id = %d
		%s
	';
	$sql = sprintf($sql, $event['event_id'], $sql_condition);
	$tournament = wrap_db_fetch($sql);
	if (!$tournament) return false;
	$event = array_merge($event, $tournament);

	wrap_setting('tournaments_public_url', sprintf('https://%s%s/%s/'
		, $event['canonical_hostname']
		, wrap_setting('local_access') ? '.local' : ''
		, $event['identifier']
	));
	wrap_setting('events_public_url', sprintf('https://%s%s%s/%s/'
		, $event['canonical_hostname']
		, wrap_setting('local_access') ? '.local' : ''
		, $event['events_path']
		, $event['identifier']
	));
	wrap_setting('logfile_name', $event['identifier']);
	if (!$internal AND !$event['tournament_id']) {
		return wrap_redirect(
			sprintf('%s/%s/', wrap_setting('events_path'), implode('/', $vars)), 307);
	}
	if ($event['series_parameter']) {
		parse_str($event['series_parameter'], $series_parameter);
		$event += $series_parameter;
	}
	mf_tournaments_cache($event['duration']);
	$event['internal'] = $internal ? true : false;

	if (!empty($event['turnierform']))
		$event[str_replace('-', '_', $event['turnierform'])] = true;
	$event[str_replace('-', '_', $event['event_category'])] = true;
	
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

	// Auswertungen
	$sql = 'SELECT REPLACE(SUBSTRING_INDEX(categories.path, "/", -1), "-", "_") AS category
			, tournaments_identifiers.identifier
		FROM tournaments_identifiers
		LEFT JOIN categories
			ON tournaments_identifiers.identifier_category_id = categories.category_id
		WHERE tournament_id = %d';
	$sql = sprintf($sql, $event['tournament_id']);
	$ratings = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach ($ratings as $rating => $code) {
		$area = ($pos = strpos($rating, '_')) ? substr($rating, 0, $pos) : $rating;
		if ($event['year'] < 2011 AND $area === 'dwz') {
			$setting = sprintf('tournaments_rating_link[%s_before_2011]', $area);
			$fields = [$event['year'], $code];
		} else {
			$setting = sprintf('tournaments_rating_link[%s]', $area);
			$fields = [$code];
		}
		if (wrap_setting($setting))
			$event[$area.'_tournament_link'] = vsprintf(wrap_setting($setting), $fields);
	}
	
	// Bedenkzeit?
	$sql = 'SELECT tb_id, phase, bedenkzeit_sec/60 AS bedenkzeit, zeitbonus_sec AS zeitbonus, zuege
		FROM turniere_bedenkzeiten
		WHERE tournament_id = %d
		ORDER BY phase';
	$sql = sprintf($sql, $event['tournament_id']);
	$event['bedenkzeit'] = wrap_db_fetch($sql, 'tb_id');

	$event['organisations'] = mf_events_event_organisations($event['event_id'], ['addresses' => 1]);

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
			, %s AS internal
		FROM events
		WHERE main_event_id = %d
		AND event_category_id IN (/*_ID categories event/round _*/, /*_ID categories event/deadline _*/, /*_ID categories event/payment _*/)
		ORDER BY IFNULL(date_begin, date_end) ASC, IFNULL(time_begin, time_end) ASC, runde_no
	';
	$sql = sprintf($sql, $internal ? 1 : 'NULL', $event['event_id']);
	$event['events'] = wrap_db_fetch($sql, 'event_id');
	foreach ($event['events'] as $event_id => $program_item) {
		if ($event['offen'] OR $event['freiplatz']) {
			if ($program_item['event_category_id'] !== wrap_category_id('event/deadline')) continue;
			if ($program_item['date_begin'] > date('Y-m-d')) {
				$event['offen'] = false;
				$event['freiplatz'] = false;
			}
			if ($program_item['date_end'] < date('Y-m-d')) {
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
				wrap_category_id('event/deadline'),
				wrap_category_id('event/payment')
			]) AND $timetable['date_begin'] <= date('Y-m-d'))
			unset($event['events'][$id]);
		}
	}
	$letzte_dauer = '';
	foreach ($event['events'] as $event_id => $program_item) {
		if ($event['livebretter']) $event['events'][$event_id]['livebretter'] = true;
		if ($program_item['pgn']) $event['partien'] = true;
		else $event['events'][$event_id]['pgn'] = NULL;
		if ($program_item['duration'] === $letzte_dauer) {
			$event['events'][$event_id]['dauer_gleich'] = true;
		} else {
			$letzte_dauer = $program_item['duration'];
		}
		if ($internal 
			AND (!brick_access_rights(['Webmaster']) 
			AND !brick_access_rights(['Schiedsrichter', 'Technik', 'Turnierleitung'], 'event:'.$event['identifier']))
		) {
			//$event['events'][$event_id]['tabelle'] = false;
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
	
	$sql = 'SELECT eventdetail_id, identification, label
		FROM eventdetails
		WHERE event_id = %d
		AND ISNULL(team_id)
	';
	$sql = sprintf($sql, $event['event_id']);
	$event['links'] = wrap_db_fetch($sql, 'eventdetail_id');

	// Organisers
	$event = mod_tournaments_tournament_organisers($event, $internal);

	$event['round_no'] = mf_tournaments_current_round($event['event_id']);
	if ($event['round_no'] AND !$internal) $event['tabelle'] = true;

	if (wrap_setting('tournaments_type_single')) {
		$event['players_compact'] = mod_tournaments_tournament_players_compact($event);
	} elseif (wrap_setting('tournaments_type_team')) {
		$event['teams_compact'] = mod_tournaments_tournament_teams_compact($event, $internal);
	}

	$page['title'] = $event['event'].', '.wrap_date($event['duration']);
	$page['breadcrumbs'][]['title'] = $event['event'];
	$page['dont_show_h1'] = true;
	if ($internal) {
		$page['query_strings'][] = 'absage';
		if (array_key_exists('absage', $_GET))
			$event['team_abgesagt'] = true;
	}

	if (!empty($event['einzel'])) {
		$sql = 'SELECT COUNT(*) FROM participations
			WHERE event_id = %d
			AND usergroup_id = /*_ID usergroups spieler _*/
			AND status_category_id IN (%s/*_ID categories participation-status/participant _*/,
				/*_ID categories participation-status/disqualified _*/,
				/*_ID categories participation-status/blocked _*/
			)';
		$sql = sprintf($sql
			, $event['event_id']
			, ($event['date_end'] >= date('Y-m-d')) ? sprintf('%d, ', wrap_category_id('participation-status/verified')) : ''
		);
		$event['einzelteilnehmerliste'] = wrap_db_fetch($sql, '', 'single value');
	}
	if ($internal) $event['tabellenstaende'] = [];
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
		$event['photouebersicht'] = $event['year'] >= wrap_setting('dem_spielerphotos_aus_mediendb') ? true : false;
	}

	$page['text'] = wrap_template('tournament', $event);
	return $page;
}

/**
 * get organisers of tournament
 *
 * @param array $event
 * @param bool $internal
 * @return array
 * @todo use contactdetails functions for internal view
 */
function mod_tournaments_tournament_organisers($event, $internal) {	
	if ($internal) {
		$sql_fields = ', GROUP_CONCAT(category, ": ", identification SEPARATOR "<br>") AS telefon
		, (SELECT identification FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND provider_category_id = /*_ID categories provider/e-mail _*/
			LIMIT 1
		) AS e_mail';
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
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		%s
		LEFT JOIN usergroups USING (usergroup_id)
		WHERE event_id = %d
		AND usergroup_id IN (
			/*_ID usergroups organisator _*/,
			/*_ID usergroups schiedsrichter _*/,
			/*_ID usergroups turnierleitung _*/
		)
		GROUP BY participations.contact_id, usergroups.usergroup_id
		ORDER BY last_name, first_name
	';
	$sql = sprintf($sql, $sql_fields, $sql_join, $event['event_id']);
	$event = array_merge($event, wrap_db_fetch($sql, ['group_identifier', 'person_id']));
	return $event;
}

/**
 * show compact players list or standings, depending on the state of the tournament
 *
 * @param array $event
 * @return string
 */
function mod_tournaments_tournament_players_compact($event) {
	$sql = 'SELECT wertung_category_id
		FROM turniere_wertungen
		WHERE turniere_wertungen.tournament_id = %d
		AND turniere_wertungen.reihenfolge = 1';
	$sql = sprintf($sql, $event['tournament_id']);
	$main_rating_category_id = wrap_db_fetch($sql, '', 'single value');

	$sql = 'SELECT participation_id, platz_no
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS spieler
			, setzliste_no
			, tabellenstaende_wertungen.wertung
			, participations.club_contact_id
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN tabellenstaende
			ON persons.person_id = tabellenstaende.person_id
			AND tabellenstaende.event_id = participations.event_id
			AND tabellenstaende.runde_no = %d
		LEFT JOIN tabellenstaende_wertungen
			ON tabellenstaende_wertungen.tabellenstand_id = tabellenstaende.tabellenstand_id
			AND tabellenstaende_wertungen.wertung_category_id = %d
		WHERE participations.event_id = %d
		AND usergroup_id = /*_ID usergroups spieler _*/
		AND status_category_id = /*_ID categories participation-status/participant _*/
		AND NOT ISNULL(platz_no)
		ORDER BY platz_no';
	$sql = sprintf($sql
		, $event['round_no']
		, $main_rating_category_id
		, $event['event_id']
	);
	$event['players'] = wrap_db_fetch($sql, 'participation_id');
	if ($event['players'])
		$event['players'] = mf_tournaments_clubs_to_federations($event['players']);
	$max_players = wrap_setting('tournaments_players_compact_max');
	$max_players_ceil = $max_players * wrap_setting('tournaments_players_compact_max_tolerance');
	if (count($event['players']) > $max_players_ceil) {
		$event['more_players'] = count($event['players']) - $max_players;
		$event['players'] = array_slice($event['players'], 0, $max_players);
	}
	if ($event['main_event_path'])
		foreach (array_keys($event['players']) as $participation_id)
			$event['players'][$participation_id]['main_event_path'] = $event['main_event_path'];

	return wrap_template('players-compact', $event);
}

/**
 * show compact team list or standings, depending on the state of the tournament
 *
 * @param array $event
 * @return string
 */
function mod_tournaments_tournament_teams_compact(&$event, $internal) {
	$sql = 'SELECT teams.team_id
			, team, team_no, teams.identifier AS team_identifier, team_status
			, setzliste_no
			, IF(LENGTH(main_series.path) > 7, CONCAT(IFNULL(events.event_year, YEAR(events.date_begin)), "/", SUBSTRING_INDEX(main_series.path, "/", -1)), NULL) AS main_series_path
			, platz_no, tabellenstand_id
			, teams.club_contact_id
			, (SELECT place FROM addresses WHERE addresses.contact_id = teams.club_contact_id ORDER BY address_id LIMIT 1) AS place
		FROM teams
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
		ORDER BY platz_no, setzliste_no, team, team_no
	';
	$sql = sprintf($sql
		, $event['round_no']
		, $event['event_id']
	);
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	if (!$event['teams']) return '';

	$event['teams'] = mf_tournaments_clubs_to_federations($event['teams']);

	$standings = [];
	foreach ($event['teams'] as $id => $team) {
		if (!empty($event['turnierform']))
			$event['teams'][$id][str_replace('-', '_', $event['turnierform'])] = true;
		if (empty($team['tabellenstand_id'])) continue;
		$standings[$team['tabellenstand_id']] = $team['team_id'];
	}
	if ($standings) {
		// format 0 points as 0.00 to get points displayed
		$sql = 'SELECT tsw_id, tabellenstand_id, wertung_category_id
				, IF(wertung = "0", "0.00", wertung) AS wertung
			FROM tabellenstaende_wertungen
			WHERE tabellenstand_id IN (%s)';
		$sql = sprintf($sql, implode(',', array_keys($standings)));
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
		$sql = sprintf($sql, implode(',', array_keys($standings)));
		$wertungskategorie = wrap_db_fetch($sql);
		foreach ($wertungen as $ts_id => $wertung) {
			$event['teams'][$standings[$ts_id]]['wertung'] 
				= $wertung[$wertungskategorie['category_id']]['wertung'];
		}
	}

	$dwz_sort = false;
	if (!empty($event['participant_list'])) {
		$dwz_sort = true;
		$first_team = current($event['teams']);
		if ($first_team['setzliste_no']) $dwz_sort = false;

		list($event['dwz_schnitt'], $event['teams']) 
			= mf_tournaments_team_rating_average_dwz($event['event_id'], $event['teams'], $event['bretter_min'], $event['pseudo_dwz']);
	}
	if ($dwz_sort AND !$event['round_no']) {
		// Sortierung nach DWZ-Schnitt
		foreach ($event['teams'] AS $key => $row) {
			$teamname[$key] = $row['place'].$row['team'].$row['team_no'];
			$verband[$key] = $row['country'] ?? '';
			$schnitt[$key] = $row['dwz_schnitt'] ?? NULL;
		}
		
		// Nach DWZ-Schnitt absteigend, danach nach Teamname aufsteigend sortieren
		array_multisort($schnitt, SORT_DESC, $teamname, SORT_ASC, $event['teams']);
	}

	// eigener Verein
	$own_teams = mf_tournaments_team_own();
	foreach ($event['teams'] as $id => $team) {
		$active = false;
		if (!empty($event['participant_list']) AND $team['team_status'] === 'Teilnehmer') $active = true;
		elseif (in_array($id, $own_teams) AND $internal) $active = true;
		elseif (brick_access_rights('Webmaster') AND $event['internal']) $active = true;
		if ($active) $event['teams'][$id]['active'] = 1;
	}

	return wrap_template('teams-compact', $event);
}
