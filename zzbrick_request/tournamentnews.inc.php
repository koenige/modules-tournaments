<?php

/**
 * tournaments module
 * Output tournament news
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/tournaments
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2021, 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_tournaments_tournamentnews($params, $settings) {
	$sql = 'SELECT articles.article_id
		FROM articles
		LEFT JOIN articles_events USING (article_id)
		LEFT JOIN events USING (event_id)
		LEFT JOIN articles_categories
			ON articles_categories.article_id = articles.article_id
			AND articles_categories.type_category_id = %d
		WHERE events.identifier = "%d/%s"
		AND articles.published = "yes"
		AND articles_categories.category_id = %d
		ORDER BY date DESC';
	$sql = sprintf($sql
		, wrap_category_id('publications')
		, $params[0]
		, wrap_db_escape($params[1])
		, wrap_category_id('publications/tournament-news')
	);
	$ids = wrap_db_fetch($sql, 'article_id');
	if (!$ids) {
		$page['text'] = ' ';
		return $page;
	}

	wrap_include('zzbrick_request_get/articledata', 'news');
	$data = mod_news_get_articledata($ids, $settings);
	
	$page['text'] = wrap_template('tournamentnews', $data);
	return $page;
}
