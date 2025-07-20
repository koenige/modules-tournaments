<?php

/**
 * tournaments module
 * export participant signs as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2020, 2022-2025 Gustaf Mossakowski
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
function mf_tournaments_export_pdf_teilnehmerschilder($ops) {
	wrap_include('pdf', 'tournaments');

	$ids = [];
	foreach ($ops['output']['rows'] as $line) {
		$ids[] = $line['id_value'];
	}
	$sql = 'SELECT participation_id, event_id
		FROM participations
		LEFT JOIN categories
			ON participations.status_category_id = categories.category_id
		WHERE participation_id IN (%s)
		AND (ISNULL(categories.parameters) OR categories.parameters NOT LIKE "%%&tournaments_no_cards=1%%")';
	$sql = sprintf($sql, implode(',', $ids));
	$data = wrap_db_fetch($sql, 'participation_id');
	if (!$data) wrap_quit(404, 'Es gibt keine Teilnehmerkarten für diese Personen.');
	
	$events = [];
	foreach ($data as $line) {
		if (array_key_exists($line['event_id'], $events)) continue;
		$events[$line['event_id']] = my_event($line['event_id']);
		if ($events[$line['event_id']]['series_parameter']) {
			parse_str($events[$line['event_id']]['series_parameter'], $events[$line['event_id']]['series_parameter']);
		}
 	}
 	$event = wrap_static('zzform', 'event');
	if ($event['series_parameter']) {
		parse_str($event['series_parameter'], $event['series_parameter']);
		$event += $event['series_parameter'];
	}
	
	// extra form fields?
	$sql = 'SELECT formfield_id, formfield, parameters, formfield_category_id
			, IFNULL(registrationvarchar, registrationtext) AS text
			, participation_id
		FROM formfields
	    LEFT JOIN forms USING (form_id)
	    LEFT JOIN registrationvarchars USING (formfield_id)
	    LEFT JOIN registrationtexts USING (formfield_id)
	    LEFT JOIN registrations
	    	ON IFNULL(registrationvarchars.registration_id, registrationtexts.registration_id) = registrations.registration_id
	    WHERE forms.event_id IN (%s)
	    AND parameters LIKE "%%&name_tag=%%"
	';
	$sql = sprintf($sql, implode(',', array_keys($events)));
	$formfields = wrap_db_fetch($sql, ['participation_id', 'formfield_id']);

	$name_tag_size = !empty($event['name_tag_size']) ? $event['name_tag_size'] : '10.5x7';
	switch ($name_tag_size) {
	case '9x5.5':
		$card['width'] = 255.12;
		$card['height'] = 155.9;
		$card['rows'] = 5;
		$card['margin'] = 12;
		$card['image_size'] = 54;
		$card['logo_height'] = 26;
		$card['bar_height'] = 22;
		$card['bar_font_size'] = 14;
		$card['event_font_size'] = 10;
		$card['club_font_size'] = 10;
		break;
	default:
	case '10.5x7':
		$card['width'] = 297.5;
		$card['height'] = 198.5;
		$card['rows'] = 4;
		$card['margin'] = 20;
		$card['image_size'] = 68;
		$card['logo_height'] = 36;
		$card['bar_height'] = 28;
		$card['bar_font_size'] = 18;
		$card['event_font_size'] = 11;
		$card['club_font_size'] = 12;
		break;
	}

	$sql = 'SELECT participation_id, participations.contact_id
			, t_fidetitel AS fidetitel
			, CONCAT(
				IFNULL(CONCAT(t_fidetitel, " "), ""),
				IFNULL(t_vorname, first_name), 
				IFNULL(CONCAT(" ", IFNULL(t_namenszusatz, name_particle)), ""), " ", 
				IFNULL(t_nachname, last_name)
			) AS name
			, sex
			, t_verein AS club
			, usergroups.usergroup
			, usergroup_categories.category AS usergroup_category
			, role
			, federations.contact_abbr AS federation_abbr
			, IFNULL(participations.club_contact_id, teams.club_contact_id) AS club_contact_id
			, YEAR(CURDATE()) - YEAR(date_of_birth) AS age
			, usergroups.parameters
			, participations.event_id
			, IF(IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) > 18, 1,
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_begin, 1, NULL)
			) AS volljaehrig
			, IF(SUBSTRING(date_of_birth, 5, 6) = "-00-00" AND IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) = 18, 1, 
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_end AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) >= events.date_begin, 1, NULL)
			) AS evtl_volljaehrig
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN categories usergroup_categories
			ON usergroups.usergroup_category_id = usergroup_categories.category_id
		LEFT JOIN contacts federations
			ON participations.federation_contact_id = federations.contact_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN teams USING (team_id)
		WHERE participations.participation_id IN (%s)
		AND (ISNULL(usergroups.parameters) OR usergroups.parameters NOT LIKE "%%&present=0%%")
		ORDER BY IFNULL(t_nachname, last_name), IFNULL(t_vorname, first_name), participation_id
	';
	$sql = sprintf($sql
		, implode(',', array_keys($data))
	);
	$data = wrap_db_fetch($sql, 'participation_id');
	if (!$data) wrap_quit(404, 'Es gibt keine Teilnehmerschilder für diese Personen.');
	if (in_array('ratings', wrap_setting('modules'))) {
		wrap_package_activate('ratings');
		$data = mf_ratings_titles($data);
	}
	
	foreach ($data as $participation_id => $line) {
		if ($line['parameters'])
			parse_str($line['parameters'], $line['parameters']);
		else
			$line['parameters'] = [];
		if (!empty($events[$line['event_id']]['series_parameter']))
			$line['parameters'] += $events[$line['event_id']]['series_parameter'];
		$line['event'] = $events[$line['event_id']]['series_short'] ?? $events[$line['event_id']]['event'];
		$line['colors'] = mf_tournaments_pdf_colors($line['parameters'], $line['role']);
		$line['zusaetzliche_ak'] = mf_tournaments_pdf_agegroups($line['parameters'], $line['age']);
		$line['group_line'] = mf_tournaments_pdf_group_line($line);
		$line['club_line'] = mf_tournaments_pdf_club_line($line);
		$line['graphic'] = mf_tournaments_pdf_graphic([$line['role'], $line['usergroup']], $card);
		if (empty($line['fidetitel']) AND !empty($line['fide_title']))
			$line['name'] = $line['fide_title'].' '.$line['name'];

		if (array_key_exists($participation_id, $formfields)) {
			foreach ($formfields[$participation_id] as $formfield) {
				if (!$formfield['text']) continue;
				parse_str($formfield['parameters'], $formfield['parameters']);
				if (empty($line[$formfield['parameters']['name_tag']]))
					$line[$formfield['parameters']['name_tag']] = '';
				else
					$line[$formfield['parameters']['name_tag']] .= "\n";
				$line[$formfield['parameters']['name_tag']] .= $formfield['formfield'].': '.str_replace("\n", ", ", $formfield['text']);
			}
		}
		$data[$participation_id] = $line;
	}
	$data = mf_tournaments_clubs_to_federations($data, 'club_contact_id');
	
	wrap_lib('tfpdf');

	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->setCompression(true);
	// Fira Sans!
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$pdf->SetLineWidth(0.25);
	
	$event['main_series_long'] = mf_tournaments_event_title_wrap($event['main_series_long']);
	
	$cell_width = $card['width'] - 2 * $card['margin'];

	$i = 0;
	foreach ($data as $line) {
		// PDF setzen
		$row = $i % $card['rows'];
		if (!$row) {
			$pdf->addPage();
			$pos_x = $card['width'];
			while($pos_x < 595) {
				$pdf->Line($pos_x, 0, $pos_x, 842);
				$pos_x += $card['width'];
			}
		}
		$top = $card['height'] * $row;
		$pdf->Line(0, $top + $card['height'], 595, $top + $card['height']);
		for ($j = 0; $j < 2; $j++) {
			// logo or QR code
			$left = $card['width'] * $j;
			
			$logo = [
				'top' => $card['margin'] + $top,
				'left' => $left
			];
			if ($j & 1) {
				$logo['filename'] = mf_tournaments_p_qrcode($line['participation_id']);
				$logo['height_factor'] = 1.35;
				$logo['width_factor'] = 1.35;
			} elseif (!empty($line['federation_abbr']) AND wrap_setting('tournaments_type_team')) {
				// @todo better do this via parameters and not event_category
				$logo['filename'] = sprintf('%s/flaggen/%s.png', wrap_setting('media_folder'), wrap_filename($line['federation_abbr']));
				$logo['border'] = true;
			}
			mf_tournaments_pdf_logo($pdf, $logo, $card);
			$pdf->SetLineWidth(0.25);

			// event
			$pdf->setFont('FiraSans-Regular', '', $card['event_font_size']);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($left + $card['width']/2, $card['margin'] + $top);
			$pdf->MultiCell(125, round($card['event_font_size'] * 1.2), $event['main_series_long']."\n".$event['place'].' '.$event['year'], 0, 'L');
			
			// name
			$pdf->SetXY($card['margin'] + $left, $pdf->GetY() + $card['margin'] * 1.2);
			if (strlen($line['name']) > 23) {
				$pdf->setFont('FiraSans-SemiBold', '', 16);
			} else {
				$pdf->setFont('FiraSans-SemiBold', '', 20);
			}
			$pdf->Cell($cell_width, 24, $line['name'], 0, 2, 'L');
			$pdf->setFont('FiraSans-Regular', '', $card['club_font_size']);
			$pdf->MultiCell($cell_width, round($card['club_font_size'] * 1.33), $line['club_line'], 0, 'L');

			// bar
			$pdf->SetXY($card['margin'] + $left, $top + $card['height'] - $card['margin'] - 20 - $card['bar_height']);
			$pdf->setFont('FiraSans-SemiBold', '', $card['bar_font_size']);
			$pdf->Cell($cell_width, 24, $line['federation_abbr'], 0, 2, 'R');
			$pdf->SetTextColor(255, 255, 255);
			$pdf->SetFillColor($line['colors']['red'], $line['colors']['green'], $line['colors']['blue']);
			if ($line['colors']['red'] + $line['colors']['green'] + $line['colors']['blue'] > 458) {
				$pdf->SetTextColor(0, 0, 0);
			}
			$y = $pdf->getY();
			if (!empty($line['graphic'])) {
				$line['group_line'] .= '  '; // move to left
			}
			$pdf->Cell($cell_width, $card['bar_height'], $line['group_line'], 0, 2, 'C', 1);
			if (!empty($line['zusaetzliche_ak'])) {
				$pdf->SetXY($pdf->getX(), $y);
				$pdf->Cell($cell_width, $card['bar_height'], 'U'.$line['zusaetzliche_ak'], 0, 2, 'R'); // 41
			}
			if ($line['volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, $card['bar_height'], ' ', 0, 2, 'R', 1);
			} elseif ($line['evtl_volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y + 14);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, $card['bar_height'] / 2, ' ', 0, 2, 'R', 1);
			}

			if (!empty($line['graphic'])) {
				$pdf->image($line['graphic']['filename'], $card['width']*($j+1) - $line['graphic']['width'] - $card['margin'], $top + $card['height'] - $card['margin'] - $card['image_size'], $line['graphic']['width'], $line['graphic']['height']);
			}
		}
		$i++;
	}
	$folder = wrap_setting('tmp_dir').'/schilder/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/teilnehmerschilder.pdf')) {
		unlink($folder.'/teilnehmerschilder.pdf');
	}
	$file['name'] = $folder.'/teilnehmerschilder.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Teilnehmerschilder.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
	wrap_send_file($file);
}	

/**
 * mf_tournaments_p_qrcode(https://r.schach.in/p/12345, 12345)
 */
function mf_tournaments_p_qrcode($id) {
	require_once wrap_setting('lib').'/phpqrcode/lib/full/qrlib.php';
	$folder = wrap_setting('tmp_dir').'/tournaments/qr-codes';
	wrap_mkdir($folder);
	$file = $folder.'/'.$id.'.png';
	if (file_exists($file)) return $file;
	$url = sprintf('https://r.schach.in/p/%d', $id);

	QRcode::png($url, $file, QR_ECLEVEL_L, 4, 0);
	// resize a bit to make it not blurry in PDF
	$command = 'convert -scale 300x300 %s %s';
	exec(sprintf($command, $file, $file));
	return $file;
}
