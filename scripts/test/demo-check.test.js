/**
 * Tests for the P2-06 demo:check pure helpers (scripts/demo/lib/check-assertions.mjs,
 * scripts/demo/lib/local-variant.mjs).
 */

const path = require( 'path' );

let checkPages;
let localBlueprint;

beforeAll( async () => {
	const assertions = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'check-assertions.mjs' )
	);
	checkPages = assertions.checkPages;

	const variant = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'local-variant.mjs' )
	);
	localBlueprint = variant.localBlueprint;
} );

const SECTIONS = [
	'Technology',
	'Business',
	'Faith',
	'Journal',
	'Writing',
	'Security',
	'Opinion',
];

function frontHtml( {
	withLeadImage = true,
	labels = SECTIONS,
	seriesRows = 3,
} = {} ) {
	const leadFigure = withLeadImage
		? '<figure class="ttm-lead__media"><img src="https://example.com/demo-a.jpg"></figure>'
		: '<figure class="ttm-lead__media"><span>text only</span></figure>';

	const cellLabels = labels
		.map(
			( label ) => `<h6 class="ttm-cell-heading__label">${ label }</h6>`
		)
		.join( '' );

	const seriesStrip = Array.from(
		{ length: seriesRows },
		( _, i ) =>
			`<a class="ttm-series-row" href="/series/s-${ i }/">Row ${ i }</a>`
	).join( '' );

	return `<html><body>${ leadFigure }${ cellLabels }<div class="ttm-series-strip">${ seriesStrip }</div></body></html>`;
}

function articleHtml( { withHeroImage = true } = {} ) {
	const figure = withHeroImage
		? '<figure class="wp-block-post-featured-image"><img src="https://example.com/demo-b.jpg"></figure>'
		: '<figure class="wp-block-post-featured-image"><span>no image</span></figure>';
	return `<html><body>${ figure }</body></html>`;
}

function seriesHtml( { count = 7 } = {} ) {
	const rows = Array.from(
		{ length: count },
		( _, i ) => `<span class="ttm-series-row__title">Series ${ i }</span>`
	).join( '' );
	return `<html><body>${ rows }</body></html>`;
}

function writingHtml( { withHero = true } = {} ) {
	return `<html><body>${
		withHero
			? '<div class="ttm-serial-hero">Hero</div>'
			: '<div>no hero</div>'
	}</body></html>`;
}

function conformingPages( overrides = {} ) {
	return {
		front: { status: 200, html: frontHtml(), ...( overrides.front || {} ) },
		article: {
			status: 200,
			html: articleHtml(),
			...( overrides.article || {} ),
		},
		series: {
			status: 200,
			html: seriesHtml(),
			...( overrides.series || {} ),
		},
		writing: {
			status: 200,
			html: writingHtml(),
			...( overrides.writing || {} ),
		},
	};
}

describe( 'checkPages', () => {
	it( 'passes on conforming synthetic pages', () => {
		expect( checkPages( conformingPages() ) ).toEqual( [] );
	} );

	it( 'skipAttachmentChecks skips both demo-photograph assertions (local Playground variant)', () => {
		const pages = conformingPages( {
			front: { html: frontHtml( { withLeadImage: false } ) },
			article: { html: articleHtml( { withHeroImage: false } ) },
		} );

		expect( checkPages( pages, { skipAttachmentChecks: true } ) ).toEqual(
			[]
		);
		expect( checkPages( pages ).length ).toBeGreaterThan( 0 );
	} );

	it( 'fails on a non-200 status', () => {
		const failures = checkPages(
			conformingPages( { front: { status: 500 } } )
		);
		expect( failures.join( '\n' ) ).toMatch( /front: status 500/ );
	} );

	it( 'fails on a PHP notice in the page body', () => {
		const pages = conformingPages();
		pages.article.html += '<p>Deprecated: something old</p>';

		const failures = checkPages( pages );
		expect( failures.join( '\n' ) ).toMatch(
			/article: page contains "Deprecated:"/
		);
	} );

	it( 'fails when the front lead has no demo image', () => {
		const failures = checkPages(
			conformingPages( {
				front: { html: frontHtml( { withLeadImage: false } ) },
			} )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/front: no demo photograph in \.ttm-lead__media img/
		);
	} );

	it( 'fails when a section label is missing', () => {
		const failures = checkPages(
			conformingPages( {
				front: {
					html: frontHtml( {
						labels: SECTIONS.filter( ( s ) => 'Opinion' !== s ),
					} ),
				},
			} )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/front: \.ttm-cell-heading__label is missing "Opinion"/
		);
	} );

	it( 'fails when the series strip has fewer than 3 rows', () => {
		const failures = checkPages(
			conformingPages( {
				front: { html: frontHtml( { seriesRows: 2 } ) },
			} )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/front: \.ttm-series-strip \.ttm-series-row count is 2, expected >= 3/
		);
	} );

	it( 'fails when the article has no demo hero image', () => {
		const failures = checkPages(
			conformingPages( {
				article: { html: articleHtml( { withHeroImage: false } ) },
			} )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/article: no demo photograph in \.wp-block-post-featured-image img/
		);
	} );

	it( 'fails when the series page has other than 7 distinct titles', () => {
		const failures = checkPages(
			conformingPages( { series: { html: seriesHtml( { count: 5 } ) } } )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/series: 5 distinct \.ttm-series-row__title text\(s\), expected 7/
		);
	} );

	it( 'fails when the writing page has no serial hero', () => {
		const failures = checkPages(
			conformingPages( {
				writing: { html: writingHtml( { withHero: false } ) },
			} )
		);
		expect( failures.join( '\n' ) ).toMatch(
			/writing: \.ttm-serial-hero not present/
		);
	} );

	it( 'fails when any page contains Uncategorized', () => {
		const pages = conformingPages();
		pages.series.html += '<p>Uncategorized</p>';

		const failures = checkPages( pages );
		expect( failures.join( '\n' ) ).toMatch(
			/series: page contains "Uncategorized"/
		);
	} );
} );

