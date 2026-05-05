<?php
/**
 * Admin UI and order resend tools for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin settings UI, log viewer, and resend actions.
 */
class BF_WC_Admin {
	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'bookfunnel-woocommerce';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'bf_wc_settings';

	/**
	 * Email injection option name.
	 *
	 * @var string
	 */
	const OPTION_EMAIL_INJECTION = 'bf_wc_email_injection';

	/**
	 * Individual order action key.
	 *
	 * @var string
	 */
	const ORDER_ACTION = 'bf_resend';

	/**
	 * Bulk action key.
	 *
	 * @var string
	 */
	const BULK_ACTION = 'bf_wc_resend';

	/**
	 * Admin notice transient prefix.
	 *
	 * @var string
	 */
	const NOTICE_TRANSIENT_PREFIX = 'bf_wc_admin_notice_';

	/**
	 * Maximum number of log rows to display.
	 *
	 * @var int
	 */
	const LOG_LIMIT = 100;

	/**
	 * Maximum number of delivery rows to display.
	 *
	 * @var int
	 */
	const DELIVERY_LIMIT = 100;

	/**
	 * Delivery health notice dismissal TTL.
	 *
	 * @var int
	 */
	const HEALTH_NOTICE_DISMISS_TTL = 4 * HOUR_IN_SECONDS;

