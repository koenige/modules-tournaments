<?php 

/**
 * tournaments module
 * definition helper functions for forms with zzform
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * add upload fields per categories
 *
 * @param array $zz existing table definition so far
 * @param int $no field number where to start
 * @param array $data
 */
function mf_tournaments_upload_fields(&$zz, $no, $data) {
	$sql = 'SELECT *
		FROM categories
		WHERE main_category_id = /*_ID categories tournament-uploads _*/
		ORDER BY sequence';
	$categories = wrap_db_fetch($sql, 'category_id');
	
	foreach ($categories as $category) {
		parse_str($category['parameters'], $parameters);
		if (!empty($parameters['tournaments_upload_if']))
			if (empty($data[$parameters['tournaments_upload_if']])) continue;

		$zz['fields'][$no]['title'] = $category['category'];
		$zz['fields'][$no]['field_name'] = $parameters['tournaments_field_name'];
		if (!empty($parameters['tournaments_upload_optional']))
			$zz['fields'][$no]['dont_show_missing'] = true;
		$path_suffix = $parameters['tournaments_path_suffix'] ?? '';
		$zz['fields'][$no]['type'] = 'upload_image';
		$zz['fields'][$no]['path'] = [
			'root' => wrap_setting('tournaments_teams_dir').'/',
			'field1' => 'identifier',
			'string1' => $path_suffix.'.',
			'string2' => 'pdf'
		];
		$zz['fields'][$no]['input_filetypes'] = ['pdf'];
		$zz['fields'][$no]['path_web'] = [
			'area' => 'tournaments_team_file',
			'fields' => ['identifier']
		];
		if ($path_suffix)
			$zz['fields'][$no]['path_web']['strings_append'] = [$path_suffix];
		$zz['fields'][$no]['optional_image'] = true;
		$zz['fields'][$no]['explanation'] = $category['description'];
		if (!empty($parameters['tournaments_help_url']) AND !empty($parameters['tournaments_help_label']))
			$zz['fields'][$no]['explanation'] .= sprintf('<br><a href="%s">%s</a>', $parameters['tournaments_help_url'], $parameters['tournaments_help_label']);
		$zz['fields'][$no]['image'][0]['title'] = 'pdf';
		$zz['fields'][$no]['image'][0]['field_name'] = 'pdf';
		$zz['fields'][$no]['list_append_next'] = true;
		$zz['fields'][$no]['hide_in_list'] = true;
		$no++;
	}
}
