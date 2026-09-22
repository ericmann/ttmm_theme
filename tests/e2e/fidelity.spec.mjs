/**
 * Front-page fidelity suite (SPEC §3.2 rule 38, §6.2). One `test()` per §6.2 table row (rows
 * sharing an id and viewport, e.g. `rule-2` and `lead-row`, are one test with several
 * `expect()`s); every test starts `test.fixme` in Phase 0 and later tasks un-fixme the rows
 * they make pass. The flight ends with zero `fixme` rows here.
 *
 * Test names are `'<id>: <selector> @<viewport>'`. Every test sets the viewport, navigates to
 * the seeded front page, and waits for web fonts before measuring (font metrics affect
 * line-height/measured text sizes).
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { color, px } from './lib/presets.mjs';
import { computed, tracks, before, text, visibleCount } from './lib/style.mjs';
import { SCREENS, SCREEN_URLS, securityFiltered } from './lib/urls.mjs';

/**
 * Navigate to `/` at the given viewport and wait for fonts to be ready.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {number}                          width Viewport width.
 * @return {Promise<void>}
 */
async function gotoFront( page, width ) {
	await page.setViewportSize( { width, height: 900 } );
	await page.goto( '/' );
	await page.evaluate( () => document.fonts.ready );
}

/**
 * Navigate to any seeded screen (P0-04, SPEC §6.9) at the given viewport and wait for web
 * fonts before measuring. `gotoFront` stays the front-page-specific alias above.
 *
 * @param {import('@playwright/test').Page} page  Page.
 * @param {string}                          path  Path to navigate to (e.g. `SCREENS.article`).
 * @param {number}                          width Viewport width.
 * @return {Promise<void>}
 */
async function gotoScreen( page, path, width ) {
	await page.setViewportSize( { width, height: 900 } );
	await page.goto( path );
	await page.evaluate( () => document.fonts.ready );
}

test.describe( 'rule', () => {
	test( 'rule-2: main > hr.is-style-rule-2 (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const rule = page.locator( 'main > hr.is-style-rule-2' ).first();
		const container = page.locator( '.wp-site-blocks' ).first();
		const containerWidth = await container.evaluate(
			( el ) => el.getBoundingClientRect().width
		);
		expect( await computed( rule, 'width' ) ).toBe(
			px( containerWidth - 96 )
		);
		expect( await computed( rule, 'height' ) ).toBe( px( 2 ) );
	} );

	test( 'rule-2-phone: main > hr.is-style-rule-2 (first) @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		const rule = page.locator( 'main > hr.is-style-rule-2' ).first();
		expect( await computed( rule, 'width' ) ).toBe( px( 350 ) );
	} );
} );

test.describe( 'masthead', () => {
	// Flight controller review of P0-09's screenshot: `.ttm-skip` rendered visibly instead of
	// screen-reader-only-until-focus. Not a §6.2 row from the planner; added here once fixed.
	test( 'skip-hidden: .ttm-skip @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const skip = page.locator( '.ttm-skip' );
		const box = await skip.boundingBox();
		expect( box.height ).toBeLessThanOrEqual( 1 );
	} );

	test( 'mast-meta: .ttm-masthead-front__meta @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-masthead-front__meta' );
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );

	test( 'mast-title: .ttm-masthead-front .wp-block-site-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page.locator(
			'.ttm-masthead-front .wp-block-site-title'
		);
		expect( await computed( title, 'font-size' ) ).toBe( px( 76 ) );
	} );

	test( 'mast-byline: .ttm-masthead-front__byline @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const byline = page.locator( '.ttm-masthead-front__byline' );
		expect( await computed( byline, 'margin-top' ) ).toBe( px( 12 ) );
	} );

	test( 'mast-byline-phone: .ttm-masthead-front__byline @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		const byline = page.locator( '.ttm-masthead-front__byline' );
		expect( await computed( byline, 'font-size' ) ).toBe( px( 12 ) );
	} );

	test( 'mast-nav: .ttm-masthead-front__nav a (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const link = page.locator( '.ttm-masthead-front__nav a' ).first();
		expect( await computed( link, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( link, 'font-weight' ) ).toBe( '600' );
	} );

	test( 'mast-nav-gap: .ttm-masthead-front__nav ul @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const list = page.locator( '.ttm-masthead-front__nav ul' );
		expect( await computed( list, 'column-gap' ) ).toBe( px( 28 ) );
	} );

	test( 'mast-current: .ttm-masthead-front__nav .current-menu-item > a @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const current = page.locator(
			'.ttm-masthead-front__nav .current-menu-item > a'
		);
		// Decision "Colour vs a11y": accent fails WCAG AA contrast at this size/weight, so this
		// row uses accent-700 instead of the SPEC table's literal "accent" (see ttm.css).
		expect( await computed( current, 'color' ) ).toBe(
			color( 'accent-700' )
		);
	} );

	test( 'mast-hub: .ttm-masthead-front__nav .ttm-nav__hub > a @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const hub = page.locator(
			'.ttm-masthead-front__nav .ttm-nav__hub > a'
		);
		expect( await computed( hub, 'font-weight' ) ).toBe( '400' );
		expect( await computed( hub, 'color' ) ).toBe( color( 'neutral-700' ) );
		// SPEC §6.2's expected value is "auto (x >= 1000)" -- `margin-left: auto` pushes the
		// element right, and getComputedStyle() always resolves `auto` margins to a used pixel
		// value rather than returning the literal keyword, so the row is checked by position.
		const box = await hub.boundingBox();
		expect( box.x ).toBeGreaterThanOrEqual( 1000 );
	} );
} );

test.describe( 'lead', () => {
	test( 'lead-row: .ttm-lead-row @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-lead-row' );
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 2 );
		expect( cols[ 0 ] / cols[ 1 ] ).toBeCloseTo( 2, 1 );
		expect( await computed( row, 'column-gap' ) ).toBe( px( 40 ) );
	} );

	test( 'lead-media: .ttm-lead__media @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const media = page.locator( '.ttm-lead__media' );
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '16 / 9' );
		expect( await computed( media, 'filter' ) ).toContain( 'grayscale(1)' );
	} );

	test( 'lead-media-phone: .ttm-lead__media @390', async ( { page } ) => {
		await gotoFront( page, 390 );
		const media = page.locator( '.ttm-lead__media' );
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '4 / 3' );
	} );

	test( 'lead-kicker: .ttm-lead__kicker @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const kicker = page.locator( '.ttm-lead__kicker' );
		expect( await computed( kicker, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( kicker, 'text-transform' ) ).toBe(
			'uppercase'
		);
		expect( await computed( kicker, 'color' ) ).toBe(
			color( 'accent-700' )
		);
		expect( await computed( kicker, 'letter-spacing' ) ).toBe( '0.96px' );
	} );

	test( 'lead-title: .ttm-lead__title @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const title = page.locator( '.ttm-lead__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 44 ) );
		expect( await computed( title, 'line-height' ) ).toBe( px( 46.2 ) );
		expect( await computed( title, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'lead-title-phone: .ttm-lead__title @390', async ( { page } ) => {
		await gotoFront( page, 390 );
		const title = page.locator( '.ttm-lead__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 30 ) );
	} );

	test( 'lead-dek: .ttm-lead__dek @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const dek = page.locator( '.ttm-lead__dek' );
		expect( await computed( dek, 'font-size' ) ).toBe( px( 17 ) );
		expect( await computed( dek, 'color' ) ).toBe( color( 'neutral-800' ) );
	} );

	test( 'lead-meta: .ttm-lead__meta @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-lead__meta' );
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );
} );

test.describe( 'verse', () => {
	test( 'verse-box: .ttm-verse @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const box = page.locator( '.ttm-verse' );
		expect( await computed( box, 'background-color' ) ).toBe(
			color( 'surface' )
		);
		expect( await computed( box, 'padding' ) ).toBe( '18px 20px' );
	} );

	test( 'verse-kicker: .ttm-verse .is-style-kicker @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const kicker = page.locator( '.ttm-verse .is-style-kicker' );
		expect( await computed( kicker, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( kicker, 'color' ) ).toBe(
			color( 'accent-700' )
		);
		expect( await computed( kicker, 'text-transform' ) ).toBe(
			'uppercase'
		);
	} );

	test( 'verse-text: .ttm-verse__text @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const verseText = page.locator( '.ttm-verse__text' );
		expect( await computed( verseText, 'font-size' ) ).toBe( px( 19 ) );
		expect( await computed( verseText, 'font-weight' ) ).toBe( '600' );
	} );

	test( 'verse-ref: .ttm-verse__reference @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const ref = page.locator( '.ttm-verse__reference' );
		expect( await computed( ref, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( ref, 'color' ) ).toBe( color( 'neutral-800' ) );
		expect( await computed( ref, 'font-weight' ) ).toBe( '400' );
	} );

	test( 'verse-attr: .ttm-verse__attribution a @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const attr = page.locator( '.ttm-verse__attribution a' );
		expect( await computed( attr, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( attr, 'text-decoration-line' ) ).toBe(
			'underline'
		);
	} );

	test( 'verse-nocopy: .ttm-verse__copyright @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		expect( await page.locator( '.ttm-verse__copyright' ).count() ).toBe(
			0
		);
	} );
} );

