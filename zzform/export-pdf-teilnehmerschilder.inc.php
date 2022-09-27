<?php

/**
 * tournaments module
 * export participant signs as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2020, 2022 Gustaf Mossakowski
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
	global $zz_setting;
	wrap_include_files('pdf', 'tournaments');

	$ids = [];
	foreach ($ops['output']['rows'] as $line) {
		$ids[] = $line['id_value'];
	}
	$sql = 'SELECT participation_id, event_id
		FROM participations
		LEFT JOIN usergroups USING (usergroup_id)
		WHERE participation_id IN (%s)';
	$sql = sprintf($sql, implode(',', $ids));
	$data = wrap_db_fetch($sql, 'participation_id');

	// get event
	$line = reset($data);
	$event = my_event($line['event_id']);
	if ($event['series_parameter']) {
		parse_str($event['series_parameter'], $event['series_parameter']);
		$event += $event['series_parameter'];
	}
	if (empty($event['name_tag_size'])) $event['name_tag_size'] = '10.5x7';
	
	// extra form fields?
	$sql = 'SELECT formfield_id, formfield, parameters, formfield_category_id
			, IFNULL(registrationvarchar, registrationtext) AS text
			, participation_id
		FROM formfields
	    LEFT JOIN forms USING (form_id)
	    LEFT JOIN registrationvarchars USING (formfield_id)
	    LEFT JOIN registrationtexts USING (formfield_id)
	    LEFT JOIN anmeldungen
	    	ON IFNULL(registrationvarchars.anmeldung_id, registrationtexts.anmeldung_id) = anmeldungen.anmeldung_id
	    WHERE forms.event_id = %d
	    AND parameters LIKE "%%&name_tag=%%"
	';
	$sql = sprintf($sql, $event['event_id']);
	$formfields = wrap_db_fetch($sql, ['participation_id', 'formfield_id']);

	switch ($event['name_tag_size']) {
	case '9x5.5':
		$card['width'] = 255.12;
		$card['height'] = 155.9;
		$card['rows'] = 5;
		$card['margin'] = 12;
		$card['image_size'] = 54;
		$card['logo_height'] = 28;
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

	// @deprecated	
	// Feld-IDs raussuchen
	$nos = mf_tournaments_export_pdf_teilnehmerschilder_nos($ops['output']['head']);
	// get data
	$data = [];
	foreach ($ops['output']['rows'] as $line) {
		// ignoriere Orga vorab
		if (in_array($line[$nos['usergroup_id']]['text'],
			['Landesverband: Organisator', 'Verein: Organisator', 'Bewerber'])
		) continue;
		// Daten anpassen
		$new = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos, $card);
		$data[$line['id_value']] = $new;
	}
	if (!$data) wrap_quit(404, 'Es gibt keine Teilnehmerschilder für diese Personen.');
	foreach ($data as $participation_id => $line) {
		$line['colors'] = mf_tournaments_pdf_colors($line['parameters'], $line['role']);
		$line['zusaetzliche_ak'] = mf_tournaments_pdf_agegroups($line['parameters'], $line['age']);
		$line['group_line'] = mf_tournaments_pdf_group_line($line);
		$line['club_line'] = mf_tournaments_pdf_club_line($line);
		$line['graphic'] = mf_tournaments_pdf_graphic([$line['role'], $line['usergroup']], $card);

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
	
	// read title from FIDE database if person in German database is only passive
	// @todo read women’s title as well and check which one is higher
	$sql = 'SELECT participation_id
			, IF(IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) > 18, 1,
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_begin, 1, NULL)
			) AS volljaehrig
			, IF(SUBSTRING(date_of_birth, 5, 6) = "-00-00" AND IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) = 18, 1, 
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_end AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) >= events.date_begin, 1, NULL)
			) AS evtl_volljaehrig
			, IFNULL(dwz_spieler.FIDE_Titel, fide_players.title) AS FIDE_Titel
		FROM participations
		LEFT JOIN events USING (event_id)
		LEFT JOIN persons USING (person_id)
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = persons.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		LEFT JOIN dwz_spieler
			ON contacts_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
		LEFT JOIN contacts_identifiers fide
			ON fide.contact_id = persons.contact_id
			AND fide.current = "yes"
			AND fide.identifier_category_id = %d
		LEFT JOIN fide_players
			ON fide_players.player_id = fide.identifier
		WHERE participation_id IN (%s)';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, wrap_category_id('identifiers/fide-id')
		, implode(',', array_keys($data))
	);
	$more_data = wrap_db_fetch($sql, 'participation_id');
	foreach ($more_data as $id => $line) {
		$data[$id] += $more_data[$id];
		if (empty($data[$id]['fidetitel']) AND !empty($data[$id]['FIDE_Titel']))
			$data[$id]['name'] = $data[$id]['FIDE_Titel'].' '.$data[$id]['name'];
	}

	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

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
			} elseif (!empty($line['federation_abbr']) AND $event['event_category'] === 'mannschaft') {
				// @todo better do this via parameters and not event_category
				$logo['filename'] = sprintf('%s/flaggen/%s.png', $zz_setting['media_folder'], wrap_filename($line['federation_abbr']));
				$logo['border'] = true;
			}
			mf_tournaments_pdf_logo($pdf, $logo, $card);
			$pdf->SetLineWidth(0.25);

			// event
			$pdf->setFont('FiraSans-Regular', '', $card['event_font_size']);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($left + $card['width']/2, $card['margin'] + $top);
			$pdf->MultiCell(125, round($card['event_font_size'] * 1.2), $event['main_series_long']."\n".$event['turnierort'].' '.$event['year'], 0, 'L');
			
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
	$folder = $zz_setting['tmp_dir'].'/schilder/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/teilnehmerschilder.pdf')) {
		unlink($folder.'/teilnehmerschilder.pdf');
	}
	$file['name'] = $folder.'/teilnehmerschilder.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Teilnehmerschilder.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
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
function mf_tournaments_export_pdf_teilnehmerschilder_nos($head) {
	$fields = [
		'usergroup_id', 'parameters', 't_vorname', 't_nachname', 'person_id',
		't_fidetitel', 't_verein', 'event_id', 'federation_contact_id',
		'lebensalter', 'rolle', 't_dwz', 't_elo', 'sex', 'club_contact_id',
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
	$new['role'] = (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) ? $line[$nos['rolle']]['text'] : '';
	$new['sex'] = !empty($nos['sex']) ? $line[$nos['sex']]['text'] : '';
	$new['event'] = $line[$nos['event_id']]['text'];
	$new['federation_abbr'] = !empty($nos['federation_contact_id']) ? $line[$nos['federation_contact_id']]['text'] : '';
	$new['club_contact_id'] = !empty($nos['club_contact_id']) ? $line[$nos['club_contact_id']]['value'] : '';
	$new['age'] = !empty($nos['lebensalter']) ? $line[$nos['lebensalter']]['value'] : '';

	return $new;
}

/**
 * mf_tournaments_p_qrcode(https://r.schach.in/p/12345, 12345)
 */
function mf_tournaments_p_qrcode($id) {
	global $zz_setting;
	global $zz_conf;
	require_once $zz_setting['lib'].'/phpqrcode/lib/full/qrlib.php';
	$folder = $zz_setting['tmp_dir'].'/tournaments/qr-codes';
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
