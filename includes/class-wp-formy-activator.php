<?php

/**
 * Fired during plugin activation.
 */
class WP_Formy_Activator {

	/**
	 * Create custom tables.
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		
		$table_forms = $wpdb->prefix . 'formy_forms';
		$table_leads = $wpdb->prefix . 'formy_leads';

		$sql = "CREATE TABLE $table_forms (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			form_schema longtext NOT NULL,
			status varchar(50) DEFAULT 'published' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;
		CREATE TABLE $table_leads (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			lead_data longtext NOT NULL,
			status varchar(50) DEFAULT 'unread' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}