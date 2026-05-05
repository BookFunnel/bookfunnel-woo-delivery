<?php
/**
 * Plugin Name: BookFunnel
 * Description: Sell ebooks and audiobooks on your WooCommerce store and let BookFunnel handle the delivery.
 * Version: 1.0.1
 * Author: BookFunnel
 * Author URI: https://bookfunnel.com
 * Text Domain: bookfunnel
 * Domain Path: /languages
 *
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 10.7.0
 *
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BF_WC_VERSION', '0.1.0' );
define( 'BF_WC_PLUGIN_FILE', __FILE__ );
define( 'BF_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BF_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'BOOKFUNNEL_WOOCOMMERCE_MAIN_PLUGIN_FILE' ) ) {
	define( 'BOOKFUNNEL_WOOCOMMERCE_MAIN_PLUGIN_FILE', __FILE__ );
}

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

spl_autoload_register(
	function ( $class_name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- used within the closure body
		$prefix   = 'BookFunnelWooCommerce\\';
		$base_dir = plugin_dir_path( __FILE__ ) . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . 'class-' . strtolower(
			str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class )
		) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce fallback notice.
 *
 * @since 0.1.0
 */
function bookfunnel_woocommerce_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Bookfunnel requires WooCommerce to be installed and active. You can download %s here.', 'bookfunnel' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

register_activation_hook( __FILE__, 'bookfunnel_woocommerce_activate' );
register_deactivation_hook( __FILE__, 'bookfunnel_woocommerce_deactivate' );

/**
 * Activation hook.
 *
 * @since 0.1.0
 */
function bookfunnel_woocommerce_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bookfunnel_woocommerce_missing_wc_notice' );
		return;
	}

	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-activator.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-cron.php';

	BF_WC_Activator::activate();
	BF_WC_Cron::activate();
}

/**
 * Deactivation hook.
 *
 * @since 0.1.0
 * @return void
 */
function bookfunnel_woocommerce_deactivate() {
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-cron.php';

	BF_WC_Cron::deactivate();
}

if ( ! class_exists( 'BookfunnelWoocommerce' ) ) :
	/**
	 * The BookfunnelWoocommerce class.
	 */
	class BookfunnelWoocommerce {
		/**
		 * This class instance.
		 *
		 * @var \BookfunnelWoocommerce single instance of this class.
		 */
		private static $instance;

		/**
		 * Constructor.
		 */
		public function __construct() {
			BF_WC_Auth::instance();
			new BF_WC_Notifier();
			new BF_WC_ThankYou();
			new BF_WC_Cron();
			new BF_WC_Order_Events();

			if ( is_admin() ) {
				new BF_WC_Admin();
			}
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
				wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'bookfunnel' ), BF_WC_VERSION );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
				wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'bookfunnel' ), BF_WC_VERSION );
		}

		/**
		 * Gets the main instance.
		 *
		 * Ensures only one instance can be loaded.
		 *
		 * @return \bookfunnel_woocommerce
		 */
		public static function instance() {

			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}
endif;

add_action( 'plugins_loaded', 'bookfunnel_woocommerce_init', 10 );

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function bookfunnel_woocommerce_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bookfunnel_woocommerce_missing_wc_notice' );
		return;
	}

	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-activator.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-logger.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-auth.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-notifier.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-thankyou.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-cron.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-order-events.php';
	require_once BF_WC_PLUGIN_DIR . 'includes/class-bf-admin.php';

	BookfunnelWoocommerce::instance();
}
