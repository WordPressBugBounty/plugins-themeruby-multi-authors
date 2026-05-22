<?php
/**
 * Query modification class for ThemeRuby Multi Authors
 *
 * Extends WP_Query to support author parameters for posts assigned through
 * additional authors (stored in tmauthors taxonomy).
 *
 * Supports core WP_Query author parameters:
 * - author (int): Query by author ID
 * - author_name (string): Query by author nicename
 * - author__in (array): Query by multiple author IDs
 * - author__not_in (array): Exclude author IDs
 *
 * NOTE: This class handles CUSTOM queries only (new WP_Query).
 * Main queries on author archives are handled by TMAuthors_Author_Query class.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TMAuthors_Query
 *
 * @since 1.0.0
 */
class TMAuthors_Query {

	/**
	 * Store query parameters for queries we should modify
	 *
	 * @var array
	 */
	private static $queries_to_modify = [];

	/**
	 * Maximum number of queries to track (prevent memory leaks)
	 *
	 * @var int
	 */
	private static $max_queries_tracked = 50;

	/**
	 * Cache for post authors to avoid redundant queries
	 *
	 * @var array
	 */
	private static $authors_cache = [];

	/**
	 * Initialize hooks
	 *
	 * @since 1.0.0
	 */
	public static function init() {

		// Capture author parameters from custom queries
		add_action( 'pre_get_posts', [ __CLASS__, 'setup_query' ], 10 );

		// Modify SQL for custom queries with author parameters
		add_filter( 'posts_where', [ __CLASS__, 'modify_posts_where' ], 10, 2 );
		add_filter( 'posts_join', [ __CLASS__, 'modify_posts_join' ], 10, 2 );
		add_filter( 'posts_distinct', [ __CLASS__, 'modify_posts_distinct' ], 10, 2 );

		// Cleanup after query is done
		add_action( 'the_posts', [ __CLASS__, 'cleanup_query' ], 10, 2 );

		// Clear authors cache when post terms are updated
		add_action( 'set_object_terms', [ __CLASS__, 'clear_post_cache' ], 10, 4 );
	}

	/**
	 * Setup query parameters
	 *
	 * Detects custom queries with author parameters and stores them.
	 *
	 * @param WP_Query $query Query object.
	 *
	 * @since 1.0.0
	 */
	public static function setup_query( $query ) {
		$query_id = spl_object_hash( $query );

		// Prevent memory leaks by cleaning up old queries
		if ( count( self::$queries_to_modify ) >= self::$max_queries_tracked ) {
			self::$queries_to_modify = array_slice( self::$queries_to_modify, - 25, 25, true );
		}

		// Reset this query's state
		self::$queries_to_modify[ $query_id ] = false;

		// Only modify CUSTOM queries (not main query)
		if ( ! self::should_modify_query( $query ) ) {
			return;
		}

		// Determine which parameter to use (priority order)
		$author_param = self::get_author_parameter( $query );

		if ( ! $author_param ) {
			return;
		}

		// Store query parameters for later use
		self::$queries_to_modify[ $query_id ] = $author_param;
	}

	/**
	 * Check if the query should be modified
	 *
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return bool True if query should be modified, false otherwise.
	 * @since 1.0.0
	 */
	private static function should_modify_query( $query ) {

		if ( $query->is_main_query() ) {
			return false;
		}

		// Check if any author parameters are set and their corresponding settings are enabled.
		$has_author_params = false;

		if ( $query->get( 'author' ) && get_option( 'tmauthors_author_filtering', 1 ) ) {
			$has_author_params = true;
		}

		if ( $query->get( 'author_name' ) && get_option( 'tmauthors_author_name_filtering', 1 ) ) {
			$has_author_params = true;
		}

		if ( $query->get( 'author__in' ) && get_option( 'tmauthors_author_in_filtering', 1 ) ) {
			$has_author_params = true;
		}

		if ( $query->get( 'author__not_in' ) && get_option( 'tmauthors_author_not_in_filtering', 1 ) ) {
			$has_author_params = true;
		}

		if ( ! $has_author_params ) {
			return false;
		}

		// Allow filtering whether to modify this specific query.
		return apply_filters( 'tmauthors_should_modify_query', true, $query );
	}

