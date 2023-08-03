<?php 

/**
 * tournaments module
 * form script: tournament round
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include('events/events');
$zz['title'] = 'Rounds';

$zz['where']['main_event_id'] = $brick['data']['event_id'];
$zz['where']['event_category_id'] = wrap_category_id('event/round');

// overwrite event_year with round_no
$zz['fields'][53] = [];
$zz['fields'][53]['title'] = 'Round';
$zz['fields'][53]['field_name'] = 'runde_no';
$zz['fields'][53]['type'] = 'number';
$zz['fields'][53]['required'] = true;
$zz['fields'][53]['hide_in_list'] = true;
$zz['fields'][53]['auto_value'] = 'increment';

if (wrap_setting('tournaments_upload_pgn')) { // 11 = author
	$zz['fields'][11] = [];
	$zz['fields'][11]['title'] = 'PGN file';
	$zz['fields'][11]['field_name'] = 'pgn';
	$zz['fields'][11]['dont_show_missing'] = true;
	$zz['fields'][11]['type'] = 'upload_image';
	$zz['fields'][11]['path'] = [
		'root' => wrap_setting('media_folder').'/pgn/',
		'webroot' => wrap_setting('media_internal_path').'/pgn/',
		'field1' => 'main_event_identifier', 
		'string2' => '/',
		'field2' => 'runde_no',
		'string3' => '.pgn'
	];
	$zz['fields'][11]['input_filetypes'] = ['pgn'];
	$zz['fields'][11]['link'] = [
		'string1' => wrap_setting('media_internal_path').'/pgn/',
		'field1' => 'main_event_identifier',
		'string2' => '/',
		'field2' => 'runde_no',
		'string3' => '.pgn'
	];
	$zz['fields'][11]['optional_image'] = true;
	$zz['fields'][11]['image'][0]['title'] = 'main';
	$zz['fields'][11]['image'][0]['field_name'] = 'main';
	$zz['fields'][11]['image'][0]['path'] = $zz['fields'][23]['path'];
	$zz['fields'][11]['unless']['export_mode']['list_prefix'] = '<br>';
}

$keep_fields = [
	'event_id', 'date_begin', 'date_end', 'time_begin', 'time_end', 'timezone',
	'event', 'event_category_id', 'takes_place', 'published', 'main_event_id',
	'identifier', 'created', 'website_id', 'last_update', 'runde_no', 'pgn'
];

foreach ($zz['fields'] as $no => $field) {
	if (empty($field['field_name']) OR !in_array($field['field_name'], $keep_fields)) {
		unset($zz['fields'][$no]);
		continue;
	}
	switch ($field['field_name']) {
	case 'event':
		$zz['fields'][$no]['explanation'] = 'Can be left blank, will be added automatically';
		$zz['fields'][$no]['required'] = false;
		unset($zz['fields'][$no]['link']);
		break;

	case 'date_begin':
		$zz['fields'][$no]['default'] = $brick['data']['zeitplan_max'];
		break;

	case 'identifier':
		$zz['fields'][$no]['fields'] = [
			'main_event_id[identifier]', 'runde_no'
		];
		$zz['fields'][$no]['conf_identifier']['concat'] = [
			'/', '/runde/'
		];
		break;

	case 'published':
		// published? makes no sense to unpublish rounds
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['value'] = 'yes';
		$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'website_id':
		$zz['fields'][$no]['type'] = 'hidden';
		$zz['fields'][$no]['value'] = $brick['data']['website_id'];
		$zz['fields'][$no]['hide_in_form'] = true;
		$zz['fields'][$no]['hide_in_list'] = true;
		break;

	case 'main_event_id':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;

	case 'created':
		$zz['fields'][$no]['hide_in_form'] = true;
		break;
	}
}

$zz['sql'] = wrap_edit_sql($zz['sql'], 'SELECT', 'main_events.identifier AS main_event_identifier');
$zz['sql'] = wrap_edit_sql($zz['sql'], 'JOIN', 'LEFT JOIN events main_events
		ON events.main_event_id = main_events.event_id');

unset($zz['filter']);

$zz['record']['copy'] = false;

$zz['hooks']['before_insert'][] = 'mf_tournaments_round_event';
$zz['hooks']['before_update'][] = 'mf_tournaments_round_event';
$zz['hooks']['after_upload'][] = 'mf_tournaments_games_update';

$zz['page']['referer'] = '../';

if (wrap_access('tournaments_games', $brick['data']['event_rights']) AND !wrap_access('tournaments_pairings', $brick['data']['event_rights'])) {
	// just allow to upload PGN files
	foreach ($zz['fields'] as $no => $field) {
		switch ($field['field_name']) {
		case 'identifier':
			$zz['fields'][$no]['hide_in_form'] = true;
			break;

		case 'date_end':
			unset($zz['fields'][$no]['display_field']);
		case 'date_begin':
		case 'time_begin':
		case 'time_end':
		case 'runde_no':
		case 'takes_place':
		case 'event':
			$zz['fields'][$no]['type_detail'] = $zz['fields'][$no]['type'];
			$zz['fields'][$no]['type'] = 'display';
			unset($zz['fields'][$no]['explanation']);
			break;
		}
	}
	$zz['record']['delete'] = false;
	$zz['record']['add'] = false;
}
if (wrap_access('tournaments_games', $brick['data']['event_rights']) OR wrap_access('tournaments_pairings', $brick['data']['event_rights'])) {
	if (!empty($brick['data']['turnierform'])) {
		if ($brick['data']['turnierform'] === 'e')
			$zz['details'][0]['title'] = 'Games';
		else
			$zz['details'][0]['title'] = 'Pairings';
		$zz['details'][0]['link'] = [
		// @todo use area
			'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'identifier', 'string1' => '/'
		];
	}
}
if (wrap_access('tournaments_standings', $brick['data']['event_rights'])) {
	$zz['details'][1]['title'] = 'Standings';
	$zz['details'][1]['link'] = [
	// @todo use area
	//	'area' => 'tournaments_standings',
	//	'fields' => ['main_event_identifier', 'runde_no']
		'string0' => wrap_setting('events_internal_path').'/', 'field1' => 'main_event_identifier',
		'string1' => '/tabelle/', 'field2' => 'runde_no', 'string2' => '/'
	];
}
