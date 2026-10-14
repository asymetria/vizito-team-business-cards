/**
 * The block editor script registers the block by the name in block.json and
 * stores nothing in post content. Its editing UI is a manual check.
 */

'use strict';

const { test } = require( 'node:test' );
const assert = require( 'node:assert' );
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const vm = require( 'node:vm' );

const root = path.join( __dirname, '..', '..' );

test( 'registers the block from block.json and saves nothing', () => {
	const registered = [];
	const wp = {
		blocks: { registerBlockType: ( name, settings ) => registered.push( { name, settings } ) },
		element: { createElement: () => null, useState: ( v ) => [ v, () => {} ], useEffect: () => {} },
		i18n: { __: ( s ) => s },
		blockEditor: { InspectorControls: 'x', useBlockProps: () => ( {} ) },
		components: {},
		serverSideRender: 'x',
		apiFetch: () => Promise.resolve( [] ),
	};

	vm.runInNewContext( fs.readFileSync( path.join( root, 'assets', 'block.js' ), 'utf8' ), { window: { wp } } );

	const json = JSON.parse( fs.readFileSync( path.join( root, 'blocks', 'team-card', 'block.json' ), 'utf8' ) );

	assert.strictEqual( registered.length, 1 );
	assert.strictEqual( registered[ 0 ].name, json.name );
	assert.strictEqual( registered[ 0 ].settings.save(), null );
} );

test( 'does nothing outside the editor', () => {
	assert.doesNotThrow( () => {
		vm.runInNewContext( fs.readFileSync( path.join( root, 'assets', 'block.js' ), 'utf8' ), { window: {} } );
	} );
} );
