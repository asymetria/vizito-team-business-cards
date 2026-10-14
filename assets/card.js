/**
 * Vizito Team Business Cards — card page.
 *
 * Two jobs: open a scanned card in the visitor's language, and share the
 * card. No dependencies. The pure functions are exported for node --test.
 */
( function ( root ) {
	'use strict';

	/**
	 * The first browser language whose primary subtag is available.
	 *
	 * @param {string[]} browserLangs navigator.languages.
	 * @param {string[]} available    Configured codes.
	 * @param {string}   fallback     Default code.
	 * @return {string}
	 */
	function pickLanguage( browserLangs, available, fallback ) {
		var list = browserLangs || [];

		for ( var i = 0; i < list.length; i++ ) {
			var primary = String( list[ i ] ).toLowerCase().split( '-' )[ 0 ];

			if ( available.indexOf( primary ) !== -1 ) {
				return primary;
			}
		}

		return fallback;
	}

	/**
	 * Where to send a visitor who arrived without a language, or null to stay.
	 * Only an address without "lang" is ever redirected, and the target has
	 * one, so this can never loop; a click on the switcher always carries
	 * "lang" and is never overridden.
	 *
	 * @param {string}   href         Current address.
	 * @param {Object}   config       window.tbcCard.
	 * @param {string[]} browserLangs navigator.languages.
	 * @return {string|null}
	 */
	function autoSwitchUrl( href, config, browserLangs ) {
		if ( ! config || ! config.auto ) {
			return null;
		}

		var url = new URL( href );

		if ( url.searchParams.has( 'lang' ) ) {
			return null;
		}

		var wanted = pickLanguage( browserLangs, config.languages || [], config.default );

		if ( ! wanted || wanted === config.lang ) {
			return null;
		}

		url.searchParams.set( 'lang', wanted );

		return url.toString();
	}

	/**
	 * Shares a card: the system share sheet where there is one, the clipboard
	 * otherwise. A dismissed share sheet is not an error.
	 *
	 * @param {Object}      nav    navigator.
	 * @param {Object}      data   { title, url }.
	 * @param {Element|null} status Live region for "Link copied".
	 * @param {string}      copied Translated "Link copied".
	 * @return {Promise<string>} "shared", "copied", "cancelled" or "failed".
	 */
	function share( nav, data, status, copied ) {
		if ( nav && 'function' === typeof nav.share ) {
			return nav.share( data ).then(
				function () {
					return 'shared';
				},
				function ( error ) {
					return error && 'AbortError' === error.name ? 'cancelled' : 'failed';
				}
			);
		}

		if ( nav && nav.clipboard && 'function' === typeof nav.clipboard.writeText ) {
			return nav.clipboard.writeText( data.url ).then(
				function () {
					if ( status ) {
						status.textContent = copied;
					}

					return 'copied';
				},
				function () {
					return 'failed';
				}
			);
		}

		return Promise.resolve( 'failed' );
	}

	/**
	 * Where a visit came from: a printed code carries src=qr.
	 *
	 * @param {string} href Current address.
	 * @return {string} "qr" or "link".
	 */
	function sourceOf( href ) {
		return 'qr' === new URL( href ).searchParams.get( 'src' ) ? 'qr' : 'link';
	}

	/**
	 * The address without src, so a link shared on from a scanned card is
	 * not counted as another scan.
	 *
	 * @param {string} href Current address.
	 * @return {string|null} The cleaned address, or null when there was no src.
	 */
	function withoutSource( href ) {
		var url = new URL( href );

		if ( ! url.searchParams.has( 'src' ) ) {
			return null;
		}

		url.searchParams.delete( 'src' );

		return url.toString();
	}

	/**
	 * Reports one event. Fire and forget; a browser without sendBeacon simply
	 * does not count. "vcard" is never sent: the server counts downloads.
	 *
	 * @param {Object} nav    navigator.
	 * @param {Object} config window.tbcCard.
	 * @param {string} event  Event name.
	 * @param {string} source "qr" or "link".
	 * @param {number} [card] Card id; the page's own card when omitted.
	 * @return {boolean} Whether a beacon was queued.
	 */
	function track( nav, config, event, source, card ) {
		var id = card || ( config && config.card );

		if ( ! config || ! config.hit || ! id || 'vcard' === event ) {
			return false;
		}

		if ( ! nav || 'function' !== typeof nav.sendBeacon || 'undefined' === typeof Blob ) {
			return false;
		}

		var body = new Blob(
			[ JSON.stringify( { card: Number( id ), event: event, src: source } ) ],
			{ type: 'application/json' }
		);

		return nav.sendBeacon( config.hit, body );
	}

	var api = {
		pickLanguage: pickLanguage,
		autoSwitchUrl: autoSwitchUrl,
		share: share,
		sourceOf: sourceOf,
		withoutSource: withoutSource,
		track: track,
	};

	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = api;
		return;
	}

	var config = root.tbcCard || {};
	var target = autoSwitchUrl( root.location.href, config, root.navigator.languages || [ root.navigator.language ] );

	if ( target ) {
		root.location.replace( target );
		return;
	}

	var source = sourceOf( root.location.href );
	var clean  = withoutSource( root.location.href );

	// Only the card page counts a view: config.card is set there and not in embeds.
	track( root.navigator, config, 'view', source );

	if ( clean && root.history && root.history.replaceState ) {
		root.history.replaceState( null, '', clean );
	}

	root.document.addEventListener( 'click', function ( event ) {
		var tracked = event.target.closest && event.target.closest( '[data-tbc-event]' );

		if ( tracked ) {
			var holder = tracked.closest( '[data-tbc-card]' );

			track( root.navigator, config, tracked.getAttribute( 'data-tbc-event' ), source, holder ? holder.getAttribute( 'data-tbc-card' ) : 0 );
		}

		var button = event.target.closest && event.target.closest( '.tbc-share' );

		if ( ! button ) {
			return;
		}

		var card = button.closest( '.tbc-card' );

		share(
			root.navigator,
			{ title: root.document.title, url: button.getAttribute( 'data-tbc-url' ) },
			card ? card.querySelector( '.tbc-status' ) : null,
			config.copied || ''
		);
	} );
}( 'undefined' !== typeof window ? window : {} ) );
