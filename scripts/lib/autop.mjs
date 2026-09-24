/**
 * Real `wpautop()` for classic content (R2-01, SPEC §6.8): every classic post's paragraph
 * breaks come from a blank line, relying on WordPress's `wpautop` *content filter* to wrap each
 * blank-line-separated block in `<p>…</p>` at render time. `rawHandler({ HTML })` (unlike
 * `rawHandler({ plainText })`) does not run that filter itself, so without running it first,
 * `rawHandler` treats a run of blank-line-separated prose (with or without existing inline
 * markup such as `<em>`/`<a>`) as one unbroken block and converts it into a single
 * `core/paragraph` (or `core/freeform`) block instead of one block per source paragraph.
 *
 * R1-09 found this on a handful of posts with *no* HTML markup at all (`podcast-episode-*`) and
 * added a narrow guard scoped to that one shape. The real export shows the same collapse on the
 * majority of classic posts -- 553 of 724 -- because plenty of classic-editor "Visual" tab
 * content is itself just inline-tagged prose (`<em>`, `<a>`, `<strong>`) separated by blank
 * lines with no *block-level* tag at all, which the old guard's regex treated as "already has
 * real markup" and left completely alone. `@wordpress/autop`'s `autop()` is the actual function
 * the block editor runs on `core/freeform` content before "Convert to blocks", including its
 * own `<pre>`-tag protection (a `<pre>` block's content, including any blank lines a `<code>`
 * snippet's source happens to contain, is filtered out before the blank-line split and restored
 * unchanged afterward) -- so a `[cc]`/`[cci]` shortcode already rewritten by
 * `preprocessShortcodes` into `<pre class="wp-block-code"><code>…</code></pre>` by the time this
 * runs is left byte-for-byte alone, per rule 49 (non-destructive) and rule 52 (a synthetic test
 * covers the class fix, not live text).
 *
 * Run on every classic post's HTML, unconditionally -- there is no longer a narrower guard to
 * decide against: `autop()` itself already no-ops on content that has no bare blank-line
 * paragraph breaks to fill in (SPEC §6.8, rule 49).
 */

import { autop } from '@wordpress/autop';

/**
 * Real wpautop, applied unconditionally.
 *
 * @param {string} html Raw classic content, after the shortcode pre-pass.
 * @return {string} `html` with `<p>`/`<br>` inserted the way WordPress's `wpautop` filter would
 *   at render time; `<pre>` block contents are left untouched.
 */
export function autoParagraphPlainText( html ) {
	return autop( html );
}
