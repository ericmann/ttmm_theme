/**
 * Tests for scripts/lib/autop.mjs (R2-01, SPEC §6.8: real `wpautop()` on every classic post).
 */

let autoParagraphPlainText;

beforeAll( async () => {
	const mod = await import( '../lib/autop.mjs' );
	( { autoParagraphPlainText } = mod );
} );

describe( 'autoParagraphPlainText', () => {
	it( 'splits blank-line-separated content after a heading into separate paragraphs', () => {
		const html = '<h2>H</h2>\n\nPara one.\n\nPara two.';

		const result = autoParagraphPlainText( html );

		expect( result ).toContain( '<p>Para one.</p>' );
		expect( result ).toContain( '<p>Para two.</p>' );
		// Regression: the old narrow guard saw the <h2> block-level tag and returned the whole
		// string unchanged, leaving "Para one.\n\nPara two." unwrapped and unsplit.
		expect( result ).not.toBe( html );
	} );

	it( 'wraps blank-line-separated blocks of genuinely plain-text content in <p>', () => {
		const html =
			'First paragraph.\n\n' +
			'[audio http://example.com/episode-one.mp3]\n\n' +
			'Last paragraph.';

		const result = autoParagraphPlainText( html );

		expect( result ).toContain( '<p>First paragraph.</p>' );
		expect( result ).toContain(
			'<p>[audio http://example.com/episode-one.mp3]</p>'
		);
		expect( result ).toContain( '<p>Last paragraph.</p>' );
	} );

	it( 'leaves a multi-line <pre> code block untouched: no <p>/<br> inside', () => {
		const html =
			'<p>Some code:</p>\n\n' +
			'<pre class="wp-block-code"><code>a\n\nb</code></pre>\n\n' +
			'<p>After.</p>';

		const result = autoParagraphPlainText( html );

		const preMatch = result.match(
			/<pre class="wp-block-code"><code>[\s\S]*?<\/code><\/pre>/
		);
		expect( preMatch ).not.toBeNull();
		expect( preMatch[ 0 ] ).toBe(
			'<pre class="wp-block-code"><code>a\n\nb</code></pre>'
		);
		expect( preMatch[ 0 ] ).not.toContain( '<p>' );
		expect( preMatch[ 0 ] ).not.toContain( '<br' );
	} );

	it( 'returns empty/whitespace-only input as empty', () => {
		expect( autoParagraphPlainText( '' ) ).toBe( '' );
		expect( autoParagraphPlainText( '   \n\n  ' ) ).toBe( '' );
	} );
} );
