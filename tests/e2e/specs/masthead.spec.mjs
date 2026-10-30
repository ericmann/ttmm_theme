/**
 * The inner masthead stays pinned to the top of the viewport while an article scrolls, and the
 * sticky article aside (>=1025) stays below it. Runs in both the `desktop` and `phone` projects.
 */
import { test, expect } from '@playwright/test';

const ARTICLE = '/signing-your-options-table/';

test( 'inner masthead stays pinned after scrolling', async ( { page } ) => {
	await page.goto( ARTICLE );
	await page.mouse.wheel( 0, 1500 );

	const masthead = page.locator( '.ttm-masthead-inner' );
	await expect
		.poll( () =>
			masthead.evaluate( ( el ) => el.getBoundingClientRect().top )
		)
		.toBeLessThanOrEqual( 14 );
	await expect( masthead ).toBeInViewport();
} );

test( 'sticky aside clears the pinned masthead', async ( { page } ) => {
	const width = page.viewportSize()?.width ?? 0;
	test.skip( width < 1025, 'the aside only sticks at >=1025' );

	await page.goto( ARTICLE );

	// Its sticky offset, not its current position: a short seeded article never scrolls far
	// enough for the aside to stick.
	/* eslint-disable no-undef */
	const stickyTop = await page
		.locator( '.ttm-article aside' )
		.evaluate( ( el ) => parseFloat( getComputedStyle( el ).top ) );
	/* eslint-enable no-undef */
	const mastheadHeight = await page
		.locator( '.ttm-masthead-inner' )
		.evaluate( ( el ) => el.getBoundingClientRect().height );

	expect( stickyTop ).toBeGreaterThan( mastheadHeight );
} );
