#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run demo:fetch-images [-- --only=<file>] [-- --dry-run]` (P1-01, SPEC §6.1, §6.2, §6.8,
 * rule 54): a human-run, paced Openverse fetch that fills `docs/fixtures/demo/images/` and
 * `docs/fixtures/demo/CREDITS.json` from `scripts/demo/images.json`. Never runs in CI (rule
 * 54); the only network calls in the whole `scripts/demo/` tree live here.
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
import {
	validateRows,
	searchUrl,
	pickResult,
	acceptEncoded,
	creditRow,
	sortCredits,
} from './lib/openverse.mjs';
import {
	IMAGE_MAX_BYTES,
	OPENVERSE_MIN_WIDTH,
	OPENVERSE_PAGE_SIZE,
	OPENVERSE_PACE_MS,
	OPENVERSE_LICENSES,
} from './lib/constants.mjs';

const IMAGES_JSON_PATH = join( 'scripts', 'demo', 'images.json' );
const IMAGES_DIR = join( 'docs', 'fixtures', 'demo', 'images' );
const CREDITS_PATH = join( 'docs', 'fixtures', 'demo', 'CREDITS.json' );
const USER_AGENT =
	'ttmm_theme-demo-fetch (+https://github.com/ericmann/ttmm_theme)';

/**
 * Parse the two supported CLI args.
 *
 * @param {string[]} argv `process.argv.slice(2)`.
 * @return {{only: string|null, dryRun: boolean}} Parsed args.
 */
export function parseArgs( argv ) {
	let only = null;
	let dryRun = false;

	for ( const arg of argv ) {
		if ( arg.startsWith( '--only=' ) ) {
			only = arg.slice( '--only='.length );
		} else if ( '--dry-run' === arg ) {
			dryRun = true;
		}
	}

	return { only, dryRun };
}

/**
 * Fetch one Openverse search page.
 *
 * @param {string} url Search URL (`searchUrl()`'s output).
 * @return {Promise<{results: Array<Object>}>} Parsed JSON body.
 */
async function search( url ) {
	const response = await fetch( url, {
		headers: { 'User-Agent': USER_AGENT },
	} );
	if ( ! response.ok ) {
		throw new Error(
			`Openverse search failed: ${ response.status } ${ response.statusText }`
		);
	}
	return response.json();
}

/**
 * Download `url` and re-encode it with `sharp` (dynamic import: only this file and
 * screenshots.mjs pull it in) to a JPEG, metadata stripped. Does not itself judge whether the
 * result is acceptable (size or width) -- see `acceptEncoded()`, called by the caller against
 * the *actual* decoded width, since Openverse's own search-result metadata is sometimes wrong.
 *
 * @param {string} url Candidate result's `url`.
 * @return {Promise<{buffer: Buffer, width: number, height: number}>} The re-encoded image.
 */
async function downloadAndEncode( url ) {
	const response = await fetch( url, {
		headers: { 'User-Agent': USER_AGENT },
	} );
	if ( ! response.ok ) {
		throw new Error(
			`Openverse download failed: ${ response.status } ${ response.statusText }`
		);
	}
	const original = Buffer.from( await response.arrayBuffer() );

	const { default: sharp } = await import( 'sharp' );
	const pipeline = sharp( original )
		.rotate()
		.resize( { width: 1600, withoutEnlargement: true } )
		.jpeg( { quality: 82, mozjpeg: true } );
	const buffer = await pipeline.toBuffer();
	const metadata = await sharp( buffer ).metadata();

	return { buffer, width: metadata.width, height: metadata.height };
}

/**
 * Fetch, pick and (unless `dryRun`) download+encode one `images.json` row, retrying the next
 * qualifying result when the chosen one is still too large after re-encoding.
 *
 * @param {Object}      row       One `images.json` row.
 * @param {Set<string>} chosenIds Ids already used by another row this run.
 * @param {boolean}     dryRun    Search-only, no download/write.
 * @return {Promise<{ok: true, credit: Object}|{ok: true, dryRun: {id: string, title: string, license: string, width: number}}|{ok: false, error: string}>}
 *   The outcome for this row.
 */
