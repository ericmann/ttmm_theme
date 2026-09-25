/**
 * Tests for the P2-07 CI workflow (.github/workflows/ci.yml). No YAML parser dependency: plain
 * text checks over the file, matching this project's "no new dependency" convention.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const WORKFLOW_PATH = path.join(
	__dirname,
	'..',
	'..',
	'.github',
	'workflows',
	'ci.yml'
);

const WORKFLOW_FILES = fs
	.readdirSync( path.join( __dirname, '..', '..', '.github', 'workflows' ) )
	.filter( ( name ) => name.endsWith( '.yml' ) || name.endsWith( '.yaml' ) );

describe( 'ci.yml', () => {
	const workflow = fs.readFileSync( WORKFLOW_PATH, 'utf8' );

	it( 'integration job runs demo:build with --check-determinism and demo:check after env:drill', () => {
		const integrationJob = workflow.slice(
			workflow.indexOf( '\n  integration:' ),
			workflow.indexOf( '\n  e2e:' )
		);

		const drillIndex = integrationJob.indexOf( 'npm run env:drill' );
		const buildIndex = integrationJob.indexOf(
			'npm run demo:build -- --out dist/demo --check-determinism'
		);
		const checkIndex = integrationJob.indexOf(
			'npm run demo:check -- --from dist/demo'
		);
		const logsIndex = integrationJob.indexOf( 'Container logs on failure' );

		expect( drillIndex ).toBeGreaterThan( -1 );
		expect( buildIndex ).toBeGreaterThan( -1 );
		expect( checkIndex ).toBeGreaterThan( -1 );
		expect( logsIndex ).toBeGreaterThan( -1 );

		expect( buildIndex ).toBeGreaterThan( drillIndex );
		expect( checkIndex ).toBeGreaterThan( buildIndex );
		// "Container logs on failure" stays the last step.
		expect( logsIndex ).toBeGreaterThan( checkIndex );
	} );

	it( 'no workflow runs demo:fetch-images', () => {
		for ( const file of WORKFLOW_FILES ) {
			const contents = fs.readFileSync(
				path.join(
					__dirname,
					'..',
					'..',
					'.github',
					'workflows',
					file
				),
				'utf8'
			);
			expect( contents ).not.toMatch( /demo:fetch-images/ );
		}
	} );
} );
