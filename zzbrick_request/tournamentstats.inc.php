<?php

/**
 * tournaments module
 * Output tournament statistics
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2015-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Gibt statistische Informationen zu einem Turnier aus
 *
 * @param array $vars = Kennung der Veranstaltung
 * @return array $page
 */
function mod_tournaments_tournamentstats($vars) {
	if (count($vars) !== 2) return false;

	$sql = 'SELECT category AS series
			, category_short AS series_short
			, SUBSTRING(path, 8) AS series_path
		FROM categories
		WHERE path = "reihen/%s"';
	$sql = sprintf($sql, $vars[1]);
	$data = wrap_db_fetch($sql);
	if (!$data) return false;
	$data['year'] = intval($vars[0]);

	// @todo weitere Status zu normal, zeitueberschreitung hinzufügen: adjudication, rules infraction
	// Alle Turniere
	$sql = 'SELECT events.event_id
			, IFNULL(series.category_short, event) AS event
			, events.identifier
			, (SELECT COUNT(*) FROM participations WHERE event_id = events.event_id AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id)) AND usergroup_id = /*_ID usergroups spieler _*/) AS tn_total
			, (SELECT COUNT(*) FROM participations LEFT JOIN persons USING (contact_id) WHERE event_id = events.event_id AND persons.sex = "male" AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id)) AND usergroup_id = /*_ID usergroups spieler _*/) AS tn_m
			, (SELECT COUNT(*) FROM participations LEFT JOIN persons USING (contact_id) WHERE event_id = events.event_id AND persons.sex = "female" AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id)) AND usergroup_id = /*_ID usergroups spieler _*/) AS tn_w
			, (SELECT ROUND(AVG(t_dwz)) FROM participations WHERE event_id = events.event_id AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id))) AS dwz_schnitt
			, (SELECT ROUND(AVG(t_elo)) FROM participations WHERE event_id = events.event_id AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id))) AS elo_schnitt
			, (SELECT COUNT(*) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis)) AS partien
			, (SELECT COUNT(*) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis) AND NOT ISNULL(halbzuege)) AS partien_mit_zuegen
			, (SELECT SUM(CEIL(halbzuege/2)) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis)) AS zuege
			, (SELECT COUNT(*) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis) AND weiss_ergebnis = 0.5) AS remis
			, (SELECT COUNT(*) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis) AND weiss_ergebnis = 1) AS siege_weiss
			, (SELECT COUNT(*) FROM partien WHERE event_id = events.event_id AND partiestatus_category_id IN (/*_ID categories partiestatus/normal _*/, /*_ID categories partiestatus/zeitueberschreitung _*/) AND NOT ISNULL(weiss_ergebnis) AND weiss_ergebnis = 0) AS siege_schwarz
			, (SELECT COUNT(nachricht_id) FROM spieler_nachrichten LEFT JOIN participations ON participations.participation_id = spieler_nachrichten.teilnehmer_id WHERE participations.event_id = events.event_id) AS tn_nachrichten
			, (SELECT COUNT(*) FROM teams WHERE teams.event_id = events.event_id AND teams.team_status = "Teilnehmer") AS teams
			, (SELECT AVG(YEAR(events.date_begin)-YEAR(date_of_birth)) FROM participations LEFT JOIN persons USING (contact_id) WHERE event_id = events.event_id AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id)) AND usergroup_id = /*_ID usergroups spieler _*/) AS average_age
			, IF(events.event_year != YEAR(events.date_begin), CAST(events.event_year AS SIGNED) - YEAR(events.date_begin), NULL) AS different_year
			, eventtypes.parameters AS eventtypes_parameters
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN events_categories
			ON events_categories.event_id = events.event_id
			AND events_categories.type_category_id = /*_ID categories events _*/
		LEFT JOIN categories eventtypes
			ON events_categories.category_id = eventtypes.category_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		AND (ISNULL(tournaments.urkunde_parameter) OR tournaments.urkunde_parameter NOT LIKE "%%statistik=0%%")
		ORDER BY series.sequence
	';
	$sql = sprintf($sql, wrap_db_escape($vars[1]), $vars[0]);
	$data['turniere'] = wrap_db_fetch($sql, 'event_id');
	if (empty($data['turniere'])) return false;
	$data['summe_total'] = 0;
	$data['summe_w'] = 0;
	$data['summe_m'] = 0;
	$data['summe_partien'] = 0;
	$data['summe_partien_notiert'] = 0;
	$data['summe_zuege'] = 0;
	$data['summe_remis'] = 0;
	$data['summe_siege_weiss'] = 0;
	$data['summe_siege_schwarz'] = 0;
	$data['summe_tn_nachrichten'] = 0;
	$data['summe_teams'] = 0;
	$data['summe_average_age'] = 0;

	// check for teams
	foreach ($data['turniere'] as $event_id => $event) {
		if ($event['eventtypes_parameters'])
			parse_str($event['eventtypes_parameters'], $data['turniere'][$event_id]['eventtypes_parameters']);
		if (!empty($data['turniere'][$event_id]['eventtypes_parameters']['tournaments_type_single']))
			$data['turniere'][$event_id]['teams'] = NULL;
		elseif ($event['teams']) $data['summe_teams'] += $event['teams'];
	}

	foreach ($data['turniere'] as $event_id => $event) {
		// Summen
		if ($data['summe_teams'] AND !$event['teams'])
			$data['turniere'][$event_id]['teams'] = '–';
		$data['summe_total'] += $event['tn_total'];
		$data['summe_w'] += $event['tn_w'];
		$data['summe_m'] += $event['tn_m'];
		if (!$event['tn_total']) {
			// show empty string instead of 0 if no data is available
			$data['turniere'][$event_id]['tn_total'] = '';
			$data['turniere'][$event_id]['tn_w'] = '';
			$data['turniere'][$event_id]['tn_m'] = '';
		}
		$data['summe_partien'] += $event['partien'];
		if ($event['zuege']) {
			$data['summe_partien_notiert'] += $event['partien_mit_zuegen'];
		}
		$data['summe_zuege'] += $event['zuege'];
		$data['summe_remis'] += $event['remis'];
		$data['summe_siege_weiss'] += $event['siege_weiss'];
		$data['summe_siege_schwarz'] += $event['siege_schwarz'];
		$data['summe_tn_nachrichten'] += $event['tn_nachrichten'];
		$data['summe_average_age'] += $event['average_age'] * $event['tn_total'];
		
		// Quoten
		if ($event['partien']) {
			$data['turniere'][$event_id]['quote_remis'] = $event['remis']/$event['partien'];
			$data['turniere'][$event_id]['quote_siege_weiss'] = $event['siege_weiss']/$event['partien'];
			$data['turniere'][$event_id]['quote_siege_schwarz'] = $event['siege_schwarz']/$event['partien'];
			if ($event['zuege']) {
				$data['turniere'][$event_id]['zuege_pro_partie'] = round($event['zuege']/$event['partien_mit_zuegen']);
			}
		} else {
			$data['turniere'][$event_id]['partien'] = '';
		}
	}
	if ($data['summe_partien']) {
		$data['quote_remis'] = $data['summe_remis']/$data['summe_partien'];
		$data['quote_siege_weiss'] = $data['summe_siege_weiss']/$data['summe_partien'];
		$data['quote_siege_schwarz'] = $data['summe_siege_schwarz']/$data['summe_partien'];
	}
	if ($data['summe_partien_notiert']) {
		$data['summe_zuege_pro_partie'] = round($data['summe_zuege']/$data['summe_partien_notiert']);
	}
	
	if ($data['summe_zuege']) {
		foreach ($data['turniere'] as $event_id => $event) {
			 $data['turniere'][$event_id]['summe_zuege'] = true;
		}
	} else {
		$data['summe_zuege'] = NULL;
	}
	if ($data['summe_average_age']) {
		$data['summe_average_age'] /= $data['summe_total'];
	}
	$show_empty_string_for_0 = [
		'summe_teams', 'summe_partien', 'summe_average_age',
		'summe_total' => ['summe_total', 'summe_m', 'summe_w']
	];
	foreach ($show_empty_string_for_0 as $key => $field) {
		if (is_array($field)) {
			if ($data[$key]) continue;
			$data[$key] = '';
			foreach ($field as $field_name) {
				$data[$field_name] = '';
			}
		} else {
			if ($data[$field]) continue;
			$data[$field] = '';
		}
	}
	// no players, no teams at all = no statistics!
	if (empty($data['summe_total']) AND empty($data['summe_teams'])) return false;

	$sql = 'SELECT CEIL(halbzuege/2) AS zuege, partie_id, event
		FROM partien
		LEFT JOIN events USING (event_id)
		WHERE halbzuege = (SELECT MAX(halbzuege) FROM partien WHERE event_id IN (%s))
		AND event_id IN (%s)';
	$sql = sprintf($sql, implode(',', array_keys($data['turniere'])), implode(',', array_keys($data['turniere'])));
	$data['laengste_partien'] = wrap_db_fetch($sql, 'partie_id');

	// check if there's a statistic for last and/or next year
	$sql = 'SELECT IFNULL(event_year, YEAR(events.date_begin)) AS year
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON series.main_category_id = main_series.category_id
		WHERE main_series.path = "reihen/%s"
		AND (ISNULL(tournaments.urkunde_parameter) OR tournaments.urkunde_parameter NOT LIKE "%%statistik=0%%")
		AND (
			(SELECT COUNT(team_id) FROM participations LEFT JOIN teams USING (team_id) WHERE participations.event_id = events.event_id AND teams.team_status = "Teilnehmer") > 0
			OR (SELECT COUNT(*) FROM participations WHERE participations.event_id = events.event_id AND status_category_id = /*_ID categories participation-status/participant _*/ AND (NOT ISNULL(brett_no) OR ISNULL(team_id)) AND usergroup_id = /*_ID usergroups spieler _*/) > 0
		)
		ORDER BY IFNULL(events.event_year, YEAR(events.date_begin))
	';
	$sql = sprintf($sql, wrap_db_escape($vars[1]));
	$data['all_years'] = wrap_db_fetch($sql, 'year');
	$data = array_merge($data, wrap_get_prevnext_flat($data['all_years'], $data['year'], false));
	if (!empty($data['_next_year'])) {
		$data['next'] = '../../../'.$data['_next_year'].'/'.$data['series_path'].'/statistik/';
		$page['link']['next'][0]['href'] = $data['next'];	
		$page['link']['next'][0]['title'] = 'Statistik des Turniers im folgenden Jahr';
	}
	if (!empty($data['_prev_year'])) {
		$data['prev'] = '../../../'.$data['_prev_year'].'/'.$data['series_path'].'/statistik/';
		$page['link']['prev'][0]['href'] = $data['prev'];	
		$page['link']['prev'][0]['title'] = 'Statistik des Turniers im Vorjahr';
	}

	$page['text'] = wrap_template('tournamentstats', $data);
	$page['breadcrumbs'][] = ['title' => $data['year'], 'url_path' => '../../'];
	$page['breadcrumbs'][] = ['title' => $data['series_short'], 'url_path' => '../'];
	$page['breadcrumbs'][]['title'] = 'Turnierstatistik';
	$page['dont_show_h1'] = true;
	$page['title'] = 'Turnierstatistik '.$data['series_short'].' '.$data['year'];
	return $page;
}
