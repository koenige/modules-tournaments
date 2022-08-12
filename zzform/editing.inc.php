<?php 

/**
 * tournaments module
 * functions for editing values in fields inside zzform
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Teams: Anzeigename ausfüllen, wenn nicht vom Nutzer ausgefüllt
 *
 * @param array $fields
 * @param string $field
 * @return string
 */
function mf_tournaments_team_name($fields, $field) {
	// Daten vorhanden? Nicht überschreiben
	if (!empty($fields[$field])) return $fields[$field];

	if (empty($fields['club_contact_id'])) return '';
	$sql = 'SELECT contact FROM contacts WHERE contact_id = %d';
	$sql = sprintf($sql, $fields['club_contact_id']);
	$value = wrap_db_fetch($sql, '', 'single value');
	if (!empty($fields['team_no'])) {
		$value .= ' '.$fields['team_no'];
	}
	return $value;
}
