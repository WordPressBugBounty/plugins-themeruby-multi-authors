<?php
/**
 * Taxonomy Registration and On-Demand Term Creation
 *
 * Manages the hidden tmauthors taxonomy.
 * Taxonomy terms are created on-demand when authors are assigned to posts.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Taxonomy class.
 *
 * @since 1.0.0
 */
class TMAuthors_Taxonomy {

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	const TAXONOMY = 'tmauthors';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Register taxonomy.
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ], 0 );

		// Only sync on user deletion to clean up orphaned terms.
		add_action( 'delete_user', [ __CLASS__, 'delete_user_term' ], 10, 1 );

		// Validate taxonomy registration.
		add_action( 'init', [ __CLASS__, 'validate_taxonomy' ], 999 );
	}

	/**
	 * Register the tmauthors taxonomy.
	 *
	 * @since 1.0.0
	 */
	public static function register_taxonomy() {
		$labels = [
				'name'                       => _x( 'Authors', 'taxonomy general name', 'themeruby-multi-authors' ),
				'singular_name'              => _x( 'Author', 'taxonomy singular name', 'themeruby-multi-authors' ),
				'search_items'               => esc_html__( 'Search Authors', 'themeruby-multi-authors' ),
				'popular_items'              => esc_html__( 'Popular Authors', 'themeruby-multi-authors' ),
				'all_items'                  => esc_html__( 'All Authors', 'themeruby-multi-authors' ),
				'edit_item'                  => esc_html__( 'Edit Author', 'themeruby-multi-authors' ),
				'update_item'                => esc_html__( 'Update Author', 'themeruby-multi-authors' ),
				'add_new_item'               => esc_html__( 'Add New Author', 'themeruby-multi-authors' ),
				'new_item_name'              => esc_html__( 'New Author Name', 'themeruby-multi-authors' ),
				'separate_items_with_commas' => esc_html__( 'Separate authors with commas', 'themeruby-multi-authors' ),
				'add_or_remove_items'        => esc_html__( 'Add or remove authors', 'themeruby-multi-authors' ),
				'choose_from_most_used'      => esc_html__( 'Choose from the most used authors', 'themeruby-multi-authors' ),
				'not_found'                  => esc_html__( 'No authors found.', 'themeruby-multi-authors' ),
				'menu_name'                  => esc_html__( 'Authors', 'themeruby-multi-authors' ),
		];

		$args = [
				'labels'                => $labels,
				'public'                => false,
				'publicly_queryable'    => false, // Not publicly queryable - no taxonomy archives.
				'show_ui'               => false,
				'show_in_menu'          => false,
				'show_in_nav_menus'     => false,
				'show_in_rest'          => true,
				'rest_base'             => 'tmauthors',
				'rest_controller_class' => 'WP_REST_Terms_Controller',
				'show_tagcloud'         => false,
				'show_in_quick_edit'    => false,
				'show_admin_column'     => true,
				'hierarchical'          => false,
				'query_var'             => false, // Disable query var - prevents conflicts with WP core author.
				'rewrite'               => false, // No rewrite - prevents /author/ URL conflicts.
				'capabilities'          => [
						'manage_terms' => 'edit_posts',
						'edit_terms'   => 'edit_posts',
						'delete_terms' => 'edit_posts',
						'assign_terms' => 'edit_posts',
				],
		];

		/**
		 * Filter taxonomy registration args.
		 *
		 * @param array $args Taxonomy registration arguments.
		 */
		$args = apply_filters( 'tmauthors_taxonomy_args', $args );

		register_taxonomy( self::TAXONOMY, [ 'post' ], $args );
	}

	/**
	 * Create or update taxonomy term for a user on-demand.
	 *
	 * This is called only when needed (e.g., when assigning authors to posts).
	 * No automatic sync - terms are created on-demand when users are assigned as authors.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return int|WP_Error Term ID on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	public static function create_or_update_user_term( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error(
					'invalid_user',
					esc_html__( 'Invalid user ID.', 'themeruby-multi-authors' )
			);
		}

		$term_slug = 'user-' . $user_id;
		$term_name = $user->display_name;

		// Check if term already exists.
		$term = get_term_by( 'slug', $term_slug, self::TAXONOMY );

		if ( $term ) {
			// Update existing term if name changed.
			if ( $term->name !== $term_name ) {
				$result = wp_update_term( $term->term_id, self::TAXONOMY, [
						'name' => $term_name,
				] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$term_id = $result['term_id'];
			} else {
				$term_id = $term->term_id;
			}
		} else {
			// Create new term.
			$result = wp_insert_term( $term_name, self::TAXONOMY, [
					'slug' => $term_slug,
			] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$term_id = $result['term_id'];
		}

		// Store user ID in term meta.
		update_term_meta( $term_id, 'user_id', $user_id );

		// Clear cache for this term (if in admin context).
		if ( is_admin() && class_exists( 'TMAuthors_Cache_Admin' ) ) {
			TMAuthors_Cache_Admin::clear_term_cache( $term_id );
		}

		/**
		 * Fires after user term is created/updated on-demand.
		 *
		 * @param int $term_id Term ID.
		 * @param int $user_id User ID.
		 */
		do_action( 'tmauthors_user_term_created', $term_id, $user_id );

		return $term_id;
	}

	/**
	 * Delete taxonomy term when user is deleted.
	 *
	 * @param int $user_id User ID.
	 *
	 * @since 1.0.0
	 */
	public static function delete_user_term( $user_id ) {
		$term_slug = 'user-' . $user_id;
		$term      = get_term_by( 'slug', $term_slug, self::TAXONOMY );

		if ( $term ) {
			// Clear cache before deleting (if in admin context).
			if ( is_admin() && class_exists( 'TMAuthors_Cache_Admin' ) ) {
				TMAuthors_Cache_Admin::clear_term_cache( $term->term_id );
			}

			wp_delete_term( $term->term_id, self::TAXONOMY );
		}
	}

	/**
	 * Get term for a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return WP_Term|false Term object or false if not found.
	 * @since 1.0.0
	 */
	public static function get_user_term( $user_id ) {
		$term_slug = 'user-' . $user_id;

		return get_term_by( 'slug', $term_slug, self::TAXONOMY );
	}

	/**
	 * Get user ID from term.
	 *
	 * @param int|WP_Term $term Term ID or object.
	 *
	 * @return int|false User ID or false if not found.
	 * @since 1.0.0
	 */
	public static function get_term_user_id( $term ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( $term, self::TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		// Try to get from cache first if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'term_user_' . $term->term_id;
			$cache_group = 'tmauthors_term_users';
			$user_id     = wp_cache_get( $cache_key, $cache_group );

			if ( false !== $user_id ) {
				return $user_id;
			}
		}

		// Try to get from term meta first.
		$user_id = get_term_meta( $term->term_id, 'user_id', true );

		if ( $user_id ) {
			$user_id = absint( $user_id );
			// Cache the result if caching is enabled.
			if ( self::is_cache_enabled() ) {
				$cache_key   = 'term_user_' . $term->term_id;
				$cache_group = 'tmauthors_term_users';
				wp_cache_set( $cache_key, $user_id, $cache_group, 3600 );
			}

			return $user_id;
		}

		// Fallback: Parse from slug (user-123).
		if ( preg_match( '/^user-(\d+)$/', $term->slug, $matches ) ) {
			$user_id = absint( $matches[1] );
			// Cache the result if caching is enabled.
			if ( self::is_cache_enabled() ) {
				$cache_key   = 'term_user_' . $term->term_id;
				$cache_group = 'tmauthors_term_users';
				wp_cache_set( $cache_key, $user_id, $cache_group, 3600 );
			}

			return $user_id;
		}

		// Cache negative result to prevent repeated lookups if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'term_user_' . $term->term_id;
			$cache_group = 'tmauthors_term_users';
			wp_cache_set( $cache_key, false, $cache_group, 3600 );
		}

		return false;
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool True if caching is enabled.
	 * @since 1.0.0
	 */
	private static function is_cache_enabled() {
		return (bool) get_option( 'tmauthors_cache', 1 );
	}

	/**
	 * Validate taxonomy registration.
	 *
	 * @since 1.0.0
	 */
	public static function validate_taxonomy() {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {

			add_action( 'admin_notices', function () {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						echo esc_html__(
								'ThemeRuby Multi Authors: Taxonomy registration failed. Please deactivate and reactivate the plugin.',
								'themeruby-multi-authors'
						);
						?>
					</p>
				</div>
				<?php
			} );
		}
	}

	/**
	 * Get all author terms.
	 *
	 * @param array $args Get_terms arguments.
	 *
	 * @return array|WP_Error Array of term objects or WP_Error.
	 * @since 1.0.0
	 */
	public static function get_author_terms( $args = [] ) {
		$defaults = [
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		return get_terms( $args );
	}

}
