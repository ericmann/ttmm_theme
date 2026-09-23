/**
 * Tests for the P3-01 shortcode pre-pass (scripts/lib/shortcodes.mjs, SPEC §6.8).
 */

const { readFileSync } = require( 'fs' );
const path = require( 'path' );

const FIXTURE_PATH = path.join(
	__dirname,
	'..',
	'..',
	'docs',
	'fixtures',
	'classic-shortcodes.html'
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

let preprocessShortcodes;

beforeAll( async () => {
	const mod = await import( '../lib/shortcodes.mjs' );
	( { preprocessShortcodes } = mod );
} );

describe( 'preprocessShortcodes', () => {
	it( 'converts [ref] to a core footnote marker and collects the note', () => {
		const html = fixturePost( '100' );
		const { html: transformed, footnotes } = preprocessShortcodes(
			html,
			100
		);

		expect( footnotes ).toHaveLength( 1 );
		expect( footnotes[ 0 ] ).toEqual( {
			id: 'ref-100-1',
			content: 'Names in this account have been changed for clarity.',
		} );
		expect( transformed ).not.toContain( '[ref]' );
		expect( transformed ).toContain(
			'<sup data-fn="ref-100-1" class="fn"><a href="#ref-100-1" id="ref-100-1-link">1</a></sup>'
		);
	} );

	it( 'numbers [ref] and [mfn] in order of appearance', () => {
		const html = fixturePost( '101' );
		const { footnotes } = preprocessShortcodes( html, 101 );

		expect( footnotes ).toHaveLength( 2 );
		expect( footnotes[ 0 ].id ).toBe( 'ref-101-1' );
		expect( footnotes[ 0 ].content ).toContain( 'pseudonym' );
		expect( footnotes[ 1 ].id ).toBe( 'ref-101-2' );
		expect( footnotes[ 1 ].content ).toContain( "ship's log" );
	} );

	it( 'converts cci and cc to a code block preserving entities', () => {
		const html = fixturePost( '102' );
		const { html: transformed } = preprocessShortcodes( html, 102 );

		expect( transformed ).toContain(
			'<pre class="wp-block-code"><code lang="php">'
		);
		expect( transformed ).toContain(
			'<pre class="wp-block-code"><code lang="js">'
		);
		// The raw content has a literal single-quoted SQL string containing no `<`/`>`, but the
		// JS example does -- assert the entities are escaped exactly once, not left raw and not
		// double-escaped.
		expect( transformed ).toContain( '$wpdb-&gt;get_results' );
		expect( transformed ).not.toContain( '&amp;lt;' );
		expect( transformed ).not.toContain( '[cci' );
		expect( transformed ).not.toContain( '[cc ' );
	} );

	it( 'converts cc_php short form', () => {
		const html = fixturePost( '103' );
		const { html: transformed } = preprocessShortcodes( html, 103 );

		expect( transformed ).toContain(
			'<pre class="wp-block-code"><code lang="php">if ( $a &lt; $b &amp;&amp; $b &gt; 0 ) { return true; }</code></pre>'
		);
		expect( transformed ).not.toContain( '[cc_php]' );
	} );

	it( 'converts [audio] to an audio element', () => {
		// `core/audio` registers its own `type: "shortcode", tag: "audio"` raw transform
		// (verified against this project's @wordpress/blocks version) -- pre-converting
		// `[audio]` to a raw `<audio>` tag here would instead fold it into the surrounding
		// paragraph as ordinary inline content, since @wordpress/blocks' raw-handling pipeline
		// has no `type: "raw"` transform matching a bare `<audio>` element. The pre-pass leaves
		// `[audio]` untouched, same as `[caption]`, and `rawHandler` converts it to a real
		// `core/audio` block itself (asserted end to end in convert-classic.test.js).
		const html = fixturePost( '104' );
		const { html: transformed, remaining } = preprocessShortcodes(
			html,
			104
		);

		expect( transformed ).toBe( html );
		expect( transformed ).toContain(
			'[audio src="https://example.com/recordings/episode-one.mp3"]'
		);
		expect( remaining ).toEqual( [] );
	} );

	it( 'leaves [caption] alone', () => {
		const html = fixturePost( '105' );
		const { html: transformed, remaining } = preprocessShortcodes(
			html,
			105
		);

		expect( transformed ).toBe( html );
		expect( remaining ).toEqual( [] );
	} );

	it( 'leaves seoslides in place and reports it', () => {
		const html = fixturePost( '106' );
		const { html: transformed, remaining } = preprocessShortcodes(
			html,
			106
		);

		expect( transformed ).toContain( '[seoslides id="42"' );
		expect( remaining ).toEqual( [ { name: 'seoslides', count: 1 } ] );
	} );

	it( 'never touches bracketed prose', () => {
		const html = fixturePost( '107' );
		const {
			html: transformed,
			footnotes,
			remaining,
		} = preprocessShortcodes( html, 107 );

		expect( transformed ).toBe( html );
		expect( transformed ).toContain( '[architect]' );
		expect( footnotes ).toEqual( [] );
		expect( remaining ).toEqual( [] );
	} );
} );
