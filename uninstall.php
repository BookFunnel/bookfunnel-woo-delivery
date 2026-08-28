<?php
/**
 * Uninstall BookFunnel.
 *
 * @package BookFunnelWooCommerce
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- DROP TABLE on uninstall is intentional; $wpdb->prefix is safe
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}bf_wc_notifications`" );

$bf_wc_option_names = array(
	'bf_wc_token',
	'bf_wc_webhook_uid',
	'bf_wc_webhook_url',
	'bf_wc_purchase_uid',
	'bf_wc_authenticated',
	'bf_wc_ping_failed',
	'bf_wc_email_injection',
	'bf_wc_legacy_connection_warning',
);

foreach ( $bf_wc_option_names as $bf_wc_option_name ) {
	delete_option( $bf_wc_option_name );
}