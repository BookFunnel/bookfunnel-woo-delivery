<?php
/**
 * Order-specific delivery link lookup and caching for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and caches an order-specific BookFunnel delivery link.
 */
class BF_WC_Delivery_Link {
	/**
	 * Order meta key used to cache a resolved delivery link.
	 *
	 * @var string
	 */
	const META_KEY_LINK_URL = '_bf_wc_delivery_link_url';

	/**
	 * Default block title.
	 *
	 * @var string
	 */
	const DEFAULT_TITLE = 'Your order is ready';

	/**
	 * Default block body text.
	 *
	 * @var string
	 */
	const DEFAULT_BODY_TEXT = "You'll be taken to BookFunnel, our delivery partner, to get your books, audiobooks, or other files on any device.";

	/**
	 * Default button label.
	 *
	 * @var string
	 */
	const DEFAULT_BUTTON_TEXT = 'Take me to my downloads';

	/**
	 * Default "delivered by" label / logo alt text.
	 *
	 * @var string
	 */
	const DEFAULT_DELIVERED_BY = 'Delivered by BookFunnel';

	/**
	 * Default "delivered by" logo URL.
	 *
	 * @var string
	 */
	const DEFAULT_DELIVERED_BY_LOGO_URL = 'https://static.bookfunnel.com/images/delivered_by_black_grey_288.png';

	/**
	 * Get delivery block data for an order, using a cached link when available.
	 *
	 * @param WC_Order $order        WooCommerce order object.
	 * @param string   $purchase_uid Store-level BookFunnel purchase UID.
	 * @param string[] $skus         SKUs for the order's BookFunnel-deliverable line items.
	 * @return array
	 */
	public static function get_delivery_data( $order, $purchase_uid, array $skus ) {
		$cached_link_url = (string) $order->get_meta( self::META_KEY_LINK_URL );

		if ( '' !== $cached_link_url ) {
			return self::build_data( $cached_link_url );
		}

		$request_url = add_query_arg(
			array(
				'id'    => $order->get_id(),
				'items' => implode( ',', $skus ),
			),
			'https://purchase.bookfunnel.com/' . rawurlencode( $purchase_uid ) . '/woocommerce'
		);

		$response = wp_remote_get( $request_url, array( 'timeout' => 3 ) );
		$body     = self::parse_success_body( $response );

		if ( null === $body ) {
			BF_WC_Logger::info(
				'BookFunnel delivery link lookup did not return a usable link; using fallback link.',
				array(
					'order_id' => (int) $order->get_id(),
				)
			);

			return self::build_data( self::get_fallback_link_url( $order, $purchase_uid ) );
		}

		$order->update_meta_data( self::META_KEY_LINK_URL, $body['link_url'] );
		$order->save();

		return self::build_data( $body['link_url'], $body );
	}

	/**
	 * Parse a successful, well-formed delivery-link response body.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return array|null
	 */
	private static function parse_success_body( $response ) {
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data )
			|| ! isset( $data['status'], $data['link_url'] )
			|| 'OK' !== $data['status']
			|| ! is_string( $data['link_url'] )
			|| '' === $data['link_url']
		) {
			return null;
		}

		return $data;
	}

	/**
	 * Build the generic self-service fallback link for an order.
	 *
	 * @param WC_Order $order        WooCommerce order object.
	 * @param string   $purchase_uid Store-level BookFunnel purchase UID.
	 * @return string
	 */
	private static function get_fallback_link_url( $order, $purchase_uid ) {
		return add_query_arg(
			array( 'id' => $order->get_id() ),
			'https://purchase.bookfunnel.com/' . rawurlencode( $purchase_uid )
		);
	}

	/**
	 * Shape a full delivery-data array, applying local defaults for any missing copy fields.
	 *
	 * @param string $link_url  Resolved or fallback delivery link.
	 * @param array  $overrides Optional copy overrides from a successful response.
	 * @return array
	 */
	private static function build_data( $link_url, array $overrides = array() ) {
		return array(
			'link_url'              => $link_url,
			'title'                 => self::pick( $overrides, 'title', self::DEFAULT_TITLE ),
			'body_text'             => self::pick( $overrides, 'body_text', self::DEFAULT_BODY_TEXT ),
			'button_text'           => self::pick( $overrides, 'button_text', self::DEFAULT_BUTTON_TEXT ),
			'delivered_by'          => self::pick( $overrides, 'delivered_by', self::DEFAULT_DELIVERED_BY ),
			'delivered_by_logo_url' => self::pick( $overrides, 'delivered_by_logo_url', self::DEFAULT_DELIVERED_BY_LOGO_URL ),
		);
	}

	/**
	 * Pick a string override value, falling back to a default when absent or empty.
	 *
	 * @param array  $overrides     Overrides array.
	 * @param string $key           Key to look up.
	 * @param string $default_value Default value.
	 * @return string
	 */
	private static function pick( array $overrides, $key, $default_value ) {
		return ( isset( $overrides[ $key ] ) && is_string( $overrides[ $key ] ) && '' !== $overrides[ $key ] ) ? $overrides[ $key ] : $default_value;
	}
}
