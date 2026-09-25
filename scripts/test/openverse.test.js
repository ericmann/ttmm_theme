/**
 * Tests for the P1-01 Openverse search/selection/credit logic (scripts/demo/lib/openverse.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );

let validateRows;
let searchUrl;
let pickResult;
let acceptEncoded;
let creditRow;
let sortCredits;

const IMAGES_JSON = JSON.parse(
	fs.readFileSync(
		path.join( __dirname, '..', 'demo', 'images.json' ),
		'utf8'
	)
);

const SEARCH_FIXTURE = JSON.parse(
	fs.readFileSync(
		path.join( __dirname, 'fixtures', 'openverse-search.json' ),
		'utf8'
	)
).results;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'openverse.mjs' )
	);
	( {
		validateRows,
		searchUrl,
		pickResult,
		acceptEncoded,
		creditRow,
		sortCredits,
	} = mod );
} );

describe( 'images.json', () => {
	it( 'images.json has 13 valid rows: 11 wide, 1 tall, 1 square', () => {
		expect( validateRows( IMAGES_JSON ) ).toEqual( [] );
		expect( IMAGES_JSON ).toHaveLength( 13 );

		const byOrientation = IMAGES_JSON.reduce( ( counts, row ) => {
			counts[ row.orientation ] = ( counts[ row.orientation ] || 0 ) + 1;
			return counts;
		}, {} );

		expect( byOrientation ).toEqual( { wide: 11, tall: 1, square: 1 } );
	} );
} );

describe( 'validateRows', () => {
	it( 'rejects duplicate files, bad names and bad orientations', () => {
		const rows = [
			{ file: 'demo-a.jpg', orientation: 'wide' },
			{ file: 'demo-a.jpg', orientation: 'wide' },
			{ file: 'not-a-demo-file.jpg', orientation: 'wide' },
			{ file: 'demo-b.jpg', orientation: 'diagonal' },
		];

		const failures = validateRows( rows );

		expect( failures.join( '\n' ) ).toMatch( /expected 13 rows/ );
		expect( failures.join( '\n' ) ).toMatch(
			/demo-a\.jpg: duplicate file/
		);
		expect( failures.join( '\n' ) ).toMatch(
			/not-a-demo-file\.jpg: doesn't match/
		);
		expect( failures.join( '\n' ) ).toMatch(
			/demo-b\.jpg: orientation "diagonal" is not wide\/tall\/square/
		);
	} );
} );

describe( 'searchUrl', () => {
	it( 'builds the SPEC §6.1 query', () => {
		const url = searchUrl(
			{ query: 'server room cables', orientation: 'wide' },
			{ pageSize: 20, licenses: 'cc0,pdm' }
		);

		expect( url ).toBe(
			'https://api.openverse.org/v1/images/?q=server%20room%20cables&license=cc0,pdm&aspect_ratio=wide&size=large&mature=false&page_size=20'
		);
	} );
} );

describe( 'pickResult', () => {
	const options = {
		minWidth: 1600,
		licenses: [ 'cc0', 'pdm' ],
	};

	it( 'skips narrow, non-cc0/pdm, non-jpg/png, excluded, chosen and skipped results', () => {
		const result = pickResult( SEARCH_FIXTURE, {
			...options,
			exclude: [ 'excluded-1' ],
			chosenIds: [ 'chosen-1' ],
			skipIds: [ 'skipped-1' ],
		} );

		// Only "qualifies-1" survives: narrow-1 (too narrow), wrong-license-1 (by),
		// wrong-type-1 (gif), excluded-1 (excluded), chosen-1 (already chosen), skipped-1
		// (skipped) are all ruled out in order.
		expect( result.id ).toBe( 'qualifies-1' );
		// filetype null -> falls back to the .png extension in the URL; license "CC0" ->
		// matched case-insensitively.
		expect( result.filetype ).toBeNull();
	} );

	it( 'pickResult returns null when nothing qualifies', () => {
		const noneQualify = SEARCH_FIXTURE.filter( ( result ) =>
			[ 'narrow-1', 'wrong-license-1', 'wrong-type-1' ].includes(
				result.id
			)
		);

		const result = pickResult( noneQualify, options );

		expect( result ).toBeNull();
	} );
} );

describe( 'acceptEncoded', () => {
	it( 'rejects a download narrower than minWidth and one over maxBytes', () => {
		const limits = { minWidth: 1600, maxBytes: 350000 };

		expect( acceptEncoded( { width: 1600, bytes: 200000 }, limits ) ).toBe(
			true
		);
		expect( acceptEncoded( { width: 960, bytes: 200000 }, limits ) ).toBe(
			false
		);
		expect( acceptEncoded( { width: 1600, bytes: 400000 }, limits ) ).toBe(
			false
		);
	} );
} );

describe( 'creditRow', () => {
	it( 'maps every rule 53 field plus attribution', () => {
		const result = SEARCH_FIXTURE.find(
			( row ) => 'qualifies-1' === row.id
		);

		const row = creditRow( result, 'demo-example.jpg', {
			width: 1600,
			height: 1067,
			bytes: 123456,
			sha256: 'a'.repeat( 64 ),
		} );

		expect( row ).toEqual( {
			file: 'demo-example.jpg',
			openverse_id: 'qualifies-1',
			title: 'The qualifying photo',
			creator: 'Seventh Person',
			creator_url: null,
			source: 'example',
			foreign_landing_url: 'https://example.test/photos/qualifies-1',
			license: 'cc0',
			license_url: 'https://creativecommons.org/publicdomain/zero/1.0/',
			width: 1600,
			height: 1067,
			bytes: 123456,
			sha256: 'a'.repeat( 64 ),
			attribution: 'The qualifying photo by Seventh Person',
		} );
	} );
} );

describe( 'sortCredits', () => {
	it( 'orders rows by file with a fixed key order', () => {
		const rows = [
			{
				attribution: 'b attribution',
				file: 'demo-b.jpg',
				sha256: 'b',
				bytes: 2,
				height: 2,
				width: 2,
				license_url: 'https://example.test/b',
				license: 'pdm',
				foreign_landing_url: 'https://example.test/b-landing',
				source: 'example',
				creator_url: null,
				creator: 'B Creator',
				title: 'B',
				openverse_id: 'b-id',
			},
			{
				attribution: 'a attribution',
				file: 'demo-a.jpg',
				sha256: 'a',
				bytes: 1,
				height: 1,
				width: 1,
				license_url: 'https://example.test/a',
				license: 'cc0',
				foreign_landing_url: 'https://example.test/a-landing',
				source: 'example',
				creator_url: 'https://example.test/a-creator',
				creator: 'A Creator',
				title: 'A',
				openverse_id: 'a-id',
			},
		];

		const sorted = sortCredits( rows );

		expect( sorted.map( ( row ) => row.file ) ).toEqual( [
			'demo-a.jpg',
			'demo-b.jpg',
		] );
		expect( Object.keys( sorted[ 0 ] ) ).toEqual( [
			'file',
			'openverse_id',
			'title',
			'creator',
			'creator_url',
			'source',
			'foreign_landing_url',
			'license',
			'license_url',
			'width',
			'height',
			'bytes',
			'sha256',
			'attribution',
		] );
	} );
} );
