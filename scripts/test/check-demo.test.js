/**
 * Tests for the P0-05 demo-content checks (scripts/lib/demo-checks.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );

let checkCredits;
let checkFixtureImages;
let checkReadmeScreenshots;
let checkDemoOutputs;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'demo-checks.mjs' )
	);
	( {
		checkCredits,
		checkFixtureImages,
		checkReadmeScreenshots,
		checkDemoOutputs,
	} = mod );
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

const IMAGE_RAW_BASE =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/docs/fixtures/demo/images';
const WXR_RAW_URL =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/.github/demo-content.xml';

function buildWxr() {
	return `<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
<title>Demo</title>
<item>
	<wp:post_id>1001</wp:post_id>
	<wp:post_type><![CDATA[post]]></wp:post_type>
</item>
<item>
	<wp:post_id>1002</wp:post_id>
	<wp:post_type><![CDATA[page]]></wp:post_type>
</item>
<item>
	<wp:post_id>1003</wp:post_id>
	<wp:post_type><![CDATA[attachment]]></wp:post_type>
	<wp:attachment_url>${ IMAGE_RAW_BASE }/demo-a.jpg</wp:attachment_url>
</item>
</channel>
</rss>
`;
}

function buildOptions( overrides = {} ) {
	return {
		blogname: 'These Things Matter',
		blogdescription: '',
		timezone_string: '',
		permalink_structure: '/%postname%/',
		ttm_books: [],
		ttm_verse: null,
		ttm_verse_history: null,
		ttm_settings: { newsletter: { provider: 'none', endpoint: '' } },
		...overrides,
	};
}

function buildBlueprint( { options, pluginVersion = '1.2.3' } ) {
	return {
		steps: [
			{ step: 'setSiteOptions', options },
			{
				step: 'installPlugin',
				pluginData: {
					resource: 'url',
					url: `https://github-proxy.com/proxy/?repo=ericmann/ttmm_theme&release=v${ pluginVersion }&asset=ttm-core.zip`,
				},
			},
			{
				step: 'installTheme',
				themeData: {
					resource: 'url',
					url: `https://github-proxy.com/proxy/?repo=ericmann/ttmm_theme&release=v${ pluginVersion }&asset=ttm-theme.zip`,
				},
			},
			{ step: 'wp-cli', command: 'wp site empty --yes' },
			{
				step: 'importWxr',
				file: { resource: 'url', url: WXR_RAW_URL },
			},
			{
				step: 'wp-cli',
				command: 'wp rewrite structure /%postname%/ --hard',
			},
			{ step: 'wp-cli', command: 'wp ttm primary:assign' },
			{ step: 'wp-cli', command: 'wp ttm recount --all' },
			{ step: 'wp-cli', command: 'wp ttm series:rebuild' },
			{ step: 'wp-cli', command: 'wp ttm stats:flush' },
			{
				step: 'wp-cli',
				command:
					'wp ttm demo:verify --posts=1 --pages=1 --series=0 --attachments=1',
			},
		],
	};
}

function conformingSet() {
	const options = buildOptions();
	return {
		wxr: buildWxr(),
		options,
		blueprint: buildBlueprint( { options } ),
		imageFiles: [ 'demo-a.jpg' ],
		credits: [ { file: 'demo-a.jpg' } ],
		fixtures: {
			posts: [ { slug: 'p1', featured_image: 'demo-a.jpg' } ],
			pages: [ { slug: 'about', featured_image: 'demo-a.jpg' } ],
		},
		pluginVersion: '1.2.3',
	};
}

describe( 'checkDemoOutputs', () => {
	it( 'outputs: passes on a conforming synthetic set', () => {
		expect( checkDemoOutputs( conformingSet() ) ).toEqual( [] );
	} );

	it( 'outputs: fails on a count mismatch', () => {
		const data = conformingSet();
		data.fixtures.posts.push( { slug: 'p2', featured_image: null } );

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch( /post item\(s\), expected 2/ );
	} );

	it( 'outputs: fails on a localhost URL', () => {
		const data = conformingSet();
		data.wxr = data.wxr.replace(
			'<title>Demo</title>',
			'<title>Demo</title><link>http://localhost:8888</link>'
		);

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch( /local host reference/ );
	} );

	it( 'outputs: fails on an e-mail', () => {
		const data = conformingSet();
		data.options = buildOptions( {
			blogdescription: 'contact eric@example.com',
		} );
		data.blueprint = buildBlueprint( { options: data.options } );

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch( /e-mail address/ );
	} );

	it( 'outputs: fails on a missing options key', () => {
		const data = conformingSet();
		delete data.options.ttm_verse_history;
		data.blueprint = buildBlueprint( { options: data.options } );

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch(
			/demo-options\.json keys are/
		);
	} );

	it( 'outputs: fails on a wrong release tag', () => {
		const data = conformingSet();
		data.pluginVersion = '9.9.9';

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch( /release=v9\.9\.9/ );
	} );

	it( 'outputs: fails on a step out of order', () => {
		const data = conformingSet();
		const steps = data.blueprint.steps;
		[ steps[ 1 ], steps[ 2 ] ] = [ steps[ 2 ], steps[ 1 ] ];

		const failures = checkDemoOutputs( data );
		expect( failures.join( '\n' ) ).toMatch(
			/expected the SPEC §6\.4 order/
		);
	} );

	it( 'outputs: required once REQUIRE_OUTPUTS is true', () => {
		const source = fs.readFileSync(
			path.join( __dirname, '..', 'check-demo.mjs' ),
			'utf8'
		);

		expect( source ).toMatch( /const REQUIRE_OUTPUTS = true;/ );
	} );
} );
