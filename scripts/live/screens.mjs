#!/usr/bin/env node
/**
 * `node scripts/live/screens.mjs` (SPEC §6.10, `plan.sh` step 5): discovers the live URLs
 * `test:live` will check through WP-CLI (`npx wp-env run cli wp …`, host side, child_process)
 * and writes `docs/fixtures/live/screens.json` (gitignored, rule 47 -- titles are never stored,
 * only paths); prints `wp ttm audit --summary`'s markdown table for `LIVE-TRIAGE.md`.
 *
 * All fetching/formatting here is intentionally dumb: the actual manifest shape (SPEC §6.10,
 * PLAN Decision "screens.json shape") is built by the pure, unit-tested `buildScreens()` in
 * `scripts/live/lib/screens.mjs`; this file only gathers plain JSON for it and writes the file.
 *
 * "Contained [ref]/[cci]/[cc]/[mfn]" is read from `ttm_classic_backup` post meta -- the
 * *original* classic markup, saved by `migrate:images`/`convert:import` before conversion --
 * because by the time this script runs (after `plan.sh`'s `convert:import` step) a converted
 * post's own `post_content` is block markup, not the shortcodes that used to be there.
 * "classic"/"freeform" are read directly off each candidate's *current* `post_content` (the
 * same signals `AuditCommand`'s own `classic` flag and `convert-classic.mjs`'s freeform count
 * use): no `<!-- wp:` marker at all = still classic; a `core/freeform`/`core/html` block present
 * = a conversion fallback.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { buildScreens } from './lib/screens.mjs';

// Mirrors themes/ttm-theme/inc/starter-content.php's starter_sections() -- the seven section
// slugs, nav order.
const SECTIONS = [
	'technology',
	'business',
	'faith',
	'journal',
	'writing',
	'security',
	'opinion',
];

// Mirrors Config::defaults()['archive.per_page'] -- a Node script has no access to the PHP
// Config class, so this is kept in sync by hand; only affects which page is picked as "last".
const ARCHIVE_PER_PAGE = 12;

/**
 * Run a `wp` command inside the dev wp-env container and return trimmed stdout. wp-env's own
 * "Starting …"/"Ran …" banner lines go to stderr, not stdout, so stdout alone is always exactly
 * what the underlying `wp` command printed.
 *
 * @param {string[]} args `wp` args (after `wp` itself).
 * @return {string} Trimmed stdout.
 */
function wp( args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * `wp … --format=json`, parsed; `[]` for empty/absent output rather than a parse error.
 *
 * @param {string[]} args `wp` args (after `wp` itself); should already include `--format=json`.
 * @return {Array<object>} Parsed rows.
 */
function wpJson( args ) {
	const out = wp( args );
	return out ? JSON.parse( out ) : [];
}

/**
 * A raw `wp post list` row -> the pure builder's `ScreenPost` shape, or null.
 *
 * @param {object|undefined} row `{ID, post_name, post_content}`.
 * @return {{id: number, slug: string, classic: boolean, freeform: boolean}|null} The screen post, or null.
 */
function toPost( row ) {
	if ( ! row ) {
		return null;
	}
	const content = String( row.post_content ?? '' );
	return {
		id: Number( row.ID ),
		slug: row.post_name,
		classic: ! content.includes( '<!-- wp:' ),
		freeform:
			content.includes( 'wp:freeform' ) || content.includes( 'wp:html' ),
	};
}

/**
 * One `wp post list` row per section: the newest published post and the published count.
 *
 * @param {string} section Category slug.
 * @return {{section: string, newest: object|null, count: number}} The section's screen data.
 */
function sectionPost( section ) {
	const rows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		`--category=${ section }`,
		'--orderby=date',
		'--order=DESC',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content',
	] );

	const count = Number(
		wp( [
			'post',
			'list',
			'--post_type=post',
			'--post_status=publish',
			`--category=${ section }`,
			'--format=count',
		] ) || '0'
	);

	return { section, newest: toPost( rows[ 0 ] ), count };
}

