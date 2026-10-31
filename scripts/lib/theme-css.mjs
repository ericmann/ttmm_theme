/**
 * The theme stylesheets that carry `ttm-*` rules (rules 34, 37, 44): ttm.css, style.css and the
 * per-block files in assets/css/blocks/, which inc/block-css.php loads only where `ttm/<name>`
 * renders. code.css colours hljs-* tokens only and has its own unit test.
 */
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

export const BLOCK_CSS_DIR = 'themes/ttm-theme/assets/css/blocks';

/**
 * Block names with a stylesheet, from the file names in BLOCK_CSS_DIR.
 *
 * @param {string} root Repository root.
 * @return {string[]} Sorted block names (`writing-cell` for `ttm/writing-cell`).
 */
export function blockCssNames( root = '.' ) {
	return readdirSync( join( root, BLOCK_CSS_DIR ) )
		.filter( ( name ) => name.endsWith( '.css' ) )
		.map( ( name ) => name.slice( 0, -'.css'.length ) )
		.sort();
}

/**
 * Every theme stylesheet with `ttm-*` rules, relative to `root`.
 *
 * @param {string} root Repository root.
 * @return {string[]} ttm.css, style.css, then the block files.
 */
export function themeCssFiles( root = '.' ) {
	return [
		'themes/ttm-theme/assets/css/ttm.css',
		'themes/ttm-theme/style.css',
		...blockCssNames( root ).map(
			( name ) => `${ BLOCK_CSS_DIR }/${ name }.css`
		),
	];
}
