<?php 

/**
 * tournaments module
 * Participants of a federation
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht der Spieler/Teams eines Turniers nach Landesverband
 *
 * @param array $vars
 *		int [0]: Jahr
 *		string [1]: Kennung Reihe
 *		string [2]: 'lv'
 *		string [3]: Kennung Organisation
 */
function mod_tournaments_federation($vars, $settings, $event) {
	if (count($vars) !== 3) return false;

	$sql = 'SELECT contact_id, contact
			, IFNULL(country, contact_short) AS country
			, country_id
			, SUBSTRING(ok.identifier, 1, 1) AS zps_code, contacts.identifier
			, contact_abbr
		FROM contacts
		LEFT JOIN contacts_identifiers ok USING (contact_id)
		LEFT JOIN countries USING (country_id)
		WHERE (contacts.identifier = "%s" OR ok.identifier = "%s00")
		AND ok.current = "yes"
		AND mother_contact_id = %d';
	$sql = sprintf($sql
		, wrap_db_escape($vars[2])
		, wrap_db_escape($vars[2])
		, wrap_setting('contact_ids[dsb]')
	);
	$lv = wrap_db_fetch($sql);
	if (!$lv) return false;
	if ($vars[2] === $lv['zps_code']) {
		$path = wrap_path('tournaments_federation', [$vars[0].'/'.$vars[1], $lv['identifier']]);
		return wrap_redirect($path, 303);
	}
	$lv['year'] = intval($vars[0]);

	$sql = 'SELECT events.event_id, event, events.identifier AS event_identifier
			, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
			, main_series.category AS main_series, date_end
			, main_series.category_short AS main_series_short
			, IF(LENGTH(main_series.path) > 7, CONCAT(IFNULL(events.event_year, YEAR(events.date_begin)), "/", SUBSTRING_INDEX(main_series.path, "/", -1)), NULL) AS main_event_path
			, IF((SELECT COUNT(*) FROM participations
				WHERE event_id = events.event_id AND usergroup_id = %d), NULL, 1
			) AS keine_daten
			, IF((SELECT COUNT(*) FROM teams
				WHERE team_status IN ("Teilnehmer", "Teilnahmeberechtigt") AND event_id = events.event_id
				AND NOT ISNULL(teams.setzliste_no)), 1, 
				IF((SELECT COUNT(*) FROM participations
					WHERE participations.usergroup_id = %d AND event_id = events.event_id
					AND NOT ISNULL(participations.setzliste_no)), 1, NULL)
			) AS rangliste
			, IF((SELECT COUNT(*) FROM partien
				WHERE event_id = events.event_id AND ISNULL(weiss_ergebnis)), 1, NULL
			) AS zwischenstand
			, IF(tournaments.tabellenstand_runde_no = tournaments.runden
				AND (SELECT COUNT(*) FROM partien
				WHERE event_id = events.event_id AND ISNULL(weiss_ergebnis)) = 0, 1, NULL) AS endstand
			, SUBSTRING_INDEX(turnierformen.path, "/", -1) AS turnierform
			, tournaments.tabellenstand_runde_no AS runde_no
			, IF(spielerphotos = "ja", 1, NULL) AS spielerphotos
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN categories main_series
			ON main_series.category_id = series.main_category_id
		JOIN events_websites
			ON events_websites.event_id = events.event_id
			AND events_websites.website_id = %d
		WHERE main_series.path = "reihen/%s"
		AND IFNULL(events.event_year, YEAR(events.date_begin)) = %d
		ORDER BY series.sequence';
	$sql = sprintf($sql,
		wrap_id('usergroups', 'spieler'),
		wrap_id('usergroups', 'spieler'),
		wrap_setting('website_id'),
		wrap_db_escape($vars[1]), $vars[0]
	);
	$lv['events'] = wrap_db_fetch($sql, 'event_id');
	if (!$lv['events']) return false;
	$main_series = reset($lv['events']);
	$lv['main_series_short'] = $main_series['main_series_short'];
	$lv['main_event_path'] = $main_series['main_event_path'];
	$lv['main_series'] = $main_series['main_series'];
	$lv['turnierform'] = $main_series['turnierform'];

	$lv['map'] = false;
	if ($lv['turnierform'] !== 'e') {
		$lv['anzahl_teams'] = 0;
		$sql = 'SELECT teams.team_id, teams.identifier AS team_identifier
				, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
				, teams.event_id, IF(platz_no, platz_no, setzliste_no) AS no
				, setzliste_no
				, platz_no
				, IF(teilnehmerliste = "ja", IF(team_status = "Teilnehmer", 1, NULL), NULL) AS teilnehmerliste
				, tsw.wertung AS mp
				, (runde_no * IF(turniere_wertungen.wertung_category_id = %d, tournaments.bretter_min, 2) - tsw.wertung) AS mp_gegner
			FROM teams
			LEFT JOIN tournaments USING (event_id)
			LEFT JOIN turniere_wertungen
				ON tournaments.tournament_id = turniere_wertungen.tournament_id
				AND turniere_wertungen.reihenfolge = 1
			LEFT JOIN tabellenstaende
				ON teams.team_id = tabellenstaende.team_id
				AND runde_no = tournaments.tabellenstand_runde_no
			LEFT JOIN tabellenstaende_wertungen tsw
				ON tsw.tabellenstand_id = tabellenstaende.tabellenstand_id
				AND tsw.wertung_category_id = turniere_wertungen.wertung_category_id
			LEFT JOIN contacts
				ON teams.club_contact_id = contacts.contact_id 
			LEFT JOIN contacts_identifiers vereine
				ON contacts.contact_id = vereine.contact_id
				AND vereine.current = "yes"
			WHERE teams.event_id IN (%s)
			AND (IF(NOT ISNULL(vereine.identifier), SUBSTRING(vereine.identifier, 1, 1) = "%s", contacts.country_id = %d))
			AND teams.team_status IN ("Teilnehmer", "Teilnahmeberechtigt")
			ORDER BY platz_no, setzliste_no, teams.identifier';
		$sql = sprintf($sql
			, wrap_category_id('turnierwertungen/bp')
			, implode(',', array_keys($lv['events']))
			, $lv['zps_code']
			, $lv['country_id']
		);
		$teams = wrap_db_fetch($sql, ['event_id', 'team_id']);
		foreach ($teams as $event_id => $event_teams) {
			$lv['events'][$event_id]['teams'] = $event_teams;
			$lv['anzahl_teams'] += count($event_teams);
		}
		// Karte nur bei gemeldeten Spielern
		$sql = 'SELECT COUNT(*)
			FROM participations
			LEFT JOIN teams USING (team_id)
			LEFT JOIN contacts
				ON participations.club_contact_id = contacts.contact_id
			LEFT JOIN contacts_identifiers vereine
				ON contacts.contact_id = vereine.contact_id
				AND vereine.current = "yes"
			WHERE participations.event_id IN (%s)
			AND (teams.meldung = "komplett" OR teams.meldung = "teiloffen")
			AND (IF(NOT ISNULL(vereine.identifier), SUBSTRING(vereine.identifier, 1, 1) = "%s", contacts.country_id = %d))
			AND usergroup_id = %d';
		$sql = sprintf($sql,
			implode(',', array_keys($lv['events']))
			, $lv['zps_code']
			, $lv['country_id']
			, wrap_id('usergroups', 'spieler')
		);
		$lv['karte'] = wrap_db_fetch($sql, '', 'single value');
		// keine LV-Teams: keine Karte.
		if (empty($lv['anzahl_teams'])) $lv['karte'] = '';
	} else {
		$lv['anzahl_spieler'] = 0;
		$spielerphotos = false;
		$event_date_end = false;
		foreach ($lv['events'] as $event_id => $event) {
			if (!$event_date_end) $event_date_end = $event['date_end'];
			elseif ($event_date_end < $event['date_end']) $event_date_end = $event['date_end'];
			$lv['events'][$event_id]['e'] = true;
			if ($event['spielerphotos']) $spielerphotos = true;
		}
		$sql = 'SELECT participation_id, setzliste_no, persons.person_id
				, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS person
				, participations.event_id
				, tabellenstaende.platz_no
				, t_verein AS verein
				, tsw.wertung AS punkte
				, tabellenstaende.runde_no
			FROM participations
			LEFT JOIN persons USING (contact_id)
			LEFT JOIN tournaments USING (event_id)
			LEFT JOIN tabellenstaende
				ON tabellenstaende.person_id = persons.person_id
				AND tabellenstaende.event_id = participations.event_id
				AND runde_no = tournaments.tabellenstand_runde_no
			LEFT JOIN tabellenstaende_wertungen tsw
				ON tsw.tabellenstand_id = tabellenstaende.tabellenstand_id
				AND wertung_category_id = %d
			LEFT JOIN contacts
				ON participations.club_contact_id = contacts.contact_id 
			LEFT JOIN contacts_identifiers vereine
				ON contacts.contact_id = vereine.contact_id AND vereine.current = "yes"
			WHERE participations.event_id IN (%s)
			AND (IF(NOT ISNULL(vereine.identifier), SUBSTRING(vereine.identifier, 1, 1) = "%s", contacts.country_id = %d))
			AND participations.usergroup_id = %d
			AND status_category_id IN (%s%d)
			ORDER BY platz_no, setzliste_no, t_nachname, t_vorname
		';
		$sql = sprintf($sql
			, wrap_category_id('turnierwertungen/pkt')
			, implode(',', array_keys($lv['events']))
			, $lv['zps_code']
			, $lv['country_id']
			, wrap_id('usergroups', 'spieler')
			, $event_date_end > date('Y-m-d') ? sprintf('%d, ', wrap_category_id('participation-status/verified')) : ''
			, wrap_category_id('participation-status/participant')
		);
		$spieler = wrap_db_fetch($sql, ['event_id', 'participation_id']);
		$player_ids = [];
		foreach ($spieler as $event_id => $event_players) {
			foreach ($event_players as $player) {
				$player_ids[] = $player['person_id'];
			}
		}

		if ($lv['year'] >= wrap_setting('dem_spielerphotos_aus_mediendb') AND $spielerphotos) {
			$photos = mf_mediadblink_media([$lv['main_event_path'], 'Website/Spieler'], [], 'person', $player_ids);
			foreach ($spieler as $event_id => $event_players) {
				foreach ($event_players as $participation_id => $player) {
					if (!array_key_exists($player['person_id'], $photos)) continue;
					$spieler[$event_id][$participation_id]['bilder'][] = $photos[$player['person_id']];
					$lv['turnierphotos'] = true;
				}
			}
		}

		foreach ($spieler as $event_id => $event_players) {
			if (!empty($lv['turnierphotos'])) $lv['events'][$event_id]['turnierphotos'] = true;
			$lv['events'][$event_id]['spieler'] = $event_players;
			$lv['anzahl_spieler'] += count($event_players);
		}
		// has organisation member organisations? then show map
		$sql = 'SELECT COUNT(*) FROM contacts WHERE mother_contact_id = %d';
		$sql = sprintf($sql, $lv['contact_id']);
		$sub_orgs = wrap_db_fetch($sql, '', 'single value');
		if ($sub_orgs) $lv['karte'] = true;
	}

	$bilder = mf_mediadblink_media([$lv['main_event_path'], 'Website/Delegation']);
	// @todo add Landesverband below Organisations
	$lv_filename = strtolower(wrap_filename($lv['contact_abbr']));
	foreach ($bilder as $bild) {
		// @todo change nextline after real linking of LV images
		if (substr(strtolower($bild['identifier']), -strlen($lv_filename)) !== $lv_filename) continue;
		$lv['teambild'][$bild['object_id']] = $bild;
	}

	$page['breadcrumbs'][] = ['title' => $lv['country']];
	$page['dont_show_h1'] = true;
	$page['title'] = $lv['main_series_short'].' '.$lv['year'].', Teilnehmer aus '.$lv['country'];
	$page['text'] = wrap_template('federation', $lv);
	if (in_array('magnificpopup', wrap_setting('modules')))
		$page['extra']['magnific_popup'] = true;
	return $page;
}
