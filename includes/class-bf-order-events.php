<?php
/**
 * Cancel and refund event handling for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends cancel and refund order events to BookFunnel.
 */
class BF_WC_Order_Events {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'handle_order_cancelled' ), 10, 1 );
		add_action( 'woocommerce_refund_created', array( $this, 'handle_order_refunded' ), 10, 2 );
	}

	/**
	 * Handle cancelled orders.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_order_cancelled( $order_id ) {
		BF_WC_Notifier::queue_and_send_order_event( $order_id, 'order.cancelled' );
	}

	/**
	 * Handle refunded orders.
	 *
	 * @param int   $refund_id WooCommerce refund ID.
	 * @param array $args      Refund creation arguments.
	 * @return void
	 */
	public function handle_order_refunded( $refund_id, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- required by woocommerce_refund_created hook signature
		$refund = wc_get_order( absint( $refund_id ) );

		if ( ! $refund || ! is_a( $refund, 'WC_Order_Refund' ) ) {
			return;
		}

		$order_id = (int) $refund->get_parent_id();

		if ( $order_id > 0 ) {
			BF_WC_Notifier::queue_and_send_order_event( $order_id, 'order.refunded' );
		}
	}
}
