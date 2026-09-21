/**
 * Theme-preset colour and size helpers for the fidelity suite (P0-04, SPEC §6.2).
 * `theme.json` is the single source; nothing here duplicates a hex/px value by hand.
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

// Resolved from the process cwd (the repo root: both `npm run test:e2e` and Playwright's own
// runner invoke from there) rather than `import.meta.url` -- Playwright's on-the-fly transform
// of these `.spec.js`/`.mjs` files runs them as CommonJS, where `import.meta` is unavailable.
const themeJsonPath = join( 'themes', 'ttm-theme', 'theme.json' );
const themeJson = JSON.parse( readFileSync( themeJsonPath, 'utf8' ) );

const palette = new Map(
	themeJson.settings.color.palette.map( ( entry ) => [
		entry.slug,
		entry.color,
	] )
);

/**
 * `#rrggbb` -> `rgb(r, g, b)`, the form `getComputedStyle()` returns for an opaque colour.
 * A `rgba(...)` preset (only `divider`) is returned as-is.
 *
 * @param {string} hex Hex colour.
 * @return {string} The `rgb()` form.
 */
function hexToRgb( hex ) {
	const r = parseInt( hex.slice( 1, 3 ), 16 );
	const g = parseInt( hex.slice( 3, 5 ), 16 );
	const b = parseInt( hex.slice( 5, 7 ), 16 );
	return `rgb(${ r }, ${ g }, ${ b })`;
}

/**
 * The computed-style colour for a `theme.json` preset slug (e.g. `'accent-700'`).
 *
 * @param {string} slug Preset slug.
 * @return {string} `rgb(r, g, b)`, or the preset's own value when it is already an `rgba()`.
 */
export function color( slug ) {
	const value = palette.get( slug );
	if ( undefined === value ) {
		throw new Error( `Unknown theme.json colour preset: ${ slug }` );
	}
	return value.startsWith( '#' ) ? hexToRgb( value ) : value;
}

/**
 * A pixel literal as `getComputedStyle()` renders it.
 *
 * @param {number} n Pixel count.
 * @return {string} `<n>px`.
 */
export function px( n ) {
	return `${ n }px`;
}
