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
 * @copyright Copyright © 2022-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * send a message to a player while the tournament is runnng
 *
 * @param array $vars
 * @return array $page
 */
function mod_tournaments_make_playermessage($vars, $settings) {
	$data = my_event($vars[0], $vars[1]);
	if (!$data) return false;

	$sql = 'SELECT participation_id, contact
		FROM participations
		LEFT JOIN contacts USING (contact_id)
		WHERE event_id = %d
		AND setzliste_no = %d';
	$sql = sprintf($sql, $data['event_id'], $vars[2]);
	$data = array_merge($data, wrap_db_fetch($sql));
	if (!$data['contact']) return false;
	
	$sql = 'SELECT IF(spielernachrichten = "ja", NULL, 1)
		FROM tournaments
	    WHERE event_id = %d';
	$sql = sprintf($sql, $data['event_id']);
	$data['news_inactive'] = wrap_db_fetch($sql, '', 'single value');
	if ($data['news_inactive']) {
		$page['status'] = 404;
		$data['hide_form'] = true;
	}
	
	if (!empty($_GET['hash']) AND !$data['news_inactive']) {
		$sql = 'UPDATE spieler_nachrichten SET verified = "yes" WHERE hash like "%s"';
		$sql = sprintf($sql, wrap_db_escape($_GET['hash']));
		wrap_db_query($sql);
		$data['message_activated'] = true;
		$data['hide_form'] = true;
	} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' AND !$data['news_inactive']) {
		mod_tournaments_make_playermessage_send($data);
	}
	if (array_key_exists('sent', $_GET)) {
		$data['mail_sent'] = true;
		$data['hide_form'] = true;
	}
	
	$page['query_strings'][] = 'hash';
	$page['query_strings'][] = 'sent';
	$page['text'] = wrap_template('playermessage', $data);
	$page['title'] = sprintf('Brett-Nachricht an %s – %s %d', $data['contact'], $data['event'], $data['year']);
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = '<a href="../../../../">'.$data['year'].'</a>';
	if ($data['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../../../'.$data['main_series_path'].'/">'.$data['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../../../">'.$data['event'].'</a>';
	$page['breadcrumbs'][] = '<a href="../../">Startrangliste</a>';
	$page['breadcrumbs'][] = '<a href="../">'.$data['contact'].'</a>';
	$page['breadcrumbs'][]['title'] = 'Brett-Nachricht';
	$page['meta'][] = ['name' => 'robots', 'content' => 'noindex'];
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

	$sql = 'INSERT INTO spieler_nachrichten
		(teilnehmer_id, nachricht, email, absender, eintragszeit, ip, hash, verified)
		VALUES (%d, "%s", "%s", "%s", NOW(), "%s", "%s", "no")';
	$sql = sprintf($sql
		, $data['participation_id']
		, wrap_db_escape($data['message'])
		, $data['mail']
		, wrap_db_escape($data['sender'])
		, $_SERVER['REMOTE_ADDR']
		, $data['hash']
	);
	wrap_db_query($sql);

	$mail['to'] = $data['mail'];
	$mail['message'] = wrap_template('playermessage-mail', $data);
	wrap_mail($mail);
	return wrap_redirect_change('?sent');
}
