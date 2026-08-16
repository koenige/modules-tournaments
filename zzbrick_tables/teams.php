<?php 

/**
 * tournaments module
 * table script: teams
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Teams';
$zz['table'] = 'teams';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'team_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Event';
$zz['fields'][2]['field_name'] = 'event_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year, identifier
	FROM events';
$zz['fields'][2]['display_field'] = 'event';
$zz['fields'][2]['sql_ignore'] = 'identifier';
$zz['fields'][2]['if']['where']['hide_in_form'] = true;
$zz['fields'][2]['if']['where']['hide_in_list'] = true;

$zz['fields'][3]['title'] = 'Organisation';
$zz['fields'][3]['field_name'] = 'club_contact_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT contacts.contact_id, contact
		, contacts_identifiers.identifier AS zps_code
	FROM contacts
	LEFT JOIN contacts_identifiers
		ON contacts_identifiers.contact_id = contacts.contact_id
		AND contacts_identifiers.current = "yes"
		AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
	LEFT JOIN categories
		ON contacts.contact_category_id = categories.category_id
	WHERE categories.parameters LIKE "%&contacts_organisation=1%"
	ORDER BY contacts_identifiers.identifier, contact_abbr';
$zz['fields'][3]['display_field'] = 'organisation';
$zz['fields'][3]['search'] = 'vereine.contact';
$zz['fields'][3]['character_set'] = 'utf8';
$zz['fields'][3]['sql_fieldnames_ignore'] = ['contacts.contact_id'];
$zz['fields'][3]['hide_in_list'] = true;
$zz['fields'][3]['add_details'] = ['area' => 'contacts_general'];

$zz['fields'][4]['field_name'] = 'team';
$zz['fields'][4]['append_next'] = true;
$zz['fields'][4]['list_append_next'] = true;
$zz['fields'][4]['link'] = [
	'area' => 'tournaments_team_registration',
	'fields' => ['identifier']
];
$zz['fields'][4]['unless']['export_mode']['list_prefix'] = '<strong>';
$zz['fields'][4]['if'][1]['link'] = false;
$zz['fields'][4]['function'] = 'mf_tournaments_team_name';
$zz['fields'][4]['explanation'] = 'If empty, the organisation name is used here.';
$zz['fields'][4]['fields'] = ['team', 'club_contact_id', 'team_no'];
$zz['fields'][4]['required'] = false;

$zz['fields'][5]['field_name'] = 'team_no';
$zz['fields'][5]['list_prefix'] = ' ';
$zz['fields'][5]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][5]['explanation'] = '+ team no. if applicable';

$zz['fields'][11]['title'] = 'Federation';
$zz['fields'][11]['title_tab'] = 'Fed.';
$zz['fields'][11]['field_name'] = 'verband';
$zz['fields'][11]['type'] = 'display';
$zz['fields'][11]['exclude_from_search'] = true;
$zz['fields'][11]['unless']['export_mode']['list_prefix'] = '</strong><br>';
$zz['fields'][11]['list_append_next'] = true;
$zz['fields'][11]['if']['add']['hide_in_form'] = true;

$zz['fields'][10]['title'] = 'Regional group';
$zz['fields'][10]['title_tab'] = 'Regional grp.';
$zz['fields'][10]['field_name'] = 'regionalgruppe';
$zz['fields'][10]['type'] = 'display';
$zz['fields'][10]['exclude_from_search'] = true;
$zz['fields'][10]['list_prefix'] = ' (';
$zz['fields'][10]['list_suffix'] = ')';
$zz['fields'][10]['if']['add']['hide_in_form'] = true;

$zz['fields'][6]['field_name'] = 'identifier';
$zz['fields'][6]['type'] = 'identifier';
$zz['fields'][6]['fields'] = ['event_id[identifier]', 'team', 'team_no'];
$zz['fields'][6]['identifier']['concat'] = ['/', '/', '-'];
$zz['fields'][6]['hide_in_list'] = true;
$zz['fields'][6]['merge_ignore'] = true;

$zz['fields'][6]['separator'] = 'text <div class="separator">'.wrap_text('Before the tournament').'</div>';

$zz['fields'][7]['title'] = 'Eligibility';
$zz['fields'][7]['field_name'] = 'berechtigung_category_id';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories';
$zz['fields'][7]['show_hierarchy'] = 'main_category_id';
$zz['fields'][7]['show_hierarchy_subtree'] = wrap_category_id('berechtigungen');
$zz['fields'][7]['display_field'] = 'category';
$zz['fields'][7]['hide_in_list'] = true;

$zz['fields'][8]['title'] = 'Status';
$zz['fields'][8]['field_name'] = 'team_status';
$zz['fields'][8]['type'] = 'select';
$zz['fields'][8]['enum'] = ['Teilnahmeberechtigt', 'Teilnehmer', 'Nachrücker', 'Löschung'];
$zz['fields'][8]['enum_title'] = [wrap_text('Eligible'), wrap_text('Participant'), wrap_text('Reserve'), wrap_text('Deletion')];
$zz['fields'][8]['default'] = 'Teilnahmeberechtigt';
$zz['fields'][8]['append_next'] = true;
$zz['fields'][8]['group_in_list'] = true;

$zz['fields'][9]['title'] = ['#', ['context' => 'Sequence']];
$zz['fields'][9]['field_name'] = 'nachruecker_reihenfolge';
$zz['fields'][9]['type'] = 'number';
$zz['fields'][9]['explanation'] = 'Rank order – only meaningful for reserves';
$zz['fields'][9]['hide_in_list'] = true;

$zz['fields'][13]['field_name'] = 'meldung';
$zz['fields'][13]['title'] = 'Registration';
$zz['fields'][13]['title_tab'] = 'Reg.';
$zz['fields'][13]['type'] = 'select';
$zz['fields'][13]['enum'] = ['offen', 'teiloffen', 'gesperrt', 'komplett'];
$zz['fields'][13]['enum_title'] = [wrap_text('open'), wrap_text('partly open'), wrap_text('locked'), wrap_text('Complete')];
$zz['fields'][13]['unless']['export_mode']['enum_abbr'] = ['offen', 'teiloffen', 'gesperrt', 'komplett'];
$zz['fields'][13]['unless']['export_mode']['enum_title'] = [
	'<span class="status-open">&nbsp;</span>', '<span class="status-partly">&nbsp;</span>',
	'<span class="status-no">&nbsp;</span>', '<span class="status-yes">&nbsp;</span>'
];
$zz['fields'][13]['default'] = 'gesperrt';
$zz['fields'][13]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][13]['show_values_as_list'] = true;

$zz['fields'][32]['title'] = 'Registration date';
$zz['fields'][32]['field_name'] = 'meldung_datum';
$zz['fields'][32]['type'] = 'hidden';
$zz['fields'][32]['type_detail'] = 'datetime';
$zz['fields'][32]['display_field'] = 'meldung_datum_list';
$zz['fields'][32]['search'] = 'meldung_datum';
$zz['fields'][32]['unless']['export_mode']['list_prefix'] = '<br>';
$zz['fields'][32]['merge_ignore'] = true;

$zz['fields'][33]['title'] = 'Hash';
$zz['fields'][33]['field_name'] = 'meldung_hash';
$zz['fields'][33]['type'] = 'identifier';
$zz['fields'][33]['hide_in_list'] = true;
$zz['fields'][33]['identifier']['random_hash'] = 16;
$zz['fields'][33]['fields'] = ['meldung_hash'];
$zz['fields'][33]['merge_ignore'] = true;

if (wrap_access('tournaments_teams_foreign_key')) {
	$zz['fields'][40]['title'] = 'Foreign key';
	$zz['fields'][40]['title_tab'] = 'FK';
	$zz['fields'][40]['field_name'] = 'fremdschluessel';
	$zz['fields'][40]['explanation'] = 'E.g. key from tournament software';
	$zz['fields'][40]['hide_in_list_if_empty'] = true;
}

$zz['fields'][17]['separator_before'] = 'text <div class="separator">'.wrap_text('During the tournament').'</div>';
$zz['fields'][17]['title'] = 'Seeding list no.';
$zz['fields'][17]['field_name'] = 'setzliste_no';
$zz['fields'][17]['title_tab'] = 'Seed';
$zz['fields'][17]['hide_in_list'] = true;

$zz['fields'][17]['separator'] = 'text <div class="separator">'.wrap_text('Additional details').'</div>';

$zz['fields'][34]['title_append'] = 'Arrival';
$zz['fields'][34]['title_tab'] = 'Arrival and departure';
$zz['fields'][34]['title'] = 'Arrival date';
$zz['fields'][34]['field_name'] = 'datum_anreise';
$zz['fields'][34]['type'] = 'date';
$zz['fields'][34]['hide_in_list'] = true;
$zz['fields'][34]['append_next'] = true;
$zz['fields'][34]['list_append_next'] = true;
$zz['fields'][34]['display_field'] = 'datum_anreise_list';
$zz['fields'][34]['search'] = 'datum_anreise';
$zz['fields'][34]['prefix'] = 'am ';
$zz['fields'][34]['hide_in_list_if_empty'] = true;

$zz['fields'][14]['title'] = 'Arrival time';
$zz['fields'][14]['field_name'] = 'uhrzeit_anreise';
$zz['fields'][14]['type'] = 'time';
$zz['fields'][14]['hide_in_list'] = true;
$zz['fields'][14]['prefix'] = ' gegen ca. ';
$zz['fields'][14]['suffix'] = ' Uhr';
$zz['fields'][14]['list_prefix'] = ', ~&nbsp;';
$zz['fields'][14]['list_suffix'] = '&nbsp;Uhr';
$zz['fields'][14]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][14]['replace_values'] = ['--' => '', 'Uhr' => '', '-:-' => ''];

$zz['fields'][15]['title_append'] = 'Departure';
$zz['fields'][15]['title'] = 'Departure date';
$zz['fields'][15]['field_name'] = 'datum_abreise';
$zz['fields'][15]['type'] = 'date';
$zz['fields'][15]['hide_in_list'] = true;
$zz['fields'][15]['append_next'] = true;
$zz['fields'][15]['prefix'] = 'am ';
$zz['fields'][15]['unless']['export_mode']['list_prefix'] = '<br>';
$zz['fields'][15]['list_append_next'] = true;
$zz['fields'][15]['display_field'] = 'datum_abreise_list';
$zz['fields'][15]['search'] = 'datum_abreise';

$zz['fields'][35]['title'] = 'Departure time';
$zz['fields'][35]['field_name'] = 'uhrzeit_abreise';
$zz['fields'][35]['type'] = 'time';
$zz['fields'][35]['hide_in_list'] = true;
$zz['fields'][35]['prefix'] = ' gegen ca. ';
$zz['fields'][35]['suffix'] = ' Uhr';
$zz['fields'][35]['list_prefix'] = ', ~&nbsp;';
$zz['fields'][35]['list_suffix'] = '&nbsp;Uhr';
$zz['fields'][35]['replace_values'] = ['--' => '', 'Uhr' => '', '-:-' => ''];

if (wrap_setting('tournaments_team_league')) {
	$zz['fields'][48]['title'] = 'Game start';
	$zz['fields'][48]['field_name'] = 'spielbeginn';
	$zz['fields'][48]['type'] = 'time';
	$zz['fields'][48]['suffix'] = ' Uhr';
	$zz['fields'][48]['prefix'] = 'um ';
	$zz['fields'][48]['hide_in_list'] = true;
	$zz['fields'][48]['explanation'] = 'If always different from the scheduled start time';
}

$zz['fields'][21] = zzform_include('anmerkungen');
$zz['fields'][21]['separator_before'] = 'text <div class="separator">'.wrap_text('Other').'</div>';
$zz['fields'][21]['title_tab'] = 'Remarks / contact';
$zz['fields'][21]['title'] = 'Remarks';
$zz['fields'][21]['type'] = 'subtable';
$zz['fields'][21]['min_records'] = 0;
$zz['fields'][21]['fields'][3]['type'] = 'foreign_key';
unset($zz['fields'][21]['fields'][9]); // participation_id
// Zeige nur offene Anmerkungen in Liste
$zz['fields'][21]['subselect']['sql'] = 'SELECT team_id
		, CONCAT(SUBSTRING(persons.first_name, 1, 1), SUBSTRING(persons.last_name, 1, 1)) AS person, DATE_FORMAT(erstellt, "%d%m")
		, anmerkung
	FROM anmerkungen
	LEFT JOIN persons
		ON anmerkungen.autor_person_id = persons.person_id
	WHERE anmerkung_status = "offen"
';
$zz['fields'][21]['unless']['export_mode']['subselect']['prefix'] = '<p><em>'.wrap_text('Remark:').'</em><br>';
$zz['fields'][21]['subselect']['field_suffix'][0] = ' '; 
$zz['fields'][21]['subselect']['field_suffix'][1] = ': '; 
$zz['fields'][21]['subselect']['field_suffix'][2] = '<br>'; 
$zz['fields'][21]['if']['export_mode']['subselect']['field_suffix'][2] = ','; 
$zz['fields'][21]['unless']['export_mode']['subselect']['concat_rows'] = '<br>'; 
$zz['fields'][21]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][21]['if']['export_mode']['subselect']['prefix'] = '';
$zz['fields'][21]['if']['export_mode']['subselect']['suffix'] = '';
$zz['fields'][21]['hide_in_list_if_empty'] = true;

$zz['hooks'] = $zz['fields'][21]['hooks'];

$zz['fields'][25]['title'] = 'Contact';
$zz['fields'][25]['type'] = 'subtable';
$zz['fields'][25]['table'] = 'participations';
$zz['fields'][25]['fields'] = [];
$zz['fields'][25]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][25]['fields'][2]['field_name'] = 'team_id';
$zz['fields'][25]['fields'][2]['key_field_name'] = 'team_id';
$zz['fields'][25]['fields'][3]['field_name'] = 'contact_id';
$zz['fields'][25]['fields'][3]['type'] = 'select';
$zz['fields'][25]['fields'][3]['search'] = 'IF(logins.active = "yes", "(+)", "(-)")';
$zz['fields'][25]['fields'][4]['field_name'] = 'contact_id';
$zz['fields'][25]['fields'][4]['type'] = 'select';
$zz['fields'][25]['fields'][4]['search'] = 'contact';
$zz['fields'][25]['hide_in_form'] = true;
$zz['fields'][25]['sql'] =
$zz['fields'][25]['subselect']['sql'] = 'SELECT team_id
		, contacts.identifier
		, CONCAT(contact,
			IF(logins.active = "yes", " (+)", " (-)")) AS person
		, (SELECT identification FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND channel_category_id = /*_ID categories channel/e-mail _*/
			LIMIT 1
		) AS e_mail
		, GROUP_CONCAT(CONCAT(category_short, ": ", identification) SEPARATOR "<br>") AS telefon
	FROM participations
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN logins USING (contact_id)
	LEFT JOIN contactdetails USING (contact_id)
	LEFT JOIN categories
		ON contactdetails.channel_category_id = categories.category_id
	WHERE usergroup_id = /*_ID usergroups team-organisator _*/
	GROUP BY participation_id';
