<?php 

/**
 * tournaments module
 * common functions for PDFs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get event for PDF
 *
 * fields just used by some functions:
 * - teampdfs: hinweis_meldebogen, dateiname, pseudo_dwz
 * - teampdf: hinweis_meldebogen
 * - teampdfsarrival: date_begin, ratings_updated, dateiname, pseudo_dwz
 * @param array $event_params
 * @return array
 */
function mf_tournaments_pdf_event($event_params) {
	$sql = 'SELECT event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, events.identifier AS event_identifier
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, bretter_min, bretter_max
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS dateiname
			, pseudo_dwz
			, date_begin
			, ratings_updated
			, hinweis_meldebogen
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $event_params[0], wrap_db_escape($event_params[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	$event = array_merge($event, mf_tournaments_pdf_event_accounts($event['event_id']));
	return $event;
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
	global $zz_setting;

	// team_identifier is more specific
	if (!empty($params['team_identifier'])) {
		$where = sprintf('teams.identifier = "%s"', wrap_db_escape($params['team_identifier']));
	} else {
		$event_rights = 'event_id:'.$event['event_id'];
		if (!brick_access_rights(['Webmaster', 'Vorstand', 'AK Spielbetrieb', 'Geschäftsstelle'])
			AND !brick_access_rights(['Schiedsrichter', 'Organisator', 'Turnierleitung'], $event_rights)
		) {
			wrap_quit(403);
		}
		$where = sprintf('event_id = %d', $event['event_id']);
	}

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
	if (!empty($params['team_identifier'])) {
		$team_id = key($teams);
		if (!mf_tournaments_team_access($team_id, ['Teilnehmer'])) wrap_quit(403);
	}

	$teams = mf_tournaments_clubs_to_federations($teams);

	// get participants
	$team_contact_ids = [];
	foreach ($teams as $team_id => $team) {
		$team_contact_ids[$team_id] = $team['club_contact_id'];
	}
	if (!empty($params['participants_order_by']))
		$participants = mf_tournaments_team_participants($team_contact_ids, $event, true, $params['participants_order_by']);
	else
		$participants = mf_tournaments_team_participants($team_contact_ids, $event);
	if (!is_numeric(key($participants))) $participants = [$team_id => $participants];

	// get bookings
	if (!empty($params['bookings']))
		$bookings = mf_tournaments_team_bookings(array_keys($teams), $event);
	else
		$bookings = [];

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
		if (!empty($params['check_completion']))
			$teams[$team_id]['komplett'] = mf_tournaments_team_application_complete($teams[$team_id]);
		if (!empty($params['check_uploads'])) {
			$filename = sprintf('%s/meldeboegen/%s%%s.pdf', $zz_setting['media_folder'], $teams[$team_id]['team_identifier']);
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

/**
 * prepare PDF and some settings
 *
 * @param object $pdf
 * @param array $event
 * @return void
 */
function mf_tournaments_pdf_prepare($event) {
	global $zz_setting;
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	$settings = [];
	switch ($event['turnierform']) {
	case 'm-a':
		$settings['text_chair'] = 'Vorsitzende/r Verband';
		$settings['text_in_org'] = 'im Verband';
		$settings['show_federation'] = false;
		break;
	case 'm-v':
		$settings['text_chair'] = 'Vereinsvorsitzende/r';
		$settings['text_in_org'] = 'im Verein';
		$settings['show_federation'] = true;
		break;
	case 'm-s':
		$settings['text_chair'] = 'Rektor/in der Schule';
		$settings['text_in_org'] = 'in der Schule';
		$settings['show_federation'] = true;
		break;
	}

	$settings['logo_filename'] = $zz_setting['media_folder'].'/logos/DSJ Logo Text schwarz-gelb.png';
	$settings['logo_width'] = 146;
	$settings['logo_height'] = 50;

	$pdf = new TFPDF('P', 'pt', 'A4');
	//$pdf->setCompression(true);           // Activate compression.

	$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
	$pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
	$pdf->AddFont('DejaVu', 'I', 'DejaVuSansCondensed-Oblique.ttf', true);
	
	$pdf->SetLineWidth(0.15);
	$pdf->SetFillColor(230, 230, 230);

	$settings['page_height'] = $pdf->GetPageHeight();

	return [$pdf, $settings];
}

/**
 * write PDF to filesystem and send it to user
 *
 * @param object $pdf
 * @param array $event
 * @return void
 */
function mf_tournaments_pdf_send($pdf, $event) {
	global $zz_setting;

	$folder = $zz_setting['tmp_dir'].'/team-meldungen';
	if (count($event['teams']) === 1) {
		$team = reset($event['teams']);
		$tournament_folder = dirname($folder.'/'.$team['team_identifier']);
		$file['name'] = $folder.'/'.$team['team_identifier'].'-'.$event['filename_suffix'].'.pdf';
		$file['send_as'] = $event['send_as_singular'].' '.$event['event'].' '.$team['team'].'.pdf';
	} else {
		$tournament_folder = dirname($folder.'/'.$event['event_identifier']);
		$file['name'] = $tournament_folder.'/'.$event['dateiname'].'/'.$event['filename_suffix'].'.pdf';
		$file['send_as'] = $event['send_as_plural'].' '.$event['event'].'.pdf';
	}
	wrap_mkdir($tournament_folder);
	
	$file['caching'] = false;
	$file['etag_generate_md5'] = true;
	$pdf->output('F', $file['name'], true);
	wrap_file_send($file);
}

/**
 * fügt Hintergrundfarbe unter einzeilige Zellen, wenn nebenan eine
 * mehrzeilige Zelle vorhanden ist
 * 
 * @param object $pdf
 * @param int $y_bottom
 * @param int $width
 * @return void
 */
function mf_tournaments_pdf_add_bg_color(&$pdf, $y_bottom, $width, $height) {
	if ($pdf->GetY() >= $y_bottom) return;
	$current_x = $pdf->GetX();
	$current_y = $pdf->GetY();
	$pdf->SetXY($current_x - $width, $current_y + $height);
	$pdf->Cell($width, $y_bottom - $pdf->GetY(), '', '', 0, 'R', 1);
	$pdf->SetXY($current_x, $current_y);
}
