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

/**
 * A locator's `innerText`, whitespace-normalised (collapsed runs of whitespace, trimmed
 * ends) so a fidelity assertion doesn't fail on incidental line-wrap or indentation.
 *
 * @param {import('@playwright/test').Locator} locator Element locator.
 * @return {Promise<string>} Normalised text content.
 */
export async function text( locator ) {
	const raw = await locator.innerText();
	return raw.replace( /\s+/g, ' ' ).trim();
}

/**
 * How many of a locator's matches are actually visible (`Locator.isVisible()` on each),
 * for asserting a count of visible elements when some matches are legitimately hidden
 * (e.g. a responsive container closed by default).
 *
 * @param {import('@playwright/test').Locator} locator Element locator (may match several).
 * @return {Promise<number>} Count of visible matches.
 */
export async function visibleCount( locator ) {
	const count = await locator.count();
	let visible = 0;
	for ( let i = 0; i < count; i++ ) {
		if ( await locator.nth( i ).isVisible() ) {
			visible++;
		}
	}
	return visible;
}
