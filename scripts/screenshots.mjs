#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run screenshots` (SPEC §6.6, P0-05): writes the seven-PNG comparison set into
 * `docs/feedback/phase-2/` from the running, seeded site so the owner can compare against
 * `docs/feedback/design_*.png`. Requires `wp-env start` + `npm run env:seed` first.
 */
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

/**
 * The seven files this script writes, in write order. `viewport` is set before navigating;
 * `fullPage` shots capture the whole scrollable page; `selector` clips to one element;
 * `range` is `[fromSelector, toSelector]` -- top of the first to bottom of the second, full
 * content width (see `unionClip`).
 *
 * @type {Array<{file: string, viewport: {width: number, height: number}, fullPage?: boolean, selector?: string, range?: [string, string], rangeEdge?: ['first' | 'last', 'first' | 'last']}>}
 */
export const ZONES = [
	{
		file: 'front-1280.png',
		viewport: { width: 1280, height: 900 },
		fullPage: true,
	},
	{
		file: 'front-390.png',
		viewport: { width: 390, height: 844 },
		fullPage: true,
	},
	{
		file: 'masthead.png',
		viewport: { width: 1280, height: 900 },
		selector: '.ttm-masthead-front',
	},
	{
		file: 'lead-row.png',
		viewport: { width: 1280, height: 900 },
		selector: '.ttm-lead-row',
	},
	{
		file: 'section-rows.png',
		viewport: { width: 1280, height: 900 },
		range: [ '.ttm-section-row', '.ttm-section-row' ],
		rangeEdge: [ 'first', 'last' ],
	},
	{
		file: 'series-strip.png',
		viewport: { width: 1280, height: 900 },
		selector: '.ttm-series-strip',
	},
	{
		file: 'poster-footer.png',
		viewport: { width: 1280, height: 900 },
		range: [ '.ttm-poster', '.ttm-footer' ],
	},
];

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

const OUT_DIR = join( 'docs', 'feedback', 'phase-2' );
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

	for ( const zone of ZONES ) {
		await page.setViewportSize( zone.viewport );
		await page.goto( BASE_URL + '/', { waitUntil: 'networkidle' } );
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
