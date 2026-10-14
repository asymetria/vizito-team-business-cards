/**
 * Vizito Team Business Cards — admin screens.
 *
 * No dependencies beyond what WordPress already loads on the screen. Every
 * feature here is an enhancement: each form still works without this file.
 */
( function ( root ) {
	'use strict';

	/**
	 * Which tab a key press moves to. Pure, so node --test can reach it.
	 *
	 * @param {number} current Index of the focused tab.
	 * @param {string} key     KeyboardEvent.key.
	 * @param {number} count   Number of tabs.
	 * @return {number|null} New index, or null when the key is not ours.
	 */
	function nextTabIndex( current, key, count ) {
		if ( count < 1 ) {
			return null;
		}

		switch ( key ) {
			case 'ArrowRight':
				return ( current + 1 ) % count;
			case 'ArrowLeft':
				return ( current - 1 + count ) % count;
			case 'Home':
				return 0;
			case 'End':
				return count - 1;
			default:
				return null;
		}
	}

	/**
	 * Turns every [data-tbc-tabs] block into tabs. Without this, every
	 * language panel stays visible with its own code label.
	 *
	 * @param {Document} doc Document.
	 */
	function initTabs( doc ) {
		doc.querySelectorAll( '[data-tbc-tabs]' ).forEach( function ( block ) {
			var list   = block.querySelector( '[role="tablist"]' );
			var tabs   = Array.prototype.slice.call( block.querySelectorAll( '[role="tab"]' ) );
			var panels = tabs.map( function ( tab ) {
				return doc.getElementById( tab.getAttribute( 'aria-controls' ) );
			} );

			if ( ! list || tabs.length < 2 ) {
				return;
			}

			function select( index, focus ) {
				tabs.forEach( function ( tab, i ) {
					var on = i === index;

					tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					tab.tabIndex = on ? 0 : -1;

					if ( panels[ i ] ) {
						panels[ i ].hidden = ! on;
					}
				} );

				if ( focus ) {
					tabs[ index ].focus();
				}
			}

			list.hidden = false;
			block.classList.add( 'tbc-tabs-ready' );

			tabs.forEach( function ( tab, i ) {
				tab.addEventListener( 'click', function () {
					select( i, false );
				} );

				tab.addEventListener( 'keydown', function ( event ) {
					var next = nextTabIndex( i, event.key, tabs.length );

					if ( null !== next ) {
						event.preventDefault();
						select( next, true );
					}
				} );
			} );

			select( 0, false );
		} );
	}

	/**
	 * "Select logo": opens the media library and writes the chosen id into
	 * the number input the button points at.
	 *
	 * @param {Document} doc Document.
	 */
	function initMediaButtons( doc ) {
		if ( ! root.wp || ! root.wp.media ) {
			return;
		}

		doc.querySelectorAll( '[data-tbc-media]' ).forEach( function ( button ) {
			var input = doc.getElementById( button.getAttribute( 'data-tbc-media' ) );
			var frame = null;

			if ( ! input ) {
				return;
			}

			button.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = root.wp.media( {
						library: { type: 'image' },
						multiple: false,
					} );

					frame.on( 'select', function () {
						var chosen = frame.state().get( 'selection' ).first();

						if ( chosen ) {
							input.value = String( chosen.get( 'id' ) );
						}
					} );
				}

				frame.open();
			} );
		} );
	}

	var api = {
		nextTabIndex: nextTabIndex,
		initTabs: initTabs,
		initMediaButtons: initMediaButtons,
	};

	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = api;
		return;
	}

	function start() {
		initTabs( document );
		initMediaButtons( document );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}( 'undefined' !== typeof window ? window : {} ) );
