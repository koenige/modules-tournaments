<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2012-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Copyright (c) 2014 Erik Kothe <erik@deutsche-schachjugend.de>
// update standings for single tournaments


/**
 * Berechne den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param array $event
 * @param int $runde_no
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_calculate_einzel($event, $runde_no) {
	// gibt es überhaupt Partien in der Runde, die schon gespielt wurden?
	$sql = 'SELECT COUNT(partie_id)
		FROM partien
		WHERE event_id = %d AND runde_no = %d
		AND NOT ISNULL(weiss_ergebnis)';
	$sql = sprintf($sql, $event['event_id'], $runde_no);
	$anzahl_partien = wrap_db_fetch($sql, '', 'single value');
	if (!$anzahl_partien) return false;

	// Termin-ID setzen
	$sql = 'SELECT @event_id:=%d';
	$sql = sprintf($sql, $event['event_id']);
	wrap_db_query($sql);

	// Spieler auslesen
	$tabelleeinzeln = new cms_tabellenstand_einzel();
	$tabelleeinzeln->setAktRunde($runde_no);
	$tabelle = $tabelleeinzeln->getSpieler($event['event_id']);

	// Turnierwertungen
	$turnierwertungen = cms_tabellenstandupdate_wertungen($event['event_id']);
	if (in_array(wrap_category_id('turnierwertungen/3p'), array_keys($turnierwertungen))) {
		$tabelleeinzeln->setSieg(3);
		$tabelleeinzeln->setRemis(1);
	}
	foreach ($turnierwertungen as $id => $turnierwertung) {
		if (!function_exists($function = 'my_wertung_einzel_'.$turnierwertung['path'])) continue;
		$wertungen[$id] = $function($event['event_id'], $runde_no, $tabelle, $tabelleeinzeln);
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

	$tabelle = cms_tabellenstand_wertungen($event, $tabelle, $wertungen, $turnierwertungen);
	return $tabelle;
}

/**
 * Aktualisiere den Tabellenstand einer Runde eines Einzelturniers
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle Daten, berechnet aus cms_tabellenstand_calculate_einzel()
 * @return void
 * @todo return Anzahl der geänderten Datensätze, ggf.
 */
function cms_tabellenstand_write_einzel($event_id, $runde_no, $tabelle) {
	global $zz_setting;

	// Bestehenden Tabellenstand aus Datenbank auslesen
	$sql = 'SELECT person_id, tabellenstand_id
		FROM tabellenstaende
		WHERE event_id = %d
		AND runde_no = %d';
	$sql = sprintf($sql, $event_id, $runde_no);
	$tabellenstaende = wrap_db_fetch($sql, '_dummy_', 'key/value');

	// Werte für Partien gewonnen, unentschieden, verloren auslesen
	$sql = 'SELECT person_id
			, SUM(IF(schwarz_ergebnis = "1.0" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "1.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_g
			, SUM(IF(schwarz_ergebnis = "0.5" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "0.5" AND weiss_person_id = person_id, 1, 0))) AS spiele_u
			, SUM(IF(schwarz_ergebnis = "0.0" AND schwarz_person_id = person_id, 1, 
				IF(weiss_ergebnis = "0.0" AND weiss_person_id = person_id, 1, 0))) AS spiele_v
		FROM teilnahmen
		LEFT JOIN partien
			ON (partien.weiss_person_id = teilnahmen.person_id
			OR partien.schwarz_person_id = teilnahmen.person_id)
			AND partien.event_id = teilnahmen.event_id
		WHERE teilnahmen.event_id = %d
		AND runde_no <= %d
		AND teilnahme_status = "Teilnehmer"
		AND usergroup_id = %d
		GROUP BY person_id
	';
	$sql = sprintf($sql,
		$event_id, $runde_no, wrap_id('usergroups', 'spieler')
	);
	$guv = wrap_db_fetch($sql, 'person_id');
	$punktspalten = ['g', 'u', 'v'];

	// Daten in Datenbank schreiben
	foreach ($tabelle as $index => $stand) {
		$values = [];
		$values['ids'] = ['event_id', 'person_id'];
		// Hauptdatensatz
		// debug
		if (!array_key_exists('person_id', $stand)) {
			wrap_error('TABELLENSTAND '.json_encode($stand));
			continue;
		}
		if (array_key_exists($stand['person_id'], $tabellenstaende)) {
			$values['action'] = 'update';
			$values['POST']['tabellenstand_id'] = $tabellenstaende[$stand['person_id']];
		} else {
			$values['action'] = 'insert';
			$values['POST']['tabellenstand_id'] = '';
		}
		$values['POST']['event_id'] = $event_id;
		$values['POST']['runde_no'] = $runde_no;
		$values['POST']['person_id'] = $stand['person_id'];
		$values['POST']['platz_no'] = $stand['platz_no'];
		foreach ($punktspalten AS $ps) {
			$values['POST']['spiele_'.$ps] = isset($guv[$stand['person_id']]['spiele_'.$ps])
			? $guv[$stand['person_id']]['spiele_'.$ps] : 0;
		}

		// Feinwertungen, Detaildatensätze
		$values['POST']['wertungen'] = $stand['wertungen'];
		if ($values['action'] === 'update') {
			// überflüssige Feinwertungen löschen
			$sql = 'SELECT tsw_id, wertung_category_id FROM
				tabellenstaende_wertungen
				WHERE tabellenstand_id = %d';
			$sql = sprintf($sql, $tabellenstaende[$stand['person_id']]);
			$feinwertungen = wrap_db_fetch($sql, 'tsw_id');
			foreach ($feinwertungen as $bestandswertung) {
				if (in_array($bestandswertung['wertung_category_id'], array_keys($stand['wertungen']))) continue;
				$values['POST']['wertungen'][] = [
					'tsw_id' => $bestandswertung['tsw_id'],
					'wertung_category_id' => '',
					'wertung' => ''
				];
			}
		}
		$ops = zzform_multi('tabellenstaende', $values);
		if (!$ops['id']) {
			wrap_error('Tabellenstand konnte nicht aktualisiert oder hinzugefügt werden.
			Termin-ID: '.$event_id.', Runde: '.$runde_no.'. Fehler: '.implode(', ', $ops['error']), E_USER_ERROR);
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
class cms_tabellenstand_einzel {
	var $buchholz = [];
	var $buchholzSpieler = [];
	var $runde_no = 0;
	var $sieg = 1;
	var $remis = 0.5;

	function setAktRunde($runde) {
		$this->runde_no = $runde;
	}

	function setSieg($punkte) {
		$this->sieg = $punkte;
	}

	function setRemis($punkte) {
		$this->remis = $punkte;
	}

	function getSpieler($event_id) {
		global $zz_setting;
		$sql = 'SELECT event_id, person_id, t_vorname, t_nachname, setzliste_no
			FROM teilnahmen
			WHERE event_id = %d
			AND usergroup_id = %d
			AND teilnahme_status = "Teilnehmer"';
		$sql = sprintf($sql, $event_id, wrap_id('usergroups', 'spieler'));
		$spieler = wrap_db_fetch($sql, 'person_id');
		return $spieler;
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
		static $rundenergebnisse;
		if (empty($rundenergebnisse)) $rundenergebnisse = [];
		$buchholzsumme = [];

		// Welche Regelung wird angewendet?
		$korrektur = my_fide_wertungskorrektur($event_id);

		if (empty($rundenergebnisse)) {
			$sql = 'SELECT person_id, runde_no, partiestatus_category_id, gegner_id
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse
				WHERE runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $this->runde_no);
			$rundenergebnisse = wrap_db_fetch($sql, ['person_id', 'runde_no']);
		}
		$runden = $rundenergebnisse[$person_id];
		foreach ($runden as $runde) {
			if ($runde['gegner_id'] == NULL) continue;
			$buchholzsumme[$runde['gegner_id']] = $this->getBuchholz($event_id, $runde['gegner_id'], $korrektur, $variante);
		}

		if (count($buchholzsumme) < $this->runde_no) {
			if ($korrektur === 'fide-2012') {
			/* Für den Fall das nicht gepaart wurde */
				$rundenSumme = 1; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]) || $runden[$runde]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/kampflos').'') {
						$buchholzsumme['aktRunde'.$runde] = $rundenSumme + ($this->runde_no - $runde) * 0.5; // $this->remis?
					}
					if (!empty($runden[$runde])) {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				// Wichtig für Streichergebnisse!
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]['gegner_id'])) {
						$buchholzsumme['runde'.$runde] = 0;
					}
				}
			}
		}

		$buchholz = my_buchholz_varianten($buchholzsumme);
		return $buchholz[$variante];
	}

	/**
	 * Buchholz für Buchholzsumme auswerten
	 */
	function getBuchholz($event_id, $person_id, $korrektur, $variante) {
		if (isset($this->buchholzSpielerFein[$event_id][$person_id])) {
			return $this->buchholzSpielerFein[$event_id][$person_id][$variante];
		}

		$gegner_punkte = $this->getBuchholzGegnerPunkte($event_id, $person_id, $korrektur);

		if (count($gegner_punkte) < $this->runde_no) {
			$sql = 'SELECT runde_no, partiestatus_category_id
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse 
				WHERE person_id = %d
				AND runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $person_id, $this->runde_no);
			$runden = wrap_db_fetch($sql, 'runde_no');
			if ($korrektur === 'fide-2012') {
				$rundenSumme = 0; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde]) || $runden[$runde]['partiestatus_category_id'].'' === wrap_category_id('partiestatus/kampflos').'') {
						$gegner_punkte["runde".$runde] = $rundenSumme + ($this->runde_no-$runde) * $this->remis;
					}
					if (!empty($runden[$runde])) {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				// Wichtig für Streichergebnisse!
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde])) {
						$gegner_punkte['runde'.$runde] = 0;
					}
				}
			}
		}

		$buchholz = my_buchholz_varianten($gegner_punkte);
		$this->buchholzSpielerFein[$event_id][$person_id] = $buchholz;
		return $buchholz[$variante];
	}

	/**
	 * Buchholz auswerten
	 */
	function getBuchholzSpieler($event_id, $person_id) {
		static $kampflose_turnier;
		static $berechnet;
		if (empty($kampflose_turnier)) $kampflose_turnier = [];
		if (isset($this->buchholzSpieler[$event_id][$person_id])) {
			return $this->buchholzSpieler[$event_id][$person_id];
		}
		// Welche Regelung wird angewendet?
		$korrektur = my_fide_wertungskorrektur($event_id);

		// Hat Spieler eine Partie kampflos gewonnen?
		if (empty($berechnet)) {
			$sql = 'SELECT person_id, CONCAT(IFNULL(gegner_id, "freilos"), "-", runde_no) AS _index
					, gegner_id, runde_no
				FROM partien_einzelergebnisse 
				WHERE partiestatus_category_id = %d
				AND ergebnis = "1.0"
				AND runde_no <= %d';
			$sql = sprintf($sql, wrap_category_id('partiestatus/kampflos'), $this->runde_no);
			$kampflose_turnier = wrap_db_fetch($sql, ['person_id', '_index']);
			$berechnet = true; // zweite Variable, da Ergebnis leer falls keine kampflose
		}
		if (array_key_exists($person_id, $kampflose_turnier)) {
			$kampflose = $kampflose_turnier[$person_id];
		} else {
			$kampflose = [];
		}

		$gegner_punkte = $this->getBuchholzGegnerPunkte($event_id, $person_id, $korrektur, $kampflose);

		/* Testen ob nicht gepaart wurde */
		if (count($gegner_punkte) < $this->runde_no) {
			$sql = 'SELECT runde_no
					, (CASE ergebnis WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS ergebnis
				FROM partien_einzelergebnisse
				WHERE person_id = %d
				AND runde_no <= %d
				ORDER BY runde_no';
			$sql = sprintf($sql, $this->sieg, $this->remis, $person_id, $this->runde_no);
			$runden = wrap_db_fetch($sql, 'runde_no');
			if ($korrektur === 'fide-2012') {

				$rundenSumme = 1; // Beinhaltet die bis jetzt gespielten Punkte
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if (empty($runden[$runde])) {
						$gegner_punkte["aktRunde".$runde] = $rundenSumme + ($this->runde_no-$runde) * $this->remis;
					} else {
						$rundenSumme += $runden[$runde]["ergebnis"];
					}
				}
			} else {
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					$gegner_punkte['runde'.$runde] = 0;
				}
			}
		}

		$buchholz = my_buchholz_varianten($gegner_punkte);
		$this->buchholzSpieler[$event_id][$person_id] = $buchholz;
		return $buchholz;
	}

	/**
	 * Lese Punkte der Gegner aus
	 * Kampflose Partien werden unabhängig vom tatsächlichen Ergebnis mit 0.5 gewertet
	 * Runden ohne Paarung werden ebenfalls mit 0.5 gewertet
	 *
	 * @param int $event_id
	 * @param int $person_id
	 * @param string $korrektur
	 * @param array $kampflose (optional)
	 * @return array 
	 */
	function getBuchholzGegnerPunkte($event_id, $person_id, $korrektur, $kampflose = []) {
		static $gegnerpunkte;
		// Punkte pro Runde auslesen
		// Liste, bspw. [2005-1] => [1 => 0.5, 2 => 0.0 ...], [2909-2] => ()
		// fide-2009, fide-2012: kampflose Partien werden mit 0.5 gewertet
		$kampflos_als_remis = 0;
		if (in_array($korrektur, ['fide-2009', 'fide-2012'])) $kampflos_als_remis = 1;

		if (empty($gegnerpunkte)) {
			// Einmal pro Turnier berechnen, damit die teure Abfrage
			// nicht öfter gestellt werden muß
			$sql = 'SELECT person_id
					, CONCAT(gegner_id, "-", runde_no) AS _index
					, IF(partiestatus_category_id = %d AND %d = 1, %s, CASE punkte WHEN 1 THEN %s WHEN 0.5 THEN %s ELSE 0 END) AS buchholz
					, runde_gegner
				FROM buchholz_einzel_mit_kampflosen_view
				WHERE runde_no <= %d
				AND runde_gegner <= %d
				ORDER BY runde_no, gegner_id, runde_gegner';
			$sql = sprintf($sql
				, wrap_category_id('partiestatus/kampflos')
				, $kampflos_als_remis,
				$this->remis, $this->sieg, $this->remis, $this->runde_no, $this->runde_no
			);
			$gegnerpunkte = wrap_db_fetch($sql, ['person_id', '_index', 'runde_gegner', 'buchholz'], 'key/value');
		}
		if (in_array($person_id, $gegnerpunkte)) {
			$gegner_punkte_pro_runde = $gegnerpunkte[$person_id];
		} else {
			// only default wins so far
			$gegner_punkte_pro_runde = [];
		}

		if ($korrektur === 'fide-2012') {
			// Kampflose Siege?
			foreach ($kampflose as $gegner => $kampflos) {
				$freilos = !in_array($gegner, array_keys($gegner_punkte_pro_runde)) ? true : false;
				for ($runde = 1; $runde <= $this->runde_no; $runde++) {
					if ($runde > $kampflos['runde_no']) {
						// Runden nach kampfloser Paarung: 0.5 Punkte
						$punkte = $this->remis;
					} elseif ($freilos OR $runde == $kampflos['runde_no']) {
						// Bei Freilos: Runden vor kampfloser Paarung: 0 Punkte
						// Partie selbst wird ebenfalls mit 0 Punkten gewertet
						$punkte = 0;
					} else {
						// Gegner existiert, Partie kampflos
						// Tatsächliche Punkte bis zur Runde mit kampflosem Verlust,
						continue;
					}
					$gegner_punkte_pro_runde[$gegner][$runde] = $punkte;
				}
			}
		}

		// Punkte zusammenfassen pro Gegner
		$gegner_punkte = [];
		foreach ($gegner_punkte_pro_runde as $gegner => $punkte_pro_runde) {
			if ($korrektur === 'fide-2012') {
				// Falls weniger Runden als aktuelle Runde, pro Runde 0.5 Punkte addieren
				if (count($punkte_pro_runde) < $this->runde_no) {
					$punkte_pro_runde[] = ($this->runde_no - count($punkte_pro_runde)) * $this->remis;
				}
			}
			$gegner_punkte[$gegner] = array_sum($punkte_pro_runde);
		}
		return $gegner_punkte;
	}
}

