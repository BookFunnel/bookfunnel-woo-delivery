<?php
/**
 * Notification handling for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends WooCommerce order events to BookFunnel.
 */
class BF_WC_Notifier {
	/**
	 * Meta key used to store fulfillment state on order items.
	 *
	 * @var string
	 */
	const ITEM_FULFILLED_META_KEY = '_bf_fulfilled';

	/**
	 * Event name for completed orders.
	 *
	 * @var string
	 */
	const EVENT_COMPLETED = 'order.completed';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_processing' ), 10, 1 );
	}

	/**
	 * Handle completed orders.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_completed( $order_id ) {
		if ( ! self::has_existing_notification( $order_id, self::EVENT_COMPLETED ) ) {
			self::queue_and_send_order_event( $order_id, self::EVENT_COMPLETED );
		}
	}

	/**
	 * Handle orders that reach processing status.
	 * Fires for mixed physical/digital orders where completed may not fire immediately.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_processing( $order_id ) {
		if ( ! self::has_existing_notification( $order_id, self::EVENT_COMPLETED ) ) {
			self::queue_and_send_order_event( $order_id, self::EVENT_COMPLETED );
		}
	}

	/**
	 * Queue and send an order event to BookFunnel.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type to send.
	 * @param array  $args       Optional arguments.
	 * @return array
	 */
	public static function queue_and_send_order_event( $order_id, $event_type, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'notification_id'  => 0,
				'suppress_logging' => false,
			)
		);

		$order_id        = absint( $order_id );
		$notification_id = absint( $args['notification_id'] );
		$order           = wc_get_order( $order_id );

		if ( ! $order ) {
			self::maybe_log(
				$args,
				'warning',
				'BookFunnel notification skipped because the WooCommerce order could not be loaded.',
				array(
					'order_id'      => $order_id,
					'event_type'    => $event_type,
					'response_code' => 0,
					'response_body' => 'Order not found.',
				)
			);

			return array(
				'success'         => false,
				'status'          => 'failed',
				'notification_id' => $notification_id,
			);
		}

		if ( ! $notification_id ) {
			$notification_id = self::insert_notification( $order_id, $event_type );
		}

		if ( ! $notification_id ) {
			self::maybe_log(
				$args,
				'error',
				'BookFunnel notification could not be queued because the notification record could not be created.',
				array(
					'order_id'      => $order_id,
					'event_type'    => $event_type,
					'response_code' => 0,
					'response_body' => 'Notification insert failed.',
				)
			);

			return array(
				'success'         => false,
				'status'          => 'failed',
				'notification_id' => 0,
			);
		}

		$base_url = trim( (string) get_option( 'bf_wc_webhook_url', '' ) );
		if ( '' === $base_url ) {
			$failure_result = self::handle_send_failure( $notification_id, 'Webhook URL not configured.' );

			self::maybe_log(
				$args,
				'warning',
				'BookFunnel notification failed because no webhook URL is configured.',
				array(
					'order_id'      => $order_id,
					'event_type'    => $event_type,
					'attempt'       => $failure_result['attempt'],
					'response_code' => 0,
					'response_body' => 'Webhook URL not configured.',
				)
			);

			return array(
				'success'         => false,
				'status'          => $failure_result['status'],
				'notification_id' => $notification_id,
			);
		}

		$webhook_url = untrailingslashit( $base_url ) . '/wooplugin';

		$payload = self::build_payload( $order, $event_type );
		$body    = wp_json_encode( $payload );

		if ( false === $body ) {
			$failure_result = self::handle_send_failure( $notification_id, 'Unable to encode the BookFunnel request payload.' );

			self::maybe_log(
				$args,
				'warning',
				'BookFunnel notification failed because the request payload could not be encoded as JSON.',
				array(
					'order_id'      => $order_id,
					'event_type'    => $event_type,
					'attempt'       => $failure_result['attempt'],
					'response_code' => 0,
					'response_body' => 'Unable to encode the BookFunnel request payload.',
				)
			);

			return array(
				'success'         => false,
				'status'          => $failure_result['status'],
				'notification_id' => $notification_id,
			);
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'        => $body,
				'headers'     => array(
					'Content-Type'              => 'application/json',
					'Accept'                    => 'application/json',
					'X-BookFunnel-Plugin-Token' => BF_WC_Auth::instance()->get_token(),
				),
				'timeout'     => 30,
				'data_format' => 'body',
			)
		);

		return self::handle_response( $order, $notification_id, $event_type, $response, $args );
	}

	/**
	 * Build the outbound payload for BookFunnel.
	 *
	 * @param WC_Order $order      WooCommerce order object.
	 * @param string   $event_type Event type to send.
	 * @return array
	 */
	private static function build_payload( $order, $event_type ) {
		$payload               = self::normalize_value( $order->get_data() );
		$payload['event']      = $event_type;
		$payload['line_items'] = self::get_line_items( $order );
		$payload['meta']       = array(
			'event'          => $event_type,
			'source'         => 'woo_exten',
			'plugin_version' => BF_WC_VERSION,
		);

		// Ensure date_paid is set — free orders may have no payment timestamp.
		if ( empty( $payload['date_paid'] ) || 'null' === $payload['date_paid'] ) {
			$payload['date_paid'] = $order->get_date_created()
				? $order->get_date_created()->date( 'c' )
				: gmdate( 'c' );
		}

		if ( 'order.refunded' === $event_type ) {
			$payload['refunded_line_items'] = self::get_refunded_line_items( $order );
		}

		return $payload;
	}

	/**
	 * Convert complex order data to JSON-safe values.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed
	 */
	private static function normalize_value( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::normalize_value( $item );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			if ( is_a( $value, 'WC_DateTime' ) ) {
				return $value->date( 'c' );
			}

			if ( method_exists( $value, 'get_data' ) ) {
				return self::normalize_value( $value->get_data() );
			}

			if ( method_exists( $value, '__toString' ) ) {
				return (string) $value;
			}

			return null;
		}

		return $value;
	}

	/**
	 * Build payload-ready line item data.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array
	 */
	private static function get_line_items( $order ) {
		$line_items = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();

			$line_items[] = array(
				'id'           => $item->get_id(),
				'name'         => $item->get_name(),
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'sku'          => $product ? (string) $product->get_sku() : '',
				'quantity'     => (int) $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'subtotal_tax' => $item->get_subtotal_tax(),
				'total'        => $item->get_total(),
				'total_tax'    => $item->get_total_tax(),
				'taxes'        => self::normalize_value( $item->get_taxes() ),
				'meta_data'    => self::normalize_value( $item->get_meta_data() ),
			);
		}

		return $line_items;
	}

	/**
	 * Build refunded line-item data for refund notifications.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array
	 */
	private static function get_refunded_line_items( $order ) {
		$refunded_items = array();

		foreach ( $order->get_refunds() as $refund ) {
			foreach ( $refund->get_items( 'line_item' ) as $item ) {
				$order_item_id = absint( $item->get_meta( '_refunded_item_id', true ) );
				$quantity      = absint( $item->get_quantity() );
				$sku           = '';

				if ( $order_item_id ) {
					$original_item = $order->get_item( $order_item_id );

					if ( $original_item && $original_item->get_product() ) {
						$sku = (string) $original_item->get_product()->get_sku();
					}
				}

				if ( ! $sku && $item->get_product() ) {
					$sku = (string) $item->get_product()->get_sku();
				}

				if ( 0 === $quantity ) {
					continue;
				}

				if ( ! isset( $refunded_items[ $order_item_id ] ) ) {
					$refunded_items[ $order_item_id ] = array(
						'order_item_id' => $order_item_id,
						'sku'           => $sku,
						'quantity'      => 0,
					);
				}

				$refunded_items[ $order_item_id ]['quantity'] += $quantity;
			}
		}

		return array_values( $refunded_items );
	}

	/**
	 * Persist a new pending notification record.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type to queue.
	 * @return int
	 */
	private static function insert_notification( $order_id, $event_type ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}bf_wc_notifications (order_id, event_type, status, attempts, next_attempt_at, server_response, created_at, updated_at) VALUES (%d, %s, %s, %d, NULL, NULL, %s, %s)",
			$order_id,
			$event_type,
			'pending',
			0,
			$now,
			$now
		);

			$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( false === $result ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Check whether a notification already exists for this order and event type.
	 * Used to prevent duplicate notifications when both processing and completed hooks fire.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $event_type Event type.
	 * @return bool
	 */
	private static function has_existing_notification( $order_id, $event_type ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bf_wc_notifications WHERE order_id = %d AND event_type = %s AND status IN ('pending', 'done')",
			$order_id,
			$event_type
		);

			return (int) $wpdb->get_var( $query ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above
	}

	/**
	 * Handle the BookFunnel response.
	 *
	 * @param WC_Order       $order           WooCommerce order object.
	 * @param int            $notification_id Notification record ID.
	 * @param string         $event_type      Event type.
	 * @param array|WP_Error $response        HTTP response.
	 * @param array          $args            Call arguments.
	 * @return array
	 */
	private static function handle_response( $order, $notification_id, $event_type, $response, array $args ) {
		$order_id      = (int) $order->get_id();
		$response_code = 0;
		$response_body = '';
		$response_data = array();

		if ( is_wp_error( $response ) ) {
			$response_body = $response->get_error_message();
		} else {
			$response_code = (int) wp_remote_retrieve_response_code( $response );
			$response_body = (string) wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );
		}

		if ( 200 === $response_code && is_array( $response_data ) && ! empty( $response_data['ok'] ) ) {
			if ( self::EVENT_COMPLETED === $event_type ) {
				self::mark_fulfilled_items( $order, isset( $response_data['fulfilled'] ) && is_array( $response_data['fulfilled'] ) ? $response_data['fulfilled'] : array() );
			}

			self::mark_notification_done( $notification_id, $response_body );

			self::maybe_log(
				$args,
				'info',
				'BookFunnel notification sent successfully.',
				array(
					'order_id'      => $order_id,
					'event_type'    => $event_type,
					'response_code' => $response_code,
					'response_body' => $response_body,
				)
			);

			return array(
				'success'         => true,
				'status'          => 'done',
				'notification_id' => $notification_id,
			);
		}

		if ( empty( $response_body ) ) {
			$response_body = 'No response body returned.';
		}

		$failure_result = self::handle_send_failure( $notification_id, $response_body );

		self::maybe_log(
			$args,
			'warning',
			'BookFunnel notification failed and has been queued for retry.',
			array(
				'order_id'      => $order_id,
				'event_type'    => $event_type,
				'attempt'       => $failure_result['attempt'],
				'response_code' => $response_code,
				'response_body' => $response_body,
			)
		);

		return array(
			'success'         => false,
			'status'          => $failure_result['status'],
			'notification_id' => $notification_id,
		);
	}

	/**
	 * Mark a notification as successful.
	 *
	 * @param int    $notification_id Notification ID.
	 * @param string $response_body   Raw response body.
	 * @return void
	 */
	private static function mark_notification_done( $notification_id, $response_body ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$query = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bf_wc_notifications SET status = %s, next_attempt_at = NULL, server_response = %s, updated_at = %s WHERE id = %d",
			'done',
			$response_body,
			$now,
			$notification_id
		);

			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above
	}

	/**
	 * Mark a notification as failed and schedule the next attempt.
	 *
	 * @param int    $notification_id Notification ID.
	 * @param string $response_body   Raw response body or error string.
	 * @return array
	 */
	private static function handle_send_failure( $notification_id, $response_body ) {
		global $wpdb;

		$notification = self::get_notification( $notification_id );

		if ( empty( $notification ) ) {
			return array(
				'attempt' => 0,
				'status'  => 'failed',
			);
		}

		$updated_at                      = current_time( 'mysql', true );
		$attempt_number                  = (int) $notification['attempts'] + 1;
		$notification['server_response'] = $response_body;
		$notification['attempts']        = $attempt_number;

		if ( 6 <= $attempt_number ) {
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->prefix}bf_wc_notifications SET status = %s, attempts = %d, next_attempt_at = NULL, server_response = %s, updated_at = %s WHERE id = %d",
				'abandoned',
				$attempt_number,
				$response_body,
				$updated_at,
				$notification_id
			);

				$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above
			BF_WC_Cron::send_abandonment_email( $notification );

			return array(
				'attempt' => $attempt_number,
				'status'  => 'abandoned',
			);
		}

		$next_attempt_at = self::get_next_attempt_time( $attempt_number );

		$query = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}bf_wc_notifications SET status = %s, attempts = %d, next_attempt_at = %s, server_response = %s, updated_at = %s WHERE id = %d",
			'failed',
			$attempt_number,
			$next_attempt_at,
			$response_body,
			$updated_at,
			$notification_id
		);

			$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( 4 === $attempt_number ) {
			BF_WC_Cron::send_warning_email( $notification );
		}

		return array(
			'attempt' => $attempt_number,
			'status'  => 'failed',
		);
	}

	/**
	 * Fetch a notification row.
	 *
	 * @param int $notification_id Notification ID.
	 * @return array
	 */
	private static function get_notification( $notification_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT id, order_id, event_type, status, attempts, next_attempt_at, server_response, created_at, updated_at FROM {$wpdb->prefix}bf_wc_notifications WHERE id = %d LIMIT 1",
			$notification_id
		);

			$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is built with $wpdb->prepare() above

		if ( ! is_array( $result ) ) {
			return array();
		}

		return $result;
	}

	/**
	 * Get the next retry timestamp in GMT for a failure attempt.
	 *
	 * @param int $attempt_number Attempt number.
	 * @return string|null
	 */
	private static function get_next_attempt_time( $attempt_number ) {
		$delays = array(
			1 => HOUR_IN_SECONDS,
			2 => 4 * HOUR_IN_SECONDS,
			3 => 12 * HOUR_IN_SECONDS,
			4 => DAY_IN_SECONDS,
			5 => 2 * DAY_IN_SECONDS,
		);

		if ( empty( $delays[ $attempt_number ] ) ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', time() + $delays[ $attempt_number ] );
	}

	/**
	 * Write a log entry when logging is enabled for the call.
	 *
	 * @param array  $args    Call arguments.
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 * @return void
	 */
	private static function maybe_log( array $args, $level, $message, array $context ) {
		if ( ! empty( $args['suppress_logging'] ) ) {
			return;
		}

		if ( 'info' === $level ) {
			BF_WC_Logger::info( $message, $context );
			return;
		}

		if ( 'error' === $level ) {
			BF_WC_Logger::error( $message, $context );
			return;
		}

		BF_WC_Logger::warning( $message, $context );
	}

	/**
	 * Mark fulfilled items on the order by SKU.
	 *
	 * @param WC_Order $order          WooCommerce order object.
	 * @param array    $fulfilled_skus Fulfilled SKUs returned by BookFunnel.
	 * @return void
	 */
	private static function mark_fulfilled_items( $order, array $fulfilled_skus ) {
		if ( empty( $fulfilled_skus ) ) {
			return;
		}

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			$sku     = $product ? (string) $product->get_sku() : '';

			if ( '' === $sku || ! in_array( $sku, $fulfilled_skus, true ) ) {
				continue;
			}

			$item->update_meta_data( self::ITEM_FULFILLED_META_KEY, 'yes' );
			$item->save();
		}
	}
}
