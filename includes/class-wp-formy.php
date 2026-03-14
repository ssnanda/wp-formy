<?php

/**
 * The core plugin class.
 */
class WP_Formy {

	/**
	 * Load the required dependencies for this plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	private function load_dependencies() {
		require_once WP_FORMY_PLUGIN_DIR . 'admin/class-wp-formy-admin.php';
		// We will add public/frontend classes here in later phases
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new WP_Formy_Admin();

		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_wpf_save_form', array( $plugin_admin, 'ajax_save_form' ) );
		add_action( 'wp_ajax_wpf_import_form', array( $plugin_admin, 'ajax_import_form' ) );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		// Hooks are defined in the constructor, plugin is ready.
	}
}