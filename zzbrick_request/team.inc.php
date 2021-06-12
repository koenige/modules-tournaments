<?php

/**
 * tournaments module
 * show team of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_team($vars, $settings) {
	global $zz_setting;

	if (!empty($settings['intern'])) {
		$intern = true;
		$sql_condition = '';
	} else {
		$intern = false;
		$sql_condition = ' AND NOT ISNULL(events_websites.website_id) ';
	}
	
	$sql = 'SELECT teams.team_id, team, team_no
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, setzliste_no
			, platz_no
			, v_ok.identifier AS zps_code, organisationen.org_id
			, teams.kennung AS team_identifier
			, SUBSTRING_INDEX(teams.kennung, "/", -1) AS team_identifier_short
			, meldung_datum, regionalgruppe
			, meldung
			, organisationen.website, organisationen.organisation
			, organisationen.kennung AS organisation_kennung
			, IFNULL(landesverbaende.kennung, landesverbaende_rueckwaerts.kennung) AS lv_kennung
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, country
			, @laufende_partien:= (SELECT IF(COUNT(partie_id) = 0, NULL, 1) FROM partien
				WHERE partien.event_id = events.event_id AND ISNULL(weiss_ergebnis)
			) AS zwischenstand
			, IF(ISNULL(@laufende_partien)
				AND tournaments.tabellenstand_runde_no = tournaments.runden, 1, NULL) AS endstand 
			, teams.team_status
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
		LEFT JOIN regionalgruppen
			ON regionalgruppen.landesverband_org_id = landesverbaende.org_id
		LEFT JOIN organisationen landesverbaende_rueckwaerts
			ON countries.country_id = landesverbaende_rueckwaerts.country_id
			AND landesverbaende_rueckwaerts.category_id = %d
			AND landesverbaende_rueckwaerts.mutter_org_id = %d
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN tabellenstaende
			ON tabellenstaende.team_id = teams.team_id
			AND (ISNULL(tabellenstaende.runde_no)
				OR tabellenstaende.runde_no = tournaments.tabellenstand_runde_no)
		WHERE teams.kennung = "%s"
		AND spielfrei = "nein"
		%s
	';
	$sql = sprintf($sql
		, $zz_setting['org_ids']['dsb']
		, wrap_category_id('organisationen/verband')
		, $zz_setting['org_ids']['dsb']
		, $zz_setting['website_id']
		, wrap_db_escape(implode('/', $vars))
		, $sql_condition
	);
	$team = wrap_db_fetch($sql);
	if (!$team) return false;
	$team[str_replace('-', '_', $team['turnierform'])] = true;

	array_pop($vars);
	$sql = 'SELECT event_id, event, bretter_min, bretter_max, alter_max, alter_min
			, geschlecht, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste
			, pseudo_dwz
			, IFNULL(place, places.contact) AS turnierort
			, YEAR(date_begin) AS year
			, events.identifier AS event_identifier
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, berechtigung_zusage, berechtigung_absage, berechtigung_spaeter
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, place_categories.parameters
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN contacts places
			ON places.contact_id = events.place_contact_id
		LEFT JOIN addresses
			ON addresses.contact_id = places.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		LEFT JOIN categories place_categories
			ON places.contact_category_id = place_categories.category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape(implode('/', $vars)));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;
	parse_str($event['parameters'], $parameters);
	$event += $parameters;

	$page['title'] = $event['event'].' '.$event['year'].': '.$team['team'].' '.$team['team_no'];
	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$event['event'].'</a>';
	$page['dont_show_h1'] = true;
	$page['extra']['realm'] = 'sports';
	$data = array_merge($team, $event);
	if ($data['team_status'] !== 'Teilnehmer' AND !$intern) {
		switch ($data['team_status']) {
			case 'Löschung':
				$data['team_withdrawn'] = true;
				$page['status'] = 410;
				$page['text'] = wrap_template('team', $data);
				return $page;
			default:
				return false;
		}
	}

	if ($intern) return mod_tournaments_team_intern($page, $data);
	return mod_tournaments_team_public($page, $data);
}

/**
 * Ausgabe Team-Ansicht extern
 *
 * @param array $page
 * @param array $data
 * @return array $page
 */
