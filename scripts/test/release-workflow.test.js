/**
 * Tests for the P3-03 release workflow (.github/workflows/release.yml). No YAML parser
 * dependency: plain text checks over the file, matching ci-workflow.test.js's convention.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const WORKFLOW_PATH = path.join(
	__dirname,
	'..',
	'..',
	'.github',
	'workflows',
	'release.yml'
);

describe( 'release.yml', () => {
	const workflow = fs.readFileSync( WORKFLOW_PATH, 'utf8' );

	it( 'triggers on v* tags only', () => {
		expect( workflow ).toMatch(
			/on:\s*\n\s*push:\s*\n\s*tags:\s*\[\s*"v\*"\s*\]/
		);
		expect( workflow ).not.toMatch( /branches:/ );
		expect( workflow ).not.toMatch( /pull_request:/ );
	} );

	it( 'runs the verify set before release:pack', () => {
		const verifySteps = [
			'composer lint',
			'composer test:unit',
			'npm run lint',
			'npm run test:unit',
			'bash scripts/forbidden-patterns.sh',
		];

		const indices = verifySteps.map( ( step ) => workflow.indexOf( step ) );
		for ( const index of indices ) {
			expect( index ).toBeGreaterThan( -1 );
		}

		const packIndex = workflow.indexOf( 'npm run release:pack' );
		expect( packIndex ).toBeGreaterThan( -1 );
		for ( const index of indices ) {
			expect( packIndex ).toBeGreaterThan( index );
		}
	} );

	it( 'checks the tag against package.json', () => {
		expect( workflow ).toMatch(
			/require\(\s*'\.\/package\.json'\s*\)\.version/
		);
		expect( workflow ).toMatch( /GITHUB_REF_NAME/ );
		expect( workflow ).toMatch( /exit 1/ );

		const versionCheckIndex = workflow.indexOf( 'GITHUB_REF_NAME' );
		const packIndex = workflow.indexOf( 'npm run release:pack' );
		expect( packIndex ).toBeGreaterThan( versionCheckIndex );
	} );

	it( 'attaches both zips, not a draft, with generated notes', () => {
		expect( workflow ).toMatch( /dist\/ttm-core\.zip/ );
		expect( workflow ).toMatch( /dist\/ttm-theme\.zip/ );
		expect( workflow ).toMatch( /draft:\s*false/ );
		expect( workflow ).toMatch( /generate_release_notes:\s*true/ );
		expect( workflow ).toMatch( /softprops\/action-gh-release@v2/ );
	} );

	it( 'grants contents: write', () => {
		expect( workflow ).toMatch( /permissions:\s*\n\s*contents:\s*write/ );
	} );
} );
