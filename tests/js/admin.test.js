/**
 * The tab keyboard model of the card edit screen. The DOM wiring is a manual
 * check (docs/manual-checks.md); the index arithmetic is pinned here.
 */

'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const path = require( 'node:path' );

const { nextTabIndex } = require( path.join( __dirname, '..', '..', 'assets', 'admin.js' ) );

test( 'ArrowRight and ArrowLeft move and wrap', () => {
	assert.strictEqual( nextTabIndex( 0, 'ArrowRight', 3 ), 1 );
	assert.strictEqual( nextTabIndex( 2, 'ArrowRight', 3 ), 0 );
	assert.strictEqual( nextTabIndex( 0, 'ArrowLeft', 3 ), 2 );
} );

test( 'Home and End jump to the ends', () => {
	assert.strictEqual( nextTabIndex( 1, 'Home', 3 ), 0 );
	assert.strictEqual( nextTabIndex( 1, 'End', 3 ), 2 );
} );

test( 'other keys and empty lists are not ours', () => {
	assert.strictEqual( nextTabIndex( 0, 'Enter', 3 ), null );
	assert.strictEqual( nextTabIndex( 0, 'ArrowRight', 0 ), null );
} );
