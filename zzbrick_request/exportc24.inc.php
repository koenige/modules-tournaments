<?php 

/**
 * tournaments module
 * Export tournament data for Chess24
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_exportc24($vars, $settings, $event) {
	global $zz_setting;

	if (empty($settings['json'])) return false;
	if (count($vars) !== 2) return false;
	$event['path'] = str_replace('/', '-', $event['identifier']);
	
	parse_str($event['tournament_parameter'], $parameter);
	if (!empty($parameter['ftp_pgn'][0])) {
		$ftp = parse_url($parameter['ftp_pgn'][0]);
	} else {
		$ftp['user'] = $event['path'];
	}
	
	// Wertungen
	$sql = 'SELECT tw_id, categories.parameters
		FROM turniere_wertungen
		LEFT JOIN tournaments USING (tournament_id)
		LEFT JOIN categories
			ON turniere_wertungen.wertung_category_id = categories.category_id
		WHERE tournaments.event_id = %d
		ORDER BY turniere_wertungen.reihenfolge';
	$sql = sprintf($sql, $event['event_id']);
	$wertungen = wrap_db_fetch($sql, 'tw_id');
	
	$data['id'] = $event['path'];
	$data['hidden'] = false;
	if (!empty($zz_setting['chess24com']['logo_url']))
		$data['logo'] = $zz_setting['chess24com']['logo_url'];
	if ($event['turnierform'] === 'e') {
		$data['eventType'] = 'open';
		// without table: bunchOfGames
	} else {
		$data['eventType'] = 'teamRoundRobin';
	}
	$data['titles'] = [
		'de' => $event['event'],
		'en' => '',
		'es' => '',
		'fr' => ''
	];
	$data['descriptions'] = [
		'de' => '',
		'en' => '',
		'es' => '',
		'fr' => ''
	];
	foreach ($wertungen as $id => $wertung) {
		parse_str($wertung['parameters'], $wparameter);
		if (empty($wparameter['chess24_com'])) continue;
		$data['tieRules'][] = $wparameter['chess24_com'];
	}
	$data['chatRooms'] = [
		'broadcast_'.$event['path'].'_en',
		'broadcast_'.$event['path'].'_de',
		'broadcast_'.$event['path'].'_es',
		'broadcast_'.$event['path'].'_fr'
	];
	$data['gameSources'] = [];
	$data['players'] = [];

	if ($event['turnierform'] !== 'e') {
		$sql = 'SELECT CONCAT("T", team_id) AS team_id
				, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS name
				, CONCAT("%s/", teams.identifier, "/") AS link
			FROM teams
			WHERE team_status = "Teilnehmer"
			AND event_id = %d';
		$sql = sprintf($sql
			, $zz_setting['host_base']
			, $event['event_id']
		);
		$data['teams'] = wrap_db_fetch($sql, 'team_id');
		foreach ($data['teams'] as $id => $team) {
			unset($data['teams'][$id]['team_id']);
		}
	} else {
		$data['teams'] = (object) [];
	}

	// brett or rang?
	$sql = 'SELECT COUNT(brett_no)
		FROM participations
		WHERE event_id = %d
		AND usergroup_id = %d';
	$sql = sprintf($sql
		, $event['event_id']
		, wrap_id('usergroups', 'spieler')
	);
	$brett_no = wrap_db_fetch($sql, '', 'single value');
	if ($event['turnierform'] !== 'e') {
		$where = sprintf('AND NOT ISNULL(%s)', $brett_no ? 'brett_no' : 'rang_no');
	} else {
		$where = '';
	}

	$sql = 'SELECT participation_id
			, IFNULL(contacts_identifiers.identifier, CONCAT("ZZ-", participations.contact_id)) AS fideId
			, CONCAT(t_nachname, ", ", t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), "")) AS name
			, t_fidetitel AS title
			, t_elo AS elo
			, IFNULL(federation, "GER") AS country
			, %s AS no, team_id
		FROM participations
		LEFT JOIN contacts_identifiers
			ON participations.contact_id = contacts_identifiers.contact_id
			AND contacts_identifiers.current = "yes"
			AND contacts_identifiers.identifier_category_id = %d
		LEFT JOIN fide_players
			ON contacts_identifiers.identifier = fide_players.player_id
		WHERE event_id = %d
		AND usergroup_id = %d
		AND teilnahme_status IN ("Teilnehmer", "disqualifiziert")
		%s
		ORDER BY team_id, brett_no, rang_no, IF(ISNULL(contacts_identifiers.identifier), 1, NULL), contacts_identifiers.identifier, participation_id';
	$sql = sprintf($sql
		, $brett_no ? 'brett_no' : 'rang_no'
		, wrap_category_id('identifiers/fide-id')
		, $event['event_id']
		, wrap_id('usergroups', 'spieler')
		, $where
	);
	$players = wrap_db_fetch($sql, 'participation_id');
	foreach ($players as $line) {
		if (empty($line['title'])) unset($line['title']);
		if (!$line['elo']) unset($line['elo']);
		elseif (is_numeric($line['elo'])) $line['elo'] = intval($line['elo']);
		// Chess24-Apps kommen nicht mit numerischen IDs zurecht
		if (is_numeric($line['fideId'])) $line['fideId'] = $line['fideId'].'';
		if ($event['turnierform'] !== 'e') {
			$data['teams']['T'.$line['team_id']]['rooster'][intval($line['no'])] = $line['fideId'];
		}
		unset($line['no']);
		unset($line['team_id']);
		unset($line['participation_id']);
		$id = $line['fideId'];
		if (is_numeric($id)) {
			$id = intval($id);
		}
		$data['players'][$id] = $line;
	}

	$data['ratingType'] = 'standard';
	$data['rounds'] = [];

	$sql = 'SELECT ROUND(bedenkzeit_sec * 1000) AS base
			, IFNULL(ROUND(zeitbonus_sec * 1000), "") AS incrementPerMove
			, bretter_min AS gamesPerMatch
		FROM turniere_bedenkzeiten
		LEFT JOIN tournaments USING (tournament_id)
		WHERE event_id = %d AND phase = 1';
	$sql = sprintf($sql, $event['event_id']);
	$turnier = wrap_db_fetch($sql);

	$sql = 'SELECT events.runde_no
			, ROUND(UNIX_TIMESTAMP(CONCAT(events.date_begin, " ", events.time_begin)) * 1000) + %d * 1000 AS startDate
		FROM events
		WHERE events.main_event_id = %d
		AND NOT ISNULL(runde_no)
		ORDER BY events.runde_no';
	$sql = sprintf($sql, wrap_get_setting('live_pgn_delay_mins') * 60, $event['event_id']);
	$data['rounds'] = wrap_db_fetch($sql, 'runde_no');
	foreach ($data['rounds'] as $id => $round) {
		$data['rounds'][$id]['timeControl'] = [
			'base' => intval($turnier['base']),
			'incrementPerMove' => intval($turnier['incrementPerMove'])
		];
		$data['rounds'][$id]['startDate'] = floatval($round['startDate']); // intval + 32 bit server leads into problems
		$data['rounds'][$id]['matches'][1]['games'] = (object) [];
		if ($event['turnierform'] !== 'e') {
			// logic of chess24: force some trash to make their system work
			$data['rounds'][$id]['matches'][1]['teams'][1] = '';
			$data['rounds'][$id]['matches'][1]['teams'][2] = '';
		}
		unset($data['rounds'][$id]['runde_no']);
		$data['gameSources'][sprintf('/home/%s/%s/%02d/games.pgn', $ftp['user'], $event['path'], $round['runde_no'])] = ['type' => 'pgn'];
	}

	$sql = 'SELECT paarung_id, paarungen.runde_no, tisch_no, heim_team_id, auswaerts_team_id
		FROM paarungen
		LEFT JOIN events
			ON events.main_event_id = paarungen.event_id
			AND events.runde_no = paarungen.runde_no
		WHERE events.main_event_id = %d
		ORDER BY paarungen.runde_no';
	$sql = sprintf($sql, $event['event_id']);
	$matches = wrap_db_fetch($sql, 'paarung_id');
	foreach ($matches as $match) {
		$data['rounds'][$match['runde_no']]['matches'][$match['tisch_no']]['games'] = (object) [];
		$data['rounds'][$match['runde_no']]['matches'][$match['tisch_no']]['teams'][1] = 'T'.$match['heim_team_id'];
		$data['rounds'][$match['runde_no']]['matches'][$match['tisch_no']]['teams'][2] = 'T'.$match['auswaerts_team_id'];
	}

	// Video sources
	// @todo support as well:
	// "type": "iframe",
    // "src": "//player.twitch.tv/?channel="
    // no event tag here
	
	$sql = 'SELECT event_id FROM events
		WHERE identifier = "%d/%s"';
	$sql = sprintf($sql, $event['year'], $event['main_series_path']);
	$series_event_id = wrap_db_fetch($sql, '', 'single value');
	
	$sql = 'SELECT category_id, category_short
		FROM categories WHERE main_category_id = %d';
	$sql = sprintf($sql, wrap_category_id('titel'));
	$fidetitel = wrap_db_fetch($sql, '_dummy_', 'key/value');
	
	$data['videoSources'] = [];
	$sql = 'SELECT "de" AS language, "" AS autoPlay
			, direct_link AS event
			, SUBSTRING_INDEX(description, ": ", -1) AS commentators
		FROM events
		WHERE (events.event = "ChessyTV" OR events.event LIKE "Live-Kommentierung%%")
		AND main_event_id = %d
		AND NOT ISNULL(direct_link)
		AND DATE_SUB(CONCAT(events.date_begin, " ", events.time_begin), INTERVAL 2 HOUR) <= NOW()
		ORDER BY events.date_begin DESC, events.time_begin DESC
		LIMIT 1';
	$sql = sprintf($sql, $series_event_id);
	$data['videoSources'] = wrap_db_fetch($sql, '_dummy_', 'numeric');
	foreach ($data['videoSources'] as $index => $source) {
		$url = parse_url($source['event']);
		switch ($url['host']) {
			case 'youtube.com':
			case 'www.youtube.com':
				$data['videoSources'][$index]['type'] = 'youtube';
				$data['videoSources'][$index]['event'] = str_replace('v=', '', $url['query']);
				break;
			case 'twitch.tv':
			case 'www.twitch.tv':
				$data['videoSources'][$index]['type'] = 'iframe';
				$data['videoSources'][$index]['src'] = sprintf(
					"//player.twitch.tv/?channel=%s&parent=chess24.com&autoplay=false"
					, substr($url['path'], 1)
				);
				break;
		}
	}
	foreach ($data['videoSources'] as $index => $source) {
		$data['videoSources'][$index]['autoPlay'] = false;
		if (!$source['commentators']) continue;
		if (strstr($source['commentators'], "\n"))
			$source['commentators'] = substr($source['commentators'], 0, strpos($source['commentators'], "\n"));
		$commentators = explode(', ', $source['commentators']);
		$data['videoSources'][$index]['commentators'] = [];
		foreach ($commentators as $c_index => $commentator) {
			$nameparts = explode(' ', trim($commentator));
			if (in_array($nameparts[0], $fidetitel)) {
				$data['videoSources'][$index]['commentators'][$c_index]['title'] = array_shift($nameparts);
			} else {
				$data['videoSources'][$index]['commentators'][$c_index]['title'] = "";
			}
			$data['videoSources'][$index]['commentators'][$c_index]['name'] = implode(' ', $nameparts);
		} 
	}
    
	$data['tournamentNews'] = [
		'en' => '',
		'de' => '',
		'es' => '',
		'fr' => ''
	];
	$data['flashes'] = [
		'en' => '',
		'de' => '',
		'es' => '',
		'fr' => ''
	];
	$data['videos'] = [
		'en' => '',
		'de' => '',
		'es' => '',
		'fr' => ''
	];

	if ($event['turnierform'] !== 'e') {
		$data['gamesPerMatch'] = intval($turnier['gamesPerMatch']);
	}

//	$data['nameMap'] = [
//		'Liu, Sijia Anna' => 'Liu, Sija Anna'
//	];

	if (!empty($parameter['chess24_com'])) {
		foreach ($parameter['chess24_com'] as $key => $values) {
			if (is_array($values)) {
				foreach ($values as $subkey => $value) {
					$values[$subkey] = str_replace('\n', "\n", $value);
				}
			} else {
				$values = str_replace('\n', "\n", $values);
				if ($values === 'true') $values = true;
			}
			$data[$key] = $values;
		}
	}

	// + JSON_NUMERIC_CHECK auskommentiert, da Chess24 keine numerischen FideIDs mag
	$page['text'] = json_encode($data, (JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE));
	$page['content_type'] = 'json';
	$page['headers']['filename'] = $event['path'].'.json';
	return $page;
}
