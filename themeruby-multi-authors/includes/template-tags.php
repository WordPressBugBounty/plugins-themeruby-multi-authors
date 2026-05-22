<?php
/**
 * Template Tags
 *
 * Public template tag functions for use in themes.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get post authors.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 *
 * @return array Array of WP_User objects.
 * @since 1.0.0
 */
function tmauthors_get_post_authors( $post_id = 0 ) {
	return TMAuthors_Display::get_post_authors( $post_id );
}

/**
 * Display formatted authors.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 * @param array $args Optional. Display arguments.
 *                       {
 *
 * @type string $before HTML before authors. Default ''.
 * @type string $after HTML after authors. Default ''.
 * @type string $separator Separator between authors. Default ', '.
 * @type bool $link Whether to link to author archives. Default true.
 * @type bool $echo Whether to echo or return. Default true.
 *                       }
 * @return string|void Formatted authors HTML or void if echo is true.
 * @since 1.0.0
 */
function tmauthors_the_authors( $post_id = 0, $args = [] ) {
	return TMAuthors_Display::the_authors( $post_id, $args );
}

/**
 * Check if post has multiple authors.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 *
 * @return bool True if post has multiple authors.
 * @since 1.0.0
 */
function tmauthors_has_multiple_authors( $post_id = 0 ) {
	return TMAuthors_Display::has_multiple_authors( $post_id );
}

/**
 * Get author posts URL.
 *
 * @param int $user_id User ID.
 *
 * @return string Author archive URL.
 * @since 1.0.0
 */
function tmauthors_get_author_posts_url( $user_id ) {
	return TMAuthors_Display::get_author_posts_url( $user_id );
}

/**
 * Set authors for a post.
 *
 * @param int $post_id Post ID.
 * @param array $author_ids Array of author user IDs.
 *
 * @return bool|WP_Error True on success, WP_Error on failure.
 * @since 1.0.0
 */
function tmauthors_set_post_authors( $post_id, $author_ids ) {
	if ( ! is_array( $author_ids ) ) {
		$author_ids = [ $author_ids ];
	}

	$author_ids = array_map( 'absint', $author_ids );
	$author_ids = array_filter( $author_ids );

	// Validate authors.
	$validation = TMAuthors_Capabilities::validate_author_assignment( $post_id, $author_ids );

	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	// Get term IDs for these authors.
	$term_ids = [];

	foreach ( $author_ids as $user_id ) {
		$term = TMAuthors_Taxonomy::get_user_term( $user_id );

		if ( ! $term ) {
			// Create term if it doesn't exist.
			$term_id = TMAuthors_Taxonomy::create_or_update_user_term( $user_id );

			if ( is_wp_error( $term_id ) ) {
				continue;
			}

			$term_ids[] = $term_id;
		} else {
			$term_ids[] = $term->term_id;
		}
	}

	// Set object terms.
	$result = wp_set_object_terms( $post_id, $term_ids, TMAuthors_Taxonomy::TAXONOMY );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	/**
	 * Fires after authors are assigned to a post.
	 *
	 * @param int $post_id Post ID.
	 * @param array $author_ids Array of author user IDs.
	 */
	do_action( 'tmauthors_assigned', $post_id, $author_ids );

	return true;
}

/**
 * Add an author to a post.
 *
 * @param int $post_id Post ID.
 * @param int $user_id User ID to add.
 *
 * @return bool|WP_Error True on success, WP_Error on failure.
 * @since 1.0.0
 */
function tmauthors_add_post_author( $post_id, $user_id ) {

	$current_authors = tmauthors_get_post_authors( $post_id );
	$current_ids     = wp_list_pluck( $current_authors, 'ID' );

	if ( in_array( $user_id, $current_ids, true ) ) {
		return true; // Already an author.
	}

	$current_ids[] = $user_id;

	return tmauthors_set_post_authors( $post_id, $current_ids );
}

/**
 * Remove an author from a post.
 *
 * @param int $post_id Post ID.
 * @param int $user_id User ID to remove.
 *
 * @return bool|WP_Error True on success, WP_Error on failure.
 * @since 1.0.0
 */
function tmauthors_remove_post_author( $post_id, $user_id ) {

	$current_authors = tmauthors_get_post_authors( $post_id );
	$current_ids     = wp_list_pluck( $current_authors, 'ID' );

	$current_ids = array_diff( $current_ids, [ $user_id ] );

	if ( empty( $current_ids ) ) {
		return new WP_Error(
			'no_authors',
			__( 'Post must have at least one author.', 'themeruby-multi-authors' )
		);
	}

	return tmauthors_set_post_authors( $post_id, $current_ids );
}

/**
 * Get all posts by an author.
 *
 * @param int $user_id User ID.
 * @param array $args Optional. WP_Query arguments.
 *
 * @return WP_Query Query object.
 * @since 1.0.0
 */
function tmauthors_get_author_posts( $user_id, $args = [] ) {
	return TMAuthors_Display::get_author_posts( $user_id, $args );
}

/**
 * Get author post count.
 *
 * @param int $user_id User ID.
 * @param string $post_type Optional. Post type. Default 'post'.
 *
 * @return int Post count.
 * @since 1.0.0
 */
function tmauthors_get_author_post_count( $user_id, $post_type = 'post' ) {
	return TMAuthors_Display::get_author_post_count( $user_id, $post_type );
}

/**
 * Get author names as string.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 * @param string $separator Optional. Separator between names. Default ', '.
 *
 * @return string Author names separated by separator.
 * @since 1.0.0
 */
function tmauthors_get_author_names( $post_id = 0, $separator = ', ' ) {
	return TMAuthors_Display::get_author_names( $post_id, $separator );
}

/**
 * Get first author.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 *
 * @return WP_User|false First author user object or false if none.
 * @since 1.0.0
 */
function tmauthors_get_first_author( $post_id = 0 ) {
	return TMAuthors_Display::get_first_author( $post_id );
}

/**
 * Get assignable authors.
 *
 * @param array $args Optional. Get_users arguments.
 *
 * @return array Array of WP_User objects.
 * @since 1.0.0
 */
function tmauthors_get_assignable_authors( $args = [] ) {
	return TMAuthors_Capabilities::get_assignable_authors( $args );
}

/**
 * Display author box.
 *
 * Shows a formatted box with author avatars, names, and bios.
 *
 * @param int $post_id Optional. Post ID. Defaults to current post.
 * @param bool $echo Optional. Whether to echo or return. Default true.
 *
 * @return string|void Author box HTML or void if echo is true.
 * @since 1.0.0
 */
function tmauthors_box( $post_id = 0, $echo = true ) {
	return TMAuthors_Display::render_author_box( $post_id, $echo );
}
