#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run screenshots` (SPEC §6.14, P0-01, formerly P0-12): writes the phase-4 comparison set
 * into `docs/feedback/phase-4/` from the running, seeded site so the owner can compare each
 * inner page against its mock (see `docs/feedback/phase-4/README.md`). Requires `wp-env start`
 * + `wp ttm seed --reset` first. `SEEDED_ZONES` is phase 3's fourteen plus three new files
 * (`article-noseries.png`, `archive-business.png`, `footer.png`); `LIVE_ZONES` is written only
 * when `docs/fixtures/live/screens.json` exists, and `live-article-classic.png`'s path is read
 * from that file at capture time (the first screen with `classic: true`) rather than fixed
 * here. `docs/feedback/phase-3/` is history and is left as it was.
 */
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

/**
 * The seeded and live files this script writes, in write order. `path` is the seeded URL
 * (SPEC §1 "Done"); `viewport` is set before navigating; every shot is `fullPage`. The
 * `selector`/`range` forms (one element, or top of the first to bottom of the second at full
 * content width -- see `unionClip`) are kept for ad-hoc crops. `live: true` zones show the
 * owner's public content (SPEC §6.14) and are only written when the live import is present;
 * `live-article-classic.png`'s `path` is `null` here and resolved at capture time.
 *
 * @type {Array<{file: string, path: string|null, viewport: {width: number, height: number}, fullPage?: boolean, live: boolean, selector?: string, range?: [string, string], rangeEdge?: ['first' | 'last', 'first' | 'last']}>}
 */
const DESKTOP = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };
const WIDE = { width: 1920, height: 900 };

const PHASE_3_ZONES = [
	{
		file: 'article.png',
		path: '/signing-your-options-table/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'journal.png',
		path: '/journal-post-1/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'writing.png',
		path: '/writing/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'archive-security.png',
		path: '/category/security/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'series-hub.png',
		path: '/series/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'series-single.png',
		path: '/series/hardening-wordpress/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'search.png',
		path: '/?s=ledger',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: '404.png',
		path: '/this-page-does-not-exist/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'article-390.png',
		path: '/signing-your-options-table/',
		viewport: PHONE,
		fullPage: true,
	},
	{
		file: 'journal-390.png',
		path: '/journal-post-1/',
		viewport: PHONE,
		fullPage: true,
	},
	{
		file: 'writing-390.png',
		path: '/writing/',
		viewport: PHONE,
		fullPage: true,
	},
	{
		file: 'archive-390.png',
		path: '/category/security/',
		viewport: PHONE,
		fullPage: true,
	},
	{ file: 'front-1920.png', path: '/', viewport: WIDE, fullPage: true },
	{
		file: 'article-1920.png',
		path: '/signing-your-options-table/',
		viewport: WIDE,
		fullPage: true,
	},
];

/**
 * The three files new in phase 4 (SPEC §6.14): mock `2b` without series chrome, mock `1e`
 * (a section without a lead), and a crop of the front footer.
 */
const PHASE_4_ZONES = [
	{
		file: 'article-noseries.png',
		path: '/transients-object-caches-and-fast-enough/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'archive-business.png',
		path: '/category/business/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'footer.png',
		path: '/',
		viewport: DESKTOP,
		selector: '.ttm-footer',
	},
];

export const SEEDED_ZONES = [ ...PHASE_3_ZONES, ...PHASE_4_ZONES ].map(
	( zone ) => ( { ...zone, live: false } )
);

/**
 * The seven live files (SPEC §6.14), written only when `docs/fixtures/live/screens.json`
 * exists. `live-article-classic.png`'s `path` is resolved from that file at capture time (see
 * `resolveLiveZones`), not fixed here.
 */
