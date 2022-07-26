<?php 

/**
 * tournaments module
 * form script: Travel data of a team
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2018, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
$data = my_team_form($brick['vars']);

$zz = zzform_include_table('teams');

$zz_conf['footer_text'] = wrap_template('team-reisedaten', $data);
$data['head'] = true;
$zz['explanation'] = wrap_template('team-reisedaten', $data);
$zz['page'] = my_team_form_page($data, 'Reisedaten');
$zz['title'] = '';
$zz['where']['team_id'] = $data['team_id'];

$fields = [1, 14, 15, 34, 35, 20];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) unset($zz['fields'][$no]);
}
unset($zz['fields'][35]['separator']);

$zz['fields'][20]['class'] = 'hidden';

$zz['access'] = 'edit_only';
$zz_conf['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);

// Daten ggf. korrigieren
$zz['hooks']['before_upload'][] = 'my_complete_date';
