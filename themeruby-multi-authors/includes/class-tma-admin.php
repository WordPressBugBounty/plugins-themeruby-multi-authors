<?php
/**
 * Admin class for ThemeRuby Multi Authors
 *
 * Handles admin interface and settings panel.
 *
 * @package ThemeRuby_Multi_Authors
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TMAuthors_Admin
 *
 * @since 1.0.0
 */
class TMAuthors_Admin {

	/**
	 * Menu ID returned from add_submenu_page.
	 *
	 * @var string
	 */
	public static $menu_id;

	/**
	 * Parent menu slug for admin integration.
	 *
	 * @var string
	 */
	private static $parent_slug = 'foxiz-admin';

	/**
	 * Initialize the admin.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 1000 );

		// AJAX handlers.
		add_action( 'wp_ajax_tmauthors_save_setting', [ __CLASS__, 'ajax_save_setting' ] );
		add_action( 'wp_ajax_tmauthors_save_post_types', [ __CLASS__, 'ajax_save_post_types' ] );
		add_action( 'wp_ajax_tmauthors_clear_cache', [ __CLASS__, 'ajax_clear_cache' ] );
	}

	/**
	 * Add admin menu page.
	 *
	 * If theme core is active, adds submenu under theme admin.
	 * Otherwise, adds submenu under Settings.
	 *
	 * @since 1.0.0
	 */
	public static function add_admin_menu() {

		// Check if theme core is active.
		if ( self::is_foxiz_core_active() ) {
			self::$menu_id = add_submenu_page(
					self::$parent_slug,
					esc_html__( 'ThemeRuby Multi Authors', 'themeruby-multi-authors' ),
					esc_html__( 'ThemeRuby Multi Authors', 'themeruby-multi-authors' ),
					'manage_options',
					'themeruby-multi-authors',
					[ __CLASS__, 'render_settings_page' ]
			);

			// Load assets only on our admin page.
			add_action( 'load-' . self::$menu_id, [ __CLASS__, 'load_page_assets' ] );
		} else {
			// Fallback: Add under Settings menu if theme core not active.
			self::$menu_id = add_options_page(
					esc_html__( 'ThemeRuby Multi Authors Settings', 'themeruby-multi-authors' ),
					esc_html__( 'ThemeRuby Multi Authors', 'themeruby-multi-authors' ),
					'manage_options',
					'themeruby-multi-authors',
					[ __CLASS__, 'render_settings_page' ]
			);

			// Load assets only on our admin page.
			add_action( 'load-' . self::$menu_id, [ __CLASS__, 'load_page_assets' ] );
		}
	}

	/**
	 * Check if theme core plugin is active.
	 *
	 * @return bool True if theme core is active.
	 * @since 1.0.0
	 */
	public static function is_foxiz_core_active() {
		// Check if theme core plugin is active.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check multiple ways to detect theme core.
		return is_plugin_active( 'foxiz-core/foxiz-core.php' ) ||
		       class_exists( 'Foxiz_Core' ) ||
		       defined( 'FOXIZ_CORE_PATH' );
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render_settings_page() {

		// Security check: Verify user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
					esc_html__( 'You do not have sufficient permissions to access this page.', 'themeruby-multi-authors' ),
					esc_html__( 'Access Denied', 'themeruby-multi-authors' ),
					[ 'response' => 403 ]
			);
		}

		// Render admin header if theme core is active.
		if ( self::is_foxiz_core_active() && class_exists( 'RB_ADMIN_CORE' ) ) {
			RB_ADMIN_CORE::get_instance()->header_template();
		}

