/**
 * Tests for the screenshot script's pure helpers (scripts/screenshots.mjs; P0-05, extended in
 * P0-12, P0-01). Importing the module does not launch Playwright -- the capture only runs when
 * the file is executed directly (see its own `import.meta.url` guard), so this is a safe,
 * plain import here.
 */

const path = require( 'path' );

let ZONES;
let SEEDED_ZONES;
let LIVE_ZONES;
let unionClip;
let pendingImages;

beforeAll( async () => {
	const mod = await import( path.join( __dirname, '..', 'screenshots.mjs' ) );
	( { ZONES, SEEDED_ZONES, LIVE_ZONES, unionClip, pendingImages } = mod );
} );

describe( 'ZONES', () => {
	it( 'ZONES has 17 seeded entries and 7 live entries', () => {
		expect( ZONES ).toHaveLength( 24 );
		expect( SEEDED_ZONES ).toHaveLength( 17 );
		expect( LIVE_ZONES ).toHaveLength( 7 );
	} );

	it( 'lists the phase-3 fourteen plus the three phase-4 files with paths', () => {
		expect(
			SEEDED_ZONES.map( ( zone ) => [ zone.file, zone.path ] )
		).toEqual( [
			[ 'article.png', '/signing-your-options-table/' ],
			[ 'journal.png', '/journal-post-1/' ],
			[ 'writing.png', '/writing/' ],
			[ 'archive-security.png', '/category/security/' ],
			[ 'series-hub.png', '/series/' ],
			[ 'series-single.png', '/series/hardening-wordpress/' ],
			[ 'search.png', '/?s=ledger' ],
			[ '404.png', '/this-page-does-not-exist/' ],
			[ 'article-390.png', '/signing-your-options-table/' ],
			[ 'journal-390.png', '/journal-post-1/' ],
			[ 'writing-390.png', '/writing/' ],
			[ 'archive-390.png', '/category/security/' ],
			[ 'front-1920.png', '/' ],
			[ 'article-1920.png', '/signing-your-options-table/' ],
			[
				'article-noseries.png',
				'/transients-object-caches-and-fast-enough/',
			],
			[ 'archive-business.png', '/category/business/' ],
			[ 'footer.png', '/' ],
		] );
	} );

	it( 'live zones are flagged live and seeded zones are not', () => {
		expect( SEEDED_ZONES.every( ( zone ) => false === zone.live ) ).toBe(
			true
		);
		expect( LIVE_ZONES.every( ( zone ) => true === zone.live ) ).toBe(
			true
		);
		expect( ZONES.filter( ( zone ) => zone.live ) ).toEqual( LIVE_ZONES );
		expect( ZONES.filter( ( zone ) => ! zone.live ) ).toEqual(
			SEEDED_ZONES
		);
	} );

	it( 'OUT_DIR is docs/feedback/phase-4', () => {
		const source = require( 'fs' ).readFileSync(
			path.join( __dirname, '..', 'screenshots.mjs' ),
			'utf8'
		);

		expect( source ).toMatch(
			/OUT_DIR = join\( 'docs', 'feedback', 'phase-4' \)/
		);
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
