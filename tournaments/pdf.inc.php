<?php 

/**
 * tournaments module
 * common functions for PDFs
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * prepare PDF and some settings
 *
 * @param object $pdf
 * @param array $event
 * @return void
 */
function mf_tournaments_pdf_prepare($event) {
	wrap_lib('tfpdf');
	wrap_page_format_files();

	$settings = [];
	switch ($event['turnierform']) {
	case 'm-a':
		$settings['text_chair'] = 'Vorsitzende/r Verband';
		$settings['text_in_org'] = 'im Verband';
		$settings['show_federation'] = false;
		break;
	case 'm-v':
		$settings['text_chair'] = 'Vereinsvorsitzende/r';
		$settings['text_in_org'] = 'im Verein';
		$settings['show_federation'] = true;
		break;
	case 'm-s':
		$settings['text_chair'] = 'Rektor/in der Schule';
		$settings['text_in_org'] = 'in der Schule';
		$settings['show_federation'] = true;
		break;
	}

	$settings['logo_filename'] = wrap_setting('media_folder').'/logos/DSJ Logo Text schwarz-gelb.png';
	$settings['logo_width'] = 146;
	$settings['logo_height'] = 50;

	$pdf = new TFPDF('P', 'pt', 'A4');
	//$pdf->setCompression(true);           // Activate compression.

	$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
	$pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
	$pdf->AddFont('DejaVu', 'I', 'DejaVuSansCondensed-Oblique.ttf', true);
	
	$pdf->SetLineWidth(0.15);
	$pdf->SetFillColor(230, 230, 230);

	$settings['page_height'] = $pdf->GetPageHeight();

	return [$pdf, $settings];
}

/**
 * write PDF to filesystem and send it to user
 *
 * @param object $pdf
 * @param array $event
 * @param string $return (optional)
 * @return void
 */
function mf_tournaments_pdf_send($pdf, $event, $return = 'send') {
	$folder = wrap_setting('tmp_dir').'/team-meldungen';
	if (count($event['teams']) === 1) {
		$team = reset($event['teams']);
		$tournament_folder = dirname($folder.'/'.$team['team_identifier']);
		$file['name'] = $folder.'/'.$team['team_identifier'].'-'.$event['filename_suffix'].'.pdf';
		$file['send_as'] = $event['send_as_singular'].' '.$event['event'].' '.$team['team'].'.pdf';
	} else {
		$tournament_folder = dirname($folder.'/'.$event['event_identifier']);
		$file['name'] = $tournament_folder.'/'.$event['event_idf'].'/'.$event['filename_suffix'].'.pdf';
		$file['send_as'] = $event['send_as_plural'].' '.$event['event'].'.pdf';
	}
	wrap_mkdir(dirname($file['name']));
	
	$file['caching'] = false;
	$file['etag_generate_md5'] = true;
	$pdf->output('F', $file['name'], true);
	if ($return === 'filename') return $file['name'];
	wrap_file_send($file);
}

/**
 * fügt Hintergrundfarbe unter einzeilige Zellen, wenn nebenan eine
 * mehrzeilige Zelle vorhanden ist
 * 
 * @param object $pdf
 * @param int $y_bottom
 * @param int $width
 * @return void
 */
function mf_tournaments_pdf_add_bg_color(&$pdf, $y_bottom, $width, $height) {
	if ($pdf->GetY() >= $y_bottom) return;
	$current_x = $pdf->GetX();
	$current_y = $pdf->GetY();
	$pdf->SetXY($current_x - $width, $current_y + $height);
	$pdf->Cell($width, $y_bottom - $pdf->GetY(), '', '', 0, 'R', 1);
	$pdf->SetXY($current_x, $current_y);
}

/**
 * add logo to PDF
 *
 * @param object $pdf
 * @param array $logo
 * @param array $card
 */
