#!/usr/bin/env node
/**
 * Extracts the WXR's own `<wp:category>` term-id -> slug map (R2-03, SPEC §6.7, §9 Q2): a
 * WXR's top-level `<wp:category>` elements record the *source* site's category `<wp:term_id>`
 * next to its `<wp:category_nicename>` (the slug), which survives the import unchanged even
 * though the *destination* site assigns fresh term ids on `wp import` (existing categories are
 * matched and reused by slug, new ones get new ids -- either way, the id in a post's Yoast
 * `_yoast_wpseo_primary_category` meta, written against the *source* site, is never a
 * destination term id). `primary:assign --from-yoast --term-map=<path>` reads this map to
 * translate that source id to the *slug*, then resolves the slug to whatever term id it has on
 * this site -- so it works regardless of whether that category was reused or freshly created.
 *
 * `import.sh` writes this file next to `import.xml`/`classic.ndjson` (same gitignored
 * `docs/fixtures/live/`/`wp-content/ttm-fixtures/live/` convention, rule 47: never committed);
 * `plan.sh` passes its path to `primary:assign --from-yoast`.
 *
 * Regex over the WXR's own well-formed, single-line CDATA fields (matching the `<wp:category>`
 * scan `docs/fixtures/live/import.xml` actually contains -- see convert-classic.mjs's
 * shortcode/footnote pre-passes for the same "raw XML export, not general-purpose XML" choice),
 * not a full XML parser: cheap, and every field involved is a WordPress-generated id/slug, never
 * arbitrary user HTML.
 */

import { readFileSync, writeFileSync } from 'node:fs';

const CATEGORY_BLOCK = /<wp:category>([\s\S]*?)<\/wp:category>/g;
const TERM_ID = /<wp:term_id>(\d+)<\/wp:term_id>/;
const NICENAME =
	/<wp:category_nicename>(?:<!\[CDATA\[([\s\S]*?)\]\]>|([^<]*))<\/wp:category_nicename>/;

/**
 * Pure: every `<wp:category>` block's `<wp:term_id>` -> `<wp:category_nicename>` pair.
 *
 * @param {string} xml Raw WXR document text.
 * @return {Record<string, string>} Source term id (as a string key) -> slug.
 */
export function extractTermMap( xml ) {
	const map = {};

	for ( const match of xml.matchAll( CATEGORY_BLOCK ) ) {
		const block = match[ 1 ];
		const idMatch = TERM_ID.exec( block );
		const slugMatch = NICENAME.exec( block );

		if ( ! idMatch || ! slugMatch ) {
			continue;
		}

		const slug = slugMatch[ 1 ] ?? slugMatch[ 2 ] ?? '';
		if ( '' === slug ) {
			continue;
		}

		map[ idMatch[ 1 ] ] = slug;
	}

	return map;
}

function main() {
	const [ inputPath, outputPath ] = process.argv.slice( 2 );
	if ( ! inputPath || ! outputPath ) {
		// eslint-disable-next-line no-console
		console.error(
			'Usage: node scripts/live/term-map.mjs <in.xml> <out.json>'
		);
		process.exit( 1 );
	}

	const xml = readFileSync( inputPath, 'utf8' );
	const map = extractTermMap( xml );

	writeFileSync( outputPath, JSON.stringify( map ) );
	// eslint-disable-next-line no-console
	console.log(
		`term-map.mjs: wrote ${ Object.keys( map ).length } term(s) -> ${ outputPath }`
	);
}

// Only run the CLI body when this file is executed directly, not when it's imported for
// `extractTermMap` by scripts/test/term-map.test.js (same guard as scripts/live/series-args.mjs).
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'term-map.mjs' ) ) {
	main();
}
