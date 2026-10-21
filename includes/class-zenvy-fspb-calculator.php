<?php
/**
 * Free shipping goal and cart progress calculator.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Zenvy_FSPB_Calculator
 */
class Zenvy_FSPB_Calculator {

	/**
	 * Per-request cache of detected zone goals, keyed by destination hash.
	 *
	 * @var array
	 */
	private static $zone_goal_cache = array();

	/**
	 * Get the progress data for the current cart.
	 *
	 * Returned keys: goal, current_total, remaining, percentage (0-100), is_unlocked,
	 * plus is_empty and is_applicable helpers used by the renderer.
	 *
	 * @return array
	 */
	public static function get_data() {
		$data = array(
			'goal'          => 0.0,
			'current_total' => 0.0,
			'remaining'     => 0.0,
			'percentage'    => 0.0,
			'is_unlocked'   => false,
			'is_empty'      => true,
			'is_applicable' => false,
		);

		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			/** This filter is documented below. */
			return apply_filters( 'zenvy_fspb_calculator_data', $data );
		}

		$cart     = WC()->cart;
		$settings = Zenvy_FSPB_Core::get_settings();
		$goal     = self::get_goal( $settings );
		$is_empty = $cart->is_empty();
		$current  = $is_empty ? 0.0 : self::get_cart_progress_total( $settings );

		// A cart with no shippable products (virtual / downloadable only) never needs free shipping.
		// Products are checked directly because WC_Cart::needs_shipping() returns false on stores
		// that have no shipping methods configured yet, which would hide the bar on new stores.
		$is_applicable = $goal > 0 && ( $is_empty || self::cart_has_shippable_items( $cart ) );

		$remaining  = max( 0.0, $goal - $current );
		$percentage = $goal > 0 ? min( 100.0, max( 0.0, ( $current / $goal ) * 100 ) ) : 0.0;

		$data = array(
			'goal'          => (float) $goal,
			'current_total' => (float) $current,
			'remaining'     => (float) $remaining,
			'percentage'    => (float) $percentage,
			'is_unlocked'   => ( $goal > 0 && ! $is_empty && $current >= $goal ),
			'is_empty'      => $is_empty,
			'is_applicable' => $is_applicable,
		);

		/**
		 * Filter the calculated progress data.
		 *
		 * @param array $data     Progress data.
		 * @param array $settings Plugin settings.
		 */
		return apply_filters( 'zenvy_fspb_calculator_data', $data, $settings );
	}

	/**
	 * Whether the cart contains at least one product that requires shipping.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return bool
	 */
	private static function cart_has_shippable_items( $cart ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product && $cart_item['data']->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve the free shipping goal amount.
	 *
	 * @param array $settings Plugin settings.
	 * @return float
	 */
	public static function get_goal( $settings ) {
		$goal = 0.0;

		if ( 'auto' === $settings['calc_mode'] ) {
			$goal = self::get_zone_goal();
		}

		if ( $goal <= 0 ) {
			$goal = max( 0.0, floatval( $settings['manual_goal'] ) );
		}

		/**
		 * Filter the final goal amount (useful for multi-currency plugins).
		 *
		 * @param float $goal     Goal amount.
		 * @param array $settings Plugin settings.
		 */
		return (float) apply_filters( 'zenvy_fspb_goal', $goal, $settings );
	}

	/**
	 * Get the current cart amount that counts towards the goal.
	 *
	 * @param array $settings Plugin settings.
	 * @return float
	 */
	public static function get_cart_progress_total( $settings ) {
		$cart  = WC()->cart;
		$total = (float) $cart->get_displayed_subtotal();

		if ( 'yes' === $settings['deduct_coupons'] ) {
			$discount = (float) $cart->get_discount_total();
			if ( $cart->display_prices_including_tax() ) {
				$discount += (float) $cart->get_discount_tax();
			}
			$total -= $discount;
		}

		return max( 0.0, round( $total, wc_get_price_decimals() ) );
	}

	/**
	 * Detect the minimum amount of the free shipping method in the customer's matched zone.
	 *
	 * @return float Goal amount, or 0 when no usable free shipping method is found.
	 */
	private static function get_zone_goal() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) || ! function_exists( 'WC' ) || null === WC()->customer ) {
			return 0.0;
		}

		$customer    = WC()->customer;
		$destination = array(
			'country'   => $customer->get_shipping_country(),
			'state'     => $customer->get_shipping_state(),
			'postcode'  => $customer->get_shipping_postcode(),
			'city'      => $customer->get_shipping_city(),
			'address'   => $customer->get_shipping_address(),
			'address_1' => $customer->get_shipping_address_1(),
			'address_2' => $customer->get_shipping_address_2(),
		);

		$cache_key = md5( wp_json_encode( $destination ) );
		if ( isset( self::$zone_goal_cache[ $cache_key ] ) ) {
			return self::$zone_goal_cache[ $cache_key ];
		}

		$goal = 0.0;
		$zone = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => $destination ) );

		if ( $zone instanceof WC_Shipping_Zone ) {
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( 'free_shipping' !== $method->id ) {
					continue;
				}

				$requires = $method->get_option( 'requires' );
				if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
					continue;
				}

				$min_amount = (float) wc_format_decimal( $method->get_option( 'min_amount' ) );
				if ( $min_amount > 0 && ( 0.0 === $goal || $min_amount < $goal ) ) {
					$goal = $min_amount;
				}
			}
		}

		self::$zone_goal_cache[ $cache_key ] = $goal;

		return $goal;
	}
}
