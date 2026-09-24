/* eslint-disable jest/no-standalone-expect -- the eslint-plugin-jest rule can't see through
 * `maybeIt` (`editor ? it : it.skip`), a deliberate runtime skip for spike Outcome B; every
 * `expect()` below is still inside a real `it()`/`it.skip()` callback. */
/**
 * Tests for the P8-01 classic-to-block conversion spike.
 *
 * Runs under wp-scripts' Jest preset (testEnvironment: "jsdom"), which already provides
 * `window`/`document`/etc as real globals -- unlike scripts/convert-classic.mjs's CLI path,
 * this test doesn't need to build its own jsdom environment first. `footnotes.mjs` is pure ESM,
 * loaded via dynamic `import()` (Node's own loader, not Jest's babel-transform, handles that);
 * `@wordpress/blocks`/`@wordpress/block-library` are loaded via `require()` so their CJS build
 * is used (their ESM build imports `.json` files without an import attribute, which fails under
 * Node's stricter ESM loader -- see scripts/convert-classic.mjs's docblock).
 */

const { readFileSync } = require( 'fs' );
const path = require( 'path' );

const FIXTURE_PATH = path.join(
	__dirname,
	'..',
	'..',
	'docs',
	'fixtures',
	'classic-sample.html'
);

function fixturePost( postId ) {
	const html = readFileSync( FIXTURE_PATH, 'utf8' );
	const match = html.match(
		new RegExp(
			`<article[^>]*data-post-id="${ postId }"[^>]*>([\\s\\S]*?)<\\/article>`
		)
	);
	return match ? match[ 1 ] : null;
}

const SHORTCODES_FIXTURE_PATH = path.join(
	__dirname,
	'..',
	'..',
	'docs',
	'fixtures',
	'classic-shortcodes.html'
);

function shortcodeFixturePost( postId ) {
	const html = readFileSync( SHORTCODES_FIXTURE_PATH, 'utf8' );
	const match = html.match(
		new RegExp(
			`<article[^>]*data-post-id="${ postId }"[^>]*>([\\s\\S]*?)<\\/article>`
		)
	);
	return match ? match[ 1 ] : null;
}

/**
 * @return {{ rawHandler: Function, serialize: Function } | null} null if the block-library
 *          build can't load under this environment (spike Outcome B).
 */
function loadEditor() {
	try {
		const { rawHandler, serialize } = require( '@wordpress/blocks' );
		const { registerCoreBlocks } = require( '@wordpress/block-library' );
		registerCoreBlocks();
		return { rawHandler, serialize };
	} catch {
		return null;
	}
}

describe( 'transformFootnotes', () => {
	it( 'converts sup/span pairs and strips the list', async () => {
		const { transformFootnotes } = await import( '../lib/footnotes.mjs' );

		const html = fixturePost( '6914' );
		const { html: transformed, footnotes } = transformFootnotes(
			html,
			6914
		);

		expect( footnotes ).toHaveLength( 3 );
		expect( footnotes[ 0 ] ).toEqual( {
			id: 'mfn-6914-1',
			content: expect.stringContaining( 'Obviously not his real name' ),
		} );
		expect( transformed ).not.toContain( 'modern-footnotes' );
		expect( transformed ).toContain(
			'<sup data-fn="mfn-6914-1" class="fn"><a href="#mfn-6914-1" id="mfn-6914-1-link">1</a></sup>'
		);
	} );

	it( 'fixture with footnotes yields footnotes array of length 3', async () => {
		const { transformFootnotes } = await import( '../lib/footnotes.mjs' );

		const html = fixturePost( '6914' );
		const { footnotes } = transformFootnotes( html, 6914 );

		expect( footnotes ).toHaveLength( 3 );
	} );
} );

