/**
 * Tests for scripts/live/plan.sh's targets: the default wp-env path and the TTM_KUBECTL_TARGET
 * (k3s) path. plan.sh runs in a throwaway directory against stub `wp`, `kubectl`, converter and
 * screens scripts that only log what they were asked to do -- no WordPress, Docker or cluster.
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const REPO = path.join( __dirname, '..', '..' );
const PLAN = path.join( REPO, 'scripts', 'live', 'plan.sh' );

const scratchDirs = [];

afterAll( () => {
	for ( const dir of scratchDirs ) {
		fs.rmSync( dir, { recursive: true, force: true } );
	}
} );

const CLASSIC_LINE =
	'{"id":1,"slug":"old-post","content_raw":"<p>Old</p>","footnotes_meta":null}';

/**
 * A scratch working directory with everything plan.sh reads relative to its cwd.
 *
 * @param {{ importXml?: boolean }} options Whether to create docs/fixtures/live/import.xml.
 * @return {{ dir: string, log: string, wp: string, kubectl: string }} Paths.
 */
function scratch( { importXml = false } = {} ) {
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'ttm-plan-' ) );
	scratchDirs.push( dir );
	const log = path.join( dir, 'calls.log' );

	fs.mkdirSync( path.join( dir, 'scripts', 'live' ), { recursive: true } );
	fs.mkdirSync( path.join( dir, 'docs', 'migration' ), { recursive: true } );
	fs.mkdirSync( path.join( dir, 'docs', 'fixtures', 'live' ), {
		recursive: true,
	} );
	fs.mkdirSync( path.join( dir, 'bin' ) );

	fs.copyFileSync(
		path.join( REPO, 'scripts', 'live', 'series-args.mjs' ),
		path.join( dir, 'scripts', 'live', 'series-args.mjs' )
	);
	fs.writeFileSync(
		path.join( dir, 'docs', 'migration', 'series.json' ),
		JSON.stringify( [
			{
				slug: 'cryptopals',
				name: 'Cryptopals',
				tags: [ 'cryptopals' ],
				form: 'nonfiction',
				status: 'complete',
			},
		] )
	);
	if ( importXml ) {
		fs.writeFileSync(
			path.join( dir, 'docs', 'fixtures', 'live', 'import.xml' ),
			'<rss/>'
		);
		fs.writeFileSync(
			path.join( dir, 'docs', 'fixtures', 'live', 'term-map.json' ),
			'{}'
		);
		// Under wp-env the export lands here through the mount; the stub `wp` can't write it.
		fs.writeFileSync(
			path.join( dir, 'docs', 'fixtures', 'live', 'classic.ndjson' ),
			CLASSIC_LINE + '\n'
		);
	}

	// Converter stub: logs its input and writes one converted record.
	fs.writeFileSync(
		path.join( dir, 'scripts', 'convert-classic.mjs' ),
		`import fs from 'node:fs';
const [ input, output ] = process.argv.slice( 2 );
fs.appendFileSync( ${ JSON.stringify(
			log
		) }, 'CONVERT ' + fs.readFileSync( input, 'utf8' ).trim() + '\\n' );
fs.writeFileSync( output, '{"id":1,"blocks":"<!-- wp:paragraph -->"}\\n' );
`
	);
	fs.writeFileSync(
		path.join( dir, 'scripts', 'live', 'screens.mjs' ),
		`import fs from 'node:fs';
fs.appendFileSync( ${ JSON.stringify( log ) }, 'SCREENS\\n' );
`
	);

	const wp = path.join( dir, 'bin', 'wp' );
	fs.writeFileSync(
		wp,
		`#!/usr/bin/env bash
echo "WP $*" >>${ JSON.stringify( log ) }
if [ "$1 $2" = "ttm audit" ] || [ "$2 $3" = "ttm audit" ]; then echo "id,flag"; fi
exit 0
`,
		{ mode: 0o755 }
	);

	const kubectl = path.join( dir, 'bin', 'kubectl' );
	fs.writeFileSync(
		kubectl,
		`#!/usr/bin/env bash
echo "KUBECTL $*" >>${ JSON.stringify( log ) }
args=("$@")
last=$(( \${#args[@]} - 1 ))
if [ "\${args[$((last - 1))]}" = "cat" ]; then echo ${ JSON.stringify(
			CLASSIC_LINE
		) }; fi
if printf '%s\\n' "$@" | grep -q 'cat >'; then echo "STDIN $(cat)" >>${ JSON.stringify(
			log
		) }; fi
exit 0
`,
		{ mode: 0o755 }
	);

	return { dir, log, wp, kubectl };
}

/**
 * @param {string}                 dir Working directory.
 * @param {Record<string, string>} env Extra environment.
 * @return {import('child_process').SpawnSyncReturns<string>} Result.
 */
function runPlan( dir, env ) {
	return spawnSync( 'bash', [ PLAN ], {
		cwd: dir,
		encoding: 'utf8',
		env: { ...process.env, ...env },
	} );
}

function calls( log ) {
	return fs.existsSync( log )
		? fs.readFileSync( log, 'utf8' ).trim().split( '\n' )
		: [];
}

