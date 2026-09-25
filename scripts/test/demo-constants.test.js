/**
 * Tests for the P0-01 demo-content script constants (scripts/demo/lib/constants.mjs).
 */

const path = require( 'path' );

let constants;

beforeAll( async () => {
	constants = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'constants.mjs' )
	);
} );

describe( 'demo constants', () => {
	it( 'exports the six SPEC §5 constants with their SPEC values', () => {
		expect( constants.IMAGE_MAX_BYTES ).toBe( 350000 );
		expect( constants.IMAGE_BUDGET_BYTES ).toBe( 8000000 );
		expect( constants.OPENVERSE_MIN_WIDTH ).toBe( 1600 );
		expect( constants.OPENVERSE_PAGE_SIZE ).toBe( 20 );
		expect( constants.OPENVERSE_PACE_MS ).toBe( 3500 );
		expect( constants.OPENVERSE_LICENSES ).toBe( 'cc0,pdm' );
	} );

	it( 'OPENVERSE_LICENSES is exactly cc0,pdm', () => {
		expect( constants.OPENVERSE_LICENSES ).toBe( 'cc0,pdm' );
	} );
} );
