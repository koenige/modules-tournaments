<?php 

/**
 * tournaments module
 * form script: Bookings for a team in a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


require_once $zz_setting['custom_wrap_dir'].'/team.inc.php';
$data = my_team_form($brick['vars']);
if (!$data['zimmerbuchung']) wrap_quit(404, 'Zimmerbuchungen erfolgen direkt über Ausrichter!');

require $zz_conf['form_scripts'].'/buchungen.php';
$zz = $zz_sub;
unset($zz_sub);

$zz_conf['footer_text'] = wrap_template('team-buchung');
$data['head'] = true;
$zz['explanation'] = wrap_template('team-buchung', $data);
$zz['page'] = my_team_form_page($data, 'Buchungen');
$zz['where']['team_id'] = $data['team_id'];
$zz['title'] = '';

$zz_conf['delete'] = true;
$zz_conf['add'] = true; // nur eine Kategorie hinzufügbar

$zz['fields'][2]['class'] = 'hidden';
unset($zz['fields'][2]['link']);
$zz['fields'][3]['sql'] .= sprintf(' AND event_id = %d', $data['event_id']);
$zz['fields'][5]['default'] = $data['dauer_tage'];
$zz['fields'][6]['required'] = true;
$zz['fields'][7]['required'] = true;
// Bankkonto
$zz['fields'][18]['hide_in_form'] = true;
$zz['fields'][18]['hide_in_list'] = true;
$zz['fields'][18]['required'] = false;
// Anmerkungen
$zz['fields'][10]['explanation'] = $zz['fields'][10]['if'][9]['explanation'];
// Status
$zz['fields'][11]['type'] = 'display';
$zz['fields'][11]['type_detail'] = 'select';
$zz['fields'][11]['explanation'] = '(Nach erfolgter Meldung wird die Buchung durch die Organisatoren bestätigt.)';
// Buchungsdatum
$zz['fields'][14]['type'] = 'hidden';
$zz['fields'][14]['class'] = 'hidden';

// Zahlungsdatum
$zz['fields'][15]['hide_in_form'] = true;

// Buchung
$zz['fields'][13]['hide_in_form'] = true;
$zz['fields'][13]['dont_show_missing'] = true;
// Betrag
$zz['fields'][8]['dont_show_missing'] = true;
$zz['fields'][8]['type'] = 'hidden';
// Nur Teams, keine Einzelmeldungen
unset($zz['fields'][17]);
// Buchungskategorie
$zz['fields'][19]['type'] = 'hidden';
$zz['fields'][19]['class'] = 'hidden';
$zz['fields'][19]['default'] = wrap_category_id('buchungen/buchung');

$zz['hooks']['before_upload'] = 'my_buchung';

$zz['if'][2]['access'] = 'none';

if (!brick_access_rights('Organisator', $data['event_rights'])
	AND !brick_access_rights('Veranstalter', $data['event_rights'])) {
	$zz_conf['if'][5]['delete'] = false;
	$zz_conf['if'][5]['edit'] = false;
	$zz_conf['if'][2]['delete'] = false;
	$zz_conf['if'][2]['edit'] = false;
}

unset($zz['subtitle']);
