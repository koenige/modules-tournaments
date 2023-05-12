<?php

/**
 * tournaments module
 * export participant data as PDF for tables
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2023 Gustaf Mossakowski
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
	wrap_lib('tfpdf');
	wrap_include_files('pdf', 'tournaments');

	// event information
	$event = wrap_static('page', 'event');
	$event_title = sprintf("%s \n%s %s"
		, str_replace('-', '- ', $event['main_series_long'])
		, $event['turnierort'], $event['year']
	);
	$event_title = mf_tournaments_event_title_wrap($event_title);
	
	// get data for cards
	$first_field = reset($ops['output']['head']);
	switch ($first_field['field_name']) {
		case 'usergroup_id': $data = mf_tournaments_export_pdf_tischkarten_single($ops); break;
		case 'team_id': $data = mf_tournaments_export_pdf_tischkarten_team($ops); break;
		default: return false;
	}
	if (!$data) wrap_quit(404, 'Es gibt keine Tischkarten für diese Personen.');

	// A4 PDF, set fonts
	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->setCompression(true);
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);

	$card['width'] = 297.5;
	$card['height'] = 198.5;
	$card['margin'] = 20;
	$card['logo_height'] = 34;

	$k = 0;
	foreach ($data as $id => $line) {
		if (!is_numeric($id)) continue;
		$i = floor($k / 2);
		$col = $k % 2;
		// PDF setzen
		$row = $i % 4;
		if (!$row AND !$col) $pdf->addPage();
		$top = $card['height'] * $row;
		$left = $card['width'] * $col;

		// logo and tournament
		$logo = [
			'top' => 15 + $top,
			'left' => $left
		];
		if (!empty($line['federation_abbr'])) {
			$logo['filename'] = sprintf('%s/flaggen/%s.png', wrap_setting('media_folder'), wrap_filename($line['federation_abbr']));
			$logo['border'] = true;
		}
		mf_tournaments_pdf_logo($pdf, $logo, $card);

		$pdf->setFont('FiraSans-Regular', '', 10);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetXY($card['margin'] + $left + $card['width']/2 - 25, 15 + $top);
		$pdf->MultiCell(118.5, 12, $event_title, 0, 'L');
		
		// name + club
		$pdf->setFont('FiraSans-SemiBold', '', 25);
		$pdf->SetXY($card['margin'] + $left, $pdf->GetY() + 10);
		if ($pdf->GetStringWidth($line['name']) < 252) {
			// 257 geht nicht, passt nicht immer
			$pdf->SetXY($card['margin'] + $left, $pdf->GetY() + 12.5);
		} else {
			// @todo this can be done way better
			if (!strstr($line['name'], ' '))
				$line['name'] = str_replace('-', '- ', $line['name']);
		}
		if (empty($data['has_club_line'])) {
			$pdf->SetXY($card['margin'] + $left, $pdf->GetY() + 12.5);
		}
		$pdf->MultiCell(257, 25, $line['name'], 0, 'C');
		$pdf->SetXY($card['margin'] + $left, ($pdf->GetY() + 2));
		$pdf->setFont('FiraSans-Regular', '', 12);
		$pdf->MultiCell(257, 14, $line['club_line'], 0, 'C');

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
		$startpos = $card['width'] * $col + 148.75 - $width/2;
		$pdf->SetXY($startpos, $top + 130);
		foreach ($line['ratings'] as $key => $value) {
			$pdf->setFont('FiraSans-Regular', '', 12);
			$pdf->Cell($pdf->GetStringWidth($key), 27, $key, 0, 0, 'L');	
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$pdf->Cell($pdf->GetStringWidth($value), 24, $value, 0, 0, 'L');	
		}

		// Balken
		$pdf->SetXY($card['margin'] + $left, $top + 155);
		$pdf->setFont('FiraSans-SemiBold', '', 18);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFillColor($line['colors']['red'], $line['colors']['green'], $line['colors']['blue']);
		if ($line['colors']['red'] + $line['colors']['green'] + $line['colors']['blue'] > 458) {
			$pdf->SetTextColor(0, 0, 0);
		}
		$y = $pdf->getY();
		$pdf->Cell(257, 28, ' '.$line['group_line'], 0, 2, 'L', 1);
		$pdf->SetXY($pdf->getX(), $y);
		if (!empty($line['federation_abbr']))
			$pdf->Cell(257, 28, $line['federation_abbr'].' ', 0, 2, 'R');
		$k++;
	}

	// write PDF to cache folder, send
	$folder = wrap_setting('tmp_dir').'/schilder/'.$event['identifier'];
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
	
	$name_tag['image_size'] = 68;

	$data = [];
	foreach ($ops['output']['rows'] as $index => $line) {
		// ignoriere Orga vorab
		if (!in_array($line[$nos['usergroup_id']]['text'], ['Spieler'])) continue;
		$data[$index] = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos, $name_tag);
		$data[$index]['colors'] = mf_tournaments_pdf_colors($data[$index]['parameters'], $data[$index]['role']);
		$data[$index]['group_line'] = mf_tournaments_pdf_group_line($data[$index]);
		$data[$index]['club_line'] = mf_tournaments_pdf_club_line($data[$index]);

		// Daten anpassen
		$data[$index]['ratings'] = [];
		if ($line[$nos['t_dwz']]['text']) $data[$index]['ratings']['DWZ'] = ' '.$line[$nos['t_dwz']]['text'];
		if ($line[$nos['t_elo']]['text']) $data[$index]['ratings']['Elo'] = ' '.$line[$nos['t_elo']]['text'];
	}
	if ($data) $data['has_club_line'] = true;
	return $data;
}

function mf_tournaments_export_pdf_tischkarten_team($ops) {
	$team_ids = [];
	foreach ($ops['output']['rows'] as $row) {
		$team_ids[] = $row['0']['value'];
	}
	$sql = 'SELECT team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS name
			, team
			, IF(LENGTH(event) > 12, category_short, event) AS group_line
			, club_contact_id AS contact_id
			, categories.parameters
	    FROM teams
	    LEFT JOIN events USING (event_id)
	    LEFT JOIN categories
	    	ON events.series_category_id = categories.category_id
	    WHERE team_id IN (%s)';
	$sql = sprintf($sql, implode(',', $team_ids));
	$teams = wrap_db_fetch($sql, 'team_id');
	$teams = mf_tournaments_clubs_to_federations($teams, 'contact_id');
	
	foreach ($teams as $team_id => $team) {
		if (empty($team['country'])) $team['country'] = '';
		$teams[$team_id]['club_line'] = $team['country'] !== $teams[$team_id]['team'] ? $team['country'] : '';
		$teams[$team_id]['ratings'] = [];
		parse_str($team['parameters'], $team['parameters']);
		if (empty($team['parameters']['color'])) $team['parameters']['color'] = '#CC0000';
		$teams[$team_id]['colors'] = mf_tournaments_colors_hex2dec($team['parameters']['color']);
		if ($teams[$team_id]['club_line'])
			$teams['has_club_line'] = true;
	}
	return $teams;
}

/**
 * Suche Feld-IDs aus Daten
 * IDs sind nicht vorherbestimmbar
 *
 * @param array $head = $ops['output']['head']
 * @return array $nos
 */
