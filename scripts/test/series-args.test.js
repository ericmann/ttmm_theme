/**
 * Tests for the P2-06 series.json -> series:assign argument formatter
 * (scripts/live/series-args.mjs).
 */

const path = require( 'path' );

let seriesArgsLines;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'live', 'series-args.mjs' )
	);
	( { seriesArgsLines } = mod );
} );

describe( 'seriesArgsLines', () => {
	it( 'prints one pipe-separated line per series with tags joined by commas', () => {
		const entries = [
			{
				slug: 'boundless-summer-challenge',
				name: 'Boundless Summer Challenge',
				tags: [ 'boundless-summer-challenge', 'boundless' ],
				form: 'nonfiction',
				status: 'complete',
			},
			{
				slug: 'cryptopals',
				name: 'Cryptopals',
				tags: [ 'cryptopals' ],
				form: 'nonfiction',
				status: 'complete',
				total: 6,
			},
		];

		expect( seriesArgsLines( entries ) ).toEqual( [
			'boundless-summer-challenge|boundless-summer-challenge,boundless|nonfiction|complete||Boundless Summer Challenge',
			'cryptopals|cryptopals|nonfiction|complete|6|Cryptopals',
		] );
	} );

	it( 'keeps an empty total field empty rather than shifting name into it', () => {
		// Regression: `plan.sh` splits this line with `IFS='|' read`, which (unlike a tab)
		// never collapses two adjacent delimiters -- an empty `total` field must stay its own
		// field, not disappear and shift `name` one column over.
		const entries = [
			{
				slug: 'x',
				name: 'X',
				tags: [ 'x' ],
				form: 'nonfiction',
				status: 'ongoing',
			},
		];

		const [ line ] = seriesArgsLines( entries );
		const fields = line.split( '|' );

		expect( fields ).toHaveLength( 6 );
		expect( fields[ 4 ] ).toBe( '' );
		expect( fields[ 5 ] ).toBe( 'X' );
	} );

	it( 'rejects an entry without slug or tags', () => {
		expect( () => seriesArgsLines( [ { tags: [ 'x' ] } ] ) ).toThrow(
			/missing slug/
		);
		expect( () => seriesArgsLines( [ { slug: 'x', tags: [] } ] ) ).toThrow(
			/missing tags/
		);
	} );
} );
