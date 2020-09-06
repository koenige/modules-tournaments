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

	if (count($params) !== 2) return false;
	if (!in_array($params[0], ['download', 'import'])) return false;
	if (empty($zz_setting['rating_download'][$params[1]])) return false;

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


/**
 *
 * common functions
 *
 */


/**
 * unpack archive
 *
 * @param string $archive filename of archive
 * @param string $dest_folder name of destination folder
 * @return bool true: keine Fehler; sonst exit 503
 */
function mod_tournaments_make_ratings_unzip($archive, $dest_folder) {
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive;
		$res = $zip->open($archive);
		if ($res === true) {
			$zip->extractTo($dest_folder);
			$zip->close();
			return true;
		}
		wrap_error(sprintf(wrap_text('Error while unpacking file %s, Code %s'), $archive, $res), E_USER_ERROR);
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
	return true;
}

