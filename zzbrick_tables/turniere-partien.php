<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014, 2017, 2020-2021 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Livepartien eines Turnieres


$zz['title'] = 'Livepartien';
$zz['table'] = 'turniere_partien';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'tp_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][2]['field_name'] = 'tournament_id';
$zz['fields'][2]['type'] = 'select';
$zz['fields'][2]['sql'] = 'SELECT tournament_id
		, CONCAT(event, " ", YEAR(date_begin)) AS turnier
	FROM turniere
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz['fields'][2]['display_field'] = 'turnier';
$zz['fields'][2]['search'] = 'CONCAT(event, " ", YEAR(date_begin))';

$zz['fields'][3]['title'] = 'Link';
$zz['fields'][3]['field_name'] = 'partien_pfad';
$zz['fields'][3]['explanation'] = 'Link zu einer Adresse, unter der Livepartien verfügbar sind oder Pfad auf dem Webserver zu den Livepartien';


$zz['sql'] = 'SELECT turniere_partien.*
		, CONCAT(event, " ", YEAR(date_begin)) AS turnier
	FROM turniere_partien
	LEFT JOIN turniere USING (tournament_id)
	LEFT JOIN events USING (event_id)
';
$zz['sqlorder'] = ' ORDER BY date_begin, event ASC';

$zz['access'] = 'all';
