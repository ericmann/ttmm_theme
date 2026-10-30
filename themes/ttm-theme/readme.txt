=== These Things Matter ===
Contributors: ericmann
Tags: block-theme, newspaper, one-column, custom-colors, editor-style
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.3.2
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
