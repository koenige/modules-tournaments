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
	if (count($params) !== 2) return false;

	$path = strtolower(implode('-', $params));
	if (file_exists($filename = __DIR__.'/ratings-'.$path.'.inc.php')) {
		require_once $filename;
		$function = 'mod_tournaments_make_ratings_'.strtolower(implode('_', $params));
		$function_params = [];
	} elseif (file_exists($filename = __DIR__.'/ratings-'.$params[0].'.inc.php')) {
		require_once $filename;
		$function = 'mod_tournaments_make_ratings_'.strtolower($params[0]);
		$function_params = [$params[1]];
	}
	if (empty($function)) return false;

	return $function($function_params);
}