export const LIVE_ZONES = [
	{
		file: 'live-front.png',
		path: '/',
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-article-classic.png',
		path: null,
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-archive-technology.png',
		path: '/category/technology/',
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-writing.png',
		path: '/writing/',
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-series.png',
		path: '/series/',
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-journal.png',
		path: '/category/journal/',
		viewport: DESKTOP,
		fullPage: true,
		live: true,
	},
	{
		file: 'live-front-390.png',
		path: '/',
		viewport: PHONE,
		fullPage: true,
		live: true,
	},
];

export const ZONES = [ ...SEEDED_ZONES, ...LIVE_ZONES ];

const LIVE_SCREENS_PATH = join( 'docs', 'fixtures', 'live', 'screens.json' );

/**
 * The live zones to capture this run: `[]` when the live import hasn't happened
 * (`docs/fixtures/live/screens.json` absent, rule 48), otherwise `LIVE_ZONES` with
 * `live-article-classic.png`'s `path` filled in from the first `classic: true` screen.
 *
 * @return {Array<object>} Live zones to capture, each with a resolved (non-null) `path`.
 */
export function resolveLiveZones() {
	if ( ! existsSync( LIVE_SCREENS_PATH ) ) {
		return [];
	}

	// screens.json's shape (P2-07, PLAN Decision "screens.json shape") is
	// `{generated, host, screens: [...]}`, not a bare array.
	const { screens } = JSON.parse( readFileSync( LIVE_SCREENS_PATH, 'utf8' ) );

	// Before the shortcode pre-pass (P3-01/P3-02) ran, `live-article-classic.png` showed a
	// still-unconverted post; after it, the plan converts every classic post, so no
	// `classic: true` screen exists any more (P3-03). Its purpose becomes "a converted [ref]
	// post with its footnotes intact" instead -- `screens.mjs`'s own `ref-*` ids are exactly
	// that (P2-07's `refPosts` discovery). Falls back to the pre-conversion `classic: true`
	// screen when one still exists (e.g. a partial/failed conversion run).
	const classicScreen =
		screens.find( ( screen ) => screen.classic ) ||
		screens.find( ( screen ) => screen.id.startsWith( 'ref-' ) );

	return LIVE_ZONES.map( ( zone ) =>
		'live-article-classic.png' === zone.file && classicScreen
			? { ...zone, path: classicScreen.path }
			: zone
	);
}

/**
 * The `src`/`currentSrc` of every entry in `list` whose `naturalWidth` is 0 -- an image that
 * never finished loading (REVIEW.md F3: a lazy image below the fold hadn't loaded when
 * `fullPage: true` captured, and the resulting empty figure went uncaught).
 *
 * @param {Array<{src: string, naturalWidth: number}>} list Every `document.images` entry,
 *                                                          already read out of the page.
 * @return {string[]} The `src` of each image that never loaded.
 */
export function pendingImages( list ) {
	return list
		.filter( ( image ) => 0 === image.naturalWidth )
		.map( ( image ) => image.src );
}

/**
 * The bounding box that spans from the top of `a` to the bottom of `b`, at the full content
 * width (the narrower of the two boxes' left edges to the wider of their right edges).
 *
 * @param {{x: number, y: number, width: number, height: number}} a First (top) box.
 * @param {{x: number, y: number, width: number, height: number}} b Second (bottom) box.
 * @return {{x: number, y: number, width: number, height: number}} The union clip.
 */
export function unionClip( a, b ) {
	const left = Math.min( a.x, b.x );
	const right = Math.max( a.x + a.width, b.x + b.width );
	const top = Math.min( a.y, b.y );
	const bottom = Math.max( a.y + a.height, b.y + b.height );

	return { x: left, y: top, width: right - left, height: bottom - top };
}

const OUT_DIR = join( 'docs', 'feedback', 'phase-4' );
const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Run the whole capture: launch chromium, write each zone, close.
 *
 * @return {Promise<void>}
 */
