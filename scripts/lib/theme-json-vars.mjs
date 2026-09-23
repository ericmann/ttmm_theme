/**
 * Pure functions for the theme.json preset-variable check (SPEC §3.2 rule 44, P0-02).
 *
 * WordPress generates a CSS custom property for every `settings.typography.fontSizes`
 * and `settings.color.palette` entry by kebab-casing the slug at letter/digit
 * boundaries (Decision "Rule 44 slug"): `h2` -> `h-2`, `article-h2` -> `article-h-2`.
 * Every `--wp--preset--(font-size|color)--*` reference in CSS or theme.json's own
 * `styles` must resolve to a slug that actually generates that variable.
 */

/**
 * Kebab-case a slug at letter/digit boundaries, lower-cased.
 *
 * @param {string} slug Preset slug, e.g. "article-h2".
 * @return {string} Kebab-cased slug, e.g. "article-h-2".
 */
export function kebabSlug( slug ) {
	return slug
		.toLowerCase()
		.replace( /(?<=[a-z])(?=\d)/g, '-' )
		.replace( /(?<=\d)(?=[a-z])/g, '-' );
}

/**
 * Every `--wp--preset--font-size--*` / `--wp--preset--color--*` variable that
 * `theme.json`'s settings actually generate.
 *
 * @param {Object} themeJson Parsed theme.json.
 * @return {Set<string>} Generated variable names, e.g. "--wp--preset--font-size--article-h-2".
 */
export function generatedVars( themeJson ) {
	const found = new Set();

	const fontSizes = themeJson.settings?.typography?.fontSizes ?? [];
	for ( const { slug } of fontSizes ) {
		found.add( `--wp--preset--font-size--${ kebabSlug( slug ) }` );
	}

	const palette = themeJson.settings?.color?.palette ?? [];
	for ( const { slug } of palette ) {
		found.add( `--wp--preset--color--${ kebabSlug( slug ) }` );
	}

	return found;
}

const VAR_REFERENCE = /--wp--preset--(?:font-size|color)--[a-z0-9-]+/g;

/**
 * Every `--wp--preset--(font-size|color)--*` variable referenced in text.
 *
 * @param {string} cssText Raw CSS (or JSON.stringify'd theme.json styles).
 * @return {Set<string>} Referenced variable names.
 */
export function referencedVars( cssText ) {
	const found = new Set();
	const matches = cssText.match( VAR_REFERENCE );
	if ( matches ) {
		matches.forEach( ( m ) => found.add( m ) );
	}
	return found;
}
