/**
 * The card page script: language pick, auto switch, share. The document
 * wiring (click delegation, location.replace) is a manual check.
 */

'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const path = require( 'node:path' );

const { pickLanguage, autoSwitchUrl, share } = require( path.join( __dirname, '..', '..', 'assets', 'card.js' ) );

const config = { auto: true, lang: 'pl', languages: [ 'pl', 'de' ], default: 'pl' };

test( 'pickLanguage matches the primary subtag, in browser order', () => {
	assert.strictEqual( pickLanguage( [ 'de-DE', 'en' ], [ 'pl', 'de' ], 'pl' ), 'de' );
	assert.strictEqual( pickLanguage( [ 'fr', 'EN-gb' ], [ 'pl', 'en' ], 'pl' ), 'en' );
} );

test( 'pickLanguage falls back when nothing matches', () => {
	assert.strictEqual( pickLanguage( [ 'fr' ], [ 'pl', 'de' ], 'pl' ), 'pl' );
	assert.strictEqual( pickLanguage( undefined, [ 'pl' ], 'pl' ), 'pl' );
} );

test( 'autoSwitchUrl adds the browser language to a bare address', () => {
	assert.strictEqual(
		autoSwitchUrl( 'https://example.test/card/jan/?src=qr', config, [ 'de-AT' ] ),
		'https://example.test/card/jan/?src=qr&lang=de'
	);
} );

test( 'autoSwitchUrl never touches an address that already has a language', () => {
	assert.strictEqual( autoSwitchUrl( 'https://example.test/card/jan/?lang=pl', config, [ 'de' ] ), null );
} );

test( 'autoSwitchUrl stays when auto is off or the language already matches', () => {
	assert.strictEqual( autoSwitchUrl( 'https://example.test/card/jan/', { ...config, auto: false }, [ 'de' ] ), null );
	assert.strictEqual( autoSwitchUrl( 'https://example.test/card/jan/', config, [ 'pl-PL' ] ), null );
	assert.strictEqual( autoSwitchUrl( 'https://example.test/card/jan/', config, [ 'fr' ] ), null );
} );

test( 'share uses the system sheet when there is one', async () => {
	const seen = [];
	const nav = { share: ( data ) => { seen.push( data ); return Promise.resolve(); } };

	assert.strictEqual( await share( nav, { title: 'T', url: 'u' }, null, 'Copied' ), 'shared' );
	assert.deepStrictEqual( seen, [ { title: 'T', url: 'u' } ] );
} );

test( 'share treats a dismissed sheet as cancelled, not failed', async () => {
	const abort = Object.assign( new Error( 'x' ), { name: 'AbortError' } );
	const nav = { share: () => Promise.reject( abort ) };

	assert.strictEqual( await share( nav, { url: 'u' }, null, '' ), 'cancelled' );
} );

test( 'share falls back to the clipboard and announces it', async () => {
	const copied = [];
	const status = { textContent: '' };
	const nav = { clipboard: { writeText: ( text ) => { copied.push( text ); return Promise.resolve(); } } };

	assert.strictEqual( await share( nav, { url: 'https://example.test/card/jan/' }, status, 'Link copied' ), 'copied' );
	assert.deepStrictEqual( copied, [ 'https://example.test/card/jan/' ] );
	assert.strictEqual( status.textContent, 'Link copied' );
} );

test( 'share reports failure when neither is available', async () => {
	assert.strictEqual( await share( {}, { url: 'u' }, null, '' ), 'failed' );
} );

const { sourceOf, withoutSource, track } = require( path.join( __dirname, '..', '..', 'assets', 'card.js' ) );

const tracking = { card: 5, hit: 'https://example.test/wp-json/tbcards/v1/hit' };

test( 'sourceOf tells a scan from a link', () => {
	assert.strictEqual( sourceOf( 'https://example.test/card/jan/?src=qr' ), 'qr' );
	assert.strictEqual( sourceOf( 'https://example.test/card/jan/?lang=de&src=qr' ), 'qr' );
	assert.strictEqual( sourceOf( 'https://example.test/card/jan/' ), 'link' );
	assert.strictEqual( sourceOf( 'https://example.test/card/jan/?src=other' ), 'link' );
} );

test( 'withoutSource drops src and keeps everything else', () => {
	assert.strictEqual( withoutSource( 'https://example.test/card/jan/?lang=de&src=qr' ), 'https://example.test/card/jan/?lang=de' );
	assert.strictEqual( withoutSource( 'https://example.test/card/jan/' ), null );
} );

test( 'track sends one JSON beacon to the hit address', async () => {
	const sent = [];
	const nav = { sendBeacon: ( url, body ) => { sent.push( { url, body } ); return true; } };

	assert.strictEqual( track( nav, tracking, 'call', 'qr' ), true );
	assert.strictEqual( sent[ 0 ].url, tracking.hit );
	assert.strictEqual( sent[ 0 ].body.type, 'application/json' );
	assert.deepStrictEqual( JSON.parse( await sent[ 0 ].body.text() ), { card: 5, event: 'call', src: 'qr' } );
} );

test( 'track never sends vcard, and does nothing without sendBeacon or config', () => {
	const sent = [];
	const nav = { sendBeacon: () => { sent.push( 1 ); return true; } };

	assert.strictEqual( track( nav, tracking, 'vcard', 'link' ), false );
	assert.strictEqual( track( {}, tracking, 'view', 'link' ), false );
	assert.strictEqual( track( nav, {}, 'view', 'link' ), false );
	assert.deepStrictEqual( sent, [] );
} );

test( 'track counts a click for the card it happened in, and no view in an embed', async () => {
	const sent = [];
	const nav = { sendBeacon: ( url, body ) => { sent.push( body ); return true; } };
	const embed = { hit: 'https://example.test/wp-json/tbcards/v1/hit', auto: false };

	assert.strictEqual( track( nav, embed, 'view', 'link' ), false );
	assert.strictEqual( track( nav, embed, 'call', 'link', '7' ), true );
	assert.deepStrictEqual( JSON.parse( await sent[ 0 ].text() ), { card: 7, event: 'call', src: 'link' } );
} );
