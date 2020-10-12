<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Export tournament data for ChessBase


function mod_tournaments_tournamentexport_cb($params) {
	global $zz_setting;

	if (count($params) !== 2) return false;
	$event = my_event($params[0], $params[1]);
	if (!$event) return false;
	$event['path'] = str_replace('/', '-', $event['kennung']);
	parse_str($event['parameters'], $parameters);
	if ($parameters) $event += $parameters;
	if (empty($event['team'])) return false; // only for team tournaments

	// get teams
	// get players per team
	$code = '';
	$sql = 'SELECT team_id, team, team_no
		FROM teams
		WHERE event_id = %d AND team_status = "Teilnehmer"
		ORDER BY setzliste_no, team, team_no';
	$sql = sprintf($sql, $event['event_id']);
	$teams = wrap_db_fetch($sql, 'team_id');
	foreach ($teams as $team_id => $team) {
		if (!$code) $code = 'AAA';
		else $code++;
		$teams[$team_id]['code'] = $code;
	}

	$sql = 'SELECT team_id, teilnahme_id
			, CONCAT(t_nachname, ",", t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), "")) AS player
			, brett_no AS board
		FROM teilnahmen
		WHERE team_id IN (%s) AND NOT ISNULL(brett_no)
		AND teilnahme_status = "Teilnehmer"
		ORDER BY team_id, brett_no';
	$sql = sprintf($sql, implode(',', array_keys($teams)));
	$players = wrap_db_fetch($sql, ['team_id', 'teilnahme_id']);

	foreach ($players as $team_id => $teamplayers) {
		$teams[$team_id]['players'] = $teamplayers;
	}

	$page['text'] = wrap_template('tournamentexport-cb', $teams);
	$page['content_type'] = 'txt';
	$page['headers']['filename'] = $event['path'].'.txt';
	return $page;
}
