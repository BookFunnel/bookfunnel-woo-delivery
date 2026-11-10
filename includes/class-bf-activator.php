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
		self::install();
	}

	/**
	 * Run the install routine when the stored database version is missing or outdated.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( get_option( 'bf_wc_db_version' ) === BF_WC_DB_VERSION ) {
			return;
		}

		if ( get_transient( 'bf_wc_db_install_retry' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or update the notifications table and record the database version.
	 *
	 * @return void
	 */
	private static function install() {
		global $wpdb;

		require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-logger.php';

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

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

		if ( $table_exists ) {
			update_option( 'bf_wc_db_version', BF_WC_DB_VERSION );
			delete_transient( 'bf_wc_db_install_retry' );
			return;
		}

		BF_WC_Logger::error(
			'BookFunnel notification table could not be created.',
			array(
				'table'    => $table_name,
				'db_error' => $wpdb->last_error,
			)
		);

		set_transient( 'bf_wc_db_install_retry', 1, HOUR_IN_SECONDS );
	}
}
