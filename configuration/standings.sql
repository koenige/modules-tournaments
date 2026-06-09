/**
 * tournaments module
 * SQL queries for calculating standings per round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

-- tournaments_scores_team_bw --
/* calculate berlin score for team tournaments */
SELECT team_id, SUM(CASE ergebnis
	WHEN 1 THEN ((1 + bretter_min) - brett_no)
	WHEN 0.5 THEN (((1 + bretter_min) - brett_no) / 2)
	WHEN 0 THEN 0
	ELSE 0 END
) AS score
FROM (
	SELECT paarungen.heim_team_id AS team_id
		, partien.runde_no
		, partien.brett_no
		, partien.heim_wertung AS ergebnis
		, tournaments.bretter_min
	FROM paarungen
	JOIN partien ON partien.paarung_id = paarungen.paarung_id
	JOIN tournaments ON tournaments.event_id = paarungen.event_id
	WHERE paarungen.event_id = %d
	UNION ALL
	SELECT paarungen.auswaerts_team_id AS team_id
		, partien.runde_no
		, partien.brett_no
		, partien.auswaerts_wertung AS ergebnis
		, tournaments.bretter_min
	FROM paarungen
	JOIN partien ON partien.paarung_id = paarungen.paarung_id
	JOIN tournaments ON tournaments.event_id = paarungen.event_id
	WHERE paarungen.event_id = %d
) results
LEFT JOIN teams USING (team_id)
WHERE runde_no <= %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY score DESC, team_id;

-- tournaments_scores_team_rg --
/* get seeding list no per team */
SELECT team_id, setzliste_no
FROM teams
WHERE team_id IN (%s)
ORDER BY setzliste_no, team_id;
