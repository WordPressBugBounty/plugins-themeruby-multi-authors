<?php
/**
 * Display Functions
 *
 * Handles frontend display of authors and template tags.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Display class.
 *
 * @since 1.0.0
 */
class TMAuthors_Display {

	/**
	 * Allowed HTML tags for output.
	 *
	 * @var array
	 */
	public static $allowed_html = [
		'span'   => [ 'class' => true ],
		'strong' => [],
		'div'    => [],
		'a'      => [
			'href'   => true,
			'title'  => true,
			'class'  => true,
			'id'     => true,
			'rel'    => true,
			'target' => true,
		],
		'em'     => [],
		'b'      => [],
		'i'      => [],
	];

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {

		if ( get_option( 'tmauthors_author_box', 0 ) ) {
			add_filter( 'the_content', [ __CLASS__, 'maybe_add_author_box' ] );
		}

		// Override core count_user_posts() function.
		add_filter( 'get_usernumposts', [ __CLASS__, 'override_user_post_count' ], 10, 4 );
	}

	/**
	 * Display formatted authors.
	 *
	 * @param int $post_id Post ID. Defaults to current post.
	 * @param array $args Optional. Display arguments.
	 *
	 * @return string|void Formatted authors HTML or void if echo is true.
	 * @since 1.0.0
	 */
	public static function the_authors( $post_id = 0, $args = [] ) {
		$defaults = [
			'before'    => '',
			'after'     => '',
			'separator' => ', ',
			'link'      => true,
			'echo'      => true,
		];

		$args = wp_parse_args( $args, $defaults );
		$args = self::validate_author_shortcode_atts( $args );

		$authors = self::get_post_authors( $post_id );

		if ( empty( $authors ) ) {
			return '';
		}

		/**
		 * Fires before authors are displayed.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'tmauthors_before_authors_display', $post_id );

		$output = self::format_authors( $authors, $args );

		/**
		 * Fires after authors are displayed.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'tmauthors_after_authors_display', $post_id );

		if ( $args['echo'] ) {
			echo wp_kses( $output, self::$allowed_html );

			return;
		}

		return wp_kses( $output, self::$allowed_html );
	}

	/**
	 * Validate and sanitize shortcode attributes for author display.
	 *
	 * @param array $atts User-supplied shortcode attributes.
	 *
	 * @return array Sanitized and validated attributes.
	 */
	public static function validate_author_shortcode_atts( $atts ) {
		$defaults = [
			'before'    => '',
			'after'     => '',
			'separator' => ', ',
			'link'      => true,
			'echo'      => true,
		];

		// Merge defaults and user attributes
		$atts = shortcode_atts( $defaults, $atts, 'tmauthors' );

		return [
			'before'    => wp_kses( $atts['before'], self::$allowed_html ),
			'after'     => wp_kses( $atts['after'], self::$allowed_html ),
			'separator' => sanitize_text_field( $atts['separator'] ),
			'link'      => filter_var( $atts['link'], FILTER_VALIDATE_BOOLEAN ),
			'echo'      => filter_var( $atts['echo'], FILTER_VALIDATE_BOOLEAN ),
		];
	}

	/**
	 * Get post authors.
	 *
	 * @param int $post_id Post ID. Defaults to current post.
	 *
	 * @return array Array of WP_User objects.
	 * @since 1.0.0
	 */
	public static function get_post_authors( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return [];
		}

		$post_id = (int) $post_id;

		// Try to get from cache first if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'post_authors_' . $post_id;
			$cache_group = 'tmauthors_post_authors';
			$authors     = wp_cache_get( $cache_key, $cache_group );

			if ( false !== $authors ) {
				return $authors;
			}
		}

		// Get terms for this post.
		$terms = wp_get_object_terms( $post_id, TMAuthors_Taxonomy::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Fallback to post_author.
			$post = get_post( $post_id );
			if ( $post && $post->post_author ) {
				$user = get_user_by( 'id', $post->post_author );

				$authors = $user ? [ $user ] : [];
			} else {
				$authors = [];
			}

			// Cache the result if caching is enabled.
			if ( self::is_cache_enabled() ) {
				$cache_key   = 'post_authors_' . $post_id;
				$cache_group = 'tmauthors_post_authors';
				wp_cache_set( $cache_key, $authors, $cache_group, 3600 );
			}

			return $authors;
		}

