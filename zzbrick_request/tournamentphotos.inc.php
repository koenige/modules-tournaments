<?php 

/**
 * tournaments module
 * Output tournament photos
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2016, 2018-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournamentphotos($vars, $settings, $event) {
	if (count($vars) !== 2) return false;
	if (!$event) return false;

	$sql = 'SELECT person_id, setzliste_no,
		CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS spieler
		FROM participations
		LEFT JOIN persons USING (contact_id)
		WHERE event_id = %d
		AND usergroup_id = /*_ID usergroups spieler _*/
		AND status_category_id = /*_ID categories participation-status/participant _*/
		ORDER BY setzliste_no, t_nachname, t_vorname
	';
	$sql = sprintf($sql, $event['event_id']);
	$event['spieler'] = wrap_db_fetch($sql, 'person_id');
	if (!$event['spieler']) return false;

	$photos = mf_mediadblink_media(
		[$event['year'], $event['main_series_path'], 'Website/Spieler'], [], 'person', array_keys($event['spieler'])
	);
	if (!$photos) {
		// while event is running, do not send 404 error mails if no photos are there yet
		if ($event['running']) wrap_setting('error_ignore_404');
		return false;
	}
	foreach ($photos as $id => $photo)
		$event['spieler'][$id] += $photo;

	$page['title'] = 'Teilnehmerphotos '.$event['event'].' '.$event['year'];
	$page['breadcrumbs'][]['title'] = 'Photos der Teilnehmer';
	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('tournamentphotos', $event);
	return $page;
}
