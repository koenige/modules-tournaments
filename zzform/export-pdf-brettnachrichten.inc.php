<?php

/**
 * tournaments module
 * export player messages as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022-2023, 2025-2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * export player messages as PDF
 *
 * @param array $ops
 */
function mf_tournaments_export_pdf_brettnachrichten($ops) {
	$data = mf_tournaments_playermessages_export_data($ops);
	mf_tournaments_playermessages_export_pdf($data);
}

/**
 * Collect verified player messages for PDF export.
 *
 * @param array $ops zzform export ops
 * @return array<int, array> rows keyed by playermessage_id
 */
function mf_tournaments_playermessages_export_data($ops) {
	$ids = [];
	foreach ($ops['output']['rows'] as $row) {
		$ids[] = $row['id_value'];
	}
	if (!$ids) {
		wrap_quit(404, wrap_text('No player messages were selected for this export.'));
	}

	$sql = 'SELECT playermessage_id, message, email, sender, playermessages.created
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS contact
			, CONCAT(events.event, " ", IFNULL(events.event_year, YEAR(events.date_begin))) AS event
			, federations.contact_short AS federation
			, IFNULL(white.runde_no, black.runde_no) AS round_no
			, IFNULL(white.brett_no, black.brett_no) AS board_no
			, IF (ISNULL(white.brett_no), "black", "white") AS colour
		FROM playermessages
		LEFT JOIN participations
			ON playermessages.participation_id = participations.participation_id
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN contacts federations
			ON participations.federation_contact_id = federations.contact_id
		LEFT JOIN partien white
			ON white.event_id = events.event_id
			AND white.runde_no = (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id)
			AND white.weiss_person_id = persons.person_id
		LEFT JOIN partien black
			ON black.event_id = events.event_id
			AND black.runde_no = (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id)
			AND black.schwarz_person_id = persons.person_id
		WHERE playermessage_id IN (%s)
		AND playermessages.verified = "yes"
		ORDER BY federations.contact_short, series.sequence, IFNULL(white.brett_no, black.brett_no), playermessages.created';
	$sql = sprintf($sql, implode(',', $ids));
	$data = wrap_db_fetch($sql, 'playermessage_id');
	if (!$data) {
		wrap_quit(404, wrap_text('No verified player messages are available for this export.'));
	}
	return $data;
}

/**
 * Render player message export as two-column landscape PDF.
 *
 * @param array<int, array> $data rows keyed by playermessage_id
 */