$zz['fields'][25]['unless']['export_mode']['subselect']['prefix'] = '<p><em>'.wrap_text('Contact:').'</em><br>';
$zz['fields'][25]['if']['export_mode']['subselect']['prefix'] = '';
$zz['fields'][25]['if']['export_mode']['subselect']['suffix'] = '';
$zz['fields'][25]['unless']['export_mode']['subselect']['concat_fields'] = ' ';
$zz['fields'][25]['if']['export_mode']['subselect']['concat_fields'] = ', ';
$zz['fields'][25]['unless']['export_mode']['subselect']['concat_rows'] = '<br>'; 
$zz['fields'][25]['if']['export_mode']['subselect']['concat_rows'] = '; '; 
$zz['fields'][25]['unless']['export_mode']['subselect']['field_link'][0] = [
	'area' => 'contacts_profile[person]',
	'fields' => ['identifier']
];
$zz['fields'][25]['unless']['export_mode']['subselect']['field_suffix'][1] = '<br>';
$zz['fields'][25]['unless']['export_mode']['subselect']['field_suffix'][2] = '<br>';
$zz['fields'][25]['subselect']['sql_ignore'] = ['identifier'];

$zz['fields'][26]['title'] = 'Bye';
$zz['fields'][26]['field_name'] = 'spielfrei';
$zz['fields'][26]['type'] = 'select';
$zz['fields'][26]['enum'] = ['ja', 'nein'];
$zz['fields'][26]['enum_title'] = [wrap_text('yes'), wrap_text('no')];
$zz['fields'][26]['default'] = 'nein';

