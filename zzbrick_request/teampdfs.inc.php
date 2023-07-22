<?php

/**
 * tournaments module
 * Ausgabe aller Meldeformulare zu einer Meisterschaft als PDF
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2013-2014, 2017-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */



function mod_tournaments_teampdfs($params, $settings, $event) {
	if (!$event) return false;
	if (count($params) < 2) return false;
	if (count($params) > 3) return false;
	wrap_include_files('pdf', 'tournaments');
	
	// $data can be from team or event placeholder
	if (empty($event['event_identifier']))
		$event['event_identifier'] = $event['identifier'];
	
	$sql = 'SELECT bretter_min, bretter_max
			, pseudo_dwz, ratings_updated
			, IF(zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, (SELECT eventtext FROM eventtexts
				WHERE eventtexts.event_id = tournaments.event_id
				AND eventtexts.eventtext_category_id = %d
			) AS hinweis_meldebogen
	    FROM tournaments
	    WHERE event_id = %d';
	$sql = sprintf($sql
		, wrap_category_id('event-texts/note-registration-form')
		, $event['event_id']
	);
	$event += wrap_db_fetch($sql);
	$event += mf_tournaments_pdf_event_accounts($event['event_id']);
	
	$params = [
		'team_identifier' => count($params) === 3 ? implode('/', $params) : false,
		'check_uploads' => !empty($settings['no_uploads']) ? false : true
	];
	$event['teams'] = mf_tournaments_pdf_teams($event, $params);

	if (empty($event['teams']['pdf_uploads'])) return mod_tournaments_teampdfs_pdf($event);

	unset($event['teams']['pdf_uploads']);
	$pdfs = [];
	foreach ($event['teams'] as $id => $team) {
		if (!empty($team['pdf'])) {
			$pdfs = array_merge($pdfs, $team['pdf']);
			continue;
		}
		$my_event = $event;
		$my_event['teams'] = [$id => $team];
		$pdfs[] = mod_tournaments_teampdfs_pdf($my_event, 'filename');
	}
	$folder = wrap_setting('tmp_dir').'/team-meldungen';
	$turnier_folder = dirname($folder.'/'.$event['event_identifier']);
	$file['name'] = $folder.'/'.$event['event_idf'].'-meldebogen.pdf';
	$file['send_as'] = 'Meldebögen '.$event['event'].'.pdf';
	$command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile="%s" "%s"';
	$command = sprintf($command, $file['name'], implode('" "', $pdfs));
	exec($command);
	if (!file_exists($file['name'])) {
		wrap_error(sprintf('PDF für %s konnte nicht erstellt werden.', $event['event']), E_USER_ERROR);
	}
	wrap_file_send($file);
}

/**
 * Ausgabe der Meldung als PDF
 *
 * @param array $daten
 * @param string $return 'send' => send PDF to browser, 'filename' => return filename
 * @return void
 */
function mod_tournaments_teampdfs_pdf($event, $return = 'send') {
	list($pdf, $settings) = mf_tournaments_pdf_prepare($event);

	$margin_top_bottom = 45;
	
	$pdf->setMargins(45, $margin_top_bottom);
	
	foreach ($event['teams'] AS $team) {
		$pdf->AddPage();
		mod_tournaments_teampdfs_draft($pdf, $team);

		$pdf->Image($settings['logo_filename'], 595 - $margin_top_bottom - $settings['logo_width'], $margin_top_bottom, $settings['logo_width'], $settings['logo_height'], 'PNG');
		$pdf->setFont('DejaVu', '', 14);
		$pdf->write(19, 'Meldebogen');
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
		if ($settings['show_federation']) {
			$pdf->write(19, ' ('
				.$team['country']
				.($team['regionalgruppe'] ? ', Regionalgruppe '.$team['regionalgruppe'] : '').')');
		}
		$pdf->Ln();
		$pdf->Ln();

		$y_pos = $pdf->getY();
		$pdf->setFont('DejaVu', 'B', 10);
		$pdf->write(18, '1. Aufstellung');
		$pdf->Ln();
		$pdf->setFont('DejaVu', 'I', 10);
		$pdf->Cell(30, 14, 'Rang', '', 0, 'R');
		$pdf->Cell(105, 14, 'Name', '', 0, 'L', 1);
		$pdf->Cell(35, 14, 'W/M', '', 0, 'R');
		$pdf->Cell(35, 14, 'DWZ', '', 0, 'R', 1);
		$pdf->Cell(35, 14, 'Geburt', '', 0, 'R');
		$pdf->Ln();
		$pdf->setFont('DejaVu', '', 10);
		foreach ($team['spieler'] as $line) {
			if (empty($line['rang_no'])) $line['rang_no'] = '';
			if (empty($line['geschlecht'])) $line['geschlecht'] = '';
			if (empty($line['t_dwz'])) $line['t_dwz'] = '';
			if (empty($line['geburtsjahr'])) $line['geburtsjahr'] = '';
			$pdf->Cell(30, 14, $line['rang_no'], 'T', 0, 'R');
			$x_pos_name = $pdf->getX();
			$y_pos_name = $pdf->getY();
			$pdf->MultiCell(105, 14, $line['person'], 'T', 'L', 1);
			$y_bottom = $pdf->getY();
			$pdf->setY($y_pos_name);
			$pdf->setX($x_pos_name + 105);
			$pdf->Cell(35, 14, $line['geschlecht'], 'T', 0, 'R');
			$pdf->Cell(35, 14, $line['t_dwz'], 'T', 0, 'R', 1);
			mf_tournaments_pdf_add_bg_color($pdf, $y_bottom, 35, 14);
			$pdf->Cell(35, 14, $line['geburtsjahr'], 'T', 0, 'R');
			$pdf->Ln();
			$pdf->setY($y_bottom);
		}
		$pdf->Ln();
		$bottom_y = $pdf->getY();
		$pdf->setY($y_pos);
		$pdf->setX(310);

		$pdf->setFont('DejaVu', 'B', 10);
		$pdf->write(18, '2. Kontaktdaten');
		$pdf->setFont('DejaVu', 'I', 10);
		$pdf->Ln();
		$pdf->setX(310);
		$usergroups = [
			'betreuer', 'verein-vorsitz', 'verein-jugend', 'team-organisator', 'gast'
		];
		$pdf->Cell(30, 14, '', '', 0, 'R');
		$pdf->Cell(175, 14, 'Name / E-Mail / Telefon', '', 0, 'L', 1);
		$pdf->Cell(35, 14, 'Geburt', '', 0, 'R');
		$pdf->Ln();
		$pdf->setX(310);
		$pdf->setFont('DejaVu', '', 10);
		$i = 0;
		$y_diff = 0;
		$person_ids = [];
		foreach ($usergroups as $usergroup) {
			if (!array_key_exists($usergroup, $team)) continue;
			$gruppentitel = reset($team[$usergroup]);
			if (empty($gruppentitel['usergroup'])) continue;
			$pdf->Cell(30, 14, '', 'T');
			$pdf->setFont('DejaVu', 'B', 10);
			$pdf->Cell(175, 14, $gruppentitel['usergroup'], 'T', 0, 'L', 1);
			$pdf->setFont('DejaVu', '', 10);
			$pdf->Cell(35, 14, '', 'T');
			$pdf->Ln();
			$pdf->SetX(310);
			foreach ($team[$usergroup] as $line) {
				$i++;
				$pdf->Cell(30, 14, $i, 'T', 0, 'R');
				$y_pos = $pdf->GetY();
				$x_pos = $pdf->GetX();
				if (!in_array($line['person_id'], $person_ids)) {
					// Anschriften müssen nicht doppelt auf Liste auftauchen
					$adressdaten = ($line['e_mail'] ? 'E-Mail: '.$line['e_mail']."\n" : '')
						.($line['telefon'] ? str_replace('<br>', "\n", $line['telefon']) : '');
				} else {
					$adressdaten = '';
				}
				$pdf->MultiCell(175, 14, $line['person']."\n".$adressdaten, 'T', 'L', 1);
				$y_bottom = $pdf->GetY();
				$y_diff = $y_bottom - $y_pos;
				$pdf->SetY($y_pos);
				$pdf->SetX($x_pos + 175);
				$pdf->Cell(35, 14, $line['geburtsjahr'], 'T', 0, 'R');
				$pdf->SetY($y_bottom);
				$pdf->SetX(310);
				$person_ids[] = $line['person_id'];
			}
		}
		$pdf->Ln(14);
		$bottom_y_2 = $pdf->getY();
		if ($bottom_y > $bottom_y_2) $pdf->setY($bottom_y);
		else $pdf->setY($bottom_y_2);

		$pdf->setFont('DejaVu', 'B', 10);
		$pdf->write(18, '3. An- und Abreise');
		$pdf->setFont('DejaVu', '', 10);
		$pdf->Ln();
		$pdf->write(14, 'Anreise am '.wrap_date($team['datum_anreise']).' um '.$team['uhrzeit_anreise'].' Uhr');
		$pdf->Ln();
		$pdf->write(14, 'Abreise am '.wrap_date($team['datum_abreise']).' um '.$team['uhrzeit_abreise'].' Uhr');
		$pdf->Ln();
		$pdf->Ln();

		if ($event['zimmerbuchung']) {
			$pdf->setFont('DejaVu', 'B', 10);
			$pdf->write(18, '4. Zimmerbuchung');
			$pdf->setFont('DejaVu', 'I', 10);
			$pdf->Ln();
			$pdf->Cell(75, 14, 'Gruppe', '', 0, 'L');
			$pdf->Cell(201, 14, 'Buchung', '', 0, 'L', 1);	
			$pdf->Cell(79, 14, 'Kosten', '', 0, 'R');
			$pdf->Cell(30, 14, 'Tage', '', 0, 'R', 1);
			$pdf->Cell(20, 14, 'W', '', 0, 'R');
			$pdf->Cell(20, 14, 'M', '', 0, 'R', 1);
			$pdf->Cell(80, 14, 'Summe', '', 0, 'R');
			$pdf->Ln();
			$pdf->setFont('DejaVu', '', 10);
			if (!empty($team['kosten'])) foreach ($team['kosten'] as $line) {
				if (in_array($line['buchungskategorie'], ['buchungen/zahlung-startgeld-unterkunft', 'buchungen/zahlung-reuegeld'])) {
					// Zahlungen wieder herausnehmen und Betrag von Summe abziehen!
					$team['betrag'] -= $line['betrag'];
					continue;
				}
				if ($line['anmerkungen']) {
					$line['anmerkungen'] = str_replace("\n", " ", $line['anmerkungen']);
					$rows = ceil($pdf->GetStringWidth($line['anmerkungen']) / 201);
					$y_pos = $pdf->GetY();
					if ($y_pos + (12 * $rows) >= $settings['page_height'] - $margin_top_bottom) {
						$pdf->AddPage();
					} 			
				}
				$pdf->Cell(75, 14, $line['gruppe'], 'T', 0, 'L');
				$y_pos = $pdf->GetY();
				$x_pos = $pdf->GetX();
				$pdf->MultiCell(201, 14, $line['kosten'], 'T', 'L', 1);
				if ($line['anmerkungen']) {
					$pdf->setX($x_pos);
					$pdf->setFont('DejaVu', '', 8);
					$pdf->MultiCell(201, 10, $line['anmerkungen'], '', 'L', 1);
					$pdf->setFont('DejaVu', '', 10);
				}
				$y_bottom = $pdf->GetY();
				$pdf->SetY($y_pos);
				$pdf->SetX($x_pos + 201);
				$pdf->Cell(79, 14, wrap_money($line['kosten_betrag']).' '.$line['betrag_waehrung'], 'T', 0, 'R');
				$pdf->Cell(30, 14, $line['anzahl_tage'], 'T', 0, 'R', 1);
				mf_tournaments_pdf_add_bg_color($pdf, $y_bottom, 30, 14);
				$pdf->Cell(20, 14, $line['anzahl_weiblich'], 'T', 0, 'R');
				$pdf->Cell(20, 14, $line['anzahl_maennlich'], 'T', 0, 'R', 1);
				mf_tournaments_pdf_add_bg_color($pdf, $y_bottom, 20, 14);
				$pdf->Cell(80, 14, wrap_money($line['betrag']).' '.$line['betrag_waehrung'], 'T', 0, 'R');
				$pdf->SetY($y_bottom - 14);
				$pdf->Ln();
			}
			if (!isset($line['kosten_waehrung'])) $line['kosten_waehrung'] = '';
			$pdf->Cell(75, 14, '', 'T', 0, 'L');
			$pdf->Cell(201, 14, 'Gesamtsumme', 'T', 0, 'L', 1);	
			$pdf->Cell(79, 14, '', 'T', 0, 'R');
			$pdf->Cell(30, 14, '', 'T', 0, 'R', 1);
			$pdf->Cell(20, 14, '', 'T', 0, 'R');
			$pdf->Cell(20, 14, '', 'T', 0, 'R', 1);
			if (isset($team['betrag'])) {
				$pdf->Cell(80, 14, wrap_money($team['betrag']).' '.$line['betrag_waehrung'], 'T', 0, 'R');
			} else {
				$pdf->Cell(80, 14, '', 'T', 0, 'R');
			}
			$pdf->Ln();
		}
		if ($pdf->getY() > 650) {
			$pdf->AddPage();
			mod_tournaments_teampdfs_draft($pdf, $team);
			$pdf->setFont('DejaVu', '', 8);
			$pdf->Cell(0, 14, 'Seite 2 zum Meldebogen '.$event['event'].', '.$team['team'].' '.$team['team_no']);
			$pdf->setFont('DejaVu', '', 10);
			$pdf->Ln();
			$pdf->Ln();
		}
		$pdf->Ln();
		$pdf->MultiCell(0, 14, 'Unsere Angaben sind vollständig und korrekt. Die '
			.'Ausschreibung zu dem Turnier ist uns bekannt und wird von uns inhaltlich '
			.'akzeptiert.');
		$pdf->setFont('DejaVu', '', 8);
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Ln();
		$pdf->Cell(265, 14, wrap_date($team['meldung_datum']));
		$pdf->Cell(265, 14, wrap_date($team['meldung_datum']));
		$pdf->Ln();
		$y_pos = $pdf->GetY();
		$x_pos = $pdf->GetX();
		$pdf->MultiCell(240, 10, sprintf("Datum, Unterschrift\n%s", $settings['text_chair']), 'T', 'L');
		$pdf->SetY($y_pos);
		$pdf->SetX($x_pos+265);
		$pdf->MultiCell(240, 10, sprintf("Datum, Unterschrift\nVerantwortliche/r für das Turnier %s", $settings['text_in_org']), 'T', 'L');

		if ($event['hinweis_meldebogen']) {
			$pdf->Ln();
			$hinweis_meldebogen = brick_format($event['hinweis_meldebogen'], $event);
			$hinweis_meldebogen = $hinweis_meldebogen['text'];
			$hinweis_meldebogen = strip_tags($hinweis_meldebogen);
			$hinweis_meldebogen = html_entity_decode($hinweis_meldebogen, ENT_QUOTES, 'UTF-8');
			$hinweis_meldebogen = trim($hinweis_meldebogen); // remove line breaks
			$pdf->MultiCell(0, 10, $hinweis_meldebogen);
		}
	}

	$event['filename_suffix'] = 'meldebogen';
	$event['send_as_singular'] = 'Meldebogen';
	$event['send_as_plural'] = 'Meldebögen';
	return mf_tournaments_pdf_send($pdf, $event, $return);
}

/**
 * Vorläufige Meldung mit Wasserzeichen
 *
 * @param object $pdf
 * @param array $daten
 *		bool 'komplett'
 * @return void
 */
function mod_tournaments_teampdfs_draft(&$pdf, $daten) {
	if (!empty($daten['komplett'])) return;
	$current_x = $pdf->GetX();
	$current_y = $pdf->GetY();
	$pdf->SetTextColor(200, 200, 200);
	$pdf->setFont('DejaVu', 'B', 60);
	$pdf->MultiCell(0, 700, "Vorschau", '', 'C', 0);
	$pdf->setFont('DejaVu', '', 10);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->SetXY($current_x, $current_y);
}

/**
 * Liest Konten zu Termin aus
 *
 * @param int $event_id
 * @return array
 *		array 'konten_veranstalter',
 *		array 'konten_ausrichter'
 * @todo in Termin-Funktionsskript verschieben
 */
function mf_tournaments_pdf_event_accounts($event_id) {
	$sql = 'SELECT account_id, kontotyp
			, IFNULL(inhaber, contact) AS inhaber, iban, bic, institut
		FROM events_accounts
		LEFT JOIN accounts USING (account_id)
		LEFT JOIN contacts
			ON contacts.contact_id = accounts.owner_contact_id
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$konten = wrap_db_fetch($sql, 'account_id');
	$event = [];
	if (!$konten) return $event;
	foreach ($konten as $id => $konto) {
		$event['konten_'.strtolower($konto['kontotyp'])][$id] = $konto;
	}
	return $event;
}

/**
 * read teams per event for PDF
 *
 * @param array $event
 * @param array $params
 *		int event_id
 * 		string team_identifier
 * @return array
 */
function mf_tournaments_pdf_teams($event, $params) {
	// team_identifier is more specific
	if (!empty($params['team_identifier']))
		$where = sprintf('teams.identifier = "%s"', wrap_db_escape($params['team_identifier']));
	else
		$where = sprintf('event_id = %d', $event['event_id']);

	$sql = 'SELECT team_id, team, team_no, club_contact_id
			, teams.identifier AS team_identifier
			, meldung_datum
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, IF(datum_anreise AND uhrzeit_anreise AND datum_abreise AND uhrzeit_abreise, 1, NULL) AS reisedaten_komplett
			, meldung
		FROM teams
		WHERE %s
		AND spielfrei = "nein"
		AND team_status = "Teilnehmer"
		ORDER BY teams.identifier
	';
	$sql = sprintf($sql, $where);
	$teams = wrap_db_fetch($sql, 'team_id');
	if (!$teams) return [];

	$teams = mf_tournaments_clubs_to_federations($teams);

	// get participants
	$team_contact_ids = [];
	foreach ($teams as $team_id => $team)
		$team_contact_ids[$team_id] = $team['club_contact_id'];
	$participants = mf_tournaments_team_participants($team_contact_ids, $event);
	// single team?
	if (!is_numeric(key($participants))) $participants = [$team_id => $participants];

	// get bookings
	$bookings = mf_tournaments_team_bookings(array_keys($teams), $event);

	// move separate data to teams array
	$pdf_uploads = false;
	foreach (array_keys($teams) as $team_id) {
		if (!empty($participants[$team_id])) {
			$teams[$team_id] = array_merge($teams[$team_id], $participants[$team_id]);
		} else {
			$teams[$team_id]['spieler'] = [];
		}
		if (!empty($bookings[$team_id])) {
			$teams[$team_id] = array_merge($teams[$team_id], $bookings[$team_id]);
		} else {
			$teams[$team_id]['kosten'] = [];
		}
		$teams[$team_id]['komplett'] = mf_tournaments_team_application_complete($teams[$team_id]);
		if (!empty($params['check_uploads'])) {
			$filename = sprintf('%s/meldeboegen/%s%%s.pdf', wrap_setting('media_folder'), $teams[$team_id]['team_identifier']);
			$filenames = [
				sprintf($filename, ''),
				sprintf($filename, '-ehrenkodex'),
				sprintf($filename, '-gast')
			];
			foreach ($filenames as $filename) {
				if (!file_exists($filename)) continue;
				$teams[$team_id]['pdf'][] = $filename;
				$pdf_uploads = true;
			}
		}
	}
	if ($pdf_uploads) $teams['pdf_uploads'] = true;

	return $teams;
}
