<?php 

/**
 * tournaments module
 * form script: player messages
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2022 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz = zzform_include_table('spielernachrichten');

$event_ids = [];
if (!empty($brick['data']['event_id'])) {
	$event_ids = mf_tournaments_series_events($brick['data']['event_id']);
	$zz['sql'] = wrap_edit_sql($zz['sql'], 'WHERE', sprintf('event_id IN (%s)', implode(',', $event_ids)));
	$zz['title'] .= ': <br>'.$brick['data']['series_short'].' '.$brick['data']['year'];
}

$data = [];
$data['current_time'] = date("Y-m-d H:i:s", time());

if (!empty($_POST['sent_date'])) {
	require_once $zz_conf['dir_inc'].'/validate.inc.php';

	$sql = 'UPDATE spieler_nachrichten
		SET processed = "%s"
		WHERE ISNULL(processed)
		AND eintragszeit < "%s"';
	$sql = sprintf($sql
		, zz_check_datetime($_POST['sent_date'])
		, zz_check_datetime($_POST['sent_date'])
	);
	$result = wrap_db_query($sql);
	$data['messages_sent'] = $result['rows'];
}

$zz['explanation'] = wrap_template('playermessage-form', $data);

$zz['filter'][1]['sql'] = sprintf('SELECT DISTINCT(processed), processed
	FROM spieler_nachrichten
	LEFT JOIN participations
		ON spieler_nachrichten.teilnehmer_id = participations.participation_id
	%s
	ORDER BY processed DESC'
	, $event_ids ? sprintf(' WHERE event_id IN (%s)', implode(',', $event_ids)) : '');
$zz['filter'][1]['title'] = 'Verarbeitet';
$zz['filter'][1]['identifier'] = 'processed';
$zz['filter'][1]['type'] = 'list';
$zz['filter'][1]['field_name'] = 'processed';
$zz['filter'][1]['where'] = '/*_PREFIX_*/spieler_nachrichten.processed';
$zz['filter'][1]['default_selection'] = 'NULL';

$zz['filter'][2]['sql'] = sprintf('SELECT contacts.contact_id, contact_short
	FROM spieler_nachrichten
	LEFT JOIN participations
		ON participations.participation_id = spieler_nachrichten.teilnehmer_id
	LEFT JOIN contacts
		ON participations.federation_contact_id = contacts.contact_id
	%s
	ORDER BY contact_short'
	, $event_ids ? sprintf(' WHERE event_id IN (%s)', implode(',', $event_ids)) : '');
$zz['filter'][2]['title'] = 'Verband';
$zz['filter'][2]['identifier'] = 'federation';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['where'] = 'federations.contact_id';

$zz_conf['export'][] = 'PDF Brettnachrichten';
