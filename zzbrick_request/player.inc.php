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
			, (SELECT identifier FROM contacts_identifiers zps
				WHERE zps.contact_id = contacts.contact_id AND current = "yes"
				AND identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
			) AS player_pass_dsb
			, (SELECT identifier FROM contacts_identifiers fide
				WHERE fide.contact_id = contacts.contact_id AND current = "yes"
				AND identifier_category_id = /*_ID categories identifiers/id_fide _*/
			) AS player_id_fide
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
		AND status_category_id = /*_ID categories participation-status/participant _*/
		AND events.event_id = %d
	';
	$sql = sprintf($sql, $vars[2], $event['event_id']);
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
	$sql = wrap_sql_query('tournaments_games');
	$sql = sprintf($sql, $data['event_id'],
		sprintf('(weiss_person_id = %d OR schwarz_person_id = %d)', $data['person_id'], $data['person_id'])
	);
	$games = wrap_db_fetch($sql, 'partie_id');
	$data['punkte'] = false;
	$data['hat_punkte'] = false;
	$log_round_error = true;
	$data['games'] = [];
	foreach ($games as $game) {
		if (mf_tournaments_live_board($data['livebretter'], $game['brett_no']))
			$game['live'] = true;
		if ($game['schwarz_person_id'] === $data['person_id']) {
			$game['spielt_schwarz'] = true;
			$data['punkte'] += $game['auswaerts_ergebnis_numerisch'];
			if (isset($game['auswaerts_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		} else {
			$game['spielt_weiss'] = true;
			$data['punkte'] += $game['heim_ergebnis_numerisch'];
			if (isset($game['heim_ergebnis_numerisch'])) $data['hat_punkte'] = true;
		}
		if (array_key_exists($game['runde_no'], $data['games']) AND $log_round_error) {
			wrap_error(sprintf(
				'There’s a player having played more than one game per round: Event %s, round %d, %s–%s'
				, $event['identifier'], $game['runde_no'], $game['player_white'], $game['player_black']
			));
			$log_round_error = false;
		}
		$data['games'][$game['runde_no']] = $game;
	}
	if (count($data['games']) < $data['max_round_no']) {
		for ($i = 1; $i <= $data['max_round_no']; $i++) {
			if (array_key_exists($i, $data['games'])) continue;
			$data['games'][$i] = ['runde_no' => $i, 'no_pairing' => 1];
		}
	}
	ksort($data['games']);
	
	$sql = 'SELECT participation_id, setzliste_no
		FROM participations
		WHERE event_id = %d
		AND NOT ISNULL(setzliste_no)
		AND status_category_id = /*_ID categories participation-status/participant _*/
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
	$page['breadcrumbs'][]['title'] = $data['name'];
	if (in_array('magnificpopup', wrap_setting('modules')))
		$page['extra']['magnific_popup'] = true;
	return $page;
}
