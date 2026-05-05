<?php
/**
 * Activation routines for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation tasks.
 */
class BF_WC_Activator {
	/**
	 * Create required database tables on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'bf_wc_notifications';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			event_type varchar(32) NOT NULL,
			status varchar(20) NOT NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			next_attempt_at datetime NULL DEFAULT NULL,
			server_response text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id_event_type (order_id, event_type),
			KEY status_next_attempt_at (status, next_attempt_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