/**
 * Brettpunkte für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_pkt($event_id, $runde_no) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Sonneborn-Berger für Einzelturniere berechnen
 * = Ergebnis x Punktzahl der Gegner nach der aktuellen Runde
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_sobo($event_id, $runde_no) {
	$sql = 'SELECT pe.person_id, SUM(punkte * ergebnis) AS sb
		FROM partien_einzelergebnisse pe
		LEFT JOIN buchholz_einzel_mit_kampflosen_view bhe
			ON pe.person_id = bhe.person_id
			AND pe.gegner_id = bhe.gegner_id
		WHERE runde_gegner <= %d
		AND pe.runde_no <= %d
		GROUP BY pe.person_id
		ORDER BY sb DESC
	';
	$sql = sprintf($sql, $runde_no, $runde_no);
	$wertungen = wrap_db_fetch($sql, ['person_id', 'sb'], 'key/value');
	return $wertungen;
}

/**
 * Drei-Punkte-Regelung für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_3p($event_id, $runde_no) {
	$sql = 'SELECT person_id, SUM(IF(ergebnis = 1, 3, IF(ergebnis = 0.5, 1, 0))) AS punkte
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
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_fort($event_id, $runde_no, $tabelle) {
	$sql = 'SELECT person_id, SUM((%d - runde_no + 1) * ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE runde_no <= %d
		AND NOT ISNULL(person_id)
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no, $runde_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach ($tabelle as $person_id => $stand) {
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
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_performance($event_id, $runde_no) {
	$sql = 'SELECT partien_einzelergebnisse.person_id, ROUND(SUM(IFNULL(IFNULL(t_elo, t_dwz), 0))/COUNT(partie_id)) AS wertung
		FROM partien_einzelergebnisse
		LEFT JOIN teilnahmen
			ON partien_einzelergebnisse.event_id = teilnahmen.event_id
			AND partien_einzelergebnisse.gegner_id = teilnahmen.person_id
		WHERE runde_no <= %d
		AND NOT ISNULL(partien_einzelergebnisse.person_id)
		AND NOT ISNULL(partien_einzelergebnisse.gegner_id)
		GROUP BY partien_einzelergebnisse.person_id
	';
	$sql = sprintf($sql, $runde_no);
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Gewinnpartien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_sw($event_id, $runde_no, $tabelle) {
	$sql = 'SELECT person_id, SUM(ergebnis) AS punkte
		FROM partien_einzelergebnisse
		WHERE ergebnis = 1
		AND runde_no <= %d
		GROUP BY person_id';
	$sql = sprintf($sql, $runde_no);
	$wertungen = wrap_db_fetch($sql, '_dummy_', 'key/value');
	foreach ($tabelle as $person_id => $stand) {
		if (array_key_exists($person_id, $wertungen)) continue;
		$wertungen[$person_id] = 0;
	}
	return $wertungen;
}

/**
 * gespielte Partien für Einzelturniere berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @return array Liste person_id => value
 */
