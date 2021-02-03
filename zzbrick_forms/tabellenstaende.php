<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017, 2019-2021 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Tabellenstände zu einem Turnier


$event = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$event) wrap_quit(404);

// Wertungen
$sql = 'SELECT wertung_category_id
	FROM turniere_wertungen
	LEFT JOIN turniere USING (turnier_id)
	WHERE turniere.event_id = %d
	ORDER BY reihenfolge';
$sql = sprintf($sql, $event['event_id']);
$wertungen = wrap_db_fetch($sql, 'wertung_category_id', 'single value');

$zz = zzform_include_table('tabellenstaende');

$zz['where']['event_id'] = $event['event_id'];
$zz['where']['runde_no'] = $brick['vars'][2];

if ($event['turnierform'] === 'e') {
	unset($zz['filter'][1]);
	unset($zz['fields'][11]); // platz_brett_no
	unset($zz['fields'][4]); // Team
} else {
	$zz['fields'][4]['sql'] = sprintf('SELECT team_id
			, CONCAT(team, IFNULL(CONCAT(" ", team_no),"")) AS team
		FROM teams
		WHERE event_id = %d
		ORDER BY team, team_no', $event['event_id']);
}

$zz['fields'][5]['sql'] = 'SELECT person_id
		, contact
		, IFNULL(YEAR(geburtsdatum), "unbek.") AS geburtsjahr
		, identifier
	FROM personen
	LEFT JOIN teilnahmen USING (person_id)
	LEFT JOIN contacts USING (contact_id)
	WHERE teilnahmen.usergroup_id = %d
	AND event_id = %d
	ORDER BY last_name, first_name, YEAR(geburtsdatum), identifier';
$zz['fields'][5]['sql'] = sprintf($zz['fields'][5]['sql'], wrap_id('usergroups', 'spieler'), $event['event_id']);
$zz['fields'][5]['unique_ignore'] = ['geburtsjahr', 'identifier'];

$zz['fields'][6]['auto_value'] = 'increment';

if (!isset($_GET['filter']['typ'])) {
	if ($event['turnierform'] !== 'e') {
		$zz['fields'][5]['hide_in_form'] = true; // Spieler
	}
	if ($event['turnierform'] !== 'e') {
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

my_event_breadcrumbs($event);
$zz_conf['breadcrumbs'][] = [
	'linktext' => 'Runden',
	'url' => '/intern/termine/'.$event['identifier'].'/runde/'
];
$zz_conf['breadcrumbs'][] = ['linktext' => 'Tabelle '.$brick['vars'][2].'. Runde'];
