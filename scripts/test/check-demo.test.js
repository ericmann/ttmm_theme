/**
 * Tests for the P0-05 demo-content checks (scripts/lib/demo-checks.mjs).
 */

const path = require( 'path' );

let checkCredits;
let checkFixtureImages;
let checkReadmeScreenshots;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'demo-checks.mjs' )
	);
	( { checkCredits, checkFixtureImages, checkReadmeScreenshots } = mod );
} );

const LIMITS = { maxBytes: 350000, budgetBytes: 8000000 };

/**
 * A complete, conforming CREDITS.json row for `demo-example.jpg`.
 *
 * @param {Object} overrides Field overrides.
 * @return {Object} A row.
 */
function creditsRow( overrides = {} ) {
	return {
		file: 'demo-example.jpg',
		openverse_id: 'abc-123',
		title: 'Example',
		creator: 'Someone',
		creator_url: 'https://example.test/someone',
		source: 'openverse',
		foreign_landing_url: 'https://example.test/photo',
		license: 'cc0',
		license_url: 'https://creativecommons.org/publicdomain/zero/1.0/',
		width: 1600,
		height: 900,
		bytes: 1000,
		sha256: 'a'.repeat( 64 ),
		attribution: 'Example by Someone',
		...overrides,
	};
}

const FILE = {
	name: 'demo-example.jpg',
	bytes: 1000,
	sha256: 'a'.repeat( 64 ),
};

describe( 'checkCredits', () => {
	it( 'passes with no images and no CREDITS', () => {
		expect( checkCredits( { files: [], credits: null }, LIMITS ) ).toEqual(
			[]
		);
	} );

	it( 'passes when every file has a matching cc0/pdm row', () => {
		expect(
			checkCredits(
				{ files: [ FILE ], credits: [ creditsRow() ] },
				LIMITS
			)
		).toEqual( [] );
	} );

	it( 'fails on a file without a row and a row without a file', () => {
		const noRow = checkCredits( { files: [ FILE ], credits: [] }, LIMITS );
		expect( noRow.join( '\n' ) ).toMatch( /no CREDITS\.json row/ );

		const noFile = checkCredits(
			{
				files: [],
				credits: [ creditsRow( { file: 'demo-missing.jpg' } ) ],
			},
			LIMITS
		);
		expect( noFile.join( '\n' ) ).toMatch( /no matching file/ );
	} );

	it( 'fails on a licence outside cc0/pdm', () => {
		const failures = checkCredits(
			{
				files: [ FILE ],
				credits: [ creditsRow( { license: 'by' } ) ],
			},
			LIMITS
		);
		expect( failures.join( '\n' ) ).toMatch( /is not cc0\/pdm/ );
	} );

	it( 'fails on a missing rule 53 field', () => {
		const row = creditsRow();
		delete row.title;
		const failures = checkCredits(
			{ files: [ FILE ], credits: [ row ] },
			LIMITS
		);
		expect( failures.join( '\n' ) ).toMatch( /missing field "title"/ );
	} );

	it( 'allows a null creator_url', () => {
		const failures = checkCredits(
			{
				files: [ FILE ],
				credits: [ creditsRow( { creator_url: null } ) ],
			},
			LIMITS
		);
		expect( failures ).toEqual( [] );
	} );

	it( 'fails on a bytes or sha256 mismatch', () => {
		const bytesMismatch = checkCredits(
			{ files: [ FILE ], credits: [ creditsRow( { bytes: 999 } ) ] },
			LIMITS
		);
		expect( bytesMismatch.join( '\n' ) ).toMatch( /bytes.*doesn't match/ );

		const shaMismatch = checkCredits(
			{
				files: [ FILE ],
				credits: [ creditsRow( { sha256: 'b'.repeat( 64 ) } ) ],
			},
			LIMITS
		);
		expect( shaMismatch.join( '\n' ) ).toMatch( /sha256 doesn't match/ );
	} );

	it( 'fails on a file over IMAGE_MAX_BYTES and a total over IMAGE_BUDGET_BYTES', () => {
		const bigFile = {
			name: 'demo-big.jpg',
			bytes: 400000,
			sha256: 'c'.repeat( 64 ),
		};
		const overMax = checkCredits(
			{
				files: [ bigFile ],
				credits: [
					creditsRow( { file: 'demo-big.jpg', bytes: 400000 } ),
				],
			},
			LIMITS
		);
		expect( overMax.join( '\n' ) ).toMatch( /exceeds IMAGE_MAX_BYTES/ );

		const manyFiles = Array.from( { length: 9 }, ( _, i ) => ( {
			name: `demo-file-${ i }.jpg`,
			bytes: 1000000,
			sha256: `${ i }`.repeat( 64 ).slice( 0, 64 ),
		} ) );
		const manyRows = manyFiles.map( ( f ) =>
			creditsRow( { file: f.name, bytes: f.bytes, sha256: f.sha256 } )
		);
		const overBudget = checkCredits(
			{ files: manyFiles, credits: manyRows },
			LIMITS
		);
		expect( overBudget.join( '\n' ) ).toMatch(
			/exceeds IMAGE_BUDGET_BYTES/
		);
	} );

	it( 'fails on a bad file name', () => {
		const badFile = {
			name: 'not-a-demo-file.jpg',
			bytes: 1000,
			sha256: 'd'.repeat( 64 ),
		};
		const failures = checkCredits(
			{
				files: [ badFile ],
				credits: [ creditsRow( { file: badFile.name } ) ],
			},
			LIMITS
		);
		expect( failures.join( '\n' ) ).toMatch(
			/doesn't match \^demo-\[a-z0-9-\]\+\\\.jpg\$/
		);
	} );
} );

describe( 'checkFixtureImages', () => {
	it( 'passes when the referenced file exists', () => {
		expect(
			checkFixtureImages(
				[ { slug: 'a', featured_image: 'demo-example.jpg' } ],
				[ 'demo-example.jpg' ]
			)
		).toEqual( [] );
	} );

	it( 'ignores non-string featured_image values', () => {
		expect(
			checkFixtureImages( [ { slug: 'a', featured_image: true } ], [] )
		).toEqual( [] );
	} );

	it( 'fails when a fixture names a missing demo file', () => {
		const failures = checkFixtureImages(
			[ { slug: 'a', featured_image: 'demo-missing.jpg' } ],
			[ 'demo-example.jpg' ]
		);
		expect( failures.join( '\n' ) ).toMatch( /has no matching demo file/ );
	} );
} );

describe( 'checkReadmeScreenshots', () => {
	it( 'README: passes with no screenshots directory and no references', () => {
		expect( checkReadmeScreenshots( 'Hello, world.', [] ) ).toEqual( [] );
	} );

	it( 'README: fails on a referenced missing screenshot and on an unreferenced file', () => {
		const readme =
			'![Front page](.github/screenshots/front.png)\n' +
			'<img src=".github/screenshots/missing.png">\n';
		const failures = checkReadmeScreenshots( readme, [
			'front.png',
			'unreferenced.png',
		] );
		expect( failures.join( '\n' ) ).toMatch(
			/references missing screenshot missing\.png/
		);
		expect( failures.join( '\n' ) ).toMatch(
			/unreferenced\.png: not referenced/
		);
	} );

	it( 'fails on a non-.png file', () => {
		const readme = '![Front page](.github/screenshots/front.jpg)';
		const failures = checkReadmeScreenshots( readme, [ 'front.jpg' ] );
		expect( failures.join( '\n' ) ).toMatch( /not a \.png file/ );
	} );
} );
