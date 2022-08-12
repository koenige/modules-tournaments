<?php

/**
 * tournaments module
 * Ausgabe aller Meldeformulare vom Anreisetag zu einer Meisterschaft als PDF
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2013-2014, 2017-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Meldebögen zur Anreise, Check welche gemeldete Personen vor Ort sind,
 * Abfrage der Brettreihenfolge
 *
 * @param array $vars
 * @return void
 */
function mod_tournaments_teampdfsarrival($vars) {
	global $zz_setting;

	$event_rights = 'event:'.$vars[0].'/'.$vars[1];
	if (!brick_access_rights(['Webmaster', 'Vorstand', 'AK Spielbetrieb', 'Geschäftsstelle'])
		AND !brick_access_rights(['Schiedsrichter', 'Organisator', 'Turnierleitung'], $event_rights)
	) {
		wrap_quit(403);
	}

	if ($vars[count($vars)-1] === 'meldeboegen.pdf') {
		// brauchen wir nicht
		array_pop($vars);
	}
	if (count($vars) === 3) {
		$team = implode('/', $vars);
		array_pop($vars);
		$event = implode('/', $vars);
	} elseif (count($vars) === 2) {
		$team = false;
		$event = implode('/', $vars);
	} else {
		return false;
	}

	$sql = 'SELECT event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, date_begin
			, ratings_updated
			, events.identifier AS event_identifier
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS dateiname
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, bretter_min, bretter_max
			, pseudo_dwz
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($event));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	$event = array_merge($event, my_event_accounts($event['event_id']));

	// teams
	$sql = 'SELECT team_id, team, team_no, club_contact_id
			, teams.identifier AS team_identifier
			, meldung_datum
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, IF(datum_anreise AND uhrzeit_anreise AND datum_abreise AND uhrzeit_abreise, 1, NULL) AS reisedaten_komplett
			, meldung
		FROM teams
		WHERE event_id = %d
		AND spielfrei = "nein"
		AND team_status = "Teilnehmer"
		ORDER BY teams.identifier
	';
	$sql = sprintf($sql
		, $event['event_id']
	);
	if ($team) {
		$sql .= sprintf(' AND teams.identifier = "%s"', wrap_db_escape($team)); 
	}
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	$event['teams'] = mf_tournaments_clubs_to_federations($event['teams']);

	$team_verein = [];
	foreach ($event['teams'] as $id => $team) {
		$team_verein[$id] = $team['club_contact_id'];
	}

	$teilnehmer = mf_tournaments_team_participants($team_verein, $event, true, 't_dwz DESC, last_name, first_name');

	foreach (array_keys($event['teams']) as $id) {
		if (!empty($teilnehmer[$id])) {
			$event['teams'][$id] = array_merge($event['teams'][$id], $teilnehmer[$id]);
		} else {
			$event['teams'][$id]['spieler'] = [];
		}
	}

	return my_team_pdf_meldung($event);
}

/**
 * Ausgabe der Meldung zum Anreisetag als PDF
 *
 * @param array $daten
 * # event, duration, teams {team, team_no, country, regionalgruppe,
 * # komplett, spieler {rang_no, person, geschlecht, t_dwz, geburtsjahr},
 * # betreuer {usergroup, person_id, e_mail, telefon, person, geburtsjahr},
 * # verein-vorsitz { … }, verein-jugend { … }, team-organisator { … }, gast { …
 * # }, datum_anreise, uhrzeit_anreise, datum_abreise, uhrzeit_abreise, kosten
 * # {buchungskategorie, betrag, usergroup, kosten, anmerkungen, kosten_betrag,
 * # betrag_waehrung, anzahl_tage, anzahl_weiblich, anzahl_maennlich, betrag},
 * # betrag, meldung_datum, team_identifier }, hinweis_meldebogen, event_identifier,
 * # dateiname, konten_veranstalter {inhaber, iban, bic, institut},
 * # konten_ausrichter {inhaber, iban, bic, institut}, bretter_min,
 * # gastspieler_status, dauer_tage
 * @param string $return 'send' => send PDF to browser, 'filename' => return filename
 * @return void
 */
