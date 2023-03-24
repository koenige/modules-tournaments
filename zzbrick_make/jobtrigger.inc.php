<?php

/**
 * tournaments module
 * trigger a cron job
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
			(SELECT COUNT(*) FROM cronjobs laufend
				WHERE categories.category_id = laufend.cronjob_category_id
				AND NOT ISNULL(laufend.start) AND ISNULL(laufend.ende)) AS laufend,
			(SELECT COUNT(*) FROM cronjobs todo
				WHERE categories.category_id = todo.cronjob_category_id
				AND ISNULL(todo.start) AND ISNULL(todo.ende)) AS todo
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
		$cronjob = [];
		if ($category['laufend'] < $parameter['max_requests']) {
			$sql = 'SELECT cronjob_id, events.identifier AS event_identifier, cronjobs.runde_no
				FROM cronjobs
				LEFT JOIN events USING (event_id)
				WHERE ISNULL(cronjobs.start) AND ISNULL(cronjobs.ende)
				AND cronjob_category_id = %d
				ORDER BY prioritaet
				LIMIT 1';
			$sql = sprintf($sql, $category['category_id']);
			$cronjob = wrap_db_fetch($sql);
			$request = 1;
		}
		if (!$cronjob) {
			// Laufen gerade Jobs im Rahmen der vorgegebenen Zeit?
			$sql = 'SELECT cronjob_id, events.identifier AS event_identifier, cronjobs.runde_no
				FROM cronjobs
				LEFT JOIN events USING (event_id)
				WHERE cronjob_category_id = %d
				AND NOT ISNULL(cronjobs.start) AND ISNULL(cronjobs.ende)
				AND DATE_ADD(start, INTERVAL %d SECOND) > NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$laufend = wrap_db_fetch($sql, 'cronjob_id');

			if (count($laufend) >= $parameter['max_requests']) continue;
			// abgestürzte Prozesse aus Job-Liste erneut aufrufen
			$sql = 'SELECT cronjob_id, events.identifier AS event_identifier, cronjobs.runde_no
				FROM cronjobs
				LEFT JOIN events USING (event_id)
				WHERE cronjob_category_id = %d
				AND NOT ISNULL(cronjobs.start) AND ISNULL(cronjobs.ende)
				AND DATE_ADD(start, INTERVAL %d SECOND) < NOW()
			';
			$sql = sprintf($sql, $category['category_id'], $parameter['max_time']);
			$cronjobs = wrap_db_fetch($sql, 'cronjob_id');
			// nur soviele Jobs wie max_requests
			$cronjob = array_shift($cronjobs);
			// @todo nicht endlos probieren, sondern irgendwann auf inaktiv stellen!
			$request = count($laufend) + 1;
		}
		if (!$cronjob) continue;
		$realm = sprintf('%s-%d', $category['path'], $request);
		$locked = wrap_lock($realm, 'sequential', $parameter['max_time']);
		if ($locked) continue;
		$sql = 'UPDATE cronjobs SET start = NOW(), request = %d WHERE cronjob_id = %d';
		$sql = sprintf($sql, $request, $cronjob['cronjob_id']);
		$success = wrap_db_query($sql, E_USER_NOTICE);
		if (!$success) continue;

		$url = sprintf('/_jobs/%s/%s/%s',
			$category['path'], $cronjob['event_identifier'],
			$cronjob['runde_no'] ? $cronjob['runde_no'].'/' : ''
		);
		wrap_trigger_protected_url($url);
		sleep(5); // Warte etwas, bevor es weitergeht.
	}

	// @todo genau beschreiben, was getriggert wurde
	$page['text'] = wrap_print($categories);
	return $page;
}
