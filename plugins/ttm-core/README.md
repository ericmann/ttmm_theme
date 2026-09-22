# ttm-core

Companion plugin for the "These Things Matter" block theme (`themes/ttm-theme`). It owns
everything that must survive a theme switch: content model (taxonomy, post/term meta), blocks,
block bindings, cron (the daily verse fetch), cache headers/purge, the newsletter handler, and
all `wp ttm …` CLI/migration tooling. See `docs/SPEC.md` for the full design and `docs/PLAN.md`
for how it was built; this file documents the plugin as it actually ships.

## What it owns

- **Content model:** the `series` taxonomy; post meta (`ttm_primary_category`, `ttm_form`,
  `ttm_series_part`, `ttm_word_count`, `ttm_syndication`, `ttm_location`,
  `ttm_featured_in_section`, `ttm_form_locked`, `ttm_classic_backup`, `ttm_converted_at`) and
  term meta on `series` (`ttm_status`, `ttm_form`, `ttm_next_date`, `ttm_purchase_links`,
  `ttm_cover_id`); the `ttm_series_index`, `ttm_verse*`, `ttm_books`, `ttm_settings`, and
  `ttm_redirects` options.
- **Blocks:** every `ttm/*` block under `blocks/` (server-rendered, no front-end JS beyond the
  editor bundle) and their block-binding sources: `ttm/kicker`, `ttm/meta-line`,
  `ttm/short-date`, `ttm/relative-date`, `ttm/category-count`, `ttm/today`, `ttm/reading-time`,
  `ttm/word-count`, `ttm/journal-subline`, `ttm/series-name`, `ttm/series-part`,
  `ttm/pagination-label`, and `ttm/verse-copyright` (the footer's NIV notice, plain text, empty
  unless `verse.copyright_placement === 'footer'` and a verse is stored). `ttm/series-list`'s
  `layout` attribute gains `strip` (SPEC §6.1.7): one `ttm-series-row__meta` line per row
  ("{Category} · {Category} · {N} of {M}", plus " · {cadence}" when set) instead of the
  `rows`/`grid-2`/`grid-3` layouts' separate dek/categories/count spans.
- **Cache:** `Cache/Headers.php` (computed `Cache-Control`), `Cache/Batcache.php`,
  `Cache/Purge.php`/`Cache/Cloudflare.php` (the `ttm_purge_urls` action and its Cloudflare
  adapter).
