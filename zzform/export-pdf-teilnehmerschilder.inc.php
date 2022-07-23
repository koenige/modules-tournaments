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
		$name_tag['width'] = 255.12;
		$name_tag['height'] = 155.9;
		$name_tag['rows'] = 5;
		$name_tag['margin'] = 12;
		$name_tag['image_size'] = 54;
		$name_tag['logo_height'] = 32;
		$name_tag['bar_height'] = 22;
		$name_tag['bar_font_size'] = 14;
		$name_tag['event_font_size'] = 10;
		$name_tag['club_font_size'] = 10;
		break;
	default:
	case '10.5x7':
		$name_tag['width'] = 297.5;
		$name_tag['height'] = 198.5;
		$name_tag['rows'] = 4;
		$name_tag['margin'] = 20;
		$name_tag['image_size'] = 68;
		$name_tag['logo_height'] = 40;
		$name_tag['bar_height'] = 28;
		$name_tag['bar_font_size'] = 18;
		$name_tag['event_font_size'] = 11;
		$name_tag['club_font_size'] = 12;
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
		$new = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos, $name_tag);
		if (array_key_exists($line['id_value'], $formfields)) {
			foreach ($formfields[$line['id_value']] as $formfield) {
				if (!$formfield['text']) continue;
				parse_str($formfield['parameters'], $formfield['parameters']);
				if (empty($new[$formfield['parameters']['name_tag']]))
					$new[$formfield['parameters']['name_tag']] = '';
				else
					$new[$formfield['parameters']['name_tag']] .= "\n";
				$new[$formfield['parameters']['name_tag']] .= $formfield['formfield'].': '.str_replace("\n", ", ", $formfield['text']);
			}
		}
		$data[$line['id_value']] = $new;
	}
	if (!$data) wrap_quit(404, 'Es gibt keine Teilnehmerschilder für diese Personen.');
	
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
	
	$logo['filename'] = $zz_setting['media_folder'].'/urkunden-grafiken/DSJ-Logo.jpg';
	$logo['filename'] = $zz_setting['media_folder'].'/logos/DSJ Logo Text schwarz-gelb.png';
	$logo['size'] = getimagesize($logo['filename']);
	$logo['width'] = round($logo['size'][0] / $logo['size'][1] * $name_tag['logo_height']);
	
	require_once $zz_setting['modules_dir'].'/tournaments/tournaments/functions.inc.php';
	$event['main_series_long'] = mf_tournaments_event_title_wrap($event['main_series_long']);
	
	$cell_width = $name_tag['width'] - 2 * $name_tag['margin'];

	$i = 0;
	foreach ($data as $line) {
		// PDF setzen
		$row = $i % $name_tag['rows'];
		if (!$row) {
			$pdf->addPage();
			$pos_x = $name_tag['width'];
			while($pos_x < 595) {
				$pdf->Line($pos_x, 0, $pos_x, 842);
				$pos_x += $name_tag['width'];
			}
		}
		$top = $name_tag['height'] * $row;
		$pdf->Line(0, $top + $name_tag['height'], 595, $top + $name_tag['height']);
		for ($j = 0; $j < 2; $j++) {
			// logo or QR code
			$left = $name_tag['width'] * $j;
			if ($j & 1) {
				$image = mf_tournaments_p_qrcode($line['participation_id']);
				$width = $name_tag['logo_height'] * 1.2;
				$height = $name_tag['logo_height'] * 1.2;
			} else {
				$image = $logo['filename'];
				$width = $logo['width'];
				$height = $name_tag['logo_height'];
			}
			$pdf->image($image, $name_tag['width']*($j+1) - $name_tag['margin'] - $width, $name_tag['margin'] + $top, $width, $height);

			// event
			$pdf->setFont('FiraSans-Regular', '', $name_tag['event_font_size']);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY($name_tag['margin'] + $left, $name_tag['margin'] + $top);
			$pdf->MultiCell(125, round($name_tag['event_font_size'] * 1.2), $event['main_series_long']."\n".$event['turnierort'].' '.$event['year'], 0, 'L');
			
			// name
			$pdf->SetXY($name_tag['margin'] + $left, $pdf->GetY() + $name_tag['margin'] * 1.2);
			if (strlen($line['name']) > 23) {
				$pdf->setFont('FiraSans-SemiBold', '', 16);
			} else {
				$pdf->setFont('FiraSans-SemiBold', '', 20);
			}
			$pdf->Cell($cell_width, 24, $line['name'], 0, 2, 'L');
			$pdf->setFont('FiraSans-Regular', '', $name_tag['club_font_size']);
			$pdf->MultiCell($cell_width, round($name_tag['club_font_size'] * 1.33), $line['club'], 0, 'L');

			// bar
			$pdf->SetXY($name_tag['margin'] + $left, $top + $name_tag['height'] - $name_tag['margin'] - 20 - $name_tag['bar_height']);
			$pdf->setFont('FiraSans-SemiBold', '', $name_tag['bar_font_size']);
			$pdf->Cell($cell_width, 24, $line['federation_abbr'], 0, 2, 'R');
			$pdf->SetTextColor(255, 255, 255);
			$pdf->SetFillColor($line['colors']['red'], $line['colors']['green'], $line['colors']['blue']);
			if ($line['colors']['red'] + $line['colors']['green'] + $line['colors']['blue'] > 458) {
				$pdf->SetTextColor(0, 0, 0);
			}
			$y = $pdf->getY();
			if (!empty($line['filename'])) {
				$line['usergroup'] .= '  '; // move to left
			}
			$pdf->Cell($cell_width, $name_tag['bar_height'], $line['usergroup'], 0, 2, 'C', 1);
			if (!empty($line['zusaetzliche_ak'])) {
				$pdf->SetXY($pdf->getX(), $y);
				$pdf->Cell($cell_width, $name_tag['bar_height'], 'U'.$line['zusaetzliche_ak'], 0, 2, 'R'); // 41
			}
			if ($line['volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, $name_tag['bar_height'], ' ', 0, 2, 'R', 1);
			} elseif ($line['evtl_volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y + 14);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, $name_tag['bar_height'] / 2, ' ', 0, 2, 'R', 1);
			}

			if (!empty($line['filename'])) {
				$pdf->image($line['filename'], $name_tag['width']*($j+1) - $line['width'] - $name_tag['margin'], $top + $name_tag['height'] - $name_tag['margin'] - $name_tag['image_size'], $line['width'], $line['height']);
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
		'lebensalter', 'rolle', 't_dwz', 't_elo', 'sex'
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
 * @param array $name_tag
 * @return array $line
 */
function mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos, $name_tag) {
	global $zz_setting;
	$filename = false;
	if (!empty($line[$nos['parameters']]['text'])) {
		parse_str($line[$nos['parameters']]['text'], $parameters);
	} else {
		$parameters = [];
	}

	// Spieler
	$new['fidetitel'] = !empty($nos['t_fidetitel']) ? $line[$nos['t_fidetitel']]['text'] : '';
	$new['name'] = ($new['fidetitel'] ? $new['fidetitel'].' ' : '')
		.((!empty($nos['t_vorname']) AND !empty($line[$nos['t_vorname']]['text']))
		? $line[$nos['t_vorname']]['text'].' '.$line[$nos['t_nachname']]['text']
		: $line[$nos['person_id']]['text']);

	// Verein
	$new['club'] = (!empty($nos['t_verein']) ? $line[$nos['t_verein']]['text'] : '');

	// Gruppe
	$filename = sprintf('%s/gruppen/%s.png', $zz_setting['media_folder'], wrap_filename($line[$nos['usergroup_id']]['text']));
	if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
		$filename_2 = sprintf('%s/gruppen/%s.png', $zz_setting['media_folder'], wrap_filename($line[$nos['rolle']]['text']));
		if (file_exists($filename_2)) $filename = $filename_2;
	}
	$new['usergroup'] = $line[$nos['usergroup_id']]['text'];
	if (!empty($nos['sex'])) {
		if (!empty($parameters['weiblich']) AND $line[$nos['sex']]['text'] === 'female') {
			$new['usergroup'] = $parameters['weiblich'];
		} elseif (!empty($parameters['männlich']) AND  $line[$nos['sex']]['text'] === 'male') {
			$new['usergroup'] = $parameters['männlich'];
		}
	}

	if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text']) AND $new['club']) {
		$new['usergroup'] = $line[$nos['rolle']]['text'];
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Betreuer', 'Mitreisende', 'Teilnehmer', 'Referent'])) {
		if (empty($new['club']) AND !empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['club'] = $line[$nos['rolle']]['text'];
		}
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Spieler'])) {
		$new['usergroup'] = $line[$nos['event_id']]['text'];
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Gast'])) {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['usergroup'] = $line[$nos['rolle']]['text'];
		}
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Schiedsrichter'])) {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['club'] = $line[$nos['rolle']]['text'];
		}
	} else {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['club'] = $line[$nos['rolle']]['text'];
		} else {
			$new['club'] = $line[$nos['usergroup_id']]['text'];
		}
		$new['usergroup'] = 'Organisationsteam';
	}
	$new['federation_abbr'] = !empty($nos['federation_contact_id']) ? $line[$nos['federation_contact_id']]['text'] : '';
	
	if (!empty($parameters['color'])) {
		$color = '';
		if (is_array($parameters['color'])) {
			$rolle = !empty($nos['rolle']) ? $line[$nos['rolle']]['text'] : '';
			if ($rolle) {
				foreach ($parameters['color'] as $index => $my_color) {
					if ($index AND strstr($rolle, $index)) $color = $my_color;
				}
			}
			if (!$color) $color = $parameters['color'][0];
		} else {
			$color = $parameters['color'];
		}
	} else {
		$color = '#CC0000';
	}
	$new['colors'] = mf_tournaments_colors_hex2dec($color);
	$new['zusaetzliche_ak'] = '';
	if (!empty($parameters['aks'])) {
		foreach ($parameters['aks'] as $ak) {
			if ($ak >= $line[$nos['lebensalter']]['text']) {
				$new['zusaetzliche_ak'] = $ak;
				break;
			}
		}
	}
	if ($filename AND file_exists($filename)) {
		$new['filename'] = $filename;
		$size = getimagesize($new['filename']);
		$new['width'] = floor($size[0] / $size[1] * $name_tag['image_size']);
		$new['height'] = $name_tag['image_size'];
	}
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
