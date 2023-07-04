<?php 

/**
 * tournaments module
 * form script: PDF upload for all registration PDFs of all teams of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2019, 2021-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$brick['page']['title'] .= 'PDF Upload';
$brick['page']['breadcrumbs'][]['title'] = 'PDF Upload';

$zz = zzform_include('teams');

$zz_conf['footer_text'] = wrap_template('team-pdfupload');
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-pdfupload', $brick['data']);
$zz['title'] = '';
$zz['where']['team_id'] = $brick['data']['team_id'];

$fields = [1, 28, 29, 20];
if ($brick['data']['gastspieler']) $fields[] = 30;
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) unset($zz['fields'][$no]);
}

$zz['fields'][20]['class'] = 'hidden';
$zz['fields'][28]['dont_show_missing'] = false;

$zz['access'] = 'edit_only';
$zz_conf['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);
