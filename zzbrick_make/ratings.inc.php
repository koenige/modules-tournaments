<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) .... Jacob Roggon
// Copyright (c) 2013-2014, 2016-2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// import rating data


/**
 * import rating data
 *
 * @param string $rating
 * @return array $data
 */
function mod_tournaments_make_ratings($params) {
	global $zz_setting;

	// @todo show webpage with possible downloads if there are no parameters,
	// allow to trigger downloads

	// @todo show webpage form that allows to trigger download for this rating file
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;

	if (count($params) !== 2) return false;
	if (!in_array($params[0], ['download', 'import'])) return false;
	if (empty($zz_setting['rating_download'][$params[1]])) return false;

	// big files, no timeout please
	$zz_setting['syndication_timeout_ms'] = false;
	
	$filename = __DIR__.'/ratings-'.$params[0].'.inc.php';
	require_once $filename;
	$function = 'mod_tournaments_make_ratings_'.strtolower($params[0]);
	return $function([$params[1]]);
}
