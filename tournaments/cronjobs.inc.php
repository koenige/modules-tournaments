<?php 

/**
 * tournaments module
 * cron job functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Job-ID auslesen
 *
 * @param string $category Kategorie des Jobs (Kennung)
 * @param int $event_id
 * @param int $runde_no Optional, einzelne Runde eines Termins
 * @return $job_id
 */
function mf_tournaments_job_get_id($typ, $category, $event_id, $runde_no = false) {
	switch ($typ) {
		case 'todo': $status = ' AND ISNULL(start)'; break;
		case 'laufend': $status = ' AND NOT ISNULL(start) AND ISNULL(ende)'; break;
		default: $status = ''; break;
	}

	$sql = 'SELECT job_id FROM jobs
		WHERE job_category_id = %d
		AND event_id = %d
		AND %s
		%s
		LIMIT 1';
	$sql = sprintf($sql,
		wrap_category_id('jobs/'.$category),
		$event_id,
		$runde_no ? 'runde_no = "'.$runde_no.'"' : 'ISNULL(runde_no)',
		$status
	);
	$job_id = wrap_db_fetch($sql, '', 'single value');
	return $job_id;
}

/**
 * Erstelle einen Job im Hintergrund
 *
 * @param string $category Kategorie des Jobs (Kennung)
 * @param int $event_id
 * @param string $runde_no Optional, einzelne Runde eines Termins, mit -live
 * @param int $prioritaet Optional, -20 besser als +20, Standard 0)
 * @return bool
 */
function mf_tournaments_job_create($category, $event_id, $runde_no = false, $prioritaet = 0) {
	global $zz_setting;

	$job_id = mf_tournaments_job_get_id('todo', $category, $event_id, $runde_no);
	if ($job_id) return true;
	
	$sql = 'INSERT INTO jobs (job_category_id, event_id, runde_no, prioritaet)
		VALUES (%d, %d, %s, %d)';
	$sql = sprintf($sql,
		wrap_category_id('jobs/'.$category),
		$event_id,
		$runde_no ? '"'.$runde_no.'"' : 'NULL',
		$prioritaet
	);
	$success = wrap_db_query($sql);
	if (!$success) return false;

	wrap_trigger_protected_url('https://in.schach.in/_jobs/trigger/', $zz_setting['robot_username'], false);
	return true;
}

/**
 * Job als beendet markieren
 *
 * @param string $category Kategorie des Jobs (Kennung)
 * @param bool $success
 * @param int $event_id
 * @param int $runde_no Optional, einzelne Runde eines Termins
 * @return bool
 */
function mf_tournaments_job_finish($category, $success, $event_id, $runde_no = false) {
	global $zz_setting;
	
	$job_id = mf_tournaments_job_get_id('laufend', $category, $event_id, $runde_no);
	if (!$job_id) return false;
	require_once $zz_setting['core'].'/syndication.inc.php'; // wrap_lock()

	$sql = 'SELECT path, request
		FROM jobs
		LEFT JOIN categories
			ON jobs.job_category_id = categories.category_id
		WHERE job_id = %d';
	$sql = sprintf($sql, $job_id);
	$job = wrap_db_fetch($sql);

	$sql = 'UPDATE jobs
		SET ende = NOW(), erfolgreich = "%s"
		WHERE job_id = %d
	';
	$sql = sprintf($sql, $success ? 'ja' : 'nein', $job_id);
	$result = wrap_db_query($sql);
	if (!$result) wrap_error('Update Job fehlgeschlagen: '.$sql);
	$realm = sprintf('%s-%d', $job['path'], $job['request']);
	wrap_unlock($realm);
	
	// Nächsten Lauf anstoßen
	wrap_trigger_protected_url('https://in.schach.in/_jobs/trigger/', $zz_setting['robot_username'], false);
	return true;
}
