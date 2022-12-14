<?php

/**
 * tournaments module
 * send player message if player image is missing
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017 Erik Kothe
 * @copyright Copyright © 2017, 2019-2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_make_playerimages($params, $settings, $event) {
	global $zz_setting;

	$event_ids = mf_tournaments_series_events($event['event_id']);
	$event_ids[] = $event['event_id'];

	$sql = 'SELECT person_id, participation_id
			, t_vorname AS first_name
			, CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS contact
			, event
			, contact_short
			, (SELECT IF(nachricht_id, 1, NULL)
				FROM spieler_nachrichten
				WHERE spieler_nachrichten.teilnehmer_id = participations.participation_id
				AND missing_image = "yes"
			) AS message_received
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN contacts
			ON contacts.contact_id = participations.federation_contact_id
		WHERE event_id IN (%s)
		AND usergroup_id = %d
		AND spielerphotos = "ja"
		AND spielernachrichten = "ja"
		AND teilnahme_status = "Teilnehmer"
		ORDER BY contacts.contact
	';
	$sql = sprintf($sql, implode(',', $event_ids), wrap_id('usergroups', 'spieler'));
	$event['players'] = wrap_db_fetch($sql, 'person_id');
	$images = mf_mediadblink_media([$params[0], $params[1], 'Website/Spieler'], 'person', array_keys($event['players']));
	$event['players'] = array_diff_key($event['players'], $images);
	$event['form'] = false;
	foreach ($event['players'] as $player) {
		if ($player['message_received']) continue;
		$event['form'] = true;
		break;
	}
	$event['sender'] = 'Presseteam '.$event['series_short'].' '.$event['year'];
	$event['sender_mail'] = $zz_setting['own_e_mail'];
	$event['msg'] = file(wrap_template_file('playerimages-mail'));
	$unset = true;
	foreach ($event['msg'] as $index => $line) {
		if (str_starts_with($line, '#') AND $unset) unset($event['msg'][$index]);
		elseif (str_starts_with($line, ' ')) $unset = false;
	}
	$event['msg'] = implode('', $event['msg']);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!empty($_POST['sender']))
			$event['sender'] = $_POST['sender'];
		if (!empty($_POST['sender_mail']) AND wrap_mail_valid($_POST['sender_mail']))
			$event['sender_mail'] = $_POST['sender_mail'];
		if (!empty($_POST['msg']))
			$event['msg'] = $_POST['msg'];
			
		$messages = 0;
		foreach ($event['players'] as $player) {
			if ($player['message_received']) continue;

			$msg = wrap_template($event['msg'], $player);
			$sql = 'INSERT INTO spieler_nachrichten
				(nachricht, email, absender, teilnehmer_id, eintragszeit, missing_image, ip, hash, verified)
				VALUES ("%s", "%s", "%s", %d, NOW(), "yes", "", "", "yes")';
			$sql = sprintf($sql
				, $msg
				, $event['sender_mail']
				, $event['sender']
				, $player['participation_id']
			);
			wrap_db_query($sql);
			$messages++;
		}
		wrap_redirect(sprintf('?sent=%d', $messages));
	}
	
	$page['query_strings'][] = 'sent';
	if (isset($_GET['sent'])) $event['sent_messages'] = intval($_GET['sent']);
	$page['text'] = wrap_template('playerimages', $event);
	$page['dont_show_h1'] = true;
	$page['title'] = 'Brett-Nachrichten wg. fehlender Spielerbilder – '.$event['series_short'].' '.$event['year'];
	$page['breadcrumbs'][] = 'Fehlende Spielerbilder';
	return $page;
}