test.describe( 'journal rail', () => {
	test( 'rail-head: .ttm-journal-rail .ttm-cell-heading.is-rail @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const head = page.locator(
			'.ttm-journal-rail .ttm-cell-heading.is-rail'
		);
		expect( await computed( head, 'border-bottom-width' ) ).toBe( px( 2 ) );
	} );

	test( 'rail-head-link: .ttm-journal-rail .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const link = page.locator(
			'.ttm-journal-rail .ttm-cell-heading__link'
		);
		expect( await link.innerText() ).toMatch( /^All \d+ entries$/ );
	} );

	test( 'rail-entry: .ttm-journal-rail .wp-block-post (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const entry = page
			.locator( '.ttm-journal-rail .wp-block-post' )
			.first();
		expect( await computed( entry, 'padding-top' ) ).toBe( px( 16 ) );
		expect( await computed( entry, 'border-bottom-width' ) ).toBe(
			px( 1 )
		);
	} );

	test( 'rail-date: .ttm-journal-rail .ttm-journal-excerpt__date @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const date = page
			.locator( '.ttm-journal-rail .ttm-journal-excerpt__date' )
			.first();
		expect( await computed( date, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( date, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );

	test( 'rail-title: .ttm-journal-rail .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page
			.locator( '.ttm-journal-rail .wp-block-post-title' )
			.first();
		expect( await computed( title, 'font-size' ) ).toBe( px( 16 ) );
		expect( await computed( title, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'rail-excerpt: .ttm-journal-rail .wp-block-post-excerpt__excerpt @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const excerpt = page
			.locator( '.ttm-journal-rail .wp-block-post-excerpt__excerpt' )
			.first();
		expect( await computed( excerpt, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( excerpt, 'color' ) ).toBe(
			color( 'neutral-800' )
		);
		const excerptText = await excerpt.innerText();
		expect( excerptText.trim().split( /\s+/ ).length ).toBeLessThanOrEqual(
			55
		);
	} );

	test( 'rail-more: .ttm-journal-rail .wp-block-read-more @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const more = page
			.locator( '.ttm-journal-rail .wp-block-read-more' )
			.first();
		expect( await computed( more, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( more, 'font-weight' ) ).toBe( '600' );
		expect( await computed( more, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( more, 'margin-top' ) ).toBe( px( 8 ) );
	} );

	// Markup can't vary per viewport (the query always fetches 3), so the phone row hides the
	// 3rd entry with CSS (`nth-child(n+3){display:none}`) rather than fetching fewer -- Playwright's
	// plain `.count()` counts DOM nodes regardless of `display:none`, so this needs `:visible`.
	test( 'rail-count-phone: .ttm-journal-rail .wp-block-post @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		expect(
			await page
				.locator( '.ttm-journal-rail .wp-block-post:visible' )
				.count()
		).toBe( 2 );
	} );
} );

test.describe( 'section rows', () => {
	test( 'row-grid: .ttm-section-row (each) @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 4 );
		expect( new Set( cols.map( ( n ) => Math.round( n ) ) ).size ).toBe(
			1
		);
	} );

	test( 'row-grid-tablet: .ttm-section-row (each) @1000', async ( {
		page,
	} ) => {
		await gotoFront( page, 1000 );
		const row = page.locator( '.ttm-section-row' ).first();
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 2 );
	} );

	test( 'row-gap: .ttm-section-row (each) @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		expect( await computed( row, 'column-gap' ) ).toBe( px( 32 ) );
	} );

	test( 'row-layout: .ttm-section-row (each) @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		const classes = await row.evaluate( ( el ) => el.className );
		expect( classes ).not.toContain( 'is-layout-constrained' );
	} );
} );

test.describe( 'section cells', () => {
	test( 'cell-pad: .ttm-cell:not(:last-child) (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const cell = page.locator( '.ttm-cell:not(:last-child)' ).first();
		expect( await computed( cell, 'padding' ) ).toBe(
			'20px 32px 24px 0px'
		);
		expect( await computed( cell, 'border-right-width' ) ).toBe( px( 1 ) );
	} );

	test( 'cell-last: .ttm-section-row .ttm-cell:last-child @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		// An earlier phase gave section-row-2's own Writing cell a `.ttm-cell` last-child too,
		// so this selector now matches once per row; `.first()` targets the row-1 cell as before.
		const cell = page
			.locator( '.ttm-section-row .ttm-cell:last-child' )
			.first();
		expect( await computed( cell, 'border-right-width' ) ).toBe( px( 0 ) );
	} );

	test( 'cell-head: .ttm-cell .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const label = page
			.locator( '.ttm-cell .ttm-cell-heading__label' )
			.first();
		expect( await computed( label, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( label, 'text-transform' ) ).toBe( 'uppercase' );
		expect( await computed( label, 'border-bottom-width' ) ).toBe(
			px( 0 )
		);
	} );

	test( 'cell-head-link: .ttm-cell .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const link = page
			.locator( '.ttm-cell .ttm-cell-heading__link' )
			.first();
		expect( await computed( link, 'font-size' ) ).toBe( px( 11 ) );
		// Decision "Colour vs a11y" names this exact row: neutral-700 replaces the SPEC table's
		// literal neutral-600 (see ttm.css).
		expect( await computed( link, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );

	test( 'cell-lead: .ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page
			.locator(
				'.ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .wp-block-post-title'
			)
			.first();
		expect( await computed( title, 'font-size' ) ).toBe( px( 21 ) );
	} );

	test( 'cell-lead-dek: .ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .ttm-item__dek @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const dek = page
			.locator(
				'.ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .ttm-item__dek'
			)
			.first();
		expect( await computed( dek, 'font-size' ) ).toBe( px( 13 ) );
		// Flight controller note after P3-06: 03 §9's 3-line clamp needs
		// `display: -webkit-box`, which Chrome's computed style serializes as `flow-root`
		// (confirmed live), not the literal `block` this row originally expected.
		expect( await computed( dek, 'display' ) ).toBe( 'flow-root' );
	} );

	test( 'cell-dek-clamp: .ttm-cell .ttm-item__dek @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const dek = page.locator( '.ttm-cell .ttm-item__dek' ).first();
		expect( await computed( dek, '-webkit-line-clamp' ) ).toBe( '3' );
	} );

	test( 'cell-item: .ttm-cell:not(.is-style-span-2) .wp-block-post:nth-child(2) .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page
			.locator(
				'.ttm-cell:not(.is-style-span-2) .wp-block-post:nth-child(2) .wp-block-post-title'
			)
			.first();
		expect( await computed( title, 'font-size' ) ).toBe( px( 15 ) );
		const parent = page
			.locator(
				'.ttm-cell:not(.is-style-span-2) .wp-block-post:nth-child(2)'
			)
			.first();
		expect( await computed( parent, 'border-top-width' ) ).toBe( px( 1 ) );
	} );

	test( 'cell-item-nodek: .ttm-cell .wp-block-post:nth-child(2) .ttm-item__dek @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const dek = page
			.locator( '.ttm-cell .wp-block-post:nth-child(2) .ttm-item__dek' )
			.first();
		expect( await computed( dek, 'display' ) ).toBe( 'none' );
	} );

	test( 'cell-meta: .ttm-cell .ttm-item__meta (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-cell .ttm-item__meta' ).first();
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );
} );

test.describe( 'technology featured cell', () => {
	test( 'tech-grid: .ttm-cell.is-style-span-2 .wp-block-post-template @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const grid = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post-template'
		);
		const cols = await tracks( grid );
		expect( cols ).toHaveLength( 2 );
		expect( Math.round( cols[ 0 ] ) ).toBe( Math.round( cols[ 1 ] ) );
	} );

	test( 'tech-featured: .ttm-cell.is-style-span-2 .wp-block-post:first-child @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const featured = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post:first-child'
		);
		// Chrome's computed `grid-column` for a `span 2` shorthand with no explicit end line is
		// the single value `span 2`, not a two-part `span 2 / span 2` shorthand.
		expect( await computed( featured, 'grid-column' ) ).toBe( 'span 2' );
	} );

	test( 'tech-img: .ttm-cell.is-style-span-2 .ttm-item-featured__media @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const media = page.locator(
			'.ttm-cell.is-style-span-2 .ttm-item-featured__media'
		);
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '3 / 2' );
		expect( await computed( media, 'filter' ) ).toContain( 'grayscale(1)' );

		// REVIEW.md F3: an empty figure (no <img>, or an <img> that never finished loading)
		// passed this row before -- the lazy featured image below the fold hadn't loaded when
		// docs/feedback/phase-2/front-1280.png was captured with fullPage: true.
		const image = media.locator( 'img' );
		await image.scrollIntoViewIfNeeded();
		expect( await image.count() ).toBe( 1 );
		expect(
			await image.evaluate(
				( img ) => img.complete && img.naturalWidth > 0
			)
		).toBe( true );
	} );

	test( 'tech-title: .ttm-cell.is-style-span-2 .wp-block-post:first-child .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post:first-child .wp-block-post-title'
		);
		expect( await computed( title, 'font-size' ) ).toBe( px( 24 ) );
	} );

	test( 'tech-item: .ttm-cell.is-style-span-2 .wp-block-post:nth-child(2) .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post:nth-child(2) .wp-block-post-title'
		);
		expect( await computed( title, 'font-size' ) ).toBe( px( 17 ) );
	} );
} );

