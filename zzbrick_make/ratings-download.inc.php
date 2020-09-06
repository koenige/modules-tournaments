<?php

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) .... Jacob Roggon
// Copyright (c) 2013-2014, 2016-2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// download rating data from other server


/**
 * download rating data from other server
 * download as a ZIP file
 *
 * @param string $rating
 * @return array $data
 */
function mod_tournaments_make_ratings_download($params) {
	global $zz_setting;
	if (count($params) !== 1) return false;
	
	$data = [];
	$data['rating'] = $params[0];
	$data['path'] = strtolower($data['rating']);
	if (empty($zz_setting['rating_download'])) return false; // @todo log error
	if (!array_key_exists($data['rating'], $zz_setting['rating_download'])) return false; // @todo log error
	$data['url'] = $zz_setting['rating_download'][$data['rating']];
	if (!$data['url']) return false;

	// fetches the rating file from the server
	// might take a little longer, but if possible, If-Modified-Since and 304s
	// are taken into account
	require_once $zz_setting['core'].'/syndication.inc.php';
	$rating_data = wrap_syndication_get($data['url'], 'file');
	if (!$rating_data) {
		wrap_error(sprintf(wrap_text('Unable to download rating file for %s.'), $params[0]), E_USER_ERROR);
	}
	// save metadata
	$meta = $rating_data['_'];

	// move current rating file into /_files/[path] folder unless already done
	// 1. create folder
	$year = date('Y', strtotime($meta['Last-Modified']));
	$destination_folder = sprintf($zz_setting['media_folder'].'/'.$data['path'].'/%d', $year);
	if (!file_exists($destination_folder)) mkdir($destination_folder);

	// 2. get filename
	$data['date'] = date('Y-m-d', strtotime($meta['Last-Modified']));
	$filename = $meta['filename'];
	if (strpos($filename, '/') !== false) {
		$filename = substr($filename, strrpos($filename, '/') + 1);
	}
	if (strpos($filename, '%2F') !== false) {
		$filename = substr($filename, strrpos($filename, '%2F') + 3);
	}
	$filename = sprintf('%s-%s', $data['date'], $filename);

	// 3. archive file
	$destination_folder = realpath($destination_folder);
	if (!$destination_folder) {
		wrap_error(sprintf(
			wrap_text('File path for downloaded rating file for %s is wrong: %s/%s.'), $rating, $destination_folder, $filename
		), E_USER_ERROR);
	}
	$data['filename'] = $destination_folder.'/'.$filename;
	if (!file_exists($data['filename'])) {
		copy($meta['filename'], $data['filename']);
	}
	$page['text'] = json_encode($data);
	$page['content_type'] = 'json';
	return $page;
}
