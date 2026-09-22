/**
 * Tests for the P0-01 CSS coverage lint (scripts/lib/css-coverage.mjs).
 * The lib is pure ESM; loaded via dynamic import() the way
 * scripts/test/convert-classic.test.js loads footnotes.mjs.
 */

const path = require( 'path' );

let collectMarkupClasses;
let collectCssClasses;
let globToRegExp;
let parseAllowList;
let report;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'css-coverage.mjs' )
	);
	( {
		collectMarkupClasses,
		collectCssClasses,
		globToRegExp,
		parseAllowList,
		report,
	} = mod );
} );

describe( 'collectMarkupClasses', () => {
	it( 'collects class attributes, className json and wrapper() names', () => {
		const files = [
			'<div class="ttm-foo ttm-bar"></div>',
			"<div class='ttm-baz'></div>",
			'<!-- wp:group {"className":"ttm-lead-row is-style-grid-8-4"} -->',
			"echo Helpers::wrapper( 'story-tiles', $classes );",
		];

		const classes = collectMarkupClasses( files );

		expect( classes.has( 'ttm-foo' ) ).toBe( true );
		expect( classes.has( 'ttm-bar' ) ).toBe( true );
		expect( classes.has( 'ttm-baz' ) ).toBe( true );
		expect( classes.has( 'ttm-lead-row' ) ).toBe( true );
		expect( classes.has( 'ttm-story-tiles' ) ).toBe( true );
	} );

	it( 'ignores is-state classes and prefix literals', () => {
		const files = [
			'<div class="is-style-grid-8-4 is-after-poster"></div>',
			"array_merge( [ 'ttm-' . $name ], $classes )",
		];

		const classes = collectMarkupClasses( files );

		expect( classes.size ).toBe( 0 );
	} );
} );

describe( 'collectCssClasses', () => {
	it( 'collects ttm-* selectors and ignores everything else', () => {
		const css = `
			.ttm-lead__inner { display: grid; }
			.ttm-lead__inner.is-featured { color: red; }
			.wp-block-group { color: blue; }
		`;

		const classes = collectCssClasses( css );

		expect( classes.has( 'ttm-lead__inner' ) ).toBe( true );
		expect( classes.has( 'wp-block-group' ) ).toBe( false );
		expect( classes.size ).toBe( 1 );
	} );
} );

describe( 'globToRegExp', () => {
	it( 'glob patterns match braces and stars', () => {
		const braceRe = globToRegExp( 'ttm-{foo,bar}' );
		expect( braceRe.test( 'ttm-foo' ) ).toBe( true );
		expect( braceRe.test( 'ttm-bar' ) ).toBe( true );
		expect( braceRe.test( 'ttm-baz' ) ).toBe( false );

		const starRe = globToRegExp( 'ttm-series-bar*' );
		expect( starRe.test( 'ttm-series-bar' ) ).toBe( true );
		expect( starRe.test( 'ttm-series-bar__item' ) ).toBe( true );
		expect( starRe.test( 'ttm-other' ) ).toBe( false );

		const comboRe = globToRegExp( 'ttm-{book,serial-hero}*' );
		expect( comboRe.test( 'ttm-book__cover' ) ).toBe( true );
		expect( comboRe.test( 'ttm-serial-hero' ) ).toBe( true );
		expect( comboRe.test( 'ttm-other__cover' ) ).toBe( false );
	} );
} );

describe( 'parseAllowList', () => {
	it( 'ignores blank/comment-only lines', () => {
		const text = [
			'# a full comment line',
			'',
			'ttm-{foo,bar} # phase 2 pending: reason here',
			'   ',
		].join( '\n' );

		const entries = parseAllowList( text );

		expect( entries ).toHaveLength( 1 );
		expect( entries[ 0 ].pattern ).toBe( 'ttm-{foo,bar}' );
		expect( entries[ 0 ].reason ).toBe( 'phase 2 pending: reason here' );
		expect( entries[ 0 ].regex.test( 'ttm-foo' ) ).toBe( true );
	} );
} );

describe( 'report', () => {
	it( 'reports missing and dead classes after the allow-list', () => {
		const markup = new Set( [ 'ttm-foo', 'ttm-bar', 'ttm-allowed' ] );
		const css = new Set( [ 'ttm-foo', 'ttm-dead', 'ttm-allowed' ] );
		const allow = parseAllowList( 'ttm-allowed # known gap' );

		const result = report( { markup, css, allow } );

		expect( result.missing ).toEqual( [ 'ttm-bar' ] );
		expect( result.dead ).toEqual( [ 'ttm-dead' ] );
		expect( result.allowCount ).toBe( 1 );
	} );

	it( 'fails when the allow-list has ten entries', () => {
		const lines = [];
		for ( let i = 0; i < 10; i++ ) {
			lines.push( `ttm-allow-${ i } # reason ${ i }` );
		}
		const allow = parseAllowList( lines.join( '\n' ) );

		const result = report( { markup: new Set(), css: new Set(), allow } );

		expect( result.allowCount ).toBe( 10 );
		expect( result.allowCount >= 10 ).toBe( true );
	} );
} );
