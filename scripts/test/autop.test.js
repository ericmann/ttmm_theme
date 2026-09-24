/**
 * Tests for scripts/lib/autop.mjs (R1-09, SPEC §6.8 finding).
 */

let autoParagraphPlainText;

beforeAll( async () => {
	const mod = await import( '../lib/autop.mjs' );
	( { autoParagraphPlainText } = mod );
} );

describe( 'autoParagraphPlainText', () => {
	it( 'wraps blank-line-separated blocks of genuinely plain-text content in <p>', () => {
		const html =
			'First paragraph.\n\n' +
			'[audio http://example.com/episode-one.mp3]\n\n' +
			'Last paragraph.';

		const result = autoParagraphPlainText( html );

		expect( result ).toBe(
			'<p>First paragraph.</p>\n\n' +
				'<p>[audio http://example.com/episode-one.mp3]</p>\n\n' +
				'<p>Last paragraph.</p>'
		);
	} );

	it( 'leaves content that already has block-level HTML untouched', () => {
		const html = '<p>Already a paragraph.</p>\n\nMore text.';

		expect( autoParagraphPlainText( html ) ).toBe( html );
	} );

	it( 'leaves content with a heading untouched, even if other runs are plain text', () => {
		const html = 'Intro text.\n\n<h2>A heading</h2>\n\nMore text.';

		expect( autoParagraphPlainText( html ) ).toBe( html );
	} );

	it( 'returns empty/whitespace-only input unchanged', () => {
		expect( autoParagraphPlainText( '' ) ).toBe( '' );
		expect( autoParagraphPlainText( '   \n\n  ' ) ).toBe( '   \n\n  ' );
	} );
} );
