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
	require_once $zz_setting['custom_wrap_dir'].'/anmeldung.inc.php';

	$event_ids = my_get_series_events($event['event_id']);
	$event_ids[] = $event['event_id'];

	$sql = 'SELECT person_id, participation_id
			, t_vorname AS first_name
			, CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS contact
			, event
			, contact_short
			, (SELECT IF(nachricht_id, 1, NULL)
				FROM spieler_nachrichten
				WHERE spieler_nachrichten.teilnehmer_id = participations.participation_id
				AND bildnachricht = 1
			) AS message_received
		FROM participations
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN contacts ON (contact_id = federation_contact_id)
		WHERE event_id IN (%s)
		AND usergroup_id = %d
		AND spielerphotos = "ja"
		AND spielernachrichten = "ja"
		AND teilnahme_status = "Teilnehmer"
		ORDER BY contact
	';
	$sql = sprintf($sql, implode(',', $event_ids), wrap_id('usergroups', 'spieler'));
	$event['players'] = wrap_db_fetch($sql, 'person_id');
	$images = mf_mediadblink_media($params[0].'/'.$params[1], 'Website/Spieler', 'person', array_keys($event['players']));
	$event['players'] = array_diff_key($event['players'], $images);
	$event['form'] = false;
	foreach ($event['players'] as $player) {
		if ($player['message_received']) continue;
		$event['form'] = true;
		break;
	}

	if (!empty($_POST['action'])) {
		$messages = 0;
		foreach ($event['players'] as $player) {
			if ($player['message_received']) continue;

			$msg = wrap_template('playerimages-mail', $player);
			$sql = 'INSERT INTO spieler_nachrichten
				(nachricht, email, absender, teilnehmer_id, bildnachricht, ip, fertig, hash, hidden)
				VALUES ("%s", "%s", "%s", %d, 1, "", 0, "", 0)';
			$sql = sprintf($sql
				, $msg
				, $zz_setting['own_e_mail']
				, 'Presseteam '.$event['series_short'].' '.$event['year']
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
