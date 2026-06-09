<?php 

/**
 * tournaments module
 * contact functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mf_tournaments_contact($data, $ids) {
	$contact_id = key($data);
	// we need data from activities module
	if (empty($data[$contact_id]['participations'])) return $data;
	
	$players = 'spieler'; // @todo internationalize
	
	// tournaments?
	$participations = [];
	foreach ($data[$contact_id]['participations'] as $participation_id => $participation) {
		if ($participation['usergroup_id'] !== wrap_id('usergroups', $players)) continue;
		$participations[$participation_id] = $participation;
		unset($data[$contact_id]['participations'][$participation_id]);
	}
	if (!$participations) return $data;
	
	$sql = 'SELECT participation_id
			, event
			, events.identifier AS event_identifier
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
			, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
			, brett_no
			, t_verein, t_dwz, t_elo
			, IF(status_category_id != /*_ID categories participation-status/participant _*/, participation_status.category, "") AS teilnahme_status
			, standings.rank_no
			, IF (turnierformen.parameters LIKE "%%&team=1%%", 1, NULL) AS mannschaftsturnier
			, IF (turnierformen.parameters LIKE "%%&team=0%%", 1, NULL) AS einzelturnier
			, IF (ISNULL(brett_no) AND turnierformen.parameters LIKE "%%&team=1%%", 1, NULL) AS nur_gemeldet
		FROM participations
		LEFT JOIN categories participation_status
			ON participations.status_category_id = participation_status.category_id
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN categories
			ON usergroups.usergroup_category_id = categories.category_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN teams USING (team_id)
		LEFT JOIN standings
			ON standings.event_id = events.event_id
			AND IF(ISNULL(participations.team_id), standings.person_id = persons.person_id, standings.team_id = participations.team_id)
			AND standings.runde_no = tournaments.tabellenstand_runde_no
		WHERE participations.participation_id IN (%s)
		ORDER BY participations.date_begin DESC, participations.date_end DESC, events.date_begin DESC, events.date_end DESC';
	$sql = sprintf($sql, implode(',', array_keys($participations)));
	$tournaments = wrap_db_fetch($sql, 'participation_id');
	foreach ($tournaments as $participation_id => $participation) {
		$data[$contact_id][$players][$participation_id] = $participations[$participation_id];
		$data[$contact_id][$players][$participation_id] += $tournaments[$participation_id];
		unset($participations[$participation_id]);
	}
	foreach ($participations as $participation_id => $participation) {
		$data[$contact_id][$players][$participation_id] = $participations[$participation_id];
	}
	
	$data['templates']['contact_6'][] = 'contact-tournaments';
	return $data;
}
