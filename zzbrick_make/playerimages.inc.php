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
			, t_vorname AS vorname
			, CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS person
			, event
			, contact_short
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
	$players = wrap_db_fetch($sql, 'person_id');
	$images = mf_mediadblink_media($params[0].'/'.$params[1], 'Website/Spieler', 'person', array_keys($players));
	$players = array_diff_key($players, $images);

	if (!empty($_POST['action'])) {
		foreach ($players as $player) {
			$sql = 'SELECT nachricht_id
				FROM spieler_nachrichten
				WHERE teilnehmer_id = %d AND bildnachricht = 1';
			$sql = sprintf($sql, $player['participation_id']);
			$message_received = wrap_db_fetch($sql);
			if ($message_received) continue;

			$nachricht = 'Hallo '.$player['vorname'].',
wir haben von dir leider noch kein Bild für die Teilnehmerseite. Wir würden uns sehr freuen, wenn du nach der Runde direkt bei uns im Büro des Öff.-Teams (Konferenzraum 30 im Keller) vorbeikommst. Dann können wir das Foto von dir machen. Vielen DANK.
';
			$sql = 'INSERT INTO spieler_nachrichten
				(nachricht, email, absender, teilnehmer_id, bildnachricht, ip, fertig, hash, hidden)
				VALUES ("%s", "presse@dem%d.de", "DEM Presse", %d, 1, "", 0, "", 0)';
			$sql = sprintf($sql, $nachricht, date('Y'), $player['participation_id']);
			wrap_db_query($sql);
		}
	}
	
	$page['text'] = wrap_template('playerimages', $players);
	$page['breadcrumbs'][] = 'Fehlende Spielerbilder';
	return $page;
}
