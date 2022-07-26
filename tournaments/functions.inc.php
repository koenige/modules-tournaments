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

/**
 * read identifiers of persons per federation
 * Kennungen einzelner Verbände auslesen
 *
 * @param array $players indexed by person_id
 * @param array $categories
 * @return array
 */
function mf_tournaments_person_identifiers($players, $categories) {
	if (!$players) return $players;
	$sql = 'SELECT person_id
			, contacts_identifiers.identifier
			, SUBSTRING_INDEX(categories.path, "/", -1) AS category
		FROM contacts_identifiers
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN categories
			ON contacts_identifiers.identifier_category_id = categories.category_id
		WHERE person_id IN (%s) AND current = "yes"';
	$sql = sprintf($sql, implode(',', array_keys($players)));
	$identifiers = wrap_db_fetch($sql, ['person_id', 'category', 'identifier'], 'key/value');
	foreach ($identifiers as $person_id => $pk) {
		foreach ($categories as $category) {
			$key = str_replace('-', '_', $category);
			if (!array_key_exists($category, $pk)) continue;
			$players[$person_id][$key] = $pk[$category];
		}
	}
	return $players;
}
