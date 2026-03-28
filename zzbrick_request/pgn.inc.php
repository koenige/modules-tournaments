<?php 

/**
 * tournaments module
 * PGN download
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2005, 2012-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * PGN download
 *
 * @param array $vars
 *		int [0]: year
 *		string [1]: event_identifier
 *		string [2]: 1.pgn, gesamt.pgn
 * @param array $settings
 * @param array $event
 * @return array
 */
function mod_tournaments_pgn($vars, $settings = [], $event = []) {
	if (count($vars) !== 3 AND count($vars) !== 4) return false;
	// direct access via filemove
	if (empty($event)) $event = my_event($vars[0], $vars[1]);

	$settings['request'] = array_pop($vars);
	$settings = mod_tournaments_pgn_settings($settings);

	if (!$settings['type'] AND $event['sub_series'])
		return mod_tournaments_pgn_series($event, $settings);
	if ($settings['type'])
		return mod_tournaments_pgn_special($event, $settings);

	$sql = 'SELECT series.category_short AS series_short
			, runden, tournament_id, livebretter
			, IF(bretter_min, bretter_min, (SELECT COUNT(*)/2 FROM participations
				WHERE event_id = events.event_id
				AND usergroup_id = /*_ID usergroups spieler _*/)) AS bretter_max
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN tournaments USING (event_id)
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE events.event_id = %d
	';
	$sql = sprintf($sql, $event['website_id'], $event['event_id']);
	$event = array_merge($event, wrap_db_fetch($sql));
	$settings['send_as'] = $event['year'].' '.($event['series_short'] ?? $event['event']);

	if ($settings['request'] === 'gesamt')
		return mod_tournaments_pgn_file_complete($event, $settings);
	if ($settings['request'] === 'current')
		return mod_tournaments_pgn_file_current($event, $settings);
	if ($settings['request'] === 'live')
		return mod_tournaments_pgn_file_live($event, $settings);
	if ($settings['request'] === 'live-raw')
		return mod_tournaments_pgn_file_liveraw($event, $settings);
	if (preg_match('/^(\d+)-live$/', $settings['request'], $matches))
		return mod_tournaments_pgn_file_live_round($event, $matches[1], $settings);
	if (preg_match('/^(\d+)-live-raw$/', $settings['request'], $matches))
		return mod_tournaments_pgn_file_liveraw_round($event, $matches[1], $settings);
	if (preg_match('/^(\d+)$/', $settings['request'], $matches))
		return mod_tournaments_pgn_file_round($event, $matches[1], $settings);
	if (preg_match('/^(\d+)-(\d+)$/', $settings['request'], $matches))
		return mod_tournaments_pgn_file_game_single($event, $matches[1], $matches[2], $settings);
	if (preg_match('/^(\d+)-(\d+)\-(\d+)$/', $settings['request'], $matches))
		return mod_tournaments_pgn_file_game_team($event, $matches[1], $matches[2], $matches[3], $settings);
	return false;
}

/**
 * check request, set content_type, character_set, pgn_path, query_strings
 *
 * @param array $settings
 * @return array $settings
 */
function mod_tournaments_pgn_settings($settings) {
	if (empty($settings['type'])) $settings['type'] = false;
	$settings['content_type'] = 'pgn';
	if (str_ends_with($settings['request'], 'utf8')) {
		$settings['character_set'] = 'utf-8';
		$settings['request'] = substr($settings['request'], 0, -5);
	} else {
		$settings['character_set'] = 'iso-8859-1';
	}
	$settings['pgn_path'] = wrap_setting('media_folder').'/pgn/%s/%s.pgn';
	// ignore query strings behind .pgn
	$settings['qs'] = mod_tournaments_pgn_check_qs();
	return $settings;
}

/**
 * create a PGN file from data
 *
 * @param array $data
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_file($data, $settings) {
	wrap_package_activate('chess'); // pgn_wordwrap

	$page['text'] = '';
	// it is faster to use template per game than to format all games at once
	foreach ($data as $line)
		$page['text'] .= wrap_template('pgn', [$line]);

	return mod_tournaments_pgn_send($page, $settings);
}

/**
 * send PGN data to browser, convert according to chosen character set
 *
 * @param array $page
 * @param array $settings
 */
