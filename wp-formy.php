<?php
/**
 * Plugin Name:       WP Formy
 * Plugin URI:        https://example.com/wp-formy
 * Description:       A custom WordPress form builder plugin.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-formy
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'WP_FORMY_VERSION', '1.0.2' );
define( 'WP_FORMY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_FORMY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_FORMY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wp_formy() {
	require_once WP_FORMY_PLUGIN_DIR . 'includes/class-wp-formy-activator.php';
	WP_Formy_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_wp_formy' );

/**
 * Begins execution of the plugin.
 */
function run_wp_formy() {
	require_once WP_FORMY_PLUGIN_DIR . 'includes/class-wp-formy.php';

	$plugin = new WP_Formy();
	$plugin->run();
}

// Run the plugin initialization.
run_wp_formy();