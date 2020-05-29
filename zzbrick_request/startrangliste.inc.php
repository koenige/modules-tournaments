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
	
	$sql = 'SELECT termine.termin_id, termin
			, CONCAT(beginn, IFNULL(CONCAT("/", ende), "")) AS dauer
			, YEAR(beginn) AS jahr, IFNULL(ende, beginn) AS ende
			, places.contact AS veranstaltungsort
			, address, postcode, place, places.description
			, latitude, longitude
			, termine.kennung
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste
			, IFNULL(place, places.contact) AS turnierort
			, pseudo_dwz, bretter_min
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(LENGTH(hauptreihen.path) > 7, SUBSTRING_INDEX(hauptreihen.path, "/", -1), NULL) AS hauptreihe_kennung
			, hauptreihen.category_short AS hauptreihe
		FROM termine
		LEFT JOIN categories reihen
			ON termine.reihe_category_id = reihen.category_id
		LEFT JOIN categories hauptreihen
			ON hauptreihen.category_id = reihen.main_category_id
		LEFT JOIN turniere USING (termin_id)
		JOIN termine_websites
			ON termine_websites.termin_id = termine.termin_id
			AND termine_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON turniere.turnierform_category_id = turnierformen.category_id
		LEFT JOIN contacts places
			ON termine.place_contact_id = places.contact_id
		LEFT JOIN addresses USING (contact_id)
		WHERE termine.kennung = "%s"';
	$sql = sprintf($sql, $zz_setting['website_id'], wrap_db_escape(implode('/', $vars)));
	$termin = wrap_db_fetch($sql);
	if (!$termin) return false;
	$termin[str_replace('-', '_', $termin['turnierform'])] = true;

	$page['breadcrumbs'][] = '<a href="../../">'.$termin['jahr'].'</a>';
	if ($termin['hauptreihe']) {
		$page['breadcrumbs'][] = '<a href="../../'.$termin['hauptreihe_kennung'].'/">'.$termin['hauptreihe'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$termin['termin'].'</a>';
	$page['extra']['realm'] = 'sports';
	$page['dont_show_h1'] = true;

	$meldeliste = false;
	if ($termin['turnierform'] === 'e') {
		$termin = mod_tournaments_startrangliste_einzel($termin);
		foreach ($termin['spieler'] as $spieler) {
			if ($spieler['teilnahme_status'] !== 'angemeldet') continue;
			$meldeliste = true;
			break;
		}
		if ($meldeliste) {
			foreach (array_keys($termin['spieler']) as $person_id) {
				$termin['spieler'][$person_id]['meldeliste'] = true;
			}
		}
		if ($termin['latitude']) {
			$page['head'] = wrap_template('termin-map-head');
			$termin['map'] = my_teilnehmerkarte($termin);
		}
	} else {
		$termin = mod_tournaments_startrangliste_mannschaft($termin);
	}
	if ($meldeliste) {
		$page['title'] = $termin['termin'].' '.$termin['jahr'].': Meldeliste';
		$page['breadcrumbs'][] = 'Meldeliste';
		$termin['meldeliste'] = true;
		$termin['meldungen'] = !empty($termin['spieler']) ? count($termin['spieler']) : count($termin['teams']);
	} else {
		$page['title'] = $termin['termin'].' '.$termin['jahr'].': Startrangliste';
		$page['breadcrumbs'][] = 'Startrangliste';
	}

	if ($termin['turnierform'] === 'e') {
		$page['text'] = wrap_template('startrangliste-einzel', $termin);
	} else {
		$page['text'] = wrap_template('startrangliste-mannschaft', $termin);
	}
	return $page;
}

function mod_tournaments_startrangliste_einzel($termin) {
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
			, IF(LENGTH(hauptreihen.path) > 7, SUBSTRING_INDEX(hauptreihen.path, "/", -1), NULL) AS hauptreihe_kennung
			, teilnahme_status
			, DATE_FORMAT(eintrag_datum, "%%d.%%m %%H:%%i") AS eintrag_datum
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
		LEFT JOIN termine USING (termin_id)
		LEFT JOIN categories reihen
			ON termine.reihe_category_id = reihen.category_id
		LEFT JOIN categories hauptreihen
			ON reihen.main_category_id = hauptreihen.category_id
		WHERE termin_id = %d
		AND gruppe_id = %d
		AND teilnahme_status IN (%s"Teilnehmer", "disqualifiziert", "geblockt")
		ORDER BY setzliste_no, IFNULL(t_dwz, t_elo) DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql
		, $zz_setting['org_ids']['dsb']
		, $termin['termin_id']
		, $zz_setting['gruppen_ids']['spieler']
		, $termin['ende'] > date('Y-m-d') ? '"angemeldet", ' : ''
	);
	$termin['spieler'] = wrap_db_fetch($sql, 'person_id');
	$termin['spieler'] = my_get_personen_kennungen($termin['spieler'], ['fide-id', 'zps']);
	$termin['zeige_dwz'] = false;
	$termin['zeige_elo'] = false;
	$termin['zeige_titel'] = false;
	foreach ($termin['spieler'] as $person_id => $spieler) {
		if ($spieler['t_fidetitel']) $termin['zeige_titel'] = true;
		if ($spieler['t_elo']) $termin['zeige_elo'] = true;
		if ($spieler['t_dwz']) $termin['zeige_dwz'] = true;
		if (!$spieler['t_fidetitel']) continue;
		$termin['spieler'][$person_id]['fidetitel_lang'] = my_fidetitel($spieler['t_fidetitel']);
	}
	foreach ($termin['spieler'] as $person_id => $spieler) {
		if ($termin['zeige_dwz']) $termin['spieler'][$person_id]['zeige_dwz'] = true;
		if ($termin['zeige_elo']) $termin['spieler'][$person_id]['zeige_elo'] = true;
		if ($termin['zeige_titel']) $termin['spieler'][$person_id]['zeige_titel'] = true;
	}
	return $termin;
}
	
