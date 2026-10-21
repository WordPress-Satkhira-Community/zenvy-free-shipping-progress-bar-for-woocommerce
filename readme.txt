=== Zenvy Free Shipping Progress Bar for WooCommerce ===
Contributors: wpdelower
Tags: free shipping bar, woocommerce free shipping, progress bar, cart upsell, free shipping progress bar
Requires at least: 6.0
Tested up to: 7.1 
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a free shipping progress bar to your WooCommerce mini-cart, cart and checkout. Auto-detects your free shipping goal. HPOS and Blocks compatible.

== Description ==

**Zenvy Free Shipping Progress Bar for WooCommerce** shows customers exactly how much more they need to spend to unlock free shipping. A clear WooCommerce free shipping progress bar is one of the simplest cart upsell tactics for increasing average order value and reducing cart abandonment.

The bar reads your real WooCommerce free shipping threshold automatically, updates instantly when products are added or removed, and works with both the classic cart and the new Cart and Checkout Blocks. The plugin is fully **HPOS compatible** (High-Performance Order Storage).

= Why a free shipping bar? =

Shoppers love free shipping. When they see "Add $12.50 more to unlock FREE Shipping!", many add one more product to reach the goal. That means bigger orders without discounts.

= Key Features =

* **Smart goal detection** - automatically reads the minimum amount of the Free Shipping method in the customer's matched WooCommerce shipping zone.
* **Manual fallback goal** - set a fixed amount when you do not use shipping zones, or when no zone matches yet.
* **Coupon-aware** - optionally deduct coupons and discounts from the progress calculation.
* **Virtual cart friendly** - the bar is hidden for carts that do not need shipping.
* **Real-time updates** - the bar refreshes with AJAX add to cart, mini-cart changes and Cart/Checkout Block updates. No jQuery is required on the frontend.
* **Plug and play** - activate the plugin and the bar appears automatically at the top of your store. No shortcode, widget or page builder needed.
* **Flexible placement** - also show it in the mini-cart / side cart, on the cart page, on the checkout page, or anywhere with the optional `[zenvy_shipping_bar]` shortcode.
* **Dynamic messages** - customize the initial, in-progress and success messages with the `{goal}`, `{remaining}` and `{cart_total}` placeholders. Prices follow your WooCommerce currency settings.
* **Two style presets** - a modern rounded pill bar (Minimal) and animated diagonal stripes (Striped).
* **Full color control** - background, fill, text color and border radius with a live preview in the settings page.
* **Modern settings dashboard** - clean tabbed interface under the shared Zenvy menu.
* **Compatible with HPOS and Blocks** - declares support for custom order tables and Cart/Checkout Blocks.
* **Developer friendly** - actions and filters to customize the goal, message, markup and settings.
* **Clean uninstall** - optionally remove all plugin data when the plugin is deleted.

= Free vs Pro =

**Free (this plugin)**

* Auto-detect free shipping goal from shipping zones
* Manual fallback goal and coupon deduction
* Automatic top-of-page bar, mini-cart, cart, checkout and shortcode placements
* Cart and Checkout Block support
* Minimal and Striped presets with live preview
* Custom messages and colors

**Pro (companion plugin, coming soon)**

* Multi-tier milestones for a free gift or discount
* Sticky top bar that stays visible while scrolling
* Confetti celebration animation
* 1-click in-bar upsell product

Pro features are shown as locked options in the settings page. A companion Pro plugin unlocks them through the hooks provided by this plugin.

= Placeholders =

* `{goal}` - the free shipping threshold.
* `{remaining}` - the amount left to reach the threshold.
* `{cart_total}` - the current cart amount that counts towards the goal.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload the `zenvy-free-shipping-progress-bar` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress Plugins screen.
3. Activate the plugin through the Plugins screen.
4. In WooCommerce, go to Shipping, open a shipping zone and add the Free Shipping method with a minimum order amount (recommended).
5. That is it. The bar appears at the top of your store automatically.
6. Optional: go to Zenvy > Free Shipping Bar to choose where the bar appears, edit the messages and pick your colors.

== Frequently Asked Questions ==

= Does it work without a Free Shipping method in WooCommerce? =

Yes. Choose "Manual goal only" in General & Goal, or keep Auto-detect enabled and set a manual fallback goal. The fallback is used whenever no free shipping minimum is found for the customer's zone.

= How does Auto-detect choose the goal? =

The plugin finds the shipping zone that matches the customer's shipping location, looks for enabled Free Shipping methods that use a minimum order amount, and uses the lowest minimum amount.

= Does it support the WooCommerce Cart and Checkout Blocks? =

Yes. The bar is added above the Cart and Checkout blocks, and it refreshes when the block cart changes. Plugin compatibility with Cart/Checkout Blocks is declared to WooCommerce.

= Is it compatible with HPOS (High-Performance Order Storage)? =

Yes. The plugin declares compatibility with custom order tables.

= Can I show the bar anywhere else? =

Yes. The shortcode is optional: use `[zenvy_shipping_bar]` in any page, widget or block theme template to place the bar exactly where you want.

= Why is the bar not showing? =

Check that "Top of page bar" is enabled, that a goal greater than zero exists, and that the cart contains at least one product that needs shipping (virtual and downloadable products do not count). If you hide the bar for empty carts, it appears after the first product is added.

= Can I deduct coupons from the progress? =

Yes. Enable "Deduct coupons and discounts" in General & Goal.

= Does it work with page caching? =

The bar is refreshed through WooCommerce AJAX fragments and a lightweight refresh request, so it stays accurate on cached pages that use the standard WooCommerce cart fragments.

= Is there a Pro version? =

A companion Pro plugin is planned. It adds multi-tier milestones, a sticky top bar, confetti and a 1-click upsell product.

= Is my data removed when I delete the plugin? =

Only when you enable "Delete data on uninstall" in General & Goal. Otherwise your settings are kept.

== Changelog ==

= 1.0.1 =
* New: plug-and-play top-of-page bar that appears automatically after activation.
* Fix: the bar is no longer hidden on stores that have no shipping methods configured yet.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
Adds an automatic top-of-page bar and fixes the bar being hidden when no shipping methods exist.

= 1.0.0 =
Initial release.
