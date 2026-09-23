/**
 * Live check suite (SPEC §6.10, P4-01): every `docs/fixtures/live/screens.json` entry, at 1280
 * and 390, against the theme's inner-page contract. Skips cleanly (rule 48) when the live
 * import hasn't happened -- `docs/fixtures/live/screens.json` doesn't exist yet.
 *
 * `npm run test:live` (`scripts/live/test-live.sh`) already guards this at the shell level
 * before Playwright ever runs; the `test.skip()` below is a second, file-level guard for
 * anyone invoking `npx playwright test --project live` directly.
 *
 * Every check uses `expect.soft()` so one run reports every failing class on a screen, not
 * just the first (SPEC §6.10: "Every failure class is triaged... into LIVE-TRIAGE.md").
 */

import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { collectCssClasses } from '../../scripts/lib/css-coverage.mjs';
import {
	classifyRequests,
	debugLogLineCount,
	phpErrorMarkers,
} from './lib/live.mjs';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const ROOT = join( __dirname, '..', '..' );
const SCREENS_PATH = join( ROOT, 'docs', 'fixtures', 'live', 'screens.json' );

const hasScreens = existsSync( SCREENS_PATH );

test.skip( ! hasScreens, 'skipped: no live import' );

const manifest = hasScreens
	? JSON.parse( readFileSync( SCREENS_PATH, 'utf8' ) )
	: { screens: [] };

const CSS_FILES = [
	'themes/ttm-theme/assets/css/ttm.css',
	'themes/ttm-theme/style.css',
];

/**
 * Every `ttm-*` class any theme stylesheet defines a selector for.
 *
 * @return {Set<string>} Known classes.
 */
function knownCssClasses() {
	const cssText = CSS_FILES.map( ( relative ) =>
		readFileSync( join( ROOT, relative ), 'utf8' )
	).join( '\n' );
	return collectCssClasses( cssText );
}

const KNOWN_CSS_CLASSES = hasScreens ? knownCssClasses() : new Set();

