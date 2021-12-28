<?php

/**
 * tournaments module
 * export participant data as PDF for tables
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
 * FM Vorname Nachname
 * SK Doppelbauer Kiel
 * U18
 * SHO
 * @param array $ops
 */
function mf_tournaments_export_pdf_tischkarten($ops) {
	global $zz_setting;
	global $zz_conf;
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	// event information
	$event = $zz_conf['event'];
	$event_title = sprintf('%s %s %s'
		, str_replace('-', '- ', $event['main_series_long'])
		, $event['turnierort'], $event['year']
	);

	// get data for cards
	$first_field = reset($ops['output']['head']);
	switch ($first_field['field_name']) {
		case 'usergroup_id': $data = mf_tournaments_export_pdf_tischkarten_single($ops); break;
		case 'team_id': $data = mf_tournaments_export_pdf_tischkarten_team($ops); break;
		default: return false;
	}

	// A4 PDF, set fonts
	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->setCompression(true);
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);

	$hoehe_flagge = 34;
	$k = 0;
	foreach ($data as $id => $line) {
		if (!is_numeric($id)) continue;
		$i = floor($k / 2);
		$col = $k % 2;
		// PDF setzen
		$row = $i % 4;
		if (!$row AND !$col) $pdf->addPage();
		$top = 198.5 * $row;

		if (!empty($line['federation_abbr'])) {
			$line['flagge'] = sprintf('%s/flaggen/%s.png', $zz_setting['media_folder'], wrap_filename($line['federation_abbr']));
			if (!file_exists($line['flagge'])) {
				$line['flagge'] = false;
			} else {
				$size = getimagesize($line['flagge']);
				$line['flagge_width'] = $size[0] / $size[1] * $hoehe_flagge;
			}
		}

		// logo and tournament
		$left = 297.5 * $col;
		if (!empty($line['flagge'])) {
			$imageleft = 20 + $left + 297.5/2 - 10 - $line['flagge_width'] - 25;
			$pdf->SetXY($imageleft, 15 + $top);
			$pdf->Cell($line['flagge_width'], $hoehe_flagge, '', 1);
			$pdf->image($line['flagge'], $imageleft, 15 + $top, $line['flagge_width'], $hoehe_flagge);
		} else {
			$pdf->image($zz_setting['media_folder'].'/urkunden-grafiken/DSJ-Logo.jpg', 20 + $left + 297.5/2 - 10 - 58 - 25, 15 + $top, 41, $hoehe_flagge);
		}
		$pdf->setFont('FiraSans-Regular', '', 10);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY(20 + $left + 297.5/2 - 25, 15 + $top);
		$pdf->MultiCell(118.5, 12, $event_title, 0, 'L');
		
		// name + club
		$pdf->setFont('FiraSans-SemiBold', '', 25);
		$pdf->SetXY(20 + $left, $pdf->GetY() + 10);
		if ($pdf->GetStringWidth($line['name']) < 252) {
			// 257 geht nicht, passt nicht immer
			$pdf->SetXY(20 + $left, $pdf->GetY() + 12.5);
		} else {
			// @todo this can be done way better
			if (!strstr($line['name'], ' '))
				$line['name'] = str_replace('-', '- ', $line['name']);
		}
		if (empty($data['has_club_line'])) {
			$pdf->SetXY(20 + $left, $pdf->GetY() + 12.5);
		}
		$pdf->MultiCell(257, 25, $line['name'], 0, 'C');
		$pdf->SetXY(20 + $left, ($pdf->GetY() + 2));
		$pdf->setFont('FiraSans-Regular', '', 12);
		$pdf->MultiCell(257, 14, $line['club'], 0, 'C');

		// Wertungen
		$width = 0;
		$count = 0;
		foreach ($line['ratings'] as $key => $value) {
			$count++;
			if ($count !== count($line['ratings'])) {
				$value .= ' ';
				$line['ratings'][$key] = $value.' ';
			}
			// mittig
			$pdf->setFont('FiraSans-Regular', '', 12);
			$width += $pdf->GetStringWidth($key);
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$width += $pdf->GetStringWidth($value);
		}
		$startpos = 297.5 * $col + 148.75 - $width/2;
		$pdf->SetXY($startpos, $top + 130);
		foreach ($line['ratings'] as $key => $value) {
			$pdf->setFont('FiraSans-Regular', '', 12);
			$pdf->Cell($pdf->GetStringWidth($key), 27, $key, 0, 0, 'L');	
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$pdf->Cell($pdf->GetStringWidth($value), 24, $value, 0, 0, 'L');	
		}

		// Balken
		$pdf->SetXY(20 + $left, $top + 155);
		$pdf->setFont('FiraSans-SemiBold', '', 18);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFillColor($line['colors']['red'], $line['colors']['green'], $line['colors']['blue']);
		if ($line['colors']['red'] + $line['colors']['green'] + $line['colors']['blue'] > 458) {
			$pdf->SetTextColor(0, 0, 0);
		}
		$y = $pdf->getY();
		$pdf->Cell(257, 28, ' '.$line['usergroup'], 0, 2, 'L', 1);
		$pdf->SetXY($pdf->getX(), $y);
		$pdf->Cell(257, 28, $line['federation_abbr'].' ', 0, 2, 'R');
		$k++;
	}

	// write PDF to cache folder, send
	$folder = $zz_setting['tmp_dir'].'/schilder/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/tischkarten.pdf')) {
		unlink($folder.'/tischkarten.pdf');
	}
	$file['name'] = $folder.'/tischkarten.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Tischkarten.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
	wrap_file_send($file);
	exit;
}

