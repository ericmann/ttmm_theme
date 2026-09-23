#!/usr/bin/env node
/**
 * Prints one field-separated line per `docs/migration/series.json` entry, for `plan.sh` to
 * build `wp ttm series:assign <slug> --from-tags=<tags> --form=<form> --status=<status>
 * [--total=<n>] --name=<name>` calls from (SPEC §6.6 step 4, §9 Q1).
 *
 * Usage: node scripts/live/series-args.mjs <series.json>
 * Each line: slug<SEP>tags(comma-joined)<SEP>form<SEP>status<SEP>total-or-empty<SEP>name
 * (SEP is FIELD_SEPARATOR, `|`, not a tab: bash's own `read` treats a tab as IFS *whitespace*
 * and silently collapses two adjacent tabs -- i.e. an empty middle field -- into one delimiter,
 * shifting every field after it. `|` isn't whitespace, so `IFS='|' read` in plan.sh splits
 * every field correctly even when `total` is empty. Verified against a real env:live run: an
 * empty `total` field shifted `name` out of `--name=` and into `--total=`, which then fataled
 * `series:assign` on an empty term name.)
 *
 * Exits 1 (message on stderr) if any entry is missing a slug or a non-empty tags list.
 */

import { readFileSync } from 'node:fs';

const FIELD_SEPARATOR = '|';

/**
 * Pure: validate and format every series.json entry as a field-separated line.
 *
 * @param {Array<object>} entries Parsed series.json array.
 * @return {string[]} One line per entry, in order.
 */
export function seriesArgsLines( entries ) {
	return entries.map( ( entry, index ) => {
		if ( ! entry || typeof entry.slug !== 'string' || '' === entry.slug ) {
			throw new Error( `series.json entry ${ index }: missing slug` );
		}
		if ( ! Array.isArray( entry.tags ) || 0 === entry.tags.length ) {
			throw new Error(
				`series.json entry ${ index } (${ entry.slug }): missing tags`
			);
		}

		return [
			entry.slug,
			entry.tags.join( ',' ),
			entry.form ?? '',
			entry.status ?? '',
			entry.total ?? '',
			entry.name ?? '',
		].join( FIELD_SEPARATOR );
	} );
}

function main() {
	const path = process.argv[ 2 ];
	if ( ! path ) {
		// eslint-disable-next-line no-console
		console.error(
			'Usage: node scripts/live/series-args.mjs <series.json>'
		);
		process.exit( 1 );
	}

	let entries;
	try {
		entries = JSON.parse( readFileSync( path, 'utf8' ) );
		seriesArgsLines( entries ).forEach( ( line ) => {
			// eslint-disable-next-line no-console
			console.log( line );
		} );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( error.message );
		process.exit( 1 );
	}
}

// Only run the CLI body when this file is executed directly (`node scripts/live/series-args.mjs`),
// not when it's imported for `seriesArgsLines` by scripts/test/series-args.test.js.
// `process.argv[1]` (not `import.meta.url`, which Jest's CommonJS transform of this test's
// dynamic `import()` can't parse -- see scripts/screenshots.mjs for the same pattern) is this
// process's entry script path.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'series-args.mjs' ) ) {
	main();
}
