/**
 * Layout facts that only make sense at the phone viewport (SPEC §6.1.9, §6.4, 02 §A
 * Responsive) and aren't already covered by a `tests/e2e/fidelity.spec.mjs` row. Runs only in
 * the `phone` project (390x844; see `../playwright.config.mjs`), against the seeded site.
 */
import { test, expect } from '@playwright/test';
import { SCREEN_URLS } from '../lib/urls.mjs';

test( 'every seeded screen has no horizontal overflow at 390', async ( {
	page,
} ) => {
	for ( const path of SCREEN_URLS ) {
		await page.goto( path );
		const scrollWidth = await page.evaluate(
			() => document.documentElement.scrollWidth
		);
		expect( scrollWidth, path ).toBeLessThanOrEqual( 390 );
	}
} );

test( 'archive filter row scrolls horizontally at 390', async ( { page } ) => {
	await page.goto( '/category/security/' );
	const row = page.locator( '.ttm-filter-row' );

	/* eslint-disable no-undef */
	const overflowX = await row.evaluate(
		( el ) => getComputedStyle( el ).overflowX
	);
	const [ scrollWidth, clientWidth ] = await row.evaluate( ( el ) => [
		el.scrollWidth,
		el.clientWidth,
	] );
	/* eslint-enable no-undef */

	expect( overflowX ).toBe( 'auto' );
	expect( scrollWidth ).toBeGreaterThan( clientWidth );
} );

test( 'article aside zones follow prev/next at 390', async ( { page } ) => {
	await page.goto( '/signing-your-options-table/' );

	const top = ( locator ) =>
		locator.evaluate( ( el ) => el.getBoundingClientRect().top );

	const prevNext = page.locator( '.ttm-prevnext' );
	const aside = page.locator( '.ttm-article aside' );

	const [ prevNextTop, asideTop ] = await Promise.all( [
		top( prevNext ),
		top( aside ),
	] );

	expect( prevNextTop ).toBeLessThan( asideTop );
} );

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

test( 'writing page reorders sections at 1000', async ( { page } ) => {
	await page.setViewportSize( { width: 1000, height: 900 } );
	await page.goto( '/writing/' );

	const top = ( locator ) =>
		locator.evaluate( ( el ) => el.getBoundingClientRect().top );

	const serials = page.locator( '.ttm-writing-body__serials' );
	const stories = page.locator( '.ttm-writing-body__stories' );
	const chapters = page.locator( '.ttm-writing-body__chapters' );
	const books = page.locator( '.ttm-writing-body__books' );

	const [ serialsTop, storiesTop, chaptersTop, booksTop ] = await Promise.all(
		[ top( serials ), top( stories ), top( chapters ), top( books ) ]
	);

	expect( serialsTop ).toBeLessThan( storiesTop );
	expect( storiesTop ).toBeLessThan( chaptersTop );
	expect( chaptersTop ).toBeLessThan( booksTop );
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