		$authors = [];

		foreach ( $terms as $term ) {
			$user_id = TMAuthors_Taxonomy::get_term_user_id( $term );
			if ( $user_id ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user ) {
					$authors[] = $user;
				}
			}
		}

		/**
		 * Filter post authors.
		 *
		 * @param array $authors Array of WP_User objects.
		 * @param int $post_id Post ID.
		 */
		$authors = apply_filters( 'tmauthors_post_authors', $authors, $post_id );

		// Cache the result for 1 hour if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'post_authors_' . $post_id;
			$cache_group = 'tmauthors_post_authors';
			wp_cache_set( $cache_key, $authors, $cache_group, 3600 );
		}

		return $authors;
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
	 * Format authors for display.
	 *
	 * @param array $authors Array of WP_User objects.
	 * @param array $args Display arguments.
	 *
	 * @return string Formatted authors HTML.
	 * @since 1.0.0
	 */
	private static function format_authors( $authors, $args ) {
		$author_links = [];

		foreach ( $authors as $author ) {
			if ( $args['link'] ) {
				$link = self::get_author_link( $author );
			} else {
				$link = esc_html( $author->display_name );
			}

			/**
			 * Filter individual author link.
			 *
			 * @param string $link Author link HTML.
			 * @param WP_User $author User object.
			 */
			$author_links[] = apply_filters( 'tmauthors_link', $link, $author );
		}

		$output = $args['before'] . implode( $args['separator'], $author_links ) . $args['after'];

		/**
		 * Filter authors output.
		 *
		 * @param string $output Formatted authors HTML.
		 * @param array $authors Array of WP_User objects.
		 * @param int $post_id Post ID.
		 * @param array $args Display arguments.
		 */
		return apply_filters( 'tmauthors_output', $output, $authors, get_the_ID(), $args );
	}

	/**
	 * Get author link.
	 *
	 * @param WP_User $author User object.
	 *
	 * @return string Author link HTML.
	 * @since 1.0.0
	 */
	private static function get_author_link( $author ) {
		$url = self::get_author_posts_url( $author->ID );

		return sprintf(
			'<a href="%s" class="author-link" rel="author">%s</a>',
			esc_url( $url ),
			esc_html( $author->display_name )
		);
	}

	/**
	 * Get author posts URL.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string Author archive URL.
	 * @since 1.0.0
	 */
	public static function get_author_posts_url( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return '';
		}

		/**
		 * Filter author posts URL.
		 *
		 * @param string $url Author archive URL.
		 * @param int $user_id User ID.
		 * @param WP_User $user User object.
		 */
		return apply_filters(
			'tmauthors_posts_url',
			get_author_posts_url( $user_id, $user->user_nicename ),
			$user_id,
			$user
		);
	}

	/**
	 * Check if post has multiple authors.
	 *
	 * @param int $post_id Post ID. Defaults to current post.
	 *
	 * @return bool True if post has multiple authors.
	 * @since 1.0.0
	 */
	public static function has_multiple_authors( $post_id = 0 ) {
		$authors = self::get_post_authors( $post_id );

		return count( $authors ) > 1;
	}

	/**
	 * Override core count_user_posts() function.
	 *
	 * Filters the number of posts a user has written to include both
	 * primary author posts and multi-author taxonomy posts.
	 *
	 * @param int $count Post count from core.
	 * @param int $user_id User ID.
	 * @param string|array $post_type Single post type or array of post types.
	 * @param bool $public_only Whether to only count public posts.
	 *
	 * @return int Accurate post count.
	 * @since 1.0.0
	 */
	public static function override_user_post_count( $count, $user_id, $post_type, $public_only ) {

		// If multiple post types, use the default count.
		if ( is_array( $post_type ) ) {
			if ( count( $post_type ) > 1 ) {
				return $count;

			} else {
				$post_type = $post_type[0];
			}
		}

		// Use our accurate count method.
		return self::get_author_post_count( $user_id, $post_type );
	}

	/**
	 * Get author posts count.
	 *
	 * Counts all posts where user is assigned as author (including multi-author posts).
	 *
	 * @param int $user_id User ID.
	 * @param string $post_type Optional. Post type. Default 'post'.
	 *
	 * @return int Post count.
	 * @since 1.0.0
	 */
	public static function get_author_post_count( $user_id, $post_type = 'post' ) {

		// Try to get from cache first if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'author_count_' . $user_id . '_' . $post_type;
			$cache_group = 'tmauthors_counts';
			$count       = wp_cache_get( $cache_key, $cache_group );

			if ( false !== $count ) {
				return $count;
			}
		}

		$count = TMAuthors_Author_Query::count_posts( $user_id, $post_type );

		// Cache the result for 1 hour if caching is enabled.
		if ( self::is_cache_enabled() ) {
			$cache_key   = 'author_count_' . $user_id . '_' . $post_type;
			$cache_group = 'tmauthors_counts';
			wp_cache_set( $cache_key, $count, $cache_group, 3600 );
		}

		return $count;
	}

	/**
	 * Get posts by author.
	 *
	 * @param int $user_id User ID.
	 * @param array $args Optional. WP_Query arguments.
	 *
	 * @return WP_Query Query object.
	 * @since 1.0.0
	 */
	public static function get_author_posts( $user_id, $args = [] ) {

		$term = TMAuthors_Taxonomy::get_user_term( $user_id );

		if ( ! $term ) {
			return new WP_Query( [ 'post__in' => [ 0 ] ] );
		}

		$defaults = [
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'tax_query'      => [
				[
					'taxonomy' => TMAuthors_Taxonomy::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
		];

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filter author posts query args.
		 *
		 * @param array $args Query arguments.
		 * @param int $user_id User ID.
		 */
		$args = apply_filters( 'tmauthors_posts_args', $args, $user_id );

		return new WP_Query( $args );
	}

	/**
	 * Get author names as string.
	 *
	 * @param int $post_id Post ID. Defaults to current post.
	 * @param string $separator Optional. Separator between names. Default ', '.
	 *
	 * @return string Author names separated by separator.
	 * @since 1.0.0
	 */
	public static function get_author_names( $post_id = 0, $separator = ', ' ) {
		$authors = self::get_post_authors( $post_id );

		if ( empty( $authors ) ) {
			return '';
		}

		$names = wp_list_pluck( $authors, 'display_name' );

		return implode( $separator, $names );
	}

	/**
	 * Get first author.
	 *
	 * @param int $post_id Post ID. Defaults to current post.
	 *
	 * @return WP_User|false First author user object or false if none.
	 * @since 1.0.0
	 */
	public static function get_first_author( $post_id = 0 ) {
		$authors = self::get_post_authors( $post_id );

		return ! empty( $authors ) ? $authors[0] : false;
	}

	/**
	 * Maybe add author box to content.
	 *
	 * @param string $content Post content.
	 *
	 * @return string Modified content.
	 * @since 1.0.0
	 */
	public static function maybe_add_author_box( $content ) {
		// Only display on singular post pages.
		if ( ! is_singular() || ! is_main_query() ) {
			return $content;
		}

		// Check if this post type is supported.
		$supported_post_types = get_option( 'tmauthors_supported_post_types', [ 'post' ] );
		if ( ! in_array( get_post_type(), $supported_post_types, true ) ) {
			return $content;
		}

		// Get author box HTML.
		$author_box = self::render_author_box( get_the_ID(), false );

		// Append to content.
		return $content . $author_box;
	}

	/**
	 * Render author box.
	 *
	 * @param int $post_id
	 * @param bool $echo
	 * @param bool $show_avatar
	 * @param bool $bio
	 * @param int $avatar_size
	 *
	 * @return string|void Author box HTML or void if echo is true.
	 * @since 1.0.0
	 */
	public static function render_author_box( $post_id = 0, $echo = true, $show_avatar = true, $bio = true, $avatar_size = 80 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '';
		}

		$authors = self::get_post_authors( $post_id );

		if ( empty( $authors ) ) {
			return '';
		}

		// Load style
		wp_enqueue_style( 'tmauthors' );

		// Start building output as a string
		$output = '<div class="tma-author-box">';

		$output .= '<div class="tma-author-box-title">';
		$output .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="currentColor" fill-rule="evenodd">';
		$output .= '<path d="M15.867 14.881a4.941 4.941 0 1 0-4.941-4.94 4.946 4.946 0 0 0 4.941 4.94zm0-7.881a2.941 2.941 0 1 1-2.941 2.941 2.945 2.945 0 0 1 2.941-2.941z" />';
		$output .= '<path d="M6.533 17.785a3.8 3.8 0 1 0-3.8-3.8 3.8 3.8 0 0 0 3.8 3.8zm0-5.6a1.8 1.8 0 1 1-1.8 1.8 1.8 1.8 0 0 1 1.8-1.8z" />';
		$output .= '<path d="M25.2 17.785a3.8 3.8 0 1 0-3.8-3.8 3.8 3.8 0 0 0 3.8 3.8zm0-5.6a1.8 1.8 0 1 1-1.8 1.8 1.8 1.8 0 0 1 1.8-1.8z" />';
		$output .= '<path d="M25.333 19.791a5.269 5.269 0 0 0-2.506.653 8.237 8.237 0 0 0-6.827-3.837 8.237 8.237 0 0 0-6.827 3.837 5.269 5.269 0 0 0-2.506-.653 5.963 5.963 0 0 0-5.667 6.209 1 1 0 0 0 2 0 3.971 3.971 0 0 1 3.667-4.209 3.272 3.272 0 0 1 1.577.419 10.085 10.085 0 0 0-.711 3.732 1 1 0 0 0 2 0c0-4.045 2.9-7.335 6.467-7.335s6.467 3.29 6.467 7.335a1 1 0 0 0 2 0 10.085 10.085 0 0 0-.711-3.732 3.272 3.272 0 0 1 1.577-.419 3.971 3.971 0 0 1 3.667 4.209 1 1 0 0 0 2 0 5.963 5.963 0 0 0-5.667-6.209z" />';
		$output .= '</svg>';
		$output .= '<span>' . esc_html( _n( 'Author', 'Authors', count( $authors ), 'themeruby-multi-authors' ) ) . '</span>';
		$output .= '</div>'; // .tma-author-box-title

		$output .= '<div class="tma-author-box-authors">';

		foreach ( $authors as $author ) {
			$output .= '<div class="tma-author-box-item">';

			if ( $show_avatar ) {
				$output .= '<div class="tma-author-avatar" style="max-width:' . intval( $avatar_size ) . 'px;">';
				$output .= get_avatar( $author->ID, (int) $avatar_size );
				$output .= '</div>';
			}

			$output .= '<div class="tma-author-info">';
			$output .= '<h4 class="tma-author-name">';
			$output .= '<a href="' . esc_url( self::get_author_posts_url( $author->ID ) ) . '">';
			$output .= esc_html( $author->display_name );
			$output .= '</a>';
			$output .= '</h4>';

			if ( ! empty( $author->description ) && $bio ) {
				$output .= '<div class="tma-author-bio">';
				$output .= wpautop( $author->description );
				$output .= '</div>';
			}

			$output .= '</div>'; // .tma-author-info
			$output .= '</div>'; // .tma-author-box-item
		}

		$output .= '</div>'; // .tma-author-box-authors
		$output .= '</div>'; // .tma-author-box

		// Apply filter
		$output = apply_filters( 'tmauthors_box_output', $output, $post_id, $authors );

		if ( $echo ) {
			echo wp_kses_post( $output );

			return;
		}

		return wp_kses_post( $output );
	}
}
