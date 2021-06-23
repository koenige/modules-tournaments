<?php

/**
 * tournaments module
 * export participant data as PDF for tables
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ausgabe von Teilnehmerschildern
 *
 * Deutsche Einzelmeisterschaft 2017 Willingen
 * FM Vorname Nachname
 * SK Doppelbauer Kiel
 * U18
 * SHO
 * @param array $ops
 */
function export_pdf_tischkarten($ops) {
	global $zz_setting;
	global $zz_conf;
	require_once __DIR__.'/export-pdf-teilnehmerschilder.inc.php';
	$event = $zz_conf['event'];
	$event['main_series_long'] = str_replace('-', '- ', $event['main_series_long']);
	
	// Feld-IDs raussuchen
	$nos = export_pdf_teilnehmerschilder_nos($ops['output']['head']);
	
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->open();
	$pdf->setCompression(true);
	// Fira Sans!
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$vorlagen = $zz_setting['media_folder'].'/urkunden-grafiken';

	$hoehe_flagge = 34;

	$k = 0;
	foreach ($ops['output']['rows'] as $line) {
		// ignoriere Orga vorab
		if (!in_array($line[$nos['usergroup_id']]['text'], ['Spieler'])) continue;

		$i = floor($k / 2);
		$col = $k % 2;
		// PDF setzen
		$row = $i % 4;
		if (!$row AND !$col) $pdf->addPage();
		$top = 198.5 * $row;

		// Daten anpassen
		$line = export_pdf_teilnehmerschilder_prepare($line, $nos);
		$wertungen = [];
		if ($line[$nos['t_dwz']]['text']) $wertungen['DWZ'] = ' '.$line[$nos['t_dwz']]['text'];
		if ($line[$nos['t_elo']]['text']) $wertungen['Elo'] = ' '.$line[$nos['t_elo']]['text'];

		if (!empty($line['lv'])) {
			$line['flagge'] = sprintf('%s/flaggen/%s.png', $zz_setting['media_folder'], wrap_filename($line['lv']));
			if (!file_exists($line['flagge'])) {
				$line['flagge'] = false;
			} else {
				$size = getimagesize($line['flagge']);
				$line['flagge_width'] = $size[0] / $size[1] * $hoehe_flagge;
			}
		}

		// DSJ-Logo
		$left = 297.5 * $col;
		if (!empty($line['flagge'])) {
			$imageleft = 20 + $left + 297.5/2 - 10 - $line['flagge_width'] - 25;
			$pdf->SetXY($imageleft, 15 + $top);
			$pdf->Cell($line['flagge_width'], $hoehe_flagge, '', 1);
			$pdf->image($line['flagge'], $imageleft, 15 + $top, $line['flagge_width'], $hoehe_flagge);
		} else {
			$pdf->image($vorlagen.'/DSJ-Logo.jpg', 20 + $left + 297.5/2 - 10 - 58 - 25, 15 + $top, 41, $hoehe_flagge);
		}
		$pdf->setFont('FiraSans-Regular', '', 10);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY(20 + $left + 297.5/2 - 25, 15 + $top);
		$pdf->MultiCell(118.5, 12, $event['main_series_long'].' '.$event['turnierort'].' '.$event['year'], 0, 'L');
		
		// Name + Verein
		$pdf->setFont('FiraSans-SemiBold', '', 25);
		$pdf->SetXY(20 + $left, $pdf->GetY() + 10);
		if ($pdf->GetStringWidth($line['spieler']) < 252) {
			// 257 geht nicht, passt nicht immer
			$pdf->SetXY(20 + $left, $pdf->GetY() + 12.5);
		}
		$pdf->MultiCell(257, 25, $line['spieler'], 0, 'C');
		$pdf->SetXY(20 + $left, ($pdf->GetY() + 2));
		$pdf->setFont('FiraSans-Regular', '', 12);
		$pdf->MultiCell(257, 14, $line['verein'], 0, 'C');

		// Wertungen
		$width = 0;
		$count = 0;
		foreach ($wertungen as $key => $value) {
			$count++;
			if ($count !== count($wertungen)) {
				$value .= ' ';
				$wertungen[$key] = $value.' ';
			}
			// mittig
			$pdf->setFont('FiraSans-Regular', '', 12);
			$width += $pdf->GetStringWidth($key);
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$width += $pdf->GetStringWidth($value);
		}
		$startpos = 297.5 * $col + 148.75 - $width/2;
		$pdf->SetXY($startpos, $top + 130);
		foreach ($wertungen as $key => $value) {
			$pdf->setFont('FiraSans-Regular', '', 12);
			$pdf->Cell($pdf->GetStringWidth($key), 27, $key, 0, 0, 'L');	
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$pdf->Cell($pdf->GetStringWidth($value), 24, $value, 0, 0, 'L');	
		}

		// Balken
		$pdf->SetXY(20 + $left, $top + 155);
		$pdf->setFont('FiraSans-SemiBold', '', 18);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFillColor($line['red'], $line['green'], $line['blue']);
		if ($line['red'] + $line['green'] + $line['blue'] > 458) {
			$pdf->SetTextColor(0, 0, 0);
		}
		$y = $pdf->getY();
		$pdf->Cell(257, 28, ' '.$line['usergroup'], 0, 2, 'L', 1);
		$pdf->SetXY($pdf->getX(), $y);
		$pdf->Cell(257, 28, $line['lv'].' ', 0, 2, 'R');
		$k++;
	}
	$folder = $zz_setting['cache_dir'].'/schilder/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/tischkarten.pdf')) {
		unlink($folder.'/tischkarten.pdf');
	}
	$file['name'] = $folder.'/tischkarten.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Tischkarten.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output($file['name']);
	wrap_file_send($file);
	exit;
}	
