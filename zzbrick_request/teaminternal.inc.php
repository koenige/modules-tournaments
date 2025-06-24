<?php

/**
 * tournaments module
 * internal view of team of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_teaminternal($vars, $settings, $data) {
	$sql = 'SELECT datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, setzliste_no
			, platz_no
			, teams.identifier AS team_identifier
			, SUBSTRING_INDEX(teams.identifier, "/", -1) AS team_identifier_short
			, meldung_datum
			, @laufende_partien:= (SELECT IF(COUNT(partie_id) = 0, NULL, 1) FROM partien
				WHERE partien.event_id = teams.event_id AND ISNULL(weiss_ergebnis)
			) AS zwischenstand
			, IF(ISNULL(@laufende_partien)
				AND tournaments.tabellenstand_runde_no = tournaments.runden, 1, NULL) AS endstand 
			, teams.team_status
		FROM teams
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN tabellenstaende
			ON tabellenstaende.team_id = teams.team_id
			AND (ISNULL(tabellenstaende.runde_no)
				OR tabellenstaende.runde_no = tournaments.tabellenstand_runde_no)
		WHERE teams.team_id = %d
	';
	$sql = sprintf($sql, $data['team_id']);
	$data = array_merge($data, wrap_db_fetch($sql));
	if (empty($data['turnierform'])) wrap_quit(403, wrap_text('Please create a tournament first.'));
	$data[str_replace('-', '_', $data['turnierform'])] = true;
	$data += mf_contacts_contactdetails($data['contact_id']);
	$data = mf_tournaments_clubs_to_federations($data, 'contact_id');

	$sql = 'SELECT bretter_min, bretter_max, alter_max, alter_min
			, geschlecht
			, IF(gastspieler = "ja", 1, NULL) AS guest_players_allowed
			, IF(teilnehmerliste = "ja", 1, NULL) AS teilnehmerliste
			, pseudo_dwz
		FROM tournaments
		WHERE tournaments.event_id = %d';
	$sql = sprintf($sql, $data['event_id']);
	$data += wrap_db_fetch($sql);

	if ($data['parameters']) {
		parse_str($data['parameters'], $parameters);
		$data += $parameters;
	}
	$sql = 'SELECT eventtext_id, eventtext, categories.parameters
			, SUBSTRING_INDEX(categories.path, "/", -1) AS path
		FROM eventtexts
		LEFT JOIN categories
			ON eventtexts.eventtext_category_id = categories.category_id
		WHERE event_id = %d
		AND published = "yes"';
	$sql = sprintf($sql, $data['event_id']);
	$texts = wrap_db_fetch($sql, 'eventtext_id');
	foreach ($texts as $text) {
		if ($text['parameters']) {
			parse_str($text['parameters'], $text['parameters']);
			if (!empty($text['parameters']['alias'])) {
				$text['path'] = $text['parameters']['alias'];
				if ($pos = strpos($text['path'], '/'))
					$text['path'] = substr($text['path'], $pos + 1);
			}
		}
		$data[$text['path']] = $text['eventtext'];
	}
	
	$page['title'] = $data['event'].' '.$data['year'].': '.$data['team'].' '.$data['team_no'];
	$page['dont_show_h1'] = true;

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
		$line = [
			'team_id' => $data['team_id'],
			'meldung' => 'komplett',
			'meldung_datum' => date('Y-m-d H:i:s')
		];
		zzform_update('teams', $line, E_USER_ERROR);
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
	$data = array_merge($data, mf_tournaments_team_bookings($data['team_id'], $data));

	// Team + Vereinsbetreuer auslesen
	$data = array_merge($data, mf_tournaments_team_participants([$data['team_id'] => $data['contact_id']], $data));

	$data['komplett'] = mf_tournaments_team_registration_complete($data);
	if ($data['meldung'] === 'komplett') $data['pdfupload'] = true;

	$page['query_strings'][] = 'spaeter';
	$page['breadcrumbs'][]['title'] = $data['team'].' '.$data['team_no'];
	$page['text'] = wrap_template('team-internal', $data);
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
		$remarks = $data['cancellation'] ?? 'Wir nehmen nicht teil.';
		if (!empty($_POST['bemerkungen']))
			$remarks .= ' – '.$_POST['bemerkungen'];
		$line = [
			'anmerkung' => $remarks,
			'team_id' => $data['team_id'],
			'anmerkung_status' => 'offen',
			'benachrichtigung' => 'ja',
			'sichtbarkeit' => ['Team', 'Organisator']
		];
		zzform_insert('anmerkungen', $line);

		$line = [
			'team_id' => $data['team_id'],
			'team_status' => 'Löschung'
		];
		zzform_update('teams', $line);
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
		$remarks = $data['acceptance'] ?? 'Wir nehmen teil und akzeptieren die Bedingungen aus der Ausschreibung.';
		if (!empty($_POST['bemerkungen']))
			$remarks .= ' – '.$_POST['bemerkungen'];
		$line = [
			'anmerkung' => $remarks,
			'team_id' => $data['team_id'],
			'anmerkung_status' => !empty($_POST['bemerkungen']) ? 'offen' : 'erledigt',
			'benachrichtigung' => !empty($_POST['bemerkungen']) ? 'ja' : 'nein',
			'sichtbarkeit' => ['Team', 'Organisator']
		];
		zzform_insert('anmerkungen', $line);

		$line = [
			'team_id' => $data['team_id'],
			'team_status' => 'Teilnehmer'
		];
		zzform_update('teams', $line);
		return wrap_redirect_change();
	case 'spaeter':
/*
Bei späterer Meldung wird der Teilnahmestatus nicht geändert. Es wird
lediglich ein Logeintrag geschrieben, und zwar mit der Begründung aus
dem Freitextfeld. Dadurch kann zu einem späteren Zeitpunkt zu- oder
abgesagt werden oder auch zwischendurch eine Nachricht geschrieben
werden.
*/
		$remarks = $data['delay'] ?? 'Wir bitten um Verlängerung der Entscheidungsfrist.';
		if (!empty($_POST['bemerkungen']))
			$remarks .= ' – '.$_POST['bemerkungen'];
		$line = [
			'anmerkung' => $remarks,
			'team_id' => $data['team_id'],
			'anmerkung_status' => 'offen',
			'benachrichtigung' => 'ja',
			'sichtbarkeit' => ['Team', 'Organisator']
		];
		zzform_insert('anmerkungen', $line);
		return wrap_redirect_change('?spaeter');
	}
	return false;
}
