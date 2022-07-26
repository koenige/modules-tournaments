<?php 

/**
 * tournaments module
 * common functions for tournaments (not always included)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * add hyphens in long titles (for PDF export)
 *
 * @param string $title
 * @return string
 */
function mf_tournaments_event_title_wrap($title) {
	$title = explode(' ', $title);
	foreach ($title as $pos => &$word) {
		if (strlen($word) < 21) continue;
		if (strstr($word, '-')) {
			$word = str_replace('-', "- ", $word);
			continue;
		}
		if (strstr($word, 'meisterschaft')) {
			$word = str_replace('meisterschaft', '- meisterschaft', $word);
			continue;
		}
	}
	$title = implode(' ', $title);
	return $title;
}

/**
 * read chess PGN file from URL
 *
 * @param int $tournament_id
 * @return string
 * @global array $zz_conf
 */
function mf_tournaments_pgn_file_from_tournament($tournament_id) {
	global $zz_conf;

	$sql = 'SELECT urkunde_parameter
		FROM tournaments
		WHERE tournament_id = %d';
	$sql = sprintf($sql, $tournament_id);
	$parameters = wrap_db_fetch($sql, '', 'single value');
	if (!$parameters) return '';

	parse_str($parameters, $parameters);
	if (empty($parameters['tournaments_pgn_paths'])) return '';

	$pgn = '';
	foreach ($parameters['tournaments_pgn_paths'] as $path) {
		if (in_array(substr($path, 0, 1), ['/', '.'])) {
			// local path
			$path = $zz_conf['root'].$path;
			if (!file_exists($path)) continue;
		}
		if ($content = file_get_contents($path)) {
			$pgn .= $content;
		}
	}
	return $pgn;
}
