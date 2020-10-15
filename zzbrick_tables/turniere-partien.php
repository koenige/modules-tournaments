<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014, 2017, 2020 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Livepartien eines Turnieres


$zz_sub['title'] = 'Livepartien';
$zz_sub['table'] = 'turniere_partien';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tp_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'turnier_id';
$zz_sub['fields'][2]['type'] = 'select';
$zz_sub['fields'][2]['sql'] = 'SELECT turnier_id
		, CONCAT(termin, " ", YEAR(date_begin)) AS turnier
	FROM turniere
	LEFT JOIN events USING (event_id)
	ORDER BY date_begin, identifier DESC';
$zz_sub['fields'][2]['display_field'] = 'turnier';
$zz_sub['fields'][2]['search'] = 'CONCAT(termin, " ", YEAR(date_begin))';

$zz_sub['fields'][3]['title'] = 'Link';
$zz_sub['fields'][3]['field_name'] = 'partien_pfad';
$zz_sub['fields'][3]['explanation'] = 'Link zu einer Adresse, unter der Livepartien verfügbar sind oder Pfad auf dem Webserver zu den Livepartien';


$zz_sub['sql'] = 'SELECT turniere_partien.*
		, CONCAT(termin, " ", YEAR(date_begin)) AS turnier
	FROM turniere_partien
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN events USING (event_id)
';
$zz_sub['sqlorder'] = ' ORDER BY date_begin, termin ASC';

$zz_sub['access'] = 'all';
