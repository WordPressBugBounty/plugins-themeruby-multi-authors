<?php
/**
 * Cache Management for Admin
 *
 * Handles cache invalidation and clearing in admin area.
 * This class is only loaded in wp-admin to avoid overhead on frontend.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Cache_Admin class.
 *
 * @since 1.0.0
 */
class TMAuthors_Cache_Admin {

	/**
	 * Initialize admin cache management hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Cache invalidation hooks.
		add_action( 'tmauthors_assigned', [ __CLASS__, 'clear_post_authors_cache' ], 10, 2 );
		add_action( 'profile_update', [ __CLASS__, 'clear_all_cache' ], 10, 1 );
		add_action( 'delete_post', [ __CLASS__, 'clear_author_counts_cache' ], 10, 1 );
		add_action( 'transition_post_status', [ __CLASS__, 'handle_post_status_change' ], 10, 3 );
	}

	/**
	 * Clear post authors cache when authors are assigned.
	 *
	 * @param int $post_id Post ID.
	 * @param array $author_ids Array of author user IDs.
	 *
	 * @since 1.0.0
	 */
	public static function clear_post_authors_cache( $post_id, $author_ids ) {
		// Clear the post authors cache.
		$cache_key = 'post_authors_' . $post_id;
		wp_cache_delete( $cache_key, 'tmauthors_post_authors' );

		// Clear author counts cache for all assigned authors.
		foreach ( $author_ids as $user_id ) {
			self::clear_author_count_cache( $user_id );
		}
	}

	/**
	 * Clear author count cache for a specific user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @since 1.0.0
	 */
	public static function clear_author_count_cache( $user_id ) {
		// Clear all post type counts for this user.
		$post_types = get_post_types( [ 'public' => true ] );
		foreach ( $post_types as $post_type ) {
			$cache_key = 'author_count_' . $user_id . '_' . $post_type;
			wp_cache_delete( $cache_key, 'tmauthors_counts' );
		}
	}

	/**
	 * Handle post status changes to clear relevant caches.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param WP_Post $post Post object.
	 *
	 * @since 1.0.0
	 */
	public static function handle_post_status_change( $new_status, $old_status, $post ) {
		// Only clear cache if status changes to/from publish.
		if ( $new_status === 'publish' || $old_status === 'publish' ) {
			self::clear_author_counts_cache( $post->ID );
		}
	}

	/**
	 * Clear author counts cache when a post is deleted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.0.0
	 */
	public static function clear_author_counts_cache( $post_id ) {
		// Get authors for this post and clear their count cache.
		$authors = TMAuthors_Display::get_post_authors( $post_id );

		foreach ( $authors as $author ) {
			self::clear_author_count_cache( $author->ID );
		}

		// Clear the post's author cache.
		$cache_key = 'post_authors_' . $post_id;
		wp_cache_delete( $cache_key, 'tmauthors_post_authors' );
	}

	/**
	 * Clear all author-related caches.
	 *
	 * This method clears all cached data for the plugin.
	 * Use this when you need to completely flush all author caches.
	 *
	 * @since 1.0.0
	 */
	public static function clear_all_cache() {

		// Flush entire cache groups at once (fastest method).
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'tmauthors_post_authors' );
			wp_cache_flush_group( 'tmauthors_term_users' );
			wp_cache_flush_group( 'tmauthors_counts' );
		}

	}

	/**
	 * Clear term user cache.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @since 1.0.0
	 */
	public static function clear_term_cache( $term_id ) {
		$cache_key = 'term_user_' . $term_id;
		wp_cache_delete( $cache_key, 'tmauthors_term_users' );
	}
}
