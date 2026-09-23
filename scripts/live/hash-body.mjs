#!/usr/bin/env node
/**
 * `node scripts/live/hash-body.mjs <url>` (SPEC §6.9, P5-01): fetches `<url>` and prints
 * `{"title":"…","hash":"…"}` on one line -- `drill.sh` calls this once before wiping the site
 * and once after restoring it, for each of five URLs, and fails the drill if any hash changed.
 *
 * The hash is over the *normalized* body (whitespace-collapsed, `<meta name="generator">`
 * stripped -- WordPress prints a version number there, so a restore onto a different WP version
 * would otherwise report a false difference having nothing to do with the actual content).
 */

import { createHash } from 'node:crypto';

/**
 * Collapse whitespace and drop the `<meta name="generator">` tag, so the hash reflects visible
 * content, not incidental formatting or the current WP version string.
 *
 * @param {string} html Raw HTML.
 * @return {string} Normalized HTML.
 */
export function normalizeBody( html ) {
	return html
		.replace( /<meta\s+name=["']generator["'][^>]*>/gi, '' )
		.replace( /\s+/g, ' ' )
		.trim();
}

/**
 * SHA-256 hex digest of `html`'s normalized form.
 *
 * @param {string} html Raw HTML.
 * @return {string} Hex-encoded SHA-256 digest.
 */
export function hashBody( html ) {
	return createHash( 'sha256' )
		.update( normalizeBody( html ) )
		.digest( 'hex' );
}

async function main() {
	const url = process.argv[ 2 ];
	if ( ! url ) {
		// eslint-disable-next-line no-console
		console.error( 'usage: node scripts/live/hash-body.mjs <url>' );
		process.exit( 1 );
	}

	const response = await fetch( url );
	const html = await response.text();
	const titleMatch = html.match( /<title>([^<]*)<\/title>/i );

	// eslint-disable-next-line no-console
	console.log(
		JSON.stringify( {
			title: titleMatch ? titleMatch[ 1 ].trim() : '',
			hash: hashBody( html ),
		} )
	);
}

// Only run the CLI body when this file is executed directly (`node scripts/live/hash-body.mjs`),
// not when it's imported for `normalizeBody`/`hashBody` by scripts/test/hash-body.test.js.
// `process.argv[1]` (not `import.meta.url`, which Jest's CommonJS transform of this test's
// dynamic `import()` can't parse -- see scripts/live/series-args.mjs for the same pattern) is
// this process's entry script path.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'hash-body.mjs' ) ) {
	main().catch( ( err ) => {
		// eslint-disable-next-line no-console
		console.error( err );
		process.exit( 1 );
	} );
}