describe( 'localBlueprint', () => {
	it( 'rewrites exactly the two release URLs and the WXR URL', () => {
		const blueprint = {
			steps: [
				{ step: 'setSiteOptions', options: { blogname: 'x' } },
				{
					step: 'installPlugin',
					pluginData: {
						resource: 'url',
						url: 'https://github-proxy.com/proxy/?repo=ericmann/ttmm_theme&release=v0.2.0&asset=ttm-core.zip',
					},
					options: { activate: true },
				},
				{
					step: 'installTheme',
					themeData: {
						resource: 'url',
						url: 'https://github-proxy.com/proxy/?repo=ericmann/ttmm_theme&release=v0.2.0&asset=ttm-theme.zip',
					},
					options: { activate: true },
				},
				{ step: 'wp-cli', command: 'wp site empty --yes' },
				{
					step: 'importWxr',
					file: {
						resource: 'url',
						url: 'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/.github/demo-content.xml',
					},
					fetchAttachments: true,
				},
				{ step: 'wp-cli', command: 'wp ttm demo:verify --posts=1' },
			],
		};

		const rewritten = localBlueprint(
			blueprint,
			'http://127.0.0.1:54321/'
		);

		const installPlugin = rewritten.steps.find(
			( step ) => 'installPlugin' === step.step
		);
		expect( installPlugin.pluginData.url ).toBe(
			'http://127.0.0.1:54321/ttm-core.zip'
		);

		const installTheme = rewritten.steps.find(
			( step ) => 'installTheme' === step.step
		);
		expect( installTheme.themeData.url ).toBe(
			'http://127.0.0.1:54321/ttm-theme.zip'
		);

		const importWxr = rewritten.steps.find(
			( step ) => 'importWxr' === step.step
		);
		expect( importWxr.file.url ).toBe(
			'http://127.0.0.1:54321/demo-content.xml'
		);
		expect( importWxr.fetchAttachments ).toBe( true );

		// setSiteOptions and a wp-cli step with no --attachments mention are untouched.
		expect( rewritten.steps[ 0 ] ).toEqual( blueprint.steps[ 0 ] );
		expect( rewritten.steps[ 3 ] ).toEqual( blueprint.steps[ 3 ] );
		expect( rewritten.steps[ 5 ] ).toEqual( blueprint.steps[ 5 ] );

		// The original is not mutated.
		expect( blueprint.steps[ 1 ].pluginData.url ).toContain(
			'github-proxy.com'
		);
	} );

	it( "zeroes demo:verify's --attachments count (fetchAttachments never works against the local server)", () => {
		const stringForm = {
			steps: [
				{
					step: 'wp-cli',
					command:
						'wp ttm demo:verify --posts=107 --pages=4 --series=7 --attachments=13',
				},
			],
		};
		const rewrittenString = localBlueprint(
			stringForm,
			'http://127.0.0.1:1'
		);
		expect( rewrittenString.steps[ 0 ].command ).toBe(
			'wp ttm demo:verify --posts=107 --pages=4 --series=7 --attachments=0'
		);

		const arrayForm = {
			steps: [
				{
					step: 'wp-cli',
					command: [
						'wp',
						'eval',
						"parse_str(str_replace(['--',' '],['','&'],'--posts=107 --pages=4 --series=7 --attachments=13'),$a);",
					],
				},
			],
		};
		const rewrittenArray = localBlueprint(
			arrayForm,
			'http://127.0.0.1:1'
		);
		expect( rewrittenArray.steps[ 0 ].command[ 2 ] ).toContain(
			'--attachments=0'
		);
		expect( rewrittenArray.steps[ 0 ].command[ 2 ] ).not.toContain(
			'--attachments=13'
		);
		expect( rewrittenArray.steps[ 0 ].command[ 0 ] ).toBe( 'wp' );
	} );
} );