function mf_tournaments_export_pdf_tischkarten_single($ops) {	
	require_once __DIR__.'/export-pdf-teilnehmerschilder.inc.php';
	
	// Feld-IDs raussuchen
	$nos = mf_tournaments_export_pdf_teilnehmerschilder_nos($ops['output']['head']);

	$data = [];
	foreach ($ops['output']['rows'] as $index => $line) {
		// ignoriere Orga vorab
		if (!in_array($line[$nos['usergroup_id']]['text'], ['Spieler'])) continue;
		$data[$index] = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos);

		// Daten anpassen
		$data[$index]['ratings'] = [];
		if ($line[$nos['t_dwz']]['text']) $data[$index]['ratings']['DWZ'] = ' '.$line[$nos['t_dwz']]['text'];
		if ($line[$nos['t_elo']]['text']) $data[$index]['ratings']['Elo'] = ' '.$line[$nos['t_elo']]['text'];
	}
	$data['has_club_line'] = true;
	return $data;
}

function mf_tournaments_export_pdf_tischkarten_team($ops) {
	$team_ids = [];
	foreach ($ops['output']['rows'] as $row) {
		$team_ids[] = $row['0']['value'];
	}
	$sql = 'SELECT team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS name
			, IF(LENGTH(event) > 12, category_short, event) AS usergroup
			, club_contact_id AS contact_id
			, categories.parameters
	    FROM teams
	    LEFT JOIN events USING (event_id)
	    LEFT JOIN categories
	    	ON events.series_category_id = categories.category_id
	    WHERE team_id IN (%s)';
	$sql = sprintf($sql, implode(',', $team_ids));
	$teams = wrap_db_fetch($sql, 'team_id');
	$teams = mf_tournaments_federations($teams, 'team_id');
	
	foreach ($teams as $team_id => $team) {
		$teams[$team_id]['name'] = my_verein_saeubern($team['name']);
		$teams[$team_id]['club'] = $team['federation'] !== $teams[$team_id]['name'] ? $team['federation'] : '';
		$teams[$team_id]['ratings'] = [];
		parse_str($team['parameters'], $team['parameters']);
		if (empty($team['parameters']['color'])) $team['parameters']['color'] = '#CC0000';
		$teams[$team_id]['colors'] = mf_tournaments_colors_hex2dec($team['parameters']['color']);
		if ($teams[$team_id]['club'])
			$teams['has_club_line'] = true;
	}
	return $teams;
}
