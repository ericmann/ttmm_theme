/**
 * Tests for the P4-01/P4-05 live-check pure helpers (tests/e2e/lib/live.mjs). Loaded via
 * dynamic import() the way scripts/test/live-screens.test.js loads scripts/live/lib/screens.mjs
 * -- `debugLogLineCount()` shells out and isn't covered here (no WordPress/Docker in unit tests).
 */

const path = require( 'path' );

let classifyRequests;
let phpErrorMarkers;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', '..', 'tests', 'e2e', 'lib', 'live.mjs' )
	);
	( { classifyRequests, phpErrorMarkers } = mod );
} );

const ALLOWED_HOST = /^(?:stats\.wp\.com|jetpack\.com)$/i;

describe( 'classifyRequests', () => {
	it( 'flags a cross-origin main-frame script/stylesheet/xhr/font as an offender', () => {
		const requests = [
			{
				url: 'https://cdn.example.com/app.js',
				type: 'script',
				mainFrame: true,
			},
			{
				url: 'https://cdn.example.com/app.css',
				type: 'stylesheet',
				mainFrame: true,
			},
		];

		const { offenders } = classifyRequests(
			requests,
			'example.test',
			ALLOWED_HOST
		);

		expect( offenders ).toEqual( [
			'https://cdn.example.com/app.js',
			'https://cdn.example.com/app.css',
		] );
	} );

	it( 'ignores same-origin and allow-listed hosts', () => {
		const requests = [
			{
				url: 'https://example.test/theme.css',
				type: 'stylesheet',
				mainFrame: true,
			},
			{
				url: 'https://stats.wp.com/g.gif',
				type: 'xhr',
				mainFrame: true,
			},
		];

		const { offenders } = classifyRequests(
			requests,
			'example.test',
			ALLOWED_HOST
		);

		expect( offenders ).toEqual( [] );
	} );

	it( 'counts cross-origin images per host instead of failing', () => {
		const requests = [
			{
				url: 'https://ttmm.io/uploads/photo.jpg',
				type: 'image',
				mainFrame: true,
			},
			{
				url: 'https://ttmm.io/uploads/other.jpg',
				type: 'image',
				mainFrame: true,
			},
		];

		const { offenders, imagesByHost } = classifyRequests(
			requests,
			'example.test',
			ALLOWED_HOST
		);

		expect( offenders ).toEqual( [] );
		expect( imagesByHost ).toEqual( { 'ttmm.io': 2 } );
	} );

	it( 'never flags a request from a child frame (P4-05: embedded post content)', () => {
		const requests = [
			{
				url: 'https://www.youtube.com/s/player/base.js',
				type: 'script',
				mainFrame: false,
			},
			{
				url: 'https://www.youtube.com/s/player/style.css',
				type: 'stylesheet',
				mainFrame: false,
			},
		];

		const { offenders } = classifyRequests(
			requests,
			'example.test',
			ALLOWED_HOST
		);

		expect( offenders ).toEqual( [] );
	} );

	it( 'ignores data: and blob: URLs entirely', () => {
		const requests = [
			{
				url: 'data:image/png;base64,AAAA',
				type: 'image',
				mainFrame: true,
			},
			{
				url: 'blob:https://example.test/xyz',
				type: 'xhr',
				mainFrame: true,
			},
		];

		const { offenders, imagesByHost } = classifyRequests(
			requests,
			'example.test',
			ALLOWED_HOST
		);

		expect( offenders ).toEqual( [] );
		expect( imagesByHost ).toEqual( {} );
	} );
} );

describe( 'phpErrorMarkers', () => {
	it( 'finds every marker present, in order', () => {
		const body =
			'before Deprecated: foo after Notice: bar and Warning: baz';

		expect( phpErrorMarkers( body ) ).toEqual( [
			'Warning:',
			'Notice:',
			'Deprecated:',
		] );
	} );

	it( 'returns an empty array for clean body text', () => {
		expect( phpErrorMarkers( 'All good here.' ) ).toEqual( [] );
	} );
} );
