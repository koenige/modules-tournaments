<?php 

/**
 * tournaments module
 * Starting ranking
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_startranking($vars) {
	global $zz_conf;
	global $zz_setting;

	if (!empty($vars[2]) AND $vars[2] === 'startrangliste')
		unset($vars[2]);
	if (count($vars) !== 2) return false;
	
	$sql = 'SELECT events.event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, IFNULL(event_year, YEAR(date_begin)) AS year, IFNULL(date_end, date_begin) AS date_end
			, places.contact AS veranstaltungsort
			, address, postcode, place, places.description
			, latitude, longitude
			, events.identifier
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste
			, IFNULL(place, places.contact) AS turnierort
			, pseudo_dwz, bretter_min
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses USING (contact_id)
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, $zz_setting['website_id'], wrap_db_escape(implode('/', $vars)));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$zz_setting['active_module_for_log'] = $event['identifier'];
	$event[str_replace('-', '_', $event['turnierform'])] = true;

	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$event['event'].'</a>';
	$page['extra']['realm'] = 'sports';
	$page['dont_show_h1'] = true;

	$meldeliste = false;
	if ($event['turnierform'] === 'e') {
		$event = mod_tournaments_startranking_single($event);
		foreach ($event['spieler'] as $spieler) {
			if ($spieler['teilnahme_status'] !== 'angemeldet') continue;
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
		$page['breadcrumbs'][] = 'Meldeliste';
		$event['meldeliste'] = true;
		$event['meldungen'] = !empty($event['spieler']) ? count($event['spieler']) : count($event['teams']);
	} else {
		$page['title'] = $event['event'].' '.$event['year'].': Startrangliste';
		$page['breadcrumbs'][] = 'Startrangliste';
	}

	if ($event['turnierform'] === 'e') {
		$page['text'] = wrap_template('startranking-single', $event);
	} else {
		$page['text'] = wrap_template('startranking-team', $event);
	}
	return $page;
}

function mod_tournaments_startranking_single($event) {
	global $zz_setting;

	// @todo Sortierung nach DWZ oder Elo, je nach Turniereinstellung
	$sql = 'SELECT person_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
			, CONCAT(t_nachname, ", ", t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), "")) AS nachname_vorname
			, t_extra
			, t_verein
			, t_dwz, t_elo, t_fidetitel
			, setzliste_no
			, country
			, places.contact AS veranstaltungsort, place, latitude, longitude
			, landesverbaende.identifier AS lv_kennung
			, landesverbaende.contact_abbr AS lv_kurz
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, teilnahme_status
			, DATE_FORMAT(eintrag_datum, "%%d.%%m %%H:%%i") AS eintrag_datum
			, eintrag_datum AS eintrag_datum_raw
			, IF(SUBSTRING(qualification.event, 1, LENGTH(events.event)) = events.event
				, SUBSTRING(qualification.event, LENGTH(events.event)+1), qualification.event
			) AS qualification_event
			, YEAR(qualification.date_begin) AS qualification_year
			, qualification.identifier AS qualification_event_identifier
			, qualification
		FROM teilnahmen
		JOIN personen USING (person_id)
		LEFT JOIN organisationen
			ON teilnahmen.verein_org_id = organisationen.org_id
		LEFT JOIN organisationen_kennungen v_ok
			ON v_ok.org_id = organisationen.org_id
			AND v_ok.current = "yes"
		LEFT JOIN organisationen_kennungen lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
		LEFT JOIN organisationen landesverbaende
			ON lv_ok.org_id = landesverbaende.org_id
			AND lv_ok.current = "yes"
			AND landesverbaende.mutter_org_id = %d
		LEFT JOIN countries
			ON landesverbaende.country_id = countries.country_id
		LEFT JOIN organisationen_orte
			ON organisationen_orte.org_id = organisationen.org_id
			AND organisationen_orte.published = "yes"
		LEFT JOIN contacts places
			ON organisationen_orte.contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN events qualification
			ON teilnahmen.qualification_event_id = qualification.event_id
		WHERE events.event_id = %d
		AND usergroup_id = %d
		AND teilnahme_status IN (%s"Teilnehmer", "disqualifiziert", "geblockt")
		ORDER BY setzliste_no, IFNULL(t_dwz, t_elo) DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql
		, $zz_setting['org_ids']['dsb']
		, $event['event_id']
		, wrap_id('usergroups', 'spieler')
		, ($event['date_end'] >= date('Y-m-d')) ? '"angemeldet", ' : ''
	);
	$event['spieler'] = wrap_db_fetch($sql, 'person_id');
	$event['spieler'] = my_get_personen_kennungen($event['spieler'], ['fide-id', 'zps']);
	$event['zeige_dwz'] = false;
	$event['zeige_elo'] = false;
	$event['zeige_titel'] = false;
	foreach ($event['spieler'] as $person_id => $spieler) {
		if ($spieler['qualification_event']) $event['qualification_col'] = true;
		if ($spieler['t_fidetitel']) $event['zeige_titel'] = true;
		if ($spieler['t_elo']) $event['zeige_elo'] = true;
		if ($spieler['t_dwz']) $event['zeige_dwz'] = true;
		if (!$spieler['t_fidetitel']) continue;
		$event['spieler'][$person_id]['fidetitel_lang'] = my_fidetitel($spieler['t_fidetitel']);
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
	global $zz_setting;

	$sql = 'SELECT team_id
			, team, team_no
			, IF(NOT ISNULL(teams.setzliste_no), teams.kennung, "") AS kennung, team_status, country
			, SUBSTRING_INDEX(teams.kennung, "/", -1) AS team_identifier_short
			, places.contact AS veranstaltungsort, place, latitude, longitude, setzliste_no
			, IFNULL(landesverbaende.identifier, landesverbaende_rueckwaerts.identifier) AS lv_kennung
			, IFNULL(landesverbaende.contact_abbr, landesverbaende_rueckwaerts.contact_abbr) AS lv_kurz
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, eintrag_datum
		FROM teams
		LEFT JOIN organisationen
			ON teams.verein_org_id = organisationen.org_id
		LEFT JOIN organisationen_kennungen v_ok
			ON v_ok.org_id = organisationen.org_id AND v_ok.current = "yes"
		LEFT JOIN organisationen_kennungen lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier AND lv_ok.current = "yes"
		LEFT JOIN organisationen landesverbaende
			ON lv_ok.org_id = landesverbaende.org_id
			AND landesverbaende.mutter_org_id = %d
		LEFT JOIN countries
			ON IFNULL(landesverbaende.country_id, organisationen.country_id) 
				= countries.country_id
		LEFT JOIN organisationen landesverbaende_rueckwaerts
			ON countries.country_id = landesverbaende_rueckwaerts.country_id
			AND landesverbaende_rueckwaerts.contact_category_id = %d
			AND landesverbaende_rueckwaerts.mutter_org_id = %d
		LEFT JOIN organisationen_orte
			ON organisationen_orte.org_id = organisationen.org_id
			AND organisationen_orte.published = "yes"
		LEFT JOIN contacts places
			ON organisationen_orte.contact_id = places.contact_id
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
		, $zz_setting['org_ids']['dsb']
		, wrap_category_id('contact/federation')
		, $zz_setting['org_ids']['dsb']
		, $event['event_id']
	);
	// @todo Klären, was passiert wenn mehr als 1 Ort zu Verein in Datenbank! (Reihenfolge-Feld einführen)
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	if (!$event['teams']) wrap_quit(404); // es liegt noch keine Rangliste vor.
	$event['meldeliste'] = false;
	foreach (array_keys($event['teams']) AS $team_id) {
		// Meldelistestatus nur bei Terminen, die noch nicht zuende sind
		if (empty($event['teams'][$team_id]['setzliste_no']) AND $event['date_end'] > date('Y-m-d')) $event['meldeliste'] = true;
		$event['teams'][$team_id][str_replace('-', '_', $event['turnierform'])] = true;
		if ($event['teams'][$team_id]['country']) $event['country'] = true;
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
			$verband[$key] = $row['country'];
			$schnitt[$key] = $row['dwz_schnitt'];
			if ($row['dwz_schnitt']) $event['dwz_schnitt'] = true;
		}
	}
	if ($dwz_sortierung) {
		//Nach DWZ-Schnitt absteigend, danach nach Teamname aufsteigend sortieren
		array_multisort($schnitt, SORT_DESC, $teamname, SORT_ASC, $event['teams']);
	}

	return $event;
}