		// Get current settings.
		$enable_archive_filtering       = get_option( 'tmauthors_archive_filtering', 1 );
		$enable_author_filtering        = get_option( 'tmauthors_author_filtering', 1 );
		$enable_author_name_filtering   = get_option( 'tmauthors_author_name_filtering', 1 );
		$enable_author_in_filtering     = get_option( 'tmauthors_author_in_filtering', 1 );
		$enable_author_not_in_filtering = get_option( 'tmauthors_author_not_in_filtering', 1 );
		$enable_seo_integration         = get_option( 'tmauthors_seo_integration', 1 );
		$supported_post_types           = get_option( 'tmauthors_supported_post_types', [ 'post' ] );
		$enable_author_box              = get_option( 'tmauthors_author_box', 0 );
		$enable_cache                   = get_option( 'tmauthors_cache', 1 );
		$load_css_header                = get_option( 'tmauthors_load_css_header', 1 );
		?>
		<div class="tma-admin-wrap">
			<!-- Header -->
			<div class="tma-admin-header">
				<h1><?php esc_html_e( 'ThemeRuby Multi Authors', 'themeruby-multi-authors' ); ?></h1>
				<p><?php esc_html_e( 'High-performance, developer-friendly plugin to assign multiple authors to posts. Fully compatible with WP_Query, REST API, block editor, and standard loops.', 'themeruby-multi-authors' ); ?></p>
				<div class="tma-tab-navigation">
					<button class="tma-tab-btn active" data-tab="settings">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Settings', 'themeruby-multi-authors' ); ?>
					</button>
					<button class="tma-tab-btn" data-tab="usage">
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Usage', 'themeruby-multi-authors' ); ?>
					</button>
				</div>
			</div>
			<!-- Settings Tab -->
			<div id="tma-tab-settings" class="tma-tab-content active">
				<!-- Post Type Settings -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Post Type Settings', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<!-- Supported Post Types -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label><?php esc_html_e( 'Supported Post Types', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Select which post types support multiple authors.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<div class="tma-post-types-container">
									<?php
									$all_post_types = get_post_types( [ 'public' => true ], 'objects' );

									$excluded_types = [
											'attachment',
											'revision',
											'nav_menu_item',
											'custom_css',
											'customize_changeset',
											'oembed_cache',
											'rb-etemplate',
											'user_request',
											'wp_block',
											'page',
											'wp_template',
											'wp_template_part',
											'wp_navigation',
											'elementor_library',
											'e-floating-buttons',
											'fl-builder-template',
											'ct_template',
											'wpcf7_contact_form',
											'product_variation',
											'shop_order',
											'shop_coupon',
											'shop_order_refund',
											'shop_subscription',
									];

