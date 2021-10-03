<?php

/**
 * tournaments module
 * Functions for reading PGN files
 * PGN = Portable Game Notation for chess games
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2016, 2019-2021 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Search a PGN file to find a game that corresponds to some search strings
 *
 * @param string $file
 * @param array $search
 * @return array
 */
function pgn_find($file, $search) {
	global $zz_conf;
	require_once $zz_conf['dir'].'/functions.inc.php';
	
	$pgn = [];
	if (!file_exists($file)) {
		$pgn['error'] = sprintf('File %s does not exist', $file);
		return $pgn;
	}
	$pgn = file($file);
	$games = pgn_parse($pgn, $file);
	foreach ($games as $game) {
		$found = true;
		foreach ($search as $q => $string) {
			if (!isset($game['head'][$q])) {
				$found = false;
			} elseif ($game['head'][$q] !== $string) {
				// @todo use similar_text or wrap_filename for ASCII comparison
				if (wrap_filename($game['head'][$q]) !== wrap_filename($string)) {
					$found = false;
				}
			}
		}
		if ($found) return $game;
	}
	return [];
}

/**
 * Parse a PGN file, return an array
 *
 * @param string $pgn
 * @param string $filename (for errors)
 * @return array
 *		0 => array
 *			head => array
 *				Event => EventName ...
 *			moves => string
 */
function pgn_parse($pgn, $filename = false) {
	$pgn = pgn_correct_line_breaks($pgn);
	$pgn = pgn_fix_headers($pgn);
	$pgn = pgn_add_newlines($pgn);
	$pgn = pgn_remove_double_emptylines($pgn);
	$head = true;
	$i = 0;
	$games = [];
	foreach ($pgn as $line_no => $line) {
		$line = trim($line);
		// PGN, Chapter 6: Escape mechanism
		if (substr($line, 0, 1) === '%') continue;
		if ($head) {
			// empty line terminates head, but only if there's something
			// (there might be two or more empty lines before head)
			if (!$line AND !empty($games[$i]['head'])) {
				$head = false;
			}
			if (!$line) continue;
			preg_match('/^\[(.+) "(.*)"\]$/', $line, $matches);
			if (!$matches) {
				wrap_log(sprintf(
					'PGN: Cannot interpret line %d as tag pair: %s (Filename: %s)'
					, $line_no, $line, $filename ? $filename : 'unknown'
				));
			} else {
				$games[$i]['head'][$matches[1]] = utf8_encode($matches[2]);
			}
			$games[$i]['moves'] = '';
		} else {
			if (!$line) {
				$head = true;
				$i++;
				continue;
			}
			$games[$i]['moves'] .= $line.' ';
		}
	}
	foreach ($games as $index => $game) {
		$games[$index]['moves'] = utf8_encode(wrap_convert_string($game['moves'], 'iso-8859-1'));
		$games[$index]['head'] = wrap_convert_string($games[$index]['head'], 'iso-8859-1');
		$games[$index]['head'] = wrap_convert_string($games[$index]['head'], 'utf-8');
	}
	
	return $games;
}

/**
 * Check if some weird editor uses different line breaks in one file
 * (some even use CR, CRLF and LF in the same file)
 *
 * @param array $pgn
 * @return array
 */
function pgn_correct_line_breaks($pgn) {
	// bad lines have only CR instead of CRLF
	$bad_lines = [];
	foreach ($pgn as $index => $line) {
		if (substr($line, 0, 1) !== "\r") continue;
		if (substr($line, 0, 2) === "\r\n") continue;
		$pgn[$index] = substr($line, 1);
		$bad_lines[] = $index;
	}
	// insert correct CRLF before bad line
	foreach ($bad_lines as $index) {
		array_splice($pgn, $index, 0, ["\r\n"]);	
	}

	// even worse lines have CR somewhere inside of the line
	$bad_lines = [];
	foreach ($pgn as $index => $line) {
		if (strstr(substr($line, 1, -2), "\r")) {
			$bad_lines[] = $index;
		}
	}
	$i = 0;
	foreach ($bad_lines as $index) {
		$index += $i;
		$lines = explode("\r", trim($pgn[$index]));
		unset($pgn[$index]);
		foreach ($lines as $no => $line) {
			if (substr($line, -2) === "\r\n") continue;
			$lines[$no] = trim($line)."\r\n";
		}
		array_splice($pgn, $index, 0, $lines);
		$i += count($lines) - 1;
	}
	return $pgn;
}

