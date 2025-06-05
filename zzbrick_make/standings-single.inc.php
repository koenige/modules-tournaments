<?php

/**
 * tournaments module
 * calculate standings for single tournaments
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Erik Kothe <kontakt@erikkothe.de>
 * @copyright Copyright © 2012-2022, 2024-2025 Gustaf Mossakowski
 * @copyright Copyright © 2014, 2022 Erik Kothe
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Berechne den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param array $event
 * @param int $round_no
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function mod_tournaments_make_standings_calculate_single($event, $round_no) {
	// gibt es überhaupt Partien in der Runde, die schon gespielt wurden?
	$sql = 'SELECT COUNT(*)
		FROM partien
		WHERE event_id = %d AND runde_no = %d
		AND NOT ISNULL(weiss_ergebnis)';
	$sql = sprintf($sql, $event['event_id'], $round_no);
	$anzahl_partien = wrap_db_fetch($sql, '', 'single value');
	if (!$anzahl_partien) return false;

	// Termin-ID setzen
	$sql = 'SELECT @event_id:=%d';
	$sql = sprintf($sql, $event['event_id']);
	wrap_db_query($sql);

	// Spieler auslesen
	$tabelleeinzeln = new mod_tournaments_make_standings_single();
	$tabelleeinzeln->setCurrentRound($round_no);
	$tabelle = $tabelleeinzeln->getSpieler($event['event_id']);

	// Turnierwertungen
	$turnierwertungen = mod_tournaments_make_standings_get_scoring($event['event_id']);
	if (in_array(wrap_category_id('turnierwertungen/3p'), array_keys($turnierwertungen))) {
		$tabelleeinzeln->setWin(3);
		$tabelleeinzeln->setDraw(1);
	} elseif (in_array(wrap_category_id('turnierwertungen/3-2-1'), array_keys($turnierwertungen))) {
		$tabelleeinzeln->setWin(3);
		$tabelleeinzeln->setDraw(2);
		$tabelleeinzeln->setLoss(1);
	}
	foreach ($turnierwertungen as $id => $turnierwertung) {
		if (!function_exists($function = 'mf_tournaments_make_single_'.$turnierwertung['path'])) continue;
		$wertungen[$id] = $function($event['event_id'], $round_no, $tabelle, $tabelleeinzeln);
	}

	$niedrig_besser = [
		wrap_category_id('turnierwertungen/rg'),
		wrap_category_id('turnierwertungen/p')
	];
	$null_punkte_bei_null = [
		wrap_category_id('turnierwertungen/p')
	];
	$null_komma_null_punkte_bei_null = [
		wrap_category_id('turnierwertungen/pkt')
	];

	if (empty($wertungen)) {
		wrap_error('Keine (möglichen) Wertungen in Turnierstand angegeben!', E_USER_ERROR);
	}
	foreach ($wertungen as $index => $values) {
		if (in_array($index, $null_punkte_bei_null)) {
			// auch wenn es noch keine gespielte Partie gibt: 0 Punkte!
			foreach (array_keys($tabelle) as $person_id) {
				if (isset($values[$person_id])) continue;
				$wertungen[$index][$person_id] = 0;
			}
		}
		if (in_array($index, $null_komma_null_punkte_bei_null)) {
			// auch wenn es noch keine gespielte Partie gibt: 0 Punkte!
			foreach (array_keys($tabelle) as $person_id) {
				if (isset($values[$person_id])) continue;
				$wertungen[$index][$person_id] = "0.0";
			}
		}
		if (in_array($index, $niedrig_besser)) {
			// höherer Wert = schlechter === nicht möglich
			asort($wertungen[$index]);
		} else {
			// höherer Wert = besser
			arsort($wertungen[$index]);
		}
	}

	$tabelle = mod_tournaments_make_standings_prepare($event, $tabelle, $wertungen, $turnierwertungen);
	return $tabelle;
}

/**
 * Aktualisiere den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle Daten, berechnet aus mod_tournaments_make_standings_calculate_single()
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function mod_tournaments_make_standings_write_single($event_id, $round_no, $tabelle) {
	// Bestehenden Tabellenstand aus Datenbank auslesen
	$sql = 'SELECT person_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d
		AND runde_no = %d';
	$sql = sprintf($sql, $event_id, $round_no);
	$tabellenstaende = wrap_db_fetch($sql, '_dummy_', 'key/value');

	// Werte für Partien gewonnen, unentschieden, verloren auslesen
	$sql = 'SELECT person_id
			, SUM(IF(schwarz_ergebnis = "1.0" AND schwarz_person_id = person_id, 1,
				IF(weiss_ergebnis = "1.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_g
			, SUM(IF(schwarz_ergebnis = "0.5" AND schwarz_person_id = person_id, 1,
				IF(weiss_ergebnis = "0.5" AND weiss_person_id = person_id, 1, 0))) AS spiele_u
			, SUM(IF(schwarz_ergebnis = "0.0" AND schwarz_person_id = person_id, 1,
				IF(weiss_ergebnis = "0.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_v
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN partien
			ON (partien.weiss_person_id = persons.person_id
			OR partien.schwarz_person_id = persons.person_id)
			AND partien.event_id = participations.event_id
		WHERE participations.event_id = %d
		AND runde_no <= %d
		AND status_category_id = /*_ID categories participation-status/participant _*/
		AND usergroup_id = /*_ID usergroups spieler _*/
		GROUP BY person_id
	';
	$sql = sprintf($sql, $event_id, $round_no);
	$guv = wrap_db_fetch($sql, 'person_id');
	$punktspalten = ['g', 'u', 'v'];

	// Daten in Datenbank schreiben
	foreach ($tabelle as $index => $stand) {
		// Hauptdatensatz
		// debug
		if (!array_key_exists('person_id', $stand)) {
			wrap_error('TABELLENSTAND '.json_encode($stand));
			continue;
		}
		$line = [
			'tabellenstand_id' => $tabellenstaende[$stand['person_id']] ?? NULL,
			'event_id' => $event_id,
			'runde_no' => $round_no,
			'person_id' => $stand['person_id'],
			'platz_no' => $stand['platz_no']
		];
		foreach ($punktspalten AS $ps) {
			$line['spiele_'.$ps] = isset($guv[$stand['person_id']]['spiele_'.$ps])
			? $guv[$stand['person_id']]['spiele_'.$ps] : 0;
		}
		// Feinwertungen, Detaildatensätze
		$line['wertungen'] = $stand['wertungen'];
		if ($line['tabellenstand_id']) {
			// überflüssige Feinwertungen löschen
			$sql = 'SELECT tsw_id, wertung_category_id FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $tabellenstaende[$stand['person_id']]);
			$feinwertungen = wrap_db_fetch($sql, 'tsw_id');
			foreach ($feinwertungen as $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$line['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
			zzform_update('tabellenstaende', $line, E_USER_ERROR);
		} else {
			zzform_insert('tabellenstaende', $line, E_USER_ERROR);
		}
	}
	return true;
}

/**
 * generiert einen Tabellenstand für ein Einzelturnier
 *
 * @author Erik Kothe
 * @author Gustaf Mossakowski
 */
class mod_tournaments_make_standings_single {
	var $buchholz = [];
	var $buchholzSpieler = [];
    var $buchholzSpielerFein = [];
	var $round_no = 0;
	var $win = 1;
	var $draw = 0.5;
	var $loss = 0;

	function setCurrentRound($round_no) {
		$this->round_no = $round_no;
	}

	function setWin($points) {
		$this->win = $points;
	}

	function setDraw($points) {
		$this->draw = $points;
	}

	function setLoss($points) {
		$this->loss = $points;
	}

	function getSpieler($event_id) {
		$sql = 'SELECT person_id, setzliste_no
			FROM participations
			LEFT JOIN persons USING (contact_id)
			WHERE event_id = %d
			AND usergroup_id = /*_ID usergroups spieler _*/
			AND status_category_id = /*_ID categories participation-status/participant _*/';
		$sql = sprintf($sql, $event_id);
		$spieler = wrap_db_fetch($sql, 'person_id');
		return $spieler;
	}
	
	function getRoundResults($person_id, $round_no = false) {
		static $round_results = [];
		if (!$round_results) {
			$sql = 'SELECT person_id, runde_no, partiestatus_category_id, gegner_id
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE %s END) AS ergebnis
				FROM partien_einzelergebnisse
				WHERE runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->win, $this->draw, $this->loss, $this->round_no);
			$round_results = wrap_db_fetch($sql, ['person_id', 'runde_no']);
		}
		if (!array_key_exists($person_id, $round_results)) return [];
		if ($round_no !== false) return $round_results[$person_id][$round_no];
		return $round_results[$person_id];
	}

	/**
	 * Buchholzsumme berechnen
	 *
	 * @param int $event_id
	 * @param int $person_id
	 * @param string $variante
	 * @return array
	 */
	function getBuchholzsumme($event_id, $person_id, $variante) {
		$buchholzsumme = [];

		$results = $this->getRoundResults($person_id);
		for ($round = 1; $round <= $this->round_no; $round++) {
			if (!array_key_exists($round, $results))
				$buchholzsumme[$round] = 0; // for Buchholz cut
			elseif ($results[$round]['gegner_id'] === NULL)
				$buchholzsumme[$round] = 0; // for Buchholz cut
			else
				$buchholzsumme[$round] = $this->getBuchholz($event_id, $results[$round]['gegner_id'], $variante);
		}
		$buchholz = mf_tournaments_make_buchholz_variants($buchholzsumme);
		return $buchholz[$variante];
	}

	/**
	 * Buchholz auswerten
	 */
	function getBuchholz($event_id, $person_id, $variante) {
		if (isset($this->buchholzSpielerFein[$event_id][$person_id])) {
			return $this->buchholzSpielerFein[$event_id][$person_id][$variante];
		}

		$gegner_punkte = $this->getBuchholzGegnerPunkte($event_id, $person_id);
		$buchholz = mf_tournaments_make_buchholz_variants($gegner_punkte);
		$this->buchholzSpielerFein[$event_id][$person_id] = $buchholz;
		return $buchholz[$variante];
	}

	/**
	 * Lese Punkte der Gegner aus
	 * Kampflose Partien werden unabhängig vom tatsächlichen Ergebnis mit 0.5 gewertet
	 * Runden ohne Paarung werden ebenfalls mit 0.5 gewertet
	 *
	 * @param int $event_id
	 * @param int $this_person_id
	 * @return array
	 */
	function getBuchholzGegnerPunkte($event_id, $this_person_id) {
		static $opponent_scores = [];
		// Punkte pro Runde auslesen
		// Liste, bspw. [2005-1] => [1 => 0.5, 2 => 0.0 ...], [2909-2] => ()
		$correction = mf_tournaments_make_fide_correction($event_id);

		if (!empty($opponent_scores[$this_person_id]))
			return $opponent_scores[$this_person_id];
		
		// fide-2009, fide-2012: kampflose Partien werden mit 0.5 gewertet
		$count_bye_as_draw = in_array($correction, ['fide-2009', 'fide-2012']) ? 1 : 0;

		$sql = 'SELECT own_scores.person_id
				, CONCAT(own_scores.gegner_id, "-", own_scores.runde_no) AS _index
				, IF(opponents_scores.partiestatus_category_id = /*_ID categories partiestatus/kampflos _*/ AND %d = 1, %s,
					CASE opponents_scores.ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE %s END
				) AS buchholz
				, opponents_scores.runde_no AS runde_gegner
			FROM partien_einzelergebnisse own_scores
			JOIN partien_einzelergebnisse opponents_scores
				ON own_scores.event_id = opponents_scores.event_id
				AND own_scores.gegner_id = opponents_scores.person_id
			WHERE own_scores.runde_no <= %d
			AND opponents_scores.runde_no <= %d
			AND NOT ISNULL(own_scores.person_id)
			AND own_scores.partiestatus_category_id != %d
			ORDER BY own_scores.runde_no, own_scores.gegner_id, opponents_scores.runde_no';
		$sql = sprintf($sql
			, $count_bye_as_draw, $this->draw
			, $this->win, $this->draw, $this->loss, $this->round_no, $this->round_no
			// FIDE 2012: exclude all byes, calculate individually
			, $correction === 'fide-2012' ? wrap_category_id('partiestatus/kampflos') : 0
		);
		$opponent_scores = wrap_db_fetch($sql, ['person_id', '_index', 'runde_gegner', 'buchholz'], 'key/value');

		if ($correction === 'fide-2012') {
			// Falls weniger Runden als aktuelle Runde, pro Runde 0.5 Punkte addieren
			foreach ($opponent_scores as $person_id => $scores) {
				foreach (array_keys($scores) as $opponent) {
					while (count($opponent_scores[$person_id][$opponent]) < $this->round_no) {
						$opponent_scores[$person_id][$opponent][] = $this->draw;
					}
				}
			}
		}

		// Punkte zusammenfassen pro Gegner
		foreach ($opponent_scores as $person_id => $opponents) {
			foreach ($opponents as $opponent => $scores) {
				$opponent_scores[$person_id][$opponent] = array_sum($scores);
			}
			// Testen ob nicht gepaart wurde
			if (count($opponent_scores[$person_id]).'' === $this->round_no.'') continue;
			$existing_rounds = [];
			foreach (array_keys($opponent_scores[$person_id]) as $opponent) {
				$existing_round = explode('-', $opponent);
				$existing_rounds[] = $existing_round[1];
			}
			for ($round = 1; $round <= $this->round_no; $round++) {
				if (in_array($round, $existing_rounds)) continue;
				if ($correction === 'fide-2012') {
					// SPR + (1 – SfPR) + 0.5 * (n – R)
					$round_data = $this->getRoundResults($person_id);
					$spr = 0;
					for ($round_played = 1; $round_played < $round; $round_played++) {
						if (!array_key_exists($round_played, $round_data)) continue;
						$spr += $round_data[$round_played]['ergebnis'];
					}
					$sfpr = array_key_exists($round, $round_data)
						? $round_data[$round]['ergebnis'] : 0;
					$opponent_scores[$person_id]['bye-'.$round]
						= $spr + 1 - $sfpr + $this->draw * ($this->round_no - $round);
				} else {
					// Wichtig für Streichergebnisse!
					$opponent_scores[$person_id]['bye-'.$round] = 0;
				}
			}
		}
		// person might not have been paired (yet)
		if (!array_key_exists($this_person_id, $opponent_scores)) return [];
		return $opponent_scores[$this_person_id];
	}
}