									foreach ( $all_post_types as $post_type ) :
										if ( in_array( $post_type->name, $excluded_types, true ) ) {
											continue;
										}
										$is_checked = in_array( $post_type->name, $supported_post_types, true );
										?>
										<label class="tma-post-type-tag <?php echo $is_checked ? 'active' : ''; ?>">
											<input
													type="checkbox"
													class="tma-post-type-checkbox"
													value="<?php echo esc_attr( $post_type->name ); ?>"
													<?php checked( $is_checked ); ?>
											/>
											<span class="tma-post-type-label"><?php echo esc_html( $post_type->label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Archive Page Filtering -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Author Page Settings', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_archive_filtering"><?php esc_html_e( 'Include Additional Authors on Archive Pages', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'When enabled, author archive pages will display all posts where the user is either the primary author or an additional author. When disabled, only posts where the user is the primary author will be shown.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_archive_filtering" name="tmauthors_archive_filtering" value="1" <?php checked( $enable_archive_filtering, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- Custom Query Filtering Settings -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'WP_Query Settings', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<div class="tma-section-description">
							<p><?php esc_html_e( 'Allow ThemeRuby Multi Authors to integrate with WP_Query through filters. These settings enable you to display posts assigned to multiple authors in any theme or plugin.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<!-- Author Parameter Filtering -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_author_filtering"><?php esc_html_e( 'Author ID Parameter', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Enable filtering in custom queries using "author" parameter. Example: new WP_Query([\'author\' => 5])', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_author_filtering" name="tmauthors_author_filtering" value="1" <?php checked( $enable_author_filtering, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Author Name Parameter Filtering -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_author_name_filtering"><?php esc_html_e( 'Author Name Parameter', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Enable filtering in custom queries using "author_name" parameter. Example: new WP_Query([\'author_name\' => \'john\'])', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_author_name_filtering" name="tmauthors_author_name_filtering" value="1" <?php checked( $enable_author_name_filtering, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Author__in Parameter Filtering -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_author_in_filtering"><?php esc_html_e( 'Author__in Parameter', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Enable filtering in custom queries using "author__in" parameter. Example: new WP_Query([\'author__in\' => [5, 10, 15]])', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_author_in_filtering" name="tmauthors_author_in_filtering" value="1" <?php checked( $enable_author_in_filtering, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Author__not_in Parameter Filtering -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_author_not_in_filtering"><?php esc_html_e( 'Author__not_in Parameter', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Enable filtering in custom queries using "author__not_in" parameter. Example: new WP_Query([\'author__not_in\' => [5, 10]])', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_author_not_in_filtering" name="tmauthors_author_not_in_filtering" value="1" <?php checked( $enable_author_not_in_filtering, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- SEO Settings -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'SEO Integration', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<!-- SEO Integration -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_seo_integration"><?php esc_html_e( 'Enable SEO Integration', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Automatically integrate with SEO plugins for proper multi-author schema markup.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_seo_integration" name="tmauthors_seo_integration" value="1" <?php checked( $enable_seo_integration, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- Display Settings -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Display Settings', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<!-- Author Box -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_author_box"><?php esc_html_e( 'Show Author Box', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Automatically display an author box at the end of posts showing all authors with their avatars, names, and bios.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_author_box" name="tmauthors_author_box" value="1" <?php checked( $enable_author_box, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
					</div>
				</div>
				<!-- Performance Settings -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Performance Settings', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<!-- Load CSS in Header -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_load_css_header"><?php esc_html_e( 'Load CSS in Header', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Load shortcodes CSS in the header to prevent flash of unstyled content. Disable to load in footer for slightly better page load performance.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_load_css_header" name="tmauthors_load_css_header" value="1" <?php checked( $load_css_header, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Enable Cache -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label for="tmauthors_cache"><?php esc_html_e( 'Enable Author Data Caching', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Cache author data using object cache to reduce database queries.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<label class="tma-toggle-switch">
									<input type="checkbox" id="tmauthors_cache" name="tmauthors_cache" value="1" <?php checked( $enable_cache, 1 ); ?> />
									<span class="tma-toggle-slider"></span>
								</label>
							</div>
						</div>
						<!-- Clear Cache -->
						<div class="tma-setting-row">
							<div class="tma-setting-label">
								<div>
									<label><?php esc_html_e( 'Clear Author Cache', 'themeruby-multi-authors' ); ?></label>
									<span class="description"><?php esc_html_e( 'Manually clear all cached author data. Use this if you notice stale author information.', 'themeruby-multi-authors' ); ?></span>
								</div>
							</div>
							<div class="tma-setting-control">
								<button type="button" id="tmauthors_clear_cache" class="tma-action-btn danger">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Clear All Author Cache', 'themeruby-multi-authors' ); ?>
								</button>
								<span id="tmauthors_clear_cache_status" class="tma-status-message"></span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- Usage Tab -->
			<div id="tma-tab-usage" class="tma-tab-content">
				<!-- Shortcodes Section -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Shortcodes', 'themeruby-multi-authors' ); ?></h3>
						<p class="tma-section-intro"><?php esc_html_e( 'Use these shortcodes anywhere: in post content, widgets, page builders, etc.', 'themeruby-multi-authors' ); ?></p>
					</div>
					<div class="tma-section-body">
						<div class="tma-shortcodes-grid">
							<!-- Shortcode 1: tmauthors -->
							<div class="tma-shortcode-card">
								<div class="tma-shortcode-header">
									<h4><?php esc_html_e( 'Simple Author List', 'themeruby-multi-authors' ); ?></h4>
									<span class="tma-shortcode-badge">[tmauthors]</span>
								</div>
								<div class="tma-shortcode-description">
									<p><?php esc_html_e( 'Display author names as a simple list with links.', 'themeruby-multi-authors' ); ?></p>
								</div>
								<div class="tma-shortcode-examples">
									<strong><?php esc_html_e( 'Examples:', 'themeruby-multi-authors' ); ?></strong>
									<div class="tma-code-example mini">
										<code>[tmauthors]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: John Doe, Jane Smith', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors separator=" & "]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: John Doe & Jane Smith', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors before="By " link="false"]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: By John Doe, Jane Smith', 'themeruby-multi-authors' ); ?></span>
									</div>
								</div>
								<div class="tma-shortcode-params">
									<strong><?php esc_html_e( 'Parameters:', 'themeruby-multi-authors' ); ?></strong>
									<ul>
										<li><code>separator</code> - Text between names (default: <code>, </code>)</li>
										<li><code>link</code> - Link to archives (default: <code>true</code>)</li>
										<li><code>before</code> - HTML before list (default: <code>""</code>)</li>
										<li><code>after</code> - HTML after list (default: <code>""</code>)</li>
										<li><code>post_id</code> - Specific post ID (default: current post)</li>
									</ul>
								</div>
							</div>
							<!-- Shortcode 2: tmauthors_box -->
							<div class="tma-shortcode-card">
								<div class="tma-shortcode-header">
									<h4><?php esc_html_e( 'Complete Author Box', 'themeruby-multi-authors' ); ?></h4>
									<span class="tma-shortcode-badge">[tmauthors_box]</span>
								</div>
								<div class="tma-shortcode-description">
									<p><?php esc_html_e( 'Display a complete author box with avatars, names, bios, and links.', 'themeruby-multi-authors' ); ?></p>
								</div>
								<div class="tma-shortcode-examples">
									<strong><?php esc_html_e( 'Examples:', 'themeruby-multi-authors' ); ?></strong>
									<div class="tma-code-example mini">
										<code>[tmauthors_box]</code>
										<span class="tma-output"><?php esc_html_e( 'Full author box with avatars and bios', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_box show_bio="false"]</code>
										<span class="tma-output"><?php esc_html_e( 'Author box without biographies', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_box avatar_size="40"]</code>
										<span class="tma-output"><?php esc_html_e( 'Smaller avatar display', 'themeruby-multi-authors' ); ?></span>
									</div>
								</div>
								<div class="tma-shortcode-params">
									<strong><?php esc_html_e( 'Parameters:', 'themeruby-multi-authors' ); ?></strong>
									<ul>
										<li><code>show_avatar</code> - Display avatars (default: <code>true</code>)</li>
										<li><code>show_bio</code> - Display bios (default: <code>true</code>)</li>
										<li><code>avatar_size</code> - Avatar size in pixels (default: <code>120</code>)</li>
										<li><code>post_id</code> - Specific post ID (default: current post)</li>
									</ul>
								</div>
							</div>
							<!-- Shortcode 3: tmauthors_list -->
							<div class="tma-shortcode-card">
								<div class="tma-shortcode-header">
									<h4><?php esc_html_e( 'Author List (Bullets)', 'themeruby-multi-authors' ); ?></h4>
									<span class="tma-shortcode-badge">[tmauthors_list]</span>
								</div>
								<div class="tma-shortcode-description">
									<p><?php esc_html_e( 'Display authors as an unordered list with optional avatars and post counts.', 'themeruby-multi-authors' ); ?></p>
								</div>
								<div class="tma-shortcode-examples">
									<strong><?php esc_html_e( 'Examples:', 'themeruby-multi-authors' ); ?></strong>
									<div class="tma-code-example mini">
										<code>[tmauthors_list]</code>
										<span class="tma-output"><?php esc_html_e( 'Simple bulleted list', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_list show_avatar="true"]</code>
										<span class="tma-output"><?php esc_html_e( 'List with avatars', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_list show_count="true"]</code>
										<span class="tma-output"><?php esc_html_e( 'List with post counts', 'themeruby-multi-authors' ); ?></span>
									</div>
								</div>
								<div class="tma-shortcode-params">
									<strong><?php esc_html_e( 'Parameters:', 'themeruby-multi-authors' ); ?></strong>
									<ul>
										<li><code>show_avatar</code> - Show avatars (default: <code>false</code>)</li>
										<li><code>show_count</code> - Show post count (default: <code>false</code>)</li>
										<li><code>avatar_size</code> - Avatar size (default: <code>32</code>)</li>
										<li><code>post_id</code> - Specific post ID (default: current post)</li>
									</ul>
								</div>
							</div>
							<!-- Shortcode 4: tmauthors_avatars -->
							<div class="tma-shortcode-card">
								<div class="tma-shortcode-header">
									<h4><?php esc_html_e( 'Author Avatars Only', 'themeruby-multi-authors' ); ?></h4>
									<span class="tma-shortcode-badge">[tmauthors_avatars]</span>
								</div>
								<div class="tma-shortcode-description">
									<p><?php esc_html_e( 'Display only author profile pictures in a row.', 'themeruby-multi-authors' ); ?></p>
								</div>
								<div class="tma-shortcode-examples">
									<strong><?php esc_html_e( 'Examples:', 'themeruby-multi-authors' ); ?></strong>
									<div class="tma-code-example mini">
										<code>[tmauthors_avatars]</code>
										<span class="tma-output"><?php esc_html_e( 'Row of linked avatars', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_avatars size="80"]</code>
										<span class="tma-output"><?php esc_html_e( 'Larger avatars', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_avatars link="false"]</code>
										<span class="tma-output"><?php esc_html_e( 'Avatars without links', 'themeruby-multi-authors' ); ?></span>
									</div>
								</div>
								<div class="tma-shortcode-params">
									<strong><?php esc_html_e( 'Parameters:', 'themeruby-multi-authors' ); ?></strong>
									<ul>
										<li><code>size</code> - Avatar size in pixels (default: <code>48</code>)</li>
										<li><code>link</code> - Link to archives (default: <code>true</code>)</li>
										<li><code>post_id</code> - Specific post ID (default: current post)</li>
									</ul>
								</div>
							</div>
							<!-- Shortcode 5: tmauthors_count -->
							<div class="tma-shortcode-card">
								<div class="tma-shortcode-header">
									<h4><?php esc_html_e( 'Author Count', 'themeruby-multi-authors' ); ?></h4>
									<span class="tma-shortcode-badge">[tmauthors_count]</span>
								</div>
								<div class="tma-shortcode-description">
									<p><?php esc_html_e( 'Display the number of authors for a post.', 'themeruby-multi-authors' ); ?></p>
								</div>
								<div class="tma-shortcode-examples">
									<strong><?php esc_html_e( 'Examples:', 'themeruby-multi-authors' ); ?></strong>
									<div class="tma-code-example mini">
										<code>[tmauthors_count]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: 3 authors', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_count text="Written by %d people"]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: Written by 3 people', 'themeruby-multi-authors' ); ?></span>
									</div>
									<div class="tma-code-example mini">
										<code>[tmauthors_count text="%d co-authors"]</code>
										<span class="tma-output"><?php esc_html_e( 'Output: 3 co-authors', 'themeruby-multi-authors' ); ?></span>
									</div>
								</div>
								<div class="tma-shortcode-params">
									<strong><?php esc_html_e( 'Parameters:', 'themeruby-multi-authors' ); ?></strong>
									<ul>
										<li><code>text</code> - Custom text with %d placeholder (default: auto)</li>
										<li><code>post_id</code> - Specific post ID (default: current post)</li>
									</ul>
								</div>
							</div>
							<!-- Where to Use Shortcodes -->
							<div class="tma-usage-tip">
								<h4><?php esc_html_e( '📌 Where to Use Shortcodes', 'themeruby-multi-authors' ); ?></h4>
								<ul>
									<li><strong><?php esc_html_e( 'Post/Page Content', 'themeruby-multi-authors' ); ?></strong> - <?php esc_html_e( 'Add directly in Block or Classic Editor', 'themeruby-multi-authors' ); ?></li>
									<li><strong><?php esc_html_e( 'Widgets', 'themeruby-multi-authors' ); ?></strong> - <?php esc_html_e( 'Use in Text or HTML widgets (Appearance > Widgets)', 'themeruby-multi-authors' ); ?></li>
									<li><strong><?php esc_html_e( 'Page Builders', 'themeruby-multi-authors' ); ?></strong> - <?php esc_html_e( 'Works with popular page builders.', 'themeruby-multi-authors' ); ?></li>
									<li><strong><?php esc_html_e( 'Theme Templates', 'themeruby-multi-authors' ); ?></strong> - <?php esc_html_e( 'Use do_shortcode() in PHP files', 'themeruby-multi-authors' ); ?></li>
								</ul>
							</div>
							<!-- Common Use Cases -->
							<div class="tma-usage-examples">
								<h4><?php esc_html_e( '💡 Common Use Cases', 'themeruby-multi-authors' ); ?></h4>
								<div class="tma-use-case">
									<strong><?php esc_html_e( 'Article Byline:', 'themeruby-multi-authors' ); ?></strong>
									<code>By [tmauthors separator=" & "] | [tmauthors_count]</code>
								</div>
								<div class="tma-use-case">
									<strong><?php esc_html_e( 'Sidebar Widget:', 'themeruby-multi-authors' ); ?></strong>
									<code>&lt;h4&gt;Written By&lt;/h4&gt; [tmauthors_list show_avatar="true"]</code>
								</div>
								<div class="tma-use-case">
									<strong><?php esc_html_e( 'Footer Credits:', 'themeruby-multi-authors' ); ?></strong>
									<code>[tmauthors_box show_bio="false" avatar_size="60"]</code>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Template Tags Section -->
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Template Tags (For Developers)', 'themeruby-multi-authors' ); ?></h3>
						<p class="tma-section-intro"><?php esc_html_e( 'Use these PHP functions for custom integration.', 'themeruby-multi-authors' ); ?></p>
					</div>
					<div class="tma-section-body">
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Display Authors', 'themeruby-multi-authors' ); ?></h4>
							<code>&lt;?php tmauthors_the_authors(); ?&gt;</code>
							<p><?php esc_html_e( 'Display linked author names for the current post.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Display Author Box', 'themeruby-multi-authors' ); ?></h4>
							<code>&lt;?php tmauthors_box(); ?&gt;</code>
							<p><?php esc_html_e( 'Display a formatted author box with avatars, names, and bios.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Get Author Names', 'themeruby-multi-authors' ); ?></h4>
							<code>&lt;?php echo tmauthors_get_author_names(); ?&gt;</code>
							<p><?php esc_html_e( 'Get author names as a string, separated by commas.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Get Post Authors', 'themeruby-multi-authors' ); ?></h4>
							<code>&lt;?php $authors = tmauthors_get_post_authors(); ?&gt;</code>
							<p><?php esc_html_e( 'Get an array of WP_User objects for all authors of the current post.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Check if Post Has Multiple Authors', 'themeruby-multi-authors' ); ?></h4>
							<code>&lt;?php if ( tmauthors_has_multiple_authors() ) { ... } ?&gt;</code>
							<p><?php esc_html_e( 'Check if the current post has more than one author.', 'themeruby-multi-authors' ); ?></p>
						</div>
					</div>
				</div>
				<div class="tma-settings-section">
					<div class="tma-section-header">
						<h3><?php esc_html_e( 'Query Examples', 'themeruby-multi-authors' ); ?></h3>
					</div>
					<div class="tma-section-body">
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Query by Author ID', 'themeruby-multi-authors' ); ?></h4>
							<code>
								$query = new WP_Query( [<br />
								&nbsp;&nbsp;'author' => 5,<br />
								&nbsp;&nbsp;'post_type' => 'post'<br />
								] );
							</code>
							<p><?php esc_html_e( 'Get all posts where user ID 5 is the primary or additional author.', 'themeruby-multi-authors' ); ?></p>
						</div>
						<div class="tma-code-example">
							<h4><?php esc_html_e( 'Query by Multiple Authors', 'themeruby-multi-authors' ); ?></h4>
							<code>
								$query = new WP_Query( [<br />
								&nbsp;&nbsp;'author__in' => [5, 10, 15],<br />
								&nbsp;&nbsp;'post_type' => 'post'<br />
								] );
							</code>
							<p><?php esc_html_e( 'Get all posts by multiple authors.', 'themeruby-multi-authors' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Load page assets.
	 *
	 * Called via load-{page_hook} action.
	 *
	 * @since 1.0.0
	 */
	public static function load_page_assets() {
		// Hook into admin_enqueue_scripts with specific callback.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_page_scripts' ], 80 );
	}

	/**
	 * Enqueue page-specific scripts and styles.
	 *
	 * Only called when on ThemeRuby Multi Authors admin page.
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_page_scripts() {

		// Enqueue our admin styles.
		wp_enqueue_style( 'tmauthors-admin', TMAUTHORS_PLUGIN_URL . 'admin/css/tma-admin.css', [], TMAUTHORS_VERSION );

		// Enqueue our admin script.
		wp_enqueue_script( 'tmauthors-admin', TMAUTHORS_PLUGIN_URL . 'admin/js/tma-admin.js', [ 'jquery' ], TMAUTHORS_VERSION, true );

		// Localize script with AJAX data.
		wp_localize_script(
				'tmauthors-admin',
				'tmAuthorsAdmin',
				[
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'tmauthors_admin_nonce' ),
				]
		);
	}

	/**
	 * AJAX handler: Save individual setting.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_save_setting() {
		// Verify nonce.
		check_ajax_referer( 'tmauthors_admin_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'themeruby-multi-authors' ) ] );
		}

		// Get and sanitize input.
		$setting_name  = isset( $_POST['setting_name'] ) ? sanitize_key( $_POST['setting_name'] ) : '';
		$setting_value = isset( $_POST['setting_value'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_value'] ) ) : '';

		// Validate setting name.
		$allowed_settings = [
				'tmauthors_archive_filtering',
				'tmauthors_author_filtering',
				'tmauthors_author_name_filtering',
				'tmauthors_author_in_filtering',
				'tmauthors_author_not_in_filtering',
				'tmauthors_seo_integration',
				'tmauthors_author_box',
				'tmauthors_load_css_header',
				'tmauthors_cache',
		];

		if ( ! in_array( $setting_name, $allowed_settings, true ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid setting name.', 'themeruby-multi-authors' ) ] );
		}

		// Sanitize value (all are checkboxes).
		$setting_value = self::sanitize_checkbox( $setting_value );

		// Update option.
		$updated = update_option( $setting_name, $setting_value );

		if ( $updated || get_option( $setting_name ) === $setting_value ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Setting saved successfully.', 'themeruby-multi-authors' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to save setting.', 'themeruby-multi-authors' ) ] );
		}
	}

	/**
	 * Sanitize checkbox input.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return int 1 or 0.
	 * @since 1.0.0
	 */
	public static function sanitize_checkbox( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}

	/**
	 * AJAX handler: Save post types setting.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_save_post_types() {
		// Verify nonce.
		check_ajax_referer( 'tmauthors_admin_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'themeruby-multi-authors' ) ] );
		}

		// Get post types from request.
		$post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ?
				array_map( 'sanitize_text_field', wp_unslash( $_POST['post_types'] ) ) : [];

		// Sanitize post types.
		$sanitized_types = self::sanitize_post_types( $post_types );

		// Update option.
		$updated = update_option( 'tmauthors_supported_post_types', $sanitized_types );

		if ( $updated || get_option( 'tmauthors_supported_post_types' ) === $sanitized_types ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Post types saved successfully.', 'themeruby-multi-authors' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to save post types.', 'themeruby-multi-authors' ) ] );
		}
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Input value.
	 *
	 * @return array Sanitized post types array.
	 * @since 1.0.0
	 */
	public static function sanitize_post_types( $value ) {
		if ( ! is_array( $value ) ) {
			return [ 'post' ];
		}

		// Sanitize each post type.
		$value = array_map( 'sanitize_key', $value );
		$value = array_filter( $value ); // Remove empty values.

		// Ensure at least 'post' is selected.
		if ( empty( $value ) ) {
			return [ 'post' ];
		}

		return $value;
	}

	/**
	 * AJAX handler: Clear all author cache.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_clear_cache() {
		// Verify nonce.
		check_ajax_referer( 'tmauthors_admin_nonce', 'nonce' );

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'themeruby-multi-authors' ) ] );
		}

		// Clear all author-related caches.
		TMAuthors_Cache_Admin::clear_all_cache();
		wp_send_json_success( [ 'message' => esc_html__( 'All author cache cleared successfully.', 'themeruby-multi-authors' ) ] );
	}
}