	/**
	 * Get the primary author parameter to use
	 *
	 * Priority order when multiple params exist:
	 * 1. author (single ID takes precedence)
	 * 2. author_name (nicename)
	 * 3. author__in (multiple IDs)
	 * 4. author__not_in (exclusion only)
	 *
	 * @param WP_Query $query Query object.
	 *
	 * @return array|false Author parameter data or false if none.
	 * @since 1.0.0
	 */
	private static function get_author_parameter( $query ) {
		// Priority 1: Single author ID (if enabled).
		$author = $query->get( 'author' );
		if ( ! empty( $author ) && is_numeric( $author ) && get_option( 'tmauthors_author_filtering', 1 ) ) {
			$author_id = absint( $author );
			if ( $author_id > 0 ) {
				$term = TMAuthors_Taxonomy::get_user_term( $author_id );

				return [
					'type'      => 'author',
					'author_id' => $author_id,
					'term_id'   => $term ? $term->term_id : null,
				];
			}
		}

		// Priority 2: Author nicename (if enabled).
		$author_name = $query->get( 'author_name' );
		if ( ! empty( $author_name ) && is_string( $author_name ) && get_option( 'tmauthors_author_name_filtering', 1 ) ) {
			$user = get_user_by( 'slug', sanitize_title( $author_name ) );
			if ( $user ) {
				$term = TMAuthors_Taxonomy::get_user_term( $user->ID );

				return [
					'type'      => 'author_name',
					'author_id' => $user->ID,
					'term_id'   => $term ? $term->term_id : null,
				];
			}
		}

		// Priority 3: Multiple author IDs (if enabled).
		$author_in = $query->get( 'author__in' );
		if ( ! empty( $author_in ) && is_array( $author_in ) && get_option( 'tmauthors_author_in_filtering', 1 ) ) {
			$author_ids = array_filter( array_map( 'absint', $author_in ) );
			if ( ! empty( $author_ids ) ) {
				$term_ids = [];
				foreach ( $author_ids as $author_id ) {
					$term = TMAuthors_Taxonomy::get_user_term( $author_id );
					if ( $term ) {
						$term_ids[] = $term->term_id;
					}
				}

				return [
					'type'       => 'author__in',
					'author_ids' => $author_ids,
					'term_ids'   => $term_ids,
				];
			}
		}

		// Priority 4: Exclude authors (only if no inclusion params and if enabled).
		$author_not_in = $query->get( 'author__not_in' );
		if ( ! empty( $author_not_in ) && is_array( $author_not_in ) && get_option( 'tmauthors_author_not_in_filtering', 1 ) ) {
			$author_ids = array_filter( array_map( 'absint', $author_not_in ) );
			if ( ! empty( $author_ids ) ) {
				$term_ids = [];
				foreach ( $author_ids as $author_id ) {
					$term = TMAuthors_Taxonomy::get_user_term( $author_id );
					if ( $term ) {
						$term_ids[] = $term->term_id;
					}
				}

				return [
					'type'       => 'author__not_in',
					'author_ids' => $author_ids,
					'term_ids'   => $term_ids,
				];
			}
		}

		return false;
	}

	/**
	 * Modify JOIN clause to include taxonomy tables
	 *
	 * @param string $join The JOIN clause.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified JOIN clause.
	 * @since 1.0.0
	 */
	public static function modify_posts_join( $join, $query ) {
		$query_id = spl_object_hash( $query );

		if ( empty( self::$queries_to_modify[ $query_id ] ) ) {
			return $join;
		}

		global $wpdb;

		// Prevent duplicate JOINs
		if ( strpos( $join, 'tmauthors_tr' ) !== false ) {
			return $join;
		}

		// Add LEFT JOIN to term_relationships and term_taxonomy
		// Note: We don't need the terms table since we use term_id directly
		$taxonomy = esc_sql( TMAuthors_Taxonomy::TAXONOMY );
		$join    .= " LEFT JOIN {$wpdb->term_relationships} AS tmauthors_tr ON ({$wpdb->posts}.ID = tmauthors_tr.object_id)";
		$join    .= " LEFT JOIN {$wpdb->term_taxonomy} AS tmauthors_tt ON (tmauthors_tr.term_taxonomy_id = tmauthors_tt.term_taxonomy_id AND tmauthors_tt.taxonomy = '{$taxonomy}')";

		return $join;
	}

