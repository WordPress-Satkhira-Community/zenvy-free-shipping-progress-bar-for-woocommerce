<?php
/**
 * Admin settings page, saving and sanitization.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Zenvy_FSPB_Settings
 */
class Zenvy_FSPB_Settings {

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'zenvy-free-shipping-bar';

	/**
	 * Capability required to manage the plugin.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Transient prefix for the "saved" notice.
	 */
	const NOTICE_PREFIX = 'zenvy_fspb_notice_';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_menu_item' ) );
		add_action( 'admin_post_zenvy_fspb_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . ZENVY_FSPB_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Register this plugin under the shared Zenvy menu.
	 *
	 * @return void
	 */
	public function register_menu_item() {
		Zenvy_Brand_Menu::add_item(
			array(
				'slug'        => self::PAGE_SLUG,
				'page_title'  => __( 'Free Shipping Progress Bar', 'zenvy-free-shipping-progress-bar' ),
				'menu_title'  => __( 'Free Shipping Bar', 'zenvy-free-shipping-progress-bar' ),
				'capability'  => self::CAPABILITY,
				'callback'    => array( $this, 'render_page' ),
				'description' => __( 'Show a live free shipping progress bar to boost order value.', 'zenvy-free-shipping-progress-bar' ),
				'version'     => ZENVY_FSPB_VERSION,
			)
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'zenvy-free-shipping-progress-bar' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Enqueue admin assets on this plugin's page only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$suffix = Zenvy_Brand_Menu::get_hook_suffix( self::PAGE_SLUG );
		if ( '' === $suffix || $suffix !== $hook ) {
			return;
		}

		// The live preview reuses the frontend bar styles.
		wp_enqueue_style( 'zenvy-fspb-frontend', ZENVY_FSPB_URL . 'assets/css/frontend.css', array(), ZENVY_FSPB_VERSION );
		wp_enqueue_style( 'zenvy-fspb-admin', ZENVY_FSPB_URL . 'assets/css/admin.css', array( 'zenvy-fspb-frontend' ), ZENVY_FSPB_VERSION );
		wp_enqueue_script( 'zenvy-fspb-admin', ZENVY_FSPB_URL . 'assets/js/admin.js', array(), ZENVY_FSPB_VERSION, true );

		$defaults = Zenvy_FSPB_Core::get_defaults();

		wp_localize_script(
			'zenvy-fspb-admin',
			'zenvyFspbAdmin',
			array(
				'defaults' => array(
					'msg_initial'  => $defaults['msg_initial'],
					'msg_progress' => $defaults['msg_progress'],
					'msg_success'  => $defaults['msg_success'],
				),
				'currency' => array(
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'format'   => get_woocommerce_price_format(),
					'decimals' => wc_get_price_decimals(),
					'decimal'  => wc_get_price_decimal_separator(),
					'thousand' => wc_get_price_thousand_separator(),
				),
				'i18n'     => array(
					'empty'    => __( 'Empty cart', 'zenvy-free-shipping-progress-bar' ),
					'progress' => __( 'In progress', 'zenvy-free-shipping-progress-bar' ),
					'unlocked' => __( 'Free shipping unlocked', 'zenvy-free-shipping-progress-bar' ),
					'hidden'   => __( 'Bar is hidden while the cart is empty', 'zenvy-free-shipping-progress-bar' ),
				),
			)
		);
	}

	/**
	 * Handle the settings form submission.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'zenvy-free-shipping-progress-bar' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'zenvy_fspb_save_settings', 'zenvy_fspb_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every field is sanitized individually in sanitize_settings().
		$raw = ( isset( $_POST['zenvy_fspb_settings'] ) && is_array( $_POST['zenvy_fspb_settings'] ) ) ? wp_unslash( $_POST['zenvy_fspb_settings'] ) : array();

		$clean = $this->sanitize_settings( $raw );

		/**
		 * Filter sanitized settings before they are saved.
		 *
		 * Runs after the capability and nonce checks, so a Pro plugin can safely read and
		 * sanitize its own fields here and add them to the returned array.
		 *
		 * @param array $clean Sanitized settings.
		 */
		$clean = apply_filters( 'zenvy_fspb_sanitize_settings', $clean );

		update_option( ZENVY_FSPB_OPTION, $clean );
		Zenvy_FSPB_Core::flush_cache();

		set_transient( self::NOTICE_PREFIX . get_current_user_id(), 'saved', 60 );

		/**
		 * Fires after the settings were saved.
		 *
		 * @param array $clean Saved settings.
		 */
		do_action( 'zenvy_fspb_settings_saved', $clean );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Sanitize the posted settings.
	 *
	 * @param array $input Unsanitized, unslashed input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = Zenvy_FSPB_Core::get_defaults();
		$current  = wp_parse_args( (array) get_option( ZENVY_FSPB_OPTION, array() ), $defaults );
		$input    = is_array( $input ) ? $input : array();
		$clean    = $current;

		$get = static function ( $key ) use ( $input ) {
			return ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) ? (string) $input[ $key ] : null;
		};

		// Calculation mode.
		$value = $get( 'calc_mode' );
		if ( null !== $value ) {
			$value              = sanitize_text_field( $value );
			$clean['calc_mode'] = in_array( $value, array( 'auto', 'manual' ), true ) ? $value : $defaults['calc_mode'];
		}

		// Manual goal.
		$value = $get( 'manual_goal' );
		if ( null !== $value ) {
			$clean['manual_goal'] = max( 0, floatval( sanitize_text_field( $value ) ) );
		}

		// Yes/no toggles.
		foreach ( array( 'deduct_coupons', 'show_top_bar', 'show_minicart', 'show_cart', 'show_checkout', 'hide_when_empty', 'delete_data' ) as $key ) {
			$value = $get( $key );
			if ( null !== $value ) {
				$clean[ $key ] = ( 'yes' === sanitize_text_field( $value ) ) ? 'yes' : 'no';
			}
		}

		// Top bar scope.
		$value = $get( 'top_bar_scope' );
		if ( null !== $value ) {
			$value                  = sanitize_text_field( $value );
			$clean['top_bar_scope'] = in_array( $value, array( 'all', 'woocommerce' ), true ) ? $value : $defaults['top_bar_scope'];
		}

		// Messages.
		foreach ( array( 'msg_initial', 'msg_progress', 'msg_success' ) as $key ) {
			$value = $get( $key );
			if ( null !== $value ) {
				$clean[ $key ] = trim( wp_kses_post( $value ) );
			}
		}

		// Preset.
		$value = $get( 'preset' );
		if ( null !== $value ) {
			$value           = sanitize_text_field( $value );
			$clean['preset'] = in_array( $value, array( 'minimal', 'striped' ), true ) ? $value : $defaults['preset'];
		}

		// Colors.
		foreach ( array( 'color_bg', 'color_fill', 'color_text' ) as $key ) {
			$value = $get( $key );
			if ( null !== $value ) {
				$color         = sanitize_hex_color( sanitize_text_field( $value ) );
				$clean[ $key ] = $color ? $color : $defaults[ $key ];
			}
		}

		// Border radius.
		$value = $get( 'border_radius' );
		if ( null !== $value ) {
			$clean['border_radius'] = min( 50, max( 0, absint( sanitize_text_field( $value ) ) ) );
		}

		return $clean;
	}

