/**
 * Detached-process-group spawn/stop helpers for `demo:check`'s Playground server (R2-01, review
 * F1).
 *
 * `demo:check` used to `spawn( 'npx', [ '@wp-playground/cli', 'server', … ] )` and stop it with a
 * bare `playgroundProc.kill()`. That builds a process tree `npm exec -> sh -> node
 * wp-playground.js -> node --experimental-wasm-jspi` (the CLI's own `cli.js` respawns itself
 * under that flag on modern Node — see `node_modules/@wp-playground/cli/cli.js`), and
 * `kill()` signals only the immediate `npm exec` child. Every process below it is reparented to
 * init and keeps running — confirmed directly: the reviewer's run left the real CLI server and
 * its worker alive, holding 1.7 GB RSS, 71% CPU, and the port.
 *
 * The fix here has two parts:
 *  - `resolvePlaygroundCliBin()` resolves `@wp-playground/cli`'s real bin script from its own
 *    `package.json`, so `startServer()` can run it directly via `process.execPath`, with no
 *    `npx`/`sh` layer in between at all.
 *  - `startServer()` spawns `detached: true`, which on POSIX makes the spawned process the
 *    leader of its own new process group (its pgid equals its pid). `stopServer()` then signals
 *    the *group* (`process.kill( -pid, signal )`), which reaches every process spawned under it
 *    — including `cli.js`'s own `--experimental-wasm-jspi` respawn — in one call, regardless of
 *    how many layers of process actually exist.
 */

import { spawn } from 'node:child_process';
import { closeSync, openSync, readFileSync } from 'node:fs';
import { constants } from 'node:os';
import { join } from 'node:path';

const DEFAULT_GRACE_MS = 5000;
const POLL_MS = 25;
const STOP_SIGNALS = [ 'SIGINT', 'SIGTERM' ];
const SIGNAL_EXIT_BASE = 128;

/**
 * Resolve `@wp-playground/cli`'s bin script (its package.json `bin` entry) to an absolute path,
 * so it can be run directly with `process.execPath` instead of through `npx`/`sh` (see module
 * docblock). Deliberately avoids both `node:module`'s `createRequire()` and `import.meta` (of
 * any form, including `import.meta.resolve()`): either one, present anywhere in a `.mjs` module
 * that Jest reaches via a dynamic `import()` from a CommonJS test, makes Jest's own transform of
 * that dynamic `import()` throw "Must use import to load ES Module" even though Node itself
 * loads the file fine (confirmed directly; the same class of issue `scripts/live/hash-body.mjs`
 * and `scripts/live/series-args.mjs` already route around for `import.meta.url`). Every npm
 * script in this repo runs from the repo root, so `node_modules` is resolved from `process.cwd()`
 * the same way every other script here already resolves repo-relative paths (e.g.
 * `scripts/demo/check.mjs`'s `dist/ttm-core.zip`).
 *
 * @return {string} Absolute path to the CLI's bin script.
 */
export function resolvePlaygroundCliBin() {
	const pkgDir = join(
		process.cwd(),
		'node_modules',
		'@wp-playground',
		'cli'
	);
	const pkg = JSON.parse(
		readFileSync( join( pkgDir, 'package.json' ), 'utf8' )
	);
	const binEntry =
		'string' === typeof pkg.bin ? pkg.bin : pkg.bin[ 'wp-playground-cli' ];
	return join( pkgDir, binEntry );
}

/**
 * Start `command args…` detached, leading its own process group.
 *
 * @param {string}   command           Executable.
 * @param {string[]} args              Arguments.
 * @param {Object}   [options]
 * @param {string}   [options.logFile] When given, stdout/stderr are opened as real file
 *                                     descriptors on this path (append mode) and handed to the child directly, instead of piped
 *                                     back to this process. Used by `--keep`: a pipe whose read end lives in this process would
 *                                     start returning EPIPE to the child's writes the moment this process exits (or would keep
 *                                     this process's event loop alive if left open — the same class of hang R1-04's
 *                                     `process.exit( 0 )` backstop papered over) since nothing is left to read it; a real file has
 *                                     no such reader to lose.
 * @return {import('node:child_process').ChildProcess} The spawned process (detached, own group).
 */
export function startServer( command, args, { logFile } = {} ) {
	if ( ! logFile ) {
		return spawn( command, args, {
			detached: true,
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		} );
	}

	const fd = openSync( logFile, 'a' );
	const proc = spawn( command, args, {
		detached: true,
		stdio: [ 'ignore', fd, fd ],
	} );
	closeSync( fd );

	return proc;
}

/**
 * @param {number} pgid   Process group id (the group leader's pid).
 * @param {string} signal Signal name.
 */
