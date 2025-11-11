<?php 

/**
 * tournaments module
 * common functions for tournaments (not always included)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once __DIR__.'/../zzbrick_rights/access.inc.php';

function mf_tournaments_current_round($identifier) {
	$sql = 'SELECT MAX(tabellenstaende.runde_no)
		FROM events
		JOIN tabellenstaende USING (event_id)
		WHERE %s
		GROUP BY events.event_id';
	$sql = sprintf($sql
		, is_numeric($identifier)
			? sprintf('events.event_id = %d', $identifier)
			: sprintf('events.identifier = "%s"', wrap_db_escape($identifier))
	);
	$round = wrap_db_fetch($sql, '', 'single value');
	if (!$round) return 0;
	return $round;
}

/**
 * get live round of an event
 *
 * @param int $event_id
 * @return int
 */
function mf_tournaments_live_round($event_id) {
	$sql = wrap_sql_query('tournaments_live_round');
	$sql = sprintf($sql, $event_id);
	$round_no = wrap_db_fetch($sql, '', 'single value');
	if (!$round_no) return NULL;
	return $round_no;
}

/**
 * Wertet aus, ob Tisch/Brett-Kombination in Livebrettern ist
 *
 * @param string $livebretter
 *		1-10, 12, 17
 *		1.6-1.10, 2.6–4.10
 *		1.*-6.*
 * @param int $brett_no
 * @param int $tisch_no (optional)
 * @return bool
 */
function mf_tournaments_live_board($livebretter, $brett_no, $tisch_no = false) {
	if (!$livebretter) return false;
	if ($livebretter === '*') return true;
	$livebretter = explode(',', $livebretter);
	foreach ($livebretter as $bretter) {
		$bretter = trim($bretter);
		if (strstr($bretter, '-')) {
			$brett_vb = explode('-', $bretter);
		} else {
			$brett_vb[0] = $bretter;
			$brett_vb[1] = $bretter;
		}
		if (strstr($brett_vb[0], '.')) {
			// Tische und Bretter
			$min_bt = explode('.', $brett_vb[0]);
			$max_bt = explode('.', $brett_vb[1]);
			if ($tisch_no < $min_bt[0]) continue;
			if ($tisch_no > $max_bt[0]) continue;
			if ($brett_no < $min_bt[1]) continue;
			if ($brett_no > $max_bt[1]) continue;
			return true;
		} else {
			//  nur Bretter
			if ($brett_no < $brett_vb[0]) continue;
			if ($brett_no > $brett_vb[1]) continue;
			return true;
		}
	}
	return false;
}

/**
 * Rechnet Angaben zu Livebrettern in tatsächliche Bretter um
 *
 * @param string $livebretter
 *		4, 5-7, *
 * @param int $brett_max
 * @param int $tisch_max (optional)
 * @return array
 * @todo support für Mannschaftsturniere mit Tisch_no
 */
function mf_tournaments_live_boards($livebretter, $brett_max, $tisch_max = false) {
	if ($livebretter === '*') {
		if ($tisch_max) { // @todo
//			$data = range(1, $tisch_max);
//			return $data;
		} else {
			return range(1, $brett_max);
		}
	}
	$data = [];
	$livebretter = explode(',', $livebretter);
	if (!is_array($livebretter)) $livebretter = [$livebretter];
	foreach ($livebretter as $bretter) {
		$bretter = trim($bretter);
		if (strstr($bretter, '-')) {
			$bretter_von_bis = explode('-', $bretter);
			$bretter_von = $bretter_von_bis[0];
			$bretter_bis = $bretter_von_bis[1];
		} else {
			$bretter_von = $bretter;
			$bretter_bis = $bretter;
		}
		
		if (strstr($bretter_von, '.')) {
			// Tische und Bretter
			$tisch_von = explode('.', $bretter_von);
			$tisch_bis = explode('.', $bretter_bis);
			$brett_von = $tisch_von[1];
			$brett_bis = $tisch_bis[1];
			$tisch_von = $tisch_von[0];
			$tisch_bis = $tisch_bis[0];
			for ($i = $tisch_von; $i <= $tisch_bis; $i++) {
				if ($i === $tisch_von) {
					$range = range($brett_von, $brett_max);
				} elseif ($i === $tisch_bis) {
					$range = range(1, $brett_bis);
				} else {
					$range = range(1, $brett_max);
				}
				foreach ($range as $brett) {
					$data[] = $i.'.'.$brett;
				}
			}
		} else {
			$data = array_merge($data, range($bretter_von, $bretter_bis));
		}
	}
	return $data;
}

/**
 * get last update for a round
 *
 * @param array $data
 * @param string $last_update
 * @return string
 */
function mf_tournaments_last_update($data, $last_update = '') {
	foreach ($data as $line)
		if ($line['last_update'] > $last_update) $last_update = $line['last_update'];
	return $last_update;
}

/**
 * sende anderen Cache-Control-Header während Turnier
 *
 * @param string $duration
 * @return void
 */
function mf_tournaments_cache($duration) {
	$duration = explode('/', $duration);
	$today = date('Y-m-d');
	if ($today < $duration[0]) return;
	if (empty($duration[1])) return;
	if ($today > $duration[1]) return;
	wrap_cache_header('Cache-Control: max-age=0');
}

/**
 * convert hexadecimal colors to decimal
 * for use in PDF, #CC0000 to red = 204, green = 0, blue = 0
 *
 * @param string $color
 * @return array
 */
function mf_tournaments_colors_hex2dec($color) {
	$dec['red'] = hexdec(substr($color, 1, 2));
	$dec['green'] = hexdec(substr($color, 3, 2));
	$dec['blue'] = hexdec(substr($color, 5, 2));
	return $dec;
}

