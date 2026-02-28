<?php

/**
 * Plugin Name:       ThemeRuby Multi Authors
 * Plugin URI:        https://themeruby.com/multi-authors
 * Description:       A lightweight plugin that allows you to assign multiple writers to posts, fast and easy to use.
 * Tags:              authors, co-authors, multiple authors, users, team members
 * Author:            ThemeRuby
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author URI:        https://themeruby.com/
 * Text Domain:       themeruby-multi-authors
 * Domain Path:       /languages
 *
 * @package           themeruby-multi-authors
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TMAUTHORS_VERSION', '1.2.0' );
define( 'TMAUTHORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMAUTHORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TMAUTHORS_PLUGIN_FILE', __FILE__ );
define( 'TMAUTHORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main ThemeRuby Multi Authors Class.
 *
 * @since 1.0.0
 */
final class ThemeRuby_Multi_Authors {
	/**
	 * Plugin instance.
	 *
	 * @var ThemeRuby_Multi_Authors
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		// Core classes.
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-taxonomy.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-meta-box.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-author-query.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-query.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-display.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-seo.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-capabilities.php';
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-shortcodes.php';

		// Admin classes (only in admin area).
		if ( is_admin() ) {
			require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-admin.php';
			require_once TMAUTHORS_PLUGIN_DIR . 'includes/class-tma-cache-admin.php';
		}

		// Template tags and public functions.
		require_once TMAUTHORS_PLUGIN_DIR . 'includes/template-tags.php';
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Initialize core classes.
		add_action( 'plugins_loaded', [ $this, 'init_classes' ], 0 );

		// Enqueue frontend assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		// Activation and deactivation hooks.
		register_activation_hook( TMAUTHORS_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( TMAUTHORS_PLUGIN_FILE, [ $this, 'deactivate' ] );
	}

	/**
	 * Get plugin instance.
	 *
	 * @return ThemeRuby_Multi_Authors
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin classes.
	 *
	 * @since 1.0.0
	 */
	public function init_classes() {
		// Initialize taxonomy
		TMAuthors_Taxonomy::init();

		// Initialize unified meta box.
		TMAuthors_Meta_Box::init();

		// Initialize author archive query filter.
		TMAuthors_Author_Query::init();

		// Initialize custom WP_Query author parameter support.
		TMAuthors_Query::init();

		// Initialize display functions.
		TMAuthors_Display::init();

		// Initialize SEO integrations.
		TMAuthors_SEO::init();

		// Initialize capabilities.
		TMAuthors_Capabilities::init();

		// Initialize shortcodes.
		TMAuthors_Shortcodes::init();

		// Initialize admin interface (only in admin area).
		if ( is_admin() ) {
			TMAuthors_Admin::init();
			TMAuthors_Cache_Admin::init();
		}
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		$suffix = is_rtl() ? '-rtl' : '';

		// Enqueue frontend styles.
		wp_register_style(
			'tmauthors',
			TMAUTHORS_PLUGIN_URL . 'assets/tma-frontend' . $suffix . '.css',
			[],
			TMAUTHORS_VERSION
		);

		if ( get_option( 'tmauthors_load_css_header', false ) ) {
			wp_enqueue_style( 'tmauthors' );
		}
	}

	/**
	 * Plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Check requirements.
		$this->check_requirements();

		// Register taxonomy (needed for rewrite rules).
		TMAuthors_Taxonomy::register_taxonomy();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Set default options (only if not already set).
		$default_options = [
			'tmauthors_archive_filtering'       => 1,
			'tmauthors_author_filtering'        => 1,
			'tmauthors_author_name_filtering'   => 1,
			'tmauthors_author_in_filtering'     => 1,
			'tmauthors_author_not_in_filtering' => 1,
			'tmauthors_seo_integration'         => 1,
			'tmauthors_supported_post_types'    => [ 'post' ],
			'tmauthors_author_box'              => 0,
			'tmauthors_cache'                   => 1,
		];

		foreach ( $default_options as $option => $value ) {
			add_option( $option, $value );
		}

		// Set activation flag.
		update_option( 'tmauthors_version', TMAUTHORS_VERSION );
		update_option( 'tmauthors_activated', current_time( 'timestamp' ) );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @since 1.0.0
	 */
	private function check_requirements() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( TMAUTHORS_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'ThemeRuby Multi Authors requires PHP 7.4 or higher.', 'themeruby-multi-authors' ),
				esc_html__( 'Plugin Activation Error', 'themeruby-multi-authors' ),
				[ 'back_link' => true ]
			);
		}

		// Check WP version.
		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			deactivate_plugins( TMAUTHORS_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'ThemeRuby Multi Authors requires version 5.8 or higher.', 'themeruby-multi-authors' ),
				esc_html__( 'Plugin Activation Error', 'themeruby-multi-authors' ),
				[ 'back_link' => true ]
			);
		}
	}

	/**
	 * Plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

/**
 * Get main plugin instance.
 *
 * @return ThemeRuby_Multi_Authors
 */
function tmauthors_init() {
	return ThemeRuby_Multi_Authors::instance();
}

// Initialize plugin.
tmauthors_init();