function mod_tournaments_team_public($page, $data) {
	global $zz_setting;

	if (!$data['teilnehmerliste']) {
		// Umleitung zur Terminübersicht
		return brick_format('%%% redirect /'.$data['event_identifier'].'/ %%%');
	}

	// Einen Spielort auslesen
	$sql = 'SELECT contacts.contact_id AS place_id
			, latitude, longitude, contacts.contact AS veranstaltungsort
			, place, address, postcode
		FROM contacts
		LEFT JOIN addresses USING (contact_id)
		LEFT JOIN organisationen_orte
			ON organisationen_orte.contact_id = contacts.contact_id
		WHERE org_id = %d
		AND organisationen_orte.published = "yes"
		ORDER BY contacts.contact_id LIMIT 1';
	$sql = sprintf($sql, $data['org_id']);
	$data = array_merge($data, wrap_db_fetch($sql));

	// Bilder auslesen
	$url = sprintf($zz_setting['mediaserver_website'], $data['event_identifier'], 'Website');
	$zz_setting['brick_cms_input'] = 'json';
	$bilder = brick_request_external($url, $zz_setting);
	unset($bilder['_']); // metadata
	foreach ($bilder as $bild) {
		foreach ($bild['meta'] as $meta) {
			if ($meta['foreign_key'] !== $data['team_id']) continue;
			if ($meta['category_identifier'] !== 'group') continue;
			$data['bilder'][] = $bild;
		}
	}

	// Prev/Next-Navigation
	$sql = 'SELECT team_id, kennung
		FROM teams
		WHERE event_id = %d
		AND team_status = "Teilnehmer"
		AND spielfrei = "nein"
		ORDER BY setzliste_no';
	$sql = sprintf($sql, $data['event_id']);
	$teams = wrap_db_fetch($sql, 'team_id');
	$data = array_merge($data, wrap_get_prevnext_flat($teams, $data['team_id'], true));

	$page['breadcrumbs'][] = $data['team'].' '.$data['team_no'];
	$page['link']['next'][0]['href'] = '../../../'.$data['_next_kennung'].'/';	
	$page['link']['next'][0]['title'] = 'Nächste/r in Setzliste';
	$page['link']['prev'][0]['href'] = '../../../'.$data['_prev_kennung'].'/';	
	$page['link']['prev'][0]['title'] = 'Vorherige/r in Setzliste';

	if (!empty($data['latitude'])) {
		$page['head'] = wrap_template('termin-map-head');
		$data['map'] = true;
	}

	if (!in_array($data['meldung'], ['komplett', 'teiloffen'])) {
		$page['text'] = wrap_template('team', $data);
		return $page;
	}

	// Spieler
	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	$data = array_merge($data, mf_tournaments_team_players($data['team_id'], $data));

	// Paarungen
	$sql = 'SELECT paarungen.runde_no, tisch_no
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS gegner
			, IF(spielfrei = "ja", bretter_min, 
				SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung))
			) AS punkte
			, IF(SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) < %s, 1, NULL) verloren
			, IF(SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) = %s, 1, NULL) unentschieden
			, IF(spielfrei = "ja" OR SUM(IF(paarungen.heim_team_id = %d, heim_wertung, auswaerts_wertung)) > %s, 1, NULL) gewonnen
			, IF(spielfrei = "ja", 1, NULL) AS spielfrei
		FROM paarungen
		LEFT JOIN teams
			ON (IF(paarungen.heim_team_id = %d, paarungen.auswaerts_team_id, paarungen.heim_team_id) = teams.team_id)
		LEFT JOIN partien USING (paarung_id)
		LEFT JOIN tournaments
			ON paarungen.event_id = tournaments.event_id
		WHERE (heim_team_id = %d OR auswaerts_team_id = %d)
		AND paarungen.event_id = %d
		GROUP BY paarung_id, team_id
		ORDER BY runde_no';
	$sql = sprintf($sql, $data['team_id'], $data['team_id'], $data['bretter_min']/2
		, $data['team_id'], $data['bretter_min']/2, $data['team_id'], $data['bretter_min']/2, $data['team_id']
		, $data['team_id'], $data['team_id'], $data['event_id']);
	$data['paarungen'] = wrap_db_fetch($sql, 'runde_no');
	if ($data['paarungen']) {
		$data['summe_bp'] = 0;
		$data['summe_mp'] = 0;
		foreach ($data['paarungen'] AS $paarung_id => $paarung) {
			if ($paarung['spielfrei']) $data['spielfrei'] = true;
			$data['summe_bp'] += $paarung['punkte'];
			if ($paarung['gewonnen'])
				$data['summe_mp'] += 2;
			elseif ($paarung['unentschieden'])
				$data['summe_mp'] += 1;
			elseif ($paarung['unentschieden'])
				$data['summe_mp'] += 0; // + 0 damit es Zahl ist.
			$round = $data;
			$round['runde_no'] = $paarung['runde_no'];
			$round['identifier'] = $round['event_identifier'];
			$data['paarungen'][$paarung_id]['lineup'] = mf_tournaments_lineup($round);
		}
	}

	// Partien
	if (!empty($data['spieler'])) {
		$sql = 'SELECT partie_id
				, partien.runde_no, partien.brett_no, tisch_no
				, IF(ISNULL(weiss_status.t_vorname),
					white_contact.contact,
					CONCAT(weiss_status.t_vorname, " ", IFNULL(CONCAT(weiss_status.t_namenszusatz, " "), ""), weiss_status.t_nachname)
				) AS weiss
				, weiss_person_id
				, IF(partiestatus_category_id = %d, 0.5,
					CASE weiss_ergebnis
					WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
					WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
					WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
					END
				) AS weiss_ergebnis
				, IF(partiestatus_category_id = %d, 0.5,
					CASE schwarz_ergebnis
					WHEN 1.0 THEN IF(partiestatus_category_id = %d, "+", 1)
					WHEN 0.5 THEN IF(partiestatus_category_id = %d, "=", 0.5)
					WHEN 0 THEN IF(partiestatus_category_id = %d, "-", 0)
					END
				) AS schwarz_ergebnis
				, IF(ISNULL(schwarz_status.t_vorname),
					black_contact.contact,
					CONCAT(schwarz_status.t_vorname, " ", IFNULL(CONCAT(schwarz_status.t_namenszusatz, " "), ""), schwarz_status.t_nachname)
				) AS schwarz
				, schwarz_person_id
				, IF(partiestatus_category_id = %d, 1, NULL) AS haengepartie
				, category AS partiestatus
				, IF(pgn, IF(partiestatus_category_id != %d, 1, NULL), NULL) AS pgn
				, schwarz_status.t_dwz AS schwarz_dwz
				, schwarz_status.t_elo AS schwarz_elo
				, weiss_status.t_dwz AS weiss_dwz
				, weiss_status.t_elo AS weiss_elo
			FROM partien
			LEFT JOIN categories
				ON partien.partiestatus_category_id = categories.category_id
			LEFT JOIN paarungen USING (paarung_id)
			LEFT JOIN personen weiss
				ON weiss.person_id = partien.weiss_person_id
			LEFT JOIN contacts white_contact
				ON weiss.contact_id = white_contact.contact_id
			LEFT JOIN teilnahmen weiss_status
				ON weiss_status.person_id = weiss.person_id
				AND weiss_status.usergroup_id = %d
				AND weiss_status.event_id = %d
			LEFT JOIN personen schwarz
				ON schwarz.person_id = partien.schwarz_person_id
			LEFT JOIN contacts black_contact
				ON schwarz.contact_id = black_contact.contact_id
			LEFT JOIN teilnahmen schwarz_status
				ON schwarz_status.person_id = schwarz.person_id
				AND schwarz_status.usergroup_id = %d
				AND schwarz_status.event_id = %d
			WHERE partien.event_id = %d
			AND (weiss_person_id IN (%s) OR schwarz_person_id IN (%s))';
		$sql = sprintf($sql
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_category_id('partiestatus/haengepartie')
			, wrap_category_id('partiestatus/kampflos')
			, wrap_id('usergroups', 'spieler'), $data['event_id']
			, wrap_id('usergroups', 'spieler'), $data['event_id']
			, $data['event_id']
			, implode(',', array_keys($data['spieler']))
			, implode(',', array_keys($data['spieler']))
		);
		$partien = wrap_db_fetch($sql, 'partie_id');
		foreach ($data['spieler'] as $person_id => $spieler) {
			foreach (array_keys($data['paarungen']) as $runde) {
				$data['spieler'][$person_id]['partien'][$runde] = [];
				$data['spieler'][$person_id]['partien'][$runde]['ergebnis'] = '';
				if ($data['paarungen'][$runde]['spielfrei']) {
					$data['spieler'][$person_id]['partien'][$runde]['partie_spielfrei'] = true;
				}
			}
			$data['spieler'][$person_id]['summe_bp'] = 0;
			if (!$partien) continue;
			foreach ($partien as $partie_id => $partie) {
				if ($data['paarungen'][$partie['runde_no']]['lineup']) continue;
				$ergebnis = '';
				if (empty($data['spieler'][$person_id]['partien'][$partie['runde_no']])) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = [];
				}
				if ($partie['weiss_person_id'] == $person_id) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = array_merge(
						$data['spieler'][$person_id]['partien'][$partie['runde_no']], $partie, [
						'gegner' => $partie['schwarz'],
						'gegner_dwz' => $partie['schwarz_dwz'],
						'gegner_elo' => $partie['schwarz_elo'],
						'ergebnis' => $partie['weiss_ergebnis'],
						'farbe' => 'weiß',
						'farbe_kennung' => 'weiss',
						'live' => ($partie['pgn'] AND is_null($partie['weiss_ergebnis']) ? true : false)
					]);
					$ergebnis = $partie['weiss_ergebnis'];
					unset($partien[$partie_id]);
				} elseif ($partie['schwarz_person_id'] == $person_id) {
					$data['spieler'][$person_id]['partien'][$partie['runde_no']] = array_merge(
						$data['spieler'][$person_id]['partien'][$partie['runde_no']], $partie, [
						'gegner' => $partie['weiss'],
						'gegner_dwz' => $partie['weiss_dwz'],
						'gegner_elo' => $partie['weiss_elo'],
						'ergebnis' => $partie['schwarz_ergebnis'],
						'farbe' => 'schwarz',
						'farbe_kennung' => 'schwarz',
						'live' => ($partie['pgn'] AND is_null($partie['schwarz_ergebnis']) ? true : false)
					]);
					$ergebnis = $partie['schwarz_ergebnis'];
					unset($partien[$partie_id]);
				}
				if ($ergebnis === '+') $ergebnis = 1;
				elseif ($ergebnis === '=') $ergebnis = 0.5;
				elseif ($ergebnis === '-') $ergebnis = 0;
				if ($ergebnis !== '') {
					$data['spieler'][$person_id]['summe_bp'] += $ergebnis;
				}
			}
		}
	}

	$sql = 'SELECT event_link_id, link, link_text
		FROM events_links
		WHERE team_id = %d';
	$sql = sprintf($sql, $data['team_id']);
	$data['links'] = wrap_db_fetch($sql, 'event_link_id');

	$page['text'] = wrap_template('team', $data);
	return $page;
}

