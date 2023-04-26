<?php

/**
 * tournaments module
 * trigger a background job
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_jobtrigger($params) {
	wrap_setting('log_username', wrap_setting('robot_username'));
	wrap_setting('log_trigger', 'cron');

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$data['show_form'] = true;
		$page['text'] = wrap_template('jobtrigger', $data);
		return $page;
	}

	if (!brick_access_rights()) wrap_quit(403);

	require_once wrap_setting('core').'/syndication.inc.php'; // wrap_lock()
	
	$sql = 'SELECT category_id, category
			, SUBSTRING_INDEX(path, "/", -1) AS path
			, parameters,
			(SELECT COUNT(*) FROM _jobqueue running
				WHERE categories.category_id = running.job_category_id
				AND NOT ISNULL(running.started) AND ISNULL(running.finished)) AS running,
			(SELECT COUNT(*) FROM _jobqueue todo
				WHERE categories.category_id = todo.job_category_id
				AND ISNULL(todo.started) AND ISNULL(todo.finished)) AS todo
		FROM categories
		WHERE main_category_id = %d
		GROUP BY category_id
	';
	$sql = sprintf($sql, wrap_category_id('cronjobs'));
	$categories = wrap_db_fetch($sql, 'category_id');
	
	foreach ($categories as $category) {
		parse_str($category['parameters'], $parameter);
		if (empty($parameter['max_requests'])) $parameter['max_requests'] = 1;
		if (empty($parameter['max_time'])) $parameter['max_time'] = 30;
		$job = [];
		if ($category['running'] < $parameter['max_requests']) {
			$sql = 'SELECT job_id, job_url
				FROM _jobqueue
				WHERE ISNULL(_jobqueue.started) AND ISNULL(_jobqueue.finished)
				AND job_category_id = %d
				ORDER BY priority
				LIMIT 1';
			$sql = sprintf($sql, $category['category_id']);
			$job = wrap_db_fetch($sql);
			$request = 1;
		}
		if (!$job) {
			// Laufen gerade Jobs im Rahmen der vorgegebenen Zeit?
			$sql = 'SELECT COUNT(*)
				FROM _jobqueue
				WHERE job_category_id = %d
				AND NOT ISNULL(_jobqueue.started) AND ISNULL(_jobqueue.finished)
				AND DATE_ADD(started, INTERVAL %d SECOND) > NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$running = wrap_db_fetch($sql, '', 'single value');

			if ($running >= $parameter['max_requests']) continue;
			// abgestürzte Prozesse aus Job-Liste erneut aufrufen
			$sql = 'SELECT job_id, job_url
				FROM _jobqueue
				WHERE job_category_id = %d
				AND NOT ISNULL(_jobqueue.started) AND ISNULL(_jobqueue.finished)
				AND DATE_ADD(started, INTERVAL %d SECOND) < NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$jobs = wrap_db_fetch($sql, 'job_id');
			// nur soviele Jobs wie max_requests
			$job = array_shift($jobs);
			// @todo nicht endlos probieren, sondern irgendwann auf inaktiv stellen!
			$request = $running + 1;
		}
		if (!$job) continue;
		$realm = sprintf('%s-%d', $category['path'], $request);
		$locked = wrap_lock($realm, 'sequential', $parameter['max_time']);
		if ($locked) continue;
		$sql = 'UPDATE _jobqueue SET started = NOW(), job_category_no = %d WHERE job_id = %d';
		$sql = sprintf($sql, $request, $job['job_id']);
		$success = wrap_db_query($sql, E_USER_NOTICE);
		if (!$success) continue;

		wrap_trigger_protected_url($job['job_url']);
		sleep(5); // Warte etwas, bevor es weitergeht.
	}

	// @todo genau beschreiben, was getriggert wurde
	$page['text'] = wrap_print($categories);
	return $page;
}