	/**
	 * Delivery health notice dismissal transient prefix.
	 *
	 * @var string
	 */
	const HEALTH_NOTICE_DISMISS_PREFIX = 'bf_wc_delivery_notice_dismissed_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_page_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		add_filter( 'woocommerce_order_actions', array( $this, 'register_order_action' ) );
		add_action( 'woocommerce_order_action_' . self::ORDER_ACTION, array( $this, 'handle_order_action_resend' ) );

		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_action' ), 10, 3 );
	}

	/**
	 * Enqueue admin styles for the BookFunnel admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( 'woocommerce_page_bookfunnel-woocommerce' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'bf-wc-admin',
			plugin_dir_url( BF_WC_PLUGIN_FILE ) . 'assets/css/admin.css',
			array(),
			BF_WC_VERSION
		);
	}

	/**
	 * Register the WooCommerce submenu page.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'woocommerce',
			__( 'BookFunnel', 'bookfunnel' ),
			__( 'BookFunnel', 'bookfunnel' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_EMAIL_INJECTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_email_injection_setting' ),
				'default'           => true,
			)
		);
	}

	/**
	 * Sanitize the email injection option.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return int
	 */
	public function sanitize_email_injection_setting( $value ) {
		$sanitized = filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		$current   = filter_var( get_option( self::OPTION_EMAIL_INJECTION, true ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;

		if ( $current !== $sanitized ) {
			BF_WC_Logger::info(
				'BookFunnel email injection setting updated.',
				array(
					'old_value' => $current,
					'new_value' => $sanitized,
				)
			);
		}

		return $sanitized;
	}

	/**
	 * Handle custom admin page actions.
	 *
	 * @return void
	 */
	public function handle_page_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_GET['bf_wc_dismiss_delivery_notice'] ) ) {
			check_admin_referer( 'bf_wc_dismiss_delivery_notice' );
			$this->dismiss_delivery_health_notice();
		}

		$action = isset( $_POST['bf_wc_admin_action'] ) ? sanitize_key( wp_unslash( $_POST['bf_wc_admin_action'] ) ) : '';

		if ( '' === $action ) {
			return;
		}

		if ( 'disconnect' === $action ) {
			check_admin_referer( 'bf_wc_disconnect_action', 'bf_wc_disconnect_nonce' );
			$this->handle_disconnect();
		}

		if ( 'resend_abandoned' === $action ) {
			check_admin_referer( 'bf_wc_resend_abandoned_action', 'bf_wc_resend_abandoned_nonce' );
			$this->handle_resend_abandoned();
		}

		if ( 'resend_delivery' === $action ) {
			check_admin_referer( 'bf_wc_resend_delivery_action', 'bf_wc_resend_delivery_nonce' );
			$this->handle_resend_delivery();
		}
	}

	/**
	 * Render any queued admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		$notice = get_transient( $this->get_notice_transient_key() );

		if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
			delete_transient( $this->get_notice_transient_key() );

			$type = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'success';
			$type = in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'success';
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php
		}

		$this->render_delivery_health_notice();
	}

	/**
	 * Register the BookFunnel dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'bf_wc_delivery_status',
			__( 'BookFunnel Deliveries', 'bookfunnel' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the BookFunnel dashboard widget.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		$counts = $this->get_delivery_counts();
		?>
		<ul>
			<li><?php /* translators: %d: number of pending deliveries */ echo esc_html( sprintf( __( 'Pending: %d', 'bookfunnel' ), (int) $counts['pending'] ) ); ?></li>
			<li><?php /* translators: %d: number of failed deliveries */ echo esc_html( sprintf( __( 'Failed: %d', 'bookfunnel' ), (int) $counts['failed'] ) ); ?></li>
			<li><?php /* translators: %d: number of abandoned deliveries */ echo esc_html( sprintf( __( 'Abandoned: %d', 'bookfunnel' ), (int) $counts['abandoned'] ) ); ?></li>
		</ul>
		<p><a href="<?php echo esc_url( $this->get_deliveries_tab_url() ); ?>"><?php echo esc_html__( 'View deliveries →', 'bookfunnel' ); ?></a></p>
		<?php
	}

	/**
	 * Register the custom order action.
	 *
	 * @param array $actions Existing order actions.
	 * @return array
	 */
	public function register_order_action( $actions ) {
		$actions[ self::ORDER_ACTION ] = __( 'Resend to BookFunnel', 'bookfunnel' );

		return $actions;
	}

	/**
	 * Handle the custom order action.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public function handle_order_action_resend( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$result = $this->resend_order_notification( (int) $order->get_id() );

		$this->add_admin_notice( $result['notice_type'], $result['message'] );
	}

	/**
	 * Register the custom bulk action.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		$actions[ self::BULK_ACTION ] = __( 'Resend to BookFunnel', 'bookfunnel' );

		return $actions;
	}

	/**
	 * Handle the custom bulk action.
	 *
	 * @param string $sendback Redirect URL.
	 * @param string $action   Selected bulk action.
	 * @param array  $order_ids Selected order IDs.
	 * @return string
	 */
	public function handle_bulk_action( $sendback, $action, $order_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $sendback;
		}

		$processed = 0;
		$failed    = 0;

		foreach ( (array) $order_ids as $order_id ) {
			$result = $this->resend_order_notification( absint( $order_id ) );

			if ( ! empty( $result['processed'] ) ) {
				++$processed;
			} else {
				++$failed;
			}
		}

		if ( $processed > 0 && 0 === $failed ) {
			$this->add_admin_notice(
				'success',
				sprintf(
					/* translators: %d number of orders. */
					_n( 'BookFunnel resend processed for %d order.', 'BookFunnel resend processed for %d orders.', $processed, 'bookfunnel' ),
					$processed
				)
			);
		} elseif ( $processed > 0 ) {
			$this->add_admin_notice(
				'warning',
				sprintf(
					/* translators: 1: processed orders count, 2: failed orders count. */
					__( 'BookFunnel resend processed for %1$d orders. %2$d orders could not be re-queued.', 'bookfunnel' ),
					$processed,
					$failed
				)
			);
		} else {
				$this->add_admin_notice( 'error', __( 'BookFunnel resend could not be processed for the selected orders.', 'bookfunnel' ) );
		}

		return $sendback;
	}

	/**
	 * Render the plugin admin page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bookfunnel' ) );
		}

		$active_tab              = $this->get_current_tab();
		$settings_tab_url        = $this->get_admin_page_url( 'settings' );
		$deliveries_tab_url      = $this->get_deliveries_tab_url();
		$logs_tab_url            = $this->get_admin_page_url( 'logs' );
		$is_connected            = $this->is_connected();
		$has_ping_warning        = $this->has_ping_warning();
		$logo_url                = $this->get_logo_url();
		$connect_url             = $this->get_connect_url();
		$native_logs_url         = $this->get_native_logs_url();
		$webhook_uid             = (string) get_option( 'bf_wc_webhook_uid', '' );
		$email_injection_enabled = filter_var( get_option( self::OPTION_EMAIL_INJECTION, true ), FILTER_VALIDATE_BOOLEAN );
		$log_rows                = array();
		$delivery_filter         = 'all';
		$delivery_rows           = array();
		$delivery_counts         = $this->get_default_delivery_counts();

		if ( 'logs' === $active_tab ) {
				$log_rows = $this->get_log_rows();
		} elseif ( 'deliveries' === $active_tab ) {
			$delivery_filter = $this->get_current_delivery_filter();
			$delivery_counts = $this->get_delivery_counts();
			$delivery_rows   = $this->get_delivery_rows( $delivery_filter );
		}

		include BF_WC_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * Disconnect the plugin from BookFunnel.
	 *
	 * @return void
	 */
	private function handle_disconnect() {
		delete_option( 'bf_wc_token' );
		delete_option( 'bf_wc_webhook_uid' );
		delete_option( 'bf_wc_webhook_url' );
		delete_option( 'bf_wc_authenticated' );
		delete_option( 'bf_wc_ping_failed' );
			delete_option( 'bf_wc_legacy_connection_warning' );

		BF_WC_Logger::info( 'BookFunnel plugin disconnected from the admin settings page.' );

		$this->add_admin_notice( 'success', __( 'BookFunnel has been disconnected.', 'bookfunnel' ) );

		wp_safe_redirect( $this->get_admin_page_url( 'settings' ) );
		exit;
	}

	/**
	 * Reset all abandoned notifications and trigger immediate processing.
	 *
	 * @return void
	 */
	private function handle_resend_abandoned() {
		global $wpdb;

		$delivery_filter = $this->get_posted_delivery_filter();
		$updated_at      = current_time( 'mysql', true );
		$query           = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bf_wc_notifications SET status = %s, attempts = %d, next_attempt_at = NULL, updated_at = %s WHERE status = %s",
			'pending',
			0,
			$updated_at,
			'abandoned'
		);

			$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above
		$count      = false === $result ? 0 : (int) $result;

		if ( $count > 0 ) {
			do_action( BF_WC_Cron::HOOK );

			BF_WC_Logger::info(
				'All abandoned BookFunnel notifications were reset from the admin deliveries page.',
				array(
					'count' => $count,
				)
			);

			$this->add_admin_notice(
				'success',
				sprintf(
					/* translators: %d number of notifications. */
					_n( '%d abandoned notification was reset and queued for immediate processing.', '%d abandoned notifications were reset and queued for immediate processing.', $count, 'bookfunnel' ),
					$count
				)
			);
		} else {
				$this->add_admin_notice( 'info', __( 'No abandoned BookFunnel notifications were found.', 'bookfunnel' ) );
		}

		wp_safe_redirect( $this->get_deliveries_tab_url( $delivery_filter ) );
		exit;
	}

	/**
	 * Requeue and immediately process a single delivery notification.
	 *
	 * @return void
	 */
	private function handle_resend_delivery() {
			$notification_id = isset( $_POST['bf_wc_notification_id'] ) ? absint( wp_unslash( $_POST['bf_wc_notification_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified upstream in handle_page_actions()
		$delivery_filter     = $this->get_posted_delivery_filter();

		if ( $notification_id <= 0 ) {
			$this->add_admin_notice( 'error', __( 'The selected delivery notification is invalid.', 'bookfunnel' ) );
			wp_safe_redirect( $this->get_deliveries_tab_url( $delivery_filter ) );
			exit;
		}

		$result = $this->resend_delivery_notification( $notification_id );

		$this->add_admin_notice( $result['notice_type'], $result['message'] );

		wp_safe_redirect( $this->get_deliveries_tab_url( $delivery_filter ) );
		exit;
	}

	/**
	 * Get the current admin tab.
	 *
	 * @return string
	 */
	private function get_current_tab() {
			$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin tab parameter

		return in_array( $tab, array( 'settings', 'deliveries', 'logs' ), true ) ? $tab : 'settings';
	}

	/**
	 * Get the current deliveries status filter.
	 *
	 * @return string
	 */
	private function get_current_delivery_filter() {
			$filter = isset( $_GET['delivery_status'] ) ? sanitize_key( wp_unslash( $_GET['delivery_status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter

		return $this->sanitize_delivery_filter( $filter );
	}

	/**
	 * Get the current deliveries status filter from POST data.
	 *
	 * @return string
	 */
	private function get_posted_delivery_filter() {
			$filter = isset( $_POST['bf_wc_delivery_filter'] ) ? sanitize_key( wp_unslash( $_POST['bf_wc_delivery_filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified upstream in handle_page_actions()

		return $this->sanitize_delivery_filter( $filter );
	}

	/**
	 * Sanitize a delivery filter value.
	 *
	 * @param string $filter Requested filter.
	 * @return string
	 */
	private function sanitize_delivery_filter( $filter ) {
		$allowed_filters = array( 'all', 'pending', 'failed', 'done', 'abandoned' );

		return in_array( $filter, $allowed_filters, true ) ? $filter : 'all';
	}

	/**
	 * Get the Deliveries tab URL.
	 *
	 * @param string $filter Deliveries status filter.
	 * @return string
	 */
	private function get_deliveries_tab_url( $filter = 'all' ) {
		$filter = $this->sanitize_delivery_filter( $filter );
		$url    = $this->get_admin_page_url( 'deliveries' );

		if ( 'all' !== $filter ) {
			$url = add_query_arg( 'delivery_status', $filter, $url );
		}

		return $url;
	}

	/**
	 * Get delivery counts by status.
	 *
	 * @return array
	 */
	private function get_delivery_counts() {
		global $wpdb;

		$counts = $this->get_default_delivery_counts();

		if ( ! $this->notification_table_exists() ) {
			return $counts;
		}

		$query       = "SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}bf_wc_notifications GROUP BY status";
			$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				$status = isset( $row['status'] ) ? (string) $row['status'] : '';
				$total  = isset( $row['total'] ) ? (int) $row['total'] : 0;

				$counts['all'] += $total;

				if ( array_key_exists( $status, $counts ) ) {
					$counts[ $status ] = $total;
				}
			}
		}

		$repeated_failed_query         = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bf_wc_notifications WHERE status = %s AND attempts >= %d",
			'failed',
			3
		);
			$counts['repeated_failed'] = (int) $wpdb->get_var( $repeated_failed_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		return $counts;
	}

	/**
	 * Get the default delivery counts array.
	 *
	 * @return array
	 */
	private function get_default_delivery_counts() {
		return array(
			'all'             => 0,
			'pending'         => 0,
			'failed'          => 0,
			'done'            => 0,
			'abandoned'       => 0,
			'repeated_failed' => 0,
		);
	}

	/**
	 * Get the admin page URL.
	 *
	 * @param string $tab Active tab.
	 * @return string
	 */
	private function get_admin_page_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Determine whether the plugin is connected.
	 *
	 * @return bool
	 */
	private function is_connected() {
		return filter_var( get_option( 'bf_wc_authenticated', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Determine whether the last ping failed.
	 *
	 * @return bool
	 */
	private function has_ping_warning() {
		return filter_var( get_option( 'bf_wc_ping_failed', false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Get the BookFunnel connect URL.
	 *
	 * @return string
	 */
	private function get_connect_url() {
		$state = wp_generate_password( 32, false );
		set_transient( 'bf_wc_auth_state_' . get_current_user_id(), $state, 5 * MINUTE_IN_SECONDS );

		return add_query_arg(
			array(
				'store_url'    => site_url(),
				'redirect_uri' => admin_url( 'admin.php' ),
				'_wpnonce'     => $state,
			),
			'https://dashboard.bookfunnel.com/integrate/woocommerce'
		);
	}

	/**
	 * Get the fallback native WooCommerce logs URL.
	 *
	 * @return string
	 */
	private function get_native_logs_url() {
		return add_query_arg(
			array(
				'page'   => 'wc-status',
				'tab'    => 'logs',
				'source' => BF_WC_Logger::SOURCE,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get the BookFunnel logo URL if the asset exists.
	 *
	 * @return string
	 */
	private function get_logo_url() {
		$relative_path = 'assets/images/bookfunnel-logo.png';
		$absolute_path = BF_WC_PLUGIN_DIR . $relative_path;

		if ( ! file_exists( $absolute_path ) ) {
			return '';
		}

		return BF_WC_PLUGIN_URL . $relative_path;
	}

	/**
	 * Fetch recent BookFunnel log rows from WooCommerce log files.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_log_rows() {
			$files = glob( wp_upload_dir()['basedir'] . '/wc-logs/bookfunnel-woocommerce-*.log' );

		if ( empty( $files ) || ! is_array( $files ) ) {
			return array();
		}

			rsort( $files, SORT_STRING );

			$latest_file = reset( $files );

		if ( ! is_string( $latest_file ) || '' === $latest_file || ! is_readable( $latest_file ) ) {
			return array();
		}

			$lines = file( $latest_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( ! is_array( $lines ) || empty( $lines ) ) {
			return array();
		}

			$rows = array();

		foreach ( array_reverse( $lines ) as $line ) {
			if ( ! is_string( $line ) || '' === trim( $line ) ) {
				continue;
			}

			$entry = $this->parse_log_line( $line );

			if ( null === $entry ) {
				continue;
			}

			$rows[] = $entry;

			if ( count( $rows ) >= self::LOG_LIMIT ) {
				break;
			}
		}

			return $rows;
	}

	/**
	 * Parse a WooCommerce file log line into a structured row.
	 *
	 * @param string $line Raw log line.
	 * @return array<string, string>|null
	 */
	private function parse_log_line( $line ) {
		$matches = array();

		if ( ! preg_match( '/^(\S+)\s+([A-Z]+)\s+(.*?)(?:\s+CONTEXT:\s+(.*))?$/', $line, $matches ) ) {
			return null;
		}

		$timestamp = isset( $matches[1] ) ? (string) $matches[1] : '';
		$level     = isset( $matches[2] ) ? (string) $matches[2] : '';
		$message   = isset( $matches[3] ) ? trim( (string) $matches[3] ) : '';
		$context   = isset( $matches[4] ) ? trim( (string) $matches[4] ) : '';

		if ( '' === $timestamp || '' === $level || '' === $message ) {
			return null;
		}

		if ( '' !== $context ) {
			$decoded = json_decode( $context, true );

			if ( JSON_ERROR_NONE === json_last_error() ) {
				$pretty_context = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

				if ( false !== $pretty_context ) {
					$context = $pretty_context;
				}
			}
		}

		return array(
			'timestamp' => $timestamp,
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
		);
	}

	/**
	 * Determine whether the notifications table exists.
	 *
	 * @return bool
	 */
	private function notification_table_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bf_wc_notifications';
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name );
			$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		return $table_name === $result;
	}

	/**
	 * Get delivery rows for the Deliveries tab.
	 *
	 * @param string $status_filter Status filter.
	 * @return array
	 */
	private function get_delivery_rows( $status_filter ) {
		global $wpdb;

		if ( ! $this->notification_table_exists() ) {
			return array();
		}

		$status_filter = $this->sanitize_delivery_filter( $status_filter );
		if ( 'all' === $status_filter ) {
			$query = $wpdb->prepare(
				"SELECT id, order_id, event_type, status, attempts, updated_at FROM {$wpdb->prefix}bf_wc_notifications ORDER BY updated_at DESC, id DESC LIMIT %d",
				self::DELIVERY_LIMIT
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT id, order_id, event_type, status, attempts, updated_at FROM {$wpdb->prefix}bf_wc_notifications WHERE status = %s ORDER BY updated_at DESC, id DESC LIMIT %d",
				$status_filter,
				self::DELIVERY_LIMIT
			);
		}

			$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( ! is_array( $results ) ) {
			return array();
		}

		$rows = array();

		foreach ( $results as $row ) {
			$rows[] = array(
				'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'order_id'   => isset( $row['order_id'] ) ? (int) $row['order_id'] : 0,
				'event_type' => isset( $row['event_type'] ) ? (string) $row['event_type'] : '',
				'status'     => isset( $row['status'] ) ? (string) $row['status'] : '',
				'attempts'   => isset( $row['attempts'] ) ? (int) $row['attempts'] : 0,
				'updated_at' => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Requeue and immediately process a notification for an order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	private function resend_order_notification( $order_id ) {
		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return array(
				'processed'   => false,
				'notice_type' => 'error',
				'message'     => __( 'The selected order could not be loaded.', 'bookfunnel' ),
			);
		}

		$notification = $this->get_latest_notification_for_order( (int) $order->get_id() );

		if ( ! empty( $notification ) ) {
			$notification_id = (int) $notification['id'];
			$event_type      = (string) $notification['event_type'];

			$this->reset_notification_row( $notification_id );

			BF_WC_Notifier::queue_and_send_order_event(
				(int) $order->get_id(),
				$event_type,
				array(
					'notification_id' => $notification_id,
				)
			);

			BF_WC_Logger::info(
				'BookFunnel resend requested from the admin using the latest notification record.',
				array(
					'order_id'        => (int) $order->get_id(),
					'notification_id' => $notification_id,
					'event_type'      => $event_type,
				)
			);

			return array(
				'processed'   => true,
				'notice_type' => 'success',
				'message'     => sprintf(
					/* translators: %d order ID. */
					__( 'BookFunnel resend processed for order #%d.', 'bookfunnel' ),
					(int) $order->get_id()
				),
			);
		}

		$event_type = $this->determine_event_type_from_order( $order );

		if ( '' === $event_type ) {
			BF_WC_Logger::warning(
				'BookFunnel resend could not create a new notification because the order status is unsupported.',
				array(
					'order_id'     => (int) $order->get_id(),
					'order_status' => (string) $order->get_status(),
				)
			);

			return array(
				'processed'   => false,
				'notice_type' => 'warning',
				'message'     => sprintf(
					/* translators: 1: order ID, 2: order status. */
					__( 'Order #%1$d could not be resent because status "%2$s" is not supported for BookFunnel resend.', 'bookfunnel' ),
					(int) $order->get_id(),
					(string) $order->get_status()
				),
			);
		}

		$result = BF_WC_Notifier::queue_and_send_order_event( (int) $order->get_id(), $event_type );

		if ( empty( $result['notification_id'] ) ) {
			return array(
				'processed'   => false,
				'notice_type' => 'error',
				'message'     => sprintf(
					/* translators: %d order ID. */
					__( 'A new BookFunnel notification could not be created for order #%d.', 'bookfunnel' ),
					(int) $order->get_id()
				),
			);
		}

		BF_WC_Logger::info(
			'BookFunnel resend requested from the admin and a new notification record was created.',
			array(
				'order_id'        => (int) $order->get_id(),
				'notification_id' => (int) $result['notification_id'],
				'event_type'      => $event_type,
				'order_status'    => (string) $order->get_status(),
			)
		);

		return array(
			'processed'   => true,
			'notice_type' => 'success',
			'message'     => sprintf(
				/* translators: %d order ID. */
				__( 'A new BookFunnel notification was created and processed for order #%d.', 'bookfunnel' ),
				(int) $order->get_id()
			),
		);
	}

	/**
	 * Requeue and immediately process an existing notification row.
	 *
	 * @param int $notification_id Notification ID.
	 * @return array
	 */
	private function resend_delivery_notification( $notification_id ) {
		$notification = $this->get_notification_row( $notification_id );

		if ( empty( $notification ) ) {
			return array(
				'processed'   => false,
				'notice_type' => 'error',
				'message'     => __( 'The selected delivery notification could not be loaded.', 'bookfunnel' ),
			);
		}

		if ( ! in_array( $notification['status'], array( 'failed', 'abandoned' ), true ) ) {
			return array(
				'processed'   => false,
				'notice_type' => 'info',
				'message'     => __( 'Only failed or abandoned delivery notifications can be resent.', 'bookfunnel' ),
			);
		}

		$order = wc_get_order( (int) $notification['order_id'] );

		if ( ! $order ) {
			return array(
				'processed'   => false,
				'notice_type' => 'error',
				'message'     => __( 'The order for the selected delivery notification could not be loaded.', 'bookfunnel' ),
			);
		}

		$this->reset_notification_row( $notification_id );

		BF_WC_Notifier::queue_and_send_order_event(
			(int) $order->get_id(),
			(string) $notification['event_type'],
			array(
				'notification_id' => $notification_id,
			)
		);

		BF_WC_Logger::info(
			'BookFunnel resend requested from the admin deliveries tab using an existing notification record.',
			array(
				'order_id'        => (int) $order->get_id(),
				'notification_id' => $notification_id,
				'event_type'      => (string) $notification['event_type'],
			)
		);

		return array(
			'processed'   => true,
			'notice_type' => 'success',
			'message'     => sprintf(
				/* translators: %d order ID. */
				__( 'BookFunnel resend processed for order #%d.', 'bookfunnel' ),
				(int) $order->get_id()
			),
		);
	}

	/**
	 * Determine the event type to use for a new resend.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return string
	 */
	private function determine_event_type_from_order( $order ) {
		switch ( (string) $order->get_status() ) {
			case 'completed':
				return BF_WC_Notifier::EVENT_COMPLETED;

			case 'cancelled':
				return 'order.cancelled';

			case 'refunded':
				return 'order.refunded';
		}

		return '';
	}

	/**
	 * Fetch the most recent notification for an order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array
	 */
	private function get_latest_notification_for_order( $order_id ) {
		global $wpdb;

		$query      = $wpdb->prepare(
			"SELECT id, order_id, event_type, status, attempts, next_attempt_at, server_response, created_at, updated_at FROM {$wpdb->prefix}bf_wc_notifications WHERE order_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
			$order_id
		);
			$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Fetch a notification row by ID.
	 *
	 * @param int $notification_id Notification ID.
	 * @return array
	 */
	private function get_notification_row( $notification_id ) {
		global $wpdb;

		if ( ! $this->notification_table_exists() ) {
			return array();
		}

		$query      = $wpdb->prepare(
			"SELECT id, order_id, event_type, status, attempts, updated_at FROM {$wpdb->prefix}bf_wc_notifications WHERE id = %d LIMIT 1",
			$notification_id
		);
			$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( ! is_array( $result ) ) {
			return array();
		}

		return array(
			'id'         => isset( $result['id'] ) ? (int) $result['id'] : 0,
			'order_id'   => isset( $result['order_id'] ) ? (int) $result['order_id'] : 0,
			'event_type' => isset( $result['event_type'] ) ? (string) $result['event_type'] : '',
			'status'     => isset( $result['status'] ) ? (string) $result['status'] : '',
			'attempts'   => isset( $result['attempts'] ) ? (int) $result['attempts'] : 0,
			'updated_at' => isset( $result['updated_at'] ) ? (string) $result['updated_at'] : '',
		);
	}

	/**
	 * Reset an existing notification row for immediate retry.
	 *
	 * @param int $notification_id Notification ID.
	 * @return void
	 */
	private function reset_notification_row( $notification_id ) {
		global $wpdb;

		$updated_at = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bf_wc_notifications SET status = %s, attempts = %d, next_attempt_at = NULL, updated_at = %s WHERE id = %d",
			'pending',
			0,
			$updated_at,
			$notification_id
		);

			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above
	}

	/**
	 * Queue an admin notice for the current user.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function add_admin_notice( $type, $message ) {
		set_transient(
			$this->get_notice_transient_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Get the current user's admin notice transient key.
	 *
	 * @return string
	 */
	private function get_notice_transient_key() {
		return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
	}

	/**
	 * Render an admin-wide delivery health notice.
	 *
	 * @return void
	 */
	private function render_delivery_health_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( get_transient( $this->get_delivery_notice_dismiss_transient_key() ) ) {
			return;
		}

		$counts                = $this->get_delivery_counts();
		$abandoned_count       = (int) $counts['abandoned'];
		$repeated_failed_count = (int) $counts['repeated_failed'];

		if ( 0 === $abandoned_count && 0 === $repeated_failed_count ) {
			return;
		}

		$notice_class = $abandoned_count > 0 ? 'notice-error' : 'notice-warning';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p>
				<?php echo esc_html( $this->get_delivery_health_notice_message( $abandoned_count, $repeated_failed_count ) . ' ' ); ?>
				<a href="<?php echo esc_url( $this->get_deliveries_tab_url() ); ?>"><?php echo esc_html__( 'View deliveries →', 'bookfunnel' ); ?></a>
				<span aria-hidden="true"> | </span>
				<a href="<?php echo esc_url( $this->get_delivery_notice_dismiss_url() ); ?>"><?php echo esc_html__( 'Dismiss for 4 hours', 'bookfunnel' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Get the delivery health notice message.
	 *
	 * @param int $abandoned_count Abandoned delivery count.
	 * @param int $repeated_failed_count Repeated failed delivery count.
	 * @return string
	 */
	private function get_delivery_health_notice_message( $abandoned_count, $repeated_failed_count ) {
		if ( $abandoned_count > 0 && $repeated_failed_count > 0 ) {
			return sprintf(
				/* translators: 1: abandoned clause, 2: repeated failure clause. */
				__( '⚠ BookFunnel: %1$s and %2$s.', 'bookfunnel' ),
				$this->get_abandoned_notice_clause( $abandoned_count ),
				$this->get_failed_notice_clause( $repeated_failed_count )
			);
		}

		if ( $abandoned_count > 0 ) {
			return sprintf(
				/* translators: %s abandoned clause. */
				__( '⚠ BookFunnel: %s. Readers may not have received their books.', 'bookfunnel' ),
				$this->get_abandoned_notice_clause( $abandoned_count )
			);
		}

		return sprintf(
			/* translators: %s repeated failure clause. */
			__( '⚠ BookFunnel: %s.', 'bookfunnel' ),
			$this->get_failed_notice_clause( $repeated_failed_count )
		);
	}

	/**
	 * Get the abandoned notice clause.
	 *
	 * @param int $count Delivery count.
	 * @return string
	 */
	private function get_abandoned_notice_clause( $count ) {
		return sprintf(
			/* translators: %d abandoned delivery count. */
			_n( '%d order delivery has been abandoned', '%d order deliveries have been abandoned', $count, 'bookfunnel' ),
			$count
		);
	}

	/**
	 * Get the repeated failure notice clause.
	 *
	 * @param int $count Delivery count.
	 * @return string
	 */
	private function get_failed_notice_clause( $count ) {
		return sprintf(
			/* translators: %d repeated failed delivery count. */
			_n( '%d order delivery is failing repeatedly', '%d order deliveries are failing repeatedly', $count, 'bookfunnel' ),
			$count
		);
	}

	/**
	 * Dismiss the delivery health notice for the current user.
	 *
	 * @return void
	 */
	private function dismiss_delivery_health_notice() {
		set_transient( $this->get_delivery_notice_dismiss_transient_key(), 1, self::HEALTH_NOTICE_DISMISS_TTL );

		$redirect_url = wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = admin_url();
		}

		$redirect_url = remove_query_arg( array( 'bf_wc_dismiss_delivery_notice', '_wpnonce' ), $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get the current user's delivery health notice dismissal transient key.
	 *
	 * @return string
	 */
	private function get_delivery_notice_dismiss_transient_key() {
		return self::HEALTH_NOTICE_DISMISS_PREFIX . get_current_user_id();
	}

	/**
	 * Get the delivery health notice dismissal URL.
	 *
	 * @return string
	 */
	private function get_delivery_notice_dismiss_url() {
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url();
		$current_url = remove_query_arg( array( 'bf_wc_dismiss_delivery_notice', '_wpnonce' ), $current_url );

		return wp_nonce_url(
			add_query_arg( 'bf_wc_dismiss_delivery_notice', '1', $current_url ),
			'bf_wc_dismiss_delivery_notice'
		);
	}
}