describe( 'prepareClassicHtml (R3-02, no jsdom/block-library needed)', () => {
	// The pre-rawHandler pipeline (preprocessShortcodes -> autop -> transformFootnotes) pulled
	// out of convertPost() so its ordering can be pinned by a real, running test: the shortcode
	// pre-pass must replace a multi-line shortcode body with its final tag markup *before*
	// autop() ever sees the blank line inside it, or autop() wraps that inner blank line in
	// <p>/<br> as if it were prose, corrupting the code block. rule 49/52: this exercises the
	// real pipeline functions, not a mock.
	it( 'runs the shortcode pre-pass before autop, so a multi-line [cc] body is never autopped', async () => {
		const { prepareClassicHtml } =
			await import( '../lib/prepare-classic.mjs' );

		const { html } = prepareClassicHtml(
			'Intro.\n\n[cc lang="php"]a\n\nb[/cc]\n\nOutro.',
			1
		);

		// Asserts the exact code body, not merely the absence of unescaped tags: codeMarkup()
		// HTML-escapes the shortcode body regardless of pipeline order, so a swapped order
		// (autop before the shortcode pre-pass) still yields a body with no literal `<p>`/`<br`
		// substrings -- it instead contains the *escaped* entities `&lt;p&gt;`/`&lt;br` wrapping
		// the inner blank line, which the old assertions couldn't see. Pinning the exact string
		// forces the swapped order to fail for the right reason.
		const codeMatch = html.match(
			/<pre class="wp-block-code"><code[^>]*>([\s\S]*?)<\/code><\/pre>/
		);
		expect( codeMatch ).not.toBeNull();
		expect( codeMatch[ 1 ] ).toBe( 'a\n\nb' );
		expect( html ).not.toContain( '<p><pre' );
		expect( html ).not.toContain( '</pre></p>' );

		expect( html ).toContain( '<p>Intro.</p>' );
		expect( html ).toContain( '<p>Outro.</p>' );
	} );

	it( 'pins the <br /> path for a single-newline-separated [cc] body', async () => {
		const { prepareClassicHtml } =
			await import( '../lib/prepare-classic.mjs' );

		const { html } = prepareClassicHtml(
			'Intro.\n\n[cc lang="php"]a\nb\n\nc[/cc]\n\nOutro.',
			1
		);

		const codeMatch = html.match(
			/<pre class="wp-block-code"><code[^>]*>([\s\S]*?)<\/code><\/pre>/
		);
		expect( codeMatch ).not.toBeNull();
		expect( codeMatch[ 1 ] ).toBe( 'a\nb\n\nc' );
		expect( html ).not.toContain( '<p><pre' );
		expect( html ).not.toContain( '</pre></p>' );

		expect( html ).toContain( '<p>Intro.</p>' );
		expect( html ).toContain( '<p>Outro.</p>' );
	} );
} );

describe( 'buildBlockReport (no jsdom/block-library needed)', () => {
	// convert-classic.mjs's own report-counting logic, extracted to scripts/lib/report.mjs so
	// this can be exercised without pulling in `jsdom` at all (convert-classic.mjs's top-level
	// `require('jsdom')` only runs inside setUpBlockEditorEnvironment(), but Jest's dynamic
	// `import()` of that file still fails to load under this environment the same way
	// `@wordpress/block-library` does below -- spike Outcome B). PLAN P8-01's report contract:
	// report.freeform and report.html are the `core/freeform`/`core/html` counts respectively,
	// not one merged count.
	it( 'reports core/html count separately from freeform', async () => {
		const { buildBlockReport } = await import( '../lib/report.mjs' );

		const fakeBlocks = [
			{ name: 'core/paragraph' },
			{ name: 'core/freeform' },
			{ name: 'core/html' },
			{ name: 'core/html' },
		];

		const report = buildBlockReport( fakeBlocks );

		expect( report.freeform ).toBe( 1 );
		expect( report.html ).toBe( 2 );
		expect( report.blockCounts[ 'core/html' ] ).toBe( 2 );
		expect( report.blockCounts[ 'core/freeform' ] ).toBe( 1 );
		expect( report.blockCounts[ 'core/paragraph' ] ).toBe( 1 );
	} );

	it( 'counts a core/paragraph block whose content has an internal blank line (R2-01)', async () => {
		const { buildBlockReport } = await import( '../lib/report.mjs' );

		const fakeBlocks = [
			{
				name: 'core/paragraph',
				attributes: { content: 'Para one.\n\nPara two.' },
			},
			{ name: 'core/paragraph', attributes: { content: 'Fine.' } },
		];

		const report = buildBlockReport( fakeBlocks );

		expect( report.mergedParagraphs ).toBe( 1 );
	} );

	it( "counts a merged paragraph nested inside a container block's innerBlocks (R3-02)", async () => {
		const { buildBlockReport } = await import( '../lib/report.mjs' );

		const fakeBlocks = [
			{
				name: 'core/quote',
				innerBlocks: [
					{
						name: 'core/paragraph',
						attributes: { content: 'a\n\nb' },
					},
				],
			},
		];

		const report = buildBlockReport( fakeBlocks );

		expect( report.mergedParagraphs ).toBe( 1 );
	} );

	it( 'still counts top-level blockCounts/freeform/html only (not nested)', async () => {
		const { buildBlockReport } = await import( '../lib/report.mjs' );

		const fakeBlocks = [
			{
				name: 'core/group',
				innerBlocks: [ { name: 'core/freeform' } ],
			},
		];

		const report = buildBlockReport( fakeBlocks );

		expect( report.blockCounts[ 'core/group' ] ).toBe( 1 );
		expect( report.blockCounts[ 'core/freeform' ] ).toBeUndefined();
		expect( report.freeform ).toBe( 0 );
	} );
} );

