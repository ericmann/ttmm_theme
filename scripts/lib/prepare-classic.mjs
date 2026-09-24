/**
 * The classic-content pipeline that runs before `rawHandler()` ever sees the HTML (R3-02, SPEC
 * §6.8, rule 49): shortcode pre-pass (`preprocessShortcodes`) -> `wpautop()`-equivalent
 * paragraph wrapping (`autoParagraphPlainText`) -> footnote transform (`transformFootnotes`), in
 * that fixed order. Pure (no jsdom/block-library, no I/O), split out the same way
 * scripts/lib/report.mjs and scripts/lib/summarize.mjs were (see their docblocks) so this is
 * testable -- and importable at all under this Jest environment -- without pulling in
 * scripts/convert-classic.mjs itself (its own auto-run guard and top-level jsdom/block-library
 * `require()` calls don't load cleanly under Jest's module system, unlike a plain lib module).
 * scripts/convert-classic.mjs's `convertPost()` is the only production caller.
 */

import { transformFootnotes } from './footnotes.mjs';
import { preprocessShortcodes } from './shortcodes.mjs';
import { autoParagraphPlainText } from './autop.mjs';

/**
 * Order matters and is exactly what this function pins: shortcode pre-pass must run first so a
 * multi-line shortcode body (e.g. `[cc]a\n\nb[/cc]`) is replaced with its final tag markup
 * *before* `autop()` ever sees the blank line inside it -- autop-ing first would wrap that inner
 * blank line in `<p>`/`<br>` tags as if it were prose, corrupting the code block's body (the
 * bug this pins down). Footnote transform runs last so it sees fully-autopped prose.
 *
 * @param {string}        content Raw classic `post_content`.
 * @param {number|string} postId  Post ID (footnote/shortcode ID namespacing).
 * @return {{ html: string, footnotes: Array, remaining: Array }} The HTML ready for
 *                                                                 `rawHandler()`, the combined
 *                                                                 shortcode + modern-footnotes
 *                                                                 list, and any shortcode
 *                                                                 deliberately left in place.
 */
export function prepareClassicHtml( content, postId ) {
	const {
		html: afterShortcodes,
		footnotes: shortcodeFootnotes,
		remaining,
	} = preprocessShortcodes( content, postId );

	const autopped = autoParagraphPlainText( afterShortcodes );

	const { html: transformedHtml, footnotes: mfnFootnotes } =
		transformFootnotes( autopped, postId, shortcodeFootnotes.length + 1 );

	const footnotes = [ ...shortcodeFootnotes, ...mfnFootnotes ];

	return { html: transformedHtml, footnotes, remaining };
}
