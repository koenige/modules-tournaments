<?php 

/**
 * tournaments module
 * form script: games of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (intval($brick['vars'][2]).'' !== $brick['vars'][2]) wrap_quit(404);
$sql = 'SELECT runde_no FROM events
	WHERE main_event_id = %d AND runde_no = "%s"';
$sql = sprintf($sql, $brick['data']['event_id'], $brick['vars'][2]);
$runde_no = wrap_db_fetch($sql, '', 'single value');
if (!$runde_no) wrap_quit(404);

$values = [];
if (count($brick['vars']) === 4) {
	$sql = 'SELECT paarung_id, heim_team_id, auswaerts_team_id
			, (SELECT COUNT(partie_id) FROM partien WHERE partien.paarung_id = paarungen.paarung_id) AS partien
		FROM paarungen WHERE event_id = %d
		AND runde_no = %d AND tisch_no = %d';
	$sql = sprintf($sql, $brick['data']['event_id'], $brick['vars'][2], $brick['vars'][3]);
	$paarung = wrap_db_fetch($sql);
	if (!$paarung) wrap_quit(404);

	$values['where_teams'] = sprintf('AND team_id IN(%d, %d)', $paarung['heim_team_id'],
		$paarung['auswaerts_team_id']);
}

$zz = zzform_include_table('partien', $values);
$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['runde_no'] = $runde_no;
if (count($brick['vars']) === 4) {
	$zz['where']['paarung_id'] = $paarung['paarung_id'];
}

$zz['fields'][13]['default'] = wrap_category_id('partiestatus/normal');

if (count($brick['vars']) === 3) {
	// Einzelturnier
	unset($zz['fields'][2]); // Paarung

	unset($zz['fields'][10]); // Farbe Heimspieler
	unset($zz['fields'][11]); // Teamwertung Heim
	unset($zz['fields'][12]); // Teamwertung Auswärts

	$zz['fields'][5]['auto_value'] = 'increment';

	$zz['fields'][6]['sql'] =
	$zz['fields'][8]['sql'] = sprintf('SELECT person_id
		, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
		FROM teilnahmen
		WHERE usergroup_id = %d
		AND event_id = %d
		ORDER BY t_nachname, t_vorname', wrap_id('usergroups', 'spieler'), $brick['data']['event_id']);
 	// Gruppierung nach Team entfernen
 	unset($zz['fields'][6]['group']);
	unset($zz['fields'][8]['group']);
}

if (count($brick['vars']) === 4) {
	$zz['fields'][6]['if']['add']['default'] = mf_tournaments_get_paring_player($paarung, 'weiss');
	$zz['fields'][8]['if']['add']['default'] = mf_tournaments_get_paring_player($paarung, 'schwarz');
	$zz['fields'][10]['if']['add']['default'] = mf_tournaments_get_paring_player($paarung, 'farbe');
	if ($paarung['partien'] + 1 < $brick['data']['bretter_min']) {
		$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$zz_conf['redirect']['successful_insert'] = $url_path.'?add';
	}
}

$zz_conf['breadcrumbs'][] = [
	'linktext' => 'Runden',
	'url' => '/intern/termine/'.$brick['data']['identifier'].'/runde/'
];
if (count($brick['vars']) === 4) {
	$zz_conf['breadcrumbs'][] = [
		'linktext' => $zz['where']['runde_no'],
		'url' => '/intern/termine/'.$brick['data']['identifier'].'/runde/'.$zz['where']['runde_no'].'/'
	];
	$zz_conf['breadcrumbs'][] = ['linktext' => 'Tisch '.$brick['vars'][3]];

	$sql = 'SELECT COUNT(paarung_id) FROM paarungen WHERE event_id = %d AND runde_no = %d';
	$sql = sprintf($sql, $brick['data']['event_id'], $brick['vars'][2]);
	$tische_max = wrap_db_fetch($sql, '', 'single value');

	if ($brick['vars'][3] < $tische_max) {
		$zz['page']['link']['next'][0]['href'] = '../'.($brick['vars'][3] + 1).'/';	
		$zz['page']['link']['next'][0]['title'] = 'Nächster Tisch';
	}
	if ($brick['vars'][3] > 1) {
		$zz['page']['link']['prev'][0]['href'] = '../'.($brick['vars'][3] - 1).'/';	
		$zz['page']['link']['prev'][0]['title'] = 'Vorheriger Tisch';
	}
} else {
	$zz_conf['breadcrumbs'][] = ['linktext' => 'Runde '.$brick['vars'][2]];
}
$zz_conf['dont_show_title_as_breadcrumb'] = true;
$zz_conf['footer_text'] = '<script type="text/javascript" src="/_behaviour/link-rel-nav.js"></script>';

if (count($brick['vars']) === 3) {
	// Einzelturnier
	$zz_conf['export'][] = 'PDF Ergebniszettel';
	// @todo anders übergeben
	$zz_conf['event'] = $brick['data'];
}

function mf_tournaments_get_paring_player($paarung, $farbe) {
	static $aufstellungen;
	static $partien;
	if (!$aufstellungen) {
		$sql = 'SELECT brett_no, weiss_person_id, schwarz_person_id
			FROM partien WHERE paarung_id = %d
			ORDER BY brett_no DESC LIMIT 1';
		$sql = sprintf($sql, $paarung['paarung_id']);
		$partien = wrap_db_fetch($sql);

		$sql = 'SELECT team_id, person_id FROM teilnahmen
			WHERE team_id IN (%d, %d)
			AND NOT ISNULL(brett_no)
			AND spielberechtigt = "ja"
			AND teilnahme_status = "Teilnehmer"
			ORDER BY brett_no';
		$sql = sprintf($sql, $paarung['heim_team_id'], $paarung['auswaerts_team_id']);
		$aufstellungen = wrap_db_fetch($sql, ['team_id', 'person_id'], 'key/values');
	}
	if (!$aufstellungen) return false;

	// @todo read first board from turniere table
	if (!$partien OR $partien['brett_no'] + 1 & 1) $heim_farbe = 'schwarz';
	else $heim_farbe = 'weiss';
	
	if ($farbe === 'farbe') return str_replace('ss', 'ß', $heim_farbe);

	if (!$partien) {
		// get first players of each team, starting with black for home player
		if ($farbe === $heim_farbe) { // heim_team
			$id = $aufstellungen[$paarung['heim_team_id']][0];
		} else {
			$id = $aufstellungen[$paarung['auswaerts_team_id']][0];
		}
		return $id;
	}
	
	$ha = $heim_farbe === $farbe ? 'heim' : 'auswaerts';
	$pe = $farbe === 'weiss' ? 'schwarz' : 'weiss';
	
	$index = array_search($partien[$pe.'_person_id'], $aufstellungen[$paarung[$ha.'_team_id']]);
	$index++;
	if ($index === count($aufstellungen[$paarung[$ha.'_team_id']])) return false;
	return $aufstellungen[$paarung[$ha.'_team_id']][$index];
}
