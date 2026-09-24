/**
 * Pure pre-transform: rewrites `modern-footnotes` markup (SPEC §6.7) into a plain
 * `<sup data-fn="..."><a href="#...">N</a></sup>` reference and pulls each note's text out into
 * a `{id, content}` array, so the classic-to-block converter never has to understand the
 * plugin's markup itself. No DOM required (pure regex over well-formed exported HTML).
 */

/**
 * @param {string}        html           The post's raw/rendered content, still carrying modern-footnotes markup.
 * @param {number|string} postId         The post id, used to build stable `mfn-{postId}-{N}` ids.
 * @param {number}        startNumbering The *displayed* footnote number for the first match
 *                                       (default 1); ids still use the source `data-mfn` value, but the visible `n` in the marker
 *                                       continues sequentially from here -- P3-01's shortcode pre-pass passes its own footnote
 *                                       count + 1 so a post with both `[ref]`/`[mfn]` shortcodes and modern-footnotes markup gets
 *                                       one continuous numbering sequence, not two sequences that both restart at 1.
 * @return {{ html: string, footnotes: Array<{ id: string, content: string }> }} The transformed HTML and the extracted footnotes.
 */
export function transformFootnotes( html, postId, startNumbering = 1 ) {
	const footnotes = [];
	let displayNumber = startNumbering;

	// Each <sup class="modern-footnotes-footnote ..." data-mfn="N" ...>...</sup> immediately
	// followed by <span class="modern-footnotes-footnote__note" ...>NOTE</span>.
	const pairPattern =
		/<sup\b[^>]*class="[^"]*modern-footnotes-footnote(?!__note)[^"]*"[^>]*data-mfn="(\d+)"[^>]*>[\s\S]*?<\/sup>\s*<span\b[^>]*class="[^"]*modern-footnotes-footnote__note[^"]*"[^>]*>([\s\S]*?)<\/span>/g;

	let transformed = html.replace( pairPattern, ( match, number, note ) => {
		const id = `mfn-${ postId }-${ number }`;
		const n = displayNumber;
		displayNumber += 1;
		footnotes.push( { id, content: note.trim() } );

		return `<sup data-fn="${ id }" class="fn"><a href="#${ id }" id="${ id }-link">${ n }</a></sup>`;
	} );

	// The trailing "back matter" list the plugin appends to the content.
	transformed = transformed.replace(
		/<ul\b[^>]*class="[^"]*modern-footnotes-list[^"]*"[^>]*>[\s\S]*?<\/ul>/g,
		''
	);

	return { html: transformed, footnotes };
}
