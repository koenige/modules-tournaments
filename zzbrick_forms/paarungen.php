<?php 

/**
 * tournaments module
 * form script: pairings of a tournament
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2017, 2019-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$values['where'] = sprintf('WHERE event_id = %d', $brick['data']['event_id']);
$zz = zzform_include('paarungen', $values);
$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['where']['runde_no'] = $brick['vars'][2];

$zz['page']['breadcrumbs'][] = ['title' => $brick['vars'][2]];
$zz['page']['dont_show_title_as_breadcrumb'] = true;

if ($brick['vars'][2] < $brick['data']['runden_max']) {
	$zz['page']['link']['next'][0]['href'] = '../'.($brick['vars'][2] + 1).'/';	
	$zz['page']['link']['next'][0]['title'] = 'Nächste Runde';
}
if ($brick['vars'][2] > 1) {
	$zz['page']['link']['prev'][0]['href'] = '../'.($brick['vars'][2] - 1).'/';	
	$zz['page']['link']['prev'][0]['title'] = 'Vorherige Runde';
}
$zz['footer']['text'] = wrap_template('link-rel-nav');