test.describe( 'writing cell', () => {
	test( 'writing-head: .ttm-writing-cell .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const label = page.locator(
			'.ttm-writing-cell .ttm-cell-heading__label'
		);
		expect( await computed( label, 'text-transform' ) ).toBe( 'uppercase' );
		expect( await computed( label, 'font-size' ) ).toBe( px( 12 ) );
	} );

	test( 'writing-grid: .ttm-writing-cell__body @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const body = page.locator( '.ttm-writing-cell__body' );
		const cols = await tracks( body );
		expect( cols ).toHaveLength( 2 );
		expect( Math.round( cols[ 0 ] ) ).toBe( Math.round( cols[ 1 ] ) );
		expect( await computed( body, 'column-gap' ) ).toBe( px( 28 ) );
	} );

	test( 'writing-kicker: .ttm-writing-cell__kicker @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const kicker = page.locator( '.ttm-writing-cell__kicker' );
		expect( await computed( kicker, 'color' ) ).toBe(
			color( 'accent-700' )
		);
		expect( await computed( kicker, 'text-transform' ) ).toBe(
			'uppercase'
		);
	} );

	test( 'writing-headline: .ttm-writing-cell__headline @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const headline = page.locator( '.ttm-writing-cell__headline' );
		expect( await computed( headline, 'font-size' ) ).toBe( px( 26 ) );
		expect( await computed( headline, 'line-height' ) ).toBe( px( 28.6 ) );
	} );

	test( 'writing-also: .ttm-writing-cell__also @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const also = page.locator( '.ttm-writing-cell__also' );
		expect( await computed( also, 'border-left-width' ) ).toBe( px( 1 ) );
		expect( await computed( also, 'padding-left' ) ).toBe( px( 28 ) );
	} );

	test( 'writing-btn: .ttm-writing-cell .btn-primary @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const btn = page.locator( '.ttm-writing-cell .btn-primary' );
		// Decision "Colour vs a11y" names this exact row: accent-700 replaces the SPEC table's
		// literal accent (phase 1's P8-07 already renders every .btn-primary this way).
		expect( await computed( btn, 'background-color' ) ).toBe(
			color( 'accent-700' )
		);
		expect( await computed( btn, 'color' ) ).toBe( color( 'bg' ) );
	} );
} );

test.describe( 'series strip', () => {
	test( 'strip-grid: .ttm-series-list.is-strip (or .ttm-series-strip .is-style-grid-3) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const grid = page
			.locator(
				'.ttm-series-list.is-strip, .ttm-series-strip .is-style-grid-3'
			)
			.first();
		const cols = await tracks( grid );
		expect( cols ).toHaveLength( 3 );
		expect( await computed( grid, 'column-gap' ) ).toBe( px( 24 ) );
	} );

	test( 'strip-row: .ttm-series-row (first) @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-series-row' ).first();
		expect( await computed( row, 'border-top-width' ) ).toBe( px( 1 ) );
	} );

	test( 'strip-mark: .ttm-series-row .ttm-series-mark @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const mark = page.locator( '.ttm-series-row .ttm-series-mark' ).first();
		expect( await computed( mark, 'width' ) ).toBe( px( 10 ) );
		expect( await computed( mark, 'height' ) ).toBe( px( 10 ) );
		expect( await computed( mark, 'background-color' ) ).toBe(
			color( 'accent' )
		);
	} );

	test( 'strip-meta: .ttm-series-row__meta @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-series-row__meta' ).first();
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
		const metaText = await meta.innerText();
		expect( metaText ).toContain( ' · ' );
		expect( metaText ).toMatch( /\d+ of \d+/ );
	} );
} );

test.describe( 'newsletter poster', () => {
	test( 'poster: .ttm-poster @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const poster = page.locator( '.ttm-poster' );
		expect( await computed( poster, 'background-color' ) ).toBe(
			color( 'accent' )
		);
		expect( await computed( poster, 'padding' ) ).toBe( '36px 48px 32px' );
	} );

	test( 'poster-h3: .ttm-poster h3 @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const h3 = page.locator( '.ttm-poster h3' );
		expect( await computed( h3, 'font-size' ) ).toBe( px( 40 ) );
		expect( await computed( h3, 'color' ) ).toBe( color( 'bg' ) );
		expect( await computed( h3, 'text-align' ) ).toBe( 'left' );
	} );

	test( 'poster-input: .ttm-poster input[type="email"] @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const input = page.locator( '.ttm-poster input[type="email"]' );
		expect( await computed( input, 'width' ) ).toBe( px( 260 ) );
		expect( await computed( input, 'background-color' ) ).toBe(
			color( 'bg' )
		);
	} );

	test( 'poster-btn: .ttm-poster button, .ttm-poster .btn-ghost @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const btn = page
			.locator( '.ttm-poster button, .ttm-poster .btn-ghost' )
			.first();
		expect( await computed( btn, 'color' ) ).toBe( color( 'bg' ) );
		expect( await computed( btn, 'border-width' ) ).toBe( px( 1 ) );
		// A `background: transparent` declaration's *computed* value is Chrome's canonical
		// `rgba(0, 0, 0, 0)`, never the literal keyword -- same category of getComputedStyle()
		// normalization as the `margin-left: auto` fix in mast-hub (P1-02).
		expect( await computed( btn, 'background-color' ) ).toBe(
			'rgba(0, 0, 0, 0)'
		);
	} );

	test( 'poster-nomailto: .ttm-poster a[href^="mailto:"] @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		expect(
			await page.locator( '.ttm-poster a[href^="mailto:"]' ).count()
		).toBe( 0 );
	} );

	test( 'poster-phone: .ttm-poster h3 @390', async ( { page } ) => {
		await gotoFront( page, 390 );
		const h3 = page.locator( '.ttm-poster h3' );
		expect( await computed( h3, 'font-size' ) ).toBe( px( 28 ) );
	} );
} );

test.describe( 'footer', () => {
	test( 'footer: .ttm-footer @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const footer = page.locator( '.ttm-footer' );
		expect( await computed( footer, 'padding' ) ).toBe( '16px 48px' );
		expect( await computed( footer, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( footer, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
		expect( await computed( footer, 'border-top-width' ) ).toBe( px( 0 ) );
	} );

	test( 'footer-left: .ttm-footer__meta @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-footer__meta' );
		expect( await meta.innerText() ).toMatch(
			/^These Things Matter · © \d{4} Eric Mann · Built on WordPress$/
		);
	} );

	test( 'footer-copy: .ttm-footer__copyright @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const copyright = page.locator( '.ttm-footer__copyright' );
		expect( await copyright.count() ).toBe( 1 );
		expect( await computed( copyright, 'font-size' ) ).toBe( px( 12 ) );
	} );

	test( 'footer-nav: .ttm-footer .wp-block-navigation-item @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		expect(
			await page
				.locator( '.ttm-footer .wp-block-navigation-item' )
				.count()
		).toBe( 9 );
	} );

	test( 'footer-nav-sep: .ttm-footer .wp-block-navigation-item:nth-child(2)::before @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const item = page.locator(
			'.ttm-footer .wp-block-navigation-item:nth-child(2)'
		);
		expect( await before( item, 'content' ) ).toBe( '"·"' );
	} );

	test( 'footer-phone: .ttm-footer @390', async ( { page } ) => {
		await gotoFront( page, 390 );
		const footer = page.locator( '.ttm-footer' );
		expect( await computed( footer, 'flex-direction' ) ).toBe( 'column' );
		expect( await computed( footer, 'font-size' ) ).toBe( px( 11 ) );
	} );
} );

// P0-04: every remaining SPEC §6.9 row, transcribed in table order, tagged as a fixme test.
// Each phase task un-fixmes the rows it owns (Conventions "Row-to-task map").

test.describe( 'container', () => {
	test( 'container-width: .wp-site-blocks @1920', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1920 );
		const el = page.locator( '.wp-site-blocks' );
		expect( await computed( el, 'width' ) ).toBe( px( 1280 ) );
		expect( await computed( el, 'margin-left' ) ).toBe( px( 320 ) );
	} );

	test( 'container-gutter: .wp-site-blocks @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.wp-site-blocks' );
		expect( await computed( el, 'padding-left' ) ).toBe( px( 48 ) );
		const box = await el.evaluate(
			( node ) => node.getBoundingClientRect().width
		);
		const paddingLeft = parseFloat( await computed( el, 'padding-left' ) );
		const paddingRight = parseFloat(
			await computed( el, 'padding-right' )
		);
		expect( box - paddingLeft - paddingRight ).toBeCloseTo( 1184, 0 );
	} );

	test( 'container-phone: .wp-site-blocks @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.wp-site-blocks' );
		expect( await computed( el, 'padding-left' ) ).toBe( px( 20 ) );
	} );

	test( 'container-front: .ttm-lead-row @1920', async ( { page } ) => {
		await gotoScreen( page, '/', 1920 );
		const el = page.locator( '.ttm-lead-row' );
		expect( await computed( el, 'width' ) ).toBe( px( 1184 ) );
	} );
} );

