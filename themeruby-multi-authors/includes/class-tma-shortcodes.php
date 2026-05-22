<?php
/**
 * Shortcodes
 *
 * Handles shortcode functionality for ThemeRuby Multi Authors.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Shortcodes class.
 *
 * @since 1.0.0
 */
class TMAuthors_Shortcodes {
	/**
	 * Initialize shortcodes.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Register shortcodes
		add_shortcode( 'tmauthors', [ __CLASS__, 'authors_shortcode' ] );
		add_shortcode( 'tmauthors_box', [ __CLASS__, 'author_box_shortcode' ] );
		add_shortcode( 'tmauthors_count', [ __CLASS__, 'author_count_shortcode' ] );
		add_shortcode( 'tmauthors_list', [ __CLASS__, 'author_list_shortcode' ] );
		add_shortcode( 'tmauthors_avatars', [ __CLASS__, 'author_avatars_shortcode' ] );
	}

	/**
	 * Authors shortcode - Display formatted list of authors.
	 *
	 * Usage: [tmauthors]
	 *        [tmauthors post_id="123" separator=" & " link="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Formatted authors HTML.
	 * @since 1.0.0
	 */
	public static function authors_shortcode( $atts ) {
		$atts = shortcode_atts(
				[
						'post_id'   => get_the_ID(),
						'separator' => ', ',
						'link'      => 'true',
						'before'    => '',
						'after'     => '',
				],
				$atts,
				'tmauthors'
		);

		// Load style
		wp_enqueue_style( 'tmauthors' );

		// Convert string boolean to actual boolean
		$link = filter_var( $atts['link'], FILTER_VALIDATE_BOOLEAN );

		if ( ! $atts['post_id'] ) {
			return '';
		}

		$authors = tmauthors_get_post_authors( absint( $atts['post_id'] ) );

		if ( empty( $authors ) ) {
			return '';
		}

		$output = wp_kses( $atts['before'], TMAuthors_Display::$allowed_html );

		foreach ( $authors as $index => $author ) {
			if ( $index > 0 ) {
				$output .= wp_kses( $atts['separator'], TMAuthors_Display::$allowed_html );
			}

			if ( $link ) {
				$output .= '<a href="' . esc_url( get_author_posts_url( $author->ID ) ) . '" class="tma-author-link">';
				$output .= esc_html( $author->display_name );
				$output .= '</a>';
			} else {
				$output .= '<span class="tma-author-name">' . esc_html( $author->display_name ) . '</span>';
			}
		}

		$output .= wp_kses( $atts['after'], TMAuthors_Display::$allowed_html );

		/**
		 * Filter the authors shortcode output.
		 *
		 * @param string $output The HTML output.
		 * @param array $authors Array of author objects.
		 * @param array $atts Shortcode attributes.
		 */
		return apply_filters( 'tmauthors_shortcode_output', $output, $authors, $atts );
	}

	/**
	 * Author box shortcode - Display complete author box with avatars and bios.
	 *
	 * Usage: [tmauthors_box]
	 *        [tmauthors_box post_id="123" show_avatar="true" show_bio="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Author box HTML.
	 * @since 1.0.0
	 */
	public static function author_box_shortcode( $atts ) {
		$atts = shortcode_atts(
				[
						'post_id'     => get_the_ID(),
						'show_avatar' => 'true',
						'show_bio'    => 'true',
						'avatar_size' => '120',
				],
				$atts,
				'tmauthors_box'
		);

		if ( ! $atts['post_id'] ) {
			return '';
		}

		$show_avatar = filter_var( $atts['show_avatar'], FILTER_VALIDATE_BOOLEAN );
		$show_bio    = filter_var( $atts['show_bio'], FILTER_VALIDATE_BOOLEAN );
		$avatar_size = intval( $atts['avatar_size'] );

		$authors = tmauthors_get_post_authors( absint( $atts['post_id'] ) );
		$output  = TMAuthors_Display::render_author_box( $atts['post_id'], false, $show_avatar, $show_bio, $avatar_size );

		/**
		 * Filter the author box shortcode output.
		 *
		 * @param string $output The HTML output.
		 * @param array $authors Array of author objects.
		 * @param array $atts Shortcode attributes.
		 */
		return apply_filters( 'tmauthors_box_shortcode_output', $output, $authors, $atts );
	}

	/**
	 * Author count shortcode - Display number of authors.
	 *
	 * Usage: [tmauthors_count]
	 *        [tmauthors_count post_id="123" text="Written by %d authors"]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Author count HTML.
	 * @since 1.0.0
	 */
	public static function author_count_shortcode( $atts ) {
		$atts = shortcode_atts(
				[
						'post_id' => get_the_ID(),
						'text'    => '',
				],
				$atts,
				'tmauthors_count'
		);

		if ( ! $atts['post_id'] ) {
			return '';
		}

		// Load style
		wp_enqueue_style( 'tmauthors' );

		$authors = tmauthors_get_post_authors( absint( $atts['post_id'] ) );
		$count   = count( $authors );

		if ( ! empty( $atts['text'] ) ) {
			$text = sprintf( $atts['text'], $count );
		} else {
			$text = sprintf(
			/* translators: %d: Number of authors */
					_n( '%d author', '%d authors', $count, 'themeruby-multi-authors' ),
					$count
			);
		}

		$output = '<span class="tma-author-count">' . esc_html( $text ) . '</span>';

		/**
		 * Filter the author count shortcode output.
		 *
		 * @param string $output The HTML output.
		 * @param int $count Number of authors.
		 * @param array $atts Shortcode attributes.
		 */
		return apply_filters( 'tmauthors_count_shortcode_output', $output, $count, $atts );
	}