/**
 * get FIDE title in full
 * FIDE-Titel auslesen in Langform
 *
 * @param string $title
 * @return string
 */
function mf_tournaments_fide_title($title) {
	static $titles = [];
	if (!$titles) {
		$sql = 'SELECT category, category_short, description
			FROM categories
			WHERE main_category_id = /*_ID categories fide-title _*/';
		$titles = wrap_db_fetch($sql, 'category_short');
	}
	if (array_key_exists($title, $titles)) return $titles[$title]['category'];
	return '';
}

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
 */
function mf_tournaments_pgn_file_from_tournament($tournament_id) {
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
			$path = wrap_setting('root_dir').$path;
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
 * @return array
 */
function mf_tournaments_person_identifiers($players) {
	if (!$players) return $players;
	$sql = 'SELECT person_id
			, contacts_identifiers.identifier
			, CONCAT("player_"
				, IFNULL(
					SUBSTRING_INDEX(SUBSTRING_INDEX(categories.parameters, "&alias=identifiers/", -1), "&", 1),
					SUBSTRING_INDEX(categories.path, "/", -1)
				)
			) AS category
		FROM contacts_identifiers
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN categories
			ON contacts_identifiers.identifier_category_id = categories.category_id
		WHERE person_id IN (%s) AND current = "yes"';
	$sql = sprintf($sql, implode(',', array_keys($players)));
	$identifiers = wrap_db_fetch($sql, ['person_id', 'category', 'identifier'], 'key/value');
	foreach ($identifiers as $person_id => $identifiers_per_person)
		$players[$person_id] = array_merge($players[$person_id], $identifiers_per_person);
	return $players;
}

/**
 * get federation per club
 *
 * @param array $data
 * @param string $field_name (optional)
 * @return array
 */
function mf_tournaments_clubs_to_federations($data, $field_name = 'club_contact_id') {
	$mode = 'list';
	if (!is_numeric(key($data))) {
		$mode = 'single';
		$data = [$data];
	}

	$clubs = [];
	foreach ($data as $id => $line) {
		if (is_numeric(key($line))) {
			$mode = 'multi';
			foreach ($line as $sub_id => $sub_line) {
				if (!$sub_line[$field_name]) continue;
				$clubs[$id.'-'.$sub_id] = $sub_line[$field_name];
			}
		} elseif ($line[$field_name]) {
			$clubs[$id] = $line[$field_name];
		}
	}
	if (!$clubs) {
		if ($mode === 'single')
			$data = reset($data);
		return $data;
	}
	
	// get federations
	$sql = 'SELECT contacts.contact_id AS federation_contact_id
			, SUBSTRING(contacts_identifiers.identifier, 1, 1) AS federation_code
			, contacts.identifier AS federation_identifier
			, contact_abbr AS federation_abbr
			, country_id, country
			, regionalgruppe
	    FROM contacts
	    LEFT JOIN contacts_contacts USING (contact_id)
	    LEFT JOIN countries USING (country_id)
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = contacts.contact_id
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
	    WHERE contacts_contacts.main_contact_id = /*_SETTING clubs_confederation_contact_id _*/
	    AND contacts_contacts.relation_category_id = /*_ID categories relation/member _*/
	    AND contacts.contact_category_id = /*_ID categories contact/federation _*/';
	$federations = wrap_db_fetch($sql, 'federation_contact_id');
	$federations_by_zps = [];
	$federations_by_country = [];
	foreach ($federations as $federation) {
		$federations_by_zps[$federation['federation_code']] = $federation;
		$federations_by_country[$federation['country_id']] = $federation;
	}

	// get country and federation per contact
	$sql = 'SELECT contacts.contact_id
			, country_id
			, contacts_identifiers.identifier AS zps_code
			, parameters
	    FROM contacts
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = contacts.contact_id
			AND contacts_identifiers.current = "yes"
	    WHERE contacts.contact_id IN (%s)';
	$sql = sprintf($sql
		, implode(', ', $clubs)
	);
	$contacts = wrap_db_fetch($sql, 'contact_id');

	// merge contacts and federations
	foreach ($contacts as $contact_id => $contact) {
		if ($contact['parameters']) {
			parse_str($contact['parameters'], $contact['parameters']);
			if (!empty($contact['parameters']['tournaments_contact_without_federation'])) continue;
		}
		$fed_code = $contact['zps_code'] ? substr($contact['zps_code'], 0, 1) : '';
		if ($fed_code AND array_key_exists($fed_code, $federations_by_zps)) {
			$contacts[$contact_id] = array_merge($contact, $federations_by_zps[$fed_code]);
		} elseif ($contact['country_id'] AND array_key_exists($contact['country_id'], $federations_by_country)) {
			$contacts[$contact_id] = array_merge($contact, $federations_by_country[$contact['country_id']]);
		} else {
			wrap_error(wrap_text('Unable to get federation for contact ID %d', ['values' => $contact_id]));
		}
		unset($contacts[$contact_id]['contact_id']);
	}

	// merge with $data
	foreach ($clubs as $id => $contact_id) {
		if ($mode === 'multi') {
			$id = explode('-', $id);
			$data[$id[0]][$id[1]] += $contacts[$contact_id];
		} else {
			foreach (array_keys($contacts[$contact_id]) as $field) {
				if (empty($data[$id][$field])) unset($data[$id][$field]);
			}
			$data[$id] += $contacts[$contact_id];
		}
	}
	if ($mode === 'single')
		$data = reset($data);
	
	return $data;
}
