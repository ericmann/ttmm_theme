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
import { computed, tracks, before } from './lib/style.mjs';

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

test.describe( 'rule', () => {
	test( 'rule-2: main > hr.is-style-rule-2 (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const rule = page.locator( 'main > hr.is-style-rule-2' ).first();
		const container = page.locator( 'main' ).first();
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
	test.fixme( 'lead-row: .ttm-lead-row @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-lead-row' );
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 2 );
		expect( cols[ 0 ] / cols[ 1 ] ).toBeCloseTo( 2, 1 );
		expect( await computed( row, 'column-gap' ) ).toBe( px( 40 ) );
	} );

	test.fixme( 'lead-media: .ttm-lead__media @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const media = page.locator( '.ttm-lead__media' );
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '16 / 9' );
		expect( await computed( media, 'filter' ) ).toContain( 'grayscale(1)' );
	} );

	test.fixme( 'lead-media-phone: .ttm-lead__media @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		const media = page.locator( '.ttm-lead__media' );
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '4 / 3' );
	} );

	test.fixme( 'lead-kicker: .ttm-lead__kicker @1280', async ( { page } ) => {
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

	test.fixme( 'lead-title: .ttm-lead__title @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const title = page.locator( '.ttm-lead__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 44 ) );
		expect( await computed( title, 'line-height' ) ).toBe( px( 46.2 ) );
		expect( await computed( title, 'font-weight' ) ).toBe( '800' );
	} );

	test.fixme( 'lead-title-phone: .ttm-lead__title @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		const title = page.locator( '.ttm-lead__title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 30 ) );
	} );

	test.fixme( 'lead-dek: .ttm-lead__dek @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const dek = page.locator( '.ttm-lead__dek' );
		expect( await computed( dek, 'font-size' ) ).toBe( px( 17 ) );
		expect( await computed( dek, 'color' ) ).toBe( color( 'neutral-800' ) );
	} );

	test.fixme( 'lead-meta: .ttm-lead__meta @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-lead__meta' );
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );
} );

test.describe( 'verse', () => {
	test.fixme( 'verse-box: .ttm-verse @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const box = page.locator( '.ttm-verse' );
		expect( await computed( box, 'background-color' ) ).toBe(
			color( 'surface' )
		);
		expect( await computed( box, 'padding' ) ).toBe( '18px 20px' );
	} );

	test.fixme( 'verse-kicker: .ttm-verse .is-style-kicker @1280', async ( {
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

	test.fixme( 'verse-text: .ttm-verse__text @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const text = page.locator( '.ttm-verse__text' );
		expect( await computed( text, 'font-size' ) ).toBe( px( 19 ) );
		expect( await computed( text, 'font-weight' ) ).toBe( '600' );
	} );

	test.fixme( 'verse-ref: .ttm-verse__reference @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const ref = page.locator( '.ttm-verse__reference' );
		expect( await computed( ref, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( ref, 'color' ) ).toBe( color( 'neutral-800' ) );
		expect( await computed( ref, 'font-weight' ) ).toBe( '400' );
	} );

	test.fixme( 'verse-attr: .ttm-verse__attribution a @1280', async ( {
		page,
	} ) => {
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
	test.fixme( 'rail-head: .ttm-journal-rail .ttm-cell-heading.is-rail @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const head = page.locator(
			'.ttm-journal-rail .ttm-cell-heading.is-rail'
		);
		expect( await computed( head, 'border-bottom-width' ) ).toBe( px( 2 ) );
	} );

	test.fixme( 'rail-head-link: .ttm-journal-rail .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const link = page.locator(
			'.ttm-journal-rail .ttm-cell-heading__link'
		);
		expect( await link.innerText() ).toMatch( /^All \d+ entries$/ );
	} );

	test.fixme( 'rail-entry: .ttm-journal-rail .wp-block-post (first) @1280', async ( {
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

	test.fixme( 'rail-date: .ttm-journal-rail .ttm-journal-excerpt__date @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const date = page.locator(
			'.ttm-journal-rail .ttm-journal-excerpt__date'
		);
		expect( await computed( date, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( date, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
	} );

	test.fixme( 'rail-title: .ttm-journal-rail .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page.locator( '.ttm-journal-rail .wp-block-post-title' );
		expect( await computed( title, 'font-size' ) ).toBe( px( 16 ) );
		expect( await computed( title, 'font-weight' ) ).toBe( '800' );
	} );

	test.fixme( 'rail-excerpt: .ttm-journal-rail .wp-block-post-excerpt__excerpt @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const excerpt = page.locator(
			'.ttm-journal-rail .wp-block-post-excerpt__excerpt'
		);
		expect( await computed( excerpt, 'font-size' ) ).toBe( px( 14 ) );
		expect( await computed( excerpt, 'color' ) ).toBe(
			color( 'neutral-800' )
		);
		const text = await excerpt.innerText();
		expect( text.trim().split( /\s+/ ).length ).toBeLessThanOrEqual( 55 );
	} );

	test.fixme( 'rail-more: .ttm-journal-rail .wp-block-read-more @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const more = page.locator( '.ttm-journal-rail .wp-block-read-more' );
		expect( await computed( more, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( more, 'font-weight' ) ).toBe( '600' );
		expect( await computed( more, 'color' ) ).toBe( color( 'accent-700' ) );
		expect( await computed( more, 'margin-top' ) ).toBe( px( 8 ) );
	} );

	test.fixme( 'rail-count-phone: .ttm-journal-rail .wp-block-post @390', async ( {
		page,
	} ) => {
		await gotoFront( page, 390 );
		expect(
			await page.locator( '.ttm-journal-rail .wp-block-post' ).count()
		).toBe( 2 );
	} );
} );