	/**
	 * Modify WHERE clause to include posts by additional authors
	 *
	 * Uses OR logic: post_author = X OR taxonomy has 'user-X'
	 *
	 * @param string $where The WHERE clause.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 */
	public static function modify_posts_where( $where, $query ) {
		$query_id = spl_object_hash( $query );

		if ( empty( self::$queries_to_modify[ $query_id ] ) ) {
			return $where;
		}

		global $wpdb;
		$param = self::$queries_to_modify[ $query_id ];

		// Handle based on parameter type
		switch ( $param['type'] ) {
			case 'author':
			case 'author_name':
				$where = self::modify_single_author_where( $where, $param, $wpdb );
				break;

			case 'author__in':
				$where = self::modify_author_in_where( $where, $param, $wpdb );
				break;

			case 'author__not_in':
				$where = self::modify_author_not_in_where( $where, $param, $wpdb );
				break;
		}

		return $where;
	}

	/**
	 * Modify WHERE for single author query
	 *
	 * @param string $where The WHERE clause.
	 * @param array $param_data Parameter data with author_id and term_id.
	 * @param wpdb $wpdb Database object.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 */
	private static function modify_single_author_where( $where, $param_data, $wpdb ) {
		$author_id = absint( $param_data['author_id'] );
		$term_id   = ! empty( $param_data['term_id'] ) ? absint( $param_data['term_id'] ) : null;

		// Use regex to match various author query formats
		$pattern = '/(\()?\s*' . preg_quote( $wpdb->posts, '/' ) . '\.post_author\s*(?:IN\s*\(\s*|=\s*[\'"]?)' . $author_id . '[\'"]?\s*\)?\s*(\))?/i';

		if ( $term_id ) {
			// Include term_id in OR condition
			$new_condition = "({$wpdb->posts}.post_author = {$author_id} OR tmauthors_tt.term_id = {$term_id})";
		} else {
			// No term exists yet, just keep the author condition
			$new_condition = "({$wpdb->posts}.post_author = {$author_id})";
		}

		// Try to replace using regex
		$new_where = preg_replace( $pattern, $new_condition, $where, 1, $count );

		if ( $count > 0 ) {
			// Successfully replaced
			return $new_where;
		}

		// Fallback: add condition if pattern not found
		if ( $term_id ) {
			$where .= " AND ({$wpdb->posts}.post_author = {$author_id} OR tmauthors_tt.term_id = {$term_id})";
		} else {
			$where .= " AND ({$wpdb->posts}.post_author = {$author_id})";
		}

		return $where;
	}

	/**
	 * Modify WHERE for author__in query
	 *
	 * @param string $where The WHERE clause.
	 * @param array $param_data Parameter data with author_ids and term_ids.
	 * @param wpdb $wpdb Database object.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 */
	private static function modify_author_in_where( $where, $param_data, $wpdb ) {
		$author_ids     = array_map( 'absint', $param_data['author_ids'] );
		$author_ids_str = implode( ',', $author_ids );

		$term_ids = ! empty( $param_data['term_ids'] ) ? array_map( 'absint', $param_data['term_ids'] ) : [];

		// Use regex to match: wp_posts.post_author IN (X,Y,Z) with optional outer parentheses and variable whitespace
		$pattern = '/(\()?\s*' . preg_quote( $wpdb->posts, '/' ) . '\.post_author\s+IN\s*\(\s*' . preg_quote( $author_ids_str, '/' ) . '\s*\)\s*(\))?/i';

		if ( ! empty( $term_ids ) ) {
			$term_ids_str  = implode( ',', $term_ids );
			$new_condition = "({$wpdb->posts}.post_author IN ({$author_ids_str}) OR tmauthors_tt.term_id IN ({$term_ids_str}))";
		} else {
			// No terms exist yet
			$new_condition = "({$wpdb->posts}.post_author IN ({$author_ids_str}))";
		}

		// Try to replace using regex
		$new_where = preg_replace( $pattern, $new_condition, $where, 1, $count );

		if ( $count > 0 ) {
			// Successfully replaced
			return $new_where;
		}

		// Fallback: add condition if pattern not found
		if ( ! empty( $term_ids ) ) {
			$term_ids_str = implode( ',', $term_ids );
			$where       .= " AND ({$wpdb->posts}.post_author IN ({$author_ids_str}) OR tmauthors_tt.term_id IN ({$term_ids_str}))";
		} else {
			$where .= " AND ({$wpdb->posts}.post_author IN ({$author_ids_str}))";
		}

		return $where;
	}