function mf_tournaments_playermessages_export_pdf($data) {
	$event = mf_tournaments_playermessages_export_event();
	if (!$event) {
		wrap_quit(404, wrap_text('Event context is missing for this export.'));
	}

	$pdf = new colPDF('L', 'mm', 'A4');
	$pdf->setCompression(true);
	$pdf->AddFont('FiraSans-Regular', '', 'FiraSans-Regular.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$pdf->AddFont('NotoEmoji-Regular', '', 'NotoEmoji-Regular.ttf', true);
	$pdf->AddFont('NotoSansJP-Regular', '', 'NotoSansJP-Regular.ttf', true);
	$pdf->AddFont('NotoSansSC-Regular', '', 'NotoSansSC-Regular.ttf', true);
	$pdf->AddFont('NotoSansKR-Regular', '', 'NotoSansKR-Regular.ttf', true);
	$pdf->AddFont('DejaVuSans', '', 'DejaVuSans.ttf', true);
	$pdf->AddFont('NotoSansArabic-Regular', '', 'NotoSansArabic-Regular.ttf', true);
	$pdf->AddFont('NotoSansThai-Regular', '', 'NotoSansThai-Regular.ttf', true);
	$pdf->FontEmoji = 'NotoEmoji-Regular';
	$pdf->FontCjk = 'NotoSansJP-Regular';
	$pdf->FontCjkSc = 'NotoSansSC-Regular';
	$pdf->FontCjkKr = 'NotoSansKR-Regular';
	$pdf->FontBraille = 'DejaVuSans';
	$pdf->FontArabic = 'NotoSansArabic-Regular';
	$pdf->FontThai = 'NotoSansThai-Regular';
	$pdf->SetFont('FiraSans-Regular', '', 11);
	$pdf->SetMargins(15, 15);

	$first = reset($data);
	$round_no = $first['round_no'];
	$half = 118.5;
	$event_path = str_replace('/', '-', $event['identifier']);
	$event_label = $event['series_short'] ?: $event['event'];

	if (wrap_setting('tournaments_playermessages_pdf_column_layout') == 1) {
		$data = mf_tournaments_playermessages_export_pdf_split_data($data);
	}
	mf_tournaments_playermessages_export_pdf_columns($pdf, $data, $half);

	$folder = wrap_setting('tmp_dir').'/tournaments/playermessages/'.$event_path;
	wrap_mkdir($folder);
	if (file_exists($folder.'/round-'.$round_no.'.pdf')) {
		unlink($folder.'/round-'.$round_no.'.pdf');
	}
	$file['name'] = $folder.'/round-'.$round_no.'.pdf';
	$file['send_as'] = sprintf(wrap_text('%s %s Player Messages Round %d.pdf'), $event['year'], $event_label, $round_no);
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
	wrap_send_file($file);
}

/**
 * Render messages in alternating columns (1|2, or split halves after group reordering).
 *
 * @param colPDF $pdf
 * @param array<int, array> $data
 * @param float $half column width
 */
function mf_tournaments_playermessages_export_pdf_columns($pdf, $data, $half) {
	$pdf->SetCol(1);

	$last_contact = false;
	foreach ($data as $line) {
		if ($line['contact'] !== $last_contact) {
			$pdf->event = $line['event'];
			$pdf->board_no = $line['board_no'];
			$pdf->colour = $line['colour'];
			$pdf->contact = $line['contact'];
			$pdf->federation = $line['federation'];

			if ($pdf->col === 1) {
				$pdf->SetCol(0);
				$pdf->addPage();
				$pdf->Line(148.5, 0, 148.5, 210);
			} else {
				$pdf->SetCol(1);
				$pdf->setHead();
			}
		} else {
			$pdf->MultiCell($half, 7.5, '', 0, 'L');
		}
		$last_contact = $line['contact'];
		$pdf->SetFont('FiraSans-SemiBold', '', 11);
		$pdf->MultiCellUnicode($half, 6, sprintf(wrap_text('From: %s <%s>'), $line['sender'], $line['email']), 0, 'L');
		$pdf->MultiCell($half, 6, sprintf(wrap_text('Date: %s %s'), wrap_date_plain($line['created']), wrap_time($line['created'])), 0, 'L');
		$pdf->SetFont('FiraSans-Regular', '', 11);
		$pdf->MultiCellUnicode($half, 6, trim($line['message']), 0, 'L');
	}
}

function mf_tournaments_playermessages_export_event() {
	$event = wrap_static('zzform', 'event');
	if (!$event) {
		$event = wrap_brick('data');
	}
	if (empty($event['event_id'])) {
		return false;
	}
	if (empty($event['series_short'])) {
		$event = array_merge($event, my_event($event['event_id']));
	}
	return $event;
}

/**
 * Reorder for split columns: first-half groups left, second-half groups right (1|5, 2|6, …).
 *
 * Consecutive messages to the same recipient stay together so they share one column
 * on a page, then the original alternating renderer handles page breaks.
 *
 * @param array<int, array> $data
 * @return array<int, array>
 */
function mf_tournaments_playermessages_export_pdf_split_data($data) {
	$data = array_values($data);
	$split = (int) ceil(count($data) / 2);
	$left = array_slice($data, 0, $split);
	$right = array_slice($data, $split);
	$left_groups = mf_tournaments_playermessages_export_pdf_contact_groups($left);
	$right_groups = mf_tournaments_playermessages_export_pdf_contact_groups($right);
	return mf_tournaments_playermessages_export_pdf_interleave_groups($left_groups, $right_groups);
}

/**
 * Group consecutive messages to the same recipient.
 *
 * @param array<int, array> $messages
 * @return array<int, array<int, array>>
 */
function mf_tournaments_playermessages_export_pdf_contact_groups($messages) {
	$groups = [];
	$group = [];
	$last_contact = null;
	foreach ($messages as $line) {
		if ($last_contact !== null && $line['contact'] !== $last_contact) {
			$groups[] = $group;
			$group = [];
		}
		$group[] = $line;
		$last_contact = $line['contact'];
	}
	if ($group) {
		$groups[] = $group;
	}
	return $groups;
}

/**
 * Interleave left and right recipient groups for split column pages.
 *
 * @param array<int, array<int, array>> $left_groups
 * @param array<int, array<int, array>> $right_groups
 * @return array<int, array>
 */
function mf_tournaments_playermessages_export_pdf_interleave_groups($left_groups, $right_groups) {
	$reordered = [];
	$rows = max(count($left_groups), count($right_groups));
	for ($index = 0; $index < $rows; $index++) {
		if (isset($left_groups[$index])) {
			foreach ($left_groups[$index] as $line) {
				$reordered[] = $line;
			}
		}
		if (isset($right_groups[$index])) {
			foreach ($right_groups[$index] as $line) {
				$reordered[] = $line;
			}
		}
	}
	return $reordered;
}


wrap_lib('tfpdf');

class colPDF extends zzTFPDFUnicode
{
var $col = 0;

function SetCol($col)
{
    // Move position to a column
    $this->col = $col;
    $x = 15 + $col * 148.5;
    $this->SetLeftMargin($x);
    $this->SetX($x);
}

function AcceptPageBreak()
{
	// New page, stay in the current column; Header()/SetHead() redraws this contact.
	return true;
}

function SetHead() {
	$width = 18.7;
	$height = 30;

	$this->setY(15);
	$this->image(wrap_setting('media_folder').'/chessy/70-Post.600.png', 148.5 * ($this->col + 1) + 15 - 30 - $width, 10, $width, $height);
	$board = sprintf(wrap_text('%s Board %s (%s)'), $this->event, $this->board_no, wrap_text($this->colour));
	$this->setY(22.5);
	$this->SetFont('FiraSans-SemiBold', '', 11);
	$this->MultiCellUnicode(118.5 - 22.5, 7.5, $this->contact.' ('.$this->federation.')', 1, 'L');
	$this->MultiCellUnicode(118.5 - 22.5, 7.5, $board, 1, 'L');
	$this->MultiCell(118.5, 7.5, '', 0, 'L');
}

function Header()
{
	$this->setHead();
}

}
