<?php 

/**
 * tournaments module
 * formatting functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Ergebnisse schöner formatieren
 *
 * @param string $result
 * @return string
 */
function mf_tournaments_result_format($result) {
	if (preg_match('/^0\.0+$/', $result))
		$result = '0';
	elseif (preg_match('/^(\d+)\.0+$/', $result, $matches))
		$result = $matches[1];
	elseif (preg_match('/^0\.50*$/', $result))
		$result = '&frac12;';
	elseif (preg_match('/^(\d+)\.50*$/', $result, $matches))
		$result = ($matches[1] === '0' ? '' : $matches[1]).'&frac12;';
	elseif (preg_match('/^(\d+)\.250*$/', $result, $matches))
		$result = ($matches[1] === '0' ? '' : $matches[1]).'&frac14;';
	elseif (preg_match('/^(\d+)\.750*$/', $result, $matches))
		$result = ($matches[1] === '0' ? '' : $matches[1]).'&frac34;';
	elseif (preg_match('/^(\d+)\.(\d+?)0*$/', $result, $matches))
		$result = $matches[1].','.$matches[2];
	return $result;
}

/**
 * format minutes
 *
 * @param string $time
 * @return string
 */
function mf_tournaments_minutes_format($time) {
	return number_format($time, 0);
}
