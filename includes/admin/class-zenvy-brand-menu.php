<?php
/**
 * Shared Zenvy top-level admin menu.
 *
 * Every Zenvy plugin can ship this file. Only the first copy loaded defines the class,
 * and the top-level "Zenvy" menu is registered only when the slug does not exist yet.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Zenvy_Brand_Menu' ) ) {

	/**
	 * Class Zenvy_Brand_Menu
	 */
	class Zenvy_Brand_Menu {

		/**
		 * Top-level menu slug shared by all Zenvy plugins.
		 */
		const MENU_SLUG = 'zenvy-plugins';

		/**
		 * Registered submenu items.
		 *
		 * @var array
		 */
		private static $items = array();

		/**
		 * Hook suffixes returned by add_submenu_page(), keyed by item slug.
		 *
		 * @var array
		 */
		private static $hook_suffixes = array();

		/**
		 * Whether the admin_menu callback has been attached.
		 *
		 * @var bool
		 */
		private static $hooked = false;

		/**
		 * Add a plugin page under the shared Zenvy menu.
		 *
		 * @param array $item {
		 *     Item arguments.
		 *
		 *     @type string   $slug        Unique page slug.
		 *     @type string   $page_title  Page title.
		 *     @type string   $menu_title  Menu label.
		 *     @type string   $capability  Required capability.
		 *     @type callable $callback    Page renderer.
		 *     @type string   $description Short description shown on the Zenvy dashboard.
		 *     @type string   $version     Plugin version shown on the Zenvy dashboard.
		 * }
		 * @return void
		 */
		public static function add_item( array $item ) {
			$item = wp_parse_args(
				$item,
				array(
					'slug'        => '',
					'page_title'  => '',
					'menu_title'  => '',
					'capability'  => 'manage_woocommerce',
					'callback'    => '',
					'description' => '',
					'version'     => '',
				)
			);

			if ( '' === $item['slug'] || ! is_callable( $item['callback'] ) ) {
				return;
			}

			self::$items[ $item['slug'] ] = $item;

			if ( ! self::$hooked ) {
				self::$hooked = true;
				add_action( 'admin_menu', array( __CLASS__, 'register' ), 9 );
			}
		}

		/**
		 * Get the hook suffix of a registered item page.
		 *
		 * @param string $slug Item slug.
		 * @return string Empty string when unknown.
		 */
		public static function get_hook_suffix( $slug ) {
			return isset( self::$hook_suffixes[ $slug ] ) ? (string) self::$hook_suffixes[ $slug ] : '';
		}

		/**
		 * Whether the top-level Zenvy menu already exists.
		 *
		 * @return bool
		 */
		public static function top_level_exists() {
			global $menu;

			if ( is_array( $menu ) ) {
				foreach ( $menu as $entry ) {
					if ( isset( $entry[2] ) && self::MENU_SLUG === $entry[2] ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Register the top-level menu (once) and all submenu items.
		 *
		 * @return void
		 */
		public static function register() {
			if ( empty( self::$items ) ) {
				return;
			}

			/**
			 * Filter the capability needed to see the shared Zenvy menu.
			 *
			 * @param string $capability Default manage_woocommerce.
			 */
			$capability = (string) apply_filters( 'zenvy_brand_menu_capability', 'manage_woocommerce' );

			if ( ! self::top_level_exists() ) {
				add_menu_page(
					__( 'Zenvy Plugins', 'zenvy-free-shipping-progress-bar' ),
					__( 'Zenvy', 'zenvy-free-shipping-progress-bar' ),
					$capability,
					self::MENU_SLUG,
					array( __CLASS__, 'render_dashboard' ),
					'dashicons-superhero-alt',
					58
				);

				// Rename the automatic first submenu entry to "Dashboard".
				add_submenu_page(
					self::MENU_SLUG,
					__( 'Zenvy Plugins', 'zenvy-free-shipping-progress-bar' ),
					__( 'Dashboard', 'zenvy-free-shipping-progress-bar' ),
					$capability,
					self::MENU_SLUG,
					array( __CLASS__, 'render_dashboard' )
				);
			}

			foreach ( self::$items as $slug => $item ) {
				self::$hook_suffixes[ $slug ] = add_submenu_page(
					self::MENU_SLUG,
					$item['page_title'],
					$item['menu_title'],
					$item['capability'],
					$slug,
					$item['callback']
				);
			}
		}

		/**
		 * Render the Zenvy dashboard listing every registered Zenvy plugin.
		 *
		 * @return void
		 */
		public static function render_dashboard() {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Zenvy Plugins', 'zenvy-free-shipping-progress-bar' ); ?></h1>
				<p><?php echo esc_html__( 'Manage your Zenvy plugins from one place.', 'zenvy-free-shipping-progress-bar' ); ?></p>
				<div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:20px;">
					<?php foreach ( self::$items as $slug => $item ) : ?>
						<?php if ( ! current_user_can( $item['capability'] ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<div class="card" style="margin:0;min-width:260px;max-width:340px;">
							<h2 style="margin-top:0;"><?php echo esc_html( $item['menu_title'] ); ?></h2>
							<?php if ( '' !== $item['description'] ) : ?>
								<p><?php echo esc_html( $item['description'] ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $item['version'] ) : ?>
								<p>
									<?php
									/* translators: %s: plugin version number. */
									echo esc_html( sprintf( __( 'Version %s', 'zenvy-free-shipping-progress-bar' ), $item['version'] ) );
									?>
								</p>
							<?php endif; ?>
							<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>">
								<?php echo esc_html__( 'Open Settings', 'zenvy-free-shipping-progress-bar' ); ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
	}
}
