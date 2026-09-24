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
			'<pre class="wp-block-code"><code lang="php">'
		);
		expect( transformed ).toContain(
			'return $a &lt; $b &amp;&amp; $b &gt; 0;'
		);
		expect( transformed ).not.toContain( '[cc_php]' );
	} );

	it( 'converts a single-line cci/cc to inline code, not a code block', () => {
		// Real classic content uses [cci]/[cc] both for multi-line snippets (its own paragraph)
		// and for a single short term inline in a sentence -- converting the inline form to a
		// block-level <pre> splits the sentence around it into separate paragraphs, changing the
		// visible text. Discovered via a real textEqual failure on the export; see
		// docs/feedback/phase-4/LIVE-TRIAGE.md.
		const html = fixturePost( '108' );
		const { html: transformed } = preprocessShortcodes( html, 108 );

		expect( transformed ).toContain( '<code lang="">global</code>' );
		expect( transformed ).toContain( '<code lang="">$post</code>' );
		expect( transformed ).not.toContain( '<pre' );
		expect( transformed ).not.toContain( '[cci' );
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

	/**
	 * R1-09, SPEC §6.8 finding: the pre-2016 `[audio]` shortcode accepted a bare URL as its
	 * unnamed default attribute; `rawHandler`'s native `core/audio` transform only recognizes
	 * the `src="…"` attribute form, so a bare URL survived conversion as literal text on real
	 * posts (`podcast-episode-*`). This normalizes it to the attribute form the transform
	 * understands.
	 */
	it( 'normalizes a bare-URL [audio] shortcode to the src= attribute form', () => {
		const html =
			'Some intro text.\n\n' +
			'[audio http://example.com/episode-one.mp3]';

		const { html: transformed } = preprocessShortcodes( html, 200 );

		expect( transformed ).toContain(
			'[audio src="http://example.com/episode-one.mp3"]'
		);
		expect( transformed ).not.toContain( '[audio http' );
	} );

	it( 'leaves an already-attributed [audio] shortcode untouched', () => {
		const html = '[audio src="https://example.com/a.mp3"]';

		const { html: transformed } = preprocessShortcodes( html, 201 );

		expect( transformed ).toBe( html );
	} );
} );

// SI-17: CodeColorer's block-level <code lang="x">…</code> HTML tag syntax, distinct from the
// [cci]/[cc]/[cc_x] bracket shortcodes above. All fixture bodies here are synthetic (rule 47).
describe( 'CodeColorer tag syntax', () => {
	it( 'shape 1: converts a block-level multi-line <code lang> to core/code', () => {
		const html =
			'<p>Before.</p>\n' +
			'<code lang="php">$a = 1;\n$b = 2;</code>\n' +
			'<p>After.</p>';

		const { html: transformed } = preprocessShortcodes( html, 300 );

		expect( transformed ).toContain(
			'<pre class="wp-block-code"><code lang="php">$a = 1;\n$b = 2;</code></pre>'
		);
		expect( transformed ).toContain( '<p>Before.</p>' );
		expect( transformed ).toContain( '<p>After.</p>' );
	} );

	it( 'shape 2: drops extra width/height attributes', () => {
		const html =
			'<code lang="php" width="570px" height="840">$a = 1;\n$b = 2;</code>';

		const { html: transformed } = preprocessShortcodes( html, 301 );

		expect( transformed ).toBe(
			'<pre class="wp-block-code"><code lang="php">$a = 1;\n$b = 2;</code></pre>'
		);
		expect( transformed ).not.toContain( 'width' );
		expect( transformed ).not.toContain( 'height' );
	} );

	it( 'shape 3: an already <pre>-wrapped <code lang> stays a single, non-nested <pre>', () => {
		const html = '<pre><code lang="bash">echo one\necho two</code></pre>';

		const { html: transformed } = preprocessShortcodes( html, 302 );

		expect( transformed ).toBe(
			'<pre class="wp-block-code"><code lang="bash">echo one\necho two</code></pre>'
		);
		expect( transformed ).not.toContain( '<pre><pre' );
		expect( transformed ).not.toContain( '</pre></pre>' );
	} );

	it( 'shape 4: escapes a raw <?php line and < / > comparisons, nothing swallowed into a comment', () => {
		const html =
			'<code lang="php"><?php\nif ( $a < $b && $b > 0 ) {\n\treturn $a->value;\n}</code>';

		const { html: transformed } = preprocessShortcodes( html, 303 );

		expect( transformed ).toContain( '&lt;?php' );
		expect( transformed ).toContain( '$a &lt; $b &amp;&amp; $b &gt; 0' );
		expect( transformed ).toContain( '$a-&gt;value' );
		expect( transformed ).not.toContain( '<!--' );
		expect( transformed ).not.toContain( '<?php\nif' );
	} );

	it( 'shape 5: preserves existing entities instead of double-escaping them', () => {
		const html =
			'<code lang="html">&lt;div&gt;\nif ( $a &amp;&amp; $b ) {}</code>';

		const { html: transformed } = preprocessShortcodes( html, 304 );

		expect( transformed ).toContain( '&lt;div&gt;' );
		expect( transformed ).toContain( '$a &amp;&amp; $b' );
		expect( transformed ).not.toContain( '&amp;lt;' );
		expect( transformed ).not.toContain( '&amp;amp;' );
	} );

	it( 'leaves a single-line inline <code lang> unchanged', () => {
		const html = '<p>Set the <code lang="php">$post</code> variable.</p>';

		const { html: transformed } = preprocessShortcodes( html, 305 );

		expect( transformed ).toBe( html );
	} );

	it( 'leaves <code> without lang unchanged', () => {
		const html = '<code>$a = 1;\n$b = 2;</code>';

		const { html: transformed } = preprocessShortcodes( html, 306 );

		expect( transformed ).toBe( html );
	} );

	it( 'is idempotent', () => {
		const html = '<code lang="php" width="570px">$a = 1;\n$b = 2;</code>';

		const once = preprocessShortcodes( html, 307 ).html;
		const twice = preprocessShortcodes( once, 307 ).html;

		expect( twice ).toBe( once );
	} );

	it( 'runs inside preprocessShortcodes before autop', async () => {
		const { prepareClassicHtml } =
			await import( '../lib/prepare-classic.mjs' );

		const { html } = prepareClassicHtml(
			'Intro.\n\n<code lang="php">a\n\nb</code>\n\nOutro.',
			308
		);

		const codeMatch = html.match(
			/<pre class="wp-block-code"><code lang="php">([\s\S]*?)<\/code><\/pre>/
		);
		expect( codeMatch ).not.toBeNull();
		expect( codeMatch[ 1 ] ).toBe( 'a\n\nb' );
		expect( html ).not.toContain( '<p><pre' );
		expect( html ).not.toContain( '</pre></p>' );
		expect( html ).toContain( '<p>Intro.</p>' );
		expect( html ).toContain( '<p>Outro.</p>' );
	} );
} );
