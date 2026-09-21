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
} );