	/**
	 * Read and clear the one-time "saved" notice.
	 *
	 * @return bool
	 */
	private function pull_notice() {
		$key    = self::NOTICE_PREFIX . get_current_user_id();
		$notice = get_transient( $key );
		if ( false === $notice ) {
			return false;
		}
		delete_transient( $key );
		return true;
	}

	/**
	 * Admin tabs.
	 *
	 * @return array
	 */
	private function get_tabs() {
		$tabs = array(
			'general' => __( 'General & Goal', 'zenvy-free-shipping-progress-bar' ),
			'display' => __( 'Display & Messages', 'zenvy-free-shipping-progress-bar' ),
			'design'  => __( 'Design & Colors', 'zenvy-free-shipping-progress-bar' ),
			'pro'     => __( 'Free vs Pro', 'zenvy-free-shipping-progress-bar' ),
		);

		/**
		 * Filter the admin tabs. Extra tabs render through the zenvy_fspb_render_tab_{id} action.
		 *
		 * @param array $tabs Tab id => label.
		 */
		return apply_filters( 'zenvy_fspb_admin_tabs', $tabs );
	}

	/**
	 * Pro feature definitions shown as locked UI.
	 *
	 * @return array
	 */
	private function get_pro_features() {
		$features = array(
			'milestones' => array(
				'tab'         => 'general',
				'title'       => __( 'Multi-Tier Milestones', 'zenvy-free-shipping-progress-bar' ),
				'description' => __( 'Add several reward tiers on one bar: free shipping, a free gift or a discount.', 'zenvy-free-shipping-progress-bar' ),
			),
			'sticky_bar' => array(
				'tab'         => 'display',
				'title'       => __( 'Sticky Top Bar', 'zenvy-free-shipping-progress-bar' ),
				'description' => __( 'Keep the top bar fixed while customers scroll, with a close button and page rules.', 'zenvy-free-shipping-progress-bar' ),
			),
			'upsell'     => array(
				'tab'         => 'display',
				'title'       => __( '1-Click In-Bar Upsell Product', 'zenvy-free-shipping-progress-bar' ),
				'description' => __( 'Suggest a product inside the bar that closes the gap to free shipping.', 'zenvy-free-shipping-progress-bar' ),
			),
			'confetti'   => array(
				'tab'         => 'design',
				'title'       => __( 'Confetti Celebration Animation', 'zenvy-free-shipping-progress-bar' ),
				'description' => __( 'Celebrate with a confetti burst when the customer unlocks the reward.', 'zenvy-free-shipping-progress-bar' ),
			),
		);

		/**
		 * Filter the Pro feature list. Each item: tab, title, description.
		 *
		 * @param array $features Pro features keyed by id.
		 */
		return apply_filters( 'zenvy_fspb_pro_features', $features );
	}