/**
 * Ausgabe Team-Ansicht mit Meldung intern
 *
 * @param array $page
 * @param array $data
 * @return array $page
 */
function mod_tournaments_team_intern($page, $data) {
	global $zz_setting;
	global $zz_conf;

	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	if (!my_team_access($data['team_id'])) {
		$page = brick_format('%%% redirect /'.$data['team_identifier'].'/ %%%');
		return $page;
	}
	if ($data['team_status'] === 'Teilnahmeberechtigt') {
		$data['abfrage_teilnahme'] = true;
		if (!empty($_POST['berechtigung'])) {
			return mod_tournaments_team_intern_berechtigung($data);
		}
		if (array_key_exists('spaeter', $_GET)) {
			$data['abfrage_spaeter'] = true;
		}
	}

	if ($data['datum_anreise'] AND $data['uhrzeit_anreise']
		AND $data['datum_abreise'] AND $data['uhrzeit_abreise']) {
		$data['reisedaten_komplett'] = true;	
	}

	// line-up?
	// a round is paired, round has not started, timeframe for line-up is open
	$lineup = brick_format('%%% make lineup_active '.implode(' ', explode('/', $data['team_identifier'])).' %%%');
	if ($lineup['text']) $data['lineup'] = true;

	require_once $zz_conf['dir'].'/zzform.php';

	if (!empty($_POST) AND array_key_exists('komplett', $_POST)) {
		// Meldung komplett
		$values = [];
		$values['action'] = 'update';
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['meldung'] = 'komplett';
		$values['POST']['meldung_datum'] = date('Y-m-d H:i:s');
		$values['ids'] = ['team_id'];
		$ops = zzform_multi('teams', $values);
		if (!$ops['id']) {
			wrap_error(sprintf('Komplettstatus für Team-ID %d konnte nicht hinzugefügt werden',
				$data['team_id']), E_USER_ERROR);
		}
		return wrap_redirect_change();
	}
	$sql = 'SELECT meldung 
		FROM teams
		WHERE team_id = %d';
	$sql = sprintf($sql, $data['team_id']);
	$bearbeiten = wrap_db_fetch($sql, '', 'single value');
	if ($bearbeiten === 'offen') {
		$data['bearbeiten_aufstellung'] = true;
		$data['bearbeiten_sonstige'] = true;
	} elseif ($bearbeiten === 'teiloffen') {
		$data['bearbeiten_sonstige'] = true;
	}

	// Buchungen
	$data = array_merge($data, my_team_buchungen($data['team_id'], $data));

	// Team + Vereinsbetreuer auslesen
	$data = array_merge($data, my_team_teilnehmer([$data['team_id'] => $data['org_id']], $data));

	$data['komplett'] = my_team_meldung_komplett($data);
	if ($data['meldung'] === 'komplett') $data['pdfupload'] = true;

	$page['query_strings'][] = 'spaeter';
	$page['breadcrumbs'][] = $data['team'].' '.$data['team_no'];
	$page['text'] = wrap_template('team-intern', $data);
	return $page;
}

