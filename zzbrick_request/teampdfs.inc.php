<?php

/**
 * tournaments module
 * Ausgabe aller Meldeformulare zu einer Meisterschaft als PDF
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2013-2014, 2017-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_teampdfs($vars, $settings) {
	global $zz_setting;
	require_once __DIR__.'/../tournaments/pdf.inc.php';
	
	if (count($vars) === 3) {
		$team_identifier = implode('/', $vars);
		array_pop($vars);
	} elseif (count($vars) === 2) {
		$team_identifier = false;
	} else {
		return false;
	}

	$event = mf_tournaments_pdf_event($vars);
	if (!$event) return false;

	$params = [
		'team_identifier' => $team_identifier,
		'bookings' => true,
		'check_completion' => true,
		'check_uploads' => !empty($settings['no_uploads']) ? false : true
	];
	$event['teams'] = mf_tournaments_pdf_teams($event, $params);

	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	if (empty($event['teams']['pdf_uploads'])) return my_team_pdf($event);

	unset($event['teams']['pdf_uploads']);
	$pdfs = [];
	foreach ($event['teams'] as $id => $team) {
		if (!empty($team['pdf'])) {
			$pdfs = array_merge($pdfs, $team['pdf']);
			continue;
		}
		$my_event = $event;
		$my_event['teams'] = [$id => $team];
		$pdfs[] = my_team_pdf($my_event, 'filename');
	}
	$folder = $zz_setting['tmp_dir'].'/team-meldungen';
	$turnier_folder = dirname($folder.'/'.$event['event_identifier']);
	$file['name'] = $folder.'/'.$event['dateiname'].'-meldebogen.pdf';
	$file['send_as'] = 'Meldebögen '.$event['event'].'.pdf';
	$command = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile="%s" "%s"';
	$command = sprintf($command, $file['name'], implode('" "', $pdfs));
	exec($command);
	if (!file_exists($file['name'])) {
		wrap_error(sprintf('PDF für %s konnte nicht erstellt werden.', $event['event']), E_USER_ERROR);
	}
	wrap_file_send($file);
	exit;
}
