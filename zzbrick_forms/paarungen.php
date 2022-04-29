<?php 

/**
 * tournaments module
 * form script: pairings of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$values['where'] = sprintf('WHERE event_id = %d', $brick['data']['event_id']);
$zz = zzform_include_table('paarungen', $values);
$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['runde_no'] = $brick['vars'][2];

$zz_conf['breadcrumbs'][] = [
	'linktext' => 'Runden',
	'url' => $zz_setting['events_internal_path'].'/'.$brick['data']['identifier'].'/runde/'
];
$zz_conf['breadcrumbs'][] = ['linktext' => $brick['vars'][2]];
$zz_conf['dont_show_title_as_breadcrumb'] = true;

if ($brick['vars'][2] < $brick['data']['runden_max']) {
	$zz['page']['link']['next'][0]['href'] = '../'.($brick['vars'][2] + 1).'/';	
	$zz['page']['link']['next'][0]['title'] = 'Nächste Runde';
}
if ($brick['vars'][2] > 1) {
	$zz['page']['link']['prev'][0]['href'] = '../'.($brick['vars'][2] - 1).'/';	
	$zz['page']['link']['prev'][0]['title'] = 'Vorherige Runde';
}
$zz_conf['footer_text'] = wrap_template('link-rel-nav');
