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

-- tournaments_scores_team_bhz_mp_fide2012 --
/* calculate buchholz points based on match points for team tournaments, correction 2012 */
SELECT team_id, IFNULL(SUM(buchholz_mit_korrektur), 0) AS rating
FROM buchholz_mit_kampflosen_view
LEFT JOIN teams USING (team_id)
WHERE runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY rating DESC, team_id;

-- tournaments_scores_team_bw --
/* calculate berlin rating for team tournaments */
SELECT team_id, SUM(CASE ergebnis
	WHEN 1 THEN ((1 + tournaments.bretter_min) - results.brett_no)
	WHEN 0.5 THEN (((1 + tournaments.bretter_min) - results.brett_no) / 2)
	WHEN 0 THEN 0
	ELSE 0 END
) AS rating
FROM partien_ergebnisse_view results
LEFT JOIN tournaments USING (event_id)
LEFT JOIN teams USING (team_id)
WHERE runde_no <= %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY rating DESC, team_id;

-- tournaments_scores_team_rg --
/* get seeding list no per team */
SELECT team_id, setzliste_no
FROM teams
WHERE team_id IN (%s)
ORDER BY setzliste_no, team_id;