/**
 * Speichere Zu- oder Absage für Teilnahme am Turnier
 *
 * @param array $data
 * @return void
 */
function mod_tournaments_team_intern_berechtigung($data) {
	global $zz_conf;
	require_once $zz_conf['dir'].'/zzform.php';
	$values = [];

	switch ($_POST['berechtigung']) {
	case 'absage':
/*
Bei Absage wird ebenfalls der angekreuzte Text geloggt, der Status
aber auf gelöscht gestellt. Eine Meldung oder Statusänderung ist dann
nicht mehr möglich.
*/
		$values['POST']['anmerkung'] = $data['berechtigung_absage'].
			(!empty($_POST['bemerkungen']) ? ' – '.$_POST['bemerkungen'] : '');
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['anmerkung_status'] = 'offen';
		$values['POST']['benachrichtigung'] = 'ja';
		$values['POST']['sichtbarkeit'] = ['Team', 'Organisator'];
		$values['action'] = 'insert';
		$ops = zzform_multi('anmerkungen', $values);

		$values = [];
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['team_status'] = 'Löschung';
		$values['action'] = 'update';
		$ops = zzform_multi('teams', $values);
		/*
Mir würde das reichen, wenn die Meldungen der Form "Hat abgesagt am
xx.xx.xxxx durch yy" als unerledigte Anmerkung zur Mannschaft hinterlegt
werden.
		*/
		$url = substr($_SERVER['REQUEST_URI'], 0, -1);
		$url = substr($url, 0, strrpos($url, '/') + 1);
		return wrap_redirect_change($url.'?absage');
	case 'zusage':
		/*
Bei Zusage wird der Teilnahmestatus auf Teilnehmer gesetzt und man
kann ganz normal melden. Dazu wird im Hintergrund die Zusage mit
Termin, Team, Zusagetext und Timestamp in einer Logtabelle
gespeichert.
		*/
		$values['POST']['anmerkung'] = $data['berechtigung_zusage'].
			(!empty($_POST['bemerkungen']) ? ' – '.$_POST['bemerkungen'] : '');
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['anmerkung_status'] = !empty($_POST['bemerkungen']) ? 'offen' : 'erledigt';
		$values['POST']['benachrichtigung'] = !empty($_POST['bemerkungen']) ? 'ja' : 'nein';
		$values['POST']['sichtbarkeit'] = ['Team', 'Organisator'];
		$values['action'] = 'insert';
		$ops = zzform_multi('anmerkungen', $values);

		$values = [];
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['team_status'] = 'Teilnehmer';
		$values['action'] = 'update';
		$ops = zzform_multi('teams', $values);
		return wrap_redirect_change();
	case 'spaeter':
/*
Bei späterer Meldung wird der Teilnahmestatus nicht geändert. Es wird
lediglich ein Logeintrag geschrieben, und zwar mit der Begründung aus
dem Freitextfeld. Dadurch kann zu einem späteren Zeitpunkt zu- oder
abgesagt werden oder auch zwischendurch eine Nachricht geschrieben
werden.
*/
		$values['POST']['anmerkung'] = $data['berechtigung_spaeter']
			.(!empty($_POST['bemerkungen']) ? ' – '.$_POST['bemerkungen'] : '');
		$values['POST']['team_id'] = $data['team_id'];
		$values['POST']['anmerkung_status'] = 'offen';
		$values['POST']['benachrichtigung'] = 'ja';
		$values['POST']['sichtbarkeit'] = ['Team', 'Organisator'];
		$values['action'] = 'insert';
		$ops = zzform_multi('anmerkungen', $values);
		return wrap_redirect_change('?spaeter');
	}
	return false;
}