async function fetchRow( row, chosenIds, dryRun ) {
	const url = searchUrl( row, {
		pageSize: OPENVERSE_PAGE_SIZE,
		licenses: OPENVERSE_LICENSES,
	} );

	let results;
	try {
		( { results } = await search( url ) );
	} catch ( error ) {
		return { ok: false, error: error.message };
	}

	const skipIds = new Set();
	const licenses = OPENVERSE_LICENSES.split( ',' );

	for (;;) {
		const result = pickResult( results, {
			minWidth: OPENVERSE_MIN_WIDTH,
			licenses,
			chosenIds: [ ...chosenIds ],
			exclude: row.exclude || [],
			skipIds: [ ...skipIds ],
		} );

		if ( ! result ) {
			return {
				ok: false,
				error: 'no qualifying Openverse result found',
			};
		}

		if ( dryRun ) {
			return {
				ok: true,
				dryRun: {
					id: result.id,
					title: result.title,
					license: result.license,
					width: result.width,
				},
			};
		}

		let encoded;
		try {
			encoded = await downloadAndEncode( result.url );
		} catch ( error ) {
			return { ok: false, error: error.message };
		}

		if (
			! acceptEncoded(
				{ width: encoded.width, bytes: encoded.buffer.length },
				{ minWidth: OPENVERSE_MIN_WIDTH, maxBytes: IMAGE_MAX_BYTES }
			)
		) {
			skipIds.add( result.id );
			continue;
		}

		const sha256 = createHash( 'sha256' )
			.update( encoded.buffer )
			.digest( 'hex' );

		return {
			ok: true,
			credit: creditRow( result, row.file, {
				width: encoded.width,
				height: encoded.height,
				bytes: encoded.buffer.length,
				sha256,
			} ),
			buffer: encoded.buffer,
		};
	}
}

/**
 * @return {Promise<void>}
 */
async function run() {
	if ( process.env.CI ) {
		console.error(
			'demo:fetch-images: human-run only; refusing to run with CI set (rule 54).'
		);
		process.exit( 1 );
	}

	const { only, dryRun } = parseArgs( process.argv.slice( 2 ) );

	const allRows = JSON.parse( readFileSync( IMAGES_JSON_PATH, 'utf8' ) );
	const validationFailures = validateRows( allRows );
	if ( validationFailures.length > 0 ) {
		for ( const failure of validationFailures ) {
			console.error( failure );
		}
		console.error( 'demo:fetch-images: images.json is invalid.' );
		process.exit( 1 );
	}

	const existingCredits = existsSync( CREDITS_PATH )
		? JSON.parse( readFileSync( CREDITS_PATH, 'utf8' ) )
		: [];
	const existingByFile = new Map(
		existingCredits.map( ( row ) => [ row.file, row ] )
	);

	const targetRows = only
		? allRows.filter( ( row ) => only === row.file )
		: allRows;
	if ( only && 0 === targetRows.length ) {
		console.error( `demo:fetch-images: no row for --only=${ only }` );
		process.exit( 1 );
	}

	// A row being re-fetched excludes its own previous choice, so --only=<file> never picks
	// the same photo again; every other row's current choice stays in `chosenIds` so a
	// re-fetch never grabs a photo already used elsewhere.
	const targetFiles = new Set( targetRows.map( ( row ) => row.file ) );
	const chosenIds = new Set(
		existingCredits
			.filter( ( row ) => ! targetFiles.has( row.file ) )
			.map( ( row ) => row.openverse_id )
	);
	const rowsWithExclusions = targetRows.map( ( row ) => {
		const previous = existingByFile.get( row.file );
		if ( ! previous ) {
			return row;
		}
		return {
			...row,
			exclude: [ ...( row.exclude || [] ), previous.openverse_id ],
		};
	} );

	if ( ! dryRun ) {
		mkdirSync( IMAGES_DIR, { recursive: true } );
	}

	const failures = [];
	const newCredits = [];

	for ( const [ index, row ] of rowsWithExclusions.entries() ) {
		if ( index > 0 ) {
			await new Promise( ( resolve ) =>
				setTimeout( resolve, OPENVERSE_PACE_MS )
			);
		}

		const outcome = await fetchRow( row, chosenIds, dryRun );

		if ( ! outcome.ok ) {
			failures.push( `${ row.file }: ${ outcome.error }` );
			continue;
		}

		if ( dryRun ) {
			console.log(
				`${ row.file }: ${ outcome.dryRun.id } "${ outcome.dryRun.title }" ` +
					`(${ outcome.dryRun.license }, ${ outcome.dryRun.width }px)`
			);
			continue;
		}

		chosenIds.add( outcome.credit.openverse_id );
		newCredits.push( outcome.credit );
		writeFileSync( join( IMAGES_DIR, row.file ), outcome.buffer );
		console.log( `wrote ${ join( IMAGES_DIR, row.file ) }` );
	}

	if ( ! dryRun && newCredits.length > 0 ) {
		const merged = [
			...existingCredits.filter(
				( row ) => ! newCredits.some( ( n ) => n.file === row.file )
			),
			...newCredits,
		];
		writeFileSync(
			CREDITS_PATH,
			JSON.stringify( sortCredits( merged ), null, 2 ) + '\n'
		);
		console.log( `wrote ${ CREDITS_PATH }` );
	}

	if ( failures.length > 0 ) {
		console.error( 'demo:fetch-images: failed row(s):' );
		for ( const failure of failures ) {
			console.error( `  ${ failure }` );
		}
		process.exit( 1 );
	}
}

// Only run when executed directly (`node scripts/demo/fetch-images.mjs` / `npm run
// demo:fetch-images`), matching scripts/screenshots.mjs's own guard, so
// scripts/test/*.test.js can import its pure exports (parseArgs) without triggering a run.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'fetch-images.mjs' ) ) {
	run();
}
