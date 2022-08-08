<?php 

/**
 * tournaments module
 * table script: teams
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Teams';
$zz['table'] = 'teams';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'team_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['title'] = 'Termin';
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
	LEFT JOIN categories
		ON contacts.contact_category_id = categories.category_id
	WHERE categories.parameters LIKE "%&organisation=1%"
	ORDER BY contacts_identifiers.identifier, contact_abbr';
$zz['fields'][3]['display_field'] = 'organisation';
$zz['fields'][3]['id_field_name'] = 'contacts.contact_id';
$zz['fields'][3]['search'] = 'vereine.contact';
$zz['fields'][3]['character_set'] = 'utf8';
$zz['fields'][3]['sql_fieldnames_ignore'] = ['contacts.contact_id'];
$zz['fields'][3]['hide_in_list'] = true;
$zz['fields'][3]['add_details'] = '/intern/db/organisationen';

$zz['fields'][4]['field_name'] = 'team';
$zz['fields'][4]['append_next'] = true;
$zz['fields'][4]['list_append_next'] = true;
$zz['fields'][4]['link'] = [
	'string1' => $zz_setting['events_internal_path'].'/',
	'field1' => 'identifier',
	'string2' => '/'
];
$zz['fields'][4]['unless']['export_mode']['list_prefix'] = '<strong>';
$zz['fields'][4]['if'][1]['link'] = false;
$zz['fields'][4]['function'] = 'my_teamname';
$zz['fields'][4]['explanation'] = 'Falls leer, wird hier Name der Organisation genommen.';
$zz['fields'][4]['fields'] = ['team', 'club_contact_id', 'team_no'];
$zz['fields'][4]['required'] = false;

$zz['fields'][5]['field_name'] = 'team_no';
$zz['fields'][5]['list_prefix'] = ' ';
$zz['fields'][5]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][5]['explanation'] = '+ ggf. Nr. des Teams';

$zz['fields'][11]['title_tab'] = 'LV';
$zz['fields'][11]['field_name'] = 'verband';
$zz['fields'][11]['type'] = 'display';
$zz['fields'][11]['exclude_from_search'] = true;
$zz['fields'][11]['unless']['export_mode']['list_prefix'] = '</strong><br>';
$zz['fields'][11]['list_append_next'] = true;
$zz['fields'][11]['if']['add']['hide_in_form'] = true;

$zz['fields'][10]['title_tab'] = 'Regionalg.';
$zz['fields'][10]['field_name'] = 'regionalgruppe';
$zz['fields'][10]['type'] = 'display';
$zz['fields'][10]['exclude_from_search'] = true;
$zz['fields'][10]['list_prefix'] = ' (';
$zz['fields'][10]['list_suffix'] = ')';
$zz['fields'][10]['if']['add']['hide_in_form'] = true;

$zz['fields'][6]['field_name'] = 'identifier';
$zz['fields'][6]['type'] = 'identifier';
$zz['fields'][6]['fields'] = ['event_id[identifier]', 'team', 'team_no'];
$zz['fields'][6]['conf_identifier']['concat'] = ['/', '/', '-'];
$zz['fields'][6]['hide_in_list'] = true;

$zz['fields'][6]['separator'] = 'text <div>Vor dem Turnier</div>';

$zz['fields'][7]['title'] = 'Berechtigung';
$zz['fields'][7]['field_name'] = 'berechtigung_category_id';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories';
$zz['fields'][7]['show_hierarchy'] = 'main_category_id';
$zz['fields'][7]['id_field_name'] = 'category_id';
$zz['fields'][7]['show_hierarchy_subtree'] = wrap_category_id('berechtigungen');
$zz['fields'][7]['display_field'] = 'category';
$zz['fields'][7]['hide_in_list'] = true;

$zz['fields'][8]['title'] = 'Status';
$zz['fields'][8]['field_name'] = 'team_status';
$zz['fields'][8]['type'] = 'select';
$zz['fields'][8]['enum'] = ['Teilnahmeberechtigt', 'Teilnehmer', 'Nachrücker', 'Löschung'];
$zz['fields'][8]['default'] = 'Teilnahmeberechtigt';
$zz['fields'][8]['append_next'] = true;
$zz['fields'][8]['group_in_list'] = true;

$zz['fields'][9]['field_name'] = 'nachruecker_reihenfolge';
$zz['fields'][9]['title'] = '#';
$zz['fields'][9]['type'] = 'number';
$zz['fields'][9]['explanation'] = 'Rangfolge – sinnvoll nur bei Nachrückern';
$zz['fields'][9]['hide_in_list'] = true;

$zz['fields'][13]['field_name'] = 'meldung';
$zz['fields'][13]['title'] = 'Meldung';
$zz['fields'][13]['title_tab'] = 'Mg.';
$zz['fields'][13]['type'] = 'select';
$zz['fields'][13]['enum'] = ['offen', 'teiloffen', 'gesperrt', 'komplett'];
$zz['fields'][13]['unless']['export_mode']['enum_abbr'] = ['offen', 'teiloffen', 'gesperrt', 'komplett'];
$zz['fields'][13]['unless']['export_mode']['enum_title'] = [
	'<span class="vielleicht">&nbsp;</span>', '<span class="teilweise">&nbsp;</span>',
	'<span class="nein">&nbsp;</span>', '<span class="ja">&nbsp;</span>'
];
$zz['fields'][13]['default'] = 'gesperrt';
$zz['fields'][13]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][13]['show_values_as_list'] = true;

$zz['fields'][32]['title'] = 'Meldedatum';
$zz['fields'][32]['field_name'] = 'meldung_datum';
$zz['fields'][32]['type'] = 'hidden';
$zz['fields'][32]['type_detail'] = 'datetime';
$zz['fields'][32]['display_field'] = 'meldung_datum_list';
$zz['fields'][32]['search'] = 'meldung_datum';
$zz['fields'][32]['unless']['export_mode']['list_prefix'] = '<br>';

$zz['fields'][33]['title'] = 'Hash';
$zz['fields'][33]['field_name'] = 'meldung_hash';
$zz['fields'][33]['type'] = 'hidden';
$zz['fields'][33]['hide_in_list'] = true;
$zz['fields'][33]['function'] = 'my_random_hash';
$zz['fields'][33]['fields'] = ['identifier', 'team_id', 'meldung_hash'];

if (brick_access_rights(['Webmaster'])) {
	$zz['fields'][40]['title'] = 'Fremdschlüssel';
	$zz['fields'][40]['title_tab'] = 'FS';
	$zz['fields'][40]['field_name'] = 'fremdschluessel';
	$zz['fields'][40]['explanation'] = 'Z. B. Schlüssel der Turnierauswertung';
	$zz['fields'][40]['hide_in_list_if_empty'] = true;
}

$zz['fields'][17]['separator_before'] = 'text <div>Während des Turniers</div>';
$zz['fields'][17]['title'] = 'Setzliste Nr.';
$zz['fields'][17]['field_name'] = 'setzliste_no';
$zz['fields'][17]['title_tab'] = 'Setz';
$zz['fields'][17]['hide_in_list'] = true;

$zz['fields'][17]['separator'] = 'text <div>Rahmendaten</div>';

$zz['fields'][34]['title_append'] = 'Anreise';
$zz['fields'][34]['title_tab'] = 'An- und Abreise';
$zz['fields'][34]['title'] = 'Datum Anreise';
$zz['fields'][34]['field_name'] = 'datum_anreise';
$zz['fields'][34]['type'] = 'date';
$zz['fields'][34]['hide_in_list'] = true;
$zz['fields'][34]['append_next'] = true;
$zz['fields'][34]['list_append_next'] = true;
$zz['fields'][34]['display_field'] = 'datum_anreise_list';
$zz['fields'][34]['search'] = 'datum_anreise';
$zz['fields'][34]['prefix'] = 'am ';
$zz['fields'][34]['hide_in_list_if_empty'] = true;

$zz['fields'][14]['title'] = 'Uhrzeit Anreise';
$zz['fields'][14]['field_name'] = 'uhrzeit_anreise';
$zz['fields'][14]['type'] = 'time';
$zz['fields'][14]['hide_in_list'] = true;
$zz['fields'][14]['prefix'] = ' gegen ca. ';
$zz['fields'][14]['suffix'] = ' Uhr';
$zz['fields'][14]['list_prefix'] = ', ~&nbsp;';
$zz['fields'][14]['list_suffix'] = '&nbsp;Uhr';
$zz['fields'][14]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][14]['replace_values'] = ['--' => '', 'Uhr' => '', '-:-' => ''];

$zz['fields'][15]['title_append'] = 'Abreise';
$zz['fields'][15]['title'] = 'Datum Abreise';
$zz['fields'][15]['field_name'] = 'datum_abreise';
$zz['fields'][15]['type'] = 'date';
$zz['fields'][15]['hide_in_list'] = true;
$zz['fields'][15]['append_next'] = true;
$zz['fields'][15]['prefix'] = 'am ';
$zz['fields'][15]['unless']['export_mode']['list_prefix'] = '<br>';
$zz['fields'][15]['list_append_next'] = true;
$zz['fields'][15]['display_field'] = 'datum_abreise_list';
$zz['fields'][15]['search'] = 'datum_abreise';

$zz['fields'][35]['title'] = 'Uhrzeit Abreise';
$zz['fields'][35]['field_name'] = 'uhrzeit_abreise';
$zz['fields'][35]['type'] = 'time';
$zz['fields'][35]['hide_in_list'] = true;
$zz['fields'][35]['prefix'] = ' gegen ca. ';
$zz['fields'][35]['suffix'] = ' Uhr';
$zz['fields'][35]['list_prefix'] = ', ~&nbsp;';
$zz['fields'][35]['list_suffix'] = '&nbsp;Uhr';
$zz['fields'][35]['replace_values'] = ['--' => '', 'Uhr' => '', '-:-' => ''];

$zz['fields'][35]['separator'] = true;

if (brick_access_rights(['Webmaster'])) {
	$zz['fields'][35]['separator'] = false;

	$zz['fields'][48]['field_name'] = 'spielbeginn';
	$zz['fields'][48]['type'] = 'time';
	$zz['fields'][48]['suffix'] = ' Uhr';
	$zz['fields'][48]['prefix'] = 'um ';
	$zz['fields'][48]['hide_in_list'] = true;
	$zz['fields'][48]['separator'] = true;
	$zz['fields'][48]['explanation'] = 'Falls immer abweichend vom festgesetzten Spielbeginn';

	$zz['fields'][48]['separator'] = 'text <div>Sonstiges</div>';
}

$zz['fields'][21] = zzform_include_table('anmerkungen');
$zz['fields'][21]['title_tab'] = 'Bemerkungen / Kontakt';
$zz['fields'][21]['title'] = 'Anmerkungen';
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
$zz['fields'][21]['unless']['export_mode']['subselect']['prefix'] = '<p><em>Anmerkung:</em><br>';
$zz['fields'][21]['subselect']['field_suffix'][0] = ' '; 
$zz['fields'][21]['subselect']['field_suffix'][1] = ': '; 
$zz['fields'][21]['subselect']['field_suffix'][2] = '<br>'; 
$zz['fields'][21]['if']['export_mode']['subselect']['field_suffix'][2] = ','; 
$zz['fields'][21]['unless']['export_mode']['subselect']['concat_rows'] = '<br>'; 
$zz['fields'][21]['unless']['export_mode']['list_append_next'] = true;
$zz['fields'][21]['if']['export_mode']['subselect']['prefix'] = '';
$zz['fields'][21]['if']['export_mode']['subselect']['suffix'] = '';
$zz['fields'][21]['hide_in_list_if_empty'] = true;

$zz['fields'][25]['title'] = 'Kontakt';
$zz['fields'][25]['type'] = 'subtable';
$zz['fields'][25]['table'] = 'participations';
$zz['fields'][25]['fields'] = [];
$zz['fields'][25]['fields'][2]['type'] = 'foreign_key';
$zz['fields'][25]['fields'][2]['field_name'] = 'team_id';
$zz['fields'][25]['fields'][2]['key_field_name'] = 'team_id';
$zz['fields'][25]['fields'][3]['field_name'] = 'person_id';
$zz['fields'][25]['fields'][3]['type'] = 'select';
$zz['fields'][25]['fields'][3]['search'] = 'IF(logins.active = "yes", "(+)", "(-)")';
$zz['fields'][25]['fields'][4]['field_name'] = 'person_id';
$zz['fields'][25]['fields'][4]['type'] = 'select';
$zz['fields'][25]['fields'][4]['search'] = 'contact';
$zz['fields'][25]['hide_in_form'] = true;
$zz['fields'][25]['sql'] =
$zz['fields'][25]['subselect']['sql'] = sprintf('SELECT team_id
		, contacts.identifier
		, CONCAT(contact,
			IF(logins.active = "yes", " (+)", " (-)")) AS person
		, (SELECT identification FROM contactdetails
			WHERE contactdetails.contact_id = contacts.contact_id
			AND provider_category_id = %d
			LIMIT 1
		) AS e_mail
		, GROUP_CONCAT(CONCAT(category_short, ": ", identification) SEPARATOR "<br>") AS telefon
	FROM participations
	LEFT JOIN persons USING (person_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN logins USING (person_id)
	LEFT JOIN contactdetails USING (contact_id)
	LEFT JOIN categories
		ON contactdetails.provider_category_id = categories.category_id
	WHERE usergroup_id = %d
	GROUP BY participation_id'
	, wrap_category_id('provider/e-mail')
	, wrap_id('usergroups', 'team-organisator')
);
$zz['fields'][25]['unless']['export_mode']['subselect']['prefix'] = '<p><em>Kontakt:</em><br>';
$zz['fields'][25]['if']['export_mode']['subselect']['prefix'] = '';
$zz['fields'][25]['if']['export_mode']['subselect']['suffix'] = '';
$zz['fields'][25]['unless']['export_mode']['subselect']['concat_fields'] = ' ';
$zz['fields'][25]['if']['export_mode']['subselect']['concat_fields'] = ', ';
$zz['fields'][25]['unless']['export_mode']['subselect']['concat_rows'] = '<br>'; 
$zz['fields'][25]['if']['export_mode']['subselect']['concat_rows'] = '; '; 
$zz['fields'][25]['unless']['export_mode']['subselect']['field_prefix'][0] = '<a href="/intern/personen/'; 
$zz['fields'][25]['unless']['export_mode']['subselect']['field_suffix'][0] = '/">';
$zz['fields'][25]['unless']['export_mode']['subselect']['field_suffix'][1] = '</a>, <br>';
$zz['fields'][25]['unless']['export_mode']['subselect']['field_suffix'][2] = '<br>';

$zz['fields'][26]['field_name'] = 'spielfrei';
$zz['fields'][26]['type'] = 'select';
$zz['fields'][26]['enum'] = ['ja', 'nein'];
$zz['fields'][26]['default'] = 'nein';

$zz['fields'][27]['title_append'] = 'Eintrag';
$zz['fields'][27]['title'] = 'Eintrag am';
$zz['fields'][27]['field_name'] = 'eintrag_datum';
$zz['fields'][27]['type'] = 'hidden';
$zz['fields'][27]['type_detail'] = 'datetime';
$zz['fields'][27]['hide_in_list'] = true;
$zz['fields'][27]['if']['insert']['default'] = date('Y-m-d H:i:s');
$zz['fields'][27]['export'] = false;

$zz['fields'][28]['title'] = 'Meldebogen';
$zz['fields'][28]['field_name'] = 'meldebogen';
$zz['fields'][28]['dont_show_missing'] = true;
$zz['fields'][28]['type'] = 'upload_image';
$zz['fields'][28]['path'] = [
	'root' => $zz_setting['media_folder'].'/meldeboegen/',
	'webroot' => $zz_setting['media_internal_path'].'/meldeboegen/',
	'field1' => 'identifier',
	'string1' => '.',
	'string2' => 'pdf'
];
$zz['fields'][28]['input_filetypes'] = ['pdf'];
$zz['fields'][28]['link'] = [
	'string1' => $zz_setting['media_internal_path'].'/meldeboegen/',
	'field1' => 'identifier',
	'string2' => '.',
	'string3' => 'pdf'
];
$zz['fields'][28]['optional_image'] = true;
$zz['fields'][28]['explanation'] = 'Hochladen des ausgefüllten, gescannten Meldebogens';
$zz['fields'][28]['image'][0]['title'] = 'pdf';
$zz['fields'][28]['image'][0]['field_name'] = 'pdf';
$zz['fields'][28]['image'][0]['path'] = $zz['fields'][28]['path'];
$zz['fields'][28]['list_append_next'] = true;
$zz['fields'][28]['hide_in_list'] = true;

$zz['fields'][29] = $zz['fields'][28];
$zz['fields'][29]['title'] = 'Ehrenkodex';
$zz['fields'][29]['field_name'] = 'ehrenkodex';
$zz['fields'][29]['explanation'] = 'Hochladen des ausgefüllten, gescannten Ehrenkodexes';
$zz['fields'][29]['path']['string1'] = '-ehrenkodex.';
$zz['fields'][29]['link']['string2'] = '-ehrenkodex.';
$zz['fields'][29]['image'][0]['path'] = $zz['fields'][29]['path'];

$zz['fields'][30] = $zz['fields'][28];
$zz['fields'][30]['title'] = 'Gastspielgenehmigung';
$zz['fields'][30]['field_name'] = 'gastspielgenehmigung';
$zz['fields'][30]['explanation'] = 'Hochladen der ausgefüllten, gescannten Gastspielgenehmigung';
$zz['fields'][30]['path']['string1'] = '-gast.';
$zz['fields'][30]['link']['string2'] = '-gast.';
$zz['fields'][30]['image'][0]['path'] = $zz['fields'][30]['path'];

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
	LEFT JOIN contacts_identifiers federation_identifiers
		ON CONCAT(SUBSTRING(contacts_identifiers.identifier, 1, 1), "00") = federation_identifiers.identifier 
		AND federation_identifiers.current = "yes"
	LEFT JOIN contacts landesverbaende
		ON landesverbaende.contact_id = federation_identifiers.contact_id
	LEFT JOIN regionalgruppen 
		ON landesverbaende.contact_id = regionalgruppen.federation_contact_id
	LEFT JOIN countries
		ON landesverbaende.country_id = countries.country_id
';
$zz['sqlorder'] = ' ORDER BY team_status, events.date_begin, events.identifier
	, nachruecker_reihenfolge, vereine.contact, team_no';

$zz['subtitle']['event_id']['sql'] = 'SELECT event
	, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
	FROM events';
$zz['subtitle']['event_id']['var'] = ['event', 'duration'];
$zz['subtitle']['event_id']['format'][1] = 'wrap_date';
$zz['subtitle']['event_id']['link'] = '../';
$zz['subtitle']['event_id']['link_no_append'] = true;

$zz['conditions'][1]['scope'] = 'record';
$zz['conditions'][1]['where'] = 'spielfrei = "ja"';

if (brick_access_rights(['Webmaster'])) {
	$zz['filter'][1]['title'] = 'Meldung';
	$zz['filter'][1]['type'] = 'list';
	$zz['filter'][1]['where'] = 'meldung';
	$zz['filter'][1]['sql'] = 'SELECT DISTINCT meldung, meldung AS titel
		FROM teams
		ORDER BY meldung';
}

if (!empty($zz_conf['multi'])) $zz_conf['delete'] = true;

$zz['hooks']['after_update'] = 'mf_tournaments_standings_update';

$zz['set_redirect'][] = ['old' => '/%s/', 'new' => '/%s/', 'field_name' => 'identifier'];
