<?php 

/**
 * tournaments module
 * export of players to Swiss-Chess
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2014, 2016-2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_get_swisschess($vars) {
	global $zz_setting;

	// Land wurde zugunsten der Gastspielergenehmigung fallen gelassen
	//		, SUBSTRING(landesverbaende.contact_abbr, 1, 3) AS land

	$where = 'spielberechtigt != "nein"';
	if (array_key_exists('alle', $_GET)) $where = '(ISNULL(spielberechtigt) OR spielberechtigt != "nein")';

	wrap_db_query('SET NAMES latin1');
	// Abfrage Spalte 2, 3: erste Zeile für MM, zweite für EM
	$sql = 'SELECT
			SUBSTRING(CONCAT(t_nachname, ",", t_vorname), 1, 32) AS name
			, IFNULL(
				CONCAT(SUBSTRING(teams.team, 1, 29), IFNULL(CONCAT(" ", teams.team_no), SUBSTRING(teams.team, 30, 3))),
				t_verein				
			) AS verein
			, IF(NOT ISNULL(team_id),
				IF(gastspieler = "ja", "G", ""),
				landesverbaende.contact_abbr
			) AS land
			, t_elo AS elo
			, t_dwz AS dwz
			, t_fidetitel AS fidetitel
			, DATE_FORMAT(persons.date_of_birth, "%%d.%%m.%%Y") AS geburtsdatum
			, pkz.identifier AS pkz
			, fide.identifier AS fide_kennzahl
			, SUBSTRING_INDEX(pk.identifier, "-", -1) AS teilnehmerkennung
			, IF(persons.sex = "female", "w", "m") AS teilnehmerattribut
			, IF(spielberechtigt = _utf8mb4"vorläufig nein", "N", NULL) AS selektionszeichen
			, SUBSTRING(pk.identifier, 1, 3) AS verband
			, SUBSTRING_INDEX(pk.identifier, "-", 1) AS zps_verein
			, SUBSTRING_INDEX(pk.identifier, "-", -1) AS zps_spieler
			, IF(spielberechtigt = _utf8mb4"vorläufig nein", SUBSTRING(REPLACE(participations.remarks, "\n", "/"), 1, 40), NULL) AS teilnehmer_info_1
			, NULL AS teilnehmer_info_2
			, NULL AS teilnehmer_info_3
			, CONCAT("person_id=", persons.person_id, IFNULL(CONCAT("&team_id=", team_id), "")) AS teilnehmer_info_4
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN teams USING (team_id)
		LEFT JOIN contacts organisationen
			ON teams.club_contact_id = organisationen.contact_id
		LEFT JOIN events
			ON participations.event_id = events.event_id
		LEFT JOIN contacts_identifiers pk
			ON persons.contact_id = pk.contact_id
			AND pk.current = "yes"
			AND pk.identifier_category_id = %d
		LEFT JOIN contacts_identifiers fide
			ON persons.contact_id = fide.contact_id
			AND fide.current = "yes"
			AND fide.identifier_category_id = %d
		LEFT JOIN contacts_identifiers pkz
			ON persons.contact_id = pkz.contact_id
			AND pkz.current = "yes"
			AND pkz.identifier_category_id = %d
		LEFT JOIN contacts_identifiers v_ok
			ON IFNULL(organisationen.contact_id, participations.club_contact_id) = v_ok.contact_id
			AND v_ok.current = "yes"
		LEFT JOIN contacts_identifiers lv_ok
			ON CONCAT(SUBSTRING(v_ok.identifier, 1, 1), "00") = lv_ok.identifier
		LEFT JOIN contacts landesverbaende
			ON lv_ok.contact_id = landesverbaende.contact_id
			AND lv_ok.current = "yes"
			AND landesverbaende.mother_contact_id = %d
		WHERE events.identifier = "%d/%s"
		AND usergroup_id = %d
		AND %s
		AND (ISNULL(teams.team_id) OR teams.team_status = "Teilnehmer")
		ORDER BY team, team_no, rang_no, t_nachname, t_vorname';
	$sql = sprintf($sql
		, wrap_category_id('identifiers/zps')
		, wrap_category_id('identifiers/fide-id')
		, wrap_category_id('identifiers/pkz')
		, $zz_setting['contact_ids']['dsb']
		, $vars[0], wrap_db_escape($vars[1])
		, wrap_id('usergroups', 'spieler')
		, $where
	);
	$data = wrap_db_fetch($sql, 'teilnehmer_info_4');
	if (!$data) {
		wrap_db_query('SET NAMES utf8mb4');
		return false;
	}
	$zz_setting['character_set'] = 'windows-1252';

	$sql = 'SELECT CONCAT(event, " ", YEAR(date_begin)) AS event
		FROM events
		WHERE identifier = "%d/%s"';
	$sql = sprintf($sql
		, $vars[0], wrap_db_escape($vars[1])
	);
	$data['_filename'] = wrap_db_fetch($sql, '', 'single value');
	$data['_extension'] = 'lst';
	$data['_query_strings'] = ['alle'];
	
	$data['_setting']['export_csv_show_empty_cells'] = true;
	$data['_setting']['export_csv_heading'] = false;
	return $data;
}
