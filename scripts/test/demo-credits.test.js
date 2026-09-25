/**
 * Tests for the P1-03 committed demo fixtures: docs/fixtures/demo/images.json's rows and
 * docs/fixtures/demo/CREDITS.json's rows line up, and every credit is cc0/pdm and names an
 * existing file.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const IMAGES_JSON = JSON.parse(
	fs.readFileSync(
		path.join( __dirname, '..', 'demo', 'images.json' ),
		'utf8'
	)
);

const CREDITS_JSON = JSON.parse(
	fs.readFileSync(
		path.join(
			__dirname,
			'..',
			'..',
			'docs',
			'fixtures',
			'demo',
			'CREDITS.json'
		),
		'utf8'
	)
);

const IMAGES_DIR = path.join(
	__dirname,
	'..',
	'..',
	'docs',
	'fixtures',
	'demo',
	'images'
);

describe( 'CREDITS.json', () => {
	it( 'has one row per images.json file', () => {
		const imageFiles = IMAGES_JSON.map( ( row ) => row.file ).sort();
		const creditFiles = CREDITS_JSON.map( ( row ) => row.file ).sort();

		expect( creditFiles ).toEqual( imageFiles );
	} );

	it( 'every row is cc0 or pdm and names an existing file', () => {
		for ( const row of CREDITS_JSON ) {
			expect( [ 'cc0', 'pdm' ] ).toContain( row.license );
			expect( fs.existsSync( path.join( IMAGES_DIR, row.file ) ) ).toBe(
				true
			);
		}
	} );
} );
