/**
 * Tests for the P5-01 backup-drill body hasher (scripts/live/hash-body.mjs).
 */

const path = require( 'path' );

let normalizeBody;
let hashBody;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'live', 'hash-body.mjs' )
	);
	( { normalizeBody, hashBody } = mod );
} );

describe( 'normalizeBody', () => {
	it( 'normalizes whitespace and strips the generator meta', () => {
		const html =
			'<html><head><meta name="generator" content="WordPress 7.1.1">' +
			'</head><body>\n\n  Hello   world  \n</body></html>';

		const normalized = normalizeBody( html );

		expect( normalized ).not.toMatch( /generator/i );
		expect( normalized ).toBe(
			'<html><head></head><body> Hello world </body></html>'
		);
	} );
} );

describe( 'hashBody', () => {
	it( 'hash is stable across whitespace differences', () => {
		const a = '<body> Hello   world </body>';
		const b = '<body>\nHello\nworld\n</body>';

		expect( hashBody( a ) ).toBe( hashBody( b ) );
	} );

	it( 'hash changes when text changes', () => {
		const a = '<body>Hello world</body>';
		const b = '<body>Goodbye world</body>';

		expect( hashBody( a ) ).not.toBe( hashBody( b ) );
	} );

	it( 'hash is unaffected by the generator meta version changing', () => {
		const a =
			'<meta name="generator" content="WordPress 7.1.1"><body>Same</body>';
		const b =
			'<meta name="generator" content="WordPress 7.2.0"><body>Same</body>';

		expect( hashBody( a ) ).toBe( hashBody( b ) );
	} );
} );
