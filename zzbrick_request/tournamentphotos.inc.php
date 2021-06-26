<?php 

/**
 * tournaments module
 * Output tournament photos
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2016, 2018-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournamentphotos($vars) {
	global $zz_setting;

	$sql = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin)) AS year, events.identifier
			, IF(LENGTH(main_series.path) > 7, SUBSTRING_INDEX(main_series.path, "/", -1), NULL) AS main_series_path
			, main_series.category_short AS main_series
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
			, IFNULL(place, places.contact) AS turnierort
		FROM events
		LEFT JOIN contacts places
			ON events.place_contact_id = places.contact_id
		LEFT JOIN addresses
			ON places.contact_id = addresses.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		WHERE events.identifier = "%d/%s"';
	$sql = sprintf($sql, $vars[0], wrap_db_escape($vars[1]));
	$event = wrap_db_fetch($sql);
	if (!$event) return false;

	$sql = 'SELECT person_id, setzliste_no,
		CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname) AS spieler
		FROM teilnahmen
		WHERE event_id = %d
		AND usergroup_id = %d
		AND teilnahme_status = "Teilnehmer"
		ORDER BY setzliste_no, t_nachname, t_vorname
	';
	$sql = sprintf($sql, $event['event_id'], wrap_id('usergroups', 'spieler'));
	$event['spieler'] = wrap_db_fetch($sql, 'person_id');
	if (!$event['spieler']) return false;

	$url = sprintf($zz_setting['mediaserver_website'], $event['year'].'/'.$event['main_series_path'], 'Website/Spieler');
// 	$url .=  '?meta=*'.$event['identifier'];
	$zz_setting['brick_cms_input'] = 'json';
	$bilder = brick_request_external($url, $zz_setting);
	unset($bilder['_']); // metadata
	if (!$bilder) return false;

	foreach ($bilder as $bild) {
		foreach ($bild['meta'] as $meta) {
			if ($meta['category_identifier'] !== 'person') continue;
			if (!in_array($meta['foreign_key'], array_keys($event['spieler']))) continue;
			$event['spieler'][$meta['foreign_key']] += $bild;
			continue 2;
		}
	}

	$page['extra']['realm'] = 'sports';
	$page['title'] = 'Teilnehmerphotos '.$event['event'].' '.$event['year'];
	$page['breadcrumbs'][] = '<a href="../../">'.$event['year'].'</a>';
	if ($event['main_series']) {
		$page['breadcrumbs'][] = '<a href="../../'.$event['main_series_path'].'/">'.$event['main_series'].'</a>';
	}
	$page['breadcrumbs'][] = '<a href="../">'.$event['event'].'</a>';
	$page['breadcrumbs'][] = 'Photos der Teilnehmer';
	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('tournamentphotos', $event);
	return $page;
}