/**
 * add newlines before '[Event'
 * (if it is the first element of game head only)
 *
 * @param array $pgn
 * @return array
 */
function pgn_add_newlines($pgn) {
	$bad_lines = [];
	foreach ($pgn as $index => $line) {
		if (substr($line, 0, 8) !== '[Event "') continue;
		if (!array_key_exists($index - 1, $pgn)) continue;
		if (!trim($pgn[$index - 1])) continue;
		if (substr($pgn[$index - 1], 0, 1) === '['
			AND substr($pgn[$index - 1], 0, 2) !== '[%') continue;
		$bad_lines[] = $index;
	}
	// insert correct CRLF before bad line
	$i = 0;
	foreach ($bad_lines as $index) {
		array_splice($pgn, $index + $i, 0, ["\r\n"]);
		$i++;
	}
	return $pgn;
}

/**
 * remove unnecessary empty lines (one empty line is enough)
 *
 * @param array $pgn
 * @return array
 */
function pgn_remove_double_emptylines($pgn) {
	foreach ($pgn as $index => $line) {
		if (!$index) continue; // ignore first line
		if (!trim($line) AND !trim($pgn[$index - 1])) {
			unset($pgn[$index - 1]);
		}
	}
	$pgn = array_values($pgn);
	return $pgn;
}

/**
 * Translate pieces to a different language than English
 *
 * @param string $move
 * @param string $language
 * @return string
 */
function pgn_translate_pieces($move, $language) {
	$piece = substr($move, 0, 1);
	switch ($language) {
	case 'en':
		break;
	case 'de':
		switch ($piece) {
			case 'Q': $piece = 'D'; break;
			case 'B': $piece = 'L'; break;
			case 'R': $piece = 'T'; break;
			case 'N': $piece = 'S'; break;
		}
		break;
	}
	return $piece.substr($move, 1, strlen($move));
}

/**
 * Output a parsed PGN game to HTML
 *
 * @param array $pgn
 *		string 'moves' = Moves
 * @param array $extra_comment
 *		comment from an external source which will be included in HTML output
 * @return array $game
 *		bool 'finished'
 *		int 'move' (last move)
 *		string 'html'
 *		string 'pgn'
 */
function pgn_to_html($pgn, $extra_comment = []) {
	global $zz_setting;
	// do some cleanup
	if (empty($pgn['moves'])) {
		$game = ['bool' => '*', 'move' => 0, 'html' => '', 'pgn' => ''];
		return $game;
	}
	$moves = $pgn['moves'];
	$moves = preg_replace('/{\[\%emt \d+:\d+:\d+\]} /', '', $moves);
	$moves = preg_replace('/{\[\%evp [^\]]+?]} /', '', $moves);
	$moves = preg_replace('/\[\%eval [-\d#.]*\]/', '', $moves);
	$moves = str_replace('Diagramm #', '', $moves);
	$moves = str_replace('{', ' { ', $moves);
	$moves = str_replace('}', ' } ', $moves);
	$moves = str_replace('(', ' ( ', $moves);
	$moves = str_replace(')', ' ) ', $moves);
	$moves = str_replace('  ', ' ', $moves);
	$moves = str_replace('  ', ' ', $moves);
	$moves = trim($moves);
	$moves = explode(' ', $moves);

	$game['finished'] = false;
	$game['move'] = 1;
	$comment = false;
	$variant = 0;
	$clock = false;
	$game['html'] = '';
	$game['pgn'] = '';
	$tokens_since_move = false;
	foreach ($moves as $move) {
		$move = trim($move);
		if (substr($move, -1) === '.' AND !$comment AND !$variant) {
			if (!$tokens_since_move AND substr($move, -3) === '...') continue;
			$game['html'] .= $move.' ';
			$game['pgn'] .= $move.' ';
			$tokens_since_move = false;
		} elseif ($move == '1-0' OR $move == '0-1' or $move == '1/2-1/2') {
			$game['html'] .= '<strong>'.$move.'</strong>';
			$game['finished'] = true;
		} elseif ($move == '*') {
			$game['html'] .= ' (Partie noch nicht beendet)';
		} elseif ($move === '(' AND !$comment) {
			$variant++;
			$game['html'] .= '(';
			$tokens_since_move = true;
		} elseif ($move === ')' AND !$comment) {
			$variant--;
			if (substr($game['html'], -1) === ' ')
				$game['html'] = substr($game['html'], 0, -1);
			$game['html'] .= ') ';
			$tokens_since_move = true;
		} elseif ($move === '{' OR $comment) {
			if ($move === '[%clk') {
				$clock = true;
				$move = '';
			} elseif ($clock) {
				if ($game['move'] & 1) {
					$game['BlackClock'] = substr($move, 0, -1);
				} else {
					$game['WhiteClock'] = substr($move, 0, -1);
				}
				$move = '';
				$clock = false;
			}
			$comment = true;
			if ($move === '}') {
				$comment = false;
			}
			if ($move !== '{' AND $move !== '}') {
				$game['html'] .= $move.' ';
			}
		} elseif (substr($move, 0, 1) === '$') {
			if (!isset($nag)) {
				$nag = mf_chess_pgn_basics();
			}
			if (substr($game['html'], -1) === ' ')
				$game['html'] = substr($game['html'], 0, -1);
			$game['html'] .= $nag['NAG'][substr($move, 1)]['CSM'].' ';
		} else {
			if (!$variant) {
				$game['html'] .= '<a href="javascript:SetMove('.$game['move'].',0)"><b>';
				$game['pgn'] .= $move.' ';
			}
			$game['html'] .= pgn_translate_pieces($move, 'de');
			if (!$variant)	{
				$game['html'] .= '</b></a>';
			}
			$game['html'] .= ' ';
			if (isset($extra_comment[$game['move']])) {
				$game['html'] .= ' '.$extra_comment[$game['move']].' ';
				$tokens_since_move = 1;
			}
			if (!$variant) $game['move']++;
		}
	}
	$game['move']--;
	return $game;
}

