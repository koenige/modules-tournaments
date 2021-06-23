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
	
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

	$pdf = new TFPDF('P', 'pt', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->open();
	$pdf->setCompression(true);
	// Fira Sans!
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$vorlagen = $zz_setting['media_folder'].'/urkunden-grafiken';

	$i = 0;
	foreach ($ops['output']['rows'] as $line) {
		// ignoriere Orga vorab
		if (in_array($line[$nos['usergroup_id']]['text'],
			['Landesverband: Organisator', 'Verein: Organisator', 'Bewerber'])
		) continue;

		// Daten anpassen
		$new = mf_tournaments_export_pdf_teilnehmerschilder_prepare($line, $nos);

		// PDF setzen
		$row = $i % 4;
		if (!$row) $pdf->addPage();
		$top = 198.5 * $row;
		for ($j = 0; $j < 2; $j++) {
			// DSJ-Logo
			$left = 297.5 * $j;
			$pdf->image($vorlagen.'/DSJ-Logo.jpg', 297.5*($j+1) - 78, 20 + $top, 58, 48);
			$pdf->setFont('FiraSans-Regular', '', 11);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetXY(20 + $left, 20 + $top);
			$pdf->Cell(257, 14, $event['main_series_long'], 0, 2, 'L');
			$pdf->Cell(257, 14, $event['turnierort'].' '.$event['year'], 0, 2, 'L');
			$pdf->SetXY(20 + $left, $pdf->GetY() + 40);
			if (strlen($new['spieler']) > 26) {
				$pdf->setFont('FiraSans-SemiBold', '', 17);
			} else {
				$pdf->setFont('FiraSans-SemiBold', '', 18);
			}
			$pdf->Cell(257, 24, $new['spieler'], 0, 2, 'L');
			$pdf->setFont('FiraSans-Regular', '', 12);
			$pdf->MultiCell(257, 16, $new['verein'], 0, 'L');

			$pdf->SetXY(20 + $left, $top + 136);
			$pdf->setFont('FiraSans-SemiBold', '', 18);
			$pdf->Cell(257, 24, $new['lv'], 0, 2, 'R');
			$pdf->SetTextColor(255, 255, 255);
			$pdf->SetFillColor($new['red'], $new['green'], $new['blue']);
			if ($new['red'] + $new['green'] + $new['blue'] > 458) {
				$pdf->SetTextColor(0, 0, 0);
			}
			$y = $pdf->getY();
			if (!empty($new['filename'])) {
				$new['usergroup'] .= '  ';
			}
			$pdf->Cell(257, 28, $new['usergroup'], 0, 2, 'C', 1);
			if (!empty($new['zusaetzliche_ak'])) {
				$pdf->SetXY($pdf->getX(), $y);
				$pdf->Cell(257, 28, 'U'.$new['zusaetzliche_ak'], 0, 2, 'R'); // 41
			}
			if (array_key_exists('volljaehrig', $nos) AND !empty($line[$nos['volljaehrig']]['text'])) {
				$pdf->SetXY($pdf->getX() + 5, $y);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, 28, ' ', 0, 2, 'R', 1);
			} elseif (array_key_exists('evtl_volljaehrig', $nos) AND !empty($line[$nos['evtl_volljaehrig']]['text'])) {
				$pdf->SetXY($pdf->getX() + 5, $y + 14);
				$pdf->SetFillColor(255, 255, 255);
				$pdf->Cell(5, 14, ' ', 0, 2, 'R', 1);
			}

			if (!empty($new['filename'])) {
				$pdf->image($new['filename'], 297.5*($j+1) - $new['width'] - 24, 110 + $top, $new['width'], $new['height']);
			}
		}
		$i++;
	}
	$folder = $zz_setting['cache_dir'].'/schilder/'.$event['identifier'];
	wrap_mkdir($folder);
	if (file_exists($folder.'/teilnehmerschilder.pdf')) {
		unlink($folder.'/teilnehmerschilder.pdf');
	}
	$file['name'] = $folder.'/teilnehmerschilder.pdf';
	$file['send_as'] = $event['year'].' '.$event['series_short'].' Teilnehmerschilder.pdf';
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
function mf_tournaments_export_pdf_teilnehmerschilder_nos($head) {
	$fields = [
		'usergroup_id', 'parameters', 't_vorname', 't_nachname', 'person_id',
		't_fidetitel', 't_verein', 'event_id', 'landesverband_org_id',
		'lebensalter', 'rolle', 't_dwz', 't_elo', 'geschlecht', 'volljaehrig',
		'evtl_volljaehrig'
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
	$new['spieler'] = ($new['fidetitel'] ? $new['fidetitel'].' ' : '')
		.((!empty($nos['t_vorname']) AND !empty($line[$nos['t_vorname']]['text']))
		? $line[$nos['t_vorname']]['text'].' '.$line[$nos['t_nachname']]['text']
		: $line[$nos['person_id']]['text']);

	// Verein
	$new['verein'] = (!empty($nos['t_verein']) ? $line[$nos['t_verein']]['text'] : '');
	if (function_exists('my_verein_saeubern')) {
		$new['verein'] = my_verein_saeubern($new['verein']);
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

	if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text']) AND $new['verein']) {
		$new['usergroup'] = $line[$nos['rolle']]['text'];
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Betreuer', 'Mitreisende'])) {
		if (empty($new['verein']) AND !empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['verein'] = $line[$nos['rolle']]['text'];
		}
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Spieler'])) {
		$new['usergroup'] = $line[$nos['event_id']]['text'];
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Gast'])) {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['usergroup'] = $line[$nos['rolle']]['text'];
		}
	} elseif (in_array($line[$nos['usergroup_id']]['text'], ['Schiedsrichter'])) {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['verein'] = $line[$nos['rolle']]['text'];
		}
	} else {
		if (!empty($nos['rolle']) AND !empty($line[$nos['rolle']]['text'])) {
			$new['verein'] = $line[$nos['rolle']]['text'];
		} else {
			$new['verein'] = $line['usergroup'];
		}
		$new['usergroup'] = 'Organisationsteam';
	}
	$new['lv'] = !empty($nos['landesverband_org_id']) ? $line[$nos['landesverband_org_id']]['text'] : '';
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
		$new['red'] = hexdec(substr($color, 1, 2));
		$new['green'] = hexdec(substr($color, 3, 2));
		$new['blue'] = hexdec(substr($color, 5, 2));
	} else {
		$new['red'] = 204;
		$new['green'] = 0;
		$new['blue'] = 0;
	}
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
