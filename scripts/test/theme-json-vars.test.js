/**
 * Tests for the P0-02 theme.json preset-variable check (scripts/lib/theme-json-vars.mjs).
 */

const path = require( 'path' );

let kebabSlug;
let generatedVars;
let referencedVars;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'theme-json-vars.mjs' )
	);
	( { kebabSlug, generatedVars, referencedVars } = mod );
} );

describe( 'generatedVars', () => {
	it( 'kebab-cases letter digit boundaries', () => {
		expect( kebabSlug( 'article-h2' ) ).toBe( 'article-h-2' );
		expect( kebabSlug( 'h1' ) ).toBe( 'h-1' );
		expect( kebabSlug( 'h2' ) ).toBe( 'h-2' );
		expect( kebabSlug( 'body-s' ) ).toBe( 'body-s' );

		const vars = generatedVars( {
			settings: {
				typography: {
					fontSizes: [ { slug: 'article-h2', size: '30px' } ],
				},
				color: { palette: [ { slug: 'accent-700', color: '#000' } ] },
			},
		} );

		expect( vars.has( '--wp--preset--font-size--article-h-2' ) ).toBe(
			true
		);
		expect( vars.has( '--wp--preset--color--accent-700' ) ).toBe( true );
	} );
} );

describe( 'referencedVars', () => {
	it( 'finds font-size and color references', () => {
		const css = `
			.foo { font-size: var(--wp--preset--font-size--article-h-2); }
			.bar { color: var(--wp--preset--color--accent-700); }
			.baz { color: var(--wp--custom--gutter--desktop); }
		`;

		const refs = referencedVars( css );

		expect( refs.has( '--wp--preset--font-size--article-h-2' ) ).toBe(
			true
		);
		expect( refs.has( '--wp--preset--color--accent-700' ) ).toBe( true );
		expect( refs.has( '--wp--custom--gutter--desktop' ) ).toBe( false );
	} );

	it( 'unknown reference is reported', () => {
		const generated = generatedVars( {
			settings: {
				typography: {
					fontSizes: [ { slug: 'article-h2', size: '30px' } ],
				},
				color: { palette: [] },
			},
		} );
		const referenced = referencedVars(
			'font-size: var(--wp--preset--font-size--h2);'
		);

		const unknown = [ ...referenced ].filter(
			( ref ) => ! generated.has( ref )
		);

		expect( unknown ).toEqual( [ '--wp--preset--font-size--h2' ] );
	} );
} );