function my_wertung_einzel_gespielte_partien($event_id, $runde_no) {
	global $zz_setting;
	$sql = 'SELECT person_id, COUNT(partie_id) AS partien
		FROM teilnahmen
		LEFT JOIN partien
			ON (teilnahmen.person_id = partien.schwarz_person_id
			OR teilnahmen.person_id = partien.weiss_person_id)
			AND partien.event_id = teilnahmen.event_id
		WHERE teilnahmen.event_id = %d
		AND partien.runde_no <= %d
		AND teilnahmen.usergroup_id = %d
		GROUP BY person_id
		ORDER BY COUNT(partie_id)';
	$sql = sprintf($sql, $event_id, $runde_no, wrap_id('usergroups', 'spieler'));
	return wrap_db_fetch($sql, '_dummy_', 'key/value');
}

/**
 * Platz in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @return array Liste person_id => value
 */
function my_wertung_einzel_rg($event_id, $runde_no, $tabelle) {
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $stand['setzliste_no'];
	}
	return $wertungen;
}

/**
 * Buchholz mit Korrektur in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_buchholz_korrektur($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz'];
	}
	return $wertungen;	
}

/**
 * Buchholz mit einer Streichwertung in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_1st($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz Cut 1'];
	}
	return $wertungen;	
}

/**
 * Buchholz mit zwei Streichwertungen in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_2st($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Buchholz Cut 2'];
	}
	return $wertungen;	
}

/**
 * Buchholz gemittelt in Setzliste berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_m($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$buchholz = $tabelleeinzeln->getBuchholzSpieler($event_id, $person_id);
		$wertungen[$person_id] = $buchholz['Median Buchholz'];
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_ii($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, eine Streichwertung, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_ii_1st($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 1');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, zwei Streichwertungen, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_ii_2st($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
		$wertungen[$person_id] = $tabelleeinzeln->getBuchholzsumme($event_id, $person_id, 'Buchholz Cut 2');
	}
	return $wertungen;	
}

/**
 * Verfeinerte Buchholz, gemittelt, für Tabelle berechnen
 *
 * @param int $event_id
 * @param int $runde_no
 * @param array $tabelle
 * @param object $tabelleeinzeln
 * @return array Liste person_id => value
 * @todo ggf. optimieren, dass alle Feinwertungen auf einmal berechnet werden
 */
