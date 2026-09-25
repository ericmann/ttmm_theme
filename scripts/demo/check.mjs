#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run demo:check [-- --from <dir>] [--url <site>] [--keep]` (SPEC §3.2 rule 57, §6.4,
 * P2-06, R1-01). Boots the blueprint headless against zips and WXR built from the current tree
 * and asserts the §6.4 pages. `--url` skips Playground entirely and asserts against a running
 * site (e.g. the seeded wp-env). Network only to the Playground CDN (for WordPress itself) and to
 * this script's own local static server (rule 54).
 *
 * R1-01: the previous version resolved boot readiness by polling the front page's HTML for a
 * marker class, which raced the blueprint's own last step (the post-import `wp eval` rebuild +
 * `demo:verify`): the CLI's worker pool starts answering requests before that step finishes, so
 * a page fetched too early can return 200 while still serving pre-import, empty-looking content.
 * `waitForBoot()` now waits for the CLI process itself to print its own "WordPress is running on"
 * ready line (`scripts/demo/lib/boot.mjs`'s `isReady()`) -- which the CLI only emits after every
 * blueprint step has completed -- and only then makes one sanity-check GET of `/`. This removes
 * the race entirely; no `--workers` tuning or object-cache workaround is needed.
 */
import { execFileSync } from 'node:child_process';
import {
	copyFileSync,
	existsSync,
	mkdtempSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { createServer } from 'node:http';
import { createServer as createNetServer } from 'node:net';
import { tmpdir } from 'node:os';
import { extname, join } from 'node:path';
import { isReady } from './lib/boot.mjs';
import { checkPages } from './lib/check-assertions.mjs';
import { localBlueprint } from './lib/local-variant.mjs';
import {
	resolvePlaygroundCliBin,
	startServer,
	stopServer,
} from './lib/server-process.mjs';
import { rebaseAttachmentUrls } from './lib/wxr.mjs';

const PLAYGROUND_BOOT_TIMEOUT_MS = 600000;

const IMAGE_RAW_BASE =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/docs/fixtures/demo/images';
const IMAGES_DIR = join( 'docs', 'fixtures', 'demo', 'images' );

const CONTENT_TYPES = {
	'.html': 'text/html',
	'.json': 'application/json',
	'.xml': 'application/xml',
	'.zip': 'application/zip',
	'.jpg': 'image/jpeg',
	'.jpeg': 'image/jpeg',
	'.png': 'image/png',
};

/**
 * @param {string[]} args  Raw CLI args.
 * @param {string}   name  Flag name (without `--`).
 * @param {string}   [def] Default.
 * @return {string|undefined} Value.
 */
function argValue( args, name, def ) {
	const index = args.indexOf( `--${ name }` );
	if ( -1 === index ) {
		return def;
	}
	return args[ index + 1 ];
}

/**
 * An OS-assigned free TCP port.
 *
 * @return {Promise<number>} Port number.
 */
function freePort() {
	return new Promise( ( resolve, reject ) => {
		const server = createNetServer();
		server.on( 'error', reject );
		server.listen( 0, '127.0.0.1', () => {
			const { port } = server.address();
			server.close( () => resolve( port ) );
		} );
	} );
}

/**
 * A static file server rooted at two directories: `dir` at `/`, and `docs/fixtures/demo/images/`
 * at `/images/`.
 *
 * @param {string} dir Primary directory (temp dir with the zips/WXR/blueprint).
 * @return {Promise<{server: import('node:http').Server, port: number}>} The listening server.
 */
function startStaticServer( dir ) {
	const server = createServer( ( req, res ) => {
		const url = req.url.split( '?' )[ 0 ];
		const relative = url.startsWith( '/images/' )
			? join( IMAGES_DIR, url.slice( '/images/'.length ) )
			: join( dir, url.slice( 1 ) );

		if ( ! existsSync( relative ) ) {
			res.writeHead( 404 );
			res.end( 'not found' );
			return;
		}

		const type =
			CONTENT_TYPES[ extname( relative ) ] || 'application/octet-stream';
		res.writeHead( 200, { 'Content-Type': type } );
		res.end( readFileSync( relative ) );
	} );

	return new Promise( ( resolve ) => {
		server.listen( 0, '127.0.0.1', () => {
			resolve( { server, port: server.address().port } );
		} );
	} );
}

/**
 * A minimal cookie jar: `header()` for the `Cookie` request header, `store()` to record a
 * response's `Set-Cookie`s. Playground's `--login` auto-login redirects `/` to itself with a
 * `Set-Cookie`; Node's global `fetch()` doesn't persist cookies across its own automatic
 * redirect-following the way a browser (or `curl -L -c/-b`) does, so a bare `fetch(url)` against
 * a freshly-booted, not-yet-logged-in Playground site throws "redirect count exceeded" --
 * confirmed directly against a real boot. `fetchWithCookies()` below follows redirects manually
 * instead, carrying cookies itself.
 *
 * @return {{header: () => string, store: (response: Response) => void}} The jar.
 */
function createCookieJar() {
	const jar = new Map();
	return {
		header() {
			return [ ...jar.entries() ]
				.map( ( [ key, value ] ) => `${ key }=${ value }` )
				.join( '; ' );
		},
		store( response ) {
			const setCookies = response.headers.getSetCookie
				? response.headers.getSetCookie()
				: [];
			for ( const raw of setCookies ) {
				const pair = raw.split( ';' )[ 0 ];
				const eq = pair.indexOf( '=' );
				if ( eq > -1 ) {
					jar.set(
						pair.slice( 0, eq ).trim(),
						pair.slice( eq + 1 ).trim()
					);
				}
			}
		},
	};
}

const MAX_REDIRECTS = 10;

// A single stuck request (e.g. the CLI's own documented worker/file-lock deadlock risk under a
// small `--workers` count) must not hang this whole script forever: Node's `fetch()` has no
// default timeout of its own, and a request that never resolves would block every later retry in
// `waitForBoot()`'s poll loop -- confirmed directly (a run outlived even the 600000ms
// `PLAYGROUND_BOOT_TIMEOUT_MS` with no timeout error ever printed, because the poll loop's own
// `setTimeout` between attempts never got a turn while the first `await fetch()` was still
// pending).
const FETCH_TIMEOUT_MS = 15000;

/**
 * Fetch a URL, following redirects manually (carrying `jar`'s cookies on every hop) rather than
 * relying on `fetch()`'s own automatic redirect-following -- see `createCookieJar()`'s note. Each
 * hop is bounded by `FETCH_TIMEOUT_MS` so one stuck request can't hang the caller forever.
 *
 * @param {string}                      url URL to fetch.
 * @param {ReturnType<createCookieJar>} jar Cookie jar shared across a whole check run.
 * @return {Promise<Response>} The final, non-redirect response.
 */
async function fetchWithCookies( url, jar ) {
	let currentUrl = url;
	for ( let i = 0; i <= MAX_REDIRECTS; i++ ) {
		const cookieHeader = jar.header();
		const response = await fetch( currentUrl, {
			redirect: 'manual',
			headers: cookieHeader ? { Cookie: cookieHeader } : {},
			signal: AbortSignal.timeout( FETCH_TIMEOUT_MS ),
		} );
		jar.store( response );

		const location = response.headers.get( 'location' );
		if (
			[ 301, 302, 303, 307, 308 ].includes( response.status ) &&
			location
		) {
			currentUrl = new URL( location, currentUrl ).toString();
			continue;
		}
		return response;
	}
	throw new Error( `Too many redirects fetching ${ url }` );
}

const LOG_POLL_MS = 200;

/**
 * Wait for the spawned Playground CLI process to print its own ready line (`isReady()`, R1-01)
 * -- which only happens after every blueprint step, including the final post-import rebuild, has
 * completed -- then make one sanity-check GET of `url` to confirm the site actually answers.
 * Rejects if the process exits first or the ready line never appears within
 * `PLAYGROUND_BOOT_TIMEOUT_MS`.
 *
 * Non-`--keep` runs read the ready line off `proc`'s piped stdout/stderr, same as before R2-01.
 * `--keep` runs (`logFile` given) route stdout/stderr straight to a file, never through a pipe
 * held by this process (`server-process.mjs`'s `startServer()`), so readiness is instead read by
 * polling that file's contents for the same marker -- the ready-line semantics (`isReady()`)
 * don't change either way.
 *
 * @param {string}                                    url               URL to sanity-check once
 *                                                                      ready.
 * @param {import('node:child_process').ChildProcess} proc              Playground server process.
 * @param {ReturnType<createCookieJar>}               jar               Shared cookie jar.
 * @param {Object}                                    [options]
 * @param {string}                                    [options.logFile] Log file to poll instead
 *                                                                      of piped stdout/stderr (`--keep`).
 * @return {Promise<void>} Resolves once the ready line has appeared and the sanity GET succeeds;
 *   rejects on timeout, process exit, or a non-200 sanity GET.
 */
function waitForBoot( url, proc, jar, { logFile } = {} ) {
	return new Promise( ( resolve, reject ) => {
		let output = '';
		let settled = false;
		let onData;
		let pollHandle;

		const cleanup = () => {
			if ( onData ) {
				proc.stdout.off( 'data', onData );
				proc.stderr.off( 'data', onData );
			}
			clearTimeout( pollHandle );
		};

		const finish = ( fn, arg ) => {
			if ( settled ) {
				return;
			}
			settled = true;
			clearTimeout( timeoutHandle );
			cleanup();
			fn( arg );
		};

		const sanityCheck = () => {
			fetchWithCookies( url, jar )
				.then( ( response ) => {
					if ( 200 !== response.status ) {
						finish(
							reject,
							new Error(
								`Playground server answered ${ response.status } for ${ url } after reporting ready`
							)
						);
						return;
					}
					finish( resolve );
				} )
				.catch( ( error ) => finish( reject, error ) );
		};

		if ( logFile ) {
			const poll = () => {
				try {
					output = readFileSync( logFile, 'utf8' );
				} catch {
					// Not written yet.
				}
				if ( isReady( output ) ) {
					sanityCheck();
					return;
				}
				pollHandle = setTimeout( poll, LOG_POLL_MS );
			};
			poll();
		} else {
			onData = ( chunk ) => {
				output += chunk.toString();
				if ( isReady( output ) ) {
					cleanup();
					sanityCheck();
				}
			};
			proc.stdout.on( 'data', onData );
			proc.stderr.on( 'data', onData );
		}

		proc.on( 'exit', ( code ) => {
			finish(
				reject,
				new Error(
					`Playground server exited (code ${ code }) before booting:\n${ output }`
				)
			);
		} );

		const timeoutHandle = setTimeout( () => {
			finish(
				reject,
				new Error(
					`Playground server did not print the ready line within ${ PLAYGROUND_BOOT_TIMEOUT_MS }ms:\n${ output }`
				)
			);
		}, PLAYGROUND_BOOT_TIMEOUT_MS );
	} );
}

/**
 * Fetch the four §6.4 pages from a running site.
 *
 * @param {string}                      base Site base URL, no trailing slash.
 * @param {ReturnType<createCookieJar>} jar  Shared cookie jar.
 * @return {Promise<{front: Object, article: Object, series: Object, writing: Object}>} Pages.
 */
async function fetchPages( base, jar ) {
	const paths = {
		front: '/',
		article: '/signing-your-options-table/',
		series: '/series/',
		writing: '/writing/',
	};

	const pages = {};
	for ( const [ name, path ] of Object.entries( paths ) ) {
		const response = await fetchWithCookies( `${ base }${ path }`, jar );
		pages[ name ] = {
			status: response.status,
			html: await response.text(),
		};
	}
	return pages;
}

/**
 * Run the checks against an already-running site (`--url`), no Playground involved.
 *
 * @param {string}                      url   Site base URL.
 * @param {ReturnType<createCookieJar>} [jar] Cookie jar (a fresh one when omitted).
 * @return {Promise<string[]>} Failures.
 */
async function checkAgainstUrl( url, jar = createCookieJar() ) {
	const pages = await fetchPages( url.replace( /\/$/, '' ), jar );
	return checkPages( pages );
}

/**
 * Run the checks against a headless Playground boot of a local variant of the blueprint.
 *
 * @param {string}  fromDir Directory with `demo-content.xml`/`demo-options.json`/`blueprint.json`.
 * @param {boolean} keep    Leave the server running afterwards.
 * @return {Promise<string[]>} Failures.
 */
async function checkAgainstPlayground( fromDir, keep ) {
	console.log( 'demo:check: npm run release:pack' );
	execFileSync( 'npm', [ 'run', 'release:pack' ], { stdio: 'inherit' } );

	const tempDir = mkdtempSync( join( tmpdir(), 'ttm-demo-check-' ) );
	let staticServer;
	let playgroundProc;

	try {
		copyFileSync(
			join( 'dist', 'ttm-core.zip' ),
			join( tempDir, 'ttm-core.zip' )
		);
		copyFileSync(
			join( 'dist', 'ttm-theme.zip' ),
			join( tempDir, 'ttm-theme.zip' )
		);

		const { server, port: staticPort } = await startStaticServer( tempDir );
		staticServer = server;
		const staticBase = `http://127.0.0.1:${ staticPort }`;

		const rawWxr = readFileSync(
			join( fromDir, 'demo-content.xml' ),
			'utf8'
		);
		const localWxr = rebaseAttachmentUrls(
			rawWxr,
			IMAGE_RAW_BASE,
			`${ staticBase }/images`
		);
		writeFileSync( join( tempDir, 'demo-content.xml' ), localWxr );

		const blueprint = JSON.parse(
			readFileSync( join( fromDir, 'blueprint.json' ), 'utf8' )
		);
		const local = localBlueprint( blueprint, staticBase );
		writeFileSync(
			join( tempDir, 'blueprint.local.json' ),
			JSON.stringify( local, null, 2 ) + '\n'
		);

		const playgroundPort = await freePort();
		// `--keep` writes the server's own stdout/stderr to a log file in the (also kept) temp
		// dir rather than piping them to this process: a pipe whose read end lives here would
		// either EPIPE the child the moment this process exits, or (if left open) be exactly the
		// kind of dangling handle R1-04's `process.exit( 0 )` backstop had to paper over (R2-01).
		const logFile = keep ? join( tempDir, 'playground.log' ) : undefined;
		console.log(
			`demo:check: booting Playground on 127.0.0.1:${ playgroundPort } (static server on ${ staticBase })` +
				( logFile ? ` (log: ${ logFile })` : '' )
		);
		// Run the CLI's own resolved bin script directly with `process.execPath` -- not through
		// `npx`/`sh` -- and `detached: true`, so `stopServer()` can signal the whole process
		// group (including the CLI's own `--experimental-wasm-jspi` respawn) in one call instead
		// of only the immediate `npm exec` child, which used to leave the real server and its
		// worker running, reparented to init, after a bare `.kill()` (review F1).
		playgroundProc = startServer(
			process.execPath,
			[
				resolvePlaygroundCliBin(),
				'server',
				`--blueprint=${ join( tempDir, 'blueprint.local.json' ) }`,
				`--port=${ playgroundPort }`,
				'--login',
			],
			{ logFile }
		);

		const siteUrl = `http://127.0.0.1:${ playgroundPort }`;
		const jar = createCookieJar();
		await waitForBoot( `${ siteUrl }/`, playgroundProc, jar, { logFile } );

		const failures = await checkAgainstUrl( siteUrl, jar );

		if ( keep ) {
			console.log(
				`demo:check: --keep: leaving the server running at ${ siteUrl } ` +
					`(pid/pgid ${ playgroundProc.pid }); stop it with: ` +
					`kill -TERM -${ playgroundProc.pid }`
			);
			playgroundProc.unref();
			playgroundProc = null; // Don't stop it in `finally`.
		}

		return failures;
	} finally {
		if ( playgroundProc ) {
			await stopServer( playgroundProc );
		}
		if ( staticServer ) {
			staticServer.close();
		}
		if ( ! keep ) {
			rmSync( tempDir, { recursive: true, force: true } );
		}
	}
}

async function main() {
	const args = process.argv.slice( 2 );
	const fromDir = argValue( args, 'from', '.github' );
	const url = argValue( args, 'url' );
	const keep = args.includes( '--keep' );

	const failures = url
		? await checkAgainstUrl( url )
		: await checkAgainstPlayground( fromDir, keep );

	if ( failures.length > 0 ) {
		for ( const failure of failures ) {
			console.error( failure );
		}
		console.error( `demo:check: ${ failures.length } failure(s)` );
		process.exit( 1 );
	}

	console.log( 'demo:check: ok' );
	// Explicit exit, matching the failure paths above, kept as a backstop even now that
	// `stopServer()` (R2-01) properly waits for the whole Playground process group to exit
	// before this function returns: the real cause of the original hang was the orphaned
	// grandchild (reparented to init by a bare `.kill()` on the old `npx`-spawned child) still
	// holding its stdio pipes open, which kept this process's event loop alive indefinitely even
	// after printing `demo:check: ok` (confirmed directly: CI hung on this exact step for over
	// an hour). A pooled keep-alive `fetch()` connection could do the same on its own, hence
	// keeping this as a backstop rather than removing it.
	process.exit( 0 );
}

main().catch( ( error ) => {
	console.error( error.stack || String( error ) );
	process.exit( 1 );
} );