	/**
	 * Modify WHERE for author__not_in query
	 *
	 * @param string $where The WHERE clause.
	 * @param array $param_data Parameter data with author_ids and term_ids.
	 * @param wpdb $wpdb Database object.
	 *
	 * @return string Modified WHERE clause.
	 * @since 1.0.0
	 */
	private static function modify_author_not_in_where( $where, $param_data, $wpdb ) {
		$author_ids     = array_map( 'absint', $param_data['author_ids'] );
		$author_ids_str = implode( ',', $author_ids );

		$term_ids = ! empty( $param_data['term_ids'] ) ? array_map( 'absint', $param_data['term_ids'] ) : [];

		// Use regex to match: wp_posts.post_author NOT IN (X,Y,Z) with optional outer parentheses and variable whitespace
		$pattern = '/(\()?\s*' . preg_quote( $wpdb->posts, '/' ) . '\.post_author\s+NOT\s+IN\s*\(\s*' . preg_quote( $author_ids_str, '/' ) . '\s*\)\s*(\))?/i';

		if ( ! empty( $term_ids ) ) {
			$term_ids_str  = implode( ',', $term_ids );
			$new_condition = "({$wpdb->posts}.post_author NOT IN ({$author_ids_str}) AND (tmauthors_tt.term_id IS NULL OR tmauthors_tt.term_id NOT IN ({$term_ids_str})))";
		} else {
			// No terms exist yet
			$new_condition = "({$wpdb->posts}.post_author NOT IN ({$author_ids_str}))";
		}

		// Try to replace using regex
		$new_where = preg_replace( $pattern, $new_condition, $where, 1, $count );

		if ( $count > 0 ) {
			// Successfully replaced
			return $new_where;
		}

		// Fallback: add condition if pattern not found
		if ( ! empty( $term_ids ) ) {
			$term_ids_str = implode( ',', $term_ids );
			$where       .= " AND ({$wpdb->posts}.post_author NOT IN ({$author_ids_str}) AND (tmauthors_tt.term_id IS NULL OR tmauthors_tt.term_id NOT IN ({$term_ids_str})))";
		} else {
			$where .= " AND ({$wpdb->posts}.post_author NOT IN ({$author_ids_str}))";
		}

		return $where;
	}

	/**
	 * Add DISTINCT to prevent duplicate posts
	 *
	 * @param string $distinct DISTINCT clause.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return string Modified DISTINCT clause.
	 * @since 1.0.0
	 */
	public static function modify_posts_distinct( $distinct, $query ) {
		$query_id = spl_object_hash( $query );

		if ( empty( self::$queries_to_modify[ $query_id ] ) ) {
			return $distinct;
		}

		return 'DISTINCT';
	}

	/**
	 * Cleanup query data after posts are retrieved
	 *
	 * @param array $posts Array of posts.
	 * @param WP_Query $query The WP_Query instance.
	 *
	 * @return array Posts array (unchanged).
	 * @since 1.0.0
	 */
	public static function cleanup_query( $posts, $query ) {
		$query_id = spl_object_hash( $query );

		// Remove this query from tracking to prevent memory leaks
		if ( isset( self::$queries_to_modify[ $query_id ] ) ) {
			unset( self::$queries_to_modify[ $query_id ] );
		}

		return $posts;
	}

	/**
	 * Clear post authors cache when terms are updated
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Array of term IDs.
	 * @param array  $tt_ids     Array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 *
	 * @since 1.3.0
	 */
	public static function clear_post_cache( $object_id, $terms, $tt_ids, $taxonomy ) {
		// Only clear cache for our taxonomy
		if ( TMAuthors_Taxonomy::TAXONOMY !== $taxonomy ) {
			return;
		}

		$post_id = absint( $object_id );
		if ( isset( self::$authors_cache[ $post_id ] ) ) {
			unset( self::$authors_cache[ $post_id ] );
		}
	}

