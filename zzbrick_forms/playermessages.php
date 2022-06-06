<?php 

/**
 * tournaments module
 * form script: player messages
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2015, 2017, 2019-2022 Gustaf Mossakowski
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

$zz_conf['export'][] = 'PDF Brettnachrichten';
