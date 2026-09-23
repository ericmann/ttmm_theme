/**
 * Pure pre-pass: converts the export's known shortcodes to the markup `rawHandler` maps to
 * blocks (SPEC §6.8), run before `transformFootnotes`. No DOM required (pure regex over
 * well-formed exported HTML). The matcher is an alternation of the known names only -- never a
 * generic `\[[a-z_]+` pattern -- so bracketed prose (`[architect]`, `[i]`, `[my]`…) is never
 * touched.
 */

const ESCAPE_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;' };

/**
 * Escape `&`, `<`, `>` exactly once each (SPEC §6.8: "entities preserved").
 *
 * @param {string} text Raw shortcode inner content.
 * @return {string} Escaped text, safe inside a `<code>` element.
 */
function escapeOnce( text ) {
	return text.replace( /[&<>]/g, ( char ) => ESCAPE_MAP[ char ] );
}

/**
 * `[ref]…[/ref]` and `[mfn]…[/mfn]` -> core footnote markers, numbered by order of appearance
 * across both names, in a single `ref-<postId>-<n>` id namespace (distinct from
 * `transformFootnotes`' own `mfn-<postId>-<n>` ids for modern-footnotes markup).
 *
 * @param {string}        html   HTML to search.
 * @param {number|string} postId Post id, for the marker ids.
 * @return {{html: string, footnotes: Array<{id: string, content: string}>}} Transformed HTML
 *   and the extracted footnotes, in order of appearance.
 */
function transformRefShortcodes( html, postId ) {
	const footnotes = [];
	let n = 0;

	const transformed = html.replace(
		/\[(ref|mfn)\]([\s\S]*?)\[\/\1\]/g,
		( match, name, content ) => {
			n += 1;
			const id = `ref-${ postId }-${ n }`;
			footnotes.push( { id, content: content.trim() } );

			return `<sup data-fn="${ id }" class="fn"><a href="#${ id }" id="${ id }-link">${ n }</a></sup>`;
		}
	);

	return { html: transformed, footnotes };
}

/**
 * `[cci lang="x"]…[/cci]` and `[cc lang="x" …]…[/cc]` -> a plain code block, language read from
 * the `lang` attribute (empty string when absent).
 *
 * @param {string} html HTML to search.
 * @return {string} Transformed HTML.
 */
function transformLongCodeShortcodes( html ) {
	return html.replace(
		/\[(cci|cc)\b([^\]]*)\]([\s\S]*?)\[\/\1\]/g,
		( match, name, attrs, content ) => {
			const langMatch = attrs.match( /\blang="([^"]*)"/ );
			const lang = langMatch ? langMatch[ 1 ] : '';

			return `<pre class="wp-block-code"><code lang="${ lang }">${ escapeOnce(
				content
			) }</code></pre>`;
		}
	);
}

/**
 * `[cc_php]…[/cc_php]` (language named in the tag itself) -> the same plain code block shape as
 * `transformLongCodeShortcodes`.
 *
 * @param {string} html HTML to search.
 * @return {string} Transformed HTML.
 */
function transformShortCodeShortcodes( html ) {
	return html.replace(
		/\[cc_([a-z0-9_]+)\]([\s\S]*?)\[\/cc_\1\]/g,
		( match, lang, content ) =>
			`<pre class="wp-block-code"><code lang="${ lang }">${ escapeOnce(
				content
			) }</code></pre>`
	);
}

/**
 * `[seoslides …]` is left in place (SPEC §6.8, Q12) -- only counted, for `convert:import
 * --dry-run`/`audit --only=shortcode` to list later.
 *
 * @param {string} html HTML to search.
 * @return {Array<{name: string, count: number}>} Non-empty entries only.
 */
function remainingShortcodes( html ) {
	const seoslidesCount = ( html.match( /\[seoslides\b[^\]]*\]/g ) || [] )
		.length;

	return seoslidesCount > 0
		? [ { name: 'seoslides', count: seoslidesCount } ]
		: [];
}

/**
 * Run every known shortcode conversion, in order, over one post's raw classic content.
 * `[caption]` and `[audio]` are both left untouched: `rawHandler` already maps `[caption]` to
 * `core/image` with a caption, and `core/audio` registers a `type: "shortcode", tag: "audio"`
 * transform of its own (verified against this project's `@wordpress/blocks`/
 * `@wordpress/block-library` versions) -- pre-converting `[audio]` to a raw `<audio>` tag here
 * would instead leave it as ordinary inline phrasing content, folded into the surrounding
 * paragraph rather than recognized as its own block.
 *
 * @param {string}        html   The post's raw classic content.
 * @param {number|string} postId Post id, for footnote marker ids.
 * @return {{html: string, footnotes: Array<{id: string, content: string}>, remaining: Array<{name: string, count: number}>}}
 *   Transformed HTML, extracted footnotes (order of appearance), and any shortcodes
 *   deliberately left in place.
 */
export function preprocessShortcodes( html, postId ) {
	const remaining = remainingShortcodes( html );

	const { html: afterRef, footnotes } = transformRefShortcodes(
		html,
		postId
	);
	const afterLongCode = transformLongCodeShortcodes( afterRef );
	const afterShortCode = transformShortCodeShortcodes( afterLongCode );

	return { html: afterShortCode, footnotes, remaining };
}
