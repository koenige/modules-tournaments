<?php 

/**
 * tournaments module
 * form script: PDF upload for all registration PDFs of all teams of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2019, 2021-2024, 2026 Gustaf Mossakowski
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
	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
	case 'team_id':
		break;

	case 'last_update':
		$zz['fields'][$no]['class'] = 'hidden';
		break;

	default:
		unset($zz['fields'][$no]);
		break;
	}
}

mf_tournaments_upload_fields($zz, 50, $brick['data']);

$zz['access'] = 'edit_only';
$zz['record']['no_ok'] = true;

// keine Tabellenaktualisierung
unset($zz['hooks']['after_update']);

$zz['page']['breadcrumbs'][]['title'] = 'PDF Upload';
