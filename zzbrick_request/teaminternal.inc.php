<?php

/**
 * tournaments module
 * internal view of team of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_teaminternal($vars, $settings) {
	global $zz_setting;

	$sql = 'SELECT teams.team_id, team, team_no
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, setzliste_no
			, platz_no
			, v_ok.identifier AS zps_code, contacts.contact_id, contacts.contact_id
			, teams.identifier AS team_identifier
			, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
			, meldung_datum, regionalgruppe
			, meldung
			, contacts.contact
			, contacts.identifier AS organisation_kennung
			, IFNULL(landesverbaende.identifier, landesverbaende_rueckwaerts.identifier) AS lv_kennung
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, country
			, @laufende_partien:= (SELECT IF(COUNT(partie_id) = 0, NULL, 1) FROM partien
				WHERE partien.event_id = events.event_id AND ISNULL(weiss_ergebnis)
			) AS zwischenstand
			, IF(ISNULL(@laufende_partien)
				AND tournaments.tabellenstand_runde_no = tournaments.runden, 1, NULL) AS endstand 
			, teams.team_status
		FROM teams
		LEFT JOIN contacts
			ON teams.club_contact_id = contacts.contact_id
		LEFT JOIN contacts_identifiers v_ok
			ON v_ok.contact_id = contacts.contact_id AND v_ok.current = "yes"
		LEFT JOIN contacts_identifiers lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier AND lv_ok.current = "yes"
		LEFT JOIN contacts landesverbaende
			ON lv_ok.contact_id = landesverbaende.contact_id
			AND landesverbaende.mother_contact_id = %d
		LEFT JOIN countries
			ON IFNULL(landesverbaende.country_id, contacts.country_id) 
				= countries.country_id
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = landesverbaende.contact_id
		LEFT JOIN contacts landesverbaende_rueckwaerts
			ON countries.country_id = landesverbaende_rueckwaerts.country_id
			AND landesverbaende_rueckwaerts.contact_category_id = %d
			AND landesverbaende_rueckwaerts.mother_contact_id = %d
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
		WHERE teams.identifier = "%s"
		AND spielfrei = "nein"
	';
	$sql = sprintf($sql
		, $zz_setting['contact_ids']['dsb']
		, wrap_category_id('contact/federation')
		, $zz_setting['contact_ids']['dsb']
		, $zz_setting['website_id']
		, wrap_db_escape(implode('/', $vars))
	);
	$team = wrap_db_fetch($sql);
	if (!$team) return false;
	$team[str_replace('-', '_', $team['turnierform'])] = true;
	$team += mf_contacts_contactdetails($team['contact_id']);

	array_pop($vars);
	$sql = 'SELECT event_id, event, bretter_min, bretter_max, alter_max, alter_min
			, geschlecht, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(teilnehmerliste = "ja", 1, 0) AS teilnehmerliste
			, pseudo_dwz
			, IFNULL(place, places.contact) AS turnierort
			, IFNULL(event_year, YEAR(date_begin)) AS year
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
	if ($event['parameters']) {
		parse_str($event['parameters'], $parameters);
		$event += $parameters;
	}

	$page['title'] = $event['event'].' '.$event['year'].': '.$team['team'].' '.$team['team_no'];
	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$event['event'].'</a>';
	$page['dont_show_h1'] = true;
	$page['extra']['realm'] = 'sports';
	$data = array_merge($team, $event);

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
	$data = array_merge($data, my_team_teilnehmer([$data['team_id'] => $data['contact_id']], $data));

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
