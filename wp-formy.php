<?php
/**
 * Plugin Name:       WP Formy
 * Plugin URI:        https://github.com/ssnanda/wp-formy
 * Description:       A custom WordPress form builder plugin for building forms, collecting entries, and managing workflows inside WordPress.
 * Version:           0.1.5
 * Author:            itSpector
 * Author URI:        https://itspector.com
 * Update URI:        https://github.com/ssnanda/wp-formy
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-formy
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'WP_FORMY_VERSION' ) ) {
define( 'WP_FORMY_VERSION', '0.1.5' );
}

if ( ! defined( 'WP_FORMY_PLUGIN_DIR' ) ) {
	define( 'WP_FORMY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WP_FORMY_PLUGIN_URL' ) ) {
	define( 'WP_FORMY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WP_FORMY_PLUGIN_BASENAME' ) ) {
	define( 'WP_FORMY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! function_exists( 'wp_formy_get_settings_defaults' ) ) {
	function wp_formy_get_settings_defaults() {
		return array(
			'default_notification_email'    => get_option( 'admin_email' ),
			'default_notification_subject'  => 'New submission for {form_title}',
			'default_notifications_enabled' => '1',
			'default_from_name'             => get_bloginfo( 'name' ),
			'default_reply_to_mode'         => 'submitter',
			'default_success_message'       => 'Form submitted successfully.',
			'validation_mode'               => 'native',
			'require_unique_form_names'     => '1',
			'honeypot_enabled'              => '1',
			'spam_challenge_provider'       => 'turnstile',
			'recaptcha_site_key'            => '',
			'recaptcha_secret_key'          => '',
			'hcaptcha_site_key'             => '',
			'hcaptcha_secret_key'           => '',
			'turnstile_site_key'            => '',
			'turnstile_secret_key'          => '',
			'webhook_url'                   => '',
			'asana_enabled'                 => '0',
			'asana_personal_access_token'   => '',
			'asana_workspace_gid'           => '',
			'asana_project_gid'             => '',
			'stripe_mode'                   => 'test',
			'stripe_publishable_key'        => '',
			'stripe_secret_key'             => '',
		);
	}
}

if ( ! function_exists( 'wp_formy_get_settings' ) ) {
	function wp_formy_get_settings() {
		$saved_settings = get_option( 'wp_formy_settings', array() );
		if ( ! is_array( $saved_settings ) ) {
			$saved_settings = array();
		}

		return wp_parse_args( $saved_settings, wp_formy_get_settings_defaults() );
	}
}

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

run_wp_formy();
