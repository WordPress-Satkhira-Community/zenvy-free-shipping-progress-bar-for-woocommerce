<?php
/**
 * Frontend rendering, fragments, shortcode and assets.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Zenvy_FSPB_Frontend
 */
class Zenvy_FSPB_Frontend {

	/**
	 * Whether the top bar was already printed in this request.
	 *
	 * @var bool
	 */
	private $top_bar_done = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Settings contain translatable defaults, so read them on init or later.
		add_action( 'init', array( $this, 'register_locations' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'zenvy_shipping_bar', array( $this, 'shortcode' ) );

		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_fragment' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_fragment' ) );
		add_action( 'wc_ajax_zenvy_fspb_refresh', array( $this, 'ajax_refresh' ) );
	}

	/**
	 * Hook the bar into the enabled placement locations.
	 *
	 * @return void
	 */
	public function register_locations() {
		$settings = Zenvy_FSPB_Core::get_settings();

		if ( 'yes' === $settings['show_top_bar'] ) {
			// Plug and play: print right after <body>, with a footer fallback for themes without wp_body_open().
			add_action( 'wp_body_open', array( $this, 'render_top_bar' ) );
			add_action( 'wp_footer', array( $this, 'render_top_bar_fallback' ), 5 );
		}

		if ( 'yes' === $settings['show_minicart'] ) {
			add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'render_bar' ) );
		}

		if ( 'yes' === $settings['show_cart'] ) {
			add_action( 'woocommerce_before_cart_table', array( $this, 'render_bar' ) );
			add_filter( 'render_block_woocommerce/cart', array( $this, 'prepend_to_block' ) );
		}

		if ( 'yes' === $settings['show_checkout'] ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'render_bar' ) );
			add_filter( 'render_block_woocommerce/checkout', array( $this, 'prepend_to_block' ) );
		}
	}

	/**
	 * Register (and conditionally enqueue) frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'zenvy-fspb-frontend', ZENVY_FSPB_URL . 'assets/css/frontend.css', array(), ZENVY_FSPB_VERSION );
		wp_add_inline_style( 'zenvy-fspb-frontend', self::get_inline_css() );

		wp_register_script( 'zenvy-fspb-frontend', ZENVY_FSPB_URL . 'assets/js/frontend.js', array(), ZENVY_FSPB_VERSION, true );

		$refresh_url = class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'zenvy_fspb_refresh' ) : '';
		wp_localize_script(
			'zenvy-fspb-frontend',
			'zenvyFspb',
			array(
				'refreshUrl' => $refresh_url,
			)
		);

		$settings = Zenvy_FSPB_Core::get_settings();
		if ( 'yes' === $settings['show_top_bar'] || 'yes' === $settings['show_minicart'] || 'yes' === $settings['show_cart'] || 'yes' === $settings['show_checkout'] ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * Enqueue the registered frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'zenvy-fspb-frontend' );
		wp_enqueue_script( 'zenvy-fspb-frontend' );
	}

	/**
	 * Build the CSS custom properties from settings.
	 *
	 * @return string
	 */
	private static function get_inline_css() {
		$settings = Zenvy_FSPB_Core::get_settings();
		$defaults = Zenvy_FSPB_Core::get_defaults();

		$bg     = self::safe_color( $settings['color_bg'], $defaults['color_bg'] );
		$fill   = self::safe_color( $settings['color_fill'], $defaults['color_fill'] );
		$text   = self::safe_color( $settings['color_text'], $defaults['color_text'] );
		$radius = min( 50, max( 0, absint( $settings['border_radius'] ) ) );

		$css = sprintf(
			':root{--zenvy-fspb-bg:%1$s;--zenvy-fspb-fill:%2$s;--zenvy-fspb-text:%3$s;--zenvy-fspb-radius:%4$dpx;}',
			$bg,
			$fill,
			$text,
			$radius
		);

		/**
		 * Filter the inline CSS variables.
		 *
		 * @param string $css      CSS string.
		 * @param array  $settings Plugin settings.
		 */
		return (string) apply_filters( 'zenvy_fspb_inline_css', $css, $settings );
	}

	/**
	 * Validate a hex color with a fallback.
	 *
	 * @param string $value    Color value.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	private static function safe_color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}

	/**
	 * Echo the bar for hook-based placements.
	 *
	 * @return void
	 */
	public function render_bar() {
		echo wp_kses( self::get_bar_html(), self::allowed_html() );
	}

	/**
	 * Print the top-of-page bar from the wp_body_open action.
	 *
	 * @return void
	 */
	public function render_top_bar() {
		$this->output_top_bar( false );
	}

	/**
	 * Print the top-of-page bar from wp_footer when the theme never fired wp_body_open().
	 * The frontend script then moves it to the top of the page.
	 *
	 * @return void
	 */
	public function render_top_bar_fallback() {
		$this->output_top_bar( true );
	}

	/**
	 * Output the top bar once per request.
	 *
	 * @param bool $fallback Whether the bar is printed in the footer and must be moved up.
	 * @return void
	 */
	private function output_top_bar( $fallback ) {
		if ( $this->top_bar_done || ! $this->should_show_top_bar() ) {
			return;
		}

		$this->top_bar_done = true;
		$html               = wp_kses( self::get_bar_html(), self::allowed_html() );

		if ( $fallback ) {
			?>
			<div class="zenvy-fspb-top-bar" id="zenvy-fspb-top-bar" data-zenvy-move="top"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses() above. ?></div>
			<?php
			return;
		}
		?>
		<div class="zenvy-fspb-top-bar" id="zenvy-fspb-top-bar"><?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses() above. ?></div>
		<?php
	}

	/**
	 * Decide whether the top bar should appear on the current request.
	 *
	 * @return bool
	 */
	private function should_show_top_bar() {
		if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() ) {
			return false;
		}

		$settings = Zenvy_FSPB_Core::get_settings();

		// Avoid showing two bars when the cart/checkout page already has its own bar.
		if ( function_exists( 'is_cart' ) && is_cart() && 'yes' === $settings['show_cart'] ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && 'yes' === $settings['show_checkout'] ) {
			return false;
		}

		if ( 'woocommerce' === $settings['top_bar_scope'] ) {
			$is_store_page = ( function_exists( 'is_woocommerce' ) && is_woocommerce() )
				|| ( function_exists( 'is_cart' ) && is_cart() )
				|| ( function_exists( 'is_checkout' ) && is_checkout() );
			if ( ! $is_store_page ) {
				return false;
			}
		}

		/**
		 * Filter whether the top bar is shown on the current request.
		 *
		 * @param bool  $show     Whether to show the top bar.
		 * @param array $settings Plugin settings.
		 */
		return (bool) apply_filters( 'zenvy_fspb_show_top_bar', true, $settings );
	}

	/**
	 * Prepend the bar to the Cart / Checkout block output.
	 *
	 * @param string $content Block content.
	 * @return string
	 */
	public function prepend_to_block( $content ) {
		if ( is_admin() ) {
			return $content;
		}
		return wp_kses( self::get_bar_html(), self::allowed_html() ) . $content;
	}

	/**
	 * Shortcode callback: [zenvy_shipping_bar].
	 *
	 * @return string
	 */
	public function shortcode() {
		$this->enqueue_assets();
		return wp_kses( self::get_bar_html(), self::allowed_html() );
	}

	/**
	 * Add the bar to WooCommerce AJAX fragments.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array
	 */
	public function add_fragment( $fragments ) {
		$fragments['.zenvy-fspb-wrapper'] = wp_kses( self::get_bar_html(), self::allowed_html() );
		return $fragments;
	}

	/**
	 * Lightweight refresh endpoint (?wc-ajax=zenvy_fspb_refresh).
	 *
	 * @return void
	 */
	public function ajax_refresh() {
		nocache_headers();
		wp_send_json_success(
			array(
				'html' => wp_kses( self::get_bar_html(), self::allowed_html() ),
			)
		);
	}

	/**
	 * Build the bar markup for the current cart.
	 *
	 * @return string
	 */
	public static function get_bar_html() {
		$settings = Zenvy_FSPB_Core::get_settings();
		$data     = Zenvy_FSPB_Calculator::get_data();

		$is_hidden = ! $data['is_applicable'] || ( $data['is_empty'] && 'yes' === $settings['hide_when_empty'] );
		$preset    = in_array( $settings['preset'], array( 'minimal', 'striped' ), true ) ? $settings['preset'] : 'minimal';

		$classes = array(
			'zenvy-fspb-wrapper',
			'zenvy-fspb-preset-' . sanitize_html_class( $preset ),
		);
		if ( $is_hidden ) {
			$classes[] = 'zenvy-fspb-hidden';
		}
		if ( $data['is_empty'] ) {
			$classes[] = 'zenvy-fspb-is-empty';
		}
		if ( $data['is_unlocked'] ) {
			$classes[] = 'zenvy-fspb-is-unlocked';
		}

		$percentage = round( $data['percentage'], 2 );
		$message    = self::get_message( $data, $settings );

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-percentage="<?php echo esc_attr( $percentage ); ?>">
			<?php
			/**
			 * Fires before the bar message. Pro plugin can output extra content here.
			 *
			 * @param array $data     Progress data.
			 * @param array $settings Plugin settings.
			 */
			do_action( 'zenvy_fspb_before_bar_content', $data, $settings );
			?>
			<div class="zenvy-fspb-message" aria-live="polite"><?php echo wp_kses_post( $message ); ?></div>
			<div class="zenvy-fspb-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( round( $percentage ) ); ?>" aria-label="<?php echo esc_attr__( 'Free shipping progress', 'zenvy-free-shipping-progress-bar' ); ?>">
				<div class="zenvy-fspb-fill" style="width:<?php echo esc_attr( $percentage ); ?>%"></div>
			</div>
			<?php
			/**
			 * Fires after the progress track. Pro plugin can output milestones or upsells here.
			 *
			 * @param array $data     Progress data.
			 * @param array $settings Plugin settings.
			 */
			do_action( 'zenvy_fspb_after_bar_content', $data, $settings );
			?>
		</div>
		<?php
		$html = (string) ob_get_clean();

		/**
		 * Filter the final bar markup.
		 *
		 * @param string $html     Bar HTML.
		 * @param array  $data     Progress data.
		 * @param array  $settings Plugin settings.
		 */
		return (string) apply_filters( 'zenvy_fspb_bar_html', $html, $data, $settings );
	}

	/**
	 * Pick the message for the current state and parse placeholders.
	 *
	 * @param array $data     Progress data.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private static function get_message( $data, $settings ) {
		$key = 'msg_progress';
		if ( $data['is_empty'] ) {
			$key = 'msg_initial';
		} elseif ( $data['is_unlocked'] ) {
			$key = 'msg_success';
		}

		$replacements = array(
			'{goal}'       => wc_price( $data['goal'] ),
			'{remaining}'  => wc_price( $data['remaining'] ),
			'{cart_total}' => wc_price( $data['current_total'] ),
		);

		$message = wp_kses_post( strtr( (string) $settings[ $key ], $replacements ) );

		/**
		 * Filter the parsed message.
		 *
		 * @param string $message  Parsed message HTML.
		 * @param string $key      Message key (msg_initial, msg_progress, msg_success).
		 * @param array  $data     Progress data.
		 * @param array  $settings Plugin settings.
		 */
		return (string) apply_filters( 'zenvy_fspb_message', $message, $key, $data, $settings );
	}

	/**
	 * HTML allowed in the bar output.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		$allowed = array(
			'div'    => array(
				'class'           => true,
				'style'           => true,
				'role'            => true,
				'aria-live'       => true,
				'aria-label'      => true,
				'aria-valuemin'   => true,
				'aria-valuemax'   => true,
				'aria-valuenow'   => true,
				'data-percentage' => true,
			),
			'span'   => array(
				'class' => true,
			),
			'bdi'    => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'br'     => array(),
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
		);

		/**
		 * Filter the allowed HTML for bar output (e.g. to add Pro markup).
		 *
		 * @param array $allowed Allowed tags and attributes.
		 */
		return apply_filters( 'zenvy_fspb_allowed_html', $allowed );
	}
}
