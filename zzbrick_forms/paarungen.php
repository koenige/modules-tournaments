<?php 

/**
 * tournaments module
 * form script: pairings of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2017, 2019-2020 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$event = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$event) wrap_quit(404);

$values['where'] = sprintf('WHERE event_id = %d', $event['event_id']);
$zz = zzform_include_table('paarungen', $values);
$zz['where']['event_id'] = $event['event_id'];
$zz['where']['runde_no'] = $brick['vars'][2];

my_event_breadcrumbs($event);
$zz_conf['breadcrumbs'][] = [
	'linktext' => 'Runden',
	'url' => '/intern/termine/'.$event['identifier'].'/runde/'
];
$zz_conf['breadcrumbs'][] = ['linktext' => $brick['vars'][2]];

if ($brick['vars'][2] < $event['runden_max']) {
	$zz['page']['link']['next'][0]['href'] = '../'.($brick['vars'][2] + 1).'/';	
	$zz['page']['link']['next'][0]['title'] = 'Nächste Runde';
}
if ($brick['vars'][2] > 1) {
	$zz['page']['link']['prev'][0]['href'] = '../'.($brick['vars'][2] - 1).'/';	
	$zz['page']['link']['prev'][0]['title'] = 'Vorherige Runde';
}
$zz_conf['footer_text'] = '<script type="text/javascript" src="/_behaviour/link-rel-nav.js"></script>';
