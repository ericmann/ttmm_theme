/**
 * Tests for scripts/check-private-data.sh (R1-07, SPEC rule 47): a tracked private-data file
 * directly under docs/ (not just nested) must fail the check.
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const SCRIPT_PATH = path.join( __dirname, '..', 'check-private-data.sh' );

/**
 * A throwaway git repo in a fresh temp directory, with `git` user/email set so `git commit`
 * (unused here -- only `git add`/`git ls-files`) never needs a real identity.
 *
 * @return {string} The repo's absolute path.
 */
function makeRepo() {
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'ttm-private-data-' ) );
	execFileSync( 'git', [ 'init', '--quiet' ], { cwd: dir } );
	execFileSync( 'git', [ 'config', 'user.email', 'test@example.com' ], {
		cwd: dir,
	} );
	execFileSync( 'git', [ 'config', 'user.name', 'Test' ], { cwd: dir } );

	return dir;
}

/**
 * Writes `content` to `relativePath` inside `dir` (creating parent directories) and force-adds
 * it to the git index -- `git add -f`, since every extension this test cares about is exactly
 * what a real .gitignore would exclude, same as this repo's own.
 *
 * @param {string} dir          Repo root.
 * @param {string} relativePath Path relative to `dir`.
 */
function addFile( dir, relativePath ) {
	const fullPath = path.join( dir, relativePath );
	fs.mkdirSync( path.dirname( fullPath ), { recursive: true } );
	fs.writeFileSync( fullPath, 'placeholder' );
	execFileSync( 'git', [ 'add', '-f', relativePath ], { cwd: dir } );
}

/**
 * Runs check-private-data.sh against `dir`'s repo.
 *
 * @param {string} dir Repo root.
 * @return {{status: number, stdout: string}} Exit status and combined stdout.
 */
function run( dir ) {
	try {
		const stdout = execFileSync( 'bash', [ SCRIPT_PATH ], {
			cwd: dir,
			encoding: 'utf8',
		} );
		return { status: 0, stdout };
	} catch ( error ) {
		return { status: error.status, stdout: error.stdout };
	}
}

describe( 'check-private-data.sh', () => {
	it( 'exits 0 on a clean repo', () => {
		const dir = makeRepo();
		addFile( dir, 'docs/notes.md' );

		const { status, stdout } = run( dir );

		expect( status ).toBe( 0 );
		expect( stdout ).toContain( 'clean' );
	} );

	it.each( [
		[ 'top-level .sql', 'docs/dump.sql' ],
		[ 'top-level .csv', 'docs/x.csv' ],
		[ 'top-level .tar.gz', 'docs/b.tar.gz' ],
		[ 'top-level .sql.gz', 'docs/e.sql.gz' ],
		[ 'top-level .xml', 'docs/import.xml' ],
		[ 'nested .sql', 'docs/a/c.sql' ],
		[ 'nested fixtures/live file', 'docs/fixtures/live/s.json' ],
	] )( 'fails and names the file for %s (%s)', ( _label, relativePath ) => {
		const dir = makeRepo();
		addFile( dir, relativePath );

		const { status, stdout } = run( dir );

		expect( status ).not.toBe( 0 );
		expect( stdout ).toContain( relativePath );
	} );
} );