	/**
	 * Upgrade URL.
	 *
	 * @return string
	 */
	private function get_upgrade_url() {
		/**
		 * Filter the Pro upgrade URL.
		 *
		 * @param string $url Upgrade URL.
		 */
		return (string) apply_filters( 'zenvy_fspb_upgrade_url', 'https://dbuggers.com/' );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'zenvy-free-shipping-progress-bar' ), '', array( 'response' => 403 ) );
		}

		$settings = Zenvy_FSPB_Core::get_settings();
		$tabs     = $this->get_tabs();
		$saved    = $this->pull_notice();
		?>
		<div class="wrap zenvy-fspb-admin" id="zenvy-fspb-admin">
			<h1 class="screen-reader-text"><?php echo esc_html__( 'Free Shipping Progress Bar', 'zenvy-free-shipping-progress-bar' ); ?></h1>

			<div class="zenvy-fspb-header">
				<div class="zenvy-fspb-brand">
					<span class="zenvy-fspb-logo" aria-hidden="true">Z</span>
					<div>
						<span class="zenvy-fspb-title"><?php echo esc_html__( 'Free Shipping Progress Bar', 'zenvy-free-shipping-progress-bar' ); ?></span>
						<span class="zenvy-fspb-subtitle"><?php echo esc_html__( 'by Zenvy for WooCommerce', 'zenvy-free-shipping-progress-bar' ); ?></span>
					</div>
				</div>
				<span class="zenvy-fspb-version">
					<?php
					/* translators: %s: plugin version number. */
					echo esc_html( sprintf( __( 'Version %s', 'zenvy-free-shipping-progress-bar' ), ZENVY_FSPB_VERSION ) );
					?>
				</span>
			</div>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'zenvy-free-shipping-progress-bar' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="zenvy-fspb-form">
				<input type="hidden" name="action" value="zenvy_fspb_save_settings" />
				<?php wp_nonce_field( 'zenvy_fspb_save_settings', 'zenvy_fspb_nonce' ); ?>

				<?php $this->render_preview( $settings ); ?>

				<div class="zenvy-fspb-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Settings sections', 'zenvy-free-shipping-progress-bar' ); ?>">
					<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
						<button type="button" class="zenvy-fspb-tab" role="tab" id="zenvy-fspb-tab-btn-<?php echo esc_attr( $tab_id ); ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>" aria-controls="zenvy-fspb-tab-<?php echo esc_attr( $tab_id ); ?>" aria-selected="false">
							<?php echo esc_html( $tab_label ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
					<section class="zenvy-fspb-panel" role="tabpanel" id="zenvy-fspb-tab-<?php echo esc_attr( $tab_id ); ?>" aria-labelledby="zenvy-fspb-tab-btn-<?php echo esc_attr( $tab_id ); ?>" hidden>
						<?php
						$method = 'render_tab_' . $tab_id;
						if ( method_exists( $this, $method ) ) {
							$this->$method( $settings );
						} else {
							/**
							 * Render a custom tab added through zenvy_fspb_admin_tabs.
							 *
							 * @param array $settings Current settings.
							 */
							do_action( 'zenvy_fspb_render_tab_' . $tab_id, $settings );
						}
						?>
					</section>
				<?php endforeach; ?>

				<div class="zenvy-fspb-savebar">
					<button type="submit" class="button button-primary button-hero"><?php echo esc_html__( 'Save Changes', 'zenvy-free-shipping-progress-bar' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the sticky live preview.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_preview( $settings ) {
		$preset = in_array( $settings['preset'], array( 'minimal', 'striped' ), true ) ? $settings['preset'] : 'minimal';
		?>
		<div class="zenvy-fspb-preview-card">
			<div class="zenvy-fspb-preview-head">
				<span class="zenvy-fspb-preview-label"><?php echo esc_html__( 'Live Preview', 'zenvy-free-shipping-progress-bar' ); ?></span>
				<div class="zenvy-fspb-preview-controls">
					<label for="zenvy-fspb-preview-range"><?php echo esc_html__( 'Simulate cart progress', 'zenvy-free-shipping-progress-bar' ); ?></label>
					<input type="range" id="zenvy-fspb-preview-range" min="0" max="100" value="45" />
					<span class="zenvy-fspb-preview-state" id="zenvy-fspb-preview-state" aria-live="polite"></span>
				</div>
			</div>
			<div class="zenvy-fspb-preview-stage" id="zenvy-fspb-preview-stage">
				<div class="zenvy-fspb-wrapper zenvy-fspb-preset-<?php echo esc_attr( $preset ); ?>" id="zenvy-fspb-preview">
					<div class="zenvy-fspb-message"></div>
					<div class="zenvy-fspb-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="45" aria-label="<?php echo esc_attr__( 'Free shipping progress preview', 'zenvy-free-shipping-progress-bar' ); ?>">
						<div class="zenvy-fspb-fill" style="width:45%"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * General & Goal tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_tab_general( $settings ) {
		?>
		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Free shipping goal', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<p class="zenvy-fspb-card__intro"><?php echo esc_html__( 'Choose how the amount customers need to reach is calculated.', 'zenvy-free-shipping-progress-bar' ); ?></p>

			<div class="zenvy-fspb-choice-group">
				<label class="zenvy-fspb-choice">
					<input type="radio" name="zenvy_fspb_settings[calc_mode]" value="auto" data-zenvy-setting="calc_mode" <?php checked( 'auto', $settings['calc_mode'] ); ?> />
					<span class="zenvy-fspb-choice__body">
						<strong><?php echo esc_html__( 'Auto-detect (recommended)', 'zenvy-free-shipping-progress-bar' ); ?></strong>
						<em><?php echo esc_html__( 'Reads the minimum amount of the Free Shipping method in the customer\'s matched WooCommerce shipping zone.', 'zenvy-free-shipping-progress-bar' ); ?></em>
					</span>
				</label>
				<label class="zenvy-fspb-choice">
					<input type="radio" name="zenvy_fspb_settings[calc_mode]" value="manual" data-zenvy-setting="calc_mode" <?php checked( 'manual', $settings['calc_mode'] ); ?> />
					<span class="zenvy-fspb-choice__body">
						<strong><?php echo esc_html__( 'Manual goal only', 'zenvy-free-shipping-progress-bar' ); ?></strong>
						<em><?php echo esc_html__( 'Always use the fixed amount below.', 'zenvy-free-shipping-progress-bar' ); ?></em>
					</span>
				</label>
			</div>

			<div class="zenvy-fspb-field zenvy-fspb-field--stack">
				<label class="zenvy-fspb-field__label" for="zenvy-fspb-manual-goal"><?php echo esc_html__( 'Manual fallback goal', 'zenvy-free-shipping-progress-bar' ); ?></label>
				<span class="zenvy-fspb-field__desc"><?php echo esc_html__( 'Used when Auto-detect is off, or when no free shipping minimum is found for the customer\'s zone.', 'zenvy-free-shipping-progress-bar' ); ?></span>
				<div class="zenvy-fspb-input-group">
					<span class="zenvy-fspb-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
					<input type="number" id="zenvy-fspb-manual-goal" name="zenvy_fspb_settings[manual_goal]" data-zenvy-setting="manual_goal" min="0" step="0.01" value="<?php echo esc_attr( $settings['manual_goal'] ); ?>" />
				</div>
			</div>
		</div>

		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Cart calculation', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<?php
			$this->field_toggle(
				'deduct_coupons',
				__( 'Deduct coupons and discounts', 'zenvy-free-shipping-progress-bar' ),
				__( 'Subtract coupon discounts from the cart subtotal when measuring progress. Carts with only virtual products never show the bar.', 'zenvy-free-shipping-progress-bar' ),
				$settings
			);
			?>
		</div>

		<?php $this->render_pro_features( 'general', $settings ); ?>

		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Data', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<?php
			$this->field_toggle(
				'delete_data',
				__( 'Delete data on uninstall', 'zenvy-free-shipping-progress-bar' ),
				__( 'Remove all plugin settings from the database when the plugin is deleted.', 'zenvy-free-shipping-progress-bar' ),
				$settings
			);
			?>
		</div>
		<?php
	}

	/**
	 * Display & Messages tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_tab_display( $settings ) {
		?>
		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Where to show the bar', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<?php
			$this->field_toggle( 'show_top_bar', __( 'Top of page bar (recommended)', 'zenvy-free-shipping-progress-bar' ), __( 'Shows the bar at the very top of your store automatically. No setup or shortcode needed.', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_select(
				'top_bar_scope',
				__( 'Show the top bar on', 'zenvy-free-shipping-progress-bar' ),
				array(
					'all'         => __( 'Entire site', 'zenvy-free-shipping-progress-bar' ),
					'woocommerce' => __( 'WooCommerce pages only (shop, product, cart, checkout)', 'zenvy-free-shipping-progress-bar' ),
				),
				$settings
			);
			$this->field_toggle( 'show_minicart', __( 'Mini-cart / side cart', 'zenvy-free-shipping-progress-bar' ), __( 'Above the buttons of the mini-cart widget.', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_toggle( 'show_cart', __( 'Cart page', 'zenvy-free-shipping-progress-bar' ), __( 'Above the cart table (classic cart and Cart block). When enabled, the top bar is not repeated on the cart page.', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_toggle( 'show_checkout', __( 'Checkout page', 'zenvy-free-shipping-progress-bar' ), __( 'Above the checkout form (classic checkout and Checkout block). When enabled, the top bar is not repeated on the checkout page.', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_toggle( 'hide_when_empty', __( 'Hide when the cart is empty', 'zenvy-free-shipping-progress-bar' ), __( 'Do not show the initial message while the cart has no products.', 'zenvy-free-shipping-progress-bar' ), $settings );
			?>
			<div class="zenvy-fspb-shortcode">
				<span class="zenvy-fspb-field__label"><?php echo esc_html__( 'Shortcode', 'zenvy-free-shipping-progress-bar' ); ?></span>
				<code>[zenvy_shipping_bar]</code>
				<span class="zenvy-fspb-field__desc"><?php echo esc_html__( 'Optional. Use it to place the bar exactly where you want, for example inside a page, widget or block theme template.', 'zenvy-free-shipping-progress-bar' ); ?></span>
			</div>
		</div>

		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Messages', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<p class="zenvy-fspb-card__intro">
				<?php echo esc_html__( 'Available placeholders:', 'zenvy-free-shipping-progress-bar' ); ?>
				<code>{goal}</code> <code>{remaining}</code> <code>{cart_total}</code>
			</p>
			<?php
			$this->field_textarea( 'msg_initial', __( 'Initial message (empty cart)', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_textarea( 'msg_progress', __( 'In-progress message', 'zenvy-free-shipping-progress-bar' ), $settings );
			$this->field_textarea( 'msg_success', __( 'Success message (unlocked)', 'zenvy-free-shipping-progress-bar' ), $settings );
			?>
		</div>

		<?php $this->render_pro_features( 'display', $settings ); ?>
		<?php
	}

	/**
	 * Design & Colors tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_tab_design( $settings ) {
		?>
		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Style preset', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<div class="zenvy-fspb-choice-group zenvy-fspb-choice-group--row">
				<label class="zenvy-fspb-choice">
					<input type="radio" name="zenvy_fspb_settings[preset]" value="minimal" data-zenvy-setting="preset" <?php checked( 'minimal', $settings['preset'] ); ?> />
					<span class="zenvy-fspb-choice__body">
						<span class="zenvy-fspb-mini zenvy-fspb-mini--minimal"><span></span></span>
						<strong><?php echo esc_html__( 'Minimal', 'zenvy-free-shipping-progress-bar' ); ?></strong>
						<em><?php echo esc_html__( 'Modern rounded pill bar.', 'zenvy-free-shipping-progress-bar' ); ?></em>
					</span>
				</label>
				<label class="zenvy-fspb-choice">
					<input type="radio" name="zenvy_fspb_settings[preset]" value="striped" data-zenvy-setting="preset" <?php checked( 'striped', $settings['preset'] ); ?> />
					<span class="zenvy-fspb-choice__body">
						<span class="zenvy-fspb-mini zenvy-fspb-mini--striped"><span></span></span>
						<strong><?php echo esc_html__( 'Striped', 'zenvy-free-shipping-progress-bar' ); ?></strong>
						<em><?php echo esc_html__( 'Animated diagonal stripes.', 'zenvy-free-shipping-progress-bar' ); ?></em>
					</span>
				</label>
			</div>
		</div>

		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Colors and shape', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<div class="zenvy-fspb-grid">
				<?php
				$this->field_color( 'color_bg', __( 'Bar background', 'zenvy-free-shipping-progress-bar' ), $settings );
				$this->field_color( 'color_fill', __( 'Progress fill', 'zenvy-free-shipping-progress-bar' ), $settings );
				$this->field_color( 'color_text', __( 'Message text', 'zenvy-free-shipping-progress-bar' ), $settings );
				?>
			</div>
			<div class="zenvy-fspb-field zenvy-fspb-field--stack">
				<label class="zenvy-fspb-field__label" for="zenvy-fspb-radius"><?php echo esc_html__( 'Border radius (px)', 'zenvy-free-shipping-progress-bar' ); ?></label>
				<div class="zenvy-fspb-range">
					<input type="range" id="zenvy-fspb-radius" name="zenvy_fspb_settings[border_radius]" data-zenvy-setting="border_radius" min="0" max="50" step="1" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" />
					<output for="zenvy-fspb-radius" id="zenvy-fspb-radius-output"><?php echo esc_html( $settings['border_radius'] ); ?></output>
				</div>
			</div>
		</div>

		<?php $this->render_pro_features( 'design', $settings ); ?>
		<?php
	}

	/**
	 * Free vs Pro tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_tab_pro( $settings ) {
		$rows = array(
			array( __( 'Auto-detect free shipping goal from shipping zones', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Manual fallback goal and coupon deduction', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Automatic top bar, mini-cart, cart, checkout and shortcode placements', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Cart and Checkout block support', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Minimal and striped style presets', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Live preview in the settings page', 'zenvy-free-shipping-progress-bar' ), true, true ),
			array( __( 'Multi-tier milestones (free gift or discount)', 'zenvy-free-shipping-progress-bar' ), false, true ),
			array( __( 'Sticky top bar (stays visible while scrolling)', 'zenvy-free-shipping-progress-bar' ), false, true ),
			array( __( 'Confetti celebration animation', 'zenvy-free-shipping-progress-bar' ), false, true ),
			array( __( '1-click in-bar upsell product', 'zenvy-free-shipping-progress-bar' ), false, true ),
		);
		?>
		<div class="zenvy-fspb-card">
			<h2><?php echo esc_html__( 'Free vs Pro', 'zenvy-free-shipping-progress-bar' ); ?></h2>
			<p class="zenvy-fspb-card__intro"><?php echo esc_html__( 'Everything you need to start is free. Pro adds advanced conversion tools.', 'zenvy-free-shipping-progress-bar' ); ?></p>
			<table class="zenvy-fspb-compare">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Feature', 'zenvy-free-shipping-progress-bar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Free', 'zenvy-free-shipping-progress-bar' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Pro', 'zenvy-free-shipping-progress-bar' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row[0] ); ?></td>
							<?php foreach ( array( $row[1], $row[2] ) as $included ) : ?>
								<td class="<?php echo esc_attr( $included ? 'is-yes' : 'is-no' ); ?>">
									<span aria-hidden="true"><?php echo wp_kses_post( $included ? '&#10003;' : '&mdash;' ); ?></span>
									<span class="screen-reader-text"><?php echo $included ? esc_html__( 'Included', 'zenvy-free-shipping-progress-bar' ) : esc_html__( 'Not included', 'zenvy-free-shipping-progress-bar' ); ?></span>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( ! Zenvy_FSPB_Core::is_pro_active() ) : ?>
				<p>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $this->get_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html__( 'Learn about Zenvy Pro', 'zenvy-free-shipping-progress-bar' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
		/**
		 * Fires at the end of the Free vs Pro tab.
		 *
		 * @param array $settings Current settings.
		 */
		do_action( 'zenvy_fspb_after_pro_tab', $settings );
	}

	/**
	 * Render the Pro feature fields (locked placeholders) belonging to a tab.
	 *
	 * @param string $tab      Tab id.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private function render_pro_features( $tab, $settings ) {
		$features = array();
		foreach ( $this->get_pro_features() as $id => $feature ) {
			if ( isset( $feature['tab'] ) && $tab === $feature['tab'] ) {
				$features[ $id ] = $feature;
			}
		}

		if ( empty( $features ) ) {
			return;
		}

		$is_pro = Zenvy_FSPB_Core::is_pro_active() && has_action( 'zenvy_fspb_render_pro_feature' );
		?>
		<div class="zenvy-fspb-card zenvy-fspb-card--pro">
			<h2>
				<?php echo esc_html__( 'Pro features', 'zenvy-free-shipping-progress-bar' ); ?>
				<span class="zenvy-fspb-pro-badge">PRO</span>
			</h2>
			<?php foreach ( $features as $id => $feature ) : ?>
				<?php
				if ( $is_pro ) {
					/**
					 * Render an unlocked Pro field. The Pro plugin outputs its own controls here.
					 *
					 * @param string $id       Feature id.
					 * @param array  $feature  Feature definition.
					 * @param array  $settings Current settings.
					 */
					do_action( 'zenvy_fspb_render_pro_feature', $id, $feature, $settings );
					continue;
				}
				?>
				<div class="zenvy-fspb-field zenvy-fspb-field--toggle is-locked" aria-disabled="true">
					<div class="zenvy-fspb-field__text">
						<span class="zenvy-fspb-field__label">
							<?php echo esc_html( $feature['title'] ); ?>
							<span class="zenvy-fspb-pro-badge">PRO</span>
						</span>
						<span class="zenvy-fspb-field__desc"><?php echo esc_html( $feature['description'] ); ?></span>
					</div>
					<label class="zenvy-fspb-switch is-disabled">
						<input type="checkbox" disabled="disabled" />
						<span class="zenvy-fspb-switch__slider"></span>
						<span class="screen-reader-text">
							<?php
							/* translators: %s: Pro feature name. */
							echo esc_html( sprintf( __( '%s (Pro feature, locked)', 'zenvy-free-shipping-progress-bar' ), $feature['title'] ) );
							?>
						</span>
					</label>
				</div>
			<?php endforeach; ?>
			<?php if ( ! $is_pro ) : ?>
				<a class="zenvy-fspb-pro-link" href="<?php echo esc_url( $this->get_upgrade_url() ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'Unlock with Zenvy Pro', 'zenvy-free-shipping-progress-bar' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a yes/no toggle field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param string $description Help text.
	 * @param array  $settings    Current settings.
	 * @return void
	 */
	private function field_toggle( $key, $label, $description, $settings ) {
		$name = 'zenvy_fspb_settings[' . $key . ']';
		?>
		<div class="zenvy-fspb-field zenvy-fspb-field--toggle">
			<div class="zenvy-fspb-field__text">
				<span class="zenvy-fspb-field__label"><?php echo esc_html( $label ); ?></span>
				<span class="zenvy-fspb-field__desc"><?php echo esc_html( $description ); ?></span>
			</div>
			<label class="zenvy-fspb-switch">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no" />
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="yes" data-zenvy-setting="<?php echo esc_attr( $key ); ?>" <?php checked( 'yes', $settings[ $key ] ); ?> />
				<span class="zenvy-fspb-switch__slider"></span>
				<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Render a message textarea.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private function field_textarea( $key, $label, $settings ) {
		$id = 'zenvy-fspb-' . $key;
		?>
		<div class="zenvy-fspb-field zenvy-fspb-field--stack">
			<label class="zenvy-fspb-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea id="<?php echo esc_attr( $id ); ?>" name="zenvy_fspb_settings[<?php echo esc_attr( $key ); ?>]" data-zenvy-setting="<?php echo esc_attr( $key ); ?>" rows="2"><?php echo esc_textarea( $settings[ $key ] ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render a select field.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 * @param array  $options  Value => label pairs.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private function field_select( $key, $label, $options, $settings ) {
		$id = 'zenvy-fspb-' . $key;
		?>
		<div class="zenvy-fspb-field zenvy-fspb-field--stack">
			<label class="zenvy-fspb-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>" name="zenvy_fspb_settings[<?php echo esc_attr( $key ); ?>]" data-zenvy-setting="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ], $value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Render a color field.
	 *
	 * @param string $key      Setting key.
	 * @param string $label    Label.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private function field_color( $key, $label, $settings ) {
		$id    = 'zenvy-fspb-' . $key;
		$color = sanitize_hex_color( $settings[ $key ] );
		?>
		<div class="zenvy-fspb-field zenvy-fspb-field--stack">
			<label class="zenvy-fspb-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="color" id="<?php echo esc_attr( $id ); ?>" name="zenvy_fspb_settings[<?php echo esc_attr( $key ); ?>]" data-zenvy-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $color ? $color : '#000000' ); ?>" />
		</div>
		<?php
	}
}