describe( 'summarizeResults (no jsdom/block-library needed, R1-03)', () => {
	// The CLI's exit-decision logic, extracted so it's testable without setUpBlockEditorEnvironment()
	// (jsdom + @wordpress/block-library -- unavailable under this Jest environment, spike Outcome B).
	function fakeResult( id, overrides = {} ) {
		return {
			id,
			slug: `post-${ id }`,
			report: {
				textEqual: true,
				freeform: 0,
				html: 0,
				mergedParagraphs: 0,
				...overrides,
			},
		};
	}

	it( '--allow-text-mismatch reports mismatches and exits 0', async () => {
		const { summarizeResults } = await import( '../lib/summarize.mjs' );

		const results = [
			fakeResult( 1, { textEqual: false } ),
			fakeResult( 2 ),
		];

		const { failed, messages } = summarizeResults( results, {
			allowTextMismatch: true,
		} );

		expect( failed ).toBe( false );
		expect( messages ).toEqual( [
			'post 1 (post-1): text content changed',
		] );
	} );

	it( 'without --allow-text-mismatch, a mismatch still fails', async () => {
		const { summarizeResults } = await import( '../lib/summarize.mjs' );

		const results = [ fakeResult( 1, { textEqual: false } ) ];

		const { failed, messages } = summarizeResults( results );

		expect( failed ).toBe( true );
		expect( messages ).toEqual( [
			'post 1 (post-1): text content changed',
		] );
	} );

	it( '--allow-freeform still lets a freeform/html fallback through, independent of text-mismatch handling', async () => {
		const { summarizeResults } = await import( '../lib/summarize.mjs' );

		const results = [ fakeResult( 1, { freeform: 1 } ) ];

		const allowed = summarizeResults( results, { allowFreeform: true } );
		expect( allowed.failed ).toBe( false );

		const disallowed = summarizeResults( results );
		expect( disallowed.failed ).toBe( true );
	} );

	it( 'mergedParagraphs > 0 fails without --allow-merged-paragraphs, passes with it (R2-01)', async () => {
		const { summarizeResults } = await import( '../lib/summarize.mjs' );

		const results = [ fakeResult( 1, { mergedParagraphs: 2 } ) ];

		const disallowed = summarizeResults( results );
		expect( disallowed.failed ).toBe( true );
		expect( disallowed.messages ).toEqual( [
			'post 1 (post-1): 2 merged paragraph block(s)',
		] );

		const allowed = summarizeResults( results, {
			allowMergedParagraphs: true,
		} );
		expect( allowed.failed ).toBe( false );
	} );
} );

