/**
 * Tests for scripts/lib/theme-css.mjs: the stylesheets the coverage, preset and selector checks
 * read. The lib is pure ESM; loaded via dynamic import() like css-coverage.mjs.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..', '..' );

let BLOCK_CSS_DIR;
let blockCssNames;
let themeCssFiles;

beforeAll( async () => {
	( { BLOCK_CSS_DIR, blockCssNames, themeCssFiles } = await import(
		path.join( __dirname, '..', 'lib', 'theme-css.mjs' )
	) );
} );

describe( 'theme-css', () => {
	it( 'lists ttm.css and style.css first, then one file per block stylesheet', () => {
		const files = themeCssFiles( ROOT );
		expect( files.slice( 0, 2 ) ).toEqual( [
			'themes/ttm-theme/assets/css/ttm.css',
			'themes/ttm-theme/style.css',
		] );
		expect( files.slice( 2 ) ).toEqual(
			blockCssNames( ROOT ).map(
				( name ) => `${ BLOCK_CSS_DIR }/${ name }.css`
			)
		);
		files.forEach( ( file ) =>
			expect( fs.existsSync( path.join( ROOT, file ) ) ).toBe( true )
		);
	} );

	it( 'names block stylesheets after ttm-core blocks, sorted', () => {
		const names = blockCssNames( ROOT );
		expect( names ).toContain( 'writing-cell' );
		expect( [ ...names ].sort() ).toEqual( names );
		names.forEach( ( name ) =>
			expect(
				fs.existsSync(
					path.join(
						ROOT,
						'plugins/ttm-core/blocks',
						name,
						'block.json'
					)
				)
			).toBe( true )
		);
	} );
} );
