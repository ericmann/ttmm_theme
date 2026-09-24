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
import { buildScreens, sectionCategoryArgs } from './lib/screens.mjs';

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

// Mirrors Config::defaults()['journal.archive_per_page'] (P4-04): Journal's own category
// archive paginates at a different rate than every other section's `archive.per_page`, so its
// "last page" must be computed against 20, not 12 -- using the shared constant here 404'd
// `archive-journal-last` (SPEC §6.10 finding, docs/feedback/phase-4/LIVE-TRIAGE.md).
const JOURNAL_ARCHIVE_PER_PAGE = 20;

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
 * The display name of a post's primary category (SPEC §6.10, R1-02): `ttm_primary_category`
 * post meta (falling back to nothing when unset/0, same as `PrimaryCategory::id()` -- this
 * script never re-derives the nav-order fallback, it only reads what's actually stored) ->
 * `wp term get` for that term's `name`. Empty string (not an error) when either step misses,
 * so a post that legitimately has no primary yet (e.g. `--from-yoast` hasn't run) just yields
 * `primary: null` rather than failing the whole manifest build.
 *
 * @param {number} postId Post ID.
 * @return {string} Display name, or '' when unset/not found.
 */
function primaryCategoryName( postId ) {
	const termId = Number(
		wp( [
			'post',
			'meta',
			'get',
			String( postId ),
			'ttm_primary_category',
		] ) || '0'
	);
	if ( ! termId ) {
		return '';
	}
	try {
		return wp( [
			'term',
			'get',
			'category',
			String( termId ),
			'--field=name',
		] );
	} catch {
		return '';
	}
}

/**
 * A raw `wp post list` row -> the pure builder's `ScreenPost` shape, or null.
 *
 * @param {object|undefined} row `{ID, post_name, post_content, post_date}`.
 * @return {{id: number, slug: string, classic: boolean, freeform: boolean, date: string|null, primary: string|null}|null} The screen post, or null.
 */
function toPost( row ) {
	if ( ! row ) {
		return null;
	}
	const content = String( row.post_content ?? '' );
	const primary = primaryCategoryName( Number( row.ID ) );
	return {
		id: Number( row.ID ),
		slug: row.post_name,
		classic: ! content.includes( '<!-- wp:' ),
		freeform:
			content.includes( 'wp:freeform' ) || content.includes( 'wp:html' ),
		date: row.post_date ?? null,
		primary: primary || null,
	};
}

/**
 * One `wp post list` row per section: the newest published post and the published count.
 *
 * @param {string} section Category slug.
 * @return {{section: string, newest: object|null, count: number}} The section's screen data.
 */
function sectionPost( section ) {
	const categoryArgs = sectionCategoryArgs( section );

	const rows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		...categoryArgs,
		'--orderby=date',
		'--order=DESC',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content,post_date',
	] );

	const count = Number(
		wp( [
			'post',
			'list',
			'--post_type=post',
			'--post_status=publish',
			...categoryArgs,
			'--format=count',
		] ) || '0'
	);

	return { section, newest: toPost( rows[ 0 ] ), count };
}

/**
 * Up to `limit` posts whose `ttm_classic_backup` matches `pattern`, among posts `convert:import`
 * actually converted from classic content (R1-09, SPEC §6.10 finding: `ttm_classic_backup` is
 * also written by `MigrateCommand::images()` as a plain "before I touch this content" backup
 * for *any* post whose images got rewritten -- including a modern, already-block post that
 * legitimately uses `[ref]…[/ref]` as the author's own informal footnote convention, P4-05 --
 * so filtering on that meta key alone picked those up as false "conversion" screens and failed
 * `live.spec.mjs`'s shortcode-residue check on content that was never supposed to convert.
 * `ttm_converted_at` is set only by `ConvertCommand::import_one()`, the real signal). Bounded
 * (`cli.batch`-sized fetch, `--posts_per_page=500` -- rule 12) and best-effort: an empty/absent
 * backup (nothing converted yet) yields `[]`, never an error.
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
		'--meta_key=ttm_converted_at',
		'--orderby=date',
		'--order=DESC',
		'--posts_per_page=500',
		'--format=json',
		'--fields=ID,post_name,post_date',
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
				date: row.post_date ?? null,
				primary: primaryCategoryName( Number( row.ID ) ) || null,
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
		'--fields=ID,post_name,post_content,post_date',
	] );

	const asideRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--post_format=aside',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content,post_date',
	] );

	const featuredRows = wpJson( [
		'post',
		'list',
		'--post_type=post',
		'--post_status=publish',
		'--meta_key=_thumbnail_id',
		'--posts_per_page=1',
		'--format=json',
		'--fields=ID,post_name,post_content,post_date',
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
		'--fields=ID,post_name,post_content,post_date',
	] );

	const manifest = buildScreens( {
		host,
		archivePerPage: ARCHIVE_PER_PAGE,
		archivePerPageBySection: { journal: JOURNAL_ARCHIVE_PER_PAGE },
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