/**
 * Alle Spieler eines Teams / mehrerer Teams
 *
 * @param mixed $team_ids (int = eine Team-ID, array = mehrere Team-IDs)
 * @param array $event
 *		int bretter_min
 * @return array $daten
 */
function mf_tournaments_team_players($team_ids, $event) {
	$sql = 'SELECT team_id, person_id
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
			, t_verein, t_dwz, t_elo, t_fidetitel, spielberechtigt, rang_no, brett_no
			, IF(teilnahmen.gastspieler = "ja", 1, NULL) AS gastspieler
			, IF(tournaments.gastspieler = "ja", 1, NULL) AS gastspieler_status
			, YEAR(date_of_birth) AS geburtsjahr
			, pseudo_dwz
		FROM teilnahmen
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN teams USING (team_id)
		LEFT JOIN personen USING (person_id)
		WHERE usergroups.identifier = "spieler"
		AND (ISNULL(spielberechtigt) OR spielberechtigt = "ja")
		AND teilnahmen.teilnahme_status = "Teilnehmer"
		AND team_id IN (%s)
		ORDER BY ISNULL(brett_no), brett_no, rang_no, t_dwz DESC, t_elo DESC, t_nachname, t_vorname';
	$sql = sprintf($sql, is_array($team_ids) ? implode(',', $team_ids) : $team_ids);
	$alle_spieler = wrap_db_fetch($sql, ['team_id', 'person_id']);
	if (!$alle_spieler) return [];
	foreach ($alle_spieler as $id => $team_spieler) {
		$team_spieler = my_get_personen_kennungen($team_spieler, ['fide-id', 'zps']);

		$teams[$id]['spielberechtigt'] = true;
		$teams[$id]['dwz_schnitt'] = 0;
		$bretter_min = $event['bretter_min'];
		$bretter_dwz = 0;
		$brett_no = false;
		// Gibt es Spieler mit Brett-No.?
		foreach ($team_spieler as $person_id => $spieler) {
			if (!$brett_no) $brett_no = $spieler['brett_no'];
			else break;
		}
		// Spieler- und Teamdaten ergänzen
		foreach ($team_spieler as $person_id => $spieler) {
			if ($spieler['t_fidetitel']) {
				$team_spieler[$person_id]['fidetitel_lang'] = my_fidetitel($spieler['t_fidetitel']);
			}
			if ($brett_no AND !$spieler['brett_no']) {
				// Es gibt Spieler mit Brettnummern, also blenden wir die ohne aus,
				// die wurden nur gemeldet, aber nicht eingesetzt
				unset($team_spieler[$person_id]);
			}
			if (!$spieler['spielberechtigt'] OR $spieler['spielberechtigt'] !== 'ja') {
				$teams[$id]['spielberechtigt'] = false;
			}
		}
		$teams[$id]['spieler'] = $team_spieler;
		// DWZ-Schnitt
		if (!$brett_no) {
			$team_spieler = my_team_sort_rating($team_spieler);
		}
		foreach ($team_spieler as $person_id => $spieler) {
			if ($bretter_min > 0) {
				if ($spieler['t_dwz']) {
					$teams[$id]['dwz_schnitt'] += $spieler['t_dwz'];
					$bretter_dwz++;
				} elseif ($event['pseudo_dwz']) {
					$teams[$id]['dwz_schnitt'] += $spieler['pseudo_dwz'];
					$bretter_dwz++;
				}
			}
			$bretter_min--;
		}
		if ($bretter_dwz) {
			$teams[$id]['dwz_schnitt'] /= $bretter_dwz;
			$teams[$id]['dwz_schnitt'] = round($teams[$id]['dwz_schnitt'], 0);
		}
	}
	if (is_array($team_ids)) return $teams;
	else return $teams[$team_ids];
}
