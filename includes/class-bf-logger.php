<?php
/**
 * Logger wrapper for BookFunnel for WooCommerce.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around WC_Logger for BookFunnel log entries.
 */
class BF_WC_Logger {
	/**
	 * WooCommerce log source.
	 *
	 * @var string
	 */
	const SOURCE = 'bookfunnel-woocommerce';

	/**
	 * Write an informational message.
	 *
	 * @param string $message Log message in plain English.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function info( $message, array $context = array() ) {
		self::write( 'info', $message, $context );
	}

	/**
	 * Write a warning message.
	 *
	 * @param string $message Log message in plain English.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function warning( $message, array $context = array() ) {
		self::write( 'warning', $message, $context );
	}

	/**
	 * Write an error message.
	 *
	 * @param string $message Log message in plain English.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	public static function error( $message, array $context = array() ) {
		self::write( 'error', $message, $context );
	}

	/**
	 * Write a log entry to WooCommerce logs.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message in plain English.
	 * @param array  $context Optional structured context data.
	 * @return void
	 */
	private static function write( $level, $message, array $context = array() ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();

		if ( ! is_callable( array( $logger, $level ) ) ) {
			return;
		}

		$logger->{$level}( $message, self::prepare_context( $context ) );
	}

	/**
	 * Prepare the log context.
	 *
	 * @param array $context Optional structured context data.
	 * @return array
	 */
	private static function prepare_context( array $context ) {
		$context['source'] = self::SOURCE;

		return $context;
	}
}
