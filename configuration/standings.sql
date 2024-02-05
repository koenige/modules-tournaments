/**
 * tournaments module
 * SQL queries for calculating standings per round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- tournaments_scores_team_mp --
/* calculate match points for team tournaments */
SELECT team_id, SUM(mannschaftspunkte) AS rating
FROM paarungen_ergebnisse_view
LEFT JOIN teams USING (team_id)
WHERE runde_no <= %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY rating DESC, team_id;

-- tournaments_scores_team_bp --
/* calculate board points for team tournaments */
SELECT team_id, SUM(brettpunkte) AS rating
FROM paarungen_ergebnisse_view
LEFT JOIN teams USING (team_id)
WHERE runde_no <= %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY rating DESC, team_id;

-- tournaments_scores_team_bhz_mp --
/* calculate buchholz points based on match points for team tournaments, no correction */
SELECT results.team_id, SUM(results_opponents.mannschaftspunkte) AS buchholz
FROM paarungen_ergebnisse_view results
LEFT JOIN teams USING (team_id)
LEFT JOIN tabellenstaende_termine_view
	ON results.team_id = tabellenstaende_termine_view.team_id
	AND results.runde_no <= tabellenstaende_termine_view.runde_no
LEFT JOIN paarungen_ergebnisse_view results_opponents
	ON results_opponents.team_id = results.gegner_team_id
	AND results_opponents.runde_no <= tabellenstaende_termine_view.runde_no
WHERE tabellenstaende_termine_view.runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY results.team_id, tabellenstaende_termine_view.runde_no
ORDER BY buchholz DESC, team_id;

-- tournaments_scores_team_bhz_mp_fide2012 --
/* calculate buchholz points based on match points for team tournaments, correction 2012 */
SELECT team_id, IFNULL(SUM(buchholz_mit_korrektur), 0) AS buchholz_mit_korrektur
FROM buchholz_mit_kampflosen_view
LEFT JOIN teams USING (team_id)
WHERE runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY team_id
ORDER BY buchholz_mit_korrektur DESC, team_id;

-- tournaments_scores_team_bhz_bp --
/* calculate buchholz points based on board points for team tournaments, no correction */
SELECT tabellenstaende_termine_view.team_id
	, SUM(gegners_paarungen.brettpunkte) AS buchholz
FROM paarungen_ergebnisse_view
LEFT JOIN tabellenstaende_termine_view USING (team_id)
LEFT JOIN teams USING (team_id)
LEFT JOIN paarungen_ergebnisse_view gegners_paarungen
	ON gegners_paarungen.team_id = paarungen_ergebnisse_view.gegner_team_id
WHERE paarungen_ergebnisse_view.runde_no <= tabellenstaende_termine_view.runde_no
AND tabellenstaende_termine_view.runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY tabellenstaende_termine_view.team_id
ORDER BY buchholz DESC;

-- tournaments_scores_team_bhz_bp_fide2012 --
/* calculate buchholz points based on board points for team tournaments, correction 2012 */
SELECT tabellenstaende_termine_view.team_id
	, SUM(IF((gegners_paarungen.kampflos = 1), 1, gegners_paarungen.brettpunkte)) AS buchholz_mit_korrektur
FROM paarungen_ergebnisse_view
LEFT JOIN tabellenstaende_termine_view USING (team_id)
LEFT JOIN teams USING (team_id)
LEFT JOIN paarungen_ergebnisse_view gegners_paarungen
	ON gegners_paarungen.team_id = paarungen_ergebnisse_view.gegner_team_id
WHERE paarungen_ergebnisse_view.runde_no <= tabellenstaende_termine_view.runde_no
AND tabellenstaende_termine_view.runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
GROUP BY tabellenstaende_termine_view.team_id
ORDER BY buchholz_mit_korrektur DESC;
		
-- tournaments_scores_team_sw --
/* calculate wins for team tournaments */
SELECT team_id, gewonnen
FROM tabellenstaende_guv_view
LEFT JOIN teams USING (team_id)
WHERE runde_no = %d
AND team_status = "Teilnehmer"
AND spielfrei = "nein"
ORDER BY gewonnen DESC, team_id;

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
ORDER BY setzliste_no;
