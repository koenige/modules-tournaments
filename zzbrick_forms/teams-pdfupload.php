<?php 

/**
 * tournaments module
 * form script: PDF upload for all registration PDFs of all teams of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2019, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
$data = my_team_form($brick['vars'], ['komplett']);

$zz = zzform_include_table('teams');

$zz_conf['footer_text'] = wrap_template('team-pdfupload');
$data['head'] = true;
$zz['explanation'] = wrap_template('team-pdfupload', $data);
$zz['page'] = my_team_form_page($data, 'PDF Upload');
$zz['title'] = '';
$zz['where']['team_id'] = $data['team_id'];

$fields = [1, 28, 29, 20];
if ($data['gastspieler']) $fields[] = 30;
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) unset($zz['fields'][$no]);
}

$zz['fields'][20]['class'] = 'hidden';
$zz['fields'][28]['dont_show_missing'] = false;

$zz['access'] = 'edit_only';
$zz_conf['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);