describe( 'convert-classic (rawHandler under jsdom)', () => {
	const editor = loadEditor();
	const maybeIt = editor ? it : it.skip;

	if ( ! editor ) {
		// eslint-disable-next-line no-console
		console.warn(
			'SKIPPED: @wordpress/block-library could not load under this Jest environment (spike Outcome B — see docs/spikes/P8-01.md).'
		);
	}

	maybeIt(
		'converter reports text equality for the https-everywhere post',
		async () => {
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html = fixturePost( '6917' );

			const result = convertPost(
				{
					id: 6917,
					slug: 'the-value-of-https-everywhere',
					content_raw: html,
				},
				editor
			);

			expect( result.report.textEqual ).toBe( true );
			expect( result.report.freeform ).toBe( 0 );
		}
	);

	maybeIt(
		'converter emits paragraphs and headings for the first fixture post',
		async () => {
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html = fixturePost( '6917' );
			const result = convertPost(
				{
					id: 6917,
					slug: 'the-value-of-https-everywhere',
					content_raw: html,
				},
				editor
			);

			expect(
				result.report.blockCounts[ 'core/paragraph' ]
			).toBeGreaterThan( 0 );
			expect(
				result.report.blockCounts[ 'core/heading' ]
			).toBeGreaterThan( 0 );
		}
	);

	maybeIt(
		'rawHandler maps the pre-pass output to core/code, core/audio, core/image and footnote markers',
		async () => {
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html = [
				shortcodeFixturePost( '100' ),
				shortcodeFixturePost( '102' ),
				shortcodeFixturePost( '104' ),
				shortcodeFixturePost( '105' ),
			].join( '\n' );

			const result = convertPost(
				{ id: 900, slug: 'shortcode-mix', content_raw: html },
				editor
			);

			expect( result.report.blockCounts[ 'core/code' ] ).toBeGreaterThan(
				0
			);
			expect( result.report.blockCounts[ 'core/audio' ] ).toBeGreaterThan(
				0
			);
			expect( result.report.blockCounts[ 'core/image' ] ).toBeGreaterThan(
				0
			);
			expect( result.footnotes ).toHaveLength( 1 );
			expect( result.blocks ).toContain( 'ref-900-1' );
		}
	);

	maybeIt(
		'report lists remaining shortcodes and footnote count',
		async () => {
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html = [
				shortcodeFixturePost( '101' ),
				shortcodeFixturePost( '106' ),
			].join( '\n' );

			const result = convertPost(
				{ id: 901, slug: 'shortcode-remaining', content_raw: html },
				editor
			);

			expect( result.report.shortcodes ).toEqual( [
				{ name: 'seoslides', count: 1 },
			] );
			expect( result.report.footnotes ).toBe( 2 );
			expect( result.footnotes ).toHaveLength( 2 );
		}
	);

	maybeIt(
		'textEqual is unaffected by [caption], [gallery] and bare-URL [audio], which rawHandler converts natively',
		async () => {
			// Regression: rawHandler correctly drops [caption]/[gallery]/[audio]'s own bracket
			// syntax when converting them (its own native shortcode transforms), but the pre-pass
			// deliberately never touches them -- so a naive before/after text comparison saw that
			// bracket text on the "before" side only and reported a false mismatch. Found via a
			// real failure on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md.
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html =
				'<p>Some photos from the trip.</p>' +
				'[gallery link="file" columns="2" ids="1,2"]' +
				'<p>And a recording.</p>' +
				'[audio http://example.com/recording.mp3]' +
				'[caption id="attachment_1" align="aligncenter" width="600"]<img src="https://example.com/photo.jpg" alt="" width="600" height="400" /> A caption.[/caption]';

			const result = convertPost(
				{ id: 902, slug: 'native-shortcodes', content_raw: html },
				editor
			);

			expect( result.report.textEqual ).toBe( true );
		}
	);

	/**
	 * R1-09, SPEC §6.8 finding: the pre-2016 bare-URL `[audio http://…]` form (no `src=`
	 * attribute) survived conversion as literal paragraph text on several real posts
	 * (`podcast-episode-*`) -- `rawHandler`'s native `core/audio` shortcode transform only
	 * recognizes the attribute form. `transformBareUrlAudioShortcodes` (scripts/lib/
	 * shortcodes.mjs) now normalizes it first, so it reaches `rawHandler` as something its
	 * transform understands and becomes a real `core/audio` block, same as the attribute form.
	 */
	maybeIt(
		'bare-URL [audio http://…] becomes a real core/audio block',
		async () => {
			const { convertPost } = await import( '../convert-classic.mjs' );

			const html =
				'<p>Today I start on my goal to produce a short, weekly audio broadcast.</p>' +
				'[audio http://example.com/episode-one.mp3]';

			const result = convertPost(
				{ id: 903, slug: 'bare-url-audio', content_raw: html },
				editor
			);

			expect( result.report.blockCounts[ 'core/audio' ] ).toBe( 1 );
			expect( result.blocks ).not.toContain( '[audio' );
		}
	);
} );
