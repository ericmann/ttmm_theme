/**
 * Shared helpers for the live check suite (`tests/e2e/live.spec.mjs`, SPEC §6.10, P4-01).
 */

import { execFileSync } from 'node:child_process';

/**
 * `wp-content/debug.log`'s line count inside the `cli` wp-env container (0 when the file
 * doesn't exist yet) -- checked once at the start and once at the end of the suite; a change
 * means some screen logged a PHP notice/warning/error that the body-text check below didn't
 * already catch on that specific page (e.g. a fatal on a *different* request the page made).
 *
 * @return {number} Line count.
 */
export function debugLogLineCount() {
	const out = execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'cli',
			'sh',
			'-c',
			'wc -l < wp-content/debug.log || echo 0',
		],
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] }
	).trim();

	const lines = out.split( '\n' );
	return parseInt( lines[ lines.length - 1 ], 10 ) || 0;
}

/**
 * Split a page's observed requests into same-origin/allowed vs. cross-origin-by-type, per
 * SPEC §6.10: no cross-origin script/stylesheet/XHR/font at all (returned as `offenders`);
 * cross-origin **images** are allowed but counted per host (returned as `imagesByHost`), not
 * failed.
 *
 * @param {Array<{url: string, type: string}>} requests    Every request the page made.
 * @param {string}                             baseHost    The page's own host (`new URL(baseURL).host`).
 * @param {RegExp}                             allowedHost Cross-origin hosts that are never offenders (e.g. Jetpack).
 * @return {{offenders: string[], imagesByHost: Record<string, number>}} Classified requests.
 */
export function classifyRequests( requests, baseHost, allowedHost ) {
	const offenders = [];
	const imagesByHost = {};

	for ( const { url, type } of requests ) {
		if ( url.startsWith( 'data:' ) || url.startsWith( 'blob:' ) ) {
			continue;
		}

		const parsed = new URL( url );
		if ( parsed.host === baseHost ) {
			continue;
		}
		if ( allowedHost.test( parsed.host ) ) {
			continue;
		}

		if ( 'image' === type ) {
			imagesByHost[ parsed.host ] =
				( imagesByHost[ parsed.host ] || 0 ) + 1;
			continue;
		}

		if (
			[ 'script', 'stylesheet', 'xhr', 'fetch', 'font' ].includes( type )
		) {
			offenders.push( url );
		}
	}

	return { offenders, imagesByHost };
}

/**
 * Whether a body of text contains any of the classic PHP error markers SPEC §6.10 names.
 *
 * @param {string} bodyText Rendered page text (or full HTML).
 * @return {string[]} The markers found, in order (empty when clean).
 */
export function phpErrorMarkers( bodyText ) {
	return [ 'Warning:', 'Notice:', 'Deprecated:', 'Fatal error' ].filter(
		( marker ) => bodyText.includes( marker )
	);
}
