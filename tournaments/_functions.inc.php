<?php 

/**
 * tournaments module
 * common functions for tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Setzt Parameter für Filter für Tabellenstand
 * (Geschlecht, Alter, Wertung)
 *
 * @param string $filter_kennung
 * @return array
 */
function mf_tournaments_standings_filter($filter_kennung = false) {
	$filter = [];
	$filter['where'] = [];
	$filter['error'] = false;
	$filter['kennung'] = $filter_kennung;
	switch ($filter_kennung) {
	// @todo nur Filter erlauben, die auch in tournaments.tabellenstand eingetragen sind
	case 'w':
		$filter['where'][] = 'persons.sex = "female"';
		$filter['untertitel'] = 'weiblich';
		break;
	case 'm':
		$filter['where'][] = 'persons.sex = "male"';
		$filter['untertitel'] = 'männlich';
		break;
	case 'alt':
		$filter['where'][] = 'YEAR(persons.date_of_birth) = (IFNULL(events.event_year, YEAR(events.date_begin)) - alter_max)';
		$filter['untertitel'] = 'ältester Jahrgang';
		break;
	case 'jung':
		$filter['where'][] = 'YEAR(persons.date_of_birth) > (IFNULL(events.event_year, YEAR(events.date_begin)) - alter_max)';
		$filter['untertitel'] = 'jüngere Jahrgänge';
		break;
	// @todo u12, u10, u...
	// @todo 60+, 65+
	// @todo dwz<1200, dwz>1200, elo<1200 etc
	default:
		if ($filter_kennung) $filter['error'] = true;
		break;
	}
	return $filter;
}

/**
 * Gibt Endtabelle (Platz 1-3) pro Turnier aus
 *
 * @param mixed int or array Liste von Termin-IDs
 * @return array
 * @todo move to separate request script with own template
 */