function my_wertung_einzel_bhz_ii_m($event_id, $runde_no, $tabelle, $tabelleeinzeln) {
	$wertungen = [];
	foreach ($tabelle as $person_id => $stand) {
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
function my_buchholz_varianten($gegner_punkte) {
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

/**
 * Setze FIDE-Regelung für nicht gespielte Partien nach Datum des Beginns
 * eines Turniers
 *
 * @param int $event_id
 * @return string
 *		'ohne' => Punkte werden als Punkte gewertet
 *		'fide-2009' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/a
 *			für Turnier nach FIDE-Kongreß (?) = 2009-10-18
 *		'fide-2012' => FIDE Tournament Rules Annex 3: Tie-Break Regulations 2/F/b
 *			für Turniere nach 2012-07-01
 */
function my_fide_wertungskorrektur($event_id) {
	static $korrektur;
	if (empty($korrektur)) $korrektur = [];
	if (array_key_exists($event_id, $korrektur)) return $korrektur[$event_id];
	$sql = 'SELECT
			IF(date_begin >= "2012-07-01", "fide-2012",
				IF(date_begin >= "2009-10-18", "fide-2009", "ohne")) AS regelung
		FROM events
		WHERE event_id = %d';
	$sql = sprintf($sql, $event_id);
	$korrektur[$event_id] = wrap_db_fetch($sql, '', 'single value');
	return $korrektur[$event_id];
}
