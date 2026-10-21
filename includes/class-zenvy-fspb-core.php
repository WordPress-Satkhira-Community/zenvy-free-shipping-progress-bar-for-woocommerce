<?php
/**
 * Core class: loads dependencies and exposes settings helpers.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Zenvy_FSPB_Core
 */
final class Zenvy_FSPB_Core {

	/**
	 * Singleton instance.
	 *
	 * @var Zenvy_FSPB_Core|null
	 */
	private static $instance = null;

	/**
	 * Request-level settings cache.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Zenvy_FSPB_Core
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once ZENVY_FSPB_PATH . 'includes/class-zenvy-fspb-calculator.php';
		require_once ZENVY_FSPB_PATH . 'includes/class-zenvy-fspb-frontend.php';

		if ( is_admin() ) {
			require_once ZENVY_FSPB_PATH . 'includes/admin/class-zenvy-brand-menu.php';
			require_once ZENVY_FSPB_PATH . 'includes/admin/class-zenvy-fspb-settings.php';
		}
	}

	/**
	 * Register hooks and instantiate components.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'update_option_' . ZENVY_FSPB_OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'add_option_' . ZENVY_FSPB_OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'delete_option_' . ZENVY_FSPB_OPTION, array( __CLASS__, 'flush_cache' ) );

		new Zenvy_FSPB_Frontend();

		if ( is_admin() ) {
			new Zenvy_FSPB_Settings();
		}
	}

	/**
	 * Default settings. Must only be called on or after the init action,
	 * because default messages are translatable.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		$defaults = array(
			'calc_mode'       => 'auto',
			'manual_goal'     => 100,
			'deduct_coupons'  => 'no',
			'show_top_bar'    => 'yes',
			'top_bar_scope'   => 'all',
			'show_minicart'   => 'yes',
			'show_cart'       => 'yes',
			'show_checkout'   => 'yes',
			'hide_when_empty' => 'no',
			'msg_initial'     => __( 'Free shipping on orders over {goal}!', 'zenvy-free-shipping-progress-bar' ),
			'msg_progress'    => __( 'Add {remaining} more to unlock FREE Shipping!', 'zenvy-free-shipping-progress-bar' ),
			'msg_success'     => __( '🎉 Congratulations! You\'ve unlocked FREE Shipping!', 'zenvy-free-shipping-progress-bar' ),
			'preset'          => 'minimal',
			'color_bg'        => '#e9ecef',
			'color_fill'      => '#2ea44f',
			'color_text'      => '#1d2327',
			'border_radius'   => 20,
			'delete_data'     => 'no',
		);

		/**
		 * Filter the default settings. Used by the companion Pro plugin to add its own keys.
		 *
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'zenvy_fspb_default_settings', $defaults );
	}

	/**
	 * Get the saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null === self::$settings ) {
			$saved    = get_option( ZENVY_FSPB_OPTION, array() );
			$saved    = is_array( $saved ) ? $saved : array();
			$defaults = self::get_defaults();
			$settings = wp_parse_args( $saved, $defaults );

			foreach ( array( 'msg_initial', 'msg_progress', 'msg_success' ) as $key ) {
				if ( '' === trim( (string) $settings[ $key ] ) ) {
					$settings[ $key ] = $defaults[ $key ];
				}
			}

			/**
			 * Filter the resolved settings array.
			 *
			 * @param array $settings Settings merged with defaults.
			 */
			self::$settings = apply_filters( 'zenvy_fspb_settings', $settings );
		}

		return self::$settings;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key does not exist.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Clear the request-level settings cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$settings = null;
	}

	/**
	 * Whether a companion Pro plugin is active and has unlocked Pro features.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		/**
		 * Filter whether Pro features are unlocked.
		 *
		 * @param bool $is_pro Default false.
		 */
		return (bool) apply_filters( 'zenvy_fspb_is_pro_active', false );
	}
}
