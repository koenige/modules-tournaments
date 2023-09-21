<?php 

/**
 * tournaments module
 * form script: games of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (intval($brick['vars'][2]).'' !== $brick['vars'][2]) wrap_quit(404);
$sql = 'SELECT runde_no FROM events
	WHERE main_event_id = %d AND runde_no = "%s"';
$sql = sprintf($sql, $brick['data']['event_id'], $brick['vars'][2]);
$runde_no = wrap_db_fetch($sql, '', 'single value');
if (!$runde_no) wrap_quit(404);

if (count($brick['vars']) === 4) {
	$sql = 'SELECT paarung_id, heim_team_id, auswaerts_team_id
			, (SELECT COUNT(*) FROM partien WHERE partien.paarung_id = paarungen.paarung_id) AS partien
		FROM paarungen WHERE event_id = %d
		AND runde_no = %d AND tisch_no = %d';
	$sql = sprintf($sql, $brick['data']['event_id'], $brick['vars'][2], $brick['vars'][3]);
	$paarung = wrap_db_fetch($sql);
	if (!$paarung) wrap_quit(404);
	if ($paarung['partien'] + 1 < $brick['data']['bretter_min']) {
		$url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$zz['record']['redirect']['successful_insert'] = $url_path.'?add';
	}
}

$zz = zzform_include('partien');

$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['runde_no'] = $runde_no;
if (count($brick['vars']) === 4)
	$zz['where']['paarung_id'] = $paarung['paarung_id'];

foreach ($zz['fields'] as $no => $field) {
	if (empty($field['field_name'])) continue;
	switch ($field['field_name']) {

	case 'brett_no':
		$zz['fields'][$no]['auto_value'] = 'increment';
		break;

	case 'partiestatus_category_id':
		$zz['fields'][$no]['default'] = wrap_category_id('partiestatus/normal');
		break;

	case 'weiss_person_id':
	case 'schwarz_person_id':
		if (count($brick['vars']) === 4) {
			$zz['fields'][$no]['sql'] = 'SELECT person_id, brett_no
					, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
					, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
				FROM participations
				LEFT JOIN persons USING (contact_id)
				LEFT JOIN teams USING (team_id)
				WHERE usergroup_id = %d AND NOT ISNULL(brett_no)
				AND team_id IN(%d, %d)
				ORDER BY team, brett_no, t_nachname, t_vorname';
			$zz['fields'][$no]['sql'] = sprintf($zz['fields'][$no]['sql']
				, wrap_id('usergroups', 'spieler')
				, $paarung['heim_team_id']
				, $paarung['auswaerts_team_id']
			);
			$zz['fields'][$no]['if']['insert']['default'] = mf_tournaments_get_paring_player(
				$paarung, $field['field_name'] === 'weiss_person_id' ? 'weiss' : 'schwarz'
			);
			$zz['fields'][$no]['group'] = 'team';
		} else {
			$zz['fields'][$no]['sql'] = sprintf('SELECT person_id
					, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				FROM participations
				LEFT JOIN persons USING (contact_id)
				WHERE usergroup_id = %d
				ORDER BY t_nachname, t_vorname', wrap_id('usergroups', 'spieler'));
		}
		$zz['fields'][$no]['sql'] = wrap_edit_sql($zz['fields'][$no]['sql'],
			'WHERE', sprintf('participations.event_id = %d', $brick['data']['event_id'])
		);
		break;
	
	case 'heim_spieler_farbe':
		if (count($brick['vars']) === 4)
			$zz['fields'][$no]['if']['insert']['default'] = mf_tournaments_get_paring_player($paarung, 'farbe');
		break;
	}
}

$zz['page']['breadcrumbs'][] = [
	'title' => 'Runden',
	'url_path' => wrap_setting('events_internal_path').'/'.$brick['data']['identifier'].'/runde/'
];
if (count($brick['vars']) === 4) {
	$zz['page']['breadcrumbs'][] = [
		'title' => $zz['where']['runde_no'],
		'url_path' => wrap_setting('events_internal_path').'/'.$brick['data']['identifier'].'/runde/'.$zz['where']['runde_no'].'/'
	];
	$zz['page']['breadcrumbs'][] = ['title' => 'Tisch '.$brick['vars'][3]];

	$sql = 'SELECT COUNT(*) FROM paarungen WHERE event_id = %d AND runde_no = %d';
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
	$zz['page']['breadcrumbs'][] = ['title' => 'Runde '.$brick['vars'][2]];
}
$zz['page']['dont_show_title_as_breadcrumb'] = true;
$zz['footer']['text'] = wrap_template('link-rel-nav');

if (count($brick['vars']) === 3) {
	// Einzelturnier
	$zz['export'][] = 'PDF Ergebniszettel';
	$zz['page']['event'] = $brick['data'];
}

function mf_tournaments_get_paring_player($paarung, $farbe) {
	static $aufstellungen = [];
	static $partien = [];
	if (!$aufstellungen) {
		$sql = 'SELECT brett_no, weiss_person_id, schwarz_person_id
			FROM partien WHERE paarung_id = %d
			ORDER BY brett_no DESC LIMIT 1';
		$sql = sprintf($sql, $paarung['paarung_id']);
		$partien = wrap_db_fetch($sql);

		$sql = 'SELECT team_id, person_id
			FROM participations
			LEFT JOIN persons USING (contact_id)
			WHERE team_id IN (%d, %d)
			AND NOT ISNULL(brett_no)
			AND spielberechtigt = "ja"
			AND status_category_id = %d
			ORDER BY brett_no';
		$sql = sprintf($sql
			, $paarung['heim_team_id']
			, $paarung['auswaerts_team_id']
			, wrap_category_id('participation-status/participant')
		);
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
