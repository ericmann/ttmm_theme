/**
 * Tests for the P0-01 tagged-fixme guard (scripts/lib/fixme.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );

let taggedFixmeHits;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'fixme.mjs' )
	);
	( { taggedFixmeHits } = mod );
} );

describe( 'taggedFixmeHits', () => {
	it( 'untagged fixme fails', () => {
		const lines = [ "test.fixme( 'bar-mark', () => {} );" ];

		const hits = taggedFixmeHits( lines, true );

		expect( hits ).toHaveLength( 1 );
		expect( hits[ 0 ].tagged ).toBe( false );
	} );

	it( 'tagged fixme passes while allowed', () => {
		const lines = [ "test.fixme( 'bar-mark', () => {} ); // P1-01" ];

		const hits = taggedFixmeHits( lines, true );

		expect( hits ).toHaveLength( 0 );
	} );

	it( 'tagged fixme fails when not allowed', () => {
		const lines = [ "test.fixme( 'bar-mark', () => {} ); // P1-01" ];

		const hits = taggedFixmeHits( lines, false );

		expect( hits ).toHaveLength( 1 );
		expect( hits[ 0 ].tagged ).toBe( true );
	} );
} );

describe( 'check-fixme.mjs', () => {
	it( 'ALLOW_TAGGED is false now that the flight has landed (P5-03)', () => {
		const source = fs.readFileSync(
			path.join( __dirname, '..', 'check-fixme.mjs' ),
			'utf8'
		);

		expect( source ).toMatch( /export const ALLOW_TAGGED = false;/ );
	} );
} );