async function run() {
	// Imported lazily so scripts/test/screenshots.test.js can import this module for its pure
	// helpers (ZONES, unionClip) without pulling in @playwright/test (and its own ESM/CJS
	// interop under Jest) just to read a couple of exported constants.
	const { chromium } = await import( '@playwright/test' );

	mkdirSync( OUT_DIR, { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage();

	const zones = [ ...SEEDED_ZONES, ...resolveLiveZones() ];

	for ( const zone of zones ) {
		await page.setViewportSize( zone.viewport );
		await page.goto( BASE_URL + zone.path, { waitUntil: 'networkidle' } );
		await page.evaluate( () => document.fonts.ready );

		// Scroll the full document height in viewport steps so every lazy-loaded image (below
		// the fold at this viewport) starts fetching, then wait for each one to finish (or fail)
		// loading before capturing -- otherwise a `fullPage: true` shot can capture an image
		// that hasn't loaded yet (REVIEW.md F3).
		await page.evaluate( async () => {
			const step = window.innerHeight;
			const total = document.documentElement.scrollHeight;
			for ( let y = 0; y < total; y += step ) {
				window.scrollTo( 0, y );
				await new Promise( ( r ) => setTimeout( r, 50 ) );
			}
			window.scrollTo( 0, 0 );
		} );
		await page.evaluate( () =>
			Promise.all(
				[ ...document.images ].map( ( img ) =>
					img.complete
						? null
						: new Promise( ( r ) => {
								img.onload = r;
								img.onerror = r;
							} )
				)
			)
		);

		const pending = pendingImages(
			await page.evaluate( () =>
				[ ...document.images ].map( ( img ) => ( {
					src: img.currentSrc,
					naturalWidth: img.naturalWidth,
				} ) )
			)
		);
		if ( pending.length > 0 ) {
			console.error(
				`screenshots: image(s) never loaded: ${ pending.join( ', ' ) }`
			);
			await browser.close();
			process.exit( 1 );
		}

		const path = join( OUT_DIR, zone.file );

		if ( zone.fullPage ) {
			await page.screenshot( { path, fullPage: true } );
			console.log( `wrote ${ path }` );
			continue;
		}

		if ( zone.selector ) {
			const locator = page.locator( zone.selector ).first();
			const count = await locator.count();
			if ( 0 === count ) {
				console.error(
					`screenshots: selector not found: ${ zone.selector }`
				);
				await browser.close();
				process.exit( 1 );
			}
			await locator.screenshot( { path } );
			console.log( `wrote ${ path }` );
			continue;
		}

		const [ fromSelector, toSelector ] = zone.range;
		const [ fromEdge, toEdge ] = zone.rangeEdge || [ 'first', 'first' ];
		const from = page.locator( fromSelector )[ fromEdge ]();
		const to = page.locator( toSelector )[ toEdge ]();

		if ( 0 === ( await from.count() ) || 0 === ( await to.count() ) ) {
			console.error(
				`screenshots: selector not found: ${ fromSelector } / ${ toSelector }`
			);
			await browser.close();
			process.exit( 1 );
		}

		const fromBox = await from.boundingBox();
		const toBox = await to.boundingBox();
		const clip = unionClip( fromBox, toBox );

		// `clip` is relative to the viewport, not the full scrollable page, so the viewport
		// has to be at least as tall as the clip region before it's captured.
		await page.setViewportSize( {
			width: zone.viewport.width,
			height: Math.ceil( clip.y + clip.height ) + 20,
		} );
		await page.screenshot( { path, clip } );
		console.log( `wrote ${ path }` );
	}

	await browser.close();
}

// Only run the capture when this file is executed directly (`node scripts/screenshots.mjs` /
// `npm run screenshots`), not when it's imported for its pure helpers (ZONES, unionClip) by
// scripts/test/screenshots.test.js. `process.argv[1]` (not `import.meta.url`, which Jest's
// CommonJS transform of this test's dynamic `import()` can't parse) is this process's entry
// script path.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'screenshots.mjs' ) ) {
	run();
}
