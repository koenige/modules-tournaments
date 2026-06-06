<?php 

/**
 * tournaments module
 * team rating helpers
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Berechnet den DWZ-Schnitt eines Teams
 *
 * @param int $event_id
 * @param array $teams Liste der Teams, indiziert nach team_id
 * @param int $bretter_min
 * @return array
 *		int dwz_schnitt
 *		array $teams, Liste wie in params, nur mit Feld 'dwz_schnitt' pro Team
 */
function mf_tournaments_team_rating_average_dwz($event_id, $teams, $bretter_min, $pseudo_dwz) {
	// DWZ-Schnitt der Teams berechnen
	$sql = 'SELECT participation_id, brett_no, rang_no, team_id, t_dwz
		FROM participations
		LEFT JOIN teams USING (team_id)
		WHERE participations.event_id = %d
		AND usergroup_id = /*_ID usergroups spieler _*/
		AND (meldung = "komplett" OR meldung = "teiloffen")
		AND (ISNULL(spielberechtigt) OR spielberechtigt != "nein")
		AND teams.team_status = "Teilnehmer"
		ORDER BY team_id, ISNULL(brett_no), brett_no, t_dwz DESC, t_elo DESC, rang_no';
	$sql = sprintf($sql, $event_id);
	$dwz = wrap_db_fetch($sql, ['team_id', 'participation_id']);
	if (!$dwz) return [NULL, $teams];
	
	$event_dwz_schnitt = 0;
	$dwz_personen = 0;
	foreach (array_keys($teams) as $team_id) {
		if (!is_numeric($team_id)) continue;
		$teams[$team_id]['dwz_schnitt'] = 'k. A.';
	}
	if (!$bretter_min) {
		wrap_log('Keine Mindestbrettzahl angegeben, kann keinen DWZ-Schnitt berechnen');
		return [$event_dwz_schnitt, $teams];
	}
	foreach ($dwz as $team_id => $spieler) {
		$i = $bretter_min;
		$teams[$team_id]['dwz_schnitt'] = 0;
		$dwz_team_personen = 0;
		foreach ($spieler as $person) {
			if (!$i) break;
			$i--;
			if ($person['t_dwz']) {
				$teams[$team_id]['dwz_schnitt'] += $person['t_dwz'];
				$dwz_team_personen++;
			} elseif ($pseudo_dwz) {
				$teams[$team_id]['dwz_schnitt'] += $pseudo_dwz;
				$dwz_team_personen++;
			}
		}
		$event_dwz_schnitt += $teams[$team_id]['dwz_schnitt'];
		if ($dwz_team_personen) {
			$teams[$team_id]['dwz_schnitt'] = round(($teams[$team_id]['dwz_schnitt'] / $dwz_team_personen), 0);
			$dwz_personen += $dwz_team_personen;
		} else {
			$teams[$team_id]['dwz_schnitt'] = '–';
		}
	}
	if ($dwz_personen) {
		$event_dwz_schnitt = round(($event_dwz_schnitt / $dwz_personen), 0);
	}
	return [$event_dwz_schnitt, $teams];
}
