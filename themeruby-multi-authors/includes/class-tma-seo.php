<?php

/**
 * SEO Plugin Integrations
 *
 * Integrates with SEO plugins for proper multi-author schema markup.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

/**
 * TMAuthors_SEO class.
 *
 * @since 1.0.0
 */
class TMAuthors_SEO
{
	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init()
	{
		// Check if SEO integration is enabled.
		if (! get_option('tmauthors_seo_integration', 1)) {
			return;
		}

		if (defined('WPSEO_VERSION')) {
			add_filter('wpseo_schtmauthors_person', [__CLASS__, 'yoast_schtmauthors_person'], 10, 2);
			add_filter('wpseo_schtmauthors_article', [__CLASS__, 'yoast_schtmauthors_article'], 10, 2);
		}

		if (defined('RANK_MATH_VERSION')) {
			add_filter('rank_math/json_ld', [__CLASS__, 'rankmath_schema'], 10, 2);
		}

		if (defined('AIOSEO_VERSION')) {
			add_filter('aioseo_schtmauthors_graph', [__CLASS__, 'aioseo_schema'], 10, 1);
		}

		if (defined('SEOPRESS_VERSION')) {
			add_filter('seopress_schemas_manual_author', [__CLASS__, 'seopress_author_schema'], 10, 1);
		}

		if (defined('THE_SEO_FRAMEWORK_VERSION')) {
			add_filter('the_seo_framework_ld_json_breadcrumb', [__CLASS__, 'tsf_schema'], 10, 1);
		}
	}

	/**
	 * Check if any SEO plugin is active.
	 *
	 * @return bool True if SEO plugin is active.
	 * @since 1.0.0
	 */
	private static function has_seo_plugin()
	{
		return defined('WPSEO_VERSION') ||
			   defined('RANK_MATH_VERSION') ||
			   defined('AIOSEO_VERSION') ||
			   defined('SEOPRESS_VERSION') ||
			   defined('THE_SEO_FRAMEWORK_VERSION');
	}

	/**
	 * Modify Person schema.
	 *
	 * @param array $data Schema data.
	 * @param mixed $context Context.
	 *
	 * @return array Modified schema data.
	 * @since 1.0.0
	 */
	public static function yoast_schtmauthors_person($data, $context)
	{
		if (! is_singular('post')) {
			return $data;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1) {
			return $data;
		}

		// Return array of Person schema for multiple authors.
		$author_schemas = [];

		foreach ($authors as $author) {
			$author_schemas[] = [
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => TMAuthors_Display::get_author_posts_url($author->ID),
				'@id'   => TMAuthors_Display::get_author_posts_url($author->ID) . '#person',
			];
		}

		return $author_schemas;
	}

	/**
	 * Modify Article schema.
	 *
	 * @param array $data Schema data.
	 * @param mixed $context Context.
	 *
	 * @return array Modified schema data.
	 * @since 1.0.0
	 */
	public static function yoast_schtmauthors_article($data, $context)
	{
		if (! is_singular('post')) {
			return $data;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1) {
			return $data;
		}

		// Build author references.
		$author_refs = [];

		foreach ($authors as $author) {
			$author_refs[] = [
				'@id' => TMAuthors_Display::get_author_posts_url($author->ID) . '#person',
			];
		}

		$data['author'] = $author_refs;

		return $data;
	}

	/**
	 * Modify schema.
	 *
	 * @param array $data Schema data.
	 * @param mixed $jsonld JSON-LD object.
	 *
	 * @return array Modified schema data.
	 * @since 1.0.0
	 */
	public static function rankmath_schema($data, $jsonld)
	{
		if (! is_singular('post')) {
			return $data;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1) {
			return $data;
		}

		// Modify Article schema.
		if (isset($data['Article'])) {
			$author_schemas = [];

			foreach ($authors as $author) {
				$author_schemas[] = [
					'@type' => 'Person',
					'name'  => $author->display_name,
					'url'   => TMAuthors_Display::get_author_posts_url($author->ID),
				];
			}

			$data['Article']['author'] = $author_schemas;
		}

		return $data;
	}

	/**
	 * Modify schema graph.
	 *
	 * @param array $graphs Schema graphs.
	 *
	 * @return array Modified schema graphs.
	 * @since 1.0.0
	 */
	public static function aioseo_schema($graphs)
	{
		if (! is_singular('post')) {
			return $graphs;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1) {
			return $graphs;
		}

		// Find and modify Article schema.
		foreach ($graphs as &$graph) {
			if (isset($graph['@type']) && 'Article' === $graph['@type']) {
				$author_schemas = [];

				foreach ($authors as $author) {
					$author_schemas[] = [
						'@type' => 'Person',
						'name'  => $author->display_name,
						'url'   => TMAuthors_Display::get_author_posts_url($author->ID),
					];
				}

				$graph['author'] = $author_schemas;
			}
		}

		return $graphs;
	}

	/**
	 * Modify author schema.
	 *
	 * @param array $author_schema Author schema.
	 *
	 * @return array Modified author schema.
	 * @since 1.0.0
	 */
	public static function seopress_author_schema($author_schema)
	{
		if (! is_singular('post')) {
			return $author_schema;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1) {
			return $author_schema;
		}

		// Return array of Person schemas.
		$author_schemas = [];

		foreach ($authors as $author) {
			$author_schemas[] = [
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => TMAuthors_Display::get_author_posts_url($author->ID),
			];
		}

		return $author_schemas;
	}

	/**
	 * Modify schema.
	 *
	 * @param array $schema Schema data.
	 *
	 * @return array Modified schema data.
	 * @since 1.0.0
	 */
	public static function tsf_schema($schema)
	{
		if (! is_singular('post')) {
			return $schema;
		}

		$post_id = get_the_ID();
		$authors = TMAuthors_Display::get_post_authors($post_id);

		if (count($authors) <= 1 || ! isset($schema['@graph'])) {
			return $schema;
		}

		// Modify Article schema in graph.
		foreach ($schema['@graph'] as &$graph_item) {
			if (isset($graph_item['@type']) && 'Article' === $graph_item['@type']) {
				$author_schemas = [];

				foreach ($authors as $author) {
					$author_schemas[] = [
						'@type' => 'Person',
						'name'  => $author->display_name,
						'url'   => TMAuthors_Display::get_author_posts_url($author->ID),
					];
				}

				$graph_item['author'] = $author_schemas;
			}
		}

		return $schema;
	}
}
