<?php
/**
 * Thank-you page and email HTML injection for BookFunnel deliveries.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles rendering BookFunnel delivery messaging on thank-you page and emails.
 */
class BF_WC_ThankYou {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_thankyou', array( $this, 'render_thankyou_html' ), 10, 1 );
		add_action( 'woocommerce_email_order_details', array( $this, 'render_email_html' ), 10, 4 );
	}

	/**
	 * Render delivery message on the thank-you page.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function render_thankyou_html( $order_id ) {
		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return;
		}

		$this->output_delivery_html( $order, 'thankyou' );
	}

	/**
	 * Render delivery message in customer emails when enabled.
	 *
	 * @param WC_Order $order         WooCommerce order object.
	 * @param bool     $sent_to_admin Whether the email is for the admin.
	 * @param bool     $plain_text    Whether the email is plain text.
	 * @param object   $email         WooCommerce email object.
	 * @return void
	 */
	public function render_email_html( $order, $sent_to_admin, $plain_text, $email ) {
		unset( $email );

		if ( $sent_to_admin || $plain_text || ! $this->is_email_injection_enabled() ) {
			return;
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->output_delivery_html( $order, 'email' );
	}

	/**
	 * Output BookFunnel delivery messaging for an order.
	 *
	 * @param WC_Order $order    WooCommerce order object.
	 * @param string   $location Injection location.
	 * @return void
	 */
	private function output_delivery_html( $order, $location ) {
		$order_id        = (int) $order->get_id();
		$fulfilled_count = 0;

		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === $item->get_meta( '_bf_fulfilled' ) ) {
				++$fulfilled_count;
			}
		}

		if ( 0 === $fulfilled_count ) {
			return;
		}

		$email = $order->get_billing_email();
		$book  = 1 === $fulfilled_count ? 'book' : 'books';

		$html = sprintf(
			'<p>We\'ve sent a link to <strong>%s</strong> with instructions on how to download your %s. Please check your inbox to get started.</p>',
			esc_html( $email ),
			esc_html( $book )
		);

		echo '<div class="bf-wc-delivery">' . wp_kses(
			$html,
			array(
				'p'      => array(),
				'strong' => array(),
			)
		) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is sanitized with wp_kses().

		BF_WC_Logger::info(
			'BookFunnel delivery message injected successfully.',
			array(
				'order_id'        => $order_id,
				'location'        => $location,
				'fulfilled_count' => $fulfilled_count,
			)
		);
	}

	/**
	 * Determine whether email injection is enabled.
	 *
	 * @return bool
	 */
	private function is_email_injection_enabled() {
		return filter_var( get_option( 'bf_wc_email_injection', true ), FILTER_VALIDATE_BOOLEAN );
	}
}
