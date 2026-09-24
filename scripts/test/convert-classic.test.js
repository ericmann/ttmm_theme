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
} );

describe( 'summarizeResults (no jsdom/block-library needed, R1-03)', () => {
	// The CLI's exit-decision logic, extracted so it's testable without setUpBlockEditorEnvironment()
	// (jsdom + @wordpress/block-library -- unavailable under this Jest environment, spike Outcome B).
	function fakeResult( id, overrides = {} ) {
		return {
			id,
			slug: `post-${ id }`,
			report: { textEqual: true, freeform: 0, html: 0, ...overrides },
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
} );
