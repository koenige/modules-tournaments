<?php 

/**
 * tournaments module
 * PGN download, raw files
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * PGN download
 *
 * @param array $params
 *		int [0]: year
 *		string [1]: event_identifier
 *		string [2]: 1.pgn, gesamt.pgn
 * @param array $settings
 * @param array $event
 * @return array
 */
function mod_tournaments_pgnfile($params, $settings = [], $event = []) {
	if (count($params) !== 3) return false;

	$request = array_pop($params);
	if ($request === 'gesamt')
		$basename = 'gesamt';
	elseif (preg_match('/^(\d+)$/', $request, $matches))
		$basename = $matches[1];
	elseif (preg_match('/^(\d+)-(\d+)$/', $request, $matches))
		$basename = $matches[1].'-'.$matches[2];
	elseif (preg_match('/^(\d+)-(\d+)-(\d+)$/', $request, $matches))
		$basename = $matches[1].'-'.$matches[2].'-'.$matches[3];
	else return false;

	if (!wrap_session_value('logged_in'))
		wrap_quit(403, wrap_text('You need to be logged in to see this PGN file.'));

	$file['name'] = sprintf(wrap_setting('pgn_dir').'/%s/%s.pgn', $event['identifier'], $basename);
	if (!file_exists($file['name'])) return false;
	$file['send_as'] = sprintf('%s %s %s (raw).pgn', $event['year'], ($event['series_short'] ?? $event['event']), $basename);
	wrap_send_file($file);
}
