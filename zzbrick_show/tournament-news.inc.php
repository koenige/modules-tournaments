<?php

/**
 * tournaments module
 * Output tournament news
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021, 2023-2025 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_show_tournament_news($params, $settings) {
	$sql = 'SELECT articles.article_id
		FROM articles
		LEFT JOIN articles_events USING (article_id)
		LEFT JOIN events USING (event_id)
		WHERE events.identifier = "%d/%s"
		AND articles.published = "yes"
		AND publication_id = /*_ID publications tournament-news _*/
		ORDER BY date DESC';
	$sql = sprintf($sql
		, $params[0]
		, wrap_db_escape($params[1])
	);
	$data = wrap_db_fetch($sql, 'article_id');
	if (!$data) return false;

	wrap_include('data', 'zzwrap');
	$data = wrap_data('articles', $data, $settings);
	
	$page['text'] = wrap_template('tournament-news', $data);
	return $page;
}
