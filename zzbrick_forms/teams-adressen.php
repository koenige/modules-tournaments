<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2017-2023 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Kontaktdaten einer Person eines Teams eines Turniers


if (empty(wrap_setting('tournaments_request_address_data')))
	wrap_quit(404, 'Für dieses Turnier werden keine Adressdaten erhoben.');
if ($brick['data']['meldung'] === 'gesperrt')
	wrap_quit(403, 'Dieses Team wurde gesperrt. Sie können keine Änderungen vornehmen.');

$contact_id = $brick['data']['contact_id'];
$organisation = $brick['data']['contact'];
if ($brick['data']['turnierform'] === 'm-v') {
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
	AND usergroup_id NOT IN (%d, %d, %d)
	ORDER BY series.sequence';
$sql = sprintf($sql
	, $brick['vars'][0]
	, $brick['data']['series_category_id']
	, $brick['data']['series_category_id']
	, $field
	, $contact_id
	, wrap_id('usergroups', 'team-organisator')
	, wrap_id('usergroups', 'landesverband-organisator')
	, wrap_id('usergroups', 'bewerber')
);
$contact_ids = wrap_db_fetch($sql, '_dummy_', 'single value');

require_once wrap_setting('custom').'/zzbrick_forms/persons.php';

if (empty($contact_ids)) {
	$zz['sql'] .= ' AND contact_id = 0';
	$zz['explanation'] = '<p class="error">Es wurde noch niemand angemeldet.</p>';
} else {
	$zz['sql'] .= sprintf(' AND contact_id IN (%s)', implode(',', $contact_ids));
}

$keep_fields = [1, 2, 3, 4, 6, 8, 9, 7, 20, 25];
foreach (array_keys($zz['fields'][9]['fields']) as $no) {
	if (!in_array($no, $keep_fields)) unset($zz['fields'][9]['fields'][$no]);
}

// Namen nicht veränderbar
$zz['fields'][9]['fields'][2]['type'] = 'display';
$zz['fields'][9]['if']['record_mode']['fields'][2]['display_field'] = 'first_name';
$zz['fields'][9]['fields'][3]['type'] = 'display';
$zz['fields'][9]['fields'][3]['explanation'] = false;
$zz['fields'][9]['fields'][4]['type'] = 'display';
$zz['fields'][9]['fields'][2]['list_append_next'] = false;

//$zz['fields'][9]['list_append_next'] = true;

unset($zz['fields'][2]);	// contact
$zz['fields'][3]['hide_in_form'] = true; // identifier
unset($zz['fields'][3]['read_options']);
unset($zz['fields'][17]); 	// change identifier
unset($zz['fields'][65]);	// contacts_identifiers

$zz['fields'][32]['hide_in_list'] = false;

$zz['fields'][5]['hide_in_list'] = false;
$zz['fields'][5]['title_tab'] = 'Adresse';
unset($zz['fields'][5]['unless']['export_mode']['subselect']['field_suffix'][0]);
$zz['fields'][5]['unless']['export_mode']['subselect']['field_suffix'][1] = '<br>';

wrap_setting('zzform_show_list_while_edit', false);
$zz_conf['delete'] = false;
$zz_conf['merge'] = false;
$zz_conf['add'] = false;
$zz_conf['dont_show_title_as_breadcrumb'] = true;

$zz['page']['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $organisation);
$zz['page']['breadcrumbs'][] = 'Adressen';

$zz['title'] = '<a href="../">'.$type.' '.$organisation.'</a>: Adressen
	<br><a href="../../">'.$brick['data']['event'].' '.wrap_date($brick['data']['duration']).'</a> <em>in '.$brick['data']['turnierort'].'</em>';

$zz_conf['referer'] = '../';
