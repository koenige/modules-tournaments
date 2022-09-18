<?php 

/**
 * tournaments module
 * player card
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ausgabe einer Spielerkarteikarte
 *
 * @param array $vars
 *	[0]: Jahr des Turniers
 *	[1]: Kennung des Turniers
 *  [2]: Startranglisten-Nr. des Spielers
 * @return array $page
 */
function mod_tournaments_player($vars) {
	global $zz_setting;
	if (count($vars) !== 3) return false;

	$sql = 'SELECT persons.person_id, participation_id
			, t_vorname, t_namenszusatz, t_nachname
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS name
			, t_verein, organisationen.identifier AS verein_kennung
			, t_dwz, t_elo, t_fidetitel
			, setzliste_no
			, events.event_id, event, IFNULL(events.event_year, YEAR(events.date_begin)) AS year
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
			, IFNULL(place, places.contact) AS turnierort
			, YEAR(date_of_birth) AS geburtsjahr
			, platz_no
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, contacts.identifier AS personen_kennung
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS turnier_kennung
			, (SELECT identifier FROM contacts_identifiers zps WHERE zps.contact_id = contacts.contact_id AND current = "yes" AND identifier_category_id = %d) AS zps_code
			, (SELECT identifier FROM contacts_identifiers fide WHERE fide.contact_id = contacts.contact_id AND current = "yes" AND identifier_category_id = %d) AS fide_id
			, livebretter
			, IF(DATE_SUB(events.date_end, INTERVAL 2 DAY) < CURDATE(), 1, NULL) AS einsendeschluss
			, IF(spielerphotos = "ja", 1, NULL) AS spielerphotos
			, IF(spielernachrichten = "ja", 1, NULL) AS spielernachrichten
			, events.identifier AS event_identifier
			, (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id) AS max_round_no
			, participations.club_contact_id
		FROM participations
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN persons USING (person_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN tabellenstaende
			ON tabellenstaende.person_id = participations.person_id
			AND participations.event_id = tabellenstaende.event_id
			AND tabellenstaende.runde_no = tournaments.tabellenstand_runde_no 
		LEFT JOIN contacts organisationen
			ON participations.club_contact_id = organisationen.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE setzliste_no = %d
		AND teilnahme_status = "Teilnehmer"
		AND events.identifier = "%d/%s"
	';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, wrap_category_id('identifiers/fide-id')
		, $vars[2], $vars[0], wrap_db_escape($vars[1])
	);
	$data = wrap_db_fetch($sql);
	if (!$data) return false;
	$data = mf_tournaments_clubs_to_federations($data);
	$data['fidetitel_lang'] = mf_tournaments_fide_title($data['t_fidetitel']);

	if ($data['year'] >= wrap_get_setting('dem_spielerphotos_aus_mediendb') AND $data['spielerphotos']) {
		$data['bilder'] = mf_mediadblink_media(
			$data['year'].'/'.$data['main_series_path'], 'Website/Spieler', 'person', $data['person_id']
		);
		$data['spielerphotos'] = NULL;
	} else {
		// @deprecated
		$data['filename'] = str_replace(" ", "", $data['t_vorname']).'-'
			.($data['t_namenszusatz'] ? str_replace(" ", "", $data['t_namenszusatz']).'-' : '')
			.str_replace(" ", "", $data['t_nachname']);
		$data['filename'] = wrap_filename($data['filename']).'.jpg';

		$data['filename_underscore'] = str_replace(" ", "_", $data['t_vorname']).'_'.str_replace(" ", "_", $data['t_nachname']);
		$data['filename_underscore'] = wrap_filename($data['filename_underscore'], '_', ['_' => '_']).'.jpg';

		$data['ak'] = substr($vars[1], strrpos($vars[1], '-') + 1);
		if (in_array($data['year'], ['2011', '2012', '2013']) AND $data['ak'] === 'u25a') {
			$data['ak'] = 'u25';
		} elseif (in_array($data['year'], ['2011', '2012', '2013']) AND $data['ak'] === 'kika') {
			$data['ak'] = 'ukika';
		}
		$data['alter_bildpfad'] = true;
		if (in_array($data['year'], [2010, 2011])) $data['bildpfad_underscore'] = true;
	}
	
	// Partien
	$sql = mf_tournaments_games_sql($data, 
		sprintf('(weiss_person_id = %d OR schwarz_person_id = %d)', $data['person_id'], $data['person_id'])
	);
	$data['games'] = wrap_db_fetch($sql, 'partie_id');
	$data['games'] = array_values($data['games']);
	$data['punkte'] = false;
	$data['hat_punkte'] = false;
	$round_no = 1;
	$index = 0;
	foreach ($data['games'] as $index => $partie) {
		if (mf_tournaments_live_round($data['livebretter'], $partie['brett_no'])) {
			$data['games'][$index]['live'] = true;
		}
		if ($partie['schwarz_person_id'] === $data['person_id']) {
			$data['games'][$index]['spielt_schwarz'] = true;
			$data['punkte'] += $partie['auswaerts_ergebnis_numerisch'];
			if (isset($partie['auswaerts_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		} else {
			$data['games'][$index]['spielt_weiss'] = true;
			$data['punkte'] += $partie['heim_ergebnis_numerisch'];
			if (isset($partie['heim_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		}
		while ($round_no.'' !== $partie['runde_no'].'') {
			array_splice($data['games'], $index, 0, [
				['runde_no' => $round_no, 'no_pairing' => 1]
			]);
			$index++;
			$round_no++;
		}
		$index++;
		$round_no++;
	}
	while ($round_no <= $data['max_round_no']) {
		$data['games'][] = ['runde_no' => $round_no, 'no_pairing' => 1];
		$round_no++;
	}
	
	$sql = 'SELECT participation_id, setzliste_no
		FROM participations
		WHERE event_id = %d
		AND NOT ISNULL(setzliste_no)
		AND teilnahme_status = "Teilnehmer"
		ORDER BY setzliste_no';
	$sql = sprintf($sql, $data['event_id']);
	$participants = wrap_db_fetch($sql, 'participation_id');
	
	$data = array_merge($data, wrap_get_prevnext_flat($participants, $data['participation_id'], true));
	
	$page['link']['next'][0]['href'] = '../'.$data['_next_setzliste_no'].'/';	
	$page['link']['next'][0]['title'] = 'Nächste/r in Setzliste';
	$page['link']['prev'][0]['href'] = '../'.$data['_prev_setzliste_no'].'/';	
	$page['link']['prev'][0]['title'] = 'Vorherige/r in Setzliste';
	$page['dont_show_h1'] = true;
	$page['title'] = $data['t_vorname'].' '.$data['t_namenszusatz'].' '.$data['t_nachname'].' – '.$data['event'].' '.$data['year'];
	$page['text'] = wrap_template('player', $data);
	$page['breadcrumbs'][] = '<a href="../../../">'.$data['year'].'</a>';
	if ($data['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../../'.$data['main_series_path'].'/">'.$data['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../../">'.$data['event'].'</a>';
	$page['breadcrumbs'][] = '<a href="../">Startrangliste</a>';
	$page['breadcrumbs'][] = $data['name'];
	if (in_array('magnificpopup', $zz_setting['modules']))
		$page['extra']['magnific_popup'] = true;
	return $page;
}
