<?php 

/**
 * tournaments module
 * results of a round of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_round($params) {
	global $zz_setting;

	if (count($params) !== 3) return false;
	if (!is_numeric($params[2])) return false;

	if (!brick_access_rights('Webmaster')) {
		$public = ' AND NOT ISNULL(events_websites.website_id) ';
	} else {
		$public = '';
	}
	$sql = 'SELECT main_events.event_id, main_events.event
			, events.event AS round_event, events.runde_no
			, main_events.identifier AS event_identifier
			, SUBSTRING_INDEX(main_events.identifier, "/", 1) AS turnierjahr
			, CONCAT("U", SUBSTRING(SUBSTRING_INDEX(SUBSTRING_INDEX(main_events.identifier, "/", -1), "-", -1), 2)) AS turnierkennung
			, (SELECT IF(COUNT(tabellenstand_id), 1, NULL) FROM tabellenstaende
				WHERE tabellenstaende.event_id = main_events.event_id
				AND tabellenstaende.runde_no = events.runde_no
			) AS tabelle
			, (SELECT IF(COUNT(partie_id), 1, NULL) FROM partien
				WHERE partien.event_id = main_events.event_id
				AND ISNULL(weiss_ergebnis)
				AND partien.runde_no = events.runde_no
			) AS live
			, SUBSTRING_INDEX(event_categories.path, "/", -1) AS event_category
			, tournaments.livebretter
			, events.date_begin
			, YEAR(main_events.date_begin) AS year
			, CASE WEEKDAY(events.date_begin) 
				WHEN 0 THEN "Montag"
				WHEN 1 THEN "Dienstag"
				WHEN 2 THEN "Mittwoch"
				WHEN 3 THEN "Donnerstag"
				WHEN 4 THEN "Freitag"
				WHEN 5 THEN "Sonnabend"
				WHEN 6 THEN "Sonntag"
				END AS wochentag
			, DATE_FORMAT(events.time_begin, "%%H:%%i") AS time_begin
			, DATE_FORMAT(events.time_end, "%%H:%%i") AS time_end
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, CONCAT(main_events.date_begin, IFNULL(CONCAT("/", main_events.date_end), "")) AS duration
			, IFNULL(place, places.contact) AS turnierort
			, IF (CONCAT(events.date_begin, " ", events.time_begin) > NOW(), 1, NULL) AS auslosung
			, urkunde_parameter AS parameters
			, events.identifier
		FROM events
		LEFT JOIN events main_events
			ON events.main_event_id = main_events.event_id
		LEFT JOIN contacts places
			ON places.contact_id = main_events.place_contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN categories series
			ON main_events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		LEFT JOIN tournaments
			ON main_events.event_id = tournaments.event_id
		LEFT JOIN categories event_categories
			ON main_events.event_category_id = event_categories.category_id
		LEFT JOIN events_websites
			ON events_websites.event_id = main_events.event_id
			AND events_websites.website_id = %d
		WHERE main_events.identifier = "%d/%s"
		%s
		AND events.runde_no = %d
	';
	$sql = sprintf($sql, $zz_setting['website_id'], $params[0], wrap_db_escape($params[1]), $public, $params[2]);
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	if ($event['parameters']) {
		parse_str($event['parameters'], $parameters);
		$event += $parameters;
	}
	$event[$event['event_category']] = true;
	mf_tournaments_cache($event);

	if ($event['event_category'] === 'einzel') {
		$sql = 'SELECT DISTINCT partien.runde_no
			FROM partien
			WHERE partien.event_id = %d
			ORDER BY partien.runde_no';
	} else {
		$sql = 'SELECT DISTINCT paarungen.runde_no
			FROM paarungen
			WHERE paarungen.event_id = %d
			ORDER BY paarungen.runde_no';
	}
	$sql = sprintf($sql, $event['event_id']);
	$event['runden'] = wrap_db_fetch($sql, 'runde_no', 'single value');
	if (array_key_exists($event['runde_no'] - 1, $event['runden'])) {
		$event['prev'] = $event['runde_no'] - 1;
	}
	if (array_key_exists($event['runde_no'] + 1, $event['runden'])) {
		$event['next'] = $event['runde_no'] + 1;
	}

	if ($event['event_category'] !== 'einzel') {
		$sql = 'SELECT paarung_id, tisch_no
			, IF(heim_teams.spielfrei = "ja", "", heim_teams.identifier) AS heim_kennung
			, CONCAT(heim_teams.team, IFNULL(CONCAT(" ", heim_teams.team_no), "")) AS heim_team
			, IF(auswaerts_teams.spielfrei = "ja", bretter_min,
				IF(heim_teams.spielfrei = "ja", 0, SUM(heim_wertung))
			) AS heim_m_ergebnis
			, IF(auswaerts_teams.spielfrei = "ja", "", auswaerts_teams.identifier) AS auswaerts_kennung
			, CONCAT(auswaerts_teams.team, IFNULL(CONCAT(" ", auswaerts_teams.team_no), "")) AS auswaerts_team
			, IF(heim_teams.spielfrei = "ja", bretter_min, 
				IF(auswaerts_teams.spielfrei = "ja", 0, SUM(auswaerts_wertung))
			) AS auswaerts_m_ergebnis
			FROM paarungen
			LEFT JOIN teams heim_teams
				ON paarungen.heim_team_id = heim_teams.team_id
			LEFT JOIN teams auswaerts_teams
				ON paarungen.auswaerts_team_id = auswaerts_teams.team_id
			LEFT JOIN partien USING (paarung_id)
			LEFT JOIN tournaments
				ON tournaments.event_id = paarungen.event_id
			WHERE paarungen.event_id = %d AND paarungen.runde_no = %d
			GROUP BY paarung_id
			ORDER BY tisch_no';
		$sql = sprintf($sql, $event['event_id'], $event['runde_no']);
		$event['paarungen'] = wrap_db_fetch($sql, 'paarung_id');
		if (!$event['paarungen']) return false;
	}

	$sql = mf_tournaments_games_sql($event, sprintf('runde_no = %d', $event['runde_no']));
	if ($event['event_category'] !== 'einzel') {
		$lineup = mf_tournaments_lineup($event);
		if (!$lineup)
			$partien = wrap_db_fetch($sql, ['paarung_id', 'partie_id']);
		else
			$partien = [];
	} else {
		$event['partien'] = wrap_db_fetch($sql, 'partie_id');
		if (!$event['partien']) return;
	}

	if ($event['event_category'] !== 'einzel') {
		foreach ($partien as $paarung_id => $bretter) {
			foreach ($bretter as $partie_id => $brett) {
				if ($event['livebretter'] AND $event['live']) {
					// Liveübertragung, nur für aktuelle Runde
					$bretter[$partie_id]['live'] = mf_tournaments_live_round(
						$event['livebretter'], $brett['brett_no'],
						$event['paarungen'][$paarung_id]['tisch_no']
					);
					if ($bretter[$partie_id]['live']) {
						$event['liveuebertragung'] = true;
					}
				}
				$bretter[$partie_id]['runde_no'] = $event['runde_no'];
				$bretter[$partie_id]['tisch_no'] = $event['paarungen'][$paarung_id]['tisch_no'];
			}
			$event['paarungen'][$paarung_id]['bretter'] = $bretter;
		}
	} else {
		$event['show_brett_no'] = false;
		$event['show_dwz'] = false;
		$event['show_elo'] = false;
		foreach ($event['partien'] as $partie_id => $partie) {
			if ($partie['brett_no']) $event['show_brett_no'] = true;
			if ($partie['heim_dwz'] OR $partie['auswaerts_dwz']) $event['show_dwz'] = true;
			if ($partie['heim_elo'] OR $partie['auswaerts_elo']) $event['show_elo'] = true;
			if ($event['livebretter']) {
				$event['partien'][$partie_id]['live'] = mf_tournaments_live_round(
					$event['livebretter'], $partie['brett_no']
				);
			}
			$event['partien'][$partie_id]['runde_no'] = $event['runde_no'];
		}
		foreach ($event['partien'] as $partie_id => $partie) {
			if ($event['show_brett_no']) $event['partien'][$partie_id]['show_brett_no'] = true;
			if ($event['show_dwz']) $event['partien'][$partie_id]['show_dwz'] = true;
			if ($event['show_elo']) $event['partien'][$partie_id]['show_elo'] = true;
		}
	}

	$page['head'] = '';
	if (mf_tournaments_current_round($event['event_identifier']) < $event['runde_no']) {
		// @todo ausgestellt, muß aber wieder an wenn keine Live–Übertragung
//		$page['head'] .= sprintf(
//			"\t<meta http-equiv='refresh' content='60; URL=%s%s/%s/'>\r", $zz_setting['host_base'], $zz_setting['events_path'], $zz_setting['url_parameter']
//		);
		// @todo das sollte aus der Datenbank kommen, da nicht alle Turniere
		// immer mit Swiss-Chess ausgewertet werden
		$filename = $zz_setting['media_folder'].'/swt/'.$event['event_identifier'].'.swt';
		if (file_exists($filename)) {
		// @todo Livedatum ggf. auch aus letzter Änderung Tabelle auslesen
			$event['livedatum'] = date('d.m. H:i', filemtime($filename));
		}
	}

	if (!empty($event['auslosung']))
		$page['title'] = $event['event'].' '.$event['year'].', Auslosung '.$event['round_event'];
	else
		$page['title'] = $event['event'].' '.$event['year'].', Ergebnisse '.$event['round_event'];
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = $event['round_event'];
	if (!empty($event['next'])) {
		$page['link']['next'][0]['href'] = '../'.$event['next'].'/';
		$page['link']['next'][0]['title'] = $event['next'].'. Runde';
	}
	if (!empty($event['prev'])) {
		$page['link']['prev'][0]['href'] = '../'.$event['prev'].'/';
		$page['link']['prev'][0]['title'] = $event['prev'].'. Runde';
	}

	if ($event['event_category'] !== 'einzel') {
		$page['text'] = wrap_template('round-team', $event);
		if (!empty($event['liveuebertragung'])) {
			$page['head'] .= wrap_template('round-team-live-head', $event);
			$page['text'] .= wrap_template('round-team-live');
		}
	} else {
		$page['text'] = wrap_template('round-single', $event);
	}
	return $page;
}
