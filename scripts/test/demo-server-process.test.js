/**
 * Tests for scripts/demo/lib/server-process.mjs (R2-01, review F1). No network. Spawns real
 * child processes via scripts/test/fixtures/spawn-tree.mjs to prove `stopServer()` actually
 * reaches a grandchild spawned through `sh -c` -- the exact shape of the real
 * `npx -> sh -> @wp-playground/cli -> node --experimental-wasm-jspi` tree that a bare
 * `proc.kill()` (the pre-R2-01 approach) leaves orphaned.
 */

const path = require( 'path' );

let startServer;
let stopServer;

const FIXTURE = path.join( __dirname, 'fixtures', 'spawn-tree.mjs' );
const PID_TIMEOUT_MS = 5000;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'server-process.mjs' )
	);
	startServer = mod.startServer;
	stopServer = mod.stopServer;
} );

/**
 * @param {import('node:child_process').ChildProcess} proc Process whose stdout prints
 *                                                         `GRANDCHILD_PID=<pid>`.
 * @return {Promise<number>} The grandchild's pid.
 */
function capturePid( proc ) {
	return new Promise( ( resolve, reject ) => {
		let output = '';
		const timeout = setTimeout( () => {
			reject(
				new Error(
					`fixture did not print GRANDCHILD_PID within ${ PID_TIMEOUT_MS }ms: ${ output }`
				)
			);
		}, PID_TIMEOUT_MS );

		const onData = ( chunk ) => {
			output += chunk.toString();
			const match = output.match( /GRANDCHILD_PID=(\d+)/ );
			if ( match ) {
				clearTimeout( timeout );
				proc.stdout.off( 'data', onData );
				resolve( Number( match[ 1 ] ) );
			}
		};

		proc.stdout.on( 'data', onData );
	} );
}

/**
 * @param {number} pid Process id.
 * @return {boolean} Whether the process is still alive.
 */
function isAlive( pid ) {
	try {
		process.kill( pid, 0 );
		return true;
	} catch {
		return false;
	}
}

describe( 'server-process', () => {
	it( 'stopServer kills a grandchild spawned through sh -c', async () => {
		const proc = startServer( process.execPath, [ FIXTURE ] );
		const grandchildPid = await capturePid( proc );

		expect( isAlive( grandchildPid ) ).toBe( true );

		await stopServer( proc );

		expect( isAlive( grandchildPid ) ).toBe( false );
	}, 15000 );

	it( 'stopServer resolves after SIGKILL when the group ignores SIGTERM', async () => {
		const proc = startServer( process.execPath, [ FIXTURE, '--trap' ] );
		const grandchildPid = await capturePid( proc );

		expect( isAlive( grandchildPid ) ).toBe( true );

		const graceMs = 300;
		const start = Date.now();
		await stopServer( proc, { graceMs } );
		const elapsed = Date.now() - start;

		// Must have actually waited out the grace period (proves SIGTERM alone didn't work)
		// but resolved well within graceMs plus a generous margin for the SIGKILL + poll.
		expect( elapsed ).toBeGreaterThanOrEqual( graceMs );
		expect( elapsed ).toBeLessThan( graceMs + 5000 );
		expect( isAlive( grandchildPid ) ).toBe( false );
	}, 15000 );
} );
