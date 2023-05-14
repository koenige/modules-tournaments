<?php 

/**
 * tournaments module
 * form script: upload an SWT file
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2014-2015, 2017, 2019-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


if (!wrap_setting('tournaments_upload_swt'))
	wrap_quit(403, 'SWT-Upload ist auf dieser Plattform nicht erlaubt.');

$zz = zzform_include('turniere');

$zz['title'] = 'SWT-Upload';
$zz['where']['event_id'] = $brick['data']['event_id'];
$zz['access'] = 'add_then_edit';

$zz_conf['referer'] = '../';

unset($zz['fields'][25]);
unset($zz['fields'][26]);

$zz['fields'][22]['if'][1]['separator'] = false;
$zz['fields'][22]['if'][2]['separator'] = false;
$zz['fields'][22]['explanation'] = 'SWT wird sofort nach Upload importiert.';

// Nur SWT-Upload-Feld anzeigen
$fields = [22];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $fields)) $zz['fields'][$no]['hide_in_form'] = true;
}

$zz['page']['data'] = $brick['data'];
$zz['hooks']['after_upload'] = 'mf_tournaments_swtimport';

$zz_conf['text']['de']['Edit a record'] = '';
$zz_conf['text']['de']['Record was not updated (no changes were made)'] = 'Datei wurde hochgeladen';
$zz_conf['text']['de']['edit'] = 'Erneut hochladen';
$zz_conf['text']['de']['Update record'] = 'Datei hochladen';

$zz_conf['footer_text'] = '<p><strong>Achtung:</strong> Nach Hinzufügen, Löschen oder dem Ändern von Spielern ist es bei Turnieren, bei denen nicht
jede Spielerin und jeder Spieler entweder eine ZPS-Nummer, eine FIDE-ID oder eine DSB-Personenkennziffer hat, sinnvoll, die
Personen-IDs aus der Datenbank als Identifikation in die SWT-Datei zurückzuschreiben (Feld Info4). Das geht automatisch über:</p>

<p><a href="../swtwriter/">Personen-IDs in SwissChess-Datei schreiben und herunterladen</a> (Verfügbar erst kurze Zeit nach Upload)</p>';
