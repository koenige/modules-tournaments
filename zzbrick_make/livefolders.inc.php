<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2016, 2020 Gustaf Mossakowski <gustaf@koenige.org>
// Verzeichnisse für Live-PGNs automatisch erstellen


/**
 * Verzeichnisse für Live-PGNs
 *
 * Aufruf per Cron mit POST
 * Alternativ: HTML-Formular mit Buttons
 */
function mod_tournaments_make_livefolders() {
	global $zz_setting;
	$data = [];

	// get folders which should exist
	$sql = 'SELECT events.event_id
			, REPLACE(events.identifier, "/", "-") AS folder
		FROM turniere
		LEFT JOIN events USING (event_id)
		WHERE NOT ISNULL(livebretter)
		AND DATE_SUB(events.date_begin, INTERVAL %d DAY) <= CURDATE()
		AND DATE_ADD(events.date_end, INTERVAL %d DAY) >= CURDATE()';
	$sql = sprintf($sql,
		wrap_get_setting('live_folders_days'), wrap_get_setting('live_folders_days')
	);
	$running = wrap_db_fetch($sql, '_dummy_', 'key/value');

	// get all existing folders
	$pgn_folders[] = $zz_setting['media_folder'].'/pgn-live';
	$pgn_folders[] = $zz_setting['media_folder'].'/pgn-queue';

	foreach ($pgn_folders as $pgn_folder) {
		$existing_folders = array_diff(scandir($pgn_folder), ['.', '..']);
		foreach ($existing_folders as $index => $file) {
			if (!is_dir($pgn_folder.'/'.$file)) {
				unset($existing_folders[$index]);
			}
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$data[$pgn_folder] = mod_tournaments_make_livefolders_update($existing_folders, $running, $pgn_folder);
		}
		foreach ($existing_folders as $folder) {
			$data[$pgn_folder]['existing_folders'][] = ['folder' => $folder];
		}
		$data[$pgn_folder]['folder'] = $pgn_folder;
	}
	$data = array_values($data);

	$page['text'] = wrap_template('livefolders', $data);
	return $page;
}

/**
 * Verzeichnisse erstellen
 *
 * eine Woche vor Meisterschaft, Vorgabe Livebretter NOT NULL
 * eine Woche nach Meisterschaft: Verzeichnisse löschen
 * @param array $existing_folders
 * @param array $running
 * @param string $pgn_folder
 * @return array
 */
function mod_tournaments_make_livefolders_update($existing_folders, $running, $pgn_folder) {
	$delete = array_diff($existing_folders, $running);
	foreach ($delete as $folder) {
		wrap_unlink_recursive($pgn_folder.'/'.$folder);
		$data['deleted'][]['folder'] = $folder;
	}

	$create = array_diff($running, $existing_folders);
	foreach ($create as $folder) {
		mkdir($pgn_folder.'/'.$folder);
		$data['created'][]['folder'] = $folder;
	}
	
	if (empty($data))
		$data['no_changes'] = true;
	
	return $data;
}
