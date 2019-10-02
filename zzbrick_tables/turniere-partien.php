<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2014, 2017 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Livepartien eines Turnieres


$zz_sub['title'] = 'Livepartien';
$zz_sub['table'] = 'turniere_partien';

$zz_sub['fields'][1]['title'] = 'ID';
$zz_sub['fields'][1]['field_name'] = 'tp_id';
$zz_sub['fields'][1]['type'] = 'id';

$zz_sub['fields'][2]['field_name'] = 'turnier_id';
$zz_sub['fields'][2]['type'] = 'select';
$zz_sub['fields'][2]['sql'] = 'SELECT turnier_id
		, CONCAT(termin, " ", YEAR(beginn)) AS turnier
	FROM turniere
	LEFT JOIN termine USING (termin_id)
	ORDER BY beginn, kennung DESC';
$zz_sub['fields'][2]['display_field'] = 'turnier';
$zz_sub['fields'][2]['search'] = 'CONCAT(termin, " ", YEAR(beginn))';

$zz_sub['fields'][3]['title'] = 'Link';
$zz_sub['fields'][3]['field_name'] = 'partien_pfad';
$zz_sub['fields'][3]['explanation'] = 'Link zu einer Adresse, unter der Livepartien verfügbar sind oder Pfad auf dem Webserver zu den Livepartien';


$zz_sub['sql'] = 'SELECT turniere_partien.*
		, CONCAT(termin, " ", YEAR(beginn)) AS turnier
	FROM turniere_partien
	LEFT JOIN turniere USING (turnier_id)
	LEFT JOIN termine USING (termin_id)
';
$zz_sub['sqlorder'] = ' ORDER BY beginn, termin ASC';

$zz_sub['access'] = 'all';
