<?php 

/**
 * tournaments module
 * table script: player messages
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Brett-Nachrichten';
$zz['table'] = 'spieler_nachrichten';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'nachricht_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][4]['field_name'] = 'nachricht';
$zz['fields'][4]['type'] = 'memo';

$zz['fields'][6]['field_name'] = 'absender';
$zz['fields'][6]['list_append_next'] = true;
$zz['fields'][6]['list_suffix'] = '<br>';

$zz['fields'][5]['field_name'] = 'email';
$zz['fields'][5]['type'] = 'mail';
$zz['fields'][5]['list_append_next'] = true;
$zz['fields'][5]['list_suffix'] = '<br>';

$zz['fields'][2]['field_name'] = 'ip';
$zz['fields'][2]['type'] = 'write_once';

$zz['fields'][7]['field_name'] = 'teilnehmer_id';
$zz['fields'][7]['type'] = 'select';
$zz['fields'][7]['sql'] = 'SELECT participation_id, contact
	, CONCAT(events.event, " ", IFNULL(event_year, YEAR(events.date_begin))) AS event
	FROM participations
	LEFT JOIN persons USING (person_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN events USING (event_id)';
$zz['fields'][7]['display_field'] = 'contact';

$zz['fields'][8]['field_name'] = 'eintragszeit';
$zz['fields'][8]['type'] = 'write_once';
$zz['fields'][8]['type_detail'] = 'datetime';

$zz['fields'][9]['field_name'] = 'fertig';
$zz['fields'][9]['title_tab'] = 'F.';

$zz['fields'][10]['field_name'] = 'hash';
$zz['fields'][10]['type'] = 'write_once';
$zz['fields'][10]['hide_in_list'] = true;

$zz['fields'][11]['title'] = 'Bestätigt';
$zz['fields'][11]['field_name'] = 'verified';
$zz['fields'][11]['type'] = 'select';
$zz['fields'][11]['enum'] = ['yes', 'no'];
$zz['fields'][11]['default'] = 'no';
$zz['fields'][11]['title_tab'] = 'OK';

$zz['fields'][12]['title'] = 'Bildnachricht';
$zz['fields'][12]['field_name'] = 'missing_image';
$zz['fields'][12]['title_tab'] = 'B.';
$zz['fields'][12]['type'] = 'select';
$zz['fields'][12]['enum'] = ['yes', 'no'];
$zz['fields'][12]['default'] = 'no';

$zz['fields'][13]['title'] = 'Verarbeitet';
$zz['fields'][13]['field_name'] = 'processed';
$zz['fields'][13]['title_tab'] = 'V.';


$zz['sql'] = 'SELECT spieler_nachrichten.*
		, CONCAT(events.event, " ", IFNULL(event_year, YEAR(events.date_begin))) AS event
		, events.identifier AS event_identifier
		, contact
	FROM spieler_nachrichten
	LEFT JOIN participations
		ON spieler_nachrichten.teilnehmer_id = participations.participation_id
	LEFT JOIN persons USING (person_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN events USING (event_id)
';
$zz['sqlorder'] = ' ORDER BY eintragszeit DESC';
