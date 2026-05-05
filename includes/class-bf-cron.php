<?php
/**
 * Cron processing for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles retry queue processing and cron registration.
 */
class BF_WC_Cron {
	/**
	 * Cron schedule name.
	 *
	 * @var string
	 */
	const SCHEDULE = 'bf_wc_every_15_minutes';

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const HOOK = 'bf_wc_process_notification_queue';

	/**
	 * Maximum notifications processed per run.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );
		add_action( self::HOOK, array( $this, 'process_notification_queue' ) );
	}

	/**
	 * Register the custom 15 minute cron schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes (BookFunnel)', 'bookfunnel' ),
		);

		return $schedules;
	}

	/**
	 * Register the recurring cron event on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule_on_activation' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::SCHEDULE, self::HOOK );
		}

		remove_filter( 'cron_schedules', array( __CLASS__, 'register_schedule_on_activation' ) );
	}

	/**
	 * Clear the recurring cron event on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Register the custom schedule during activation.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_schedule_on_activation( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes (BookFunnel)', 'bookfunnel' ),
		);

		return $schedules;
	}

	/**
	 * Process queued notification retries.
	 *
	 * @return void
	 */
	public function process_notification_queue() {
		$notifications = $this->get_due_notifications();

		if ( empty( $notifications ) ) {
			return;
		}

		$summary = array(
			'processed' => 0,
			'succeeded' => 0,
			'failed'    => 0,
		);

		foreach ( $notifications as $notification ) {
			$result = BF_WC_Notifier::queue_and_send_order_event(
				(int) $notification['order_id'],
				$notification['event_type'],
				array(
					'notification_id'  => (int) $notification['id'],
					'suppress_logging' => true,
				)
			);

			++$summary['processed'];

			if ( ! empty( $result['success'] ) ) {
				++$summary['succeeded'];
			} else {
				++$summary['failed'];
			}
		}

		BF_WC_Logger::info( 'BookFunnel notification queue processed.', $summary );
	}

	/**
	 * Fetch due notifications from the retry queue.
	 *
	 * @return array
	 */
	private function get_due_notifications() {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$query = $wpdb->prepare(
			"SELECT id, order_id, event_type, status, attempts, next_attempt_at, server_response, created_at, updated_at FROM {$wpdb->prefix}bf_wc_notifications WHERE status IN (%s, %s) AND ( next_attempt_at IS NULL OR next_attempt_at <= %s ) ORDER BY next_attempt_at ASC, id ASC LIMIT %d",
			'pending',
			'failed',
			$now,
			self::BATCH_SIZE
		);

			$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( ! is_array( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Send the warning email for a delayed notification.
	 *
	 * @param array $notification_row Notification row data.
	 * @return void
	 */
	public static function send_warning_email( $notification_row ) {
		$order_id   = isset( $notification_row['order_id'] ) ? absint( $notification_row['order_id'] ) : 0;
		$order_link = self::get_order_edit_url( $order_id );

		$subject = sprintf( 'BookFunnel: order delivery delayed — order #%d', $order_id );
		$body    = implode(
			"\n\n",
			array(
				sprintf( 'Order #%d has not been confirmed by BookFunnel after 24 hours.', $order_id ),
				'The plugin has continued retrying delivery, but BookFunnel has not yet confirmed receipt of this order notification.',
				sprintf( 'Review the WooCommerce order here: %s', $order_link ),
				'Please check the BookFunnel for WooCommerce log and contact BookFunnel support if the problem continues.',
			)
		);

		self::send_admin_email( $subject, $body );

		BF_WC_Logger::warning(
			'BookFunnel delay warning email sent to the store administrator.',
			array(
				'notification_id' => isset( $notification_row['id'] ) ? (int) $notification_row['id'] : 0,
				'order_id'        => $order_id,
				'event_type'      => isset( $notification_row['event_type'] ) ? $notification_row['event_type'] : '',
			)
		);
	}

	/**
	 * Send the abandonment email for a notification.
	 *
	 * @param array $notification_row Notification row data.
	 * @return void
	 */
	public static function send_abandonment_email( $notification_row ) {
		$order_id   = isset( $notification_row['order_id'] ) ? absint( $notification_row['order_id'] ) : 0;
		$order_link = self::get_order_edit_url( $order_id );

		$subject = sprintf( 'BookFunnel: order delivery delayed — order #%d', $order_id );
		$body    = implode(
			"\n\n",
			array(
				sprintf( 'Order #%d has been marked as abandoned because BookFunnel did not confirm receipt after repeated retries.', $order_id ),
				'The reader may not have received their book.',
				sprintf( 'Review the WooCommerce order here: %s', $order_link ),
				'Please check the BookFunnel for WooCommerce log and contact BookFunnel support as soon as possible.',
			)
		);

		self::send_admin_email( $subject, $body );

		BF_WC_Logger::error(
			'BookFunnel abandonment email sent to the store administrator.',
			array(
				'notification_id' => isset( $notification_row['id'] ) ? (int) $notification_row['id'] : 0,
				'order_id'        => $order_id,
				'event_type'      => isset( $notification_row['event_type'] ) ? $notification_row['event_type'] : '',
			)
		);
	}

	/**
	 * Send an email to the store administrator.
	 *
	 * @param string $subject Email subject.
	 * @param string $body    Email body.
	 * @return void
	 */
	private static function send_admin_email( $subject, $body ) {
		$admin_email = get_option( 'admin_email' );

		if ( ! is_email( $admin_email ) ) {
			return;
		}

		wp_mail( $admin_email, $subject, $body );
	}

	/**
	 * Build the WooCommerce order edit URL.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string
	 */
	private static function get_order_edit_url( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
			return $order->get_edit_order_url();
		}

		return admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' );
	}
}
