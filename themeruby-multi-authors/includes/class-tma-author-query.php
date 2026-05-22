<?php
/**
 * Author Page Query Filter
 *
 * Modifies author archive queries to include posts where the user is:
 * 1. Primary author (post_author field) - managed by core
 * 2. Additional author (tmauthors taxonomy) - managed by this plugin
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Author_Query class.
 *
 * @since 1.0.0
 */
class TMAuthors_Author_Query {

	/**
	 * Current author being queried.
	 *
	 * @var int
	 */
	private static $current_author_id = 0;

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'pre_get_posts', [ __CLASS__, 'setup_author_query' ], 200 );
		add_filter( 'posts_where', [ __CLASS__, 'modify_posts_where' ], 200, 2 );
		add_filter( 'posts_join', [ __CLASS__, 'modify_posts_join' ], 200, 2 );
		add_filter( 'posts_distinct', [ __CLASS__, 'modify_posts_distinct' ], 200, 2 );
	}

	/**
	 * Setup author query parameters.
	 *
	 * Detects if this is an author page and stores the author ID.
	 *
	 * @param WP_Query $query Query object.
	 *
	 * @since 1.0.0
	 */
	public static function setup_author_query( $query ) {

		// Only modify main query on frontend author archives.
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_author() ) {
			self::$current_author_id = 0;

			return;
		}

		// Check if archive filtering is enabled.
		if ( ! get_option( 'tmauthors_archive_filtering', 1 ) ) {
			self::$current_author_id = 0;

			return;
		}

		// Get the author being queried.
		$author_id = $query->get( 'author' );

		if ( ! $author_id ) {
			$author_name = $query->get( 'author_name' );
			if ( $author_name ) {
				$author = get_user_by( 'slug', $author_name );
				if ( $author ) {
					$author_id = $author->ID;
				}
			}
		}

		if ( ! $author_id ) {
			self::$current_author_id = 0;

			return;
		}

		// Store author ID for use in filter hooks.
		self::$current_author_id = $author_id;

		/**
		 * Fires when author archive query is detected.
		 *
		 * @param WP_Query $query Query object.
		 * @param int $author_id Author user ID.
		 */
		do_action( 'tmauthors_archive_detected', $query, $author_id );
	}

	/**
	 * Modify the SQL JOIN clause for author archive queries.
	 *
	 * Adds term relationship and taxonomy joins to ensure posts
	 * are correctly associated with the custom author taxonomy.
	 *
	 * @param string $join The existing SQL JOIN clause.
	 * @param WP_Query $query The current query instance.
	 *
	 * @return string Modified SQL JOIN clause.
	 */
	public static function modify_posts_join( $join, $query ) {

		// Only modify queries that meet our conditions.
		if ( ! self::should_modify_query( $query ) ) {
			return $join;
		}

		global $wpdb;

		$taxonomy = esc_sql( TMAuthors_Taxonomy::TAXONOMY );

		// Append LEFT JOIN clauses for taxonomy relationships.
		$join .= " LEFT JOIN {$wpdb->term_relationships} AS tmauthors_tr 
                ON ({$wpdb->posts}.ID = tmauthors_tr.object_id)";

		$join .= " LEFT JOIN {$wpdb->term_taxonomy} AS tmauthors_tt 
                ON (tmauthors_tr.term_taxonomy_id = tmauthors_tt.term_taxonomy_id 
                AND tmauthors_tt.taxonomy = '{$taxonomy}')";

		return $join;
	}

	/**
	 * Check if query should be modified.
	 *
	 * @param WP_Query $query Query object.
	 *
	 * @return bool True if query should be modified.
	 * @since 1.0.0
	 */
	private static function should_modify_query( $query ) {

		// Don't modify admin queries.
		if ( is_admin() ) {
			return false;
		}

		// Only modify main query.
		if ( ! $query->is_main_query() ) {
			return false;
		}

		// Only modify author archives.
		if ( ! $query->is_author() ) {
			return false;
		}

		// Must have current author ID set.
		if ( empty( self::$current_author_id ) ) {
			return false;
		}

		/**
		 * Filter whether to modify the author query.
		 *
		 * @param bool $should_modify Whether to modify the query.
		 * @param WP_Query $query Query object.
		 * @param int $author_id Author user ID.
		 */
		return apply_filters( 'tmauthors_should_modify_author_query', true, $query, self::$current_author_id );
	}

	/**
	 * Modify posts WHERE clause.
	 *
	 * Adds OR condition to include posts where the user is an additional author
	 * (assigned via the tmauthors taxonomy).
	 *
	 * @param string $where WHERE clause.
	 * @param WP_Query $query Query object.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 */
	public static function modify_posts_where( $where, $query ) {

		// Only modify if this is our author archive query.
		if ( ! self::should_modify_query( $query ) ) {
			return $where;
		}

		$author_id = self::$current_author_id;

		if ( ! $author_id ) {
			return $where;
		}

		// Get the author's taxonomy term.
		$term = TMAuthors_Taxonomy::get_user_term( $author_id );

		if ( ! $term ) {
			// No taxonomy term exists for this author yet.
			return $where;
		}

		global $wpdb;

		$author_id_safe = absint( $author_id );
		$term_id_safe   = absint( $term->term_id );

		// Use regex to match various query formats
		$pattern = '/(\()?\s*' . preg_quote( $wpdb->posts, '/' ) . '\.post_author\s*(?:IN\s*\(\s*|=\s*[\'"]?)' . $author_id_safe . '[\'"]?\s*\)?\s*(\))?/i';

		$new_condition = "({$wpdb->posts}.post_author = {$author_id_safe} OR tmauthors_tt.term_id = {$term_id_safe})";

		// Try to replace using regex
		$new_where = preg_replace( $pattern, $new_condition, $where, 1, $count );

		if ( $count > 0 ) {
			$where = $new_where;
		}

		/**
		 * Fires after author archive WHERE clause is modified.
		 *
		 * @param string $where Modified WHERE clause.
		 * @param int $author_id Author user ID.
		 * @param WP_Term $term Author's taxonomy term.
		 */
		do_action( 'tmauthors_where_modified', $where, $author_id, $term );

		return $where;
	}

	/**
	 * Add DISTINCT to prevent duplicate posts.
	 *
	 * When a post matches both conditions (post_author AND taxonomy term),
	 *
	 * @param string $distinct DISTINCT clause.
	 * @param WP_Query $query Query object.
	 *
	 * @return string Modified DISTINCT clause.
	 * @since 1.0.0
	 */
	public static function modify_posts_distinct( $distinct, $query ) {

		// Only modify if this is our author archive query.
		if ( ! self::should_modify_query( $query ) ) {
			return $distinct;
		}

		return 'DISTINCT';
	}

	/**
	 * Get current author ID being queried.
	 *
	 * @return int Author user ID or 0 if not in author archive.
	 * @since 1.0.0
	 */
	public static function get_current_author_id() {
		return self::$current_author_id;
	}

	/**
	 * Handles the database query logic for author post count.
	 *
	 * @param int $user_id
	 * @param string $post_type
	 *
	 * @return int
	 */
	public static function count_posts( $user_id, $post_type = 'post' ) {

		global $wpdb;

		$term = TMAuthors_Taxonomy::get_user_term( $user_id );

		if ( $term ) {
			// Count posts with either taxonomy term OR primary author in a single query.
			$sql = $wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					WHERE p.post_status = 'publish'
					AND p.post_type = %s
					AND (
						(tt.taxonomy = %s AND tt.term_id = %d)
						OR p.post_author = %d
					)",
				$post_type,
				TMAuthors_Taxonomy::TAXONOMY,
				$term->term_id,
				$user_id
			);
		} else {
			// No taxonomy term exists, only count primary author posts.
			$sql = $wpdb->prepare(
				"SELECT COUNT(p.ID)
					FROM {$wpdb->posts} p
					WHERE p.post_status = 'publish'
					AND p.post_type = %s
					AND p.post_author = %d",
				$post_type,
				$user_id
			);
		}

		/**
		 * We are executing a direct database query for performance reasons.
		 * - Safe because all variables are passed via $wpdb->prepare().
		 * - No caching here because this is a low-level helper and caching is implemented at a higher layer.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $sql );
	}
}