test.describe( 'nav', () => {
	test( 'nav-inline: .ttm-masthead-inner__nav ul @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner__nav ul' );
		expect( await computed( el, 'display' ) ).toBe( 'flex' );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 22 ) );
	} );

	test( 'nav-item: .ttm-masthead-inner__nav a (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner__nav a' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
	} );

	test( 'nav-nochrome: .wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const els = page.locator(
			'.wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close'
		);
		expect( await visibleCount( els ) ).toBe( 0 );
	} );

	test( 'nav-current: .ttm-masthead-inner__nav .current-menu-item > a @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator(
			'.ttm-masthead-inner__nav .current-menu-item > a'
		);
		// Colour vs a11y: accent-700, not SPEC's literal accent (contrast).
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await text( el ) ).toBe( 'Technology' );
	} );

	test( 'nav-hub: .ttm-masthead-inner__nav .ttm-nav__hub > a @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner__nav .ttm-nav__hub > a' );
		expect( await computed( el, 'font-weight' ) ).toBe( '400' );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
	} );

	test( 'nav-hub-current: .ttm-masthead-inner__nav .ttm-nav__hub > a @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-masthead-inner__nav .ttm-nav__hub > a' );
		// Colour vs a11y: accent-700, not SPEC's literal accent.
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'nav-phone-menu: .wp-block-navigation__responsive-container-open @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator(
			'.wp-block-navigation__responsive-container-open'
		);
		expect( await el.isVisible() ).toBe( true );
		expect( await text( el ) ).toBe( 'Menu' );
	} );

	test( 'nav-phone-hidden: .ttm-masthead-inner__nav ul @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-masthead-inner__nav ul' );
		expect( await el.isVisible() ).toBe( false );
	} );
} );

test.describe( 'masthead inner', () => {
	test( 'mast-inner: .ttm-masthead-inner @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner' );
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'padding' ) ).toBe( '14px 0px 12px' );
	} );

	test( 'mast-inner-title: .ttm-masthead-inner .wp-block-site-title @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner .wp-block-site-title' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 22 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'mast-inner-by: .ttm-masthead-inner__by @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-masthead-inner__by' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
	} );

	test( 'mast-inner-phone: .ttm-masthead-inner .wp-block-site-title @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-masthead-inner .wp-block-site-title' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
	} );
} );

test.describe( 'footer inner', () => {
	test( 'footer-inner-rule: .ttm-footer @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-footer' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'padding' ) ).toBe( '14px 48px' );
	} );
} );

test.describe( 'series bar', () => {
	test( 'bar: .ttm-series-bar @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar' );
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( t[ 0 ] ).toBeCloseTo( 10, 0 );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'padding' ) ).toBe( '12px 0px' );
	} );

	test( 'bar-mark: .ttm-series-bar .ttm-series-mark @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar .ttm-series-mark' );
		expect( await computed( el, 'width' ) ).toBe( px( 10 ) );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'accent' )
		);
	} );

	test( 'bar-name: .ttm-series-bar__name @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar__name' );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
	} );

	test( 'bar-seg: .ttm-series-bar__seg (3rd) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar__seg' ).nth( 2 );
		expect( await computed( el, 'width' ) ).toBe( px( 22 ) );
		expect( await computed( el, 'height' ) ).toBe( px( 4 ) );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'accent' )
		);
	} );

	test( 'bar-seg-1: .ttm-series-bar__seg (1st) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar__seg' ).first();
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'neutral-900' )
		);
	} );

	test( 'bar-view: .ttm-series-bar__view @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-bar__view' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( el, 'margin-left' ) ).toBe( px( 14 ) );
	} );

	test( 'bar-phone: .ttm-series-bar__seg (1st) / .ttm-series-bar__view @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const seg = page.locator( '.ttm-series-bar__seg' ).first();
		expect( await computed( seg, 'width' ) ).toBe( px( 12 ) );
		// Markup never varies per viewport (SPEC §3.2): "View series" is hidden by CSS at 390.
		const view = page.locator( '.ttm-series-bar__view' );
		expect( await visibleCount( view ) ).toBe( 0 );
	} );
} );

test.describe( 'article', () => {
	test( 'art-row: .ttm-article @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px 48px' );
	} );

	test( 'art-row-layout: .ttm-article > * @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const children = page.locator( '.ttm-article > *' );
		const count = await children.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await children.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );

	test( 'art-kicker: .ttm-article-head .is-style-kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article-head .is-style-kicker' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( el, 'text-transform' ) ).toBe( 'uppercase' );
		// innerText reflects text-transform; the source text is what the row specifies.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.replace( /\s+/g, ' ' ).trim() ).toBe(
			'Technology · Security'
		);
	} );

	test( 'art-h1: .ttm-article-head h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 56 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 57.12 ) );
		// getComputedStyle resolves ch: measure one "0" in the heading's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 18, 0 );
	} );

	test( 'art-h1-phone: .ttm-article-head h1 @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-article-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 34 ) );
	} );

	test( 'art-dek: .ttm-article-head .is-style-dek-l @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article-head .is-style-dek-l' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 21 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-800' ) );
		// getComputedStyle resolves em lengths: 32em at the dek's 21px.
		expect( await computed( el, 'max-width' ) ).toBe( px( 32 * 21 ) );
	} );

	test( 'art-dek-code: .ttm-article-head .is-style-dek-l code @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article-head .is-style-dek-l code' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'surface' )
		);
	} );

	test( 'art-byline: .ttm-byline @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-byline' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'padding' ) ).toBe( '14px 0px' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
	} );

	test( 'art-byline-author: .ttm-byline .wp-block-post-author-name a @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-byline .wp-block-post-author-name a' );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
	} );

	test( 'art-byline-tags: .ttm-byline .tag (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-byline .tag' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'surface' )
		);
	} );

	test( 'art-hero: .ttm-article .wp-block-post-featured-image @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article .wp-block-post-featured-image' );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 28 ) );
		expect( await computed( el, 'aspect-ratio' ) ).toBe( '16 / 9' );
		expect( await computed( el, 'filter' ) ).toBe( 'none' );
	} );

	test( 'art-hero-phone: .ttm-article .wp-block-post-featured-image @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-article .wp-block-post-featured-image' );
		expect( await computed( el, 'margin-left' ) ).toBe( px( -20 ) );
		expect( await computed( el, 'width' ) ).toBe( px( 390 ) );
	} );

	test( 'art-body: .ttm-entry p (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-entry p' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 29.7 ) );
		// getComputedStyle resolves em lengths: 38em at the 18px body size.
		const parent = page.locator( '.ttm-entry' );
		expect( await computed( parent, 'max-width' ) ).toBe( px( 38 * 18 ) );
	} );

	test( 'art-h2: .ttm-entry h2 (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-entry h2' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 30 ) );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 40 ) );
	} );

	test( 'art-pre: .ttm-entry pre (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-entry pre' ).first();
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'neutral-900' )
		);
		expect( await computed( el, 'border-left-width' ) ).toBe( px( 4 ) );
		expect( await computed( el, 'border-left-color' ) ).toBe(
			color( 'accent' )
		);
		expect( await computed( el, 'padding' ) ).toBe( '18px 20px' );
	} );

	test( 'art-pull: .ttm-entry .is-style-pull @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-entry .is-style-pull' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 28 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( parseFloat( await computed( el, 'text-indent' ) ) ).toBeCloseTo(
			-13.9,
			0
		);
	} );

	test( 'art-link: .ttm-entry p a (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-entry p a' ).first();
		expect( await computed( el, 'text-decoration-line' ) ).toBe(
			'underline'
		);
		expect( await computed( el, 'text-underline-offset' ) ).toBe( px( 3 ) );
	} );
} );

test.describe( 'prev/next', () => {
	test( 'prevnext: .ttm-prevnext @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-prevnext' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( t[ 0 ] ).toBeCloseTo( t[ 1 ], 0 );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 40 ) );
	} );

	test( 'prevnext-prev: .ttm-prevnext__prev @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-prevnext__prev' );
		expect( await computed( el, 'border-right-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'padding' ) ).toBe( '20px 24px 20px 0px' );
	} );

	test( 'prevnext-label: .ttm-prevnext__label (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-prevnext__label' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( el, 'text-transform' ) ).toBe( 'uppercase' );
		// innerText reflects text-transform; the row specifies the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.replace( /\s+/g, ' ' ).trim() ).toBe( '← Part 2' );
	} );

	test( 'prevnext-title: .ttm-prevnext__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-prevnext__title' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'prevnext-phone: .ttm-prevnext @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-prevnext' );
		const t = await tracks( el );
		expect( t.length ).toBe( 1 );
	} );
} );

