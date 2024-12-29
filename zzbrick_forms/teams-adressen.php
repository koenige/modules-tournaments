<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2017-2024 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Kontaktdaten einer Person eines Teams eines Turniers


if (empty(wrap_setting('tournaments_request_address_data')))
	wrap_quit(404, 'Für dieses Turnier werden keine Adressdaten erhoben.');
if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');

$contact_id = $brick['data']['contact_id'];
$organisation = $brick['data']['contact'];
if (wrap_setting('tournaments_player_pool') === 'club') {
	$field = 'participations.club_contact_id';
	$type = 'Verein';
} else {
	$field = 'teams.club_contact_id';
	$type = 'Landesverband';
}

$sql = 'SELECT contact_id
	FROM participations
	JOIN events USING (event_id)
	LEFT JOIN categories series
		ON events.series_category_id = series.category_id
	LEFT JOIN teams USING (team_id)
	WHERE IFNULL(events.event_year, YEAR(events.date_begin)) = %d
	AND (series.main_category_id = %d OR series.category_id = %d)
	AND %s = %d
	AND usergroup_id NOT IN (
		/*_ID usergroups team-organisator _*/,
		/*_ID usergroups landesverband-organisator _*/,
		/*_ID usergroups bewerber _*/
	)
	ORDER BY series.sequence';
$sql = sprintf($sql
	, $brick['vars'][0]
	, $brick['data']['series_category_id']
	, $brick['data']['series_category_id']
	, $field
	, $contact_id
);
$contact_ids = wrap_db_fetch($sql, '_dummy_', 'single value');

$zz = zzform_include('persons', [], 'forms');

if (empty($contact_ids)) {
	$zz['sql'] .= ' AND contact_id = 0';
	$zz['explanation'] = '<p class="error">Es wurde noch niemand angemeldet.</p>';
} else {
	$zz['sql'] .= sprintf(' AND contact_id IN (%s)', implode(',', $contact_ids));
}

foreach ($zz['fields'][9]['fields'] as $no => $field) {
	$identifier = zzform_field_identifier($field);
	switch ($identifier) {
	case 'person_id':
	case 'contact_id':
	case 'title_prefix':
	case 'date_of_birth':
	case 'sex':
		break;

	case 'first_name':
		// change of name not possible here
		$zz['fields'][9]['fields'][$no]['type'] = 'display';
		$zz['fields'][9]['fields'][$no]['list_append_next'] = false;
		$zz['fields'][9]['if']['record_mode']['fields'][$no]['display_field'] = 'first_name';
		break;

	case 'name_particle':
		// change of name not possible here
		$zz['fields'][9]['fields'][$no]['type'] = 'display';
		$zz['fields'][9]['fields'][$no]['explanation'] = false;
		break;

	case 'last_name':
		// change of name not possible here
		$zz['fields'][9]['fields'][$no]['type'] = 'display';

	default:
		unset($zz['fields'][9]['fields'][$no]);
	}
}

unset($zz['fields'][2]);	// contact
$zz['fields'][3]['hide_in_form'] = true; // identifier
unset($zz['fields'][3]['read_options']);
unset($zz['fields'][17]); 	// change identifier
unset($zz['fields'][19]);	// contacts_identifiers

$zz['fields'][32]['hide_in_list'] = false;

$zz['fields'][5]['hide_in_list'] = false;
$zz['fields'][5]['title_tab'] = 'Adresse';
unset($zz['fields'][5]['unless']['export_mode']['subselect']['field_suffix'][0]);
$zz['fields'][5]['unless']['export_mode']['subselect']['field_suffix'][1] = '<br>';

$zz['setting']['zzform_show_list_while_edit'] = false;
$zz['record']['delete'] = false;
$zz['list']['merge'] = false;
$zz['record']['add'] = false;
$zz['page']['dont_show_title_as_breadcrumb'] = true;

$zz['page']['breadcrumbs'][] = ['url_path' => '../', 'title' => $organisation];
$zz['page']['breadcrumbs'][]['title'] = 'Adressen';

$zz['title'] = '<a href="../">'.$type.' '.$organisation.'</a>: Adressen
	<br><a href="../../">'.$brick['data']['event'].' '.wrap_date($brick['data']['duration']).'</a> <em>in '.$brick['data']['place'].'</em>';

$zz['page']['referer'] = '../';
