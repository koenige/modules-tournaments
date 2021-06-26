<?php

/**
 * tournaments module
 * move live PGN files
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_filemove() {
	global $zz_setting;
	$pgn_delay = wrap_get_setting('live_pgn_delay_mins') * 60;

	// Laufende Turniere auslesen
	// for C24, put JSON files to server 2 DAYs before start
	$sql = 'SELECT event_id
			, events.identifier
			, REPLACE(events.identifier, "/", "-") AS pfad
			, CONCAT(YEAR(events.date_begin), "-", IF(main_series.path != "reihen", SUBSTRING_INDEX(main_series.path, "/", -1), SUBSTRING_INDEX(series.path, "/", -1))) AS main_series
			, CONCAT(IFNULL(events.event_year, YEAR(events.date_begin)), "-", IF(main_series.path != "reihen", SUBSTRING_INDEX(main_series.path, "/", -1), SUBSTRING_INDEX(series.path, "/", -1))) AS main_series
			, tournaments.urkunde_parameter AS parameter
		FROM tournaments
		JOIN events USING (event_id)
		LEFT JOIN categories series
			ON series.category_id = events.series_category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE events.date_begin <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
		AND events.date_end >= CURDATE()
		AND NOT ISNULL(tournaments.livebretter)
		ORDER BY events.identifier';
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) return false;
	
	// Runden auslesen
	$sql = 'SELECT event_id
			, runde_no
			, main_event_id, date_begin, time_begin
			, IF(NOW() > DATE_SUB(CONCAT(date_begin, " ", time_begin),
				INTERVAL %d MINUTE), "past", "future") AS type
		FROM events
		WHERE main_event_id IN (%s)
		AND event_category_id = %d
		AND takes_place = "yes"
		ORDER BY main_event_id, runde_no';
	$sql = sprintf($sql
		, wrap_get_setting('filemove_begin_before_round_mins')
		, implode(',', array_keys($tournaments))
		, wrap_category_id('zeitplan/runde')
	);
	$rounds = wrap_db_fetch($sql, ['main_event_id', 'event_id']);
	foreach ($rounds as $main_event_id => $rounds_per_event) {
		$tournaments[$main_event_id]['rounds'] = $rounds_per_event;
	}
	
	foreach ($tournaments as $index => $tournament) {
		if (empty($tournament['rounds'])) continue;
		foreach ($tournament['rounds'] as $round) {
			if ($round['type'] === 'past') {
				// current round, overwrite until last past
				$tournaments[$index]['current_round'] = $round;
			}
			if ($round['type'] === 'future') {
				// next round
				$tournaments[$index]['next_round'] = $round;
				break;
			}
		}
	}

	// pgn-live/2016-dvm-u20/games.pgn
	$pgn_live = $zz_setting['media_folder'].'/pgn-live/%s/games.pgn';
	$pgn_queue = $zz_setting['media_folder'].'/pgn-queue/%s';
	$pgn_sys = $zz_setting['media_folder'].'/pgn/%s';
	$pgn_bulletin = $zz_setting['media_folder'].'/pgn-bulletin/%s';
	$now = time();
	require_once $zz_setting['core'].'/syndication.inc.php';
	$zz_setting['syndication_timeout_ms'] = 2500;

	foreach ($tournaments as $tournament) {
		parse_str($tournament['parameter'], $parameter);

		// Allgemeine Uploads?
		// ftp_other[0][source]=/2017/dvm-u20/tournament_c24.json
		// ftp_other[0][dest]=ftp://dsjdvm2017:bla@54.194.223.217/%s/2017-dvm-u20.json
		if (!empty($parameter['ftp_other'])) {
			foreach ($parameter['ftp_other'] as $ftp_other) {
				$params['log_destination'] = false;
				$success_other = false;
				$source = trim($ftp_other['source']);
				if (substr($source, 0, 1) === '/') $source = $zz_setting['host_base'].$source;
				$dest = sprintf(trim($ftp_other['dest']), $tournament['pfad']);
				$success_other = wrap_watchdog($source, $dest, $params, false);
			}
		}

		if (empty($tournament['current_round'])) continue;
		
		// 1. move PGN files into queue
		$source = sprintf($pgn_live, $tournament['pfad']);
		if ($merged_source = mod_tournaments_make_filemove_concat_pgn($source)) {
			$source = $merged_source;
		}
		$dest_dir = sprintf($pgn_queue, $tournament['pfad']);
		if (!file_exists($dest_dir)) wrap_mkdir($dest_dir);
		$final_dir = sprintf($pgn_sys, $tournament['identifier']);
		wrap_mkdir($final_dir);
		$params = [];
		$params['destination'] = ['timestamp'];
		wrap_watchdog($source, $dest_dir.'/games-%s.pgn', $params, true);

		// 2. move queued files on
		$files = array_diff(scandir($dest_dir), ['.', '..']);
		$live_pgn = $final_dir.'/'.$tournament['current_round']['runde_no'].'-live.pgn';
		foreach ($files as $file) {
			if (substr($file, -4) !== '.pgn') continue;
			if (substr($file, 0, 6) !== 'games-') continue;
			$timestamp = substr($file, 6, -4);
			if ($timestamp + $pgn_delay > $now) continue;
			rename($dest_dir.'/'.$file, $live_pgn);
		}
		
		// DEM: Bulletin
		$bulletin_dir = sprintf($pgn_bulletin, $tournament['main_series']);
		$params['log_destination'] = true;
		if (file_exists($bulletin_dir)) {
			$tournament_identifier = explode('/', $tournament['identifier']);
			switch ($tournament_identifier[1]) {
				case 'dem-u10w': case 'dem-u10':
					$s_filename = $bulletin_dir.'/runde%d_U10.pgn';
					break;
				case 'dem-u12w': case 'dem-u12': 
				case 'dem-u14w': case 'dem-u14': case 'dem-u16w': case 'dem-u16': 
				case 'dem-u18w': case 'dem-u18': case 'odjm-a': case 'odjm-b': 
				case 'odjm-c': 
					$s_filename = $bulletin_dir.'/runde%d_U25-U12.pgn';
					break;
				default:
					$s_filename = false;
					break;
			}
			if ($s_filename) {
				for ($i = 1; $i <= $tournament['current_round']['runde_no']; $i++) {
					$source = sprintf($s_filename, $i);
					$dest = $final_dir.'/'.$i.'.pgn';
					$success = wrap_watchdog($source, $dest, $params, false);
					if ($success) {
						$partien_url = sprintf('/_jobs/partien/%s/%s/', $tournament['identifier'], $i);
						wrap_trigger_protected_url($partien_url, $zz_setting['robot_username']);
					}
				}
			}
		}

		// Upload auf FTP, results.pgn
		if (!empty($parameter['ftp_pgn'])) {
			foreach ($parameter['ftp_pgn'] as $ftp_pgn) {
				$params['log_destination'] = false;
				$success = [];
				for ($i = 1; $i <= $tournament['current_round']['runde_no']; $i++) {
					$source = sprintf('%s/%s/partien/%d-utf8.pgn'
						, $zz_setting['host_base'], $tournament['identifier']
						, $i
					);
					$round = sprintf('%02d', $i);
					$dest = sprintf($ftp_pgn, $tournament['pfad'], $round);
					$success[$i] = wrap_watchdog($source, $dest, $params, false);
				}
			}
		}

	}
	$page['text'] = '<p>PGN-Dateien verschoben</p>';
	return $page;
}

/**
 * if there's not a single games.pgn in the folder, look into each subfolder
 * for a games.pgn, glue it together and write it as 'games.pgn' into the
 * top folder
 *
 * (seperate folders for several rounds not allowed!)
 * @param string $source
 * @return bool
 */
