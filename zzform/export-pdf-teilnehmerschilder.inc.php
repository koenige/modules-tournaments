<?php

/**
 * tournaments module
 * export participant signs as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2020 Gustaf Mossakowski
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
	global $zz_conf;
	$event = $zz_conf['event'];
	
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
		$new = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos);
		$data[$line['id_value']] = $new;
	}
	
	$sql = 'SELECT teilnahme_id
			, IF(IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) > 18, 1,
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_begin, 1, NULL)
			) AS volljaehrig
			, IF(SUBSTRING(date_of_birth, 5, 6) = "-00-00" AND IFNULL(events.event_year, YEAR(events.date_begin)) - YEAR(date_of_birth) = 18, 1, 
				IF(SUBSTRING(date_of_birth, 5, 6) != "-00-00" AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) <= events.date_end AND DATE_ADD(date_of_birth, INTERVAL 18 YEAR) >= events.date_begin, 1, NULL)
			) AS evtl_volljaehrig
			, dwz_spieler.FIDE_Titel
		FROM teilnahmen
		LEFT JOIN events USING (event_id)
		LEFT JOIN personen USING (person_id)
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = personen.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		LEFT JOIN dwz_spieler
			ON contacts_identifiers.identifier = CONCAT(dwz_spieler.ZPS, "-", dwz_spieler.Mgl_Nr)
		WHERE teilnahme_id IN (%s)';
	$sql = sprintf($sql
		, wrap_category_id('kennungen/zps')
		, implode(',', array_keys($data))
	);
	$more_data = wrap_db_fetch($sql, 'teilnahme_id');
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

	$i = 0;
	foreach ($data as $line) {
		// PDF setzen
		$row = $i % 4;
		if (!$row) $pdf->addPage();
		$top = 198.5 * $row;
		for ($j = 0; $j < 2; $j++) {
			// DSJ-Logo
			$left = 297.5 * $j;
			if ($j & 1) {
				$image = mf_tournaments_p_qrcode($line['teilnahme_id']);
				$width = 48;
			} else {
				$image = $zz_setting['media_folder'].'/urkunden-grafiken/DSJ-Logo.jpg';
				$width = 58;
			}
			$pdf->image($image, 297.5*($j+1) - 20 - $width, 20 + $top, $width, 48);
			$pdf->setFont('FiraSans-Regular', '', 11);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(20 + $left, 20 + $top);
			$pdf->Cell(257, 14, $event['main_series_long'], 0, 2, 'L');
			$pdf->Cell(257, 14, $event['turnierort'].' '.$event['year'], 0, 2, 'L');
			$pdf->SetXY(20 + $left, $pdf->GetY() + 40);
			if (strlen($line['name']) > 26) {
				$pdf->setFont('FiraSans-SemiBold', '', 17);
			} else {
				$pdf->setFont('FiraSans-SemiBold', '', 18);
			}
			$pdf->Cell(257, 24, $line['name'], 0, 2, 'L');
			$pdf->setFont('FiraSans-Regular', '', 12);
			$pdf->MultiCell(257, 16, $line['club'], 0, 'L');

			$pdf->SetXY(20 + $left, $top + 136);
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$pdf->Cell(257, 24, $line['federation_abbr'], 0, 2, 'R');
			$pdf->SetTextColor(255, 255, 255);
			$pdf->SetFillColor($line['colors']['red'], $line['colors']['green'], $line['colors']['blue']);
			if ($line['colors']['red'] + $line['colors']['green'] + $line['colors']['blue'] > 458) {
				$pdf->SetTextColor(0, 0, 0);
			}
			$y = $pdf->getY();
			if (!empty($line['filename'])) {
				$line['usergroup'] .= '  ';
			}
			$pdf->Cell(257, 28, $line['usergroup'], 0, 2, 'C', 1);
			if (!empty($line['zusaetzliche_ak'])) {
				$pdf->SetXY($pdf->getX(), $y);
				$pdf->Cell(257, 28, 'U'.$line['zusaetzliche_ak'], 0, 2, 'R'); // 41
			}
			if ($line['volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, 28, ' ', 0, 2, 'R', 1);
			} elseif ($line['evtl_volljaehrig']) {
				$pdf->SetXY($pdf->getX() + 5, $y + 14);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, 14, ' ', 0, 2, 'R', 1);
			}

			if (!empty($line['filename'])) {
				$pdf->image($line['filename'], 297.5*($j+1) - $line['width'] - 24, 110 + $top, $line['width'], $line['height']);
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
		'lebensalter', 'rolle', 't_dwz', 't_elo', 'geschlecht'
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
	if (function_exists('my_verein_saeubern')) {
		$new['club'] = my_verein_saeubern($new['club']);
	}

	// Gruppe
	$filename = sprintf('%s/gruppen/%s.png', $zz_setting['media_folder'], wrap_filename($line[$nos['usergroup_id']]['text']));
	if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
		$filename_2 = sprintf('%s/gruppen/%s.png', $zz_setting['media_folder'], wrap_filename($line[$nos['rolle']]['text']));
		if (file_exists($filename_2)) $filename = $filename_2;
	}
	$new['usergroup'] = $line[$nos['usergroup_id']]['text'];
	if (!empty($nos['geschlecht'])) {
		if (!empty($parameters['weiblich']) AND  $line[$nos['geschlecht']]['text'] === 'weiblich') {
			$new['usergroup'] = $parameters['weiblich'];
		} elseif (!empty($parameters['männlich']) AND  $line[$nos['geschlecht']]['text'] === 'männlich') {
			$new['usergroup'] = $parameters['männlich'];
		}
	}

	if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text']) AND $new['club']) {
		$new['usergroup'] = $line[$nos['rolle']]['text'];
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Betreuer', 'Mitreisende'])) {
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
		$new['width'] = floor($size[0]/$size[1]*72);
		$new['height'] = 72;
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