function my_team_pdf_meldung($event, $return = 'send') {
	global $zz_setting;

	switch ($event['turnierform']) {
	case 'm-a':
		$vorsitz = 'Vorsitzende/r Verband';
		$in_org = 'im Verband';
		$zeige_verband = false;
		break;
	case 'm-v':
		$vorsitz = 'Vereinsvorsitzende/r';
		$in_org = 'im Verein';
		$zeige_verband = true;
		break;
	case 'm-s':
		$vorsitz = 'Rektor/in der Schule';
		$in_org = 'in der Schule';
		$zeige_verband = true;
		break;
	}
	
	$margin_top_bottom = 45;
	$page_height = 842;
	
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	$pdf = new TFPDF('P', 'pt', 'A4');

	//$pdf->setCompression(true);           // Activate compression.

	$pdf->setMargins(45, $margin_top_bottom);

	$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
	$pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
	$pdf->AddFont('DejaVu', 'I', 'DejaVuSansCondensed-Oblique.ttf', true);
	
	$pdf->SetLineWidth(0.15);
	$pdf->SetFillColor(230, 230, 230);

	$doc['boxes'] = ['Erfasst', '200 DWZ geprüft', 'DWZ-Schnitt'];
	$doc['boxes_width'] = 80;
	$doc['info'] =  "• Jedem anwesenden Jugendlichen ist eine Nummer zuzuweisen, "
			. "die seiner Position in der Mannschaft entspricht. "
			. "Für nicht anwesende Jugendliche ist das Feld „Nr.“ freizulassen.\n"
			. "• Es dürfen nur Jugendliche gemeldet werden, die persönlich "
			. "vor Ort sind – Zuwiderhandlungen können mit Turnierausschluss bestraft "
			. "werden. Der Turnierverantwortliche kann Ausnahmen zulassen. Diese genehmigten Ausnahmen werden in der ersten Betreuersitzung
durch die Turnierleitung bekanntgegeben.\n"
			. "• Kein Jugendlicher darf in zwei Mannschaften gemeldet werden.\n"
			. "• Ein/e Ersatzspieler/in ist zulässig.\n"
			. "• Es darf kein Jugendlicher vor einem anderen aufgestellt werden, "
			. "der eine um mehr als 200 Punkte bessere DWZ besitzt, außer beide "
			. "Jugendliche haben unter 1000 DWZ. Über Ausnahmen entscheidet der "
			. "Turnierverantwortliche. Es gilt die angegebene DWZ vom %s. "
			. "Die Pseudo-DWZ für Jugendliche ohne DWZ beträgt %d.";
	
	foreach ($event['teams'] AS $team) {
		$pdf->AddPage();

		$logo['filename'] = $zz_setting['media_folder'].'/logos/DSJ Logo Text schwarz-gelb.png';
		$logo['width'] = 146;
		$logo['height'] = 50;

		$pdf->Image($logo['filename'], 595 - $margin_top_bottom - $logo['width'], $margin_top_bottom, $logo['width'], $logo['height'], 'PNG');
		$pdf->setFont('DejaVu', '', 14);
		$pdf->write(19, 'Meldebogen zur Anreise');
		$pdf->Ln();
		$pdf->setFont('DejaVu', 'B', 14);
		$pdf->write(19, $event['event']);
		$pdf->setFont('DejaVu', '', 14);
		$pdf->write(19, ', '.html_entity_decode(wrap_date($event['duration']), ENT_QUOTES, 'UTF-8'));
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Ln();
		$pdf->write(19, $team['team'].' '.$team['team_no']);
		$pdf->setFont('DejaVu', '', 10);
		if ($zeige_verband) {
			$pdf->write(19, ' ('
				.$team['country']
				.($team['regionalgruppe'] ? ', Regionalgruppe '.$team['regionalgruppe'] : '').')');
		}
		$pdf->Ln();
		$pdf->Ln();

		$y_pos = $pdf->getY();
		$pdf->setFont('DejaVu', 'B', 10);
		$pdf->write(18, '1. Betreuungsperson vor Ort');
		$pdf->Ln();
		$pdf->setFont('DejaVu', 'I', 10);
		$pdf->setY($pdf->getY()+30);
		$pdf->MultiCell(0, 14, 'Vorname, Name, Handynummer', 'T', 'L');

		$pdf->Ln();
		$pdf->Ln();
		$pdf->setFont('DejaVu', 'B', 10);
		$pdf->write(18, '2. Verbindliche Abgabe der Mannschaftsaufstellung');
		$pdf->Ln();
		$y_pos = $pdf->getY();
		$x_pos = $pdf->getX();
		$pdf->setFont('DejaVu', 'I', 10);
		$pdf->Cell(30, 14, 'Nr.', '', 0, 'R');
		$pdf->Cell(105, 14, 'Name', '', 0, 'L', 1);
		$pdf->Cell(35, 14, 'W/M', '', 0, 'R');
		$pdf->Cell(35, 14, 'DWZ', '', 0, 'R', 1);
		$pdf->Cell(35, 14, 'Geburt', '', 0, 'R');
		$pdf->Ln();
		$pdf->setFont('DejaVu', '', 10);
		foreach ($team['spieler'] as $line) {
			if (empty($line['geschlecht'])) $line['geschlecht'] = '';
			if (empty($line['t_dwz'])) $line['t_dwz'] = '';
			if (empty($line['geburtsjahr'])) $line['geburtsjahr'] = '';
			$pdf->Cell(30, 14, '', 'T', 0, 'R');
			$x_pos_name = $pdf->getX();
			$y_pos_name = $pdf->getY();
			$pdf->MultiCell(105, 14, $line['person'], 'T', 'L', 1);
			$y_bottom = $pdf->getY();
			$pdf->setY($y_pos_name);
			$pdf->setX($x_pos_name + 105);
			$pdf->Cell(35, 14, $line['geschlecht'], 'T', 0, 'R');
			$pdf->Cell(35, 14, $line['t_dwz'], 'T', 0, 'R', 1);
			my_team_pdf_add_bg_color($pdf, $y_bottom, 35, 14);
			$pdf->Cell(35, 14, $line['geburtsjahr'], 'T', 0, 'R');
			$pdf->Ln();
			$pdf->setY($y_bottom);
		}
		$pdf->Ln();
		$bottom_y = $pdf->getY();
		
		$pdf->setY($y_pos);
		$pdf->setX($x_pos+265);
		$pdf->setFont('DejaVu', '', 9);
		$pdf->MultiCell(0, 14, sprintf($doc['info'],
			wrap_date($event['ratings_updated']),
			$event['pseudo_dwz']
		));
		if ($pdf->getY() > $bottom_y) $bottom_y = $pdf->getY();

		$pdf->setY($bottom_y);

		$pdf->Ln();
		$pdf->MultiCell(0, 14, 'Unsere Angaben sind vollständig und korrekt. Die '
			.'Ausschreibung zu dem Turnier ist uns bekannt und wird von uns inhaltlich '
			.'akzeptiert.');
		$pdf->setFont('DejaVu', '', 8);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Cell(265, 14, wrap_date($event['date_begin']));
		$pdf->Ln();
		$y_pos = $pdf->GetY();
		$x_pos = $pdf->GetX();
		$pdf->MultiCell(240, 10, 'Datum, Unterschrift der Betreuungsperson', 'T', 'L');

		$pdf->setY(740);
		$pdf->write(18, 'Prüfung durch die Turnierleitung – ' . $team['team'].' '.$team['team_no']);
		$pdf->Ln();
	
		$pos_x = $pdf->getX();
		$pos_y = $pdf->getY();
		foreach ($doc['boxes'] as $index => $box) {
			$pdf->Rect($pos_x + $index * $doc['boxes_width'], $pos_y, $doc['boxes_width'], 50, 'DF');
			$pdf->setY($pos_y);
			$pdf->setX($pos_x + $index * $doc['boxes_width']);
			$pdf->Multicell($doc['boxes_width'], 12, $box, 0, 'C');
		}
	}

	$folder = $zz_setting['tmp_dir'].'/team-meldungen';
	if (count($event['teams']) === 1) {
		$turnier_folder = dirname($folder.'/'.$team['team_identifier']);
		$file['name'] = $folder.'/'.$team['team_identifier'].'-meldebogen.pdf';
		$file['send_as'] = 'Meldebogen '.$event['event'].' '.$team['team'].'.pdf';
	} else {
		$turnier_folder = dirname($folder.'/'.$event['event_identifier']);
		$file['name'] = $folder.'/'.$event['dateiname'].'-meldebogen.pdf';
		$file['send_as'] = 'Meldebögen '.$event['event'].'.pdf';
	}
	wrap_mkdir($turnier_folder);
	
	$file['caching'] = false;
	$file['etag_generate_md5'] = true;
	$pdf->output('F', $file['name'], true);
	if ($return === 'filename') return $file['name'];
	wrap_file_send($file);
}
