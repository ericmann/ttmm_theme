/**
 * Tests for the screenshot script's pure helpers (scripts/screenshots.mjs; P0-05, extended in
 * P0-12, P0-01, P0-08). Importing the module does not launch Playwright -- the capture only
 * runs when the file is executed directly (see its own `import.meta.url` guard), so this is a
 * safe, plain import here.
 */

const path = require( 'path' );

let SETS;
let OWNER_ZONES;
let README_ZONES;
let SCREENSHOT_MAX_BYTES;
let unionClip;
let pendingImages;
let selectedSet;

beforeAll( async () => {
	const mod = await import( path.join( __dirname, '..', 'screenshots.mjs' ) );
	( {
		SETS,
		OWNER_ZONES,
		README_ZONES,
		SCREENSHOT_MAX_BYTES,
		unionClip,
		pendingImages,
		selectedSet,
	} = mod );
} );

describe( 'SETS.owner', () => {
	it( 'owner set writes the six §6.10 files to docs/feedback/phase-5', () => {
		expect( SETS.owner.outDir ).toBe(
			path.join( 'docs', 'feedback', 'phase-5' )
		);
		expect( SETS.owner.zones ).toBe( OWNER_ZONES );
		expect(
			OWNER_ZONES.map( ( zone ) => [ zone.file, zone.path ] )
		).toEqual( [
			[ 'front.png', '/' ],
			[ 'article.png', '/signing-your-options-table/' ],
			[ 'archive-technology.png', '/category/technology/' ],
			[ 'writing.png', '/writing/' ],
			[ 'about.png', '/about/' ],
			[ 'front-390.png', '/' ],
		] );
		expect( OWNER_ZONES.every( ( zone ) => zone.fullPage ) ).toBe( true );
	} );

	it( 'owner set has no live zones', () => {
		expect( SETS.owner ).not.toHaveProperty( 'live' );
		expect( OWNER_ZONES.some( ( zone ) => zone.live ) ).toBe( false );
		expect( Object.keys( SETS ).sort() ).toEqual( [ 'owner', 'readme' ] );

		const source = require( 'fs' ).readFileSync(
			path.join( __dirname, '..', 'screenshots.mjs' ),
			'utf8'
		);
		expect( source ).not.toMatch( /LIVE_ZONES/ );
		expect( source ).not.toMatch( /screens\.json/ );
	} );

	it( 'front-390 uses a 390px viewport', () => {
		const front390 = OWNER_ZONES.find(
			( zone ) => 'front-390.png' === zone.file
		);

		expect( front390.viewport ).toEqual( { width: 390, height: 844 } );
	} );
} );

describe( 'SETS.readme', () => {
	it( 'readme set writes the eight §6.5 files to .github/screenshots', () => {
		expect( SETS.readme.outDir ).toBe(
			path.join( '.github', 'screenshots' )
		);
		expect( SETS.readme.zones ).toBe( README_ZONES );
		expect(
			README_ZONES.map( ( zone ) => [ zone.file, zone.path ] )
		).toEqual( [
			[ 'front-1280.png', '/' ],
			[ 'article-1280.png', '/signing-your-options-table/' ],
			[ 'journal-1280.png', '/journal-post-1/' ],
			[ 'archive-1280.png', '/category/technology/' ],
			[ 'series-hub-1280.png', '/series/' ],
			[ 'writing-1280.png', '/writing/' ],
			[ 'front-390.png', '/' ],
			[ 'article-390.png', '/signing-your-options-table/' ],
		] );
	} );

	it( 'phone shots are 390 wide and clipped to 2200px', () => {
		const phoneZones = README_ZONES.filter(
			( zone ) => 390 === zone.viewport.width
		);

		expect( phoneZones ).toHaveLength( 2 );
		for ( const zone of phoneZones ) {
			expect( zone.viewport.width ).toBe( 390 );
			expect( zone.clipHeight ).toBe( 2200 );
			expect( zone.fullPage ).toBeUndefined();
		}
	} );
} );

describe( 'selectedSet', () => {
	it( '--readme selects the readme set and no flag the owner set', () => {
		expect( selectedSet( [] ) ).toBe( 'owner' );
		expect( selectedSet( [ '--readme' ] ) ).toBe( 'readme' );
	} );
} );

describe( 'SCREENSHOT_MAX_BYTES', () => {
	it( 'SCREENSHOT_MAX_BYTES is exported', () => {
		expect( typeof SCREENSHOT_MAX_BYTES ).toBe( 'number' );
		expect( SCREENSHOT_MAX_BYTES ).toBeGreaterThan( 0 );
	} );
} );

describe( 'pendingImages', () => {
	it( 'pendingImages lists the sources whose naturalWidth is 0', () => {
		const list = [
			{ src: 'https://example.com/loaded.png', naturalWidth: 800 },
			{ src: 'https://example.com/still-loading.png', naturalWidth: 0 },
			{ src: 'https://example.com/broken.png', naturalWidth: 0 },
		];

		expect( pendingImages( list ) ).toEqual( [
			'https://example.com/still-loading.png',
			'https://example.com/broken.png',
		] );
	} );

	it( 'returns an empty list when every image has loaded', () => {
		const list = [
			{ src: 'https://example.com/a.png', naturalWidth: 400 },
			{ src: 'https://example.com/b.png', naturalWidth: 200 },
		];

		expect( pendingImages( list ) ).toEqual( [] );
	} );
} );

describe( 'unionClip', () => {
	it( 'clip helper unions two bounding boxes', () => {
		const a = { x: 10, y: 20, width: 100, height: 30 };
		const b = { x: 0, y: 200, width: 50, height: 40 };

		expect( unionClip( a, b ) ).toEqual( {
			x: 0,
			y: 20,
			width: 110,
			height: 220,
		} );
	} );
} );
