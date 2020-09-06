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
function mod_tournaments_make_ratings_import($params) {
	global $zz_setting;

	require_once __DIR__.'/ratings-download.inc.php';
	$dl = mod_tournaments_make_ratings_download([$params[0]]);
	$dl = json_decode($dl['text'], true);
	$update = false;
	if (empty($zz_setting['rating_status'][$params[0]])) $update = true;
	elseif ($zz_setting['rating_status'][$params[0]] < $dl['date']) $update = true;
	if (!$update) return false;

	$path = strtolower($params[0]);
	$filename = __DIR__.'/ratings-import-'.$path.'.inc.php';
	require_once $filename;
	$function = 'mod_tournaments_make_ratings_import_'.$path;

	$dest_folder = mod_tournaments_make_ratings_unzip($path, $dl['filename']);
	$data = $function([$dest_folder]);
	if (empty($data)) {
		rmdir($dest_folder);
		wrap_setting_write('rating_status['.$params[0].']', $dl['date']);
		$data['import_successful'] = true;
	}
	$page['text'] = json_encode($data);
	$page['content_type'] = 'json';
	return $page;
}

/**
 * unpack archive
 *
 * @param string $archive filename of archive
 * @param string $dest_folder name of destination folder
 * @return mixed string $dest_folder = successful, false: error
 */
function mod_tournaments_make_ratings_unzip($rating, $archive) {
	global $zz_conf;

	$path = strtolower($rating);
	$tmp_dir = $zz_conf['tmp_dir'].'/'.$path;
	if (!file_exists($tmp_dir)) mkdir($tmp_dir);
	$dest_folder = tempnam($tmp_dir, $path);
	unlink($dest_folder);
	mkdir($dest_folder);

	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		$res = $zip->open($archive);
		if ($res === true) {
			$zip->extractTo($dest_folder);
			$zip->close();
			return $dest_folder;
		}
		wrap_error(sprintf(wrap_text('Error while unpacking file %s, Code %s'), $archive, $res), E_USER_ERROR);
		return false;
	}
	global $zz_setting;
	require_once $zz_setting['lib'].'/unzip/unzip.lib.php';

	$oU = new SimpleUnzip($archive);
	$bF = FALSE;
	foreach ($oU->Entries as $oI) {
		/*printf("%sFile :\n" .
		" * Error = %d\n" .
		" * Errormessage = %s\n" .
		" * Filename = %s\n" .
		" * Path = %s\n" .
		" * Filetime = %s\n" .
		" * Data = #not displayed#\n",
		$nI ? "\n" : '',
		$oI->Error,
		$oI->ErrorMsg,
		$oI->Name,
		$oI->Path,
		date('Y-m-d H:i:s', $oI->Time));*/
		if ($oI->Error != 0) {
			$error_unzip = true;
			continue;
		}
		$bF = TRUE;
		$oF = fopen($dest_folder.'/'.$oI->Name, "w");
		fwrite($oF, $oI->Data);
		fclose($oF); 
	}
	if (isset($error_unzip)) return false;
	return $dest_folder;
}
