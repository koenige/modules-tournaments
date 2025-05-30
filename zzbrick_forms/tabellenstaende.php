<?php 

/**
 * tournaments module
 * form script: standings of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2015, 2017, 2019-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (count($brick['vars']) === 1 AND strstr($brick['vars'][0], '/'))
	$brick['vars'] = explode('/', $brick['vars'][0]);

// Wertungen
$sql = 'SELECT wertung_category_id
	FROM turniere_wertungen
	LEFT JOIN tournaments USING (tournament_id)
	WHERE tournaments.event_id = %d
	ORDER BY reihenfolge';
$sql = sprintf($sql, $brick['data']['event_id']);
$wertungen = wrap_db_fetch($sql, 'wertung_category_id', 'single value');

$zz = zzform_include('tabellenstaende');

$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['runde_no'] = $brick['vars'][2];

if (wrap_setting('tournaments_type_single')) {
	unset($zz['filter'][1]);
	unset($zz['fields'][11]); // platz_brett_no
	unset($zz['fields'][4]); // Team
} else {
	$zz['fields'][4]['sql'] = sprintf('SELECT team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
		FROM teams
		WHERE event_id = %d
		ORDER BY team, team_no', $brick['data']['event_id']);
}

$zz['fields'][5]['sql'] = 'SELECT person_id
		, contact
		, IFNULL(YEAR(date_of_birth), "unbek.") AS geburtsjahr
		, identifier
	FROM persons
	LEFT JOIN participations USING (contact_id)
	LEFT JOIN contacts USING (contact_id)
	WHERE participations.usergroup_id = /*_ID usergroups spieler _*/
	AND event_id = %d
	ORDER BY last_name, first_name, YEAR(date_of_birth), identifier';
$zz['fields'][5]['sql'] = sprintf($zz['fields'][5]['sql'], $brick['data']['event_id']);
$zz['fields'][5]['unique_ignore'] = ['geburtsjahr', 'identifier'];

$zz['fields'][6]['auto_value'] = 'increment';

if (!isset($_GET['filter']['typ'])) {
	if (wrap_setting('tournaments_type_team')) {
		$zz['fields'][5]['hide_in_form'] = true; // Spieler
		$zz['fields'][11]['hide_in_form'] = true; // Spieler-Platz
	}
	$zz['fields'][10]['min_records'] =
	$zz['fields'][10]['max_records'] = count($wertungen);
	$zz['fields'][10]['fields'][3]['def_val_ignore'] = true;

	$i = 0;
	foreach ($wertungen as $wertung) {
		$zz['fields'][10]['values'][$i][3] = $wertung;
		$i++;
	}
} elseif ($_GET['filter']['typ'] === 'NULL') {
	$zz['fields'][4]['hide_in_form'] = true; // Teams
	$zz['fields'][6]['hide_in_form'] = true; // Platz
}

// @todo we are below rounds, make this breadcrumb superfluous
$zz['page']['breadcrumbs'][] = [
	'title' => 'Runden',
	'url_path' => wrap_setting('events_internal_path').'/'.$brick['data']['identifier'].'/runde/'
];
$zz['page']['breadcrumbs'][] = ['title' => 'Tabelle '.$brick['vars'][2].'. Runde'];
$zz['page']['dont_show_title_as_breadcrumb'] = true;