test.describe( 'aside', () => {
	test( 'aside-sticky: .ttm-article aside @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-article aside' );
		expect( await computed( el, 'position' ) ).toBe( 'sticky' );
		expect( await computed( el, 'top' ) ).toBe( px( 24 ) );
		expect( await computed( el, 'row-gap' ) ).toBe( px( 28 ) );
	} );

	test( 'toc-head: .ttm-series-toc .ttm-cell-heading @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-toc .ttm-cell-heading' );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'padding-bottom' ) ).toBe( px( 8 ) );
	} );

	test( 'toc-item: .ttm-series-toc__item (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-series-toc__item' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '10px 0px' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
	} );

	test( 'toc-current: .ttm-series-toc__item.is-current .ttm-series-toc__title @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator(
			'.ttm-series-toc__item.is-current .ttm-series-toc__title'
		);
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'toc-scheduled: .ttm-series-toc__item.is-scheduled @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		// The seeded series has two scheduled parts; the row's contract holds for each.
		const el = page.locator( '.ttm-series-toc__item.is-scheduled' ).first();
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await el.locator( 'a' ).count() ).toBe( 0 );
		const title = await el.getAttribute( 'title' );
		expect( title ).toMatch( /^Scheduled/ );
	} );

	test( 'toc-phone-hub: .ttm-series-toc__hub @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-series-toc__hub' );
		expect( await el.isVisible() ).toBe( true );
		expect( await text( el ) ).toBe( 'Hub →' );
	} );

	test( 'more-head: .ttm-more-in .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-more-in .ttm-cell-heading__label' );
		// innerText reflects the label's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.trim() ).toBe( 'More in Technology' );
		expect( await el.count() ).toBe( 1 );
	} );

	test( 'more-row: .ttm-more-in .ttm-item a (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-more-in .ttm-item a' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
		const parent = page.locator( '.ttm-more-in .ttm-item' ).first();
		expect( await computed( parent, 'padding' ) ).toBe( '10px 0px' );
	} );

	test( 'more-count: .ttm-more-in .wp-block-post @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const els = page.locator( '.ttm-more-in .wp-block-post' );
		expect( await els.count() ).toBe( 3 );
	} );

	test( 'box: .ttm-newsletter-box @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-newsletter-box' );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'surface' )
		);
		expect( await computed( el, 'padding' ) ).toBe( '16px 18px' );
	} );

	test( 'box-title: .ttm-newsletter-box__title @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-newsletter-box__title' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 15 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( await text( el ) ).toBe( 'Get the next part' );
	} );

	test( 'box-form: .ttm-newsletter-box form @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-newsletter-box form' );
		expect( await computed( el, 'display' ) ).toBe( 'flex' );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 6 ) );
	} );

	test( 'box-btn: .ttm-newsletter-box button @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.ttm-newsletter-box button' );
		// Colour vs a11y: accent-700, not SPEC's literal accent.
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'accent-700' )
		);
		expect( await computed( el, 'color' ) ).toBe( color( 'bg' ) );
	} );

	test( 'box-phone: .ttm-newsletter-box form @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const el = page.locator( '.ttm-newsletter-box form' );
		expect( await computed( el, 'flex-direction' ) ).toBe( 'column' );
	} );

	test( 'aside-phone-order: .ttm-series-toc, .ttm-more-in, .ttm-newsletter-box @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 390 );
		const prevnext = await page.locator( '.ttm-prevnext' ).boundingBox();
		const boxes = [];
		for ( const selector of [
			'.ttm-series-toc',
			'.ttm-more-in',
			'.ttm-newsletter-box',
		] ) {
			boxes.push( await page.locator( selector ).boundingBox() );
		}
		let lastY = prevnext.y;
		for ( const box of boxes ) {
			expect( box.y ).toBeGreaterThan( lastY );
			lastY = box.y;
		}
	} );
} );

test.describe( 'journal', () => {
	test( 'jr-grid: .ttm-journal-head @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head' );
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 48 ) );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px 48px' );
	} );

	test( 'jr-kicker: .ttm-journal-head .is-style-kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head .is-style-kicker' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'jr-date: .is-style-journal-date @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.is-style-journal-date' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 48 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( await computed( el, 'line-height' ) ).toBe( px( 48 ) );
	} );

	test( 'jr-date-phone: .is-style-journal-date @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 390 );
		const el = page.locator( '.is-style-journal-date' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 36 ) );
	} );

	test( 'jr-sub: .ttm-journal-head__subline @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head__subline' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await text( el ) ).toMatch( /^[A-Z][a-z]+day( · .+)?$/ );
	} );

	test( 'jr-h1: .ttm-journal-head h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 30 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 33.6 ) );
	} );

	test( 'jr-body: .ttm-journal-head main @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head main' );
		// getComputedStyle resolves em to px: 36em at the 18px body size.
		expect( await computed( el, 'max-width' ) ).toBe( px( 36 * 18 ) );
	} );

	test( 'jr-body-p: .ttm-journal-head main .entry-content p (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page
			.locator( '.ttm-journal-head main .entry-content p' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
		expect( await computed( el, 'margin-bottom' ) ).toBe( px( 18 ) );
	} );

	test( 'jr-synd: .ttm-syndication @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-syndication' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'padding-top' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 28 ) );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
	} );

	test( 'jr-synd-link: .ttm-syndication a (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-syndication a' ).first();
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'jr-synd-words: .ttm-syndication__words @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-syndication__words' );
		// A flex item's `margin-left: auto` resolves to a used px value; assert its effect:
		// the words sit flush right, clear of the sentence.
		const wrapper = await page.locator( '.ttm-syndication' ).boundingBox();
		const textBox = await page
			.locator( '.ttm-syndication__text' )
			.boundingBox();
		const words = await el.boundingBox();
		expect(
			Math.abs( words.x + words.width - ( wrapper.x + wrapper.width ) )
		).toBeLessThan( 1 );
		expect( words.x ).toBeGreaterThan( textBox.x + textBox.width + 20 );
		expect( await text( el ) ).toMatch( /^\d+ words$/ );
	} );

	test( 'jr-note: .ttm-journal-head__note @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head__note' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 18 ) );
	} );

	test( 'jr-rss: .ttm-journal-head__rss a @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head__rss a' );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await text( el ) ).toBe( 'Journal RSS' );
	} );

	test( 'jr-count-hidden: .ttm-journal-head__count @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head__count' );
		expect( await el.count() ).toBe( 0 );
	} );

	test( 'jr-rule: .ttm-journal-head + hr.is-style-rule-2 @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-head + hr.is-style-rule-2' );
		expect( await computed( el, 'height' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'width' ) ).toBe( px( 1184 ) );
	} );

	test( 'jr-head-layout: .ttm-journal-head descendants @1280 (rule 36)', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const descendants = page.locator( '.ttm-journal-head *' );
		const count = await descendants.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await descendants.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );
} );

test.describe( 'journal stream', () => {
	test( 'js-head: .ttm-journal-stream .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator(
			'.ttm-journal-stream .ttm-cell-heading__label'
		);
		// innerText reflects the label's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.trim() ).toBe( 'Earlier' );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 0 ) );
	} );

	test( 'js-link: .ttm-journal-stream .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator(
			'.ttm-journal-stream .ttm-cell-heading__link'
		);
		expect( await text( el ) ).toMatch( /^Full journal · \d+ entries$/ );
	} );

	test( 'js-row: .ttm-journal-row (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page.locator( '.ttm-journal-row' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'padding' ) ).toBe( '18px 0px' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 1 ) );
	} );

	test( 'js-date: .ttm-journal-row .is-style-journal-stream-date @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page
			.locator( '.ttm-journal-row .is-style-journal-stream-date' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 20 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'js-title: .ttm-journal-row .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page
			.locator( '.ttm-journal-row .wp-block-post-title' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 18 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'js-excerpt: .ttm-journal-row .wp-block-post-excerpt__excerpt @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page
			.locator( '.ttm-journal-row .wp-block-post-excerpt__excerpt' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 15 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-800' ) );
		// getComputedStyle resolves em to px: 40em at the excerpt's 15px.
		expect( await computed( el, 'max-width' ) ).toBe( px( 40 * 15 ) );
	} );

	test( 'js-words: .ttm-journal-row .ttm-journal-row__words @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const el = page
			.locator( '.ttm-journal-row .ttm-journal-row__words' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		// Colour vs a11y: neutral-700, not SPEC's literal neutral-600.
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'justify-self' ) ).toBe( 'end' );
	} );

	test( 'js-count: .ttm-journal-row @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 1280 );
		const els = page.locator( '.ttm-journal-row' );
		expect( await els.count() ).toBe( 4 );
	} );

	test( 'js-phone: .ttm-journal-row (first) @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalPost, 390 );
		const el = page.locator( '.ttm-journal-row' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		const words = page
			.locator( '.ttm-journal-row .ttm-journal-row__words' )
			.first();
		expect( await words.isVisible() ).toBe( false );
	} );
} );

