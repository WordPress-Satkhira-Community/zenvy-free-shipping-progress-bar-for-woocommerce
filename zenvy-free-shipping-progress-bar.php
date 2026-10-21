<?php
/**
 * Plugin Name:       Zenvy Free Shipping Progress Bar for WooCommerce
 * Plugin URI:        https://delowerhossain/plugins/zenvy-free-shipping-progress-bar/
 * Description:       Increase your average order value with a smart free shipping progress bar. Auto-detects your WooCommerce free shipping threshold and updates in real time on the mini-cart, cart and checkout.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Delower Hossain
 * Author URI:        https://delowerhossain.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zenvy-free-shipping-progress-bar
 * WC requires at least: 7.0
 * WC tested up to:   9.3
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

define( 'ZENVY_FSPB_VERSION', '1.0.1' );
define( 'ZENVY_FSPB_FILE', __FILE__ );
define( 'ZENVY_FSPB_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZENVY_FSPB_URL', plugin_dir_url( __FILE__ ) );
define( 'ZENVY_FSPB_BASENAME', plugin_basename( __FILE__ ) );
define( 'ZENVY_FSPB_OPTION', 'zenvy_fspb_settings' );

/**
 * Declare compatibility with WooCommerce HPOS and Cart/Checkout Blocks.
 *
 * @return void
 */
function zenvy_fspb_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ZENVY_FSPB_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ZENVY_FSPB_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'zenvy_fspb_declare_compatibility' );

/**
 * Store default settings on activation (only when nothing is saved yet).
 *
 * @return void
 */
function zenvy_fspb_activate() {
	if ( false === get_option( ZENVY_FSPB_OPTION, false ) ) {
		require_once ZENVY_FSPB_PATH . 'includes/class-zenvy-fspb-core.php';
		add_option( ZENVY_FSPB_OPTION, Zenvy_FSPB_Core::get_defaults() );
	}
}
register_activation_hook( ZENVY_FSPB_FILE, 'zenvy_fspb_activate' );

/**
 * Show an admin notice when WooCommerce is not active.
 *
 * @return void
 */
function zenvy_fspb_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo esc_html__( 'Zenvy Free Shipping Progress Bar for WooCommerce requires WooCommerce to be installed and active.', 'zenvy-free-shipping-progress-bar' );
			?>
		</p>
	</div>
	<?php
}

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function zenvy_fspb_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'zenvy_fspb_missing_woocommerce_notice' );
		return;
	}

	require_once ZENVY_FSPB_PATH . 'includes/class-zenvy-fspb-core.php';
	Zenvy_FSPB_Core::instance();
}
add_action( 'plugins_loaded', 'zenvy_fspb_boot' );