const SHORTCODE_RESIDUE = /\[(ref|cci|cc|mfn|audio|caption)\b/;
const VIEWPORTS = [
	{ width: 1280, height: 900 },
	{ width: 390, height: 844 },
];
const SERIOUS_IMPACTS = [ 'serious', 'critical' ];
// Jetpack's own front-end assets/beacons (matches tests/e2e/specs/network.spec.mjs) -- never
// requests this repo's own templates/blocks make, but tolerated when Jetpack happens to be
// active on the live import.
const ALLOWED_CROSS_ORIGIN_HOST =
	/^(?:stats\.wp\.com|s0\.wp\.com|jetpack\.com|[a-z0-9-]+\.wordpress\.com)$/i;

let startDebugLogLines = 0;

test.beforeAll( () => {
	if ( hasScreens ) {
		startDebugLogLines = debugLogLineCount();
	}
} );

for ( const screen of manifest.screens ) {
	for ( const viewport of VIEWPORTS ) {
		test( `${ screen.id }: ${ screen.path } @${ viewport.width }`, async ( {
			page,
			baseURL,
		} ) => {
			await page.setViewportSize( viewport );

			const baseHost = new URL( baseURL ).host;
			const requests = [];
			page.on( 'request', ( request ) => {
				requests.push( {
					url: request.url(),
					type: request.resourceType(),
				} );
			} );

			const response = await page.goto( screen.path );
			await page.waitForLoadState( 'networkidle' );

			expect
				.soft( response.status(), 'HTTP status' )
				.toBe( screen.expectStatus );

			const html = await page.content();
			const bodyText = await page.locator( 'body' ).innerText();

			const errorMarkers = phpErrorMarkers( bodyText );
			expect
				.soft( errorMarkers, 'no PHP error text in the body' )
				.toEqual( [] );

			expect
				.soft( bodyText, 'no unconverted shortcode text' )
				.not.toMatch( SHORTCODE_RESIDUE );
			expect
				.soft( html.includes( '&lt;p&gt;' ), 'no literal &lt;p&gt;' )
				.toBe( false );

			// axe (same exclusion as tests/e2e/specs/screens.spec.mjs: the poster ghost button
			// is SPEC's one sanctioned a11y exception).
			const axeResults = await new AxeBuilder( { page } )
				.exclude( '.ttm-poster .btn-ghost' )
				.analyze();
			const blocking = axeResults.violations.filter( ( violation ) =>
				SERIOUS_IMPACTS.includes( violation.impact )
			);
			test.info().attach( 'axe-violations.json', {
				body: JSON.stringify( axeResults.violations, null, 2 ),
				contentType: 'application/json',
			} );
			expect
				.soft( blocking, 'zero serious/critical axe violations' )
				.toEqual( [] );

			// Network: no cross-origin script/stylesheet/XHR/font; cross-origin images are
			// counted per host, not failed.
			const { offenders, imagesByHost } = classifyRequests(
				requests,
				baseHost,
				ALLOWED_CROSS_ORIGIN_HOST
			);
			if ( Object.keys( imagesByHost ).length > 0 ) {
				test.info().attach( 'cross-origin-images-by-host.json', {
					body: JSON.stringify( imagesByHost, null, 2 ),
					contentType: 'application/json',
				} );
			}
			expect
				.soft( offenders, 'no cross-origin script/stylesheet/XHR/font' )
				.toEqual( [] );

			// Every `ttm-*` class present in the DOM has a rule in ttm.css/style.css.
			const domClasses = await page.evaluate( () => {
				const found = new Set();
				document.querySelectorAll( '[class]' ).forEach( ( el ) => {
					el.classList.forEach( ( className ) => {
						if ( className.startsWith( 'ttm-' ) ) {
							found.add( className );
						}
					} );
				} );
				return [ ...found ];
			} );
			const uncovered = domClasses.filter(
				( className ) => ! KNOWN_CSS_CLASSES.has( className )
			);
			expect
				.soft( uncovered, 'every ttm-* class has a CSS rule' )
				.toEqual( [] );

			// No [data-ttm-block] wrapper without text or an <img>.
			const emptyBlocks = await page.evaluate( () => {
				return [ ...document.querySelectorAll( '[data-ttm-block]' ) ]
					.filter(
						( el ) =>
							'' === el.textContent.trim() &&
							! el.querySelector( 'img' )
					)
					.map( ( el ) => el.className );
			} );
			expect
				.soft( emptyBlocks, 'no empty [data-ttm-block] wrapper' )
				.toEqual( [] );

			// Masthead current item equals the screen's section, when it has one.
			if ( screen.section ) {
				const current = page
					.locator(
						':is(.ttm-masthead-front__nav, .ttm-masthead-inner__nav) .current-menu-item > a'
					)
					.first();
				if ( await current.count() ) {
					const currentText = ( await current.innerText() )
						.trim()
						.toLowerCase();
					expect
						.soft(
							currentText,
							'masthead current item matches the section'
						)
						.toBe( screen.section.toLowerCase() );
				}
			}

			// Footer present, 8 nav items.
			await expect
				.soft( page.locator( '.ttm-footer' ), 'footer present' )
				.toBeVisible();
			await expect
				.soft(
					page.locator(
						'.ttm-footer__nav .wp-block-navigation-item'
					),
					'footer has 8 nav items'
				)
				.toHaveCount( 8 );

			const title = await page.title();
			expect.soft( title, 'non-empty <title>' ).not.toBe( '' );
			await expect
				.soft( page.locator( 'h1' ), 'exactly one <h1>' )
				.toHaveCount( 1 );

			if ( 'single' === screen.kind ) {
				const kicker = page
					.locator(
						':is(.ttm-article-head, .ttm-journal-head) .is-style-kicker'
					)
					.first();
				if ( screen.section && ( await kicker.count() ) ) {
					const kickerText = (
						await kicker.evaluate( ( el ) => el.textContent )
					).trim();
					expect
						.soft(
							kickerText.toLowerCase(),
							'kicker matches the primary category'
						)
						.toBe( screen.section.toLowerCase() );
				}

				if ( screen.date ) {
					const byline = page.locator( '.ttm-byline' ).first();
					if ( await byline.count() ) {
						const bylineText = await byline.innerText();
						const expectedDay = screen.date.slice( 0, 10 );
						// The byline renders a formatted date (e.g. "March 5, 2014"), not the
						// ISO string, so this checks the day actually made it into the byline's
						// own <time datetime> attribute rather than string-matching prose.
						const datetime = await page
							.locator( '.ttm-byline time' )
							.first()
							.getAttribute( 'datetime' );
						expect
							.soft(
								( datetime || '' ).slice( 0, 10 ),
								`byline date matches the manifest (${ bylineText })`
							)
							.toBe( expectedDay );
					}
				}

				const entry = page.locator( '.ttm-entry' );
				if ( await entry.count() ) {
					const entryText = await entry.innerText();
					expect
						.soft( entryText.trim(), '.ttm-entry is non-empty' )
						.not.toBe( '' );
				}

				const freeformCount = await page
					.locator( '.wp-block-freeform' )
					.count();
				if ( ! screen.freeform ) {
					expect
						.soft(
							freeformCount,
							'no .wp-block-freeform unless the manifest says freeform'
						)
						.toBe( 0 );
				}
			}

			if ( 'archive' === screen.kind ) {
				const yearLabels = await page
					.locator( '.ttm-archive-year__label' )
					.allInnerTexts();
				const years = yearLabels
					.map( ( label ) => parseInt( label, 10 ) )
					.filter( ( year ) => ! Number.isNaN( year ) );
				const sorted = [ ...years ].sort( ( a, b ) => b - a );
				expect
					.soft( years, 'year groups strictly descending' )
					.toEqual( sorted );

				const rowChecks = await page
					.locator( '.ttm-archive-row' )
					.evaluateAll( ( rows ) =>
						rows.map( ( row ) => {
							const isAnchor = 'A' === row.tagName;
							const titleEl = row.querySelector(
								'.ttm-archive-row__title'
							);
							const titleText = titleEl
								? titleEl.textContent.trim()
								: '';
							const rowText = row.textContent.trim();
							return {
								isAnchor,
								startsWithTitle:
									'' !== titleText &&
									rowText.startsWith( titleText ),
							};
						} )
					);
				expect
					.soft(
						rowChecks.every( ( row ) => row.isAnchor ),
						'every archive row is a single <a>'
					)
					.toBe( true );
				expect
					.soft(
						rowChecks.every( ( row ) => row.startsWithTitle ),
						"every archive row's text starts with its title"
					)
					.toBe( true );

				for ( const direction of [ 'previous', 'next' ] ) {
					const el = page
						.locator( `.wp-block-query-pagination-${ direction }` )
						.first();
					if ( await el.count() ) {
						const info = await el.evaluate( ( node ) => ( {
							tag: node.tagName,
							disabled: node.classList.contains( 'is-disabled' ),
						} ) );
						expect
							.soft(
								'A' === info.tag || info.disabled,
								`pagination ${ direction } is a link or a disabled span`
							)
							.toBe( true );
					}
				}
			}
		} );
	}
}

test( 'wp-content/debug.log gained no lines during the run', () => {
	test.skip( ! hasScreens, 'skipped: no live import' );
	const endLines = debugLogLineCount();
	expect( endLines, 'debug.log line count unchanged' ).toBe(
		startDebugLogLines
	);
} );