/**
 * Up to `limit` posts whose `ttm_classic_backup` matches `pattern`, among posts that still carry
 * that meta key. Bounded (`cli.batch`-sized fetch, `--posts_per_page=500` -- rule 12) and
 * best-effort: an empty/absent backup (nothing converted yet) yields `[]`, never an error.
 *
 * @param {RegExp} pattern Matched against the raw classic backup text.
 * @param {number} limit   Stop once this many matches are found.
 * @return {Array<object>} Matching screen posts.
 */
function classicShortcodePosts( pattern, limit ) {
	const candidates = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--meta_compare=EXISTS',
		'--meta_key=ttm_classic_backup',
		'--orderby=date',
		'--order=DESC',
		'--posts_per_page=500',
		'--format=json',
		'--fields=ID,post_name',
	] );

	const matches = [];
	for ( const row of candidates ) {
		if ( matches.length >= limit ) {
			break;
		}
		const backup = wp( [
			'post',
			'meta',
			'get',
			String( row.ID ),
			'ttm_classic_backup',
		] );
		if ( backup && pattern.test( backup ) ) {
			matches.push( {
				id: Number( row.ID ),
				slug: row.post_name,
				classic: false,
				freeform: false,
			} );
		}
	}
	return matches;
}

function main() {
	if ( ! existsSync( 'docs/fixtures/live/import.xml' ) ) {
		// eslint-disable-next-line no-console
		console.log( 'env:live skipped: no export' );
		return;
	}

	const host = process.env.LIVE_HOST || 'http://localhost:8888';
	const seriesSlugs = JSON.parse(
		readFileSync( 'docs/migration/series.json', 'utf8' )
	).map( ( entry ) => entry.slug );

	const sectionPosts = SECTIONS.map( ( section ) => sectionPost( section ) );
	const journalSection = sectionPosts.find(
		( entry ) => 'journal' === entry.section
	);

	const oldestRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--orderby=date',
		'--order=ASC',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content',
	] );

	const asideRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--post_format=aside',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content',
	] );

	const featuredRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--meta_key=_thumbnail_id',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content',
	] );

	const unfeaturedRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--meta_key=_thumbnail_id',
		'--meta_compare=NOT EXISTS',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content',
	] );

	const manifest = buildScreens( {
		host,
		archivePerPage: ARCHIVE_PER_PAGE,
		sectionPosts,
		oldest: toPost( oldestRows[ 0 ] ),
		journalNewest: journalSection ? journalSection.newest : null,
		refPosts: classicShortcodePosts( /\[ref\b/, 2 ),
		ccPosts: classicShortcodePosts( /\[cc[i]?\b/, 2 ),
		mfnPosts: classicShortcodePosts( /\[mfn\b/, 1 ),
		asidePost: toPost( asideRows[ 0 ] ),
		featuredPost: toPost( featuredRows[ 0 ] ),
		unfeaturedPost: toPost( unfeaturedRows[ 0 ] ),
		seriesSlugs,
	} );

	writeFileSync(
		'docs/fixtures/live/screens.json',
		JSON.stringify( manifest, null, '\t' ) + '\n'
	);
	// eslint-disable-next-line no-console
	console.log(
		`screens.mjs: wrote ${ manifest.screens.length } screen(s) to docs/fixtures/live/screens.json`
	);

	try {
		// eslint-disable-next-line no-console
		console.log( wp( [ 'ttm', 'audit', '--summary' ] ) );
	} catch {
		// eslint-disable-next-line no-console
		console.log(
			'screens.mjs: audit --summary unavailable (plan.sh has not reached it yet)'
		);
	}
}

// Only run when this file is executed directly (`node scripts/live/screens.mjs`), not when it's
// imported for its pure helpers by a test. `process.argv[1]` (not `import.meta.url`, which
// Jest's CommonJS transform of a dynamic `import()` can't parse -- see scripts/screenshots.mjs
// for the same pattern) is this process's entry script path.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'screens.mjs' ) ) {
	main();
}