$zz['fields'][27]['title_append'] = 'Entry';
$zz['fields'][27]['title'] = 'Entry on';
$zz['fields'][27]['field_name'] = 'eintrag_datum';
$zz['fields'][27]['type'] = 'hidden';
$zz['fields'][27]['type_detail'] = 'datetime';
$zz['fields'][27]['hide_in_list'] = true;
$zz['fields'][27]['if']['insert']['default'] = date('Y-m-d H:i:s');
$zz['fields'][27]['export'] = false;
$zz['fields'][27]['merge_ignore'] = true;

// Uploads
$zz['fields'][50] = [];
$zz['fields'][51] = [];
$zz['fields'][52] = [];
$zz['fields'][53] = [];
$zz['fields'][54] = [];
$zz['fields'][55] = [];
$zz['fields'][56] = [];
$zz['fields'][57] = [];
$zz['fields'][58] = [];
$zz['fields'][59] = [];

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;

$zz['sql'] = 'SELECT teams.*
		, vereine.contact AS organisation
		, regionalgruppe
		, country AS verband
		, event
		, category
		, DATE_FORMAT(meldung_datum, "%d.%m. %H:%i") AS meldung_datum_list
		, DATE_FORMAT(datum_anreise, "%d.%m.") AS datum_anreise_list
		, DATE_FORMAT(datum_abreise, "%d.%m.") AS datum_abreise_list
		, events.identifier AS event_identifier
	FROM teams
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON teams.berechtigung_category_id = categories.category_id
	LEFT JOIN contacts vereine
		ON teams.club_contact_id = vereine.contact_id
	LEFT JOIN contacts_identifiers
		ON contacts_identifiers.contact_id = vereine.contact_id
		AND contacts_identifiers.current = "yes"
		AND contacts_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
	LEFT JOIN contacts_identifiers federation_identifiers
		ON CONCAT(SUBSTRING(contacts_identifiers.identifier, 1, 1), "00") = federation_identifiers.identifier 
		AND federation_identifiers.current = "yes"
		AND federation_identifiers.identifier_category_id = /*_ID categories identifiers/pass_dsb _*/
	LEFT JOIN contacts landesverbaende
		ON landesverbaende.contact_id = federation_identifiers.contact_id
	LEFT JOIN regionalgruppen 
		ON landesverbaende.contact_id = regionalgruppen.federation_contact_id
	LEFT JOIN countries
		ON landesverbaende.country_id = countries.country_id
';
$zz['sqlorder'] = ' ORDER BY team_status, events.date_begin, events.identifier
	, nachruecker_reihenfolge, vereine.contact, team_no';

$zz['subtitle']['event_id']['sql'] = 'SELECT event_id, event
	, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
	FROM events';
$zz['subtitle']['event_id']['var'] = ['event', 'duration'];
$zz['subtitle']['event_id']['format'][1] = 'wrap_date';
$zz['subtitle']['event_id']['link'] = '../';
$zz['subtitle']['event_id']['link_no_append'] = true;

$zz['conditions'][1]['scope'] = 'record';
$zz['conditions'][1]['where'] = 'spielfrei = "ja"';

$zz['filter'][1]['title'] = 'Registration';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['where'] = 'meldung';
$zz['filter'][1]['sql'] = 'SELECT DISTINCT meldung, meldung AS titel
	FROM teams
	ORDER BY meldung';

$zz['if']['batch_mode']['record']['delete'] = true;

$zz['hooks']['after_update'][] = 'mf_tournaments_standings_update';

$zz['set_redirect'][] = ['old' => '/%s/', 'new' => '/%s/', 'field_name' => 'identifier'];
