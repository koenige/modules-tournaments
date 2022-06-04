<?php

/**
 * tournaments module
 * send message to player during tournament
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2022 Erik Kothe
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * send a message to a player while the tournament is runnng
 *
 * @param array $vars
 * @return array $page
 */
function mod_tournaments_make_playermessage($vars, $settings) {
	global $zz_conf;
	global $zz_setting;
	
	$data = my_event($vars[0], $vars[1]);
	if (!$data) return false;

	$sql = 'SELECT participation_id, contact
		FROM participations
		LEFT JOIN persons USING (person_id)
		LEFT JOIN contacts USING (contact_id)
		WHERE event_id = %d
		AND setzliste_no = %d';
	$sql = sprintf($sql, $data['event_id'], $vars[2]);
	$data = array_merge($data, wrap_db_fetch($sql));
	if (!$data['contact']) return false;
	
	if (!empty($_GET['hash'])) {
		$sql = 'UPDATE spieler_nachrichten SET hidden = 0 WHERE hash like "%s"';
		$sql = sprintf($sql, wrap_db_escape($_GET['hash']));
		wrap_db_query($sql);
		$data['message_activated'] = true;
		$data['hide_form'] = true;
	} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$data = mod_tournaments_make_playermessage_send($data);
	}
	
	$page['query_strings'][] = 'hash';
	$page['text'] = wrap_template('playermessage', $data);
	$page['title'] = sprintf('Brett-Nachricht an %s – %s %d', $data['contact'], $data['event'], $data['year']);
	$page['dont_show_h1'] = true;
	$page['extra']['realm'] = 'sports';
	$page['breadcrumbs'][] = '<a href="../../../../">'.$data['year'].'</a>';
	if ($data['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../../../'.$data['main_series_path'].'/">'.$data['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../../../">'.$data['event'].'</a>';
	$page['breadcrumbs'][] = '<a href="../../">Startrangliste</a>';
	$page['breadcrumbs'][] = '<a href="../">'.$data['contact'].'</a>';
	$page['breadcrumbs'][] = 'Brett-Nachricht';
	return $page;
}

function mod_tournaments_make_playermessage_send($data) {
	$keys = ['mail', 'sender', 'message'];
	foreach ($keys as $key) {
		$data[$key] = isset($_POST[$key]) ? $_POST[$key] : '';
	}
	if (!wrap_mail_valid($data['mail'])) {
		$data['mail_address_invalid'] = true;
		return $data;
	}
	$data['hash'] = wrap_random_hash(20);
	$data['hide_form'] = true;

	$sql = 'INSERT INTO spieler_nachrichten
		(teilnehmer_id, nachricht, email, absender, ip, hash, hidden)
		VALUES (%d, "%s", "%s", "%s", "%s", "%s", 1)';
	$sql = sprintf($sql
		, $data['participation_id']
		, $data['message']
		, $data['mail']
		, $data['sender']
		, $_SERVER['REMOTE_ADDR']
		, $data['hash']
	);
	wrap_db_query($sql);

	$mail['to'] = $data['mail'];
	$mail['message'] = wrap_template('playermessage-mail', $data);
	wrap_mail($mail);
	$data['mail_sent'] = true;
	return $data;
}
