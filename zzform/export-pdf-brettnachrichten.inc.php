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
			, IF (ISNULL(white.brett_no), "schwarz", "weiß") AS colour
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
	$pdf->AddFont('OpenSansEmoji', '', 'OpenSansEmoji.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$pdf->SetMargins(15, 15);
	$pdf->SetCol(1);

	$last_contact = false;
	$first = reset($data);
	$round_no = $first['round_no'];
	$half = 118.5;
	$event_path = str_replace('/', '-', $event['identifier']);
	$event_label = $event['series_short'] ?: $event['event'];

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
			$pdf->MultiCell(118.5, 7.5, '', 0, 'L');
		}
		$last_contact = $line['contact'];
		$pdf->setFont('FiraSans-SemiBold', '', 11);
		$pdf->MultiCell($half, 6, 'Von: '.$line['sender'].' <'.$line['email'].'>', 0, 'L');
		$pdf->MultiCell($half, 6, 'Datum: '.wrap_date_plain($line['created']).' '.wrap_time($line['created']), 0, 'L');
		$pdf->setFont('OpenSansEmoji', '', 11);
		$pdf->MultiCell($half, 6, trim($line['message']), 0, 'L');
	}
	
	$folder = wrap_setting('tmp_dir').'/tournaments/playermessages/'.$event_path;
	wrap_mkdir($folder);
	if (file_exists($folder.'/round-'.$round_no.'.pdf')) {
		unlink($folder.'/round-'.$round_no.'.pdf');
	}
	$file['name'] = $folder.'/round-'.$round_no.'.pdf';
	$file['send_as'] = sprintf('%s %s Brett-Nachrichten Runde %d.pdf', $event['year'], $event_label, $round_no);
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
	wrap_send_file($file);
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


wrap_lib('tfpdf');

class colPDF extends TFPDF
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
    if($this->col<1)
    {
        // Go to next column
        $this->SetCol($this->col+1);
        $this->SetY(15);
		$this->SetHead();
		$this->MultiCell(118.5, 7.5, '', 0, 'L');
        return false;
    }
    else
    {
        // Go back to first column and issue page break
        $this->SetCol(0);
//		$this->SetHead();
//		$this->MultiCell(118.5, 7.5, '', 0, 'L');
        return true;
    }
}

function SetHead() {
	$width = 18.7;
	$height = 30;

	$this->setY(15);
	$this->image(wrap_setting('media_folder').'/chessy/70-Post.600.png', 148.5 * ($this->col + 1) + 15 - 30 - $width, 10, $width, $height);
	$board = $this->event.' Brett '.$this->board_no.' ('.$this->colour.')';
	$this->setY(22.5);
	$this->setFont('FiraSans-SemiBold', '', 11);
	$this->MultiCell(118.5 - 22.5, 7.5, $this->contact.' ('.$this->federation.')', 1, 'L');
	$this->MultiCell(118.5 - 22.5, 7.5, $board, 1, 'L');
	$this->setFont('OpenSansEmoji', '', 11);
	$this->MultiCell(118.5, 7.5, '', 0, 'L');
}

function Header()
{
	$this->setHead();
}

}
