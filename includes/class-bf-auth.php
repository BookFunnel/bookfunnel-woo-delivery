<?php
/**
 * Authentication flow for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the BookFunnel connect callback and connectivity ping.
 */
class BF_WC_Auth {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bookfunnel-woocommerce';

	/**
	 * Singleton instance.
	 *
	 * @var BF_WC_Auth|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return BF_WC_Auth
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybe_handle_auth_callback' ) );
		}
	}

	/**
	 * Process the BookFunnel auth callback when present.
	 *
	 * @return void
	 */
	public function maybe_handle_auth_callback() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$bf_auth = isset( $_GET['bf_auth'] ) ? sanitize_key( wp_unslash( $_GET['bf_auth'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token verified below via transient

		if ( '1' !== $bf_auth ) {
			return;
		}

		$stored_state   = get_transient( 'bf_wc_auth_state_' . get_current_user_id() );
		$incoming_state = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token verified against stored transient immediately below
		delete_transient( 'bf_wc_auth_state_' . get_current_user_id() );

		if ( empty( $stored_state ) || empty( $incoming_state ) || $stored_state !== $incoming_state ) {
			BF_WC_Logger::warning( 'BookFunnel authentication callback rejected because the nonce was invalid.' );
			return;
		}

		$token       = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token verified above via transient
		$webhook_uid = isset( $_GET['webhook_uid'] ) ? sanitize_text_field( wp_unslash( $_GET['webhook_uid'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token verified above via transient
		$webhook_url = isset( $_GET['webhook_url'] ) ? esc_url_raw( wp_unslash( $_GET['webhook_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state token verified above via transient

		if ( '' === $token || '' === $webhook_uid || '' === $webhook_url ) {
			BF_WC_Logger::warning(
				'BookFunnel authentication callback rejected because one or more required values were missing or invalid.',
				array(
					'has_token'       => '' !== $token,
					'has_webhook_uid' => '' !== $webhook_uid,
					'has_webhook_url' => '' !== $webhook_url,
				)
			);
			return;
		}

			$existing_uid = get_option( 'bf_wc_webhook_uid', '' );
		if ( '' !== $existing_uid && $existing_uid !== $webhook_uid ) {
			update_option( 'bf_wc_legacy_connection_warning', true );
		} else {
			delete_option( 'bf_wc_legacy_connection_warning' );
		}

		update_option( 'bf_wc_token', $token );
		update_option( 'bf_wc_webhook_uid', $webhook_uid );
		update_option( 'bf_wc_webhook_url', $webhook_url );
		update_option( 'bf_wc_authenticated', true );

		$ping_success = $this->send_ping();

		BF_WC_Logger::info(
			'BookFunnel plugin connected from the authentication callback.',
			array(
				'webhook_uid'  => $webhook_uid,
				'ping_success' => $ping_success,
			)
		);

		wp_safe_redirect(
			add_query_arg(
				'bf_connected',
				'1',
				$this->get_settings_page_url()
			)
		);
		exit;
	}

	/**
	 * Determine whether the plugin is authenticated.
	 *
	 * @return bool
	 */
	public function is_authenticated() {
		return filter_var( get_option( 'bf_wc_authenticated', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Get the stored auth token.
	 *
	 * @return string
	 */
	public function get_token() {
		return trim( (string) get_option( 'bf_wc_token', '' ) );
	}

	/**
	 * Get the stored webhook UID.
	 *
	 * @return string
	 */
	public function get_webhook_uid() {
		return trim( (string) get_option( 'bf_wc_webhook_uid', '' ) );
	}

	/**
	 * Get the stored webhook URL.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return trim( (string) get_option( 'bf_wc_webhook_url', '' ) );
	}

	/**
	 * Send a ping request to the stored webhook URL.
	 *
	 * @return bool
	 */
	public function send_ping() {
		$base_url = trim( (string) $this->get_webhook_url() );
		if ( '' === $base_url ) {
			update_option( 'bf_wc_ping_failed', true );
			BF_WC_Logger::warning( 'BookFunnel ping could not be sent because no webhook URL is configured.' );
			return false;
		}
		$webhook_url = untrailingslashit( $base_url ) . '/wooplugin';

		$body = wp_json_encode(
			array(
				'event'          => 'ping',
				'source'         => 'woo_exten',
				'plugin_version' => BF_WC_VERSION,
			)
		);

		if ( false === $body ) {
			update_option( 'bf_wc_ping_failed', true );
			BF_WC_Logger::warning( 'BookFunnel ping could not be sent because the request payload could not be encoded as JSON.' );
			return false;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'        => $body,
				'headers'     => array(
					'Content-Type'              => 'application/json',
					'Accept'                    => 'application/json',
					'X-BookFunnel-Plugin-Token' => $this->get_token(),
				),
				'timeout'     => 15,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( 'bf_wc_ping_failed', true );
			BF_WC_Logger::warning(
				'BookFunnel ping failed after the authentication callback.',
				array(
					'response_code' => 0,
					'response_body' => $response->get_error_message(),
				)
			);
			return false;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );

		if ( 200 === $response_code ) {
			delete_option( 'bf_wc_ping_failed' );
			BF_WC_Logger::info(
				'BookFunnel ping succeeded after the authentication callback.',
				array(
					'response_code' => $response_code,
				)
			);
			return true;
		}

		update_option( 'bf_wc_ping_failed', true );
		BF_WC_Logger::warning(
			'BookFunnel ping failed after the authentication callback.',
			array(
				'response_code' => $response_code,
				'response_body' => $response_body,
			)
		);

		return false;
	}

	/**
	 * Get the admin Settings page URL.
	 *
	 * @return string
	 */
	private function get_settings_page_url() {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => 'settings',
			),
			admin_url( 'admin.php' )
		);
	}
}
