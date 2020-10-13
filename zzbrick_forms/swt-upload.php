<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014-2015, 2017, 2019-2020 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Turnierdetails, nur SWT-Upload


$event = my_event($brick['vars'][0], $brick['vars'][1]);
if (!$event) wrap_quit(404);

$zz = zzform_include_table('turniere');

$zz['title'] = 'SWT-Upload';
$zz['where']['event_id'] = $event['event_id'];
$zz['access'] = 'add_then_edit';

$zz_conf['referer'] = '../';

my_event_breadcrumbs($event);
$zz_conf['breadcrumbs'][] = ['linktext' => $zz['title']];

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

// @todo extra prozess
$zz['hooks']['after_upload'] = 'my_swtimport';

$zz_conf['text']['de']['Edit a record'] = '';
$zz_conf['text']['de']['Record was not updated (no changes were made)'] = 'Datei wurde hochgeladen';
$zz_conf['text']['de']['edit'] = 'Erneut hochladen';
$zz_conf['text']['de']['Update record'] = 'Datei hochladen';

$zz_conf['footer_text'] = '<p><strong>Achtung:</strong> Nach Hinzufügen, Löschen oder dem Ändern von Spielern ist es bei Turnieren, bei denen nicht
jede Spielerin und jeder Spieler entweder eine ZPS-Nummer, eine FIDE-ID oder eine DSB-Personenkennziffer hat, sinnvoll, die
Personen-IDs aus der Datenbank als Identifikation in die SWT-Datei zurückzuschreiben (Feld Info4). Das geht automatisch über:</p>

<p><a href="/intern/swtwriter/'.$event['kennung'].'/">Personen-IDs in SwissChess-Datei schreiben und herunterladen</a> (Verfügbar erst kurze Zeit nach Upload)</p>';
