<?php 

/**
 * tournaments module
 * player card
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2017, 2019-2024 Gustaf Mossakowski
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
function mod_tournaments_player($vars, $settings, $event) {
	if (count($vars) !== 3) return false;

	$sql = 'SELECT persons.person_id, participation_id
			, t_vorname, t_namenszusatz, t_nachname
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS name
			, t_verein, organisationen.identifier AS verein_kennung
			, t_dwz, t_elo, t_fidetitel
			, setzliste_no
			, YEAR(date_of_birth) AS geburtsjahr
			, platz_no
			, contacts.identifier AS personen_kennung
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS turnier_kennung
			, (SELECT identifier FROM contacts_identifiers zps WHERE zps.contact_id = contacts.contact_id AND current = "yes" AND identifier_category_id = %d) AS zps_code
			, (SELECT identifier FROM contacts_identifiers fide WHERE fide.contact_id = contacts.contact_id AND current = "yes" AND identifier_category_id = %d) AS player_id_fide
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
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN tabellenstaende
			ON tabellenstaende.person_id = persons.person_id
			AND participations.event_id = tabellenstaende.event_id
			AND tabellenstaende.runde_no = tournaments.tabellenstand_runde_no 
		LEFT JOIN contacts organisationen
			ON participations.club_contact_id = organisationen.contact_id
		WHERE setzliste_no = %d
		AND status_category_id = %d
		AND events.event_id = %d
	';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, wrap_category_id('identifiers/fide-id')
		, $vars[2]
		, wrap_category_id('participation-status/participant')
		, $event['event_id']
	);
	$data = wrap_db_fetch($sql);
	if (!$data) return false;

	$data += $event;

	$data = mf_tournaments_clubs_to_federations($data);
	$data['fidetitel_lang'] = mf_tournaments_fide_title($data['t_fidetitel']);

	if ($data['year'] >= wrap_setting('dem_spielerphotos_aus_mediendb') AND $data['spielerphotos']) {
		$data['bilder'] = mf_mediadblink_media(
			[$data['year'], $data['main_series_path'], 'Website/Spieler'], [], 'person', $data['person_id']
		);
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
	$log_round_error = true;
	$empty_round_added = 0; // if several rounds are empty, add this value to index
	foreach ($data['games'] as $index => $game) {
		if (mf_tournaments_live_round($data['livebretter'], $game['brett_no'])) {
			$data['games'][$index]['live'] = true;
		}
		if ($game['schwarz_person_id'] === $data['person_id']) {
			$data['games'][$index]['spielt_schwarz'] = true;
			$data['punkte'] += $game['auswaerts_ergebnis_numerisch'];
			if (isset($game['auswaerts_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		} else {
			$data['games'][$index]['spielt_weiss'] = true;
			$data['punkte'] += $game['heim_ergebnis_numerisch'];
			if (isset($game['heim_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		}
		if ($game['runde_no'] < $round_no) {
			if ($log_round_error) // log only once
				wrap_error(sprintf(
					'There’s a player having played more than one game per round: Event %s, round %d, %s–%s'
					, $event['identifier'], $game['runde_no'], $game['player_white'], $game['player_black']
				));
				$log_round_error = false;
		} else {
			while ($round_no.'' !== $game['runde_no'].'') {
				array_splice($data['games'], $index + $empty_round_added, 0, [
					['runde_no' => $round_no, 'no_pairing' => 1]
				]);
				$index++;
				$round_no++;
				$empty_round_added++;
			}
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
		AND status_category_id = %d
		ORDER BY setzliste_no';
	$sql = sprintf($sql
		, $data['event_id']
		, wrap_category_id('participation-status/participant')
	);
	$participants = wrap_db_fetch($sql, 'participation_id');
	
	$data = array_merge($data, wrap_get_prevnext_flat($participants, $data['participation_id'], true));
	
	$page['link']['next'][0]['href'] = '../'.$data['_next_setzliste_no'].'/';	
	$page['link']['next'][0]['title'] = 'Nächste/r in Setzliste';
	$page['link']['prev'][0]['href'] = '../'.$data['_prev_setzliste_no'].'/';	
	$page['link']['prev'][0]['title'] = 'Vorherige/r in Setzliste';
	$page['dont_show_h1'] = true;
	$page['title'] = $data['t_vorname'].' '.$data['t_namenszusatz'].' '.$data['t_nachname'].' – '.$data['event'].' '.$data['year'];
	$page['text'] = wrap_template('player', $data);
	$page['breadcrumbs'][]['title'] = $data['name'];
	if (in_array('magnificpopup', wrap_setting('modules')))
		$page['extra']['magnific_popup'] = true;
	return $page;
}
