/**
 * Zenvy Free Shipping Progress Bar - admin script.
 *
 * Handles the tabbed interface and the real-time live preview (vanilla JS).
 */
( function () {
	'use strict';

	var config = window.zenvyFspbAdmin || {};
	var form = document.getElementById( 'zenvy-fspb-form' );

	if ( ! form ) {
		return;
	}

	var preview = document.getElementById( 'zenvy-fspb-preview' );
	var stage = document.getElementById( 'zenvy-fspb-preview-stage' );
	var range = document.getElementById( 'zenvy-fspb-preview-range' );
	var stateLabel = document.getElementById( 'zenvy-fspb-preview-state' );
	var radiusOutput = document.getElementById( 'zenvy-fspb-radius-output' );
	var tabs = form.querySelectorAll( '.zenvy-fspb-tab' );
	var panels = form.querySelectorAll( '.zenvy-fspb-panel' );
	var STORAGE_KEY = 'zenvyFspbActiveTab';
	var ALLOWED_TAGS = { STRONG: 1, B: 1, EM: 1, I: 1, BR: 1, SPAN: 1, BDI: 1 };

	/**
	 * Read the current value of a setting field.
	 *
	 * @param {string} key Setting key.
	 * @return {string}
	 */
	function getValue( key ) {
		var fields = form.querySelectorAll( '[data-zenvy-setting="' + key + '"]' );
		var i;
		var field;

		for ( i = 0; i < fields.length; i++ ) {
			field = fields[ i ];

			if ( 'radio' === field.type ) {
				if ( field.checked ) {
					return field.value;
				}
			} else if ( 'checkbox' === field.type ) {
				return field.checked ? 'yes' : 'no';
			} else {
				return field.value;
			}
		}

		return '';
	}

	/**
	 * Strip a message down to a few safe inline tags for the preview.
	 *
	 * @param {string} html Message template.
	 * @return {string}
	 */
	function cleanMessage( html ) {
		var template = document.createElement( 'template' );
		template.innerHTML = html;

		( function walk( node ) {
			Array.prototype.slice.call( node.childNodes ).forEach( function ( child ) {
				if ( 1 !== child.nodeType ) {
					return;
				}

				walk( child );

				if ( ALLOWED_TAGS[ child.nodeName ] ) {
					while ( child.attributes.length ) {
						child.removeAttribute( child.attributes[ 0 ].name );
					}
				} else {
					while ( child.firstChild ) {
						node.insertBefore( child.firstChild, child );
					}
					node.removeChild( child );
				}
			} );
		} )( template.content );

		return template.innerHTML;
	}

	/**
	 * Format an amount with the store currency settings.
	 *
	 * @param {number} amount Amount.
	 * @return {string} Price markup mirroring wc_price().
	 */
	function formatPrice( amount ) {
		var currency = config.currency || {};
		var decimals = parseInt( currency.decimals, 10 );
		var parts;
		var number;
		var text;

		if ( isNaN( decimals ) ) {
			decimals = 2;
		}

		parts = Math.abs( amount ).toFixed( decimals ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, currency.thousand || ',' );
		number = parts.join( currency.decimal || '.' );

		text = ( currency.format || '%1$s%2$s' )
			.replace( '%1$s', '<span class="woocommerce-Price-currencySymbol">' + escapeHtml( currency.symbol || '' ) + '</span>' )
			.replace( '%2$s', number );

		return '<span class="woocommerce-Price-amount amount"><bdi>' + text + '</bdi></span>';
	}

	/**
	 * Escape text for use inside HTML.
	 *
	 * @param {string} text Raw text.
	 * @return {string}
	 */
	function escapeHtml( text ) {
		var element = document.createElement( 'div' );
		element.textContent = text;
		return element.innerHTML;
	}

	/**
	 * Re-render the preview from the current form values.
	 */
	function updatePreview() {
		var percent = parseInt( range.value, 10 ) || 0;
		var goal = parseFloat( getValue( 'manual_goal' ) );
		var preset = 'striped' === getValue( 'preset' ) ? 'striped' : 'minimal';
		var hideWhenEmpty = 'yes' === getValue( 'hide_when_empty' );
		var i18n = config.i18n || {};
		var defaults = config.defaults || {};
		var key = 'msg_progress';
		var state = i18n.progress;
		var classes = 'zenvy-fspb-wrapper zenvy-fspb-preset-' + preset;
		var fillPercent = percent;
		var total;
		var remaining;
		var template;
		var message;
		var fill = preview.querySelector( '.zenvy-fspb-fill' );
		var track = preview.querySelector( '.zenvy-fspb-track' );
		var messageEl = preview.querySelector( '.zenvy-fspb-message' );
		var radius = parseInt( getValue( 'border_radius' ), 10 );

		if ( ! goal || goal <= 0 ) {
			goal = 100;
		}

		if ( 0 === percent ) {
			key = 'msg_initial';
			state = i18n.empty;
			fillPercent = 0;
			classes += ' zenvy-fspb-is-empty';
		} else if ( percent >= 100 ) {
			key = 'msg_success';
			state = i18n.unlocked;
			fillPercent = 100;
			classes += ' zenvy-fspb-is-unlocked';
		}

		total = 0 === percent ? 0 : ( goal * Math.min( percent, 100 ) ) / 100;
		remaining = Math.max( 0, goal - total );

		template = cleanMessage( getValue( key ) || defaults[ key ] || '' );
		message = template
			.split( '{goal}' ).join( formatPrice( goal ) )
			.split( '{remaining}' ).join( formatPrice( remaining ) )
			.split( '{cart_total}' ).join( formatPrice( total ) );

		preview.className = classes;
		messageEl.innerHTML = message;
		fill.style.width = fillPercent + '%';
		track.setAttribute( 'aria-valuenow', String( Math.round( fillPercent ) ) );

		preview.style.setProperty( '--zenvy-fspb-bg', getValue( 'color_bg' ) );
		preview.style.setProperty( '--zenvy-fspb-fill', getValue( 'color_fill' ) );
		preview.style.setProperty( '--zenvy-fspb-text', getValue( 'color_text' ) );
		preview.style.setProperty( '--zenvy-fspb-radius', ( isNaN( radius ) ? 20 : radius ) + 'px' );

		if ( radiusOutput ) {
			radiusOutput.textContent = isNaN( radius ) ? '20' : String( radius );
		}

		if ( 0 === percent && hideWhenEmpty ) {
			stage.classList.add( 'is-dimmed' );
			state = i18n.hidden;
		} else {
			stage.classList.remove( 'is-dimmed' );
		}

		stateLabel.textContent = state + ' \u00B7 ' + Math.round( fillPercent ) + '%';
	}

	/**
	 * Activate a tab by id.
	 *
	 * @param {string} id Tab id.
	 * @return {boolean} Whether the tab exists.
	 */
	function activateTab( id ) {
		var found = false;

		Array.prototype.forEach.call( tabs, function ( tab ) {
			var active = tab.getAttribute( 'data-tab' ) === id;
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', active ? '0' : '-1' );
			found = found || active;
		} );

		if ( ! found ) {
			return false;
		}

		Array.prototype.forEach.call( panels, function ( panel ) {
			panel.hidden = panel.id !== 'zenvy-fspb-tab-' + id;
		} );

		try {
			window.sessionStorage.setItem( STORAGE_KEY, id );
		} catch ( error ) {
			// Storage can be unavailable; ignore.
		}

		return true;
	}

	/**
	 * Move focus between tabs with the arrow keys.
	 *
	 * @param {KeyboardEvent} event Keydown event.
	 */
	function onTabKeydown( event ) {
		var list = Array.prototype.slice.call( tabs );
		var index = list.indexOf( event.currentTarget );
		var next = null;

		if ( 'ArrowRight' === event.key ) {
			next = list[ ( index + 1 ) % list.length ];
		} else if ( 'ArrowLeft' === event.key ) {
			next = list[ ( index - 1 + list.length ) % list.length ];
		}

		if ( next ) {
			event.preventDefault();
			next.focus();
			activateTab( next.getAttribute( 'data-tab' ) );
		}
	}

	Array.prototype.forEach.call( tabs, function ( tab ) {
		tab.addEventListener( 'click', function () {
			activateTab( tab.getAttribute( 'data-tab' ) );
		} );
		tab.addEventListener( 'keydown', onTabKeydown );
	} );

	form.addEventListener( 'input', updatePreview );
	form.addEventListener( 'change', updatePreview );

	var initialTab = 'general';
	try {
		initialTab = window.sessionStorage.getItem( STORAGE_KEY ) || initialTab;
	} catch ( error ) {
		// Ignore storage errors.
	}

	if ( ! activateTab( initialTab ) ) {
		activateTab( 'general' );
	}

	updatePreview();
} )();
