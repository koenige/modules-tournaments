<?php

/**
 * tournaments module
 * move live PGN files
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2016-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_filemove() {
	wrap_setting('log_username', wrap_setting('default_robot_username'));
	wrap_setting('log_trigger', 'cron');

	// Laufende Turniere auslesen
	// for C24, put JSON files to server 2 DAYs before start
	$sql = 'SELECT event_id
			, events.identifier
			, REPLACE(events.identifier, "/", "-") AS path
			, CONCAT(IFNULL(events.event_year, YEAR(events.date_begin)), "-", IF(main_series.path != "reihen", SUBSTRING_INDEX(main_series.path, "/", -1), SUBSTRING_INDEX(series.path, "/", -1))) AS main_series
			, tournaments.urkunde_parameter AS parameters
			, IFNULL((SELECT setting_value FROM _settings
				WHERE setting_key = "canonical_hostname"
				AND _settings.website_id = websites.website_id
			), domain) AS host_name
			, domain
			, tournaments.livebretter AS live_boards
		FROM tournaments
		JOIN events USING (event_id)
		LEFT JOIN websites USING (website_id)
		LEFT JOIN categories series
			ON series.category_id = events.series_category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE events.date_begin <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
		AND events.date_end >= CURDATE()
		ORDER BY events.identifier';
	$tournaments = wrap_db_fetch($sql, 'event_id');
	if (!$tournaments) return false;
	
	// Runden auslesen
	$sql = 'SELECT event_id
			, runde_no
			, main_event_id, date_begin, time_begin
			, IF(NOW() > DATE_SUB(CONCAT(date_begin, " ", time_begin),
				INTERVAL /*_SETTING filemove_begin_before_round_mins _*/ MINUTE), "past", "future") AS type
		FROM events
		WHERE main_event_id IN (%s)
		AND event_category_id = /*_ID categories event/round _*/
		AND takes_place = "yes"
		ORDER BY main_event_id, runde_no';
	$sql = sprintf($sql, implode(',', array_keys($tournaments)));
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

	$pgn_queue = wrap_setting('media_folder').wrap_setting('pgn_queue_folder').'/%s';
	$pgn_sys = wrap_setting('media_folder').wrap_setting('pgn_folder').'/%s';

	wrap_include('syndication', 'zzwrap');
	wrap_setting('syndication_timeout_ms', 2500);

	foreach ($tournaments as $tournament) {
		if ($tournament['parameters'])
			parse_str($tournament['parameters'], $tournament['parameters']);

		// move tournament info to other server already before tournament starts
		if (!empty($tournament['parameters']['ftp_other'])) {
			mod_tournaments_make_filemove_ftp_other($tournament, $tournament['parameters']['ftp_other']);
		}

		if (empty($tournament['current_round'])) continue;

		$tournament['final_dir'] = sprintf($pgn_sys, $tournament['identifier']);
		wrap_mkdir($tournament['final_dir']);

		if ($tournament['live_boards']) {
			$tournament['queue_dir'] = sprintf($pgn_queue, $tournament['path']);
			if (!file_exists($tournament['queue_dir'])) wrap_mkdir($tournament['queue_dir']);
		
			mod_tournaments_make_filemove_queue($tournament, $tournament['parameters']);
			mod_tournaments_make_filemove_final_pgn($tournament);
		}
		mod_tournaments_make_filemove_bulletin_pgn($tournament);
		if (!empty($tournament['parameters']['ftp_pgn'])) {
			mod_tournaments_make_filemove_ftp_pgn($tournament, $tournament['parameters']['ftp_pgn']);
		}
	}
	$page['text'] = '<p>PGN-Dateien verschoben</p>';
	return $page;
}

/**
 * move PGN files into queue for later processing
 *
 * @param array $tournament
 * @return void
 */
function mod_tournaments_make_filemove_queue($tournament, $parameters = []) {
	// pgn-live/2016-dvm-u20/games.pgn
	$pgn_live = wrap_setting('media_folder').wrap_setting('pgn_live_folder').'/%s/games.pgn';

	$source = sprintf($pgn_live, $tournament['path']);
	if ($merged_source = mod_tournaments_make_filemove_concat_pgn($source))
		$source = $merged_source;
	if (!empty($parameters['live_pgn_offset_mins'])) {
		$new_time = filemtime($source) + $parameters['live_pgn_offset_mins'] * 60;
		touch($source, $new_time, $new_time);
		clearstatcache();
	}
	$params = [];
	$params['destination'] = ['timestamp'];
	$params['action'] = 'delete';
	$success = wrap_watchdog($source, $tournament['queue_dir'].'/games-%s.pgn', $params);
	if ($success) {
		wrap_log(sprintf('filemove watchdog queue-in %s %s => %s'
			, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
			, $source, $tournament['queue_dir']
		), E_USER_NOTICE, 'cron');
	}
}

