<?php 

/**
 * tournaments module
 * form script: Bookings for a team in a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2016-2020, 2022-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (!$brick['data']['zimmerbuchung']) wrap_quit(404, 'Zimmerbuchungen erfolgen direkt über Ausrichter!');
if (!in_array($brick['data']['meldung'], ['offen', 'teiloffen']))
	wrap_quit(403, 'Das Team wurde bereits abschließend gemeldet. Änderungen sind nicht mehr möglich.');
// @todo show data in read-only mode instead?

$brick['page']['title'] .= 'Buchungen';
$brick['page']['breadcrumbs'][]['title'] = 'Buchungen';

$zz = zzform_include('buchungen');

$zz['footer']['text'] = wrap_template('team-buchung');
$brick['data']['head'] = true;
$zz['explanation'] = wrap_template('team-buchung', $brick['data']);
$zz['where']['team_id'] = $brick['data']['team_id'];
$zz['title'] = '';

$zz['record']['delete'] = true;
$zz['record']['add'] = true; // nur eine Kategorie hinzufügbar

$zz['fields'][2]['class'] = 'hidden';
unset($zz['fields'][2]['link']);
$zz['fields'][3]['sql'] = sprintf('SELECT costs.cost_id, product
		, CONCAT(price, " ", currency) AS price
		, haeufigkeit
	FROM costs
	LEFT JOIN costs_categories
		ON costs.cost_id = costs_categories.cost_id
		AND type_category_id = %d
	LEFT JOIN categories
		ON costs_categories.category_id = categories.category_id
	WHERE categories.parameters LIKE "%%&teilnehmer=1%%"
	AND event_id = %d
', wrap_category_id('costs/buchungen'), $brick['data']['event_id']);
$zz['fields'][5]['default'] = $brick['data']['dauer_tage'];
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
$zz['fields'][19]['default'] = wrap_category_id('costs/buchungen/buchung');

$zz['hooks']['before_upload'] = 'my_buchung';

$zz['if'][2]['access'] = 'none';

if (!brick_access_rights('Organisator', $brick['data']['event_rights'])
	AND !brick_access_rights('Veranstalter', $brick['data']['event_rights'])) {
	$zz['if'][5]['record']['delete'] = false;
	$zz['if'][5]['record']['edit'] = false;
	$zz['if'][2]['record']['delete'] = false;
	$zz['if'][2]['record']['edit'] = false;
}
$zz['if'][10]['record']['edit'] = false;
$zz['if'][10]['record']['delete'] = false;

unset($zz['subtitle']);
