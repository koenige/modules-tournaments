<?php

/**
 * tournaments module
 * import rating data
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Jacob Roggon
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © ... Jacob Roggon
 * @copyright Copyright © 2013-2014, 2016-2017, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * import rating data
 *
 * @param string $rating
 * @return array $data
 */
function mod_tournaments_make_ratings($params) {
	global $zz_setting;
	require_once $zz_setting['core'].'/syndication.inc.php';

	// @todo show webpage with possible downloads if there are no parameters,
	// allow to trigger downloads

	// @todo show webpage form that allows to trigger download for this rating file
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;

	if (count($params) !== 2) return false;
	if (!in_array($params[0], ['download', 'import'])) return false;
	if (empty($zz_setting['rating_download'][$params[1]])) return false;

	// big files, no timeout please
	$zz_setting['syndication_timeout_ms'] = false;

	$lock_realm = strtolower(implode('-', $params));
	$wait_seconds = 300;
	$lock = wrap_lock($lock_realm, 'wait', $wait_seconds);
	if ($lock) {
		$page['text'] = sprintf(wrap_text(
			'Please wait. Rating sync is only allowed to run once every %s.'
		), wrap_duration($wait_seconds));
		return $page;
	}
	
	$filename = __DIR__.'/ratings-'.$params[0].'.inc.php';
	require_once $filename;
	$function = 'mod_tournaments_make_ratings_'.strtolower($params[0]);
	return $function([$params[1]]);
}
