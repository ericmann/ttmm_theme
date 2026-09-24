/**
 * Minimal, scoped `wpautop()`-equivalent for one specific classic-content shape (R1-09, SPEC
 * §6.8 finding): a handful of real posts (`podcast-episode-*`, pre-2016) were authored via the
 * classic editor's "Text" tab with no HTML markup at all -- just plain prose and shortcodes
 * separated by blank lines, relying entirely on WordPress's own `wpautop` *content filter* to
 * wrap each blank-line-separated block in `<p>…</p>` at render time. `rawHandler({ HTML })`
 * (unlike `rawHandler({ plainText })`) does not run that filter itself, so without this step the
 * whole raw blob -- prose and a following `[audio …]` shortcode alike -- gets treated as one
 * unbroken paragraph and converted into a single `core/paragraph` block, leaving the shortcode
 * embedded as inline text instead of its own recognizable block (found on the real export: an
 * `[audio http://…]` line merged into the preceding sentence's paragraph never became a
 * `core/audio` block at all, regardless of `transformBareUrlAudioShortcodes`'s own fix).
 *
 * Deliberately narrow, not a full `wpautop()` port: only runs when the raw content has *no*
 * block-level HTML tag anywhere (a strong, safe signal this is genuinely unformatted classic
 * text, not content the visual editor already wrapped in `<p>`/`<ul>`/etc. -- touching content
 * that already has real markup risks re-splitting paragraphs the editor deliberately kept
 * together, e.g. a `<br>`-joined address block). When it does run, it only splits on blank
 * lines and wraps each resulting block in `<p>`, same as `wpautop()`'s first pass; it does not
 * attempt list/heading/blockquote detection since none of the affected real posts use them.
 *
 * @param {string} html Raw classic content.
 * @return {string} `html` unchanged if it already contains block-level HTML; otherwise each
 *   blank-line-separated block wrapped in `<p>…</p>`.
 */
export function autoParagraphPlainText( html ) {
	if (
		/<(p|div|ul|ol|li|blockquote|pre|h[1-6]|table|figure)\b/i.test( html )
	) {
		return html;
	}

	const blocks = html
		.split( /\n\s*\n+/ )
		.map( ( block ) => block.trim() )
		.filter( Boolean );

	if ( blocks.length === 0 ) {
		return html;
	}

	return blocks.map( ( block ) => `<p>${ block }</p>` ).join( '\n\n' );
}