/**
 * Liest PGN-Datei(en) aus Tabelle aus, holt sich den Inhalt und gibt alle
 * PGNs zurück
 *
 * @param int $tournament_id
 * @return string
 * @global array $zz_conf
 */
function pgn_file_from_tournament($tournament_id) {
	global $zz_conf;

	$sql = 'SELECT partien_pfad
		FROM turniere_partien
		WHERE tournament_id = %d';
	$sql = sprintf($sql, $tournament_id);
	$pgn_pfade = wrap_db_fetch($sql, '_dummy_', 'single value');
	$pgn = '';
	foreach ($pgn_pfade as $pfad) {
		if (in_array(substr($pfad, 0, 1), ['/', '.'])) {
			// lokaler Pfad
			$pfad = $zz_conf['root'].$pfad;
			if (!file_exists($pfad)) continue;
		}
		if ($dateininhalt = file_get_contents($pfad)) {
			$pgn .= $dateininhalt;
		}
	}
	return $pgn;
}

/**
 * check if PGN only is a comment
 *
 * @param string $moves
 * @param string $result
 * @return string comment if it's a comment, otherwise empty string
 */
function pgn_only_comment($moves, $result) {
	if (!substr($moves, -strlen($result)) === $result) return '';
	$moves = substr($moves, 0, -strlen($result));
	$moves = trim($moves);
	if (substr($moves, 0, 1) !== '{') return '';
	if (substr($moves, -1) !== '}') return '';
	return substr($moves, 1, -1);
}

function pgn_wordwrap($string) {
	return wordwrap($string, 79);
}

/**
 * sometimes, database software messes up header lines and does not add newlines
 *
 * @param array $pgn
 * @return array
 */
function pgn_fix_headers($pgn) {
	$possible_missing_line_break = false;
	$extra_lines = 0;
	foreach ($pgn as $index => $line) {
		$line = trim($line);
		if ($possible_missing_line_break) {
			$possible_missing_line_break = false;
			if (!str_starts_with($line, '[Site')) continue;
			$last_index = $index -1 + $extra_lines;
			$extra = [];
			// there might be more than one! (sigh)
			while (strstr($pgn[$last_index], '[Event "')) {
				$extra[] = trim(substr($pgn[$last_index], strrpos($pgn[$last_index], '[')))."\n";
				$pgn[$last_index] = substr($pgn[$last_index], 0, strrpos($pgn[$last_index], '['));
			}
			$pgn[$last_index] = trim($pgn[$last_index])."\n";
			$extra = array_unique($extra);
			array_splice($pgn, $index + $extra_lines, 0, $extra);
			$extra_lines += count($extra);
		}
		if (str_starts_with($line, '[') AND str_ends_with($line, ']')) continue;
		if (strstr($line, '[Event "')) $possible_missing_line_break = true; 
	}
	return $pgn;
}
