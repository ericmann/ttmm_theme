=== These Things Matter ===
Contributors: ericmann
Tags: block-theme, newspaper, one-column, custom-colors, editor-style
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.3.16
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A newspaper-style block theme for a personal site with sections, series and a journal.

== Description ==

These Things Matter is presentation-only: templates, patterns, block styles and CSS. All data
— custom post meta, taxonomies, queries, block bindings, cron jobs and migration tooling —
lives in the companion **These Things Matter — Core** plugin, which this theme requires.
Install and activate both together.

== Installation ==

1. Upload and activate this theme.
2. Install and activate the **These Things Matter — Core** plugin.
3. Run `wp ttm seed` (or use the admin Tools screen) to populate demo content, or begin
   publishing directly.

== Changelog ==

= 0.3.16 =
* No visual change. Maintenance release with companion plugin 0.3.16: development
  dependencies patched (isomorphic-git security fix).

= 0.3.15 =
* No visual change. Maintenance release with companion plugin 0.3.15: development
  dependencies updated.

= 0.3.14 =
* Comment form: Jetpack's "Notify me" checkboxes sit on one line with their labels, like
  the cookie-consent row, instead of the checkbox and label stacking.

= 0.3.13 =
* No visual change. Companion plugin 0.3.13: a serial staged as drafts stays off public
  pages until its first chapter publishes.

= 0.3.12 =
* No visual change. Companion plugin 0.3.12: purge correctness on publish, trash and delete,
  Cloudflare purge failures surfaced, a fresh page after commenting, a short browser TTL.

= 0.3.11 =
* Comments, built from core blocks (patterns/comments.php). One section on every post, whose
  words follow the post's section: a Discussion on articles (replies three deep, an Author tag
  on the writer's own replies), Reflections on journal entries (flat, newest first, five shown,
  no Reply), Reader notes on fiction (a single closed line after the chapter, two levels deep).
* No "Leave a Reply" form and no "N replies on 'Title'" banner: the form waits behind the
  section's button and opens under a comment when someone clicks Reply ("Replying to Dana ·
  Cancel"). No website field. A held comment reads "Held for review" to the person who wrote it.
* Busy threads collapse, and stay collapsed on the reader's next visit (this browser only);
  comments posted since the last visit are marked new. Styles and script load only on pages
  that render comments.
* Comments close on their own: reader notes 30 days after a chapter or story publishes,
  reflections after 14 days, a discussion after a year. Closed comments stay readable; the
  section's button goes.
* Layout is an editorial choice in the editor's Template panel: "Article" (the standard layout,
  so a Journal entry can opt out), "Article with photo hero" (the journal's receding photograph
  over the article layout, series bar and contents included) and "Journal entry". Journal
  entries keep the journal layout by default.

= 0.3.10 =
* Journal entries open on their featured photograph (Recede): full-bleed under the masthead,
  fading into the page; the entry slides over it as the photo recedes to a grey wash. Long or
  photo-heavy entries get a tall, pinned hero; short ones a shorter band. Reduced motion, or a
  browser without scroll timelines, gets a still photograph. The photo credit sits at its foot.
* Galleries lay out again: theme.json gave them a unitless gap, which broke core's column widths
  and stacked every photo full width. In journal entries, galleries span all three columns in
  justified rows (each frame at its photo's ratio; nothing cropped, order kept).

= 0.3.9 =
* Styles that belong to one block (verse, Writing cell, series bar, progress, contents and
  previous/next) move to assets/css/blocks/ and load only on pages that render that block.
  No visual change; ttm.css drops from 64,497 to under 56,000 bytes.
* Code blocks are keyboard-scrollable: the block's pre is the one scroll box and takes focus
  (tabindex="0"), fixing an axe scrollable-region-focusable failure with highlighted code.

= 0.3.8 =
* Static pages show the featured image (the About portrait) in the right-hand column, 3:4, beside the text.
* The inner masthead links Newsletter, RSS and About like the front page; RSS is the category's feed on a category archive.
* Writing links point at the Writing page, its canonical URL (mastheads, footer, starter Sections menu).

= 0.3.7 =
* "In print" book covers keep their own shape and stand on one baseline, so titles line up.
* Newsletter copy matches per-post delivery: poster "Everything above, in your inbox."; box
  "Each post by email as it publishes, or as a daily or weekly digest."
* Starter content stores page templates as slugs ("page-series", "default"), not file names.

= 0.3.6 =
* Syntax colours for code blocks, from the theme palette, when the site runs the
  Syntax-highlighting Code Block plugin (server-side; no front-end script). The stylesheet
  loads only on pages with a code block.

= 0.3.5 =
* Cover images sit flush in their frame (no light band under the Writing-hero cover).

= 0.3.4 =
* Editor's note and Update callouts for older posts: Group block styles "Note" and "Update",
  plus insertable patterns with a label and a placeholder paragraph.

= 0.3.3 =
* No theme changes; version kept in step with the plugin.

= 0.3.2 =
* The block editor shows posts with the published article styles (paragraph and heading
  spacing, lists, quotes) instead of running blocks together.
* Theme CSS and JS URLs carry the theme version, so a release always busts browser caches.

= 0.3.1 =
* The inner masthead stays pinned to the top while scrolling (below the admin bar), and the
  article aside sits clear of it.
* Masthead bylines are hidden when the site title already names the author.
* Front-page and archive photos are washed out (low saturation) instead of black and white.
* Verse of the day is clamped to four lines.
* Phone layouts no longer overflow on long code lines, long words, tag rows or long series bars.

= 0.3.0 =
* Tested on PHP 8.4 and WordPress 7.1.4. No visual changes.

= 0.2.0 =
* Demo content and a Playground blueprint for one-click evaluation.
* Licence hygiene: GPL-2.0-or-later throughout, an FSF-identical `LICENSE`, and the owner's
  name confined behind an author-name setting instead of hard-coded in the mastheads/footer.

= 0.1.0 =
* Initial release: front-page and inner templates, header/footer/rail parts, patterns, block
  styles and the `ttm.css` stylesheet.

== Copyright ==

These Things Matter bundles the Archivo typeface.

Archivo, Copyright 2020 The Archivo Project Authors
(https://github.com/Omnibus-Type/Archivo), licensed under the SIL Open Font License, Version
1.1. Full text: `assets/fonts/OFL.txt` and https://openfontlicense.org.

All other theme code and assets are licensed under the GNU General Public License v2.0 or
later, as noted above.
