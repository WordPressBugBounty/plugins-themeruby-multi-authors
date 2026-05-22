<?php
/**
 * Capabilities and Permissions
 *
 * Handles user capability checks for assigning and managing authors.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Capabilities class.
 *
 * @since 1.0.0
 */
class TMAuthors_Capabilities {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
	}

	/**
	 * Get users who can be assigned as authors.
	 *
	 * @param array $args Optional. Get_users arguments.
	 *
	 * @return array Array of WP_User objects.
	 * @since 1.0.0
	 */
	public static function get_assignable_authors( $args = [] ) {
		$defaults = [
			'capability' => [ 'edit_posts' ],
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'fields'     => 'all',
		];

		$args = wp_parse_args( $args, $defaults );

		$users = get_users( $args );

		// Filter by capability.
		$users = array_filter( $users, function ( $user ) {
			return self::can_be_author( $user->ID );
		} );

		/**
		 * Filter assignable authors.
		 *
		 * @param array $users Array of WP_User objects.
		 * @param array $args Get_users arguments.
		 */
		return apply_filters( 'tmauthors_assignable_authors', $users, $args );
	}

	/**
	 * Check if user can be assigned as an author.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool True if user can be an author.
	 * @since 1.0.0
	 */
	public static function can_be_author( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		/**
		 * Filter whether user can be assigned as author.
		 *
		 * @param bool $can_be_author Whether user can be author.
		 * @param int $user_id User ID.
		 * @param WP_User $user User object.
		 */
		return apply_filters(
			'tmauthors_user_can_be_author',
			user_can( $user_id, 'edit_posts' ),
			$user_id,
			$user
		);
	}

	/**
	 * Validate author assignment.
	 *
	 * @param int $post_id Post ID.
	 * @param array $author_ids Array of author user IDs.
	 *
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 * @since 1.0.0
	 */
	public static function validate_author_assignment( $post_id, $author_ids ) {

		// Check if current user can assign authors.
		if ( ! self::can_assign_authors() ) {
			return new WP_Error(
				'cannot_assign_authors',
				esc_html__( 'You do not have permission to assign authors.', 'themeruby-multi-authors' )
			);
		}

		// Validate each author.
		foreach ( $author_ids as $author_id ) {
			if ( ! self::can_be_author( $author_id ) ) {
				return new WP_Error(
					'invalid_author',
					sprintf(
					/* translators: %d: User ID */
						esc_html__( 'User %d cannot be assigned as an author.', 'themeruby-multi-authors' ),
						$author_id
					)
				);
			}
		}

		/**
		 * Filter author assignment validation.
		 *
		 * @param bool|WP_Error $valid True if valid, WP_Error on failure.
		 * @param int $post_id Post ID.
		 * @param array $author_ids Array of author user IDs.
		 */
		return apply_filters( 'tmauthors_validate_author_assignment', true, $post_id, $author_ids );
	}

	/**
	 * Check if user can assign authors to posts.
	 *
	 * @param int $user_id User ID. Defaults to current user.
	 *
	 * @return bool True if user can assign authors.
	 * @since 1.0.0
	 */
	public static function can_assign_authors( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		/**
		 * Filter whether user can assign authors.
		 *
		 * @param bool $can_assign Whether user can assign authors.
		 * @param int $user_id User ID.
		 */
		return apply_filters(
			'tmauthors_user_can_assign_authors',
			user_can( $user_id, 'edit_posts' ),
			$user_id
		);
	}
}
