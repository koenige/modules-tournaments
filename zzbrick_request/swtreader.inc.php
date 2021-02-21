<?php 

/**
 * tournaments module
 * Output an SWT file
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014, 2017, 2019, 2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Anzeige einer SWT-Datei
 *
 * @param array $params
 *		int [0]: Jahr
 *		int [1]: Turnierkennung
 * @return array $page
 */
function mod_tournaments_swtreader($params) {
	global $zz_setting;
	global $zz_conf;

	ob_start();
	$dir = $zz_setting['media_folder'].'/swt/'.$params[0].'/';
	$filename = $params[1].'.swt';
	$own = './';
	require $zz_setting['lib'].'/swtparser/example.php';
	$page['text'] = ob_get_contents();
	$page['query_strings'] = ['view'];
	$page['dont_show_h1'] = true;
	$page['title'] = sprintf('SWT-Ansicht für %s %d', $params[1], $params[0]);
	$page['breadcrumbs'][] = sprintf('<a href="../../">%d</a>', $params[0]);
	$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $params[1]);
	$page['breadcrumbs'][] = 'SWT-Ansicht';
	ob_end_clean();
	return $page;
}