function mod_tournaments_startrangliste_mannschaft($termin) {
	global $zz_setting;

	$sql = 'SELECT team_id
			, team, team_no
			, IF(NOT ISNULL(teams.setzliste_no), teams.kennung, "") AS kennung, team_status, country
			, SUBSTRING_INDEX(teams.kennung, "/", -1) AS team_kennung_kurz
			, places.contact AS veranstaltungsort, place, latitude, longitude, setzliste_no
			, IFNULL(landesverbaende.kennung, landesverbaende_rueckwaerts.kennung) AS lv_kennung
			, IFNULL(landesverbaende.org_abk, landesverbaende_rueckwaerts.org_abk) AS lv_kurz
			, IF(LENGTH(hauptreihen.path) > 7, SUBSTRING_INDEX(hauptreihen.path, "/", -1), NULL) AS hauptreihe_kennung
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
		LEFT JOIN termine USING (termin_id)
		LEFT JOIN categories reihen
			ON termine.reihe_category_id = reihen.category_id
		LEFT JOIN categories hauptreihen
			ON reihen.main_category_id = hauptreihen.category_id
		WHERE termin_id = %d
		AND team_status = "Teilnehmer"
		AND spielfrei = "nein"
		ORDER BY setzliste_no, place, team';
	$sql = sprintf($sql
		, $zz_setting['org_ids']['dsb']
		, wrap_category_id('organisationen/verband')
		, $zz_setting['org_ids']['dsb']
		, $termin['termin_id']
	);
	// @todo Klären, was passiert wenn mehr als 1 Ort zu Verein in Datenbank! (Reihenfolge-Feld einführen)
	$termin['teams'] = wrap_db_fetch($sql, 'team_id');
	if (!$termin['teams']) wrap_quit(404); // es liegt noch keine Rangliste vor.
	$termin['meldeliste'] = false;
	foreach (array_keys($termin['teams']) AS $team_id) {
		// Meldelistestatus nur bei Terminen, die noch nicht zuende sind
		if (empty($termin['teams'][$team_id]['setzliste_no']) AND $termin['ende'] > date('Y-m-d')) $termin['meldeliste'] = true;
		$termin['teams'][$team_id][str_replace('-', '_', $termin['turnierform'])] = true;
		if ($termin['teams'][$team_id]['country']) $termin['country'] = true;
	}

	$dwz_sortierung = false;
	if ($termin['teilnehmerliste']) {
		$dwz_sortierung = true;
		$erstes_team = current($termin['teams']);
		if ($erstes_team['setzliste_no']) $dwz_sortierung = false;

		list($termin['dwz_schnitt'], $termin['teams']) 
			= my_dwz_schnitt_teams($termin['termin_id'], $termin['teams'], $termin['bretter_min'], $termin['pseudo_dwz']);
	}

	foreach ($termin['teams'] AS $key => $row) {
		if ($termin['meldeliste']) $termin['teams'][$key]['meldeliste'] = true;
		if ($dwz_sortierung) {
			$teamname[$key] = $row['place'];
			$verband[$key] = $row['country'];
			$schnitt[$key] = $row['dwz_schnitt'];
			if ($row['dwz_schnitt']) $termin['dwz_schnitt'] = true;
		}
	}
	if ($dwz_sortierung) {
		//Nach DWZ-Schnitt absteigend, danach nach Teamname aufsteigend sortieren
		array_multisort($schnitt, SORT_DESC, $teamname, SORT_ASC, $termin['teams']);
	}

	return $termin;
}
