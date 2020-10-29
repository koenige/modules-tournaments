<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Common functions for tournaments


/**
 * Rechnet Angaben zu Livebrettern in tatsächliche Bretter um
 *
 * @param string $livebretter
 *		4, 5-7, *
 * @param int $brett_max
 * @param int $tisch_max (optional)
 * @return array
 * @todo support für Mannschaftsturniere mit Tisch_no
 */
function my_livebretter($livebretter, $brett_max, $tisch_max = false) {
	if ($livebretter === '*') {
		if ($tisch_max) { // @todo
//			$data = range(1, $tisch_max);
//			return $data;
		} else {
			return range(1, $brett_max);
		}
	}
	$data = [];
	$livebretter = explode(',', $livebretter);
	if (!is_array($livebretter)) $livebretter = [$livebretter];
	foreach ($livebretter as $bretter) {
		$bretter = trim($bretter);
		if (strstr($bretter, '-')) {
			$bretter_von_bis = explode('-', $bretter);
			$bretter_von = $bretter_von_bis[0];
			$bretter_bis = $bretter_von_bis[1];
		} else {
			$bretter_von = $bretter;
			$bretter_bis = $bretter;
		}
		
		if (strstr($bretter_von, '.')) {
			// Tische und Bretter
			$tisch_von = explode('.', $bretter_von);
			$tisch_bis = explode('.', $bretter_bis);
			$brett_von = $tisch_von[1];
			$brett_bis = $tisch_bis[1];
			$tisch_von = $tisch_von[0];
			$tisch_bis = $tisch_bis[0];
			for ($i = $tisch_von; $i <= $tisch_bis; $i++) {
				if ($i === $tisch_von) {
					$range = range($brett_von, $brett_max);
				} elseif ($i === $tisch_bis) {
					$range = range(1, $brett_bis);
				} else {
					$range = range(1, $brett_max);
				}
				foreach ($range as $brett) {
					$data[] = $i.'.'.$brett;
				}
			}
		} else {
			$data = array_merge($data, range($bretter_von, $bretter_bis));
		}
	}
	return $data;
}
