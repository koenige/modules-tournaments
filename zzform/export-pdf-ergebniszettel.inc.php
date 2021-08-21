<?php

/**
 * tournaments module
 * export results notes as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ausgabe von Teilnehmerschildern
 *
 * Deutsche Einzelmeisterschaft 2017 Willingen
 * Paarung in Runde: 9
 * Tisch: 2
 * Ergebnis
 * Bestätigung
 * @param array $ops
 */
function mf_tournaments_export_pdf_ergebniszettel($ops) {
	global $zz_setting;
	global $zz_conf;

	$event = $zz_conf['event'];
	
	// Feld-IDs raussuchen
	$nos = mf_tournaments_export_pdf_ergebniszettel_nos($ops['output']['head']);
	
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->open();
	$pdf->setCompression(true);
	// Fira Sans!
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);

	// Layout: Spalten/Zeilen
	$cols_pp = 2;
	$rows_pp = 6;
	$cards_pp = $cols_pp * $rows_pp;

	$cards_max = count($ops['output']['rows']);
	$page_max = ceil($cards_max / $cards_pp);

	$lines = [];
	for ($page = 0; $page < $page_max; $page++) {
		for ($i = 0; $i < $cards_pp; $i++) {
			$index = $i * $page_max + $page;
			if (empty($ops['output']['rows'][$index])) {
				$lines[] = [];
			} else {
				$lines[] = $ops['output']['rows'][$index];
			}
		}		
	}

	$k = 0;
	$runde_no = false;
	foreach ($lines as $line) {
		if (empty($line)) {
			$k++;
			$i++;
			continue;
		}

		if (!$runde_no) $runde_no = $line[$nos['runde_no']]['text'];
	
		// PDF setzen
		$i = floor($k / 2);
		$col = $k % $cols_pp;
		// PDF setzen
		$row = $i % $rows_pp;
		if (!$row AND !$col) $pdf->addPage();
		$top = 130 * $row;

		$left = 297.5 * $col;
		$pdf->setFont('FiraSans-SemiBold', '', 11);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY(20 + $left, 20 + $top);
		$length = $pdf->GetStringWidth($line[$nos['event_id']]['text']);
		$pdf->Cell($length, 14, $line[$nos['event_id']]['text'], 0, 0, 'L');
		$pdf->setFont('FiraSans-Regular', '', 11);
		$length_2 = $pdf->GetStringWidth(' '.$event['turnierort'].' '.$event['year']);
		$pdf->Cell($length_2, 14, ' '.$event['turnierort'].' '.$event['year'], 0, 0, 'L');
		// $line[$nos['runde_no']]
		$pdf->setFont('FiraSans-SemiBold', '', 11);
		$pdf->Cell(257 - $length - $length_2, 14, ' '.$line[$nos['runde_no']]['text'].'. Runde', 0, 1, 'L');

		$pdf->SetX(20 + $left);
		$pdf->setFont('FiraSans-SemiBold', '', 11);
		$pdf->MultiCell(257, 14, 'Brett '.$line[$nos['brett_no']]['text'].': '.$line[$nos['weiss_person_id']]['text'].' – '.$line[$nos['schwarz_person_id']]['text'], 0, 'L');

		$y = $pdf->GetY() + 10;
		$pdf->SetXY(20 + $left, $y);
		$pdf->setFont('FiraSans-Regular', '', 40);
		$pdf->Cell(90, 50, ':', 1, 0, 'C');

		$pdf->SetXY(120 + $left, $y+13);
		$pdf->Cell(12, 12, '', 1, 0, 'C');
		$pdf->SetXY(120 + $left, $y+38);
		$pdf->Cell(12, 12, '', 1, 0, 'C', 1);
		$pdf->Line(140 + $left, $y+25, $left + 277, $y+25);
		$pdf->Line(140 + $left, $y+50, $left + 277, $y+50);

		$k++;
		$i++;
	}
	$folder = $zz_setting['tmp_dir'].'/ergebniszettel/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/runde-'.$runde_no.'.pdf')) {
		unlink($folder.'/runde-'.$runde_no.'.pdf');
	}
	$file['name'] = $folder.'/runde-'.$runde_no.'.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Ergebniszettel '.$runde_no.'.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output($file['name']);
	wrap_file_send($file);
	exit;
}	

/**
 * Suche Feld-IDs aus Daten
 * IDs sind nicht vorherbestimmbar
 *
 * @param array $head = $ops['output']['head']
 * @return array $nos
 */
function mf_tournaments_export_pdf_ergebniszettel_nos($head) {
	$fields = [
		'runde_no', 'brett_no', 'weiss_person_id', 'weiss_ergebnis',
		'schwarz_person_id', 'event_id'
	];
	$nos = [];
	foreach ($head as $index => $field) {
		if (!in_array('field_name', array_keys($field))) continue;
		if (!in_array($field['field_name'], $fields)) continue;
		$nos[$field['field_name']] = $index;
	}
	return $nos;
}
