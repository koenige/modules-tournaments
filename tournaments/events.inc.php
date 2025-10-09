<?php 

/**
 * tournaments module
 * events functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mf_tournaments_events($data, $ids) {
	$tournament_categories = [
		wrap_category_id('events/single'),
		wrap_category_id('events/team')
	];
	
	$tournament_event_ids = [];
	foreach ($data as $event_id => $event) {
		if (!array_key_exists('categories', $event)) continue;
		$found = array_intersect(array_keys($event['categories']), $tournament_categories);
		if (!$found) continue;
		$tournament_event_ids[] = $event_id;
	}
	if (!$tournament_event_ids) return $data;
	foreach ($tournament_event_ids as $event_id)
		$data[$event_id]['tournament'] = true;

	$standings = mf_tournaments_final_standings($tournament_event_ids);
	foreach ($standings as $event_id => $event)
		$data[$event_id] += $event;
	return $data;
}