function sendToGroup( pgid, signal ) {
	try {
		process.kill( -pgid, signal );
	} catch ( error ) {
		if ( 'ESRCH' !== error.code ) {
			throw error;
		}
	}
}

/**
 * @param {number} pgid Process group id.
 * @return {boolean} True while any process in the group is still alive.
 */
function groupAlive( pgid ) {
	try {
		process.kill( -pgid, 0 );
		return true;
	} catch {
		return false;
	}
}

/**
 * @param {number} ms Milliseconds.
 * @return {Promise<void>}
 */
function delay( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

/**
 * @param {number} pgid      Process group id.
 * @param {number} timeoutMs Bound.
 * @return {Promise<void>} Resolves once the group has no live members; rejects if it outlives
 *   `timeoutMs` after a SIGKILL has already been sent.
 */
async function waitForGroupGone( pgid, timeoutMs ) {
	const deadline = Date.now() + timeoutMs;
	while ( groupAlive( pgid ) ) {
		if ( Date.now() > deadline ) {
			throw new Error(
				`Process group ${ pgid } did not exit within ${ timeoutMs }ms of SIGKILL`
			);
		}
		await delay( POLL_MS );
	}
}

/**
 * Stop the process group led by `proc` (started by `startServer()`): SIGTERM the whole group,
 * wait up to `graceMs` for it to go away on its own, then SIGKILL the whole group and wait for
 * it to actually be gone. Resolves only once no process remains in the group.
 *
 * @param {import('node:child_process').ChildProcess} proc              Process started by `startServer()`.
 * @param {Object}                                    [options]
 * @param {number}                                    [options.graceMs] Grace period before escalating to SIGKILL, and the bound on
 *                                                                      the final wait after SIGKILL.
 * @return {Promise<void>}
 */
export async function stopServer( proc, { graceMs = DEFAULT_GRACE_MS } = {} ) {
	if ( ! proc || ! proc.pid ) {
		return;
	}

	const pgid = proc.pid;

	const exited = new Promise( ( resolve ) => {
		if ( null !== proc.exitCode ) {
			resolve();
			return;
		}
		proc.once( 'exit', () => resolve() );
	} );

	sendToGroup( pgid, 'SIGTERM' );
	await Promise.race( [ exited, delay( graceMs ) ] );

	if ( groupAlive( pgid ) ) {
		sendToGroup( pgid, 'SIGKILL' );
	}

	await waitForGroupGone( pgid, graceMs );
}

/**
 * Run `body( proc )` against a server started by `start()`, and make sure the server's process
 * group is stopped however `body` ends: on return, on throw, and on SIGINT/SIGTERM to this
 * process. The group is detached (see `startServer()`), so a Ctrl-C in the terminal never
 * reaches it on its own; without the signal handlers here, interrupting `demo:check` left the
 * Playground server running under init.
 *
 * With `keep`, a server whose `body` returned normally is left running (and `unref()`d so it no
 * longer holds this process open); an interrupt or a throw before that still stops it.
 *
 * @param {() => import('node:child_process').ChildProcess}                 start             Starts the server (normally via `startServer()`).
 * @param {(proc: import('node:child_process').ChildProcess) => Promise<*>} body              Work to do while the server runs.
 * @param {Object}                                                          [options]
 * @param {boolean}                                                         [options.keep]    Leave the server running if `body` returns.
 * @param {number}                                                          [options.graceMs] Passed to `stopServer()`.
 * @param {import('node:events').EventEmitter}                              [options.emitter] Where signals arrive (tests pass their own).
 * @param {(code: number) => void}                                          [options.exit]    Called with 128 + signal number after an interrupt.
 * @return {Promise<*>} Whatever `body` resolves to.
 */
export async function runWithServer(
	start,
	body,
	{
		keep = false,
		graceMs = DEFAULT_GRACE_MS,
		emitter = process,
		exit = ( code ) => process.exit( code ),
	} = {}
) {
	const proc = start();
	const handlers = new Map();
	const removeHandlers = () => {
		for ( const [ signal, handler ] of handlers ) {
			emitter.off( signal, handler );
		}
		handlers.clear();
	};

	for ( const signal of STOP_SIGNALS ) {
		const handler = async () => {
			removeHandlers();
			try {
				await stopServer( proc, { graceMs } );
			} finally {
				exit( SIGNAL_EXIT_BASE + constants.signals[ signal ] );
			}
		};
		handlers.set( signal, handler );
		emitter.on( signal, handler );
	}

	let kept = false;
	try {
		const result = await body( proc );
		if ( keep ) {
			proc.unref();
			kept = true;
		}
		return result;
	} finally {
		removeHandlers();
		if ( ! kept ) {
			await stopServer( proc, { graceMs } );
		}
	}
}