/**
 * move PGN files into final folder after a delay
 *
 * @param array $tournament
 * @return void
 */
function mod_tournaments_make_filemove_final_pgn($tournament) {
	$now = time();
	$live_pgn = $tournament['final_dir'].'/'.$tournament['current_round']['runde_no'].'-live.pgn';
	$pgn_delay = wrap_setting('live_pgn_delay_mins') * 60;

	$files = array_diff(scandir($tournament['queue_dir']), ['.', '..']);
	foreach ($files as $file) {
		if (!str_ends_with($file, '.pgn')) continue;
		if (!str_starts_with($file, 'games-')) continue;
		$timestamp = substr($file, 6, -4);
		if ($timestamp + $pgn_delay > $now) continue;
		rename($tournament['queue_dir'].'/'.$file, $live_pgn);
		wrap_log(sprintf('filemove queue-out %s %s => %s'
			, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
			, $tournament['queue_dir'].'/'.$file, $live_pgn
		), E_USER_NOTICE, 'cron');
	}
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
	// test against filesize, too, livechess creates empty games.pgn
	if (file_exists($source) AND filesize($source)) return false;
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
		if ($file === 'games.pgn' AND filesize($folder.'/'.$file))
			return [$folder.'/'.$file];
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

/**
 * move PGN files from bulletin team to folders
 *
 * @param array $tournament
 * @return void
 */
function mod_tournaments_make_filemove_bulletin_pgn($tournament) {
	if (empty($tournament['parameters']['pgn_bulletin_file_template'])) return;
	$bulletin_dir = wrap_setting('media_folder').wrap_setting('pgn_bulletin_folder').'/'.$tournament['main_series'];
	if (!file_exists($bulletin_dir)) return;

	$s_filename = sprintf('%s/%s.pgn', $bulletin_dir, $tournament['parameters']['pgn_bulletin_file_template']);
	for ($i = 1; $i <= $tournament['current_round']['runde_no']; $i++) {
		$source = sprintf($s_filename, $i);
		$dest = $tournament['final_dir'].'/'.$i.'.pgn';
		$success = wrap_watchdog($source, $dest, ['log_destination' => true]);
		if ($success) {
			wrap_log(sprintf('filemove watchdog bulletin %s %s => %s'
				, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
				, $source, $dest
			), E_USER_NOTICE, 'cron');
			$url = wrap_path('tournaments_job_games', $tournament['identifier'].'/'.$i, false);
			wrap_job($url, ['trigger' => 1, 'job_category_id' => wrap_category_id('jobs/partien')]);
		}
	}
}

/**
 * upload PGN files to FTP server
 *
 * @param array $tournament
 * @param array $ftp_paths
 * @return void
 */
function mod_tournaments_make_filemove_ftp_pgn($tournament, $ftp_paths) {
	$website_id = wrap_setting('website_id');
	wrap_setting('website_id', wrap_id('websites', $tournament['domain']));
	$params['log_destination'] = false;
	$params['destination'] = ['timestamp'];

	foreach ($ftp_paths as $ftp_pgn) {
		for ($i = 1; $i <= $tournament['current_round']['runde_no']; $i++) {
			$source = sprintf('brick games %s %d-live-utf8.pgn'
				, str_replace('/', ' ', $tournament['identifier']), $i
			);
			$round = sprintf('%02d', $i);
			$dest = sprintf($ftp_pgn, $tournament['path'], $round);
			$success = wrap_watchdog($source, $dest, $params);
			if ($success) {
				wrap_log(sprintf('filemove watchdog ftp_pgn %s %s => %s'
					, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
					, $source, $dest
				), E_USER_NOTICE, 'cron');
			}
		}
	}
	wrap_setting('website_id', $website_id);
}

/**
 * upload other files to FTP server
 *
 * examples:
 * - ftp_other[0][source]=/2017/dvm-u20/tournament_c24.json
 * - ftp_other[0][dest]=ftp://dsjdvm2017:bla@54.194.223.217/%s/2017-dvm-u20.json
 * @param array $tournament
 * @param array $ftp_other
 * @return void
 */
function mod_tournaments_make_filemove_ftp_other($tournament, $ftp_other) {
	wrap_setting('cache', true); // file requests might change cache to false

	foreach ($ftp_other as $other) {
		$source = trim($other['source']);
		if (substr($source, 0, 1) === '/') $source = 'https://'.$tournament['host_name'].$source;
		$dest = sprintf(trim($other['dest']), $tournament['path'], $tournament['path']);
		$success = wrap_watchdog($source, $dest, ['log_destination' => false]);
		if ($success) {
			wrap_log(sprintf('filemove watchdog ftp_other %s %s => %s'
				, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'])
				, $source, $dest
			), E_USER_NOTICE, 'cron');
		}
	}
}
