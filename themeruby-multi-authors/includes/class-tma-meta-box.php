<?php
/**
 * Unified Meta Box for Both Classic and Block Editor
 *
 * Provides multi-author selection UI.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TMAuthors_Meta_Box class.
 *
 * @since 1.0.0
 */
class TMAuthors_Meta_Box {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {

		// Add meta box
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post', [ __CLASS__, 'save_meta_box' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_tmauthors_search_authors', [ __CLASS__, 'ajax_search_authors' ] );
	}

	/**
	 * Add meta box.
	 *
	 * @since 1.0.0
	 */
	public static function add_meta_box() {
		$post_types = self::get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
					'tmauthors_box',
					esc_html__( 'RubyAuthors', 'themeruby-multi-authors' ),
					[ __CLASS__, 'render_meta_box' ],
					$post_type,
					'side',
					'default'
			);
		}
	}

	/**
	 * Get supported post types.
	 *
	 * @return array Array of post type names.
	 * @since 1.0.0
	 */
	private static function get_supported_post_types() {
		/**
		 * Filter supported post types for multi-authors.
		 *
		 * @param array $post_types Array of post type names.
		 */
		$supported_post_types = get_option( 'tmauthors_supported_post_types', [ 'post' ] );
	      return apply_filters( 'tmauthors_supported_post_types', $supported_post_types );
	}

	/**
	 * Render meta box.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @since 1.0.0
	 */
	public static function render_meta_box( $post ) {
		// Check permissions.
		if ( ! TMAuthors_Capabilities::can_assign_authors() ) {
			echo '<p>' . esc_html__( 'You do not have permission to assign authors.', 'themeruby-multi-authors' ) . '</p>';

			return;
		}

		// Add nonce field.
		wp_nonce_field( 'tmauthors_save_authors', 'tmauthors_nonce' );

		// Get current primary author (post_author).
		$primary_author_id = (int) $post->post_author;

		// Get all current authors from taxonomy.
		$all_current_authors = self::get_post_author_ids( $post->ID );

		// Get additional authors (exclude primary author from display).
		$additional_author_ids = array_diff( $all_current_authors, [ $primary_author_id ] );

		// Get additional author user objects.
		$additional_authors = [];
		foreach ( $additional_author_ids as $author_id ) {
			$author = get_user_by( 'id', $author_id );
			if ( $author ) {
				$additional_authors[] = $author;
			}
		}

		?>
		<div class="tmauthors-meta-box">
			<div class="tmauthors-coauthors-wrapper">
				<label class="tmauthors-coauthors-label">
					<?php esc_html_e( 'Additional Authors', 'themeruby-multi-authors' ); ?>
				</label>
				<!-- Help Text -->
				<p class="howto" id="tmauthors-search-desc">
					<?php esc_html_e( 'Search to add additional co-authors to this post.', 'themeruby-multi-authors' ); ?>
				</p>
				<!-- Selected Authors Display (Tags) -->
				<div class="tmauthors-selected-authors tagchecklist" id="tmauthors-selected-authors">
					<?php foreach ( $additional_authors as $author ) : ?>
						<span class="tmauthors-author-tag" data-author-id="<?php echo esc_attr( $author->ID ); ?>">
							<button type="button" id="tmauthors-remove-<?php echo esc_attr( $author->ID ); ?>" class="ntdelbutton tmauthors-remove-author">
								<span class="remove-tag-icon" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Remove', 'themeruby-multi-authors' ); ?></span>
							</button>
							&nbsp;<?php echo esc_html( $author->display_name ); ?>
							<input type="hidden" name="tmauthors[]" value="<?php echo esc_attr( $author->ID ); ?>" />
						</span>
					<?php endforeach; ?>
				</div>
				<!-- Search Input -->
				<div class="tmauthors-search-wrapper">
					<input
							type="text"
							id="tmauthors_search_input"
							class="tmauthors-search-input newtag form-input-tip"
							placeholder="<?php esc_attr_e( 'Search authors...', 'themeruby-multi-authors' ); ?>"
							autocomplete="off"
							aria-describedby="tmauthors-search-desc"
					/>
					<!-- Suggestions Dropdown -->
					<div class="tmauthors-suggestions" id="tmauthors-suggestions"></div>
				</div>
			</div>
			<!-- Hidden field for primary author ID (used to filter on save) -->
			<input type="hidden" id="tmauthors-primary-author" value="<?php echo esc_attr( $primary_author_id ); ?>" />
		</div>
		<?php
	}

	/**
	 * Get post author IDs.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Array of author user IDs.
	 * @since 1.0.0
	 */
	private static function get_post_author_ids( $post_id ) {
		$terms = wp_get_object_terms( $post_id, TMAuthors_Taxonomy::TAXONOMY );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			// Fallback to post_author if no multi-authors set.
			$post = get_post( $post_id );

			return $post && $post->post_author ? [ (int) $post->post_author ] : [];
		}

		$author_ids = [];

		foreach ( $terms as $term ) {
			$user_id = TMAuthors_Taxonomy::get_term_user_id( $term );
			if ( $user_id ) {
				$author_ids[] = $user_id;
			}
		}

		return $author_ids;
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post Post object.
	 *
	 * @since 1.0.0
	 */
	public static function save_meta_box( $post_id, $post ) {

		// Check if our nonce is set.
		if ( ! isset( $_POST['tmauthors_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tmauthors_nonce'] ) ), 'tmauthors_save_authors' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if post type is supported.
		if ( ! in_array( $post->post_type, self::get_supported_post_types(), true ) ) {
			return;
		}

		// Get primary author
		$primary_author_id = (int) $post->post_author;

		// Ensure we have a valid primary author.
		if ( empty( $primary_author_id ) ) {
			$primary_author_id = (int) $post->post_author;
		}

		// Get submitted additional author IDs from our plugin.
		$submitted_additional_ids = isset( $_POST['tmauthors'] ) && is_array( $_POST['tmauthors'] )
				? array_map( 'absint', $_POST['tmauthors'] )
				: [];

		// Filter out primary author from additional authors (if user selected it).
		$additional_author_ids = array_diff( $submitted_additional_ids, [ $primary_author_id ] );

		// Combine primary author with additional authors (primary first).
		$author_ids = array_merge( [ $primary_author_id ], array_values( $additional_author_ids ) );

		// Remove duplicates and re-index.
		$author_ids = array_values( array_unique( $author_ids ) );

		// Validate authors.
		$validation = TMAuthors_Capabilities::validate_author_assignment( $post_id, $author_ids );

		if ( is_wp_error( $validation ) ) {
			return;
		}

		// Save authors to taxonomy.
		self::set_post_authors( $post_id, $author_ids );
	}

	/**
	 * Set authors for a post.
	 *
	 * Creates taxonomy terms on-demand if they don't exist.
	 * This eliminates the need to pre-sync all users to taxonomy terms.
	 *
	 * @param int $post_id Post ID.
	 * @param array $author_ids Array of author user IDs.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @since 1.0.0
	 */
	private static function set_post_authors( $post_id, $author_ids ) {
		// Get term IDs for these authors (create on-demand if needed).
		$term_ids = [];

		foreach ( $author_ids as $user_id ) {
			$term = TMAuthors_Taxonomy::get_user_term( $user_id );

			if ( ! $term ) {
				// Create term on-demand (no pre-sync needed).
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
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_scripts( $hook ) {
		// Only load on post edit screens.
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, self::get_supported_post_types(), true ) ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
				'tmauthors-editor',
				TMAUTHORS_PLUGIN_URL . 'admin/css/tma-editor.css',
				[],
				TMAUTHORS_VERSION
		);

		// Enqueue JS.
		wp_enqueue_script(
				'tmauthors-meta-box',
				TMAUTHORS_PLUGIN_URL . 'admin/js/tma-meta-box.js',
				[ 'jquery' ],
				TMAUTHORS_VERSION,
				true
		);

		// Localize script.
		wp_localize_script(
				'tmauthors-meta-box',
				'tmAuthorsAdmin',
				[
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'tmauthors_search_authors' ),
						'i18n'    => [
								'noResults' => esc_html__( 'No authors found', 'themeruby-multi-authors' ),
								'remove'    => esc_html__( 'Remove', 'themeruby-multi-authors' ),
						],
				]
		);
	}

	/**
	 * AJAX search authors.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_search_authors() {

		check_ajax_referer( 'tmauthors_search_authors', 'nonce' );

		if ( ! TMAuthors_Capabilities::can_assign_authors() ) {
			wp_send_json_success( [] );

			wp_die();
		}

		$search        = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$exclude_param = isset( $_GET['exclude'] ) ? sanitize_text_field( wp_unslash( $_GET['exclude'] ) ) : '';

		// Parse excluded IDs.
		$exclude_ids = [];
		if ( ! empty( $exclude_param ) ) {
			$exclude_ids = array_map( 'absint', explode( ',', $exclude_param ) );
			$exclude_ids = array_filter( $exclude_ids );
		}

		$args = [
				'search'  => '*' . $search . '*',
				'number'  => 20,
				'orderby' => 'display_name',
				'order'   => 'ASC',
		];

		// Exclude authors if provided.
		if ( ! empty( $exclude_ids ) ) {
			$args['exclude'] = $exclude_ids;
		}

		$authors = TMAuthors_Capabilities::get_assignable_authors( $args );

		$results = [];

		foreach ( $authors as $author ) {
			// Double-check exclusion.
			if ( in_array( $author->ID, $exclude_ids, true ) ) {
				continue;
			}

			$results[] = [
					'id'   => $author->ID,
					'name' => $author->display_name,
					'text' => $author->display_name . ' (' . $author->user_login . ')',
			];
		}

		wp_send_json_success( $results );
	}
}
