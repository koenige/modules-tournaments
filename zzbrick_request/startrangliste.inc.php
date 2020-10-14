<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Turnier: Startrangliste


function mod_tournaments_startrangliste($vars) {
	global $zz_conf;
	global $zz_setting;

	if (!empty($vars[2]) AND $vars[2] === 'startrangliste')
		unset($vars[2]);
	if (count($vars) !== 2) return false;
	
	$sql = 'SELECT events.event_id, termin
			, CONCAT(beginn, IFNULL(CONCAT("/", ende), "")) AS dauer
			, YEAR(beginn) AS jahr, IFNULL(ende, beginn) AS ende
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
		LEFT JOIN turniere USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON turniere.turnierform_category_id = turnierformen.category_id
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses USING (contact_id)
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, $zz_setting['website_id'], wrap_db_escape(implode('/', $vars)));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	$event[str_replace('-', '_', $event['turnierform'])] = true;

	$page['breadcrumbs'][] = '<a href="../../">'.$event['jahr'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$event['termin'].'</a>';
	$page['extra']['realm'] = 'sports';
	$page['dont_show_h1'] = true;

	$meldeliste = false;
	if ($event['turnierform'] === 'e') {
		$event = mod_tournaments_startrangliste_einzel($event);
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
		$event = mod_tournaments_startrangliste_mannschaft($event);
	}
	if ($meldeliste) {
		$page['title'] = $event['termin'].' '.$event['jahr'].': Meldeliste';
		$page['breadcrumbs'][] = 'Meldeliste';
		$event['meldeliste'] = true;
		$event['meldungen'] = !empty($event['spieler']) ? count($event['spieler']) : count($event['teams']);
	} else {
		$page['title'] = $event['termin'].' '.$event['jahr'].': Startrangliste';
		$page['breadcrumbs'][] = 'Startrangliste';
	}

	if ($event['turnierform'] === 'e') {
		$page['text'] = wrap_template('startrangliste-einzel', $event);
	} else {
		$page['text'] = wrap_template('startrangliste-mannschaft', $event);
	}
	return $page;
}

function mod_tournaments_startrangliste_einzel($event) {
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
			, landesverbaende.kennung AS lv_kennung
			, landesverbaende.org_abk AS lv_kurz
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, teilnahme_status
			, DATE_FORMAT(eintrag_datum, "%%d.%%m %%H:%%i") AS eintrag_datum
			, eintrag_datum AS eintrag_datum_raw
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
			ON organisationen_orte.main_contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE event_id = %d
		AND usergroup_id = %d
		AND teilnahme_status IN (%s"Teilnehmer", "disqualifiziert", "geblockt")
		ORDER BY setzliste_no, IFNULL(t_dwz, t_elo) DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql
		, $zz_setting['org_ids']['dsb']
		, $event['event_id']
		, wrap_id('usergroups', 'spieler')
		, ($event['ende'] >= date('Y-m-d')) ? '"angemeldet", ' : ''
	);
	$event['spieler'] = wrap_db_fetch($sql, 'person_id');
	$event['spieler'] = my_get_personen_kennungen($event['spieler'], ['fide-id', 'zps']);
	$event['zeige_dwz'] = false;
	$event['zeige_elo'] = false;
	$event['zeige_titel'] = false;
	foreach ($event['spieler'] as $person_id => $spieler) {
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
	}
	return $event;
}
	
function mod_tournaments_startrangliste_mannschaft($event) {
	global $zz_setting;

	$sql = 'SELECT team_id
			, team, team_no
			, IF(NOT ISNULL(teams.setzliste_no), teams.kennung, "") AS kennung, team_status, country
			, SUBSTRING_INDEX(teams.kennung, "/", -1) AS team_identifier_short
			, places.contact AS veranstaltungsort, place, latitude, longitude, setzliste_no
			, IFNULL(landesverbaende.kennung, landesverbaende_rueckwaerts.kennung) AS lv_kennung
			, IFNULL(landesverbaende.org_abk, landesverbaende_rueckwaerts.org_abk) AS lv_kurz
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
			AND landesverbaende_rueckwaerts.category_id = %d
			AND landesverbaende_rueckwaerts.mutter_org_id = %d
		LEFT JOIN organisationen_orte
			ON organisationen_orte.org_id = organisationen.org_id
			AND organisationen_orte.published = "yes"
		LEFT JOIN contacts places
			ON organisationen_orte.main_contact_id = places.contact_id
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
		, wrap_category_id('organisationen/verband')
		, $zz_setting['org_ids']['dsb']
		, $event['event_id']
	);
	// @todo Klären, was passiert wenn mehr als 1 Ort zu Verein in Datenbank! (Reihenfolge-Feld einführen)
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	if (!$event['teams']) wrap_quit(404); // es liegt noch keine Rangliste vor.
	$event['meldeliste'] = false;
	foreach (array_keys($event['teams']) AS $team_id) {
		// Meldelistestatus nur bei Terminen, die noch nicht zuende sind
		if (empty($event['teams'][$team_id]['setzliste_no']) AND $event['ende'] > date('Y-m-d')) $event['meldeliste'] = true;
		$event['teams'][$team_id][str_replace('-', '_', $event['turnierform'])] = true;
		if ($event['teams'][$team_id]['country']) $event['country'] = true;
	}

	$dwz_sortierung = false;
	if ($event['teilnehmerliste']) {
		$dwz_sortierung = true;
		$erstes_team = current($event['teams']);
		if ($erstes_team['setzliste_no']) $dwz_sortierung = false;

		list($event['dwz_schnitt'], $event['teams']) 
			= my_dwz_schnitt_teams($event['event_id'], $event['teams'], $event['bretter_min'], $event['pseudo_dwz']);
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