test.describe( 'writing', () => {
	test( 'wr-hero: .ttm-serial-hero @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'align-items' ) ).toBe( 'end' );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px' );
	} );

	test( 'wr-cover: .ttm-serial-hero .ttm-cover @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero .ttm-cover' );
		expect( await computed( el, 'aspect-ratio' ) ).toBe( '2 / 3' );
		expect( await computed( el, 'box-shadow' ) ).not.toBe( 'none' );
		expect( await computed( el, 'filter' ) ).toBe( 'none' );
	} );

	test( 'wr-kicker: .ttm-serial-hero__kicker @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero__kicker' );
		// innerText reflects the kicker's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source ).toBe( 'Writing · Serial in progress' );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'wr-title: .ttm-serial-hero__title @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero__title' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 62.72 ) );
		// getComputedStyle resolves ch to px: measure one "0" in the title's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 14, 0 );
	} );

	test( 'wr-synopsis: .ttm-serial-hero__synopsis @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero__synopsis' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 19 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-800' ) );
		// getComputedStyle resolves ch to px: measure one "0" in the paragraph's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 48, 0 );
		expect( await el.count() ).toBe( 1 );
	} );

	test( 'wr-buttons: .ttm-serial-hero__buttons .btn @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const btns = page.locator( '.ttm-serial-hero__buttons .btn' );
		expect( await btns.count() ).toBe( 3 );
		expect( await btns.nth( 0 ).getAttribute( 'class' ) ).toMatch(
			/btn-primary/
		);
		expect( await btns.nth( 1 ).getAttribute( 'class' ) ).toMatch(
			/btn-secondary/
		);
		expect( await btns.nth( 2 ).getAttribute( 'class' ) ).toMatch(
			/btn-ghost/
		);
	} );

	test( 'wr-stats: .ttm-serial-hero .ttm-stats @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-serial-hero .ttm-stats' );
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'padding-top' ) ).toBe( px( 14 ) );
	} );

	test( 'wr-stat-value: .ttm-stats__value (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-stats__value' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 20 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( await text( el ) ).toMatch( /^\d+ \/ \d+$/ );
	} );

	test( 'wr-stat-cadence: .ttm-stats__value (2nd) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-stats__value' ).nth( 1 );
		expect( await text( el ) ).toBe( 'Monthly' );
	} );

	test( 'wr-body: .ttm-writing-body @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-writing-body' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'padding' ) ).toBe( '28px 0px 48px' );
	} );

	test( 'wr-serials-head: .ttm-writing-body main .ttm-cell-heading (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-writing-body main .ttm-cell-heading' )
			.first();
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 2 ) );
		const link = el.locator( '.ttm-cell-heading__link' );
		expect( await text( link ) ).toBe( 'Newest activity first' );
	} );

	test( 'wr-serial-row: .ttm-series-list.is-list .ttm-series-row (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row' )
			.first();
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'padding' ) ).toBe( '16px 0px' );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
	} );

	test( 'wr-serial-title: .ttm-series-list.is-list .ttm-series-row__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row__title' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 20 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'wr-serial-dek: .ttm-series-list.is-list .ttm-series-row__dek (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row__dek' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		// getComputedStyle resolves ch to px: measure one "0" in the row's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 46, 0 );
	} );

	test( 'wr-serial-form: .ttm-series-list.is-list .ttm-series-row__meta (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row__meta' )
			.first();
		expect( await text( el ) ).toMatch( /^Novel · .+ · monthly$/i );
	} );

	test( 'wr-serial-count: .ttm-series-list.is-list .ttm-series-row__count (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row__count' )
			.first();
		expect( await computed( el, 'text-align' ) ).toBe( 'right' );
		expect( await text( el ) ).toMatch( /^\d+ of \d+/ );
	} );

	test( 'wr-serial-status: .ttm-series-list.is-list .ttm-series-row__status (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-list .ttm-series-row__status' )
			.first();
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
		expect( await text( el ) ).toBe( 'In progress' );
	} );

	test( 'wr-chapters-head: .ttm-series-toc.is-chapters .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator(
			'.ttm-series-toc.is-chapters .ttm-cell-heading__label'
		);
		// text-transform: uppercase means innerText reflects the CSS, not the source.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source ).toBe( 'The Quiet Ledger — recent chapters' );
	} );

	test( 'wr-chapter-row: .ttm-series-toc.is-chapters .ttm-numbered__row (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-toc.is-chapters .ttm-numbered__row' )
			.first();
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'padding' ) ).toBe( '12px 0px' );
	} );

	test( 'wr-chapter-num: .ttm-series-toc.is-chapters .ttm-numbered__num (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-toc.is-chapters .ttm-numbered__num' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 15 ) );
		// Colour vs a11y: neutral-700, not SPEC's literal neutral-500.
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'wr-chapter-title: .ttm-series-toc.is-chapters .ttm-numbered__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page
			.locator( '.ttm-series-toc.is-chapters .ttm-numbered__title' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 17 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'wr-tiles: .ttm-story-tiles @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-story-tiles' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'gap' ) ).toBe( px( 16 ) );
	} );

	test( 'wr-tile: .ttm-tile (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-tile' ).first();
		expect( await computed( el, 'aspect-ratio' ) ).toBe( '4 / 3' );
		expect( await computed( el, 'background-color' ) ).toBe(
			color( 'surface' )
		);
		expect( await computed( el, 'padding' ) ).toBe( px( 16 ) );
		expect( await computed( el, 'justify-content' ) ).toBe(
			'space-between'
		);
	} );

	test( 'wr-tile-title: .ttm-tile__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-tile__title' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 19 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'wr-tile-meta: .ttm-tile__meta (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-tile__meta' ).first();
		expect( await text( el ) ).toMatch( /^[\d,]+ words · \d{4}$/ );
	} );

	test( 'wr-tiles-note: .ttm-story-tiles__note @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-story-tiles__note' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
	} );

	test( 'wr-books: .ttm-book-grid @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-book-grid' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'gap' ) ).toBe( px( 20 ) );
	} );

	test( 'wr-book-cover: .ttm-book .ttm-cover (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-book .ttm-cover' ).first();
		expect( await computed( el, 'aspect-ratio' ) ).toBe( '2 / 3' );
		expect( await computed( el, 'box-shadow' ) ).toBe( 'none' );
	} );

	test( 'wr-book-title: .ttm-book__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const el = page.locator( '.ttm-book__title' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 10 ) );
	} );

	test( 'wr-phone: .ttm-serial-hero / .ttm-serial-hero__title @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 390 );
		const hero = page.locator( '.ttm-serial-hero' );
		const t = await tracks( hero );
		expect( t.length ).toBe( 1 );
		const title = page.locator( '.ttm-serial-hero__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 40 ) );
	} );

	test( 'wr-body-layout: .ttm-writing-body descendants @1280 (rule 36)', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.writing, 1280 );
		const descendants = page.locator( '.ttm-writing-body *' );
		const count = await descendants.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await descendants.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );
} );

test.describe( 'archive', () => {
	test( 'ar-head: .ttm-archive-head @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-head' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px 28px' );
		expect( await computed( el, 'align-items' ) ).toBe( 'end' );
	} );

	test( 'ar-kicker: .ttm-archive-head .is-style-kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-head .is-style-kicker' );
		// innerText reflects the kicker's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.trim() ).toBe( 'Section' );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'ar-h1: .ttm-archive-head h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 80 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 76 ) );
		expect( parseFloat( await computed( el, 'margin-left' ) ) ).toBeCloseTo(
			-4.64,
			0
		);
	} );

	test( 'ar-h1-phone: .ttm-archive-head h1 @390', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 390 );
		const el = page.locator( '.ttm-archive-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 44 ) );
	} );

	test( 'ar-desc: .ttm-archive-head .wp-block-term-description p @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator(
			'.ttm-archive-head .wp-block-term-description p'
		);
		expect( await computed( el, 'font-size' ) ).toBe( px( 17 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-800' ) );
		// getComputedStyle resolves ch: measure one "0" in the paragraph's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 52, 0 );
	} );

	test( 'ar-stats: .ttm-category-stats @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-category-stats' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 20.8 ) );
	} );

	test( 'ar-stats-text: .ttm-category-stats @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-category-stats' );
		const t = await text( el );
		expect( t ).toMatch( /^\d+ articles · \d{4}(–\d{4})?/ );
		expect( t ).toContain( 'Security RSS' );
	} );

	test( 'ar-stats-rss: .ttm-category-stats a @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-category-stats a' );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'ar-filter: .ttm-filter-row @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-filter-row' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'padding' ) ).toBe( '12px 0px' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
	} );

	test( 'ar-filter-all: .ttm-filter-row .tag (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-filter-row .tag' ).first();
		expect( await el.getAttribute( 'class' ) ).toMatch( /tag-accent/ );
		expect( await text( el ) ).toBe( 'All' );
	} );

	test( 'ar-filter-active: .ttm-filter-row .tag-accent @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, securityFiltered, 1280 );
		const el = page.locator( '.ttm-filter-row .tag-accent' );
		expect( await text( el ) ).toBe( 'wordpress' );
	} );

	test( 'ar-filter-sort: .ttm-filter-row__sort @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-filter-row__sort' );
		expect( await text( el ) ).toBe( 'Newest first' );
		// margin-left: auto resolves to a used px value; assert its effect instead: flush right.
		const row = await page.locator( '.ttm-filter-row' ).boundingBox();
		const sort = await el.boundingBox();
		expect(
			Math.abs( sort.x + sort.width - ( row.x + row.width ) )
		).toBeLessThan( 1 );
	} );

	test( 'ar-body: .ttm-archive-body @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-body' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'padding' ) ).toBe( '8px 0px 48px' );
	} );

	test( 'ar-year: .ttm-archive-year (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-year' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
		expect( await computed( el, 'padding' ) ).toBe( '24px 0px 8px' );
	} );

	test( 'ar-year-label: .ttm-archive-year__label (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-year__label' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 32 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
		expect( await computed( el, 'line-height' ) ).toBe( px( 32 ) );
	} );

	test( 'ar-row: .ttm-archive-row (first) @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-row' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '14px 0px' );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
		expect( await el.evaluate( ( node ) => node.tagName ) ).toBe( 'A' );
	} );

	test( 'ar-row-date: .ttm-archive-row__date (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-row__date' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'padding-top' ) ).toBe( px( 4 ) );
	} );

	test( 'ar-row-title: .ttm-archive-row__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-row__title' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 20 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'ar-row-dek: .ttm-archive-row__dek (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-row__dek' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		// getComputedStyle resolves ch to px: 56ch at the dek's 14px.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 56, 0 );
		expect( await computed( el, 'margin-top' ) ).toBe( px( 5 ) );
	} );

	test( 'ar-row-meta: .ttm-archive-row__meta (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-archive-row__meta' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await text( el ) ).toMatch( /^\d+ min( · .+)?$/ );
	} );

	test( 'ar-pagination: .wp-block-query-pagination @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.wp-block-query-pagination' );
		expect( await computed( el, 'display' ) ).toBe( 'flex' );
		expect( await computed( el, 'justify-content' ) ).toBe(
			'space-between'
		);
		expect( await computed( el, 'padding' ) ).toBe( '20px 0px' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
	} );

	test( 'ar-pagination-numbers: .wp-block-query-pagination-numbers @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const els = page.locator( '.wp-block-query-pagination-numbers' );
		expect( await els.count() ).toBe( 0 );
	} );

	test( 'ar-aside-series: .ttm-archive-body aside .ttm-cell-heading__label (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page
			.locator( '.ttm-archive-body aside .ttm-cell-heading__label' )
			.first();
		// innerText reflects the label's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.trim() ).toBe( 'Series in Security' );
	} );

	test( 'ar-aside-row: .ttm-series-list.is-rail .ttm-series-row (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-rail .ttm-series-row' )
			.first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '12px 0px' );
		const title = el.locator( '.ttm-series-row__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 15 ) );
	} );

	test( 'ar-mostread: .ttm-most-read .ttm-numbered__row (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-most-read .ttm-numbered__row' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '600' );
	} );

	test( 'ar-mostread-num: .ttm-most-read .ttm-numbered__num (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const el = page.locator( '.ttm-most-read .ttm-numbered__num' ).first();
		// Colour vs a11y: neutral-700, not SPEC's literal neutral-500.
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'ar-phone-year: .ttm-archive-year (first) @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 390 );
		const el = page.locator( '.ttm-archive-year' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 1 );
		const label = el.locator( '.ttm-archive-year__label' );
		expect( await computed( label, 'font-size' ) ).toBe( px( 24 ) );
	} );

	test( 'ar-tablet-aside: .ttm-archive-body aside @1000', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1000 );
		const el = page.locator( '.ttm-archive-body aside' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( t[ 0 ] ).toBeCloseTo( t[ 1 ], 0 );
	} );

	test( 'ar-body-layout: .ttm-archive-body descendants @1280 (rule 36)', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.securityArchive, 1280 );
		const descendants = page.locator( '.ttm-archive-body *' );
		const count = await descendants.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await descendants.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );
} );

