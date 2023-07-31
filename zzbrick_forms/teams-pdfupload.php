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



$zz = zzform_include('teams');

$zz['footer']['text'] = wrap_template('team-pdfupload');
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-pdfupload', $brick['data']);
$zz['title'] = '';
//$zz['title'] = 'PDF Upload';
$zz['where']['team_id'] = $brick['data']['team_id'];

foreach ($zz['fields'] as $no => $field) {
	if (empty($field['field_name'])) continue;
	switch ($field['field_name']) {
	case 'team_id':
	case 'ehrenkodex':
		break;
	case 'meldebogen':
		$zz['fields'][$no]['dont_show_missing'] = false;
		break;
	case 'last_update':
		$zz['fields'][$no]['class'] = 'hidden';
		break;
	case 'gastspielgenehmigung':
		if (!$brick['data']['gastspieler']) break;
	default:
		unset($zz['fields'][$no]);
		break;
	}
}

$zz['access'] = 'edit_only';
$zz['record']['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);

$zz['page']['breadcrumbs'][]['title'] = 'PDF Upload';
