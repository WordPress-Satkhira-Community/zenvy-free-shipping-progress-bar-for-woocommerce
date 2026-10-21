/**
 * Zenvy Free Shipping Progress Bar - frontend script (vanilla JS, no jQuery).
 *
 * Classic AJAX add-to-cart and mini-cart updates are handled by WooCommerce fragments.
 * This script covers Cart/Checkout Block updates (Store API) and acts as a lightweight
 * fallback by refreshing the bar through ?wc-ajax=zenvy_fspb_refresh.
 */
( function () {
	'use strict';

	var config = window.zenvyFspb || {};
	var SELECTOR = '.zenvy-fspb-wrapper';
	var timer = null;
	var inflight = false;
	var queued = false;
	var watching = false;

	/**
	 * Parse an HTML string and return its first element.
	 *
	 * @param {string} html Markup returned by the server (already sanitized with wp_kses).
	 * @return {Element|null}
	 */
	function parse( html ) {
		var template = document.createElement( 'template' );
		template.innerHTML = String( html ).trim();
		return template.content.firstElementChild;
	}

	/**
	 * Update an existing bar in place so the CSS width transition can animate.
	 *
	 * @param {Element} current Bar currently in the DOM.
	 * @param {Element} next    Freshly rendered bar.
	 */
	function patch( current, next ) {
		var curMessage = current.querySelector( '.zenvy-fspb-message' );
		var nextMessage = next.querySelector( '.zenvy-fspb-message' );
		var curTrack = current.querySelector( '.zenvy-fspb-track' );
		var nextTrack = next.querySelector( '.zenvy-fspb-track' );
		var curFill = current.querySelector( '.zenvy-fspb-fill' );
		var nextFill = next.querySelector( '.zenvy-fspb-fill' );

		if ( ! curMessage || ! nextMessage || ! curTrack || ! nextTrack || ! curFill || ! nextFill ) {
			current.replaceWith( next.cloneNode( true ) );
			return;
		}

		current.className = next.className;
		current.setAttribute( 'data-percentage', next.getAttribute( 'data-percentage' ) || '0' );

		if ( curMessage.innerHTML !== nextMessage.innerHTML ) {
			curMessage.innerHTML = nextMessage.innerHTML;
		}

		curTrack.setAttribute( 'aria-valuenow', nextTrack.getAttribute( 'aria-valuenow' ) || '0' );
		curFill.style.width = nextFill.style.width;
	}

	/**
	 * Apply the server markup to every bar on the page.
	 *
	 * @param {string} html Bar markup.
	 */
	function apply( html ) {
		var next = parse( html );
		var bars = document.querySelectorAll( SELECTOR );

		if ( ! next ) {
			return;
		}

		Array.prototype.forEach.call( bars, function ( bar ) {
			patch( bar, next );
		} );
	}

	/**
	 * Fetch the latest bar markup.
	 */
	function refresh() {
		if ( ! config.refreshUrl || ! window.fetch || ! document.querySelector( SELECTOR ) ) {
			return;
		}

		if ( inflight ) {
			queued = true;
			return;
		}

		inflight = true;

		window
			.fetch( config.refreshUrl, {
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' },
			} )
			.then( function ( response ) {
				return response.ok ? response.json() : Promise.reject( new Error( 'Request failed' ) );
			} )
			.then( function ( json ) {
				if ( json && json.success && json.data && json.data.html ) {
					apply( json.data.html );
				}
			} )
			.catch( function () {
				// Keep the current bar when the refresh fails.
			} )
			.then( function () {
				inflight = false;
				if ( queued ) {
					queued = false;
					schedule();
				}
			} );
	}

	/**
	 * Debounced refresh.
	 */
	function schedule() {
		window.clearTimeout( timer );
		timer = window.setTimeout( refresh, 150 );
	}

	/**
	 * Build a signature of the cart totals held in the WooCommerce Blocks store.
	 *
	 * @param {Object} selectors Selectors of the wc/store/cart store.
	 * @return {string}
	 */
	function signature( selectors ) {
		var totals = selectors.getCartTotals ? selectors.getCartTotals() : null;

		if ( ! totals ) {
			return '';
		}

		return [ totals.total_items, totals.total_items_tax, totals.total_discount, totals.total_price ].join( '|' );
	}

	/**
	 * Subscribe to the WooCommerce Blocks cart store when it is available.
	 *
	 * @return {boolean} True when the store is being watched.
	 */
	function watchStore() {
		var data = window.wp && window.wp.data;
		var selectors;
		var last;

		if ( ! data || 'function' !== typeof data.subscribe || 'function' !== typeof data.select ) {
			return false;
		}

		try {
			selectors = data.select( 'wc/store/cart' );
		} catch ( error ) {
			return false;
		}

		if ( ! selectors || 'function' !== typeof selectors.getCartTotals ) {
			return false;
		}

		last = signature( selectors );

		data.subscribe( function () {
			var now = signature( selectors );
			if ( now !== last ) {
				last = now;
				schedule();
			}
		}, 'wc/store/cart' );

		return true;
	}

	/**
	 * Initialise listeners.
	 */
	function init() {
		var topBar = document.getElementById( 'zenvy-fspb-top-bar' );

		// Themes without wp_body_open() print the bar in the footer; move it to the top.
		if ( topBar && topBar.hasAttribute( 'data-zenvy-move' ) && document.body ) {
			document.body.insertBefore( topBar, document.body.firstChild );
			topBar.removeAttribute( 'data-zenvy-move' );
		}

		document.addEventListener( 'wc-blocks_added_to_cart', schedule );
		document.addEventListener( 'wc-blocks_removed_from_cart', schedule );

		if ( ! watching ) {
			watching = watchStore();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// The blocks store can register after this script runs, so try once more on load.
	window.addEventListener( 'load', function () {
		if ( ! watching ) {
			watching = watchStore();
		}
	} );

	// Refresh when the page is restored from the back/forward cache.
	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			schedule();
		}
	} );
} )();
