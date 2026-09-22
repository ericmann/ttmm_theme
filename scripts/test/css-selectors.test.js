/**
 * Tests for the P0-03 selector-coverage parser (scripts/lib/css-selectors.mjs).
 */

const path = require( 'path' );

let selectorsOf;
let queryable;
let isExempt;
let parseSelectorAllow;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'css-selectors.mjs' )
	);
	( { selectorsOf, queryable, isExempt, parseSelectorAllow } = mod );
} );

describe( 'selectorsOf', () => {
	it( 'splits comma lists and flags media rules', () => {
		const css = `
			.ttm-foo, .ttm-bar { color: red; }
			@media (max-width: 720px) {
				.ttm-baz { color: blue; }
			}
		`;

		const entries = selectorsOf( css );

		expect( entries ).toEqual(
			expect.arrayContaining( [
				{ selector: '.ttm-foo', inMedia: false },
				{ selector: '.ttm-bar', inMedia: false },
				{ selector: '.ttm-baz', inMedia: true },
			] )
		);
	} );

	it( 'ignores comments containing braces', () => {
		const css = `
			/* a comment with a { brace } inside */
			.ttm-real { color: green; }
		`;

		const entries = selectorsOf( css );

		expect( entries ).toEqual( [
			{ selector: '.ttm-real', inMedia: false },
		] );
	} );

	it( 'keeps a top-level comma split out of :not() parentheses', () => {
		const css = `.ttm-a:not(.ttm-b, .ttm-c) { color: red; }`;

		const entries = selectorsOf( css );

		expect( entries ).toEqual( [
			{ selector: '.ttm-a:not(.ttm-b, .ttm-c)', inMedia: false },
		] );
	} );
} );

describe( 'queryable', () => {
	it( 'strips pseudo-elements', () => {
		expect( queryable( '.ttm-foo::before' ) ).toBe( '.ttm-foo' );
		expect( queryable( '.ttm-foo::after' ) ).toBe( '.ttm-foo' );
		expect( queryable( '.ttm-foo::-webkit-scrollbar-thumb' ) ).toBe(
			'.ttm-foo'
		);
		expect( queryable( '.ttm-foo:hover' ) ).toBe( '.ttm-foo:hover' );
	} );
} );

describe( 'isExempt', () => {
	it( 'covers hover focus state classes and media', () => {
		expect(
			isExempt( { selector: '.ttm-foo:hover', inMedia: false } )
		).toBe( true );
		expect(
			isExempt( { selector: '.ttm-foo:focus-visible', inMedia: false } )
		).toBe( true );
		expect(
			isExempt( { selector: '.ttm-nav-open .ttm-nav', inMedia: false } )
		).toBe( true );
		expect( isExempt( { selector: '.ttm-foo', inMedia: true } ) ).toBe(
			true
		);
		expect( isExempt( { selector: '.ttm-foo', inMedia: false } ) ).toBe(
			false
		);
	} );
} );

describe( 'parseSelectorAllow', () => {
	it( 'parses selector # reason lines, ignoring blanks and comments', () => {
		const text = [
			'# a full comment',
			'',
			'.is-style-tile # editor block style (04 §3)',
			'   ',
		].join( '\n' );

		const entries = parseSelectorAllow( text );

		expect( entries ).toEqual( [
			{
				selector: '.is-style-tile',
				reason: 'editor block style (04 §3)',
			},
		] );
	} );
} );
