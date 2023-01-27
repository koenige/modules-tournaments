<?php

/**
 * tournaments module
 * show tournament details
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * show tournament details depending on identifier
 *
 * @param array $params
 * @return array $page
 */
function mod_tournaments_tournamentdetails($params, $settings, $event) {
	if (empty($event['event_tournament'])) return false;
	if (empty($event['website_id'])) return false;

	if (count($params) === 2 AND $event['sub_series'])
		return brick_format('%%% request tournamentseries * *=event %%%');
	elseif (count($params) === 2)
		return brick_format('%%% request tournament * *=event %%%');
	elseif (count($params) === 3 AND !empty($event['event_team']))
		return brick_format('%%% request team * *=team status=all %%%');
	
	return false;
}
