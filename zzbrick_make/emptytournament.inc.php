<?php

/**
 * tournaments module
 * empty a tournament of games, pairing, players and teams
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_emptytournament($params, $settings, $event) {
	$sql = 'SELECT partie_id
		FROM partien
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['games'] = wrap_db_fetch($sql, 'partie_id', 'single value');
	$event['games_count'] = count($event['games']);

	$sql = 'SELECT paarung_id
		FROM paarungen
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['pairings'] = wrap_db_fetch($sql, 'paarung_id', 'single value');
	$event['pairings_count'] = count($event['pairings']);

	$sql = 'SELECT tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['standings'] = wrap_db_fetch($sql, 'tabellenstand_id', 'single value');
	$event['standings_count'] = count($event['standings']);

	// get participants, but only imported
	// no need to restrict to usergroup players, because just players are imported
	$sql = 'SELECT participations.participation_id
		FROM participations
		LEFT JOIN participations_categories
			ON participations_categories.participation_id = participations.participation_id
		WHERE event_id = %d
		AND participations_categories.category_id = /*_ID categories participations/registration/import _*/';
	$sql = sprintf($sql, $event['event_id']);
	$event['players'] = wrap_db_fetch($sql, 'participation_id', 'single value');
	$event['players_count'] = count($event['players']);

	$sql = 'SELECT team_id
		FROM teams
		WHERE event_id = %d';
	$sql = sprintf($sql, $event['event_id']);
	$event['teams'] = wrap_db_fetch($sql, 'team_id', 'single value');
	$event['teams_count'] = count($event['teams']);
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// standings are deleted automatically
		$ids = zzform_delete('partien', $event['games']);
		$event['games_deleted'] = count($ids);
		$ids = zzform_delete('paarungen', $event['pairings']);
		$event['pairings_deleted'] = count($ids);
		$ids = zzform_delete('participations', $event['players']);
		$event['players_deleted'] = count($ids);
		$ids = zzform_delete('teams', $event['teams']);
		$event['teams_deleted'] = count($ids);
		$event['entries_deleted'] = true;
	}

	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('empty-tournament', $event);
	return $page;
}
