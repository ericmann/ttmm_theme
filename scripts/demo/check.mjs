#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run demo:check [-- --from <dir>] [--url <site>] [--keep]` (SPEC §3.2 rule 57, §6.4,
 * P2-06). Boots the blueprint headless against zips and WXR built from the current tree and
 * asserts the §6.4 pages. `--url` skips Playground entirely and asserts against a running site
 * (e.g. the seeded wp-env). Network only to the Playground CDN (for WordPress itself) and to
 * this script's own local static server (rule 54).
 */
import { execFileSync, spawn } from 'node:child_process';
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
import { checkPages } from './lib/check-assertions.mjs';
import { localBlueprint } from './lib/local-variant.mjs';
import { rebaseAttachmentUrls } from './lib/wxr.mjs';

const PLAYGROUND_BOOT_TIMEOUT_MS = 600000;
const BOOT_POLL_INTERVAL_MS = 2000;

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

/**
 * Fetch a URL, following redirects manually (carrying `jar`'s cookies on every hop) rather than
 * relying on `fetch()`'s own automatic redirect-following -- see `createCookieJar()`'s note.
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

/**
 * Poll a URL until the *real* seeded front page renders (not just "the HTTP server answers") or
 * the process exits/times out. Playground's workers start accepting requests while the
 * blueprint's later steps (the `wp eval` importing/rebuilding content) may still be running --
 * confirmed directly: resolving on the first sub-500 response raced ahead of that step and
 * asserted against a still-empty site every time. `.ttm-cell-heading__label` only ever appears
 * once the theme's front-page template renders real section content, so it doubles as the
 * readiness probe.
 *
 * @param {string}                                    url  URL to poll.
 * @param {import('node:child_process').ChildProcess} proc Playground server process.
 * @param {ReturnType<createCookieJar>}               jar  Shared cookie jar.
 * @return {Promise<void>} Resolves once the front page has real content; rejects on timeout or
 *   process exit.
 */
function waitForBoot( url, proc, jar ) {
	return new Promise( ( resolve, reject ) => {
		let exited = false;
		let exitOutput = '';
		proc.stdout.on( 'data', ( chunk ) => {
			exitOutput += chunk.toString();
		} );
		proc.stderr.on( 'data', ( chunk ) => {
			exitOutput += chunk.toString();
		} );
		proc.on( 'exit', ( code ) => {
			exited = true;
			reject(
				new Error(
					`Playground server exited (code ${ code }) before booting:\n${ exitOutput }`
				)
			);
		} );

		const start = Date.now();
		const poll = async () => {
			if ( exited ) {
				return;
			}
			try {
				const response = await fetchWithCookies( url, jar );
				if ( 200 === response.status ) {
					const html = await response.text();
					if ( html.includes( 'ttm-cell-heading__label' ) ) {
						resolve();
						return;
					}
				}
			} catch {
				// Not up yet.
			}
			if ( Date.now() - start > PLAYGROUND_BOOT_TIMEOUT_MS ) {
				reject(
					new Error(
						`Playground server did not boot within ${ PLAYGROUND_BOOT_TIMEOUT_MS }ms`
					)
				);
				return;
			}
			setTimeout( poll, BOOT_POLL_INTERVAL_MS );
		};
		poll();
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
		console.log(
			`demo:check: booting Playground on 127.0.0.1:${ playgroundPort } (static server on ${ staticBase })`
		);
		playgroundProc = spawn(
			'npx',
			[
				'@wp-playground/cli',
				'server',
				`--blueprint=${ join( tempDir, 'blueprint.local.json' ) }`,
				`--port=${ playgroundPort }`,
				'--login',
			],
			{ stdio: [ 'ignore', 'pipe', 'pipe' ] }
		);

		const siteUrl = `http://127.0.0.1:${ playgroundPort }`;
		const jar = createCookieJar();
		await waitForBoot( `${ siteUrl }/`, playgroundProc, jar );

		const failures = await checkAgainstUrl( siteUrl, jar );

		if ( keep ) {
			console.log(
				`demo:check: --keep: leaving the server running at ${ siteUrl }`
			);
			playgroundProc = null; // Don't kill it in `finally`.
		}

		return failures;
	} finally {
		if ( playgroundProc ) {
			playgroundProc.kill();
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
}

main().catch( ( error ) => {
	console.error( error.stack || String( error ) );
	process.exit( 1 );
} );
