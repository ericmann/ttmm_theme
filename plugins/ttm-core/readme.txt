=== These Things Matter — Core ===
Contributors: ericmann
Tags: blocks, block-bindings, newspaper, series, block-theme
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Data, blocks, bindings, cron, CLI and migration tooling for the These Things Matter block theme.

== Description ==

These Things Matter — Core owns everything that must survive a theme switch: custom post
meta, taxonomies, queries, block bindings, cron jobs, the `wp ttm` CLI and the classic-content
migration tooling. It renders nothing on its own — presentation lives entirely in the
companion **These Things Matter** block theme, which this plugin's blocks and bindings feed.

Install and activate both the theme and this plugin together; the theme's blocks and template
parts read their data from this plugin's block-binding sources, custom fields and taxonomies,
and several theme patterns bind to `Config::author_name()` and `Config::author_url()` for the
site owner's byline.

== Installation ==

1. Install and activate the **These Things Matter** theme.
2. Upload and activate this plugin.
3. Run `wp ttm seed` (or use the admin Tools screen) to populate demo content, or begin
   publishing directly — every block and template degrades gracefully with no content.

== Changelog ==

= 0.3.0 =
* Tested on PHP 8.4 and WordPress 7.1.4.
* Cacheable responses add `stale-while-revalidate` and `stale-if-error` (new Config keys
  `cache.stale_revalidate_seconds` and `cache.stale_if_error_seconds`; `0` omits a directive),
  so a CDN can keep serving pages while the origin is unreachable.
* `wp ttm seed` and its reset refuse to wipe content unless `WP_ENVIRONMENT_TYPE` is `local`
  or `development` (staging is now refused, like production).

= 0.2.0 =
* Demo content: a full set of seeded posts, pages and series with CC0/PDM-licensed
  photographs, ready for a Playground blueprint or a fresh install.
* Playground blueprint for one-click evaluation.
* Licence hygiene: GPL-2.0-or-later throughout, an FSF-identical `LICENSE`, and the owner's
  name confined to `Config::author_name()`.
* New `site.author_name`/`site.author_url` Config keys and the `ttm/author-name` block-binding
  source, replacing the hard-coded byline in the footer and both mastheads.

= 0.1.0 =
* Initial release: custom post meta, taxonomies, queries, 19 blocks, block-binding sources,
  cron-driven verse fetching, newsletter provider abstraction, the `wp ttm` CLI and classic
  WXR migration tooling.
