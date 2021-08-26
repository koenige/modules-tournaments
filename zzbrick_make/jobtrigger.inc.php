<?php

/**
 * tournaments module
 * trigger a job
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_jobtrigger($params) {
	global $zz_conf;
	global $zz_setting;
	$zz_setting['log_username'] = $zz_setting['robot_username'];
	$zz_setting['log_trigger'] = 'cron';

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		$data['show_form'] = true;
		$page['text'] = wrap_template('jobtrigger', $data);
		return $page;
	}

	if (!brick_access_rights(['Webmaster'])) {
		wrap_quit(403);
	}
	require_once $zz_setting['core'].'/syndication.inc.php'; // wrap_lock()
	
	$sql = 'SELECT category_id, category
			, SUBSTRING_INDEX(path, "/", -1) AS path
			, parameters,
			(SELECT COUNT(laufend.job_id) FROM jobs laufend
				WHERE categories.category_id = laufend.job_category_id
				AND NOT ISNULL(laufend.start) AND ISNULL(laufend.ende)) AS laufend,
			(SELECT COUNT(todo.job_id) FROM jobs todo
				WHERE categories.category_id = todo.job_category_id
				AND ISNULL(todo.start) AND ISNULL(todo.ende)) AS todo
		FROM categories
		WHERE main_category_id = %d
		GROUP BY category_id
	';
	$sql = sprintf($sql, wrap_category_id('jobs'));
	$categories = wrap_db_fetch($sql, 'category_id');
	
	foreach ($categories as $category) {
		parse_str($category['parameters'], $parameter);
		if (empty($parameter['max_requests'])) $parameter['max_requests'] = 1;
		if (empty($parameter['max_time'])) $parameter['max_time'] = 30;
		$job = [];
		if ($category['laufend'] < $parameter['max_requests']) {
			$sql = 'SELECT job_id, events.identifier AS event_identifier, jobs.runde_no
				FROM jobs
				LEFT JOIN events USING (event_id)
				WHERE ISNULL(jobs.start) AND ISNULL(jobs.ende)
				AND job_category_id = %d
				ORDER BY prioritaet
				LIMIT 1';
			$sql = sprintf($sql, $category['category_id']);
			$job = wrap_db_fetch($sql);
			$request = 1;
		}
		if (!$job) {
			// Laufen gerade Jobs im Rahmen der vorgegebenen Zeit?
			$sql = 'SELECT job_id, events.identifier AS event_identifier, jobs.runde_no
				FROM jobs
				LEFT JOIN events USING (event_id)
				WHERE job_category_id = %d
				AND NOT ISNULL(jobs.start) AND ISNULL(jobs.ende)
				AND DATE_ADD(start, INTERVAL %d SECOND) > NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$laufend = wrap_db_fetch($sql, 'job_id');

			if (count($laufend) >= $parameter['max_requests']) continue;
			// abgestürzte Prozesse aus Job-Liste erneut aufrufen
			$sql = 'SELECT job_id, events.identifier AS event_identifier, jobs.runde_no
				FROM jobs
				LEFT JOIN events USING (event_id)
				WHERE job_category_id = %d
				AND NOT ISNULL(jobs.start) AND ISNULL(jobs.ende)
				AND DATE_ADD(start, INTERVAL %d SECOND) < NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$jobs = wrap_db_fetch($sql, 'job_id');
			// nur soviele Jobs wie max_requests
			$job = array_shift($jobs);
			// @todo nicht endlos probieren, sondern irgendwann auf inaktiv stellen!
			$request = count($laufend) + 1;
		}
		if (!$job) continue;
		$realm = sprintf('%s-%d', $category['path'], $request);
		$locked = wrap_lock($realm, 'sequential', $parameter['max_time']);
		if ($locked) continue;
		$sql = 'UPDATE jobs SET start = NOW(), request = %d WHERE job_id = %d';
		$sql = sprintf($sql, $request, $job['job_id']);
		$success = wrap_db_query($sql);
		if (!$success) continue;

		$url = sprintf('/_jobs/%s/%s/%s',
			$category['path'], $job['event_identifier'],
			$job['runde_no'] ? $job['runde_no'].'/' : ''
		);
		wrap_trigger_protected_url($url);
		sleep(5); // Warte etwas, bevor es weitergeht.
	}

	// @todo genau beschreiben, was getriggert wurde
	$page['text'] = wrap_print($categories);
	return $page;
}