function mf_tournaments_final_standings($event_ids) {
	$single = false;
	if (!is_array($event_ids)) {
		$single = $event_ids;
		$event_ids = [$event_ids];
	}
	// Wir gehen davon aus, dass bei beendeten Turnieren der Tabellenstand = Endstand ist
	$sql = 'SELECT event_id, runden, events.identifier
			, (SELECT COUNT(*) FROM teams
				WHERE spielfrei = "nein"
				AND team_status = "Teilnehmer"
				AND teams.event_id = events.event_id) AS teams
			, (SELECT COUNT(*) FROM participations
				LEFT JOIN persons USING (contact_id)
				WHERE status_category_id = %d
				AND usergroup_id = %d
				AND sex = "male"
				AND participations.event_id = events.event_id
				AND (NOT ISNULL(brett_no) OR tournaments.turnierform_category_id = %d)
			) AS spieler
			, (SELECT COUNT(*) FROM participations
				LEFT JOIN persons USING (contact_id)
				WHERE status_category_id = %d
				AND usergroup_id = %d
				AND sex = "female"
				AND participations.event_id = events.event_id
				AND (NOT ISNULL(brett_no) OR tournaments.turnierform_category_id = %d)
			) AS spielerinnen
			, tournaments.tabellenstaende
			, tournaments.*
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE event_id IN (%s)
		AND ((ISNULL(events.date_end) AND events.date_begin < CURDATE()) OR events.date_end < CURDATE())';
	$sql = sprintf($sql
		, wrap_category_id('participation-status/participant')
		, wrap_id('usergroups', 'spieler')
		, wrap_category_id('turnierformen/e')
		, wrap_category_id('participation-status/participant')
		, wrap_id('usergroups', 'spieler')
		, wrap_category_id('turnierformen/e')
		, implode(',', $event_ids)
	);
	$turniere = wrap_db_fetch($sql, 'event_id');
	$tabellenstaende = [];
	foreach ($turniere as $event_id => $turnier) {
		$tabellenstaende['gesamt'][] = $event_id;
		if (!$turnier['tabellenstaende']) continue;
		$staende = explode(',', $turnier['tabellenstaende']);
		foreach ($staende as $stand) {
			$tabellenstaende[$stand][] = $event_id;
		}
	}

	foreach ($tabellenstaende as $fkennung => $ids) {
		if ($fkennung === 'gesamt') {
			$filter[$fkennung]['where'][] = 'platz_no <= 3';
		} else {
			$filter[$fkennung] = mf_tournaments_standings_filter($fkennung);
		}

		$sql = 'SELECT tabellenstaende.event_id
				, tabellenstand_id, runde_no, platz_no
				, CONCAT(teams.team, IFNULL(CONCAT(" ", teams.team_no), "")) AS team
				, IF(tournaments.teilnehmerliste = "ja", teams.identifier, "") AS team_identifier
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, participations.setzliste_no
				, t_verein AS verein
				, persons.sex
			FROM tabellenstaende
			LEFT JOIN tournaments USING (event_id)
			LEFT JOIN teams USING (team_id)
			LEFT JOIN persons
				ON tabellenstaende.person_id = persons.person_id 
			LEFT JOIN participations
				ON participations.contact_id = persons.contact_id
				AND participations.event_id = tabellenstaende.event_id
				AND ISNULL(participations.team_id)
			WHERE tabellenstaende.event_id IN (%s)
			AND (ISNULL(participations.status_category_id) OR participations.status_category_id = %d)
			AND (%s)
			ORDER BY platz_no';
		$sql = sprintf($sql
			, implode(',', $ids)
			, wrap_category_id('participation-status/participant')
			, implode(') AND (', $filter[$fkennung]['where'])
		);
		$tabellen[$fkennung] = wrap_db_fetch($sql, ['event_id', 'tabellenstand_id']);
		foreach ($tabellen[$fkennung] as $event_id => $tabellenstand) {
			foreach ($tabellenstand as $ts_id => $platzierung) {
				if ($platzierung['runde_no'] !== $turniere[$event_id]['runden']) {
					unset($tabellen[$fkennung][$event_id][$ts_id]);
				} elseif (!$platzierung['team'] AND !$platzierung['person']) {
					// Brettrangliste in Mannschaftsturnier nicht ausgeben!
					unset($tabellen[$fkennung][$event_id][$ts_id]);
				}
			}
			if ($fkennung AND $tabellen[$fkennung][$event_id]) {
				$tabellen[$fkennung][$event_id] = array_slice($tabellen[$fkennung][$event_id], 0, 3);
				$tabellen[$fkennung][$event_id][0]['rang_no'] = 1;
				$tabellen[$fkennung][$event_id][1]['rang_no'] = 2;
				$tabellen[$fkennung][$event_id][2]['rang_no'] = 3;
			}
		}
	}
	if (empty($tabellen['gesamt'])) return [];

	foreach ($event_ids as $event_id) {
		$events[$event_id]['tabellen'] = [];
		if (!array_key_exists($event_id, $tabellen['gesamt'])) continue;
		$events[$event_id]['tabelle'] = $tabellen['gesamt'][$event_id];
		$events[$event_id]['runden'] = $turniere[$event_id]['runden'];
		$events[$event_id]['teams'] = $turniere[$event_id]['teams']
			? $turniere[$event_id]['teams'] : NULL;
		$events[$event_id]['spieler'] = $turniere[$event_id]['spieler']
			? $turniere[$event_id]['spieler'] :  NULL;
		$events[$event_id]['spielerinnen'] = $turniere[$event_id]['spielerinnen']
			? $turniere[$event_id]['spielerinnen'] :  NULL;
		foreach (array_keys($tabellen) as $ts) {
			if ($ts === 'gesamt') continue;
			if (!array_key_exists($event_id, $tabellen[$ts])) continue;
			$events[$event_id]['tabellen'][] = [
				'untertitel' => $filter[$ts]['untertitel'],
				'kennung_tab' => $filter[$ts]['kennung'],
				'weitere_tabelle' => $tabellen[$ts][$event_id],
				'identifier' => $turniere[$event_id]['identifier']
			];
		}
	}
	if ($single) return $events[$single];
	return $events;
}

/**
 * Zugehörige Teiltermine von Hauptreihe ermitteln
 *
 * @param int $event_id
 * @return array
 */
function mf_tournaments_series_events($event_id) {
	$sql = 'SELECT events.event_id
		FROM events
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN events main_events
			ON main_events.series_category_id = series.main_category_id
		AND IFNULL(main_events.event_year, YEAR(main_events.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
		WHERE main_events.event_id = %d';
	$sql = sprintf($sql, $event_id);
	$event_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
	return $event_ids;
}

/**
 * get all federations
 *
 * @return array
 */
function mf_tournaments_federations() {
	$sql = 'SELECT contact_id, contact, country
			, countries.identifier, country_id
		FROM contacts
		JOIN contacts_identifiers ok USING (contact_id)
		LEFT JOIN contacts_contacts USING (contact_id)
		JOIN countries USING (country_id)
		WHERE contact_category_id = %d
		AND ok.current = "yes"
		AND contacts_contacts.main_contact_id = %d
		AND contacts_contacts.relation_category_id = %d
		ORDER BY country';
	$sql = sprintf($sql
		, wrap_category_id('contact/federation')
		, wrap_setting('contact_ids[dsb]')
		, wrap_category_id('relation/member')
	);
	return wrap_db_fetch($sql, 'country_id');
}
