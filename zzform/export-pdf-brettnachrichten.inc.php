<?php

/**
 * tournaments module
 * export player messages as PDF
 *
 * Part of »Zugwzang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * export player messages as PDF
 *
 * @param array $ops
 */
function mf_tournaments_export_pdf_brettnachrichten($ops) {
	global $zz_setting;

	$sql = 'SELECT nachricht_id, nachricht, email, absender, eintragszeit
			, CONCAT(t_vorname, " ", IFNULL(CONCAT(t_namenszusatz, " "), ""), t_nachname) AS contact
			, CONCAT(events.event, " ", IFNULL(events.event_year, YEAR(events.date_begin))) AS event
			, federations.contact_short AS federation
			, IFNULL(white.runde_no, black.runde_no) AS round_no
			, IFNULL(white.brett_no, black.brett_no) AS board_no
			, IF (ISNULL(white.brett_no), "schwarz", "weiß") AS colour
		FROM spieler_nachrichten
		LEFT JOIN participations
			ON spieler_nachrichten.teilnehmer_id = participations.participation_id
		LEFT JOIN events USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN contacts federations
			ON participations.federation_contact_id = federations.contact_id
		LEFT JOIN partien white
			ON white.event_id = events.event_id
			AND white.runde_no = (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id)
			AND white.weiss_person_id = participations.person_id
		LEFT JOIN partien black
			ON black.event_id = events.event_id
			AND black.runde_no = (SELECT MAX(runde_no) FROM partien WHERE partien.event_id = events.event_id)
			AND black.schwarz_person_id = participations.person_id
		WHERE %s
		AND verified = "yes"
		ORDER BY federations.contact_short, series.sequence, IFNULL(white.brett_no, black.brett_no), eintragszeit';
	$sql = sprintf($sql
		, !empty($_GET['filter']['processed'])
			? sprintf('processed = "%s"', wrap_db_escape($_GET['filter']['processed']))
			: 'ISNULL(processed)'
	);
	$data = wrap_db_fetch($sql, 'nachricht_id');
	
	require_once $zz_setting['modules_dir'].'/default/libraries/tfpdf.inc.php';

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
	global $zz_setting;
	$width = 18.7;
	$height = 30;

	$this->setY(15);
	$this->image($zz_setting['media_folder'].'/chessy/70-Post.600.png', 148.5 * ($this->col + 1) + 15 - 30 - $width, 10, $width, $height);
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


	$pdf = new colPDF('L', 'mm', 'A4');		// panorama = p, DIN A4, 595 x 842
	$pdf->setCompression(true);
	$pdf->AddFont('OpenSansEmoji', '', 'OpenSansEmoji.ttf', true);
	$pdf->AddFont('FiraSans-SemiBold', '', 'FiraSans-SemiBold.ttf', true);
	$pdf->SetMargins(15, 15);
	$pdf->SetCol(1);
	
	$last_contact = false;
	$first = reset($data);
	$round_no = $first['round_no'];
	$col = 'left';
	$half = 118.5;
	$left = 15;

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
		$pdf->MultiCell($half, 6, 'Von: '.$line['absender'].' <'.$line['email'].'>', 0, 'L');
		$pdf->MultiCell($half, 6, 'Datum: '.wrap_date($line['eintragszeit']).' '.wrap_time($line['eintragszeit']), 0, 'L');
		$pdf->setFont('OpenSansEmoji', '', 11);
		$pdf->MultiCell($half, 6, trim($line['nachricht']), 0, 'L');
	}
	$folder = $zz_setting['tmp_dir'].'/brettnachrichten/dem';
	wrap_mkdir($folder);
	if (file_exists($folder.'/runde-'.$round_no.'.pdf')) {
		unlink($folder.'/runde-'.$round_no.'.pdf');
	}
	$file['name'] = $folder.'/runde-'.$round_no.'.pdf';
	$file['send_as'] = 'DEM Brettnachrichten Runde '.$round_no.'.pdf';
	$file['etag_generate_md5'] = true;

	$pdf->output('F', $file['name'], true);
	wrap_file_send($file);
}	
