/**
 * Tests for the P0-05 screenshot script's pure helpers (scripts/screenshots.mjs). Importing
 * the module does not launch Playwright -- the capture only runs when the file is executed
 * directly (see its own `import.meta.url` guard), so this is a safe, plain import here.
 */

const path = require( 'path' );

let ZONES;
let unionClip;
let pendingImages;

beforeAll( async () => {
	const mod = await import( path.join( __dirname, '..', 'screenshots.mjs' ) );
	( { ZONES, unionClip, pendingImages } = mod );
} );

describe( 'ZONES', () => {
	it( 'zone list names the seven files from SPEC 6.6', () => {
		const files = ZONES.map( ( zone ) => zone.file );

		expect( files ).toEqual( [
			'front-1280.png',
			'front-390.png',
			'masthead.png',
			'lead-row.png',
			'section-rows.png',
			'series-strip.png',
			'poster-footer.png',
		] );
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
