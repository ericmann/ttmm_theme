/**
 * Computed-style helpers for the fidelity suite (P0-04, SPEC §6.2).
 */

/**
 * A computed style property of a Playwright locator's first matching element.
 *
 * @param {import('@playwright/test').Locator} locator Element locator.
 * @param {string}                             prop    CSS property name (camelCase or kebab-case).
 * @return {Promise<string>} The computed value.
 */
export async function computed( locator, prop ) {
	// Runs inside the page, not this Node process - `getComputedStyle` is a real browser global.
	/* eslint-disable no-undef */
	return locator.evaluate(
		( el, p ) =>
			getComputedStyle( el ).getPropertyValue( p ) ||
			getComputedStyle( el )[ p ],
		prop
	);
	/* eslint-enable no-undef */
}

/**
 * The resolved `grid-template-columns` of a locator's element, as an array of pixel numbers
 * (browsers always resolve `repeat()`/`fr`/`auto` tracks to concrete `px` values).
 *
 * @param {import('@playwright/test').Locator} locator Grid container locator.
 * @return {Promise<number[]>} Track sizes in pixels.
 */
export async function tracks( locator ) {
	const value = await computed( locator, 'grid-template-columns' );
	return value
		.trim()
		.split( /\s+/ )
		.filter( Boolean )
		.map( ( token ) => parseFloat( token ) );
}

/**
 * A computed style property of a locator's element's `::before` pseudo-element.
 *
 * @param {import('@playwright/test').Locator} locator Element locator.
 * @param {string}                             prop    CSS property name.
 * @return {Promise<string>} The computed value.
 */
export async function before( locator, prop ) {
	// Runs inside the page, not this Node process - `getComputedStyle` is a real browser global.
	/* eslint-disable no-undef */
	return locator.evaluate(
		( el, p ) => getComputedStyle( el, '::before' ).getPropertyValue( p ),
		prop
	);
	/* eslint-enable no-undef */
}