- **Newsletter:** the stateless-token form handler and pluggable providers (Jetpack, mailto,
  custom URL). Every provider except `none` renders through `Newsletter\Form::render()`, the
  one `.ttm-newsletter-form__form` markup shape (SPEC §6.3). `custom-url` accepts submissions
  locally (no forward, no log) whenever `newsletter.endpoint` is empty, `newsletter.dev_accept`
  is true, and the site isn't in production — this is what `wp ttm seed` configures, so the dev
  poster shows and submits a real form without a real endpoint configured. `jetpack` is
  available only when Jetpack is actually connected (`Jetpack::is_connection_ready()`) or, for
  tests, when `jetpack/subscriptions` is registered; it renders the same shared form posting to
  the site's own `admin_post_ttm_subscribe` handler that `custom-url` uses (built from the same
  `Form::handler_fields()` helper), never Jetpack's own widget markup — that widget's POST
  requires a per-visitor nonce (`docs/spikes/P1-jetpack-form.md`, "Handler (review R1-01)"),
  which rule 7 forbids on cacheable output. `Handler::handle()` calls
  `Jetpack_Subscriptions::init()->subscribe()` (the same method the widget's own handler calls)
  directly once the shared token/honeypot/rate-limit checks pass, guarded by `class_exists()`
  since Jetpack is never installed in wp-env (non-goal). An installed-but-unconnected Jetpack
  falls through the chain like any other unavailable provider.
- **Cron/fetch:** the daily verse fetch (`Verse\Fetcher`), the only scheduled outbound request.
- **Migration/maintenance:** every `wp ttm …` command below.

It never touches presentation: no `wp_enqueue_style`, no inline `<style>`, no CSS. The theme
reads plugin data only through `ttm/*` blocks, block bindings, and the REST routes below, always
guarded with `function_exists()`/`class_exists()` (SPEC §3.1 rules 2–3).

## Requirements

- WordPress ≥ 6.7 (developed and tested against the live site's 7.1.1).
- PHP ≥ 8.1 (CI and `wp-env` run 8.3).
- `themes/ttm-theme` active for full presentation; the plugin degrades gracefully alone (no
  fatals) if a different theme is active — see `tests/integration/Separability/PluginAloneTest.php`.

## Hooks

| Hook | Type | Args |
|---|---|---|
| `ttm_config` | filter | `array $defaults` — overlays/replaces `Config::defaults()`. |
| `ttm_now` | filter | `DateTimeImmutable $now` — the one place "now" can be overridden (tests). |
| `ttm_lead_post_id` | filter | `int $id, string $reason` — override the computed lead story. |
| `ttm_series_index` | filter | `array $index` — runs after `SeriesIndex::rebuild()` computes the index, before it's stored in the `ttm_series_index` option. |
| `ttm_verse_parsed` | filter | `array $verse, array $raw_item` — the parsed verse record before it's stored. |
| `ttm_purge_urls` | action | `string[] $urls` — fired on every publish/unpublish transition and by CLI migrations; `Cache\Cloudflare` listens when configured. |
| `ttm_cache_max_age` | filter | `int $seconds, DateTimeImmutable $now` — override the computed `Cache-Control` lifetime. |
| `ttm_newsletter_subscribed` | action | `string $email_hash, string $provider` — a sha256 hash, never the raw email. |
| `ttm_section_feeds` | filter | `array $slug => $url` — the theme's `<link rel=alternate>` list. |

## REST

All routes are under `/wp-json/ttm/v1/`, `GET` only, public (`permission_callback =>
'__return_true'`), and carry the same computed cache headers as front-end pages:

- `GET /series` — the series index, with `?status=` / `?form=` filters. `?form=` accepts a `series` term's own form (`nonfiction`/`novel`/`novella`/`story-cycle`) or `fiction` (05 §3: every row whose form isn't `nonfiction`).
- `GET /series/{slug}` — one series entry with its full `parts` array.
- `GET /verse` — the current `ttm_verse` record (including `copyright`).
- `GET /lead` — `{id, reason}` for the computed lead story.

`ttm_featured_in_section` and `ttm_form_locked` post meta are also exposed on the core `/wp/v2/posts`
endpoints via `show_in_rest`; no other meta is REST-exposed, and nothing under `ttm_classic_backup`
or `ttm_converted_at` is (internal migration bookkeeping only).

## WP-CLI

Every command exits non-zero on failure and supports `--dry-run` where it writes.

| Command | Flags |
|---|---|
| `wp ttm seed` | `[--reset]` `[--state=<normal\|quiet\|empty>]` |
| `wp ttm verse fetch` | `[--force]` |
| `wp ttm verse inspect` | `[--raw]` |
| `wp ttm verse log` | — |
| `wp ttm recount` | `[--all]` `[--post=<id>]` |
| `wp ttm primary:assign` | `[--dry-run]` |
| `wp ttm series:assign <series-slug>` | `--from-tag=<tag>` `[--form=<form>]` `[--dry-run]` |
| `wp ttm series:rebuild` | — |
| `wp ttm convert:export` | `[--out=<file>]` `[--post=<id>]` `[--all-classic]` |
| `wp ttm convert:import <file>` | `[--dry-run]` `[--post=<id>]` `[--allow-freeform]` |
| `wp ttm convert:revert` | `[--post=<id>\|--all]` `[--dry-run]` |
| `wp ttm audit` | `[--format=table\|csv\|json]` `[--only=<check>[,<check>...]]` |
| `wp ttm migrate:politics` | `[--to=child\|tag]` `[--dry-run]` |
| `wp ttm migrate:redirects` | `[--format=nginx\|json]` |
| `wp ttm migrate:close-comments` | `[--dry-run]` |
| `wp ttm migrate:syndication` | `[--dry-run]` `[--post=<id>]` |

See `docs/MIGRATION.md` for the end-to-end sequencing of these commands against a real WXR
import, and `docs/spikes/` for the two bounded research spikes (`P8-01` classic→block conversion
under jsdom, `P8-05` Jetpack Social share-URL shapes) that informed `convert:*` and
`migrate:syndication`.

## Configuration

All tunables live in one place, `Config::defaults()`, read via `Config::get('key.path', $fallback)`
and overridable wholesale with the `ttm_config` filter (e.g. from an mu-plugin):

```php
add_filter( 'ttm_config', function ( array $config ): array {
    $config['series.hub_featured_parts'] = 20;
    return $config;
} );
```

`nav.front_current` (default `'lead'`) controls which section reads as current in the front-page
nav: `'lead'` marks the lead post's primary category (`Nav\CurrentSection`, via `Query\Lead` and
`Meta\PrimaryCategory`); `'none'` marks nothing. It only affects the front page — every other
template's current-section mark (single post, category archive, series pages) is unconditional.

A narrow subset (`newsletter.*`, `lead.sticky_days`, `lead.stale_days`, `journal_in_main_feed`,
`comments_enabled`) is also editable from Settings → These Things Matter, stored in the
`ttm_settings` option, and overlaid on top of the defaults before `ttm_config` runs.

`journal.excerpt_words` (default `40`) is the target length `Query\JournalExcerpt` aims for when
a Journal post has no manual excerpt (`Support\Text::sentence_excerpt()` extends to the nearest
sentence end); `journal.excerpt_max_words` (default `55`, coupled to core's own default excerpt
length so a derived excerpt never reads longer than a manual one would) is the hard cap — past
it, the excerpt is cut mid-sentence with an ellipsis rather than extended further.

Secrets are constants, never options, never `show_in_rest`, and masked (`••••`) when set in any
admin screen that reports on them:

| Constant | Purpose |
|---|---|
| `TTM_CLOUDFLARE_ZONE_ID`, `TTM_CLOUDFLARE_API_TOKEN` | Enables the Cloudflare purge adapter. |
| `TTM_NEWSLETTER_API_KEY` | Used only by the `custom-url` newsletter provider. |
| `TTM_REMOVE_DATA` | `true` makes `uninstall.php` delete the plugin's options/transients. |

## Uninstall

`uninstall.php` runs only when `WP_UNINSTALL_PLUGIN` is defined (i.e. through WordPress's own
uninstall flow, never on deactivation) **and** `TTM_REMOVE_DATA` is `true`. It deletes the
plugin's own options (`ttm_verse`, `ttm_verse_history`, `ttm_verse_log`, `ttm_books`,
`ttm_settings`, `ttm_series_index`, `ttm_redirects`) and its own rate-limit transients
(`ttm_rl_*`). It never deletes terms, term meta, or post meta — content always survives a plugin
removal (SPEC §3.3 rule 23).