	/**
	 * Author list shortcode - Display authors as unordered list.
	 *
	 * Usage: [tmauthors_list]
	 *        [tmauthors_list post_id="123" show_avatar="true" show_count="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Author list HTML.
	 * @since 1.0.0
	 */
	public static function author_list_shortcode( $atts ) {
		$atts = shortcode_atts(
				[
						'post_id'     => get_the_ID(),
						'show_avatar' => 'false',
						'show_count'  => 'false',
						'avatar_size' => '32',
				],
				$atts,
				'tmauthors_list'
		);

		if ( ! $atts['post_id'] ) {
			return '';
		}

		// Load style
		wp_enqueue_style( 'tmauthors' );

		$show_avatar = filter_var( $atts['show_avatar'], FILTER_VALIDATE_BOOLEAN );
		$show_count  = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );

		$authors = tmauthors_get_post_authors( absint( $atts['post_id'] ) );

		if ( empty( $authors ) ) {
			return '';
		}

		ob_start();
		?>
		<ul class="tma-author-list">
			<?php foreach ( $authors as $author ) : ?>
				<li class="tma-author-list-item">
					<?php if ( $show_avatar ) : ?>
						<span class="tma-author-list-avatar" style="max-width: <?php echo absint( $atts['avatar_size'] ) . 'px'; ?>">
							<?php echo get_avatar( $author->ID, absint( $atts['avatar_size'] ) ); ?>
						</span>
					<?php endif; ?>
					<a href="<?php echo esc_url( get_author_posts_url( $author->ID ) ); ?>" class="tma-author-list-link tma-author-link">
						<?php echo esc_html( $author->display_name ); ?>
					</a>
					<?php if ( $show_count ) : ?>
						<span class="tma-author-post-count">
							<?php
							$count = tmauthors_get_author_post_count( $author->ID );
							printf(
									'(%s)',
									esc_html(
											sprintf(
													/* translators: %d: Number of posts */
													_n( '%d post', '%d posts', $count, 'themeruby-multi-authors' ),
													number_format_i18n( $count )
											)
									)
							);
							?>
						</span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php

		$output = ob_get_clean();

		/**
		 * Filter the author list shortcode output.
		 *
		 * @param string $output The HTML output.
		 * @param array $authors Array of author objects.
		 * @param array $atts Shortcode attributes.
		 */
		return apply_filters( 'tmauthors_list_shortcode_output', $output, $authors, $atts );
	}

	/**
	 * Author avatars shortcode - Display only author avatars.
	 *
	 * Usage: [tmauthors_avatars]
	 *        [tmauthors_avatars post_id="123" size="60" link="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Author avatars HTML.
	 * @since 1.0.0
	 */
	public static function author_avatars_shortcode( $atts ) {
		$atts = shortcode_atts(
				[
						'post_id' => get_the_ID(),
						'size'    => '48',
						'link'    => 'true',
				],
				$atts,
				'tmauthors_avatars'
		);

		if ( ! $atts['post_id'] ) {
			return '';
		}

		$link = filter_var( $atts['link'], FILTER_VALIDATE_BOOLEAN );

		$authors = tmauthors_get_post_authors( absint( $atts['post_id'] ) );

		if ( empty( $authors ) ) {
			return '';
		}

		// Load style
		wp_enqueue_style( 'tmauthors' );

		ob_start();
		?>
		<div class="tma-author-avatars">
			<?php foreach ( $authors as $author ) : ?>
				<span class="tma-author-avatar-item">
					<?php if ( $link ) : ?>
						<a href="<?php echo esc_url( get_author_posts_url( $author->ID ) ); ?>" title="<?php echo esc_attr( $author->display_name ); ?>" class="tma-avatar-link">
							<?php echo get_avatar( $author->ID, absint( $atts['size'] ) ); ?>
						</a>
					<?php else : ?>
						<?php echo get_avatar( $author->ID, absint( $atts['size'] ) ); ?>
					<?php endif; ?>
				</span>
			<?php endforeach; ?>
		</div>
		<?php

		$output = ob_get_clean();

		/**
		 * Filter the author avatars shortcode output.
		 *
		 * @param string $output The HTML output.
		 * @param array $authors Array of author objects.
		 * @param array $atts Shortcode attributes.
		 */
		return apply_filters( 'tmauthors_avatars_shortcode_output', $output, $authors, $atts );
	}
}
