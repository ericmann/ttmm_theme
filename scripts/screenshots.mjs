#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run screenshots [-- --readme]` (SPEC §6.5/§6.10, P0-08/P3-01, formerly P0-01/P0-12):
 * writes one named set of comparison screenshots from the running, seeded site. Requires
 * `wp-env start` + `wp ttm seed --reset` first. No flag runs `SETS.owner` (this flight's six
 * files into `docs/feedback/phase-5/`, see that directory's own `README.md`); `--readme` runs
 * `SETS.readme` (the eight public README files into `.github/screenshots/`, optimised with
 * `sharp` to stay under `SCREENSHOT_MAX_BYTES`). `docs/feedback/phase-4/` is history and is
 * left as it was. No live set this flight (SPEC §6.10: "Live screenshots are not taken this
 * flight").
 */
import { mkdirSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const DESKTOP = { width: 1280, height: 900 };
const PHONE = { width: 390, height: 844 };

// ⚠️ ASSUMPTION (SPEC §6.5): the byte ceiling scripts/lib/demo-checks.mjs and P3-01's own
// measurement are tuned against. Recorded before/after sizes: see P3-01's commit log.
export const SCREENSHOT_MAX_BYTES = 1500000;

// A README phone shot is clipped to this many pixels of page height (SPEC §6.5) rather than
// capturing the whole (much longer) page -- a reader scanning the README wants a glance at the
// top of the page, not a full scroll.
const README_PHONE_CLIP_HEIGHT = 2200;

/**
 * The six §6.10 files, in write order. `path` is the seeded URL; `viewport` is set before
 * navigating; every shot is `fullPage`. The `selector`/`range` forms (one element, or top of
 * the first to bottom of the second at full content width -- see `unionClip`) are kept for
 * ad-hoc crops a future set may need.
 *
 * @type {Array<{file: string, path: string, viewport: {width: number, height: number}, fullPage?: boolean, selector?: string, range?: [string, string], rangeEdge?: ['first' | 'last', 'first' | 'last']}>}
 */
export const OWNER_ZONES = [
	{ file: 'front.png', path: '/', viewport: DESKTOP, fullPage: true },
	{
		file: 'article.png',
		path: '/signing-your-options-table/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'archive-technology.png',
		path: '/category/technology/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'writing.png',
		path: '/writing/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{ file: 'about.png', path: '/about/', viewport: DESKTOP, fullPage: true },
	{ file: 'front-390.png', path: '/', viewport: PHONE, fullPage: true },
];

/**
 * The eight §6.5 README files, in write order: six 1280×900 full-page desktop shots, then two
 * 390-wide phone shots clipped to `README_PHONE_CLIP_HEIGHT` (a full-page phone shot of these
 * pages runs to many screens; a reader wants the top of the page, not the whole scroll).
 *
 * @type {Array<{file: string, path: string, viewport: {width: number, height: number}, fullPage?: boolean, clipHeight?: number}>}
 */
export const README_ZONES = [
	{ file: 'front-1280.png', path: '/', viewport: DESKTOP, fullPage: true },
	{
		file: 'article-1280.png',
		path: '/signing-your-options-table/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'journal-1280.png',
		path: '/journal-post-1/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'archive-1280.png',
		path: '/category/technology/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'series-hub-1280.png',
		path: '/series/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'writing-1280.png',
		path: '/writing/',
		viewport: DESKTOP,
		fullPage: true,
	},
	{
		file: 'front-390.png',
		path: '/',
		viewport: PHONE,
		clipHeight: README_PHONE_CLIP_HEIGHT,
	},
	{
		file: 'article-390.png',
		path: '/signing-your-options-table/',
		viewport: PHONE,
		clipHeight: README_PHONE_CLIP_HEIGHT,
	},
];

/**
 * Every named capture run: `outDir` to write into, `zones` to capture.
 *
 * @type {Object<string, {outDir: string, zones: Array<object>}>}
 */
export const SETS = {
	owner: {
		outDir: join( 'docs', 'feedback', 'phase-5' ),
		zones: OWNER_ZONES,
	},
	readme: {
		outDir: join( '.github', 'screenshots' ),
		zones: README_ZONES,
	},
};

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

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Capture one zone into `outDir` on the given page (phase 3 REVIEW F3: every `loading="lazy"`
 * image is forced `eager` and waited on before the shot, so a `fullPage: true` capture never
 * catches an image that hasn't loaded yet).
 *
 * @param {import('@playwright/test').Page} page   Playwright page.
 * @param {string}                          outDir Directory to write into.
 * @param {Object}                          zone   One zone from a `SETS` entry's `zones`.
 * @return {Promise<boolean>} `true` on success; `false` if a selector/image never resolved
 *                             (the caller closes the browser and exits non-zero).
 */
async function captureZone( page, outDir, zone ) {
	await page.setViewportSize( zone.viewport );
	await page.goto( BASE_URL + zone.path, { waitUntil: 'networkidle' } );
	await page.evaluate( () => document.fonts.ready );

	await page.evaluate( () => {
		document.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img ) => {
			img.loading = 'eager';
		} );
	} );
	await page.evaluate( () =>
		Promise.all(
			[ ...document.images ].map( ( img ) =>
				img.complete && img.naturalWidth > 0
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
		return false;
	}

	const path = join( outDir, zone.file );

	if ( zone.fullPage ) {
		await page.screenshot( { path, fullPage: true } );
		console.log( `wrote ${ path }` );
		return true;
	}

	if ( zone.clipHeight ) {
		// `clip` only ever captures what the viewport actually renders -- the initial
		// `zone.viewport` height (390×844, a phone screen) is shorter than `clipHeight`, so the
		// viewport has to grow to at least `clipHeight` before the shot, the same technique the
		// `range` branch below uses.
		await page.setViewportSize( {
			width: zone.viewport.width,
			height: zone.clipHeight,
		} );
		await page.screenshot( {
			path,
			clip: {
				x: 0,
				y: 0,
				width: zone.viewport.width,
				height: zone.clipHeight,
			},
		} );
		console.log( `wrote ${ path }` );
		return true;
	}

	if ( zone.selector ) {
		const locator = page.locator( zone.selector ).first();
		if ( 0 === ( await locator.count() ) ) {
			console.error(
				`screenshots: selector not found: ${ zone.selector }`
			);
			return false;
		}
		await locator.screenshot( { path } );
		console.log( `wrote ${ path }` );
		return true;
	}

	const [ fromSelector, toSelector ] = zone.range;
	const [ fromEdge, toEdge ] = zone.rangeEdge || [ 'first', 'first' ];
	const from = page.locator( fromSelector )[ fromEdge ]();
	const to = page.locator( toSelector )[ toEdge ]();

	if ( 0 === ( await from.count() ) || 0 === ( await to.count() ) ) {
		console.error(
			`screenshots: selector not found: ${ fromSelector } / ${ toSelector }`
		);
		return false;
	}

	const fromBox = await from.boundingBox();
	const toBox = await to.boundingBox();
	const clip = unionClip( fromBox, toBox );

	// `clip` is relative to the viewport, not the full scrollable page, so the viewport has to
	// be at least as tall as the clip region before it's captured.
	await page.setViewportSize( {
		width: zone.viewport.width,
		height: Math.ceil( clip.y + clip.height ) + 20,
	} );
	await page.screenshot( { path, clip } );
	console.log( `wrote ${ path }` );
	return true;
}

/**
 * Run the whole capture: launch chromium, write every set, close.
 *
 * @return {Promise<void>}
 */
/**
 * Re-encode one PNG to fit under `SCREENSHOT_MAX_BYTES`: first a plain lossless re-compression,
 * then (only if still over) a palette-reduced version. Overwrites `path` in place.
 *
 * @param {string} path Screenshot file to optimise.
 * @return {Promise<{path: string, before: number, after: number, usedPalette: boolean}|null>}
 *   Measurement, or `null` if it still doesn't fit (the caller fails the run).
 */
async function optimizeScreenshot( path ) {
	// Imported lazily (dynamic import, per Decisions "Q7") so importing this module for its pure
	// helpers never pulls sharp in unless a capture actually runs.
	const sharp = ( await import( 'sharp' ) ).default;

	const before = statSync( path ).size;

	let buffer = await sharp( path )
		.png( { compressionLevel: 9, adaptiveFiltering: true } )
		.toBuffer();
	let usedPalette = false;

	if ( buffer.length > SCREENSHOT_MAX_BYTES ) {
		buffer = await sharp( path )
			.png( { palette: true, quality: 90, compressionLevel: 9 } )
			.toBuffer();
		usedPalette = true;
	}

	if ( buffer.length > SCREENSHOT_MAX_BYTES ) {
		console.error(
			`screenshots: ${ path } is ${ buffer.length } bytes, over SCREENSHOT_MAX_BYTES (${ SCREENSHOT_MAX_BYTES })`
		);
		return null;
	}

	writeFileSync( path, buffer );

	return { path, before, after: buffer.length, usedPalette };
}

/**
 * @param {string[]} args Raw CLI args.
 * @return {'owner'|'readme'} Which `SETS` entry to capture.
 */
export function selectedSet( args ) {
	return args.includes( '--readme' ) ? 'readme' : 'owner';
}

/**
 * @param {string} which Which `SETS` entry to capture (`selectedSet()`'s return value).
 * @return {Promise<void>}
 */
async function run( which ) {
	// Imported lazily so scripts/test/screenshots.test.js can import this module for its pure
	// helpers (SETS, unionClip) without pulling in @playwright/test (and its own ESM/CJS
	// interop under Jest) just to read a couple of exported constants.
	const { chromium } = await import( '@playwright/test' );

	const browser = await chromium.launch();
	const page = await browser.newPage();

	const set = SETS[ which ];
	mkdirSync( set.outDir, { recursive: true } );

	for ( const zone of set.zones ) {
		const ok = await captureZone( page, set.outDir, zone );
		if ( ! ok ) {
			await browser.close();
			process.exit( 1 );
		}
	}

	await browser.close();

	if ( 'readme' === which ) {
		const measurements = [];
		for ( const zone of set.zones ) {
			const measurement = await optimizeScreenshot(
				join( set.outDir, zone.file )
			);
			if ( ! measurement ) {
				process.exit( 1 );
			}
			measurements.push( measurement );
		}
		for ( const { path, before, after, usedPalette } of measurements ) {
			console.log(
				`optimised ${ path }: ${ before } -> ${ after } bytes${
					usedPalette ? ' (palette)' : ''
				}`
			);
		}
	}
}

// Only run the capture when this file is executed directly (`node scripts/screenshots.mjs` /
// `npm run screenshots`), not when it's imported for its pure helpers (SETS, unionClip) by
// scripts/test/screenshots.test.js. `process.argv[1]` (not `import.meta.url`, which Jest's
// CommonJS transform of this test's dynamic `import()` can't parse) is this process's entry
// script path.
if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'screenshots.mjs' ) ) {
	run( selectedSet( process.argv.slice( 2 ) ) );
}
