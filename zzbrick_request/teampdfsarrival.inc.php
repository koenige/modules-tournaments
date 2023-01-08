<?php

/**
 * tournaments module
 * Ausgabe aller Meldeformulare vom Anreisetag zu einer Meisterschaft als PDF
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2013-2014, 2017-2023 Gustaf Mossakowski
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
	wrap_include_files('pdf', 'tournaments');

	if (count($vars) === 3) {
		$team_identifier = implode('/', $vars);
		array_pop($vars);
	} elseif (count($vars) === 2) {
		$team_identifier = false;
	} else {
		return false;
	}

	$event = mf_tournaments_pdf_event($vars);
	if (!$event) return false;
	
	$params = [
		'team_identifier' => $team_identifier,
		'participants_order_by' => 't_dwz DESC, last_name, first_name'
	];
	$event['teams'] = mf_tournaments_pdf_teams($event, $params);

	return mod_tournaments_teampdfsarrival_pdf($event);
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
 * # betrag, meldung_datum, team_identifier }, event_identifier,
 * # dateiname, konten_veranstalter {inhaber, iban, bic, institut},
 * # konten_ausrichter {inhaber, iban, bic, institut}, bretter_min,
 * # gastspieler_status, dauer_tage
 * @return void
 */
function mod_tournaments_teampdfsarrival_pdf($event) {
	list($pdf, $settings) = mf_tournaments_pdf_prepare($event);

	$margin_top_bottom = 45;

	$pdf->setMargins(45, $margin_top_bottom);

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

		$pdf->Image($settings['logo_filename'], 595 - $margin_top_bottom - $settings['logo_width'], $margin_top_bottom, $settings['logo_width'], $settings['logo_height'], 'PNG');
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
		if ($settings['show_federation']) {
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
			mf_tournaments_pdf_add_bg_color($pdf, $y_bottom, 35, 14);
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
	
	$event['filename_suffix'] = 'meldebogen-anreise';
	$event['send_as_singular'] = 'Meldebogen Anreise';
	$event['send_as_plural'] = 'Meldebögen Anreise';
	return mf_tournaments_pdf_send($pdf, $event);
}