test.describe( 'journal archive', () => {
	test( 'aj-rows: .ttm-journal-row @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalArchive, 1280 );
		const els = page.locator( '.ttm-journal-row' );
		const count = await els.count();
		expect( count ).toBeGreaterThanOrEqual( 9 );
		const t = await tracks( els.first() );
		expect( t.length ).toBe( 3 );
	} );

	test( 'aj-no-filter: .ttm-filter-row @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.journalArchive, 1280 );
		const els = page.locator( '.ttm-filter-row' );
		expect( await els.count() ).toBe( 0 );
	} );
} );

test.describe( 'tag archive', () => {
	test( 'tag-kicker: .ttm-archive-head .is-style-kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.tagArchive, 1280 );
		const el = page.locator( '.ttm-archive-head .is-style-kicker' );
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source.trim() ).toBe( 'Tag' );
	} );

	test( 'tag-aside: .ttm-archive-body aside .ttm-most-read, .ttm-series-list @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.tagArchive, 1280 );
		const mostRead = page.locator(
			'.ttm-archive-body aside .ttm-most-read'
		);
		expect( await mostRead.count() ).toBe( 1 );
		const seriesList = page.locator(
			'.ttm-archive-body aside .ttm-series-list'
		);
		expect( await seriesList.count() ).toBe( 0 );
	} );
} );

test.describe( 'search', () => {
	test( 'search-h1: .ttm-archive-head h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.search, 1280 );
		const h1 = page.locator( '.ttm-archive-head h1' );
		expect( await text( h1 ) ).toBe( 'Search' );
		const summary = page.locator( '.ttm-archive-head__summary' );
		expect( await text( summary ) ).toBe( 'Results for “ledger”' );
	} );

	test( 'search-form: .ttm-archive-head .wp-block-search__input, __button @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.search, 1280 );
		const input = page.locator(
			'.ttm-archive-head .wp-block-search__input'
		);
		expect( await input.getAttribute( 'class' ) ).toMatch( /\binput\b/ );
		expect( await computed( input, 'width' ) ).toBe( px( 320 ) );
		const button = page.locator(
			'.ttm-archive-head .wp-block-search__button'
		);
		expect( await button.getAttribute( 'class' ) ).toMatch(
			/btn-secondary/
		);
	} );

	test( 'search-row-kicker: .ttm-archive-row .is-style-kicker (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.search, 1280 );
		const el = page.locator( '.ttm-archive-row .is-style-kicker' ).first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'search-row-link: .ttm-archive-row (first) is a whole-row anchor, headline first @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.search, 1280 );
		const el = page.locator( '.ttm-archive-row' ).first();
		expect( await el.evaluate( ( node ) => node.tagName ) ).toBe( 'A' );
		expect( await el.getAttribute( 'href' ) ).not.toBe( '' );
		const title = await text(
			page.locator( '.ttm-archive-row__title' ).first()
		);
		const rowText = await text( el );
		expect( rowText.startsWith( title ) ).toBe( true );
	} );
} );

test.describe( '404', () => {
	test( '404-h1: main h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.notFound, 1280 );
		const el = page.locator( 'main h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 80 ) );
		expect( await text( el ) ).toBe( 'Not here.' );
	} );

	test( '404-strip: .ttm-series-strip .ttm-series-row @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.notFound, 1280 );
		const els = page.locator( '.ttm-series-strip .ttm-series-row' );
		expect( await els.count() ).toBe( 3 );
	} );

	test( '404-latest: .ttm-latest .ttm-numbered__row @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.notFound, 1280 );
		const els = page.locator( '.ttm-latest .ttm-numbered__row' );
		expect( await els.count() ).toBe( 4 );
	} );
} );

test.describe( 'page', () => {
	test( 'page-grid: main.is-style-grid-8-4 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.about, 1280 );
		const el = page.locator( 'main.is-style-grid-8-4' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px 48px' );
	} );

	test( 'page-h1: main h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.about, 1280 );
		const el = page.locator( 'main h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 56 ) );
	} );
} );

test.describe( 'hub', () => {
	test( 'hub-head: .ttm-hub-head @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-hub-head' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'padding' ) ).toBe( '40px 0px 28px' );
		expect( await computed( el, 'align-items' ) ).toBe( 'end' );
	} );

	test( 'hub-h1: .ttm-hub-head h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-hub-head h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 80 ) );
		expect( parseFloat( await computed( el, 'margin-left' ) ) ).toBeCloseTo(
			-4.64,
			0
		);
	} );

	test( 'hub-desc: .ttm-hub-head .is-style-dek @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-hub-head .is-style-dek' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 17 ) );
		// getComputedStyle resolves ch to px: measure one "0" in the paragraph's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 52, 0 );
	} );

	test( 'hub-stats: .ttm-series-stats @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-stats' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		const t = await text( el );
		expect( t ).toMatch( /^\d+ series · \d+ in progress/ );
		expect( t ).toContain( 'Spanning' );
	} );

	test( 'hub-featured: .ttm-series-featured @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 64 ) );
		expect( await computed( el, 'padding' ) ).toBe( '28px 0px 36px' );
	} );

	test( 'hub-kicker: .ttm-series-featured__kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__kicker' );
		// innerText reflects the kicker's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source ).toMatch( /^In progress · / );
		expect( await computed( el, 'color' ) ).toBe( color( 'accent-700' ) );
	} );

	test( 'hub-title: .ttm-series-featured__title @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__title' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 40 ) );
		expect( await computed( el, 'line-height' ) ).toBe( px( 40.8 ) );
	} );

	test( 'hub-dek: .ttm-series-featured__dek @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__dek' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 16 ) );
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-800' ) );
	} );

	test( 'hub-progress: .ttm-series-progress__seg @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const els = page.locator( '.ttm-series-progress__seg' );
		const count = await els.count();
		expect( count ).toBeGreaterThan( 0 );
		expect( await computed( els.first(), 'height' ) ).toBe( px( 6 ) );
		expect( await computed( els.first(), 'flex-grow' ) ).toBe( '1' );
	} );

	test( 'hub-progress-color: .ttm-series-progress__seg (1st, last) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const els = page.locator( '.ttm-series-progress__seg' );
		expect( await computed( els.first(), 'background-color' ) ).toBe(
			color( 'neutral-900' )
		);
		expect( await computed( els.last(), 'background-color' ) ).toBe(
			color( 'neutral-300' )
		);
	} );

	test( 'hub-meta: .ttm-series-progress__meta @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-progress__meta' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		expect( await text( el ) ).toMatch(
			/^\d+ of \d+ published( · next part .+)?$/
		);
	} );

	test( 'hub-buttons: .ttm-series-featured__buttons .btn @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const btns = page.locator( '.ttm-series-featured__buttons .btn' );
		expect( await btns.count() ).toBe( 2 );
		expect( await text( btns.first() ) ).toBe( 'Start at part 1' );
		expect( await text( btns.last() ) ).toBe( 'Follow this series' );
	} );

	test( 'hub-parts: .ttm-series-featured__parts @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__parts' );
		expect( await el.evaluate( ( node ) => node.tagName ) ).toBe( 'OL' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 2 ) );
	} );

	test( 'hub-part: .ttm-series-featured__part (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__part' ).first();
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'padding' ) ).toBe( '12px 0px' );
		expect( await computed( el, 'border-bottom-width' ) ).toBe( px( 1 ) );
		expect( await computed( el, 'font-size' ) ).toBe( px( 16 ) );
	} );

	test( 'hub-part-num: .ttm-series-featured__num (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-featured__num' ).first();
		// Colour vs a11y: neutral-700, not SPEC's literal neutral-500.
		expect( await computed( el, 'color' ) ).toBe( color( 'neutral-700' ) );
		expect( await computed( el, 'font-weight' ) ).toBe( '800' );
	} );

	test( 'hub-part-scheduled: .ttm-series-featured__part.is-scheduled (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page
			.locator( '.ttm-series-featured__part.is-scheduled' )
			.first();
		const title = el.locator( '.ttm-series-featured__part-title' );
		expect( await computed( title, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
		expect( await title.locator( 'a' ).count() ).toBe( 0 );
		const date = el.locator( '.ttm-series-featured__date' );
		expect( await text( date ) ).toMatch(
			/^Sept? \d+|^[A-Z][a-z]{2,3} \d+/
		);
	} );

	test( 'hub-all-head: .ttm-hub-all .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-hub-all .ttm-cell-heading__link' );
		expect( await text( el ) ).toBe( 'Sorted by last update' );
	} );

	test( 'hub-grid: .ttm-series-list.is-grid-2 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page.locator( '.ttm-series-list.is-grid-2' );
		const t = await tracks( el );
		expect( t.length ).toBe( 2 );
		expect( t[ 0 ] ).toBeCloseTo( t[ 1 ], 0 );
		expect( await computed( el, 'column-gap' ) ).toBe( px( 48 ) );
	} );

	test( 'hub-grid-row: .ttm-series-list.is-grid-2 .ttm-series-row (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-grid-2 .ttm-series-row' )
			.first();
		const t = await tracks( el );
		expect( t.length ).toBe( 3 );
		expect( await computed( el, 'padding' ) ).toBe( '18px 0px' );
		expect( await computed( el, 'border-top-width' ) ).toBe( px( 1 ) );
	} );

	test( 'hub-grid-title: .ttm-series-list.is-grid-2 .ttm-series-row__title (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-grid-2 .ttm-series-row__title' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 20 ) );
	} );

	test( 'hub-grid-cats: .ttm-series-list.is-grid-2 .ttm-series-row__categories (first) @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const el = page
			.locator( '.ttm-series-list.is-grid-2 .ttm-series-row__categories' )
			.first();
		expect( await computed( el, 'font-size' ) ).toBe( px( 12 ) );
		const t = await text( el );
		expect( t.includes( ' · ' ) || t.length > 0 ).toBe( true );
	} );

	test( 'hub-nobox: .ttm-newsletter-box @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const els = page.locator( '.ttm-newsletter-box' );
		expect( await els.count() ).toBe( 0 );
	} );

	test( 'hub-phone: .ttm-series-featured / .ttm-hub-head h1 @390', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 390 );
		const featured = page.locator( '.ttm-series-featured' );
		const t = await tracks( featured );
		expect( t.length ).toBe( 1 );
		const h1 = page.locator( '.ttm-hub-head h1' );
		expect( await computed( h1, 'font-size' ) ).toBe( px( 44 ) );
	} );

	test( 'hub-head-layout: .ttm-hub-head descendants @1280 (rule 36)', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const descendants = page.locator( '.ttm-hub-head *' );
		const count = await descendants.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await descendants.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );

	test( 'hub-featured-layout: .ttm-series-featured descendants @1280 (rule 36)', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHub, 1280 );
		const descendants = page.locator( '.ttm-series-featured *' );
		const count = await descendants.count();
		for ( let i = 0; i < count; i++ ) {
			const cls =
				( await descendants.nth( i ).getAttribute( 'class' ) ) || '';
			expect( cls ).not.toMatch( /is-layout-constrained/ );
		}
	} );
} );

