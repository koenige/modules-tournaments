<?php 

/**
 * tournaments module
 * table script: tournaments/time control
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015, 2017, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Turniere/Bedenkzeit';
$zz['table'] = 'turniere_bedenkzeiten';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tb_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id, event
	FROM tournaments
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'event';

$zz['fields'][3]['field_name'] = 'phase';
$zz['fields'][3]['type'] = 'number';
$zz['fields'][3]['auto_value'] = 'increment';
$zz['fields'][3]['def_val_ignore'] = true;

$zz['fields'][4]['title'] = 'Zeit';
$zz['fields'][4]['field_name'] = 'bedenkzeit_sec';
$zz['fields'][4]['type'] = 'number';
if (empty($zz_conf['multi'])) {
	$zz['fields'][4]['factor'] = 60;
}

$zz['fields'][5]['title'] = 'Bonus';
$zz['fields'][5]['field_name'] = 'zeitbonus_sec';
$zz['fields'][5]['type'] = 'number';

$zz['fields'][6]['title'] = 'Züge';
$zz['fields'][6]['field_name'] = 'zuege';
$zz['fields'][6]['type'] = 'number';


$zz['sql'] = 'SELECT turniere_bedenkzeiten.*
		, event
	FROM turniere_bedenkzeiten
	LEFT JOIN tournaments USING (tournament_id)
	LEFT JOIN events USING (event_id)
';
$zz['sqlorder'] = ' ORDER BY events.date_begin, phase';