test.describe( 'section rows', () => {
	test.fixme( 'row-grid: .ttm-section-row (each) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 4 );
		expect( new Set( cols.map( ( n ) => Math.round( n ) ) ).size ).toBe(
			1
		);
	} );

	test.fixme( 'row-grid-tablet: .ttm-section-row (each) @1000', async ( {
		page,
	} ) => {
		await gotoFront( page, 1000 );
		const row = page.locator( '.ttm-section-row' ).first();
		const cols = await tracks( row );
		expect( cols ).toHaveLength( 2 );
	} );

	test.fixme( 'row-gap: .ttm-section-row (each) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		expect( await computed( row, 'column-gap' ) ).toBe( px( 32 ) );
	} );

	test.fixme( 'row-layout: .ttm-section-row (each) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-section-row' ).first();
		const classes = await row.evaluate( ( el ) => el.className );
		expect( classes ).not.toContain( 'is-layout-constrained' );
	} );
} );

test.describe( 'section cells', () => {
	test.fixme( 'cell-pad: .ttm-cell:not(:last-child) (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const cell = page.locator( '.ttm-cell:not(:last-child)' ).first();
		expect( await computed( cell, 'padding' ) ).toBe(
			'20px 32px 24px 0px'
		);
		expect( await computed( cell, 'border-right-width' ) ).toBe( px( 1 ) );
	} );

	test.fixme( 'cell-last: .ttm-section-row .ttm-cell:last-child @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const cell = page.locator( '.ttm-section-row .ttm-cell:last-child' );
		expect( await computed( cell, 'border-right-width' ) ).toBe( px( 0 ) );
	} );

	test.fixme( 'cell-head: .ttm-cell .ttm-cell-heading__label @1280', async ( {
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

	test.fixme( 'cell-head-link: .ttm-cell .ttm-cell-heading__link @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const link = page
			.locator( '.ttm-cell .ttm-cell-heading__link' )
			.first();
		expect( await computed( link, 'font-size' ) ).toBe( px( 11 ) );
		expect( await computed( link, 'color' ) ).toBe(
			color( 'neutral-600' )
		);
	} );

	test.fixme( 'cell-lead: .ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .wp-block-post-title @1280', async ( {
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

	test.fixme( 'cell-lead-dek: .ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .ttm-item__dek @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const dek = page
			.locator(
				'.ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .ttm-item__dek'
			)
			.first();
		expect( await computed( dek, 'font-size' ) ).toBe( px( 13 ) );
		expect( await computed( dek, 'display' ) ).toBe( 'block' );
	} );

	test.fixme( 'cell-item: .ttm-cell:not(.is-style-span-2) .wp-block-post:nth-child(2) .wp-block-post-title @1280', async ( {
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

	test.fixme( 'cell-item-nodek: .ttm-cell .wp-block-post:nth-child(2) .ttm-item__dek @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const dek = page
			.locator( '.ttm-cell .wp-block-post:nth-child(2) .ttm-item__dek' )
			.first();
		expect( await computed( dek, 'display' ) ).toBe( 'none' );
	} );

	test.fixme( 'cell-meta: .ttm-cell .ttm-item__meta (first) @1280', async ( {
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
	test.fixme( 'tech-grid: .ttm-cell.is-style-span-2 .wp-block-post-template @1280', async ( {
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

	test.fixme( 'tech-featured: .ttm-cell.is-style-span-2 .wp-block-post:first-child @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const featured = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post:first-child'
		);
		expect( await computed( featured, 'grid-column' ) ).toBe(
			'span 2 / span 2'
		);
	} );

	test.fixme( 'tech-img: .ttm-cell.is-style-span-2 .ttm-item-featured__media @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const media = page.locator(
			'.ttm-cell.is-style-span-2 .ttm-item-featured__media'
		);
		expect( await computed( media, 'aspect-ratio' ) ).toBe( '3 / 2' );
		expect( await computed( media, 'filter' ) ).toContain( 'grayscale(1)' );
	} );

	test.fixme( 'tech-title: .ttm-cell.is-style-span-2 .wp-block-post:first-child .wp-block-post-title @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const title = page.locator(
			'.ttm-cell.is-style-span-2 .wp-block-post:first-child .wp-block-post-title'
		);
		expect( await computed( title, 'font-size' ) ).toBe( px( 24 ) );
	} );

	test.fixme( 'tech-item: .ttm-cell.is-style-span-2 .wp-block-post:nth-child(2) .wp-block-post-title @1280', async ( {
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
	test.fixme( 'writing-head: .ttm-writing-cell .ttm-cell-heading__label @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const label = page.locator(
			'.ttm-writing-cell .ttm-cell-heading__label'
		);
		expect( await computed( label, 'text-transform' ) ).toBe( 'uppercase' );
		expect( await computed( label, 'font-size' ) ).toBe( px( 12 ) );
	} );

	test.fixme( 'writing-grid: .ttm-writing-cell__body @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const body = page.locator( '.ttm-writing-cell__body' );
		const cols = await tracks( body );
		expect( cols ).toHaveLength( 2 );
		expect( Math.round( cols[ 0 ] ) ).toBe( Math.round( cols[ 1 ] ) );
		expect( await computed( body, 'column-gap' ) ).toBe( px( 28 ) );
	} );

	test.fixme( 'writing-kicker: .ttm-writing-cell__kicker @1280', async ( {
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

	test.fixme( 'writing-headline: .ttm-writing-cell__headline @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const headline = page.locator( '.ttm-writing-cell__headline' );
		expect( await computed( headline, 'font-size' ) ).toBe( px( 26 ) );
		expect( await computed( headline, 'line-height' ) ).toBe( px( 28.6 ) );
	} );

	test.fixme( 'writing-also: .ttm-writing-cell__also @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const also = page.locator( '.ttm-writing-cell__also' );
		expect( await computed( also, 'border-left-width' ) ).toBe( px( 1 ) );
		expect( await computed( also, 'padding-left' ) ).toBe( px( 28 ) );
	} );

	test.fixme( 'writing-btn: .ttm-writing-cell .btn-primary @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const btn = page.locator( '.ttm-writing-cell .btn-primary' );
		expect( await computed( btn, 'background-color' ) ).toBe(
			color( 'accent' )
		);
		expect( await computed( btn, 'color' ) ).toBe( color( 'bg' ) );
	} );
} );

test.describe( 'series strip', () => {
	test.fixme( 'strip-grid: .ttm-series-list.is-strip (or .ttm-series-strip .is-style-grid-3) @1280', async ( {
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

	test.fixme( 'strip-row: .ttm-series-row (first) @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const row = page.locator( '.ttm-series-row' ).first();
		expect( await computed( row, 'border-top-width' ) ).toBe( px( 1 ) );
	} );

	test.fixme( 'strip-mark: .ttm-series-row .ttm-series-mark @1280', async ( {
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

	test.fixme( 'strip-meta: .ttm-series-row__meta @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const meta = page.locator( '.ttm-series-row__meta' ).first();
		expect( await computed( meta, 'font-size' ) ).toBe( px( 12 ) );
		expect( await computed( meta, 'color' ) ).toBe(
			color( 'neutral-700' )
		);
		const text = await meta.innerText();
		expect( text ).toContain( ' · ' );
		expect( text ).toMatch( /\d+ of \d+/ );
	} );
} );

test.describe( 'newsletter poster', () => {
	test.fixme( 'poster: .ttm-poster @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const poster = page.locator( '.ttm-poster' );
		expect( await computed( poster, 'background-color' ) ).toBe(
			color( 'accent' )
		);
		expect( await computed( poster, 'padding' ) ).toBe( '36px 48px 32px' );
	} );

	test.fixme( 'poster-h3: .ttm-poster h3 @1280', async ( { page } ) => {
		await gotoFront( page, 1280 );
		const h3 = page.locator( '.ttm-poster h3' );
		expect( await computed( h3, 'font-size' ) ).toBe( px( 40 ) );
		expect( await computed( h3, 'color' ) ).toBe( color( 'bg' ) );
		expect( await computed( h3, 'text-align' ) ).toBe( 'left' );
	} );

	test.fixme( 'poster-input: .ttm-poster input[type="email"] @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const input = page.locator( '.ttm-poster input[type="email"]' );
		expect( await computed( input, 'width' ) ).toBe( px( 260 ) );
		expect( await computed( input, 'background-color' ) ).toBe(
			color( 'bg' )
		);
	} );

	test.fixme( 'poster-btn: .ttm-poster button, .ttm-poster .btn-ghost @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		const btn = page
			.locator( '.ttm-poster button, .ttm-poster .btn-ghost' )
			.first();
		expect( await computed( btn, 'color' ) ).toBe( color( 'bg' ) );
		expect( await computed( btn, 'border-width' ) ).toBe( px( 1 ) );
		expect( await computed( btn, 'background-color' ) ).toBe(
			'transparent'
		);
	} );

	test.fixme( 'poster-nomailto: .ttm-poster a[href^="mailto:"] @1280', async ( {
		page,
	} ) => {
		await gotoFront( page, 1280 );
		expect(
			await page.locator( '.ttm-poster a[href^="mailto:"]' ).count()
		).toBe( 0 );
	} );

	test.fixme( 'poster-phone: .ttm-poster h3 @390', async ( { page } ) => {
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

test.describe( 'accessibility', () => {
	for ( const width of [ 1280, 390 ] ) {
		test.fixme( `a11y: whole page @${ width }`, async ( { page } ) => {
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
} );

test.describe( 'network', () => {
	for ( const width of [ 1280, 390 ] ) {
		test.fixme( `network: whole page @${ width }`, async ( { page } ) => {
			const requests = [];
			page.on( 'request', ( request ) => requests.push( request.url() ) );

			await gotoFront( page, width );

			const origin = new URL( page.url() ).origin;
			const forbidden = /wp-json|admin-ajax\.php|fonts\.googleapis\.com/;

			for ( const url of requests ) {
				expect( url.startsWith( origin ) ).toBe( true );
				expect( url ).not.toMatch( forbidden );
			}
		} );
	}
} );