/**
 * Brettpunkte für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_pkt($event_id, $round_no) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $round_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Sonneborn-Berger für Einzelturniere berechnen
 * = Ergebnis x Punktzahl der Gegner nach der aktuellen Runde
 *
 * @param int $event_id
 * @param int $round_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_sobo($event_id, $round_no) {
	$sql = 'SELECT own_scores.person_id
			, SUM(opponents_scores.ergebnis * own_scores.ergebnis) AS sb
		FROM partien_einzelergebnisse own_scores
		JOIN partien_einzelergebnisse opponents_scores
			ON own_scores.event_id = opponents_scores.event_id
			AND own_scores.gegner_id = opponents_scores.person_id
		WHERE opponents_scores.runde_no <= %d
		AND own_scores.runde_no <= %d
		AND NOT ISNULL(own_scores.person_id)
		GROUP BY own_scores.person_id
		ORDER BY sb DESC, person_id';
	$sql = sprintf($sql, $round_no, $round_no);
	$wertungen = wrap_db_fetch($sql, ['person_id', 'sb'], 'key/value');
	return $wertungen;
}

/**
 * Drei-Punkte-Regelung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_3p($event_id, $round_no) {
	$sql = 'SELECT person_id, SUM(IF(ergebnis = 1, 3, IF(ergebnis = 0.5, 1, 0))) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $round_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * 3-2-1-Punkte-Regelung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_3_2_1($event_id, $runde_no) {
	$sql = 'SELECT person_id, SUM(IF(ergebnis = 1, 3, IF(ergebnis = 0.5, 2, 1))) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Fortschrittswertung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_fort($event_id, $round_no, $tabelle) {
	$sql = 'SELECT person_id, SUM((%d - runde_no + 1) * ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $round_no, $round_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach (array_keys($tabelle) as $person_id) {
		if (array_key_exists($person_id, $wertungen)) continue;
		$wertungen[$person_id] = 0;
	}
	return $wertungen;
}

/**
 * Gegnerschnitt für Einzelturniere berechnen
 * Elo vor DWZ
 *
 * Schnitt nur über Ergebnisse gegen einen Gegner, falls Freilos wird Runde
 * nicht gewertet! = NOT ISNULL(partien_einzelergebnisse.gegner_id)
 * @param int $event_id
 * @param int $round_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_performance($event_id, $round_no) {
	$sql = 'SELECT partien_einzelergebnisse.person_id
			, ROUND(SUM(IFNULL(IFNULL(t_elo, t_dwz), 0))/COUNT(partie_id)) AS wertung
		FROM partien_einzelergebnisse
		LEFT JOIN persons
			ON partien_einzelergebnisse.gegner_id = persons.person_id
		LEFT JOIN participations
			ON partien_einzelergebnisse.event_id = participations.event_id
			AND persons.contact_id = participations.contact_id
		WHERE runde_no <= %d
		AND NOT ISNULL(partien_einzelergebnisse.person_id)
		AND NOT ISNULL(partien_einzelergebnisse.gegner_id)
		GROUP BY partien_einzelergebnisse.person_id
	';
	$sql = sprintf($sql, $round_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Gewinnpartien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_sw($event_id, $round_no, $tabelle) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE ergebnis = 1
		AND runde_no <= %d
		GROUP BY person_id';
	$sql = sprintf($sql, $round_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach (array_keys($tabelle) as $person_id) {
		if (array_key_exists($person_id, $wertungen)) continue;
		$wertungen[$person_id] = 0;
	}
	return $wertungen;
}

/**
 * gespielte Partien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_gespielte_partien($event_id, $round_no) {
	$sql = 'SELECT person_id, COUNT(partie_id) AS partien
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN partien
			ON (persons.person_id = partien.schwarz_person_id
			OR persons.person_id = partien.weiss_person_id)
			AND partien.event_id = participations.event_id
		WHERE participations.event_id = %d
		AND partien.runde_no <= %d
		AND participations.usergroup_id = /*_ID usergroups spieler _*/
		GROUP BY person_id
		ORDER BY COUNT(partie_id)';
	$sql = sprintf($sql, $event_id, $round_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Platz in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function mf_tournaments_make_single_rg($event_id, $round_no, $tabelle) {
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $stand['setzliste_no'];
	}
	return $wertungen;
}

