/**
 * Tests for the P2-05 zip writer/reader (scripts/release/lib/zip.mjs) and file filters
 * (scripts/release/lib/files.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );

let writeZip;
let readZip;
let pluginFiles;
let themePaths;

beforeAll( async () => {
	const zipMod = await import(
		path.join( __dirname, '..', 'release', 'lib', 'zip.mjs' )
	);
	writeZip = zipMod.writeZip;
	readZip = zipMod.readZip;

	const filesMod = await import(
		path.join( __dirname, '..', 'release', 'lib', 'files.mjs' )
	);
	pluginFiles = filesMod.pluginFiles;
	themePaths = filesMod.themePaths;
} );

describe( 'zip', () => {
	it( 'zip round-trips names and bytes', () => {
		const entries = [
			{
				name: 'ttm-core/ttm-core.php',
				data: Buffer.from( '<?php // plugin' ),
			},
			{
				name: 'ttm-core/build/index.js',
				// Long, repetitive content so deflate actually shrinks it (exercises the
				// "stored unless deflate is smaller" branch below too, via the short entry).
				data: Buffer.from( 'console.log("hi");'.repeat( 50 ) ),
			},
			{ name: 'ttm-core/LICENSE', data: Buffer.from( 'x' ) },
		];

		const zip = writeZip( entries );
		const roundTripped = readZip( zip );

		expect( roundTripped ).toHaveLength( entries.length );
		const byName = new Map(
			roundTripped.map( ( e ) => [ e.name, e.data ] )
		);
		for ( const entry of entries ) {
			expect( byName.get( entry.name ) ).toEqual( entry.data );
		}
	} );

	it( 'zip output is byte-identical for the same input', () => {
		const entries = [
			{ name: 'b.txt', data: Buffer.from( 'second' ) },
			{ name: 'a.txt', data: Buffer.from( 'first' ) },
		];

		const first = writeZip( entries );
		const second = writeZip( [ ...entries ].reverse() );

		expect( first.equals( second ) ).toBe( true );
	} );
} );

describe( 'files', () => {
	const SAMPLE_PATHS = [
		'plugins/ttm-core/ttm-core.php',
		'plugins/ttm-core/uninstall.php',
		'plugins/ttm-core/readme.txt',
		'plugins/ttm-core/README.md',
		'plugins/ttm-core/src/Config.php',
		'plugins/ttm-core/src/index.js',
		'plugins/ttm-core/src/editor/Sidebar.js',
		'plugins/ttm-core/src/editor/Sidebar.js.map',
		'plugins/ttm-core/blocks/lead-story/render.php',
		'plugins/ttm-core/blocks/lead-story/index.js',
		'plugins/ttm-core/assets/css/editor.css',
		'plugins/ttm-core/build/index.js',
		'plugins/ttm-core/build/index.js.map',
		'plugins/ttm-core/build/blocks/lead-story/index.js',
		'plugins/ttm-core/node_modules/foo/index.js',
		'plugins/ttm-core/tests/Something.php',
		'plugins/ttm-core/vendor/autoload.php',
		'themes/ttm-theme/style.css',
		'themes/ttm-theme/theme.json',
		'themes/ttm-theme/assets/css/ttm.css',
		'themes/ttm-theme/assets/css/ttm.css.map',
		'unrelated/file.txt',
	];

	it( 'pluginFiles keeps build/index.js, blocks, PHP, readme and drops node_modules, tests, vendor, src/editor, src JS and maps', () => {
		const kept = pluginFiles( SAMPLE_PATHS ).map( ( p ) => p.to );

		expect( kept ).toEqual(
			expect.arrayContaining( [
				'build/index.js',
				'build/blocks/lead-story/index.js',
				'blocks/lead-story/render.php',
				'blocks/lead-story/index.js',
				'ttm-core.php',
				'uninstall.php',
				'readme.txt',
				'README.md',
				'src/Config.php',
				'assets/css/editor.css',
			] )
		);

		for ( const dropped of [
			'build/index.js.map',
			'src/index.js',
			'src/editor/Sidebar.js',
			'src/editor/Sidebar.js.map',
			'node_modules/foo/index.js',
			'tests/Something.php',
			'vendor/autoload.php',
		] ) {
			expect( kept ).not.toContain( dropped );
		}

		expect( kept.every( ( to ) => ! to.startsWith( '../' ) ) ).toBe( true );
	} );

	it( 'themePaths keeps theme files and drops maps', () => {
		const kept = themePaths( SAMPLE_PATHS ).map( ( p ) => p.to );

		expect( kept ).toEqual(
			expect.arrayContaining( [
				'style.css',
				'theme.json',
				'assets/css/ttm.css',
			] )
		);
		expect( kept ).not.toContain( 'assets/css/ttm.css.map' );
		expect( kept.some( ( to ) => to.startsWith( 'plugins/' ) ) ).toBe(
			false
		);
	} );
} );

const DIST_TTM_CORE = path.join(
	__dirname,
	'..',
	'..',
	'dist',
	'ttm-core.zip'
);

( fs.existsSync( DIST_TTM_CORE ) ? describe : describe.skip )(
	'dist zip (only when npm run release:pack has already run)',
	() => {
		it( 'dist zips unpack to one top-level directory with build/index.js, LICENSE and no .map or test file', () => {
			const entries = readZip( fs.readFileSync( DIST_TTM_CORE ) );
			const names = entries.map( ( e ) => e.name );

			expect(
				names.every( ( name ) => name.startsWith( 'ttm-core/' ) )
			).toBe( true );
			expect( names ).toContain( 'ttm-core/build/index.js' );
			expect( names ).toContain( 'ttm-core/LICENSE' );
			expect( names.some( ( name ) => name.endsWith( '.map' ) ) ).toBe(
				false
			);
			expect( names.some( ( name ) => name.includes( '/tests/' ) ) ).toBe(
				false
			);
		} );
	}
);
