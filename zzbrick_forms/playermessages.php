<?php 

/**
 * tournaments module
 * form script: player messages
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include_table('spielernachrichten');

$data = [];
$data['current_time'] = date("Y-m-d H:i:s", time());

if (!empty($_POST['sent_date'])) {
	require_once $zz_conf['dir_inc'].'/validate.inc.php';

	$sql = 'UPDATE spieler_nachrichten
		SET processed = "%s"
		WHERE ISNULL(processed)
		AND eintragszeit < "%s"';
	$sql = sprintf($sql
		, zz_check_datetime($_POST['sent_date'])
		, zz_check_datetime($_POST['sent_date'])
	);
	$result = wrap_db_query($sql);
	$data['messages_sent'] = $result['rows'];
}

$zz['explanation'] = wrap_template('playermessage-form', $data);

$zz['filter'][1]['sql'] = 'SELECT DISTINCT(processed), processed
	FROM /*_PREFIX_*/spieler_nachrichten
	ORDER BY processed DESC';
$zz['filter'][1]['title'] = 'Verarbeitet';
$zz['filter'][1]['identifier'] = 'processed';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['field_name'] = 'processed';
$zz['filter'][1]['where'] = '/*_PREFIX_*/spieler_nachrichten.processed';
$zz['filter'][1]['default_selection'] = 'NULL';

$zz_conf['export'][] = 'PDF Brettnachrichten';