test.describe( 'single series', () => {
	test( 'single-head: .ttm-series-single h1 @1280', async ( { page } ) => {
		await gotoScreen( page, SCREENS.seriesHardening, 1280 );
		const el = page.locator( '.ttm-series-single h1' );
		expect( await computed( el, 'font-size' ) ).toBe( px( 80 ) );
		// getComputedStyle resolves ch to px: measure one "0" in the heading's font.
		const chs = await el.evaluate( ( node ) => {
			const cs = window.getComputedStyle( node );
			const probe = document.createElement( 'span' );
			probe.textContent = '0';
			probe.style.font = cs.font;
			probe.style.letterSpacing = '0';
			probe.style.position = 'absolute';
			probe.style.visibility = 'hidden';
			document.body.appendChild( probe );
			const ch = probe.getBoundingClientRect().width;
			probe.remove();
			return parseFloat( cs.maxWidth ) / ch;
		} );
		expect( chs ).toBeCloseTo( 16, 0 );
	} );

	test( 'single-kicker: .ttm-series-single .ttm-series-featured__kicker @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHardening, 1280 );
		const el = page.locator(
			'.ttm-series-single .ttm-series-featured__kicker'
		);
		// innerText reflects the kicker's text-transform; assert the source text.
		const source = await el.evaluate( ( node ) => node.textContent );
		expect( source ).toMatch( /^In progress · Technology · Security$/ );
	} );

	test( 'single-parts: .ttm-series-single .ttm-series-featured__part @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHardening, 1280 );
		const els = page.locator(
			'.ttm-series-single .ttm-series-featured__part'
		);
		const count = await els.count();
		expect( count ).toBeGreaterThan( 0 );
		expect( await computed( els.first(), 'font-size' ) ).toBe( px( 18 ) );
		const deks = page.locator(
			'.ttm-series-single .ttm-series-featured__part-dek'
		);
		expect( await deks.count() ).toBeGreaterThan( 0 );
	} );

	test( 'single-other: .ttm-series-single__other .ttm-series-row @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHardening, 1280 );
		const els = page.locator( '.ttm-series-single__other .ttm-series-row' );
		const count = await els.count();
		expect( count ).toBeLessThanOrEqual( 4 );
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'single-nav: .ttm-masthead-inner__nav .current-menu-item > a @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.seriesHardening, 1280 );
		const el = page.locator(
			'.ttm-masthead-inner__nav .current-menu-item > a'
		);
		expect( await text( el ) ).toBe( 'Series' );
	} );
} );

test.describe( 'seed', () => {
	test( 'seed-hero-color: .wp-block-post-featured-image img @1280', async ( {
		page,
	} ) => {
		await gotoScreen( page, SCREENS.article, 1280 );
		const el = page.locator( '.wp-block-post-featured-image img' );
		const distance = await el.evaluate( ( img ) => {
			const canvas = document.createElement( 'canvas' );
			canvas.width = img.naturalWidth;
			canvas.height = img.naturalHeight;
			const ctx = canvas.getContext( '2d' );
			ctx.drawImage( img, 0, 0 );
			const [ r, g, b ] = ctx.getImageData(
				Math.floor( canvas.width / 2 ),
				Math.floor( canvas.height / 2 ),
				1,
				1
			).data;
			const dr = r - 210;
			const dg = g - 48;
			const db = b - 19;
			return Math.sqrt( dr * dr + dg * dg + db * db );
		} );
		expect( distance ).toBeGreaterThanOrEqual( 40 );
	} );
} );

test.describe( 'accessibility', () => {
	for ( const width of [ 1280, 390 ] ) {
		test( `a11y: whole page @${ width }`, async ( { page } ) => {
			await gotoFront( page, width );
			const results = await new AxeBuilder( { page } )
				.exclude( '.ttm-poster .btn-ghost' )
				.analyze();
			const serious = results.violations.filter( ( v ) =>
				[ 'serious', 'critical' ].includes( v.impact )
			);
			expect( serious ).toEqual( [] );
		} );
	}

	// P0-04: every other seeded screen, same check (rule 33).
	for ( const path of SCREEN_URLS ) {
		if ( path === '/' ) {
			continue;
		}
		for ( const width of [ 1280, 390 ] ) {
			test( `a11y: ${ path } @${ width }`, async ( { page } ) => {
				await gotoScreen( page, path, width );
				const results = await new AxeBuilder( { page } )
					.exclude( '.ttm-poster .btn-ghost' )
					.analyze();
				const serious = results.violations.filter( ( v ) =>
					[ 'serious', 'critical' ].includes( v.impact )
				);
				expect( serious ).toEqual( [] );
			} );
		}
	}
} );

test.describe( 'network', () => {
	for ( const width of [ 1280, 390 ] ) {
		test( `network: whole page @${ width }`, async ( { page } ) => {
			const requests = [];
			page.on( 'request', ( request ) => requests.push( request.url() ) );

			await gotoFront( page, width );

			const origin = new URL( page.url() ).origin;
			const forbidden = /wp-json|admin-ajax\.php|fonts\.googleapis\.com/;

			for ( const url of requests ) {
				// `blob:`/`data:` never leave the browser -- a `blob:` URL is how Chrome
				// represents an already-fetched resource it re-serves internally (observed for
				// the page's own favicon), not a new network request (see network.spec.mjs).
				if ( url.startsWith( 'data:' ) || url.startsWith( 'blob:' ) ) {
					continue;
				}
				expect( url.startsWith( origin ) ).toBe( true );
				expect( url ).not.toMatch( forbidden );
			}
		} );
	}

	// P0-04: every other seeded screen, same check (rule 6).
	for ( const path of SCREEN_URLS ) {
		if ( path === '/' ) {
			continue;
		}
		for ( const width of [ 1280, 390 ] ) {
			test( `network: ${ path } @${ width }`, async ( { page } ) => {
				const requests = [];
				page.on( 'request', ( request ) =>
					requests.push( request.url() )
				);

				await gotoScreen( page, path, width );

				const origin = new URL( page.url() ).origin;
				const forbidden =
					/wp-json|admin-ajax\.php|fonts\.googleapis\.com/;

				for ( const url of requests ) {
					if (
						url.startsWith( 'data:' ) ||
						url.startsWith( 'blob:' )
					) {
						continue;
					}
					expect( url.startsWith( origin ) ).toBe( true );
					expect( url ).not.toMatch( forbidden );
				}
			} );
		}
	}
} );
