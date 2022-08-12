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


function mod_tournaments_teampdfs($vars) {
	global $zz_setting;
	
	$event_rights = 'event:'.$vars[0].'/'.$vars[1];
	if (!brick_access_rights(['Webmaster', 'Vorstand', 'AK Spielbetrieb', 'Geschäftsstelle'])
		AND !brick_access_rights(['Schiedsrichter', 'Organisator', 'Turnierleitung'], $event_rights)
	) {
		wrap_quit(403);
	}

	if ($vars[count($vars)-1] === 'meldeboegen.pdf') {
		// brauchen wir nicht
		array_pop($vars);
	}
	if (count($vars) === 3) {
		$team = implode('/', $vars);
		array_pop($vars);
		$event = implode('/', $vars);
	} elseif (count($vars) === 2) {
		$team = false;
		$event = implode('/', $vars);
	} else {
		return false;
	}

	$sql = 'SELECT event_id, event
			, CONCAT(date_begin, IFNULL(CONCAT("/", date_end), "")) AS duration
			, hinweis_meldebogen
			, events.identifier AS event_identifier
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS dateiname
			, DATEDIFF(date_end, date_begin) AS dauer_tage
			, IF(gastspieler = "ja", 1, NULL) AS gastspieler_status
			, bretter_min, bretter_max
			, pseudo_dwz
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, IF(tournaments.zimmerbuchung = "ja", 1, NULL) AS zimmerbuchung
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		WHERE events.identifier = "%s"';
	$sql = sprintf($sql, wrap_db_escape($event));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
	$event = array_merge($event, my_event_accounts($event['event_id']));

	// teams
	$sql = 'SELECT team_id, team, team_no, club_contact_id
			, teams.identifier AS team_identifier
			, meldung_datum
			, datum_anreise, TIME_FORMAT(uhrzeit_anreise, "%%H:%%i") AS uhrzeit_anreise
			, datum_abreise, TIME_FORMAT(uhrzeit_abreise, "%%H:%%i") AS uhrzeit_abreise
			, IF(datum_anreise AND uhrzeit_anreise AND datum_abreise AND uhrzeit_abreise, 1, NULL) AS reisedaten_komplett
			, meldung
		FROM teams
		WHERE event_id = %d
		AND spielfrei = "nein"
		AND team_status = "Teilnehmer"
		%s
		ORDER BY teams.identifier
	';
	$sql = sprintf($sql
		, $event['event_id']
		, $team ? sprintf(' AND teams.identifier = "%s"', wrap_db_escape($team)) : ''
	);
	$event['teams'] = wrap_db_fetch($sql, 'team_id');
	$event['teams'] = mf_tournaments_clubs_to_federations($event['teams']);

	$team_verein = [];
	foreach ($event['teams'] as $id => $team) {
		$team_verein[$id] = $team['club_contact_id'];
	}

	$teilnehmer = mf_tournaments_team_participants($team_verein, $event);
	$kosten = mf_tournaments_team_bookings(array_keys($event['teams']), $event);

	$pdf_uploads = false;
	foreach (array_keys($event['teams']) as $id) {
		if (!empty($teilnehmer[$id])) {
			$event['teams'][$id] = array_merge($event['teams'][$id], $teilnehmer[$id]);
		} else {
			$event['teams'][$id]['spieler'] = [];
		}
		if (!empty($kosten[$id])) {
			$event['teams'][$id] = array_merge($event['teams'][$id], $kosten[$id]);
		} else {
			$event['teams'][$id]['kosten'] = [];
		}
		$event['teams'][$id]['komplett'] = mf_tournaments_team_application_complete($event['teams'][$id]);
		$filename = sprintf('%s/meldeboegen/%s%%s.pdf', $zz_setting['media_folder'], $event['teams'][$id]['team_identifier']);
		$filenames = [
			sprintf($filename, ''),
			sprintf($filename, '-ehrenkodex'),
			sprintf($filename, '-gast')
		];
		foreach ($filenames as $filename) {
			if (file_exists($filename)) {
				$event['teams'][$id]['pdf'][] = $filename;
				$pdf_uploads = true;
			}
		}
	}

	if (!$pdf_uploads) return my_team_pdf($event);
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