describe( 'plan.sh', () => {
	it( 'skips without an export or a kubectl target', () => {
		const { dir, log } = scratch();

		const result = runPlan( dir, {} );

		expect( result.status ).toBe( 0 );
		expect( result.stdout ).toContain( 'env:live skipped: no export' );
		expect( calls( log ) ).toEqual( [] );
	} );

	it( 'wp-env path: keeps --term-map, never calls kubectl, runs screens.mjs', () => {
		const { dir, log, wp } = scratch( { importXml: true } );

		const result = runPlan( dir, { TTM_WP: wp } );

		expect( result.status ).toBe( 0 );
		const lines = calls( log );
		expect( lines ).toContain(
			'WP ttm primary:assign --from-yoast --term-map=wp-content/ttm-fixtures/live/term-map.json'
		);
		expect( lines.some( ( l ) => l.startsWith( 'KUBECTL' ) ) ).toBe(
			false
		);
		expect( lines ).toContain( 'SCREENS' );
	} );

	it( 'kubectl target: no import.xml needed, no term map, files moved through the pod', () => {
		const { dir, log, wp, kubectl } = scratch();

		const result = runPlan( dir, {
			TTM_WP: `${ wp } --allow-root`,
			TTM_KUBECTL: kubectl,
			TTM_KUBECTL_TARGET: 'blog/deploy/wordpress',
		} );

		expect( result.stderr ).toBe( '' );
		expect( result.status ).toBe( 0 );
		const lines = calls( log );

		// TTM_WP is word-split: the extra flag reaches the stub before the command.
		expect( lines ).toContain(
			'WP --allow-root ttm primary:assign --from-yoast --dry-run'
		);
		expect( lines ).toContain(
			'WP --allow-root ttm primary:assign --from-yoast'
		);
		expect( lines.some( ( l ) => l.includes( '--term-map' ) ) ).toBe(
			false
		);

		// The export is pulled from the pod, converted locally, and the result pushed back.
		// The pod's fixture dir is created as root, then handed to wp-content's owner so a
		// WP-CLI running as the web user can write the export into it.
		const mkdirLines = lines.filter(
			( l ) =>
				l.startsWith(
					'KUBECTL -n blog exec -i deploy/wordpress -- sh -c mkdir -p'
				) &&
				l.includes( 'chown -R' ) &&
				l.endsWith(
					' sh /var/www/html/wp-content/ttm-fixtures/live /var/www/html/wp-content'
				)
		);
		expect( mkdirLines.length ).toBeGreaterThan( 0 );
		expect( lines ).toContain(
			'KUBECTL -n blog exec -i deploy/wordpress -- cat /var/www/html/wp-content/ttm-fixtures/live/classic.ndjson'
		);
		expect( lines ).toContain( `CONVERT ${ CLASSIC_LINE }` );
		expect( lines ).toContain(
			'STDIN {"id":1,"blocks":"<!-- wp:paragraph -->"}'
		);
		const exportIndex = lines.findIndex( ( l ) =>
			l.includes( 'ttm convert:export --all-classic' )
		);
		const pullIndex = lines.findIndex( ( l ) =>
			l.includes( '-- cat /var/www/html' )
		);
		const importIndex = lines.findIndex(
			( l ) =>
				l.includes( 'ttm convert:import' ) &&
				! l.includes( '--dry-run' )
		);
		const pushIndex = lines.findIndex( ( l ) => l.startsWith( 'STDIN' ) );
		expect( exportIndex ).toBeLessThan( pullIndex );
		expect( pushIndex ).toBeLessThan( importIndex );

		expect( lines ).not.toContain( 'SCREENS' );
		expect( result.stdout ).toContain(
			'plan.sh: screens.mjs skipped (wp-env only)'
		);
		expect(
			fs.readFileSync(
				path.join( dir, 'docs', 'fixtures', 'live', 'audit.csv' ),
				'utf8'
			)
		).toBe( 'id,flag\n' );
	} );

	it( 'kubectl target: TTM_KUBECTL_CONTAINER and TTM_SKIP_TERM_MAP=0 are honoured', () => {
		const { dir, log, wp, kubectl } = scratch();
		fs.writeFileSync(
			path.join( dir, 'docs', 'fixtures', 'live', 'term-map.json' ),
			'{"1":"technology"}'
		);

		const result = runPlan( dir, {
			TTM_WP: wp,
			TTM_KUBECTL: kubectl,
			TTM_KUBECTL_TARGET: 'blog/wordpress-0',
			TTM_KUBECTL_CONTAINER: 'wordpress',
			TTM_SKIP_TERM_MAP: '0',
		} );

		expect( result.status ).toBe( 0 );
		const lines = calls( log );
		expect( lines ).toContain( 'STDIN {"1":"technology"}' );
		expect( lines ).toContain(
			'WP ttm primary:assign --from-yoast --term-map=wp-content/ttm-fixtures/live/term-map.json'
		);
		expect(
			lines.some( ( l ) =>
				l.startsWith(
					'KUBECTL -n blog exec -i wordpress-0 -c wordpress -- '
				)
			)
		).toBe( true );
	} );

	it( 'rejects a kubectl target without a namespace', () => {
		const { dir, log, wp, kubectl } = scratch();

		const result = runPlan( dir, {
			TTM_WP: wp,
			TTM_KUBECTL: kubectl,
			TTM_KUBECTL_TARGET: 'wordpress',
		} );

		expect( result.status ).toBe( 1 );
		expect( result.stderr ).toContain( 'TTM_KUBECTL_TARGET must be' );
		expect( calls( log ) ).toEqual( [] );
	} );
} );
