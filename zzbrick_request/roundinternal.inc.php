<?php 

/**
 * tournaments module
 * interal view of pairings or games of a round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_roundinternal($params, $settings, $event) {
	if (count($params) === 4)
		return brick_format('%%% forms partien '.implode(' ', $params).' *=event internal=1 %%%');

	switch ($event['event_category']) {
		case 'einzel':
			return brick_format('%%% forms partien '.implode(' ', $params).' *=event internal=1 %%%');
		case 'mannschaft':
			return brick_format('%%% forms paarungen '.implode(' ', $params).' *=event internal=1 %%%');
	}
	return false;
}
