<?php 

/**
 * tournaments module
 * cron job functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Job-ID auslesen
 *
 * @param string $category Kategorie des Jobs (Kennung)
 * @param int $event_id
 * @param int $runde_no Optional, einzelne Runde eines Termins
 * @return int
 */
function mf_tournaments_job_get_id($typ, $category, $event_id, $runde_no = false) {
	switch ($typ) {
		case 'todo': $status = ' AND ISNULL(started)'; break;
		case 'laufend': $status = ' AND NOT ISNULL(started) AND ISNULL(finished)'; break;
		default: $status = ''; break;
	}

	$sql = 'SELECT job_id
		FROM _jobqueue
		WHERE job_category_id = %d
		AND event_id = %d
		AND %s
		%s
		LIMIT 1';
	$sql = sprintf($sql,
		wrap_category_id('cronjobs/'.$category),
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
 * @param int $priority Optional, -20 besser als +20, Standard 0)
 * @return bool
 */
function mf_tournaments_job_create($category, $event_id, $runde_no = false, $priority = 0) {
	$job_id = mf_tournaments_job_get_id('todo', $category, $event_id, $runde_no);
	if ($job_id) return true;
	
	$sql = 'INSERT INTO _jobqueue (job_category_id, event_id, runde_no, priority)
		VALUES (%d, %d, %s, %d)';
	$sql = sprintf($sql,
		wrap_category_id('cronjobs/'.$category),
		$event_id,
		$runde_no ? '"'.$runde_no.'"' : 'NULL',
		$priority
	);
	$success = wrap_db_query($sql);
	if (!$success) return false;
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
	$job_id = mf_tournaments_job_get_id('laufend', $category, $event_id, $runde_no);
	if (!$job_id) return false;
	require_once wrap_setting('core').'/syndication.inc.php'; // wrap_lock()

	$sql = 'SELECT path, job_category_no
		FROM _jobqueue
		LEFT JOIN categories
			ON _jobqueue.job_category_id = categories.category_id
		WHERE job_id = %d';
	$sql = sprintf($sql, $job_id);
	$cronjob = wrap_db_fetch($sql);

	$sql = 'UPDATE _jobqueue
		SET finished = NOW(), erfolgreich = "%s"
		WHERE job_id = %d
	';
	$sql = sprintf($sql, $success ? 'ja' : 'nein', $job_id);
	$result = wrap_db_query($sql);
	if (!$result) wrap_error('Update Job fehlgeschlagen: '.$sql);
	$realm = sprintf('%s-%d', $cronjob['path'], $cronjob['job_category_no']);
	wrap_unlock($realm);
	
	// Nächsten Lauf anstoßen
	mf_tournaments_job_trigger();
	return true;
}

/**
 * trigger cron jobs
 *
 * @param void
 * @return void
 */
function mf_tournaments_job_trigger() {
	wrap_trigger_protected_url('https://in.schach.in/_jobs/trigger/', wrap_setting('robot_username'), false);
}

/**
 * delete old cron jobs
 *
 * @return mixed
 */
function mf_tournaments_job_delete() {
	$sql = 'DELETE FROM _jobqueue WHERE finished < DATE_SUB(NOW(), INTERVAL 7 DAY)';
	$result = wrap_db_query($sql);
	return $result;
}