function mod_tournaments_make_filemove_concat_pgn($source) {
	if (file_exists($source)) return false;
	$folder = dirname($source); // media_folder/pgn-live/[tournament]/
	$pgn_files = mod_tournaments_make_filemove_scandir($folder);
	if (!$pgn_files) return false;
	
	// glue all PGN files together
	$pgn_text = '';
	$last_mod = '';
	foreach ($pgn_files as $file) {
		$pgn_text .= file_get_contents($file);
		$last_mod_file = filemtime($file);
		if ($last_mod_file > $last_mod) $last_mod = $last_mod_file;
	}
	if (!$pgn_text) return false;
	$merged_source = substr($source, 0, -4).'-merged.pgn';
	if (file_exists($merged_source)) unlink($merged_source);
	file_put_contents($merged_source, $pgn_text);
	touch($merged_source, $last_mod);
	return $merged_source;
}

/**
 * looks for file 'games.pgn' in subfolders
 * if in one subfolder, somewhere a 'games.pgn' is found, return the path and
 * stop looking for any further files in this subfolder
 *
 * @param string $folder
 * @return array list of files
 */
function mod_tournaments_make_filemove_scandir($folder) {
	$files = scandir($folder);
	$pgn_files = [];
	foreach ($files as $file) {
		if (substr($file, 0, 1) === '.') continue;
		if ($file === 'games.pgn') return [$folder.'/'.$file];
		// LiveChess 2, separate files for each game
		if (preg_match('~^game-\d+\.pgn$~', $file)) {
			$pgn_files[] = $folder.'/'.$file;
			continue;
		}
		if (!is_dir($folder.'/'.$file)) continue;
		$pgn_files = array_merge($pgn_files, mod_tournaments_make_filemove_scandir($folder.'/'.$file));
	}
	return $pgn_files;
}