	/**
	 * Get posts by author including additional authors
	 *
	 * Helper method for querying posts by author ID.
	 * This automatically includes posts where the author is assigned as additional author.
	 *
	 * @param int $author_id Author user ID.
	 * @param array $args Additional WP_Query arguments.
	 *
	 * @return WP_Query The query object.
	 * @since 1.0.0
	 */
	public static function get_posts_by_author( $author_id, $args = [] ) {
		$default_args = [
			'author'         => $author_id,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		];

		$args = wp_parse_args( $args, $default_args );

		return new WP_Query( $args );
	}

	/**
	 * Get posts by author nicename including additional authors
	 *
	 * Helper method for querying posts by author nicename.
	 *
	 * @param string $author_name Author nicename (user_nicename).
	 * @param array $args Additional WP_Query arguments.
	 *
	 * @return WP_Query The query object.
	 * @since 1.0.0
	 */
	public static function get_posts_by_author_name( $author_name, $args = [] ) {
		$default_args = [
			'author_name'    => $author_name,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		];

		$args = wp_parse_args( $args, $default_args );

		return new WP_Query( $args );
	}

	/**
	 * Get posts by multiple authors including additional authors
	 *
	 * Helper method for querying posts by multiple author IDs.
	 *
	 * @param array $author_ids Array of author user IDs.
	 * @param array $args Additional WP_Query arguments.
	 *
	 * @return WP_Query The query object.
	 * @since 1.0.0
	 */
	public static function get_posts_by_authors( $author_ids, $args = [] ) {
		$default_args = [
			'author__in'     => $author_ids,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
		];

		$args = wp_parse_args( $args, $default_args );

		return new WP_Query( $args );
	}

	/**
	 * Check if a user is an author of a post
	 *
	 * Checks if a user is either the primary author or an additional author.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 *
	 * @return bool True if user is an author, false otherwise.
	 * @since 1.0.0
	 */
	public static function is_post_author( $post_id, $user_id ) {
		$author_ids = self::get_post_author_ids( $post_id );

		return in_array( absint( $user_id ), $author_ids, true );
	}

	/**
	 * Get author IDs for a post
	 *
	 * Returns all author IDs for a post, including primary and additional authors.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Array of author user IDs.
	 * @since 1.0.0
	 */
	public static function get_post_author_ids( $post_id ) {
		$author_ids = [];

		$authors = self::get_post_authors( $post_id );

		foreach ( $authors as $author ) {
			$author_ids[] = $author->ID;
		}

		return $author_ids;
	}

	/**
	 * Get all authors for a post
	 *
	 * Returns all authors for a post, including the primary author and additional authors.
	 * Results are cached to avoid redundant database queries.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Array of WP_User objects.
	 * @since 1.0.0
	 */
	public static function get_post_authors( $post_id ) {
		$post_id = absint( $post_id );

		// Check static cache first
		if ( isset( self::$authors_cache[ $post_id ] ) ) {
			return self::$authors_cache[ $post_id ];
		}

		$authors = [];

		// Get terms from taxonomy
		$terms = wp_get_object_terms( $post_id, TMAuthors_Taxonomy::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Fallback to post_author if no taxonomy terms
			$post = get_post( $post_id );
			if ( $post && $post->post_author ) {
				$user = get_user_by( 'id', $post->post_author );
				if ( $user ) {
					$authors[] = $user;
				}
			}

			// Cache and return
			self::$authors_cache[ $post_id ] = $authors;
			return $authors;
		}

		// Collect user IDs for batch fetching
		$user_ids = [];
		foreach ( $terms as $term ) {
			$user_id = TMAuthors_Taxonomy::get_term_user_id( $term );
			if ( $user_id ) {
				$user_ids[] = $user_id;
			}
		}

		// Batch fetch users with single query
		if ( ! empty( $user_ids ) ) {
			$users = get_users(
				[
					'include' => $user_ids,
					'orderby' => 'include', // Preserve term order
				]
			);

			foreach ( $users as $user ) {
				$authors[] = $user;
			}
		}

		// Cache result (limit cache size to prevent memory issues)
		if ( 100 <= count( self::$authors_cache ) ) {
			self::$authors_cache = array_slice( self::$authors_cache, -50, 50, true );
		}
		self::$authors_cache[ $post_id ] = $authors;

		return $authors;
	}
}