/**
 * Buchholz mit Korrektur in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholz($event_id, $person_id, 'Buchholz');
	}
	return $wertungen;
}

/**
 * Buchholz mit einer Streichwertung in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_1st($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholz($event_id, $person_id, 'Buchholz Cut 1');
	}
	return $wertungen;
}

/**
 * Buchholz mit zwei Streichwertungen in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_2st($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholz($event_id, $person_id, 'Buchholz Cut 2');
	}
	return $wertungen;
}

/**
 * Buchholz gemittelt in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_m($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholz($event_id, $person_id, 'Median Buchholz');
	}
	return $wertungen;
}

/**
 * Verfeinerte Buchholz für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_ii($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz');
	}
	return $wertungen;
}

/**
 * Verfeinerte Buchholz, eine Streichwertung, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_ii_1st($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 1');
	}
	return $wertungen;
}

/**
 * Verfeinerte Buchholz, zwei Streichwertungen, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_ii_2st($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 2');
	}
	return $wertungen;
}

/**
 * Verfeinerte Buchholz, gemittelt, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $round_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function mf_tournaments_make_single_bhz_ii_m($event_id, $round_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach (array_keys($tabelle) as $person_id) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Median Buchholz');
	}
	return $wertungen;
}

/**
 * Buchholz-Varianten berechnen
 *
 * @param array $gegner_punkte Liste der Punkte der Gegner bzw. Buchholz
 * @return array
 */
function mf_tournaments_make_buchholz_variants($gegner_punkte) {
	// Reine Buchholz
	$buchholz['Buchholz'] = array_sum($gegner_punkte);

	// Cut 1: schlechteste Wertung streichen
	arsort($gegner_punkte);
	array_pop($gegner_punkte);
	$buchholz['Buchholz Cut 1'] = array_sum($gegner_punkte);

	// Cut 2: zwei schlechteste Wertungen streichen
	$cut2 = $gegner_punkte;
	array_pop($cut2);
	$buchholz['Buchholz Cut 2'] = array_sum($cut2);

	// Median: schlechteste und beste Wertung streichen
	array_shift($gegner_punkte);
	$buchholz['Median Buchholz'] = array_sum($gegner_punkte);

	// Median 2: zwei schlechteste und zwei beste Wertungen streichen
	array_shift($cut2);
	array_shift($cut2);
	$buchholz['Median Buchholz 2'] = array_sum($cut2);

	return $buchholz;
}