function mod_tournaments_pgn_send($page, $settings) {
	// normalize line endings to CRLF
	$page['text'] = str_replace(["\r\n", "\r", "\n"], "\n", $page['text']);
	$page['text'] = str_replace("\n", "\r\n", $page['text']);
	$page['headers']['filename'] = sprintf('%s.pgn', $settings['send_as']);
	if ($settings['character_set'] !== 'iso-8859-1') {
		$page['text'] = mb_convert_encoding($page['text'], $settings['character_set'], 'ISO-8859-1');
		$page['headers']['filename'] = mb_convert_encoding($page['headers']['filename'], $settings['character_set'], 'ISO-8859-1');
	}
	$page['text'] = trim($page['text']);
	$page['text'] .= "\r\n";
	wrap_setting('character_set', $settings['character_set']);
	$page['query_strings'] = $settings['qs'] ?? [];
	$page['content_type'] = 'pgn';
	return $page;
}

//
// --- OUTPUT ---
//

/**
 * return all games for a tournament series
 *
 * @param array $event
 * @param array $settings
 * @return array
 */
function mod_tournaments_pgn_series($event, $settings) {
	if ($settings['request'] !== 'gesamt') return false;
	if ($settings['content_type'] !== 'pgn') return false;

	$sql = 'SELECT events.event_id
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_contacts events_places
			ON events.event_id = events_places.event_id
			AND events_places.role_category_id = /*_ID categories roles/location _*/
			AND events_places.sequence = 1
		LEFT JOIN addresses
			ON addresses.contact_id = events_places.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE main_event_id = %d
		AND (tournaments.notationspflicht = "ja"
			OR addresses.country_id = /*_ID countries -- _*/
		)
		ORDER BY events.identifier
	';
	$sql = sprintf($sql
		, $event['website_id']
		, $event['event_id']
	);
	$events = wrap_db_fetch($sql, 'event_id', 'single value');
	if (!$events) return false;

	wrap_include('pgn', 'tournaments');
	$games = [];
	foreach ($events as $event_id)
		$games = array_merge($games, mf_tournaments_pgn_db($event_id));
	if (!$games) return false;

	$settings['send_as'] = $event['year'].' '.$event['series_short'];
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * export separate file, e.g. pdt
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_special($event, $settings) {
	$file['name'] = sprintf($settings['pgn_path']
		, $event['identifier'], $settings['type'].'-'.$settings['request']
	);
	if (!file_exists($file['name'])) return false;

	$page['text'] = file_get_contents($file['name']);
	$settings['send_as'] = $event['year'].' '.$settings['type'].' Runde '.$settings['request'];
	
	return mod_tournaments_pgn_send($page, $settings);
}

/**
 * export PGN for a complete tournament
 * gesamt = alle Dateien eines Turniers oder einer Turnierreihe
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_file_complete($event, $settings) {
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id']);
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * export PGN for current round of tournament
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_file_current($event, $settings) {
	$round_no = mf_tournaments_live_round($event['event_id']);
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no);
	$settings['send_as'] .= ' Runde '.$round_no;
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * export PGN for live games of tournament
 * live = aktuelle Runde, nur Livebretter, Kopf aus Datenbank
 * (vor Runde: nur Partienköpfe)
 *
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_file_live($event, $settings) {
	$round_no = mf_tournaments_live_round($event['event_id']);
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no);
	$games = mod_tournaments_pgn_liveonly($games, $event);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	wrap_setting('cache_age', 1);
	wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_setting('tournaments_live_cache_control_age')));
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * export raw PGN from board for live games of tournament
 *
 * output of raw live games, current round, directly how they come from the boards
 * check for local file and source from external server
 * live-raw = aktuelle Runde, nur Livebretter, 1:1 von Brettern
 * @param array $event
 * @param array $settings
 */
function mod_tournaments_pgn_file_liveraw($event, $settings) {
	wrap_cache_header(sprintf('Cache-Control: max-age=%d', wrap_setting('tournaments_live_cache_control_age')));
	for ($i = 1; $i <= $event['runden']; $i++) {
		$pgn = sprintf($settings['pgn_path'], $event['identifier'], $i.'-live');
		if (!file_exists($pgn)) continue;
		$settings['send_as'] .= ' Runde '.$I.' (Live)';
		$page['text'] = file_get_contents($pgn);
		return mod_tournaments_pgn_send($page, $settings);
	}

	// maybe there's some path on the server / on an external server?
	$page['text'] = mf_tournaments_pgn_file_from_tournament($event['tournament_id']);
	if (!$page['text']) return false;
	$settings['send_as'] .= ' (Live)';
	return mod_tournaments_pgn_send($page, $settings);
}

/**
 * export PGN for live games of tournament, specific round
 * 1-live = 1. Runde, nur Livebretter, Kopf aus Datenbank
 *
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_pgn_file_live_round($event, $round_no, $settings) {
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no);
	$games = mod_tournaments_pgn_liveonly($games, $event);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	wrap_setting('cache', false);
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * output of raw live games per round, directly how they come from the boards
 *
 * 1-live-raw = 1. Runde, nur Livebretter, 1:1 von Brettern
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_pgn_file_liveraw_round($event, $round_no, $settings) {
	$pgn = sprintf($settings['pgn_path'], $event['identifier'], $round_no.'-live');
	if (!file_exists($pgn)) return false;
	$page['text'] = file_get_contents($pgn);
	$settings['send_as'] .= ' Runde '.$round_no.' (Live)';
	return mod_tournaments_pgn_send($page, $settings);
}

/**
 * output of all games in one round of a tournament
 *
 * 1 = Runde 1
 * @param array $event
 * @param int $round_no
 * @param array $settings
 */
function mod_tournaments_pgn_file_round($event, $round_no, $settings) {
	// "2012 DVM U20w Runde 1.pgn"
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no;
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * output game of a board in a tournament (single)
 * 
 * 1-1 = Runde 1, Brett 1 (Einzelturnier)
 * @param array $event
 * @param int $round_no
 * @param int $board_no
 * @param array $settings
 */
function mod_tournaments_pgn_file_game_single($event, $round_no, $board_no, $settings) {
	// 1-1.pgn = Runde 1 Brett 1, Einzelturnier
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no, $board_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no.' Brett '.$board_no;
	return mod_tournaments_pgn_file($games, $settings);
}

/*
 * output game of a board on a table in a tournament (teams)
 *
 * 1-1-1 = Runde 1, Tisch 1, Brett 1 (Mannschaftsturnier)
 * @param array $event
 * @param int $round_no
 * @param int $table_no
 * @param int $board_no
 * @param array $settings
 */
function mod_tournaments_pgn_file_game_team($event, $round_no, $table_no, $board_no, $settings) {
	// 1-4-1.pgn = Runde 1 Tisch 4 Brett 1, Mannschaftsturnier
	wrap_include('pgn', 'tournaments');
	$games = mf_tournaments_pgn_db($event['event_id'], $round_no, $board_no, $table_no);
	if (!$games) return false;
	$settings['send_as'] .= ' Runde '.$round_no.' Tisch '.$table_no.' Brett '.$board_no;
	return mod_tournaments_pgn_file($games, $settings);
}

/**
 * reduce game list to live games from this tournament
 *
 * @param array $games
 * @param array $event
 * @return array
 */
function mod_tournaments_pgn_liveonly($games, $event) {
	$livebretter = mf_tournaments_live_boards($event['livebretter'], $event['bretter_max']);
	if (str_starts_with($event['turnierform'], 'mannschaft-')) {
		$livebretter_old = $livebretter;
		$livebretter = [];
		foreach ($livebretter_old as $brett) {
			if (strstr($brett, '.')) $livebretter[] = $brett;
			else {
				for ($i = 1; $i <= $event['bretter_max']; $i++) {
					$livebretter[] = sprintf('%d.%d', $brett, $i);
				}
			}
		}
	}
	foreach ($games as $index => $partie) {
		if ($partie['Table']) $board = $partie['Table'].'.'.$partie['Board'];
		else $board = $partie['Board'];
		if (!in_array($board, $livebretter)) unset($games[$index]);
	}
	return $games;
}

/**
 * check for query string attached to .pgn-requests
 *
 * some clever programmers thought this is a good idea to disable caching
 * but this of course disables 304 requests, too, not so clever
 *
 * @return array
 */
function mod_tournaments_pgn_check_qs() {
	global $zz_page;

	$url = parse_url(wrap_setting('request_uri'));
	if (!str_ends_with($url['path'], '.pgn')) return [];
	if (empty($url['query'])) return [];
	parse_str($url['query'], $qs);
	$zz_page['url']['full']['query'] = false;
	if (wrap_setting('cache_age'))
		wrap_send_cache(wrap_setting('cache_age'));
	return array_keys($qs);
}