function mf_tournaments_export_pdf_teilnehmerschilder_nos($head) {
	$fields = [
		'usergroup_id', 'parameters', 't_vorname', 't_nachname', 'person_id',
		't_fidetitel', 't_verein', 'event_id', 'federation_contact_id',
		'lebensalter', 'role', 't_dwz', 't_elo', 'sex', 'club_contact_id',
		'series_parameters', 'usergroup_category'
	];
	$nos = [];
	foreach ($head as $index => $field) {
		if (!in_array('field_name', array_keys($field))) continue;
		if (!in_array($field['field_name'], $fields)) continue;
		$nos[$field['field_name']] = $index;
	}
	return $nos;
}

/**
 * Daten anpassen für Ausgabe
 *
 * @param array $line
 * @param array $nos
 * @return array $line
 */
function mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos) {
	if (!empty($line[$nos['parameters']]['text'])) {
		parse_str($line[$nos['parameters']]['text'], $new['parameters']);
	} else {
		$new['parameters'] = [];
	}
	if (!empty($line[$nos['series_parameters']]['text'])) {
		parse_str($line[$nos['series_parameters']]['text'], $series_parameters);
		$new['parameters'] = array_merge($new['parameters'], $series_parameters);
	}

	// Spieler
	$new['fidetitel'] = !empty($nos['t_fidetitel']) ? $line[$nos['t_fidetitel']]['text'] : '';
	$new['name'] = ($new['fidetitel'] ? $new['fidetitel'].' ' : '')
		.((!empty($nos['t_vorname']) AND !empty($line[$nos['t_vorname']]['text']))
		? $line[$nos['t_vorname']]['text'].' '.$line[$nos['t_nachname']]['text']
		: $line[$nos['person_id']]['text']);

	// further data
	$new['club'] = (!empty($nos['t_verein']) ? $line[$nos['t_verein']]['text'] : '');
	$new['usergroup'] = $line[$nos['usergroup_id']]['text'];
	$new['usergroup_category'] = $line[$nos['usergroup_category']]['text'];
	$new['role'] = (!empty($nos['role']) AND !empty($line[$nos['role']]['text'])) ? $line[$nos['role']]['text'] : '';
	$new['sex'] = !empty($nos['sex']) ? $line[$nos['sex']]['text'] : '';
	$new['event'] = $line[$nos['event_id']]['text'];
	$new['federation_abbr'] = !empty($nos['federation_contact_id']) ? $line[$nos['federation_contact_id']]['text'] : '';
	$new['club_contact_id'] = !empty($nos['club_contact_id']) ? $line[$nos['club_contact_id']]['value'] : '';
	$new['age'] = !empty($nos['lebensalter']) ? $line[$nos['lebensalter']]['value'] : '';

	return $new;
}

