/**
 * Tests for scripts/demo/lib/server-process.mjs (R2-01, review F1). No network. Spawns real
 * child processes via scripts/test/fixtures/spawn-tree.mjs to prove `stopServer()` actually
 * reaches a grandchild spawned through `sh -c` -- the exact shape of the real
 * `npx -> sh -> @wp-playground/cli -> node --experimental-wasm-jspi` tree that a bare
 * `proc.kill()` (the pre-R2-01 approach) leaves orphaned.
 */

const { EventEmitter } = require( 'events' );
const fs = require( 'fs' );
const path = require( 'path' );

let startServer;
let stopServer;
let runWithServer;

const FIXTURE = path.join( __dirname, 'fixtures', 'spawn-tree.mjs' );
const PID_TIMEOUT_MS = 5000;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'server-process.mjs' )
	);
	startServer = mod.startServer;
	stopServer = mod.stopServer;
	runWithServer = mod.runWithServer;
} );

// Every group and grandchild a test starts, so a failing assertion never leaves them running.
const started = [];

afterEach( () => {
	for ( const pid of started.splice( 0 ) ) {
		for ( const target of [ -pid, pid ] ) {
			try {
				process.kill( target, 'SIGKILL' );
			} catch {}
		}
	}
} );

/**
 * @param {string[]} [extra] Fixture arguments.
 * @return {import('node:child_process').ChildProcess} Fixture process, tracked for cleanup.
 */
function startFixture( extra = [] ) {
	const proc = startServer( process.execPath, [ FIXTURE, ...extra ] );
	started.push( proc.pid );
	return proc;
}

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
	} ).then( ( pid ) => {
		started.push( pid );
		return pid;
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
		const proc = startFixture();
		const grandchildPid = await capturePid( proc );

		expect( isAlive( grandchildPid ) ).toBe( true );

		await stopServer( proc );

		expect( isAlive( grandchildPid ) ).toBe( false );
	}, 15000 );

	it( 'stopServer resolves after SIGKILL when the group ignores SIGTERM', async () => {
		const proc = startFixture( [ '--trap' ] );
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

	it( 'runWithServer stops the group once the body resolves', async () => {
		let grandchildPid;
		const result = await runWithServer( startFixture, async ( proc ) => {
			grandchildPid = await capturePid( proc );
			return 'done';
		} );

		expect( result ).toBe( 'done' );
		expect( isAlive( grandchildPid ) ).toBe( false );
	}, 15000 );

	it( 'runWithServer stops the group when the body throws', async () => {
		let grandchildPid;
		await expect(
			runWithServer( startFixture, async ( proc ) => {
				grandchildPid = await capturePid( proc );
				throw new Error( 'boom' );
			} )
		).rejects.toThrow( 'boom' );

		expect( isAlive( grandchildPid ) ).toBe( false );
	}, 15000 );

	it( 'runWithServer with keep leaves the group running', async () => {
		let grandchildPid;
		await runWithServer(
			startFixture,
			async ( proc ) => {
				grandchildPid = await capturePid( proc );
			},
			{ keep: true }
		);

		expect( isAlive( grandchildPid ) ).toBe( true );
	}, 15000 );

	it( 'runWithServer stops the group and exits 130 on SIGINT', async () => {
		const emitter = new EventEmitter();
		let exitCode;
		const exited = new Promise( ( resolve ) => {
			runWithServer(
				startFixture,
				async ( proc ) => {
					const grandchildPid = await capturePid( proc );
					emitter.emit( 'SIGINT' );
					await new Promise( () => {} ); // An interrupt never lets the body finish.
					return grandchildPid;
				},
				{
					keep: true, // An interrupt stops the server even under --keep.
					emitter,
					exit: ( code ) => {
						exitCode = code;
						resolve();
					},
				}
			);
		} );

		await exited;

		expect( exitCode ).toBe( 130 );
		expect( started.slice( 1 ).every( ( pid ) => ! isAlive( pid ) ) ).toBe(
			true
		);
		expect( emitter.listenerCount( 'SIGINT' ) ).toBe( 0 );
		expect( emitter.listenerCount( 'SIGTERM' ) ).toBe( 0 );
	}, 15000 );

	it( 'runWithServer removes its signal handlers when it returns', async () => {
		const emitter = new EventEmitter();
		await runWithServer(
			startFixture,
			async ( proc ) => capturePid( proc ),
			{
				emitter,
			}
		);

		expect( emitter.listenerCount( 'SIGINT' ) ).toBe( 0 );
		expect( emitter.listenerCount( 'SIGTERM' ) ).toBe( 0 );
	}, 15000 );

	it( 'demo:check runs the Playground server through runWithServer', () => {
		const source = fs.readFileSync(
			path.join( __dirname, '..', 'demo', 'check.mjs' ),
			'utf8'
		);

		// The only startServer() call is the one runWithServer() owns, so the checks can't
		// finish, throw or be interrupted without stopping the group (the process.exit( 0 )
		// backstop in main() would otherwise hide a missing stop).
		expect( source.match( /^\s*startServer\(/gm ) ).toHaveLength( 1 );
		expect( source ).toMatch( /runWithServer\(\s*\(\) =>\s*startServer\(/ );
	} );
} );
