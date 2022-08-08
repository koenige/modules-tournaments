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

/**
 * get federation per club
 *
 * @param array $data
 * @param string $field_name
 * @return array
 */
function mf_tournaments_clubs_to_federations($data, $field_name) {
	global $zz_setting;
	
	if (!is_numeric(key($data))) {
		$single_record = true;
		$data = [$data];
	}

	$clubs = [];
	foreach ($data as $id => $line) {
		$clubs[$id] = $line[$field_name];
		unset($data[$id][$field_name]);
	}
	$sql = sprintf('SELECT organisationen.contact_id
			, countries.country
			, IFNULL(landesverbaende.identifier, landesverbaende_rueckwaerts.identifier) AS lv_kennung
			, IFNULL(landesverbaende.contact_abbr, landesverbaende_rueckwaerts.contact_abbr) AS lv_kurz
			, v_ok.identifier AS zps_code
			, regionalgruppe
		FROM contacts organisationen
		LEFT JOIN contacts_identifiers v_ok
			ON v_ok.contact_id = organisationen.contact_id
			AND v_ok.current = "yes"
		LEFT JOIN contacts_identifiers lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
			AND lv_ok.current = "yes"
		LEFT JOIN contacts landesverbaende
			ON lv_ok.contact_id = landesverbaende.contact_id
			AND landesverbaende.mother_contact_id = %d
		LEFT JOIN countries
			ON IFNULL(landesverbaende.country_id, organisationen.country_id) 
				= countries.country_id
		LEFT JOIN contacts landesverbaende_rueckwaerts
			ON countries.country_id = landesverbaende_rueckwaerts.country_id
			AND landesverbaende_rueckwaerts.contact_category_id = %d
			AND landesverbaende_rueckwaerts.mother_contact_id = %d
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = landesverbaende.contact_id
		WHERE organisationen.contact_id IN (%s)
	', $zz_setting['contact_ids']['dsb']
		, wrap_category_id('contact/federation')
		, $zz_setting['contact_ids']['dsb']
		, implode(', ', $clubs)
	);
	$clubdata = wrap_db_fetch($sql, 'contact_id');
	foreach ($clubs as $id => $contact_id) {
		unset($clubdata[$contact_id]['contact_id']);
		$data[$id] += $clubdata[$contact_id];
	}
	if (!empty($single_record))
		$data = reset($data);
	
	return $data;
}
