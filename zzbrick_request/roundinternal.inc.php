<?php 

/**
 * tournaments module
 * interal view of pairings or games of a round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023, 2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_roundinternal($params, $settings, $event) {
	if (wrap_setting('tournaments_type_single'))
		return brick_format('%%% forms partien '.implode(' ', $params).' *=event internal=1 %%%');

	if (wrap_setting('tournaments_type_team')) {
		if (count($params) === 4)
			return brick_format('%%% forms partien '.implode(' ', $params).' *=event internal=1 %%%');
		return brick_format('%%% forms paarungen '.implode(' ', $params).' *=event internal=1 %%%');
	}

	return false;
}