function mf_tournaments_pdf_logo($pdf, $logo, $card) {
	$logo['border'] = $logo['border'] ?? false;
	$logo['filename'] = $logo['filename'] ?? false;
	$logo['height_factor'] = $logo['height_factor'] ?? 1;
	$logo['width_factor'] = $logo['width_factor'] ?? 1;
	$logo['height'] = $card['logo_height'] * $logo['height_factor'];

	if ($logo['filename'] AND !file_exists($logo['filename'])) {
		$logo['filename'] = false;
		$logo['border'] = false;
	}
	if (!$logo['filename'])
		// @todo = $settings['logo_filename']
		$logo['filename'] = wrap_setting('media_folder').'/logos/DSJ Logo Text schwarz-gelb.png';

	$logo['size'] = getimagesize($logo['filename']);
	$logo['width'] = round($logo['size'][0] / $logo['size'][1] * $card['logo_height'] * $logo['width_factor']);
	$logo['left'] = $logo['left'] + $card['width'] / 2 - $card['margin'] - $logo['width'];

	if ($logo['border']) {
		$pdf->SetLineWidth(0.6);
		$pdf->SetXY($logo['left'], $logo['top']);
		$pdf->Cell($logo['width'], $logo['height'], '', 1);
	}
	$pdf->image($logo['filename'], $logo['left'], $logo['top'], $logo['width'], $logo['height']);
}

/**
 * get RGB colours from hex colours
 *
 * @param array $parameters
 * @param string $role
 * @return array
 */
function mf_tournaments_pdf_colors($parameters, $role) {
	if (empty($parameters['color']))
		$color = '#CC0000';
	else {
		$color = '';
		if (is_array($parameters['color'])) {
			if ($role) {
				foreach ($parameters['color'] as $index => $my_color) {
					if ($index AND strstr($role, $index)) $color = $my_color;
				}
			}
			if (!$color) $color = $parameters['color'][0];
		} else {
			$color = $parameters['color'];
		}
	}
	return mf_tournaments_colors_hex2dec($color);
}

/**
 * mark with extra age group
 *
 * @param array $parameters
 * @param int $age
 * @return string
 */
function mf_tournaments_pdf_agegroups($parameters, $age) {
	$text = '';
	if (empty($parameters['aks'])) return $text;
	foreach ($parameters['aks'] as $ak) {
		if ($ak >= $age) {
			$text = $ak;
			break;
		}
	}
	return $text;
}

/**
 * check if there is a graphic for a usergroup or a role available
 *
 * @param array $graphics possible filenames
 * @param array $card
 * @return array
 */
function mf_tournaments_pdf_graphic($graphics, $card) {
	$filename = false;
	foreach ($graphics as $graphic) {
		if (!$graphic) continue;
		$filename = sprintf('%s/gruppen/%s.png', wrap_setting('media_folder'), wrap_filename($graphic));
		if (file_exists($filename)) break;
		$filename = false;
	}
	if (!$filename) return [];

	$graphic = [];
	$graphic['filename'] = $filename;
	$size = getimagesize($graphic['filename']);
	$graphic['width'] = floor($size[0] / $size[1] * $card['image_size']);
	$graphic['height'] = $card['image_size'];
	return $graphic;
}

/**
 * get group line for PDFs
 *
 * @param array $line keys parameters, usergroup, club, role, sex
 * @return string
 */
function mf_tournaments_pdf_group_line($line) {
	if (!empty($line['parameters']['pdf_group_line'])
		AND array_key_exists($line['parameters']['pdf_group_line'], $line)
		AND !empty($line[$line['parameters']['pdf_group_line']])) {
		// e. g. pdf_group_line=event, pdf_group_line=role
		return $line[$line['parameters']['pdf_group_line']];
	} elseif ($line['role'] AND $line['club']) {
		// club is filled out, put role into group_line
		return $line['role'];
	} elseif ($line['sex']) {
		// female or male forms?
		if (!empty($line['parameters']['weiblich']) AND $line['sex'] === 'female') {
			return $line['parameters']['weiblich'];
		} elseif (!empty($line['parameters']['female']) AND $line['sex'] === 'female') {
			return $line['parameters']['female'];
		} elseif (!empty($line['parameters']['männlich']) AND $line['sex'] === 'male') {
			return $line['parameters']['männlich'];
		} elseif (!empty($line['parameters']['male']) AND $line['sex'] === 'male') {
			return $line['parameters']['male'];
		}
	}
	return $line['usergroup'];
}

/**
 * get club line for PDFs
 * role overwrites club, sometimes usergroup overwrites club
 *
 * @param array $line keys parameters, usergroup, club, role, sex
 * @return string
 */
function mf_tournaments_pdf_club_line($line) {
	if ($line['club']) return $line['club'];
	if (!empty($line['parameters']['pdf_group_line'])) {
		switch ($line['parameters']['pdf_group_line']) {
		case 'role':
			 // do not show role twice
			$line['role'] = false;
			break;
		case 'usergroup_category':
			if (!$line['role']) $line['role'] = $line['usergroup'];
			break;
		}
	}
	if ($line['role']) return $line['role'];
	return '';
}
