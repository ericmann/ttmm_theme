/**
 * Layout facts that only make sense at the phone viewport (SPEC §6.1.9, §6.4, 02 §A
 * Responsive) and aren't already covered by a `tests/e2e/fidelity.spec.mjs` row. Runs only in
 * the `phone` project (390x844; see `../playwright.config.mjs`), against the seeded site.
 */
import { test, expect } from '@playwright/test';

test( 'front page has no horizontal overflow at 390', async ( { page } ) => {
	await page.goto( '/' );
	const scrollWidth = await page.evaluate(
		() => document.documentElement.scrollWidth
	);
	expect( scrollWidth ).toBeLessThanOrEqual( 390 );
} );

test( 'journal post and archive have no horizontal overflow at 390', async ( {
	page,
} ) => {
	for ( const path of [ '/journal-post-1/', '/category/journal/' ] ) {
		await page.goto( path );
		const scrollWidth = await page.evaluate(
			() => document.documentElement.scrollWidth
		);
		expect( scrollWidth, path ).toBeLessThanOrEqual( 390 );
	}
} );

test( 'section nav scrolls horizontally at 390', async ( { page } ) => {
	await page.goto( '/' );
	// Both the <nav> and its inner <ul> carry this class; .first() is the <nav> (the
	// overflow-x:auto scroll container).
	const nav = page.locator( '.ttm-masthead-front__nav' ).first();

	/* eslint-disable no-undef */
	const overflowX = await nav.evaluate(
		( el ) => getComputedStyle( el ).overflowX
	);
	const [ scrollWidth, clientWidth ] = await nav.evaluate( ( el ) => [
		el.scrollWidth,
		el.clientWidth,
	] );
	/* eslint-enable no-undef */

	expect( overflowX ).toBe( 'auto' );
	expect( scrollWidth ).toBeGreaterThan( clientWidth );
} );

test( 'writing cell stacks with a rule above also-running', async ( {
	page,
} ) => {
	await page.goto( '/' );
	const body = page.locator( '.ttm-writing-cell__body' );
	await expect( body ).toBeVisible();

	/* eslint-disable no-undef */
	const columns = await body.evaluate(
		( el ) => getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length
	);
	const also = page.locator( '.ttm-writing-cell__also' );
	const borderLeft = await also.evaluate(
		( el ) => getComputedStyle( el ).borderLeftWidth
	);
	const borderTop = await also.evaluate(
		( el ) => getComputedStyle( el ).borderTopWidth
	);
	/* eslint-enable no-undef */

	expect( columns ).toBe( 1 );
	expect( borderLeft ).toBe( '0px' );
	expect( borderTop ).not.toBe( '0px' );
} );

test( 'poster stacks its form at 390', async ( { page } ) => {
	await page.goto( '/' );
	const poster = page.locator( '.ttm-poster' );
	const input = poster.locator( 'input[type="email"]' );

	const posterBox = await poster.boundingBox();
	const inputBox = await input.boundingBox();

	expect( posterBox ).not.toBeNull();
	expect( inputBox ).not.toBeNull();

	// "Stacked full width" -- the input's own width should span (within the poster's own
	// left/right padding) the poster's own content width.
	expect( inputBox.width ).toBeGreaterThan( posterBox.width - 60 );
} );
