# These Things Matter — Specification

Version: 0.2
Status: ready for flight (proof of concept on branch `poc`)

The Foundry planner reads this file in full and derives the build plan from it. Every design decision has already been made in the Claude Design handoff (`docs/README.md` and `docs/01`–`07`); this document is the engineering contract that turns that handoff into a WordPress block theme plus a companion plugin. Where this file and the design docs disagree, **this file wins on engineering and the design docs win on visuals**. Numbers marked `⚠️ ASSUMPTION` carry a config key and must never be hard-coded anywhere else.

Read `docs/README.md` first, then this file, then `01`–`07` as tasks require them. The prototype HTML (`docs/Eric Mann Newspaper.dc.html`) is reference only.

---

## 1. Overview

A newspaper-style rebuild of **eric.mann.blog** ("These Things Matter"): one author, seven sections, cross-category series, serialized fiction, and a daily verse. It ships as two installable units in one repository: the block theme `ttm-theme` (presentation only) and the plugin `ttm-core` (all data, blocks, bindings, cron, CLI, migration). The site is served almost entirely from cache (Cloudflare in front, Batcache at the origin), so every page must render correctly as a static document with no client-side data fetching.

"Done" for this flight: `npx wp-env start && npm run env:seed` produces a site where all ten templates in `docs/02-screens.md` render the seeded content at high fidelity to the prototype, every fallback row in `docs/06-fallbacks.md` has a passing test, GitHub CI is green on `poc/*` branches (lint, unit, integration, security), and the migration CLI can be run against a WXR export of the live site.

## 2. Goals and non-goals

Goals (from the design, plus engineering goals for this build):

- Goal: Reproduce the seven current screens (`2a/3a`, `2b/3b`, `2c`, `2d`, `1e`, `1f`, `3c`) plus the "not drawn" screens in `02 §H` as block templates, at high visual fidelity.
- Goal: Theme and plugin are separable. Theme active + plugin inactive renders every template without PHP notices; plugin active + a default theme keeps all data and renders semantic, unstyled blocks.
- Goal: 100% block editor authoring. No shortcodes, no widgets, no page builders, no classic-theme PHP templates.
- Goal: Static-ish output. No front-end network requests, no per-visitor markup, no nonces on cached pages, cache lifetimes that make date-sensitive chrome correct.
- Goal: Every fallback in `06` is a server-side branch with a test.
- Goal: Security by construction: WordPress Coding Standards security sniffs pass at error level, all input sanitized and all output escaped, no user-controlled URLs fetched, read-only public REST.
- Goal: Migration path for the existing 888 posts (WordPress 7.1.1, Powder theme, Jetpack): primary categories, Politics fold, series creation, word counts, redirects.
- Goal: CI on GitHub covers PHP unit, JS unit, wp-env integration, and security scanning on every push.

Non-goals (become `**Out of scope:**` lines on tasks):

- Non-goal: Comments UI. Comments are off (`default_comment_status = closed`, existing comments closed by the migration); the theme carries no comment template part. The owner may revisit after content cleanup and migration; nothing in this build should make that harder (leave core comment blocks styleable via `theme.json`, add nothing else).
- Non-goal: Multi-author UX. One author; "by Eric Mann" is static in mastheads.
- Non-goal (this flight): Dark mode. Not designed yet; a nice-to-have later. Do not add a dark palette, `prefers-color-scheme` rules, or a toggle now, but keep every colour a `theme.json` preset so a dark variant can be added later without touching components.
- Non-goal: E-commerce; purchase links are plain outbound links.
- Non-goal: Search ranking (core search, or Jetpack Search if the owner enables it later; the theme only styles the results layout). Analytics (Jetpack Stats stays as-is; its beacon is a third-party plugin and outside this repo's "no front-end requests" rule). Consent banners. A newsletter archive (Jetpack Newsletter hosts it). A views counter (Most read is a manual flag).
- Non-goal: Hand-editing content. Classic (pre-2016) posts are converted to blocks by tooling (§6.7 `convert:*`, Phase 8); the theme must still render unconverted classic HTML correctly in the meantime.
- Non-goal: Signed commits. Commits on this flight are unsigned; they will be rebased and signed by a human later.
- Non-goal: A production deploy. The flight ends with a reviewable `poc/*` branch and a draft PR into `poc`.

## 3. Engineering principles

Each rule is phrased so the reviewer can check it with a grep, a test, or a lint run. These become `## Constraints` in `CLAUDE.md`.

### 3.1 Boundaries

1. **Theme never owns data.** No `register_taxonomy`, `register_post_type`, `register_post_meta`, `register_term_meta`, `add_option`, `update_option`, `get_term_meta`, `get_post_meta`, `WP_Query`, `get_posts`, or `wp_remote_*` anywhere under `themes/ttm-theme/`. The theme reads plugin data only through `ttm/*` blocks and `ttm/*` block bindings inside templates and patterns.
2. **Plugin never owns presentation.** No `wp_enqueue_style` hooked to `wp_enqueue_scripts`, no `<style` tag, and no `style="` attribute in any file under `plugins/ttm-core/` except the allow-listed inline properties `grid-column`, `aspect-ratio`, and `--ttm-*` custom properties (grep `style="` in `plugins/ttm-core/**/render.php` must match only those). Plugin markup uses `ttm-*` classes that `themes/ttm-theme/assets/css/ttm.css` styles.
3. **Theme guards every plugin call.** Any PHP in `themes/ttm-theme/` that references a `TTM\Core` symbol or `ttm_*` function is wrapped in `function_exists()` / `class_exists()`. Grep for `TTM\\Core` and `ttm_` in `themes/` and check each hit.
4. **Versioned contract.** `plugins/ttm-core/ttm-core.php` defines `TTM_CORE_API = 1`. `themes/ttm-theme/functions.php` checks `defined('TTM_CORE_API') && TTM_CORE_API === 1` and shows an admin notice on mismatch, never a fatal.
5. **Namespaces are fixed.** PHP `TTM\Theme\…` (theme) and `TTM\Core\…` (plugin); block namespace `ttm/`; CSS prefix `ttm-`; text domains `ttm-theme` and `ttm-core`; option/meta/transient prefix `ttm_`. Grep for any other prefix on options, meta keys or transients.

### 3.2 Cache-safety (the site is static-ish)

6. **No front-end network requests from this repository.** No `fetch(`, `XMLHttpRequest`, `wp.apiFetch`, `apiFetch(`, `admin-ajax.php` or `/wp-json/` string under `themes/ttm-theme/assets/js/` or any plugin `view.js`/`viewScript`. Front-end JavaScript is exactly one file: `themes/ttm-theme/assets/js/nav.js` (phone nav toggle) plus the core Navigation block's own script.
7. **No nonces on cacheable output.** No `wp_create_nonce`, `wp_nonce_field`, or `wp_nonce_url` in any `render.php`, pattern, template part, or template. Front-end forms (newsletter) use the stateless token described in §6.9 or post directly to the provider.
8. **No per-visitor markup.** No `is_user_logged_in()`, `wp_get_current_user()`, `get_current_user_id()`, `$_COOKIE`, or `$_SESSION` in any `render.php`, block binding, pattern, or template. The single exception is `Cache/Headers.php`, which only ever *skips* caching for logged-in users.
9. **All "now" reads go through one clock.** No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new \DateTime` or `DateTimeImmutable` construction outside `plugins/ttm-core/src/Support/Clock.php`. Everything else calls `Clock::now()` (filterable via `ttm_now` for tests). This is what makes "Today", "Yesterday", the masthead date, and the 30-day lead rule testable and cache-predictable.
10. **Cache lifetime is computed, not guessed.** `Cache/Headers.php` emits `Cache-Control: public, max-age=<seconds until the next site-timezone boundary>, s-maxage=<same>` for anonymous front-end responses, where the boundary is the earlier of local midnight and the verse refresh hour (`cache.verse_boundary_hour`, ⚠️ ASSUMPTION default `6`). Batcache reads the same value via `$batcache['max_age']` when Batcache is present. Cloudflare respects origin headers (see `docs/DEPLOYMENT.md`).
11. **Publishing purges.** On `transition_post_status` to/from `publish` and on a successful verse fetch, the plugin fires `do_action('ttm_purge_urls', array $urls)` with the front page, the affected section archives, the series archive, and the post URL. The Cloudflare adapter (`Cache/Cloudflare.php`) listens when `cache.cloudflare.zone_id` and `cache.cloudflare.api_token` (from constants, never options) are set; otherwise nothing happens and Cloudflare APO's own purge applies.
12. **Every front-end query is bounded.** No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, or `number => 0` under `plugins/ttm-core/src/` or `render.php`. Term queries use `number` with a value from `Config`.
13. **Expensive derived data is cached in options or transients, never computed per request.** Series index, category stats, top tags, lead ID, and the verse all read from the stores listed in §5.3 and are recomputed only on the write hooks listed there.

### 3.3 Security

14. **PHPCS security sniffs are errors.** `phpcs.xml.dist` uses `WordPress-Extra`, `WordPress-VIP-Go` (cache-aware sniffs) and `PHPCompatibilityWP` (PHP 8.1–8.4). `WordPress.Security.*` sniffs are `error` severity and `composer lint` must exit 0. Every superglobal read is unslashed and sanitized on the same statement; every echo is escaped.
15. **No dangerous PHP.** No `eval(`, `unserialize(`, `extract(`, `create_function(`, `assert(`, `` ` `` backticks, `system(`, `exec(`, `shell_exec(`, `passthru(`, `proc_open(`, `curl_`, `file_get_contents('http`, `fopen('http`, or `include`/`require` of a variable path under `plugins/` or `themes/`. Serialization for `ttm_purchase_links`, `ttm_syndication`, `ttm_books` is JSON via `wp_json_encode` / `json_decode(..., true)` with schema validation (`rest_validate_value_from_schema`).
16. **One outbound URL.** The only `wp_remote_get`/`wp_remote_post` targets are the constant `TTM\Core\Verse\Fetcher::ENDPOINT`, the Cloudflare purge endpoint in `Cache/Cloudflare.php`, and the `custom-url` newsletter endpoint in `Newsletter/Provider/CustomUrl.php` (an `https` URL set by an administrator in settings, validated with `wp_http_validate_url`, never from request input). No URL passed to `wp_remote_*` may originate from request input, post content, or options editable in the admin UI. Use `wp_safe_remote_get`/`wp_safe_remote_post` (which enforces `wp_http_validate_url`).
17. **Secrets are constants.** API keys and tokens (`TTM_NEWSLETTER_API_KEY`, `TTM_CLOUDFLARE_API_TOKEN`) are read from PHP constants or environment only, never stored in the options table, never `show_in_rest`, never printed in admin HTML (masked as `••••` when a value is set).
18. **Capabilities and nonces on every write.** Every `admin_post_*`, `admin_action`, settings save, and REST route with a non-`GET` method checks `current_user_can()` with the specific capability and verifies a nonce (`check_admin_referer` / `wp_verify_nonce`) or uses a REST `permission_callback` that is not `__return_true`. Public REST routes (§6.6) are `GET` only, `permission_callback => '__return_true'`, and expose only data already public on the site.
19. **Meta and term meta are typed and sanitized at registration.** Every `register_post_meta` / `register_term_meta` call passes `type`, `single`, `sanitize_callback`, `auth_callback` (returns `current_user_can('edit_post', $post_id)` / `edit_term`), and a `show_in_rest` schema. No `update_post_meta` with unsanitized request data anywhere.
20. **Rate limits on unauthenticated POSTs.** The newsletter handler enforces `newsletter.rate_limit_per_ip` (⚠️ ASSUMPTION default `5` per `newsletter.rate_limit_window` seconds, default `600`) using a transient keyed on a hashed IP, plus a honeypot field named from `newsletter.honeypot_field` (default `ttm_website`). Failing either returns the same redirect as success (no oracle).
21. **Sanitized third-party text.** Verse text and reference pass through `wp_kses` with an allow-list of `em` and `strong` only; `meditation_content` is never stored. `copyright_notice` is stored and rendered as plain text via `esc_html`.
22. **Supply chain.** `composer.lock` and `package-lock.json` are committed. CI runs `composer audit` and `npm audit --audit-level=high` and fails on findings. Dependabot is enabled for composer, npm and GitHub Actions. Actions are pinned to major tags; no `uses: */master`.
23. **Uninstall keeps content.** `uninstall.php` deletes options and transients only, and only when `TTM_REMOVE_DATA` is `true`; terms and meta are never deleted by uninstall.

### 3.4 Code quality and tests

24. **Every tunable is a config key.** All numeric and string tunables (query counts, day windows, timeouts, cache hours, rate limits, image sizes, verse endpoint) live in `plugins/ttm-core/src/Config.php` as a single `defaults()` array, read via `Config::get('key.path')` and filterable through `ttm_config`. No bare numeric literal outside `Config.php` except `0`, `1`, `-1` for `array_search` results, array indices, and CSS values inside `ttm.css`/`theme.json`.
25. **Every `ttm/*` block has three files and a test.** `block.json` (with `supports: { html:false, align:false, color:false, typography:false, spacing:false }` and `"render": "file:./render.php"`), `render.php`, `index.js` (editor registration using `ServerSideRender` for preview), and a PHPUnit integration test under `tests/integration/Blocks/` that covers the normal case and every `06` row naming that block.
26. **Every block-binding source has a unit test** covering its normal value and its `F25`-style empty value.
27. **Tests are the acceptance criteria.** A task that changes behaviour adds or changes a test that fails without the change. The reviewer rejects a behavioural change without a test.
28. **Unit tests do not load WordPress.** `tests/unit/` uses Brain\Monkey; any test that needs a real `WP_Query`, template rendering, or the REST server goes in `tests/integration/` and runs inside wp-env.
29. **Deterministic markup.** Rendered block HTML for a given fixture is stable (no random IDs, no timestamps except through `Clock`). Integration tests may snapshot render output.
30. **CSS budget.** `themes/ttm-theme/assets/css/ttm.css` ≤ 25 KB uncompressed (`cssBudgetBytes`, ⚠️ ASSUMPTION default `25600`, in `scripts/check-budget.mjs`). No CSS framework, no preprocessor. Plain CSS with custom properties from `theme.json` presets.
31. **No new front-end dependencies.** `package.json` `dependencies` stays empty; everything is a `devDependency`. The theme ships no `node_modules` output beyond fonts and the two CSS files.
32. **i18n.** Every user-facing string in PHP goes through `__()`/`_e()`/`esc_html__()`/`_n()` with the correct text domain; every JS string through `@wordpress/i18n`. Grep for bare strings in `esc_html(` / `echo '` in `render.php`.
33. **Accessibility is testable.** Landmarks per `04 §8`; every whole-row link is a single `<a>` whose accessible name is the headline; `axe-core` via Playwright reports zero `serious`/`critical` violations on the seven seeded screens (Phase 8).

## 4. Architecture

### 4.1 Repository layout

```text
.
├── docs/                         design handoff (read-only for the flight) + SPEC/SETUP/MIGRATION/DEPLOYMENT
│   └── fixtures/                 verse API sample, seed content JSON, WXR sample
├── plugins/ttm-core/             companion plugin (data, blocks, bindings, cron, CLI, migration)
│   ├── ttm-core.php              header, constants (TTM_CORE_API, TTM_CORE_VERSION, TTM_CORE_DIR/URL), autoload, boot
│   ├── uninstall.php
│   ├── src/                      PSR-4 `TTM\Core\` (composer autoload, classmap fallback for hosts without composer)
│   ├── blocks/<name>/            block.json · render.php · index.js (built to build/blocks/<name>/)
│   ├── build/                    wp-scripts output (gitignored)
│   └── languages/
├── themes/ttm-theme/             block theme
│   ├── style.css  theme.json  functions.php  screenshot.png
│   ├── templates/  parts/  patterns/  inc/  assets/{css,fonts,js}
├── tests/
│   ├── unit/                     Brain\Monkey, no WordPress (composer test:unit)
│   ├── integration/              WP test suite inside wp-env (npm run test:integration)
│   └── e2e/                      Playwright + axe (npm run test:e2e), Phase 8
├── scripts/                      check-theme-json.mjs · check-budget.mjs · seed helpers
├── .wp-env.json  package.json  composer.json  phpcs.xml.dist  phpunit.xml.dist  phpunit-integration.xml.dist
└── .github/workflows/ci.yml  .github/dependabot.yml
```

`.wp-env.json` maps `plugins/ttm-core` and `themes/ttm-theme` into the container, pins WordPress `7.1.1` and PHP `8.3`, and activates both on start.

### 4.2 Plugin module map (`plugins/ttm-core/src/`)

| Directory | Responsibility | May import |
|---|---|---|
| `Config.php` | Single defaults array + `get()` + `ttm_config` filter | nothing |
| `Support/` | `Clock`, `Dates` (`short_month`, `relative_day`, formats from `03 §13`), `Text` (word count, sentence-trimmed excerpt, curly quotes), `Html` (escaped fragment builders) | `Config` |
| `Taxonomy/Series.php` | Registers `series` taxonomy, term meta, admin columns, term edit fields, REST schema | `Support`, `Config` |
| `Meta/` | `PostMeta` (registration + sanitizers), `PrimaryCategory` (resolver + save hook), `WordCount` (save hook), `Form` (article/chapter/story derivation) | `Support`, `Config`, `Taxonomy` |
| `Query/` | `Lead` (selection + cache), `Cells` (`query_loop_block_query_vars` filter), `Archive` (`pre_get_posts`, year grouping, pagination labels), `SeriesIndex` (computed index + cache), `Stats` (category stats, top tags) | `Support`, `Config`, `Meta`, `Taxonomy` |
| `Fiction/` | `Serials` (active serial, chapters, stats), `Books` (option repeater + settings screen) | `Query`, `Taxonomy`, `Support` |
| `Verse/` | `Fetcher` (HTTP, parse, store, history, log), `Cron` (schedule + retry), `Admin` (settings tab, fetch-now) | `Support`, `Config`, `Cache` |
| `Bindings/Sources.php` | All `register_block_bindings_source` calls (§6.3) | `Query`, `Support`, `Meta` |
| `Blocks/Registrar.php` | Registers every `blocks/*/block.json`; shared render helpers in `Blocks/Helpers.php` | everything above |
| `Editor/` | `Sidebar` (enqueue `build/editor.js`), `Checks` (pre-publish, server side data), `Columns` | `Meta`, `Taxonomy` |
| `Templates/Hierarchy.php` | `single_template`, `category_template` routing; body classes | `Meta`, `Config` |
| `Nav/CurrentSection.php` | `render_block_core/navigation-link` current-section class; hide "Series" when no terms | `Meta`, `Query` |
| `Newsletter/` | `Handler` (admin-post endpoint for `custom-url`: token, honeypot, rate limit), `Provider/{Provider.php,Jetpack.php,CustomUrl.php,Mailto.php,None.php}`, `Settings` | `Config`, `Support` |
| `Cache/` | `Headers` (Cache-Control), `Purge` (collects URLs, fires `ttm_purge_urls`), `Cloudflare` (adapter), `Batcache` (sets `$batcache['max_age']` if defined) | `Config`, `Support` |
| `Rest/` | `SeriesController`, `VerseController`, `LeadController` (all `GET`) | `Query`, `Verse` |
| `Cli/` | `VerseCommand`, `RecountCommand`, `PrimaryCommand`, `SeriesCommand`, `MigrateCommand`, `SeedCommand` | everything |
| `Compat/Theme.php` | API version constant, notice | `Config` |

Dependency arrow points **down** the table: `Support`/`Config` import nothing; `Blocks`, `Bindings`, `Rest`, `Cli` import anything. `Cache`, `Verse`, `Newsletter` never import `Blocks`. The reviewer enforces this with `grep -n "^use TTM" plugins/ttm-core/src/<dir>/*.php`.

### 4.3 Theme layout (`themes/ttm-theme/`)

Exactly the file list in `docs/04-theme-spec.md §1`. `functions.php` only: theme supports, enqueue `ttm.css`/`editor.css`/`nav.js`, register block styles (`inc/block-styles.php`), pattern categories (`inc/patterns.php`), image sizes (`inc/image-sizes.php`), template-hierarchy compat (`inc/template-hierarchy.php`, guarded), block variations script (`assets/js/variations.js`, editor only), `TTM_CORE_API` check (`inc/bindings-compat.php`), section feed `<link rel="alternate">` tags, starter content (`inc/starter-content.php`: creates the seven categories, the Series/Writing/Newsletter/About pages with templates assigned, and the `wp_navigation` post on `after_switch_theme`, idempotent).

### 4.4 Request flow (front page, cache miss)

1. Cloudflare misses → Batcache misses → WordPress boots.
2. `Cache/Headers` computes `max-age` from `Clock::now()` and the next boundary.
3. `front-page.html` renders: `ttm/lead-story` calls `Query\Lead::id()` (transient `ttm_lead_id`), section `core/query` blocks are filtered by `Query\Cells`, `ttm/verse-of-the-day` reads option `ttm_verse`, `ttm/series-list` reads option `ttm_series_index`, `ttm/writing-cell` reads `Fiction\Serials` (from the same index).
4. Output is stored by Batcache and Cloudflare until the boundary, or until `ttm_purge_urls` fires.

There are zero database writes on a front-end request.

## 5. Data and configuration

### 5.1 Taxonomy `series` (plugin)

Registered on `post`; `hierarchical=false`, `public=true`, `show_in_rest=true`, `show_admin_column=true`, `rewrite=['slug'=>'series','with_front'=>false]`, `query_var='series'`. Term meta exactly as `03 §3`:

| Key | Type | Sanitizer | Default |
|---|---|---|---|
| `ttm_status` | string enum `in-progress\|complete\|hiatus` | enum check, else `in-progress` | `in-progress` |
| `ttm_total_parts` | integer ≥ 0 | `absint` | `0` (open-ended) |
| `ttm_form` | string enum `nonfiction\|novel\|novella\|story-cycle` | enum check | `nonfiction` |
| `ttm_genre` | string | `sanitize_text_field` | `''` |
| `ttm_cadence` | string | `sanitize_text_field` | `''` |
| `ttm_next_date` | string `Y-m-d` or `''` | regex + `checkdate` | `''` |
| `ttm_cover_id` | integer attachment ID | `absint` + `wp_attachment_is_image` | `0` |
| `ttm_featured` | boolean | `rest_sanitize_boolean` | `false` |
| `ttm_purchase_links` | array of `{label:string,url:string}` (max `series.max_purchase_links` = 6) | schema validate, `esc_url_raw` | `[]` |

A post has at most one `series` term: the editor UI is single-select and `Taxonomy\Series::enforce_single()` on `set_object_terms` keeps the first term.

### 5.2 Post meta (plugin, on `post`)

| Key | Type | Notes |
|---|---|---|
| `ttm_series_part` | integer ≥ 1 | Required when a series term is set (pre-publish warning, never blocks save). Uniqueness per series is a warning. |
| `ttm_part_title` | string | Optional short title. |
| `ttm_primary_category` | integer term ID | Default resolver: first assigned section in nav order (`sections.order`); set on `save_post` when empty; editable in sidebar. |
| `ttm_form` | string enum `article\|chapter\|story` | Derived on save: series term with `ttm_form ≠ nonfiction` → `chapter`; in Writing with no series → `story`; else `article`. Editable; manual value wins (`ttm_form_locked` bool). |
| `ttm_word_count` | integer | Computed on `save_post` (skip autosave/revision): strip `core/code`, `core/preformatted`, shortcodes, tags. |
| `ttm_syndication` | object `{x?:url, mastodon?:url, bluesky?:url}` | Schema-validated; each `esc_url_raw`; only `https` scheme. |
| `ttm_location` | string | Journal only in UI; harmless elsewhere. |
| `ttm_featured_in_section` | boolean | "Most read" manual flag (Q6). Max `archive.most_read_limit` per category is a pre-publish warning. |

### 5.3 Options, transients, and when they change

| Store | Shape | Written by | Read by |
|---|---|---|---|
| option `ttm_verse` | `{date:'Y-m-d', text, reference, title, url, source_id, copyright, fetched_at}` | `Verse\Fetcher::store()` | verse block, REST |
| option `ttm_verse_history` | array of the above, newest first, max `verse.history_size` (30) | `Fetcher::store()` | F6 fallback |
| option `ttm_verse_log` | array of `{at, ok:bool, message}`, max `verse.log_size` (20) | `Fetcher` | admin screen, CLI |
| option `ttm_books` | array of `{title, form, year:int, cover_id:int, formats:string[], links:[{label,url}], series_id:int}` | Books settings screen | `ttm/book-grid` |
| option `ttm_settings` | `{newsletter:{provider, endpoint, list_id}, journal_in_main_feed:bool, lead:{sticky_days, stale_days}, comments_enabled:bool}` | Settings screen (nonce + `manage_options`) | everywhere via `Config` overlay |
| option `ttm_series_index` | computed index: per series `{id, slug, name, status, form, published:int, total:int, categories:int[], last_update:'Y-m-d H:i:s', next_date, first_post_id, latest_post_id, parts:[{post_id, part, title, status, date}]}` | `Query\SeriesIndex::rebuild()` on `save_post`, `deleted_post`, `transition_post_status`, `edited_series`, `created_series`, `delete_series`, and CLI | series blocks, bindings, nav, REST |
| transient `ttm_lead_id` | `{id, reason}` | `Query\Lead::compute()`; TTL `lead.cache_seconds` (300); deleted on `transition_post_status` | lead block, cells, REST |
| transient `ttm_category_stats_{term_id}` | `{count, first_year, last_year, series_count}` | `Query\Stats`; TTL `stats.cache_seconds` (3600); deleted on publish | `ttm/category-stats`, `ttm/category-count` |
| transient `ttm_top_tags_{term_id}` | `[{term_id, slug, name, count}]` | `Query\Stats`; TTL `stats.tags_cache_seconds` (43200); deleted on publish | `ttm/tag-filter` |
| transient `ttm_rl_{hash}` | int | `Newsletter\Handler` | itself |

An option write that changes rendered output (verse, books, settings, series index) also fires `ttm_purge_urls`.

### 5.4 Config keys (`Config::defaults()`)

All values below are the design's numbers unless marked `⚠️ ASSUMPTION`. The planner names the key in the task that introduces it.

```text
sections.order              = ['technology','business','faith','journal','writing','security','opinion']
sections.nav_hub_slug       = 'series'
sections.journal_slug       = 'journal'
sections.writing_slug       = 'writing'
sections.politics_slug      = 'politics'

lead.sticky_days            = 30
lead.stale_days             = 30
lead.cache_seconds          = 300

cells.counts                = { technology: 3, business: 3, security: 3, faith: 2, opinion: 2 }   # includes featured item
cells.thin_days             = 90      # F9 "no posts in 90 days" → still show what exists
cells.stale_year_days       = 365    # F9 "< 1 post in a year" → drop dek, show 2 with full dates
journal.rail_count          = 3
journal.rail_window_days    = 30      # F7
journal.excerpt_words       = 40
journal.stream_count        = 4
journal.archive_per_page    = 20
journal.relative_day_window = 6

series.strip_limit          = 3
series.max_purchase_links   = 6      ⚠️ ASSUMPTION
series.hub_featured_parts   = 12     ⚠️ ASSUMPTION (parts listed in hub featured block before "All N →")
writing.also_running_limit  = 3
writing.chapters_recent     = 4
writing.story_tiles         = 4
writing.shelf_limit         = 4      # F1

archive.per_page            = 12
archive.tag_filter_limit    = 5
archive.row_tags            = 2
archive.most_read_limit     = 3
article.more_in_section     = 3
reading.words_per_minute    = 230

verse.endpoint              = 'https://dailymedtoday.com/api/v1/meditations/'
verse.item_url_pattern      = 'https://dailymedtoday.com/meditation/%s'   # %s = item id (verified 2026-09-20, returns 200)
verse.fetch_hour            = 5       # site time
verse.retry_delay_seconds   = 7200
verse.timeout_seconds       = 8
verse.user_agent            = 'TTM-Core/{version} (+https://eric.mann.blog)'
verse.history_size          = 30
verse.log_size              = 20

cache.verse_boundary_hour   = 6      ⚠️ ASSUMPTION — first cache boundary after the 05:00 fetch (+retry at 07:00 → purge handles it)
cache.max_age_cap_seconds   = 86400  ⚠️ ASSUMPTION
cache.min_age_seconds       = 60     ⚠️ ASSUMPTION — never emit a max-age under this
cache.cloudflare.zone_id    = constant TTM_CLOUDFLARE_ZONE_ID or ''
cache.cloudflare.api_token  = constant TTM_CLOUDFLARE_API_TOKEN or ''

newsletter.provider         = 'jetpack'   # jetpack | custom-url | mailto | none
newsletter.endpoint         = ''          # custom-url only
newsletter.fallback_email   = ''
newsletter.token_ttl        = 86400  ⚠️ ASSUMPTION — HMAC form token validity (must exceed cache max-age cap)
newsletter.rate_limit_per_ip= 5      ⚠️ ASSUMPTION
newsletter.rate_limit_window= 600    ⚠️ ASSUMPTION
newsletter.honeypot_field   = 'ttm_website'

images.sizes                = { 'ttm-lead': [1600,900,true], 'ttm-thumb': [800,533,true], 'ttm-cover': [600,900,true], 'ttm-tile': [800,600,true] }
stats.cache_seconds         = 3600
stats.tags_cache_seconds    = 43200
```

`ttm_settings` values overlay defaults for the keys under `newsletter.*`, `lead.*`, `journal_in_main_feed`, and `comments_enabled` only.

## 6. Interfaces

### 6.1 Blocks (`ttm/*`)

All server-rendered. Attributes, render rules, and fallbacks are exactly the table in `docs/05-plugin-spec.md §3`; that table is normative. Additional contract:

- Every block wrapper is `<div class="ttm-<name> [is-<state>]" data-ttm-block="<name>">` using `get_block_wrapper_attributes()`. State classes are the ones named in `06` (`is-textonly`, `is-nocover`, `is-empty`, `is-shelf` for F1, `is-plain` for F2, `is-complete` for F3/F5).
- A block that has nothing to render returns `''` (empty string), never an empty wrapper, so theme `:has()` rules and `is-empty` on parent groups work.
- Every block accepts a `previewState` attribute (`normal|empty|thin`, editor-only, stripped on save by `render.php` ignoring it outside `is_admin()`/REST edit context) that forces the fallback branch for Site Editor previews (per `06` implementation note).
- `ttm/lead-story` is the **default** lead implementation (Q from `05 §3`); the theme's `ttm/lead-story` pattern wraps this block.
- `ttm/archive-by-year` wraps a `core/query` inner block; its `render.php` uses `render_block_data`/`render_block_core/post-template` filters scoped by a static flag set during its own render, and injects `<div class="ttm-archive-year"><h2>2026</h2>` group wrappers when the post year changes.
- `ttm/newsletter-form` is a thin provider switch. Provider `jetpack` (default when the `jetpack/subscriptions` block is registered): renders `do_blocks('<!-- wp:jetpack/subscriptions {"buttonText":"Subscribe","showSubscribersTotal":false} /-->')` inside a `ttm-newsletter-form` wrapper; the theme styles Jetpack's markup (`.wp-block-jetpack-subscriptions` input/button) with the `.input`/`.btn` rules; the form posts to WordPress.com, so nothing here touches PHP on submit and the output is cache-safe. Provider `custom-url`: renders `<form method="post" action="{admin-post url}">` with `action=ttm_subscribe`, `email`, the honeypot, the stateless token (§6.9), and `redirect_to` (validated with `wp_validate_redirect`); the handler forwards to `newsletter.endpoint`. Provider `mailto`: a `mailto:` link. Provider `none`: the statement only (F26). If the configured provider is unavailable (Jetpack inactive), fall back to `mailto` when `newsletter.fallback_email` is set, else `none`.

### 6.2 Block variations (theme JS, editor only)

Exactly `04 §4`: `ttm/section-query` and `ttm/journal-query` on `core/query` (attributes `namespace`, `query.ttmSection`, `query.ttmExcludeLead`, `query.ttmPrimaryOnly` — these three are custom query keys the plugin reads in `query_loop_block_query_vars`), and `ttm/sections-nav` on `core/navigation`.

### 6.3 Block-binding sources

Exactly the table in `05 §4`, registered in `Bindings/Sources.php`. Each source's `get_value_callback` receives `$source_args`, `$block_instance`, `$attribute_name` and must resolve `postId` from `$block_instance->context`. `ttm/meta-line` and `ttm/relative-date` may return HTML (a link) only for the `content` attribute of `core/paragraph`; all other sources return plain text. `ttm/today` respects §3.2 rule 9. `ttm/category-count` returns "All →" when the count is 0 (F25).

### 6.4 Query filters

- `query_loop_block_query_vars` (`Query\Cells`): reads `$block->context['query']['ttmSection']`, `ttmExcludeLead`, `ttmPrimaryOnly`; adds `category_name`, `post__not_in`, and `meta_query` on `ttm_primary_category`; always adds `category__not_in => [journal_id]` for non-journal section queries; sets `ignore_sticky_posts => 1`.
- `pre_get_posts` (`Query\Archive`): category archives `posts_per_page` from config; `?tag=` narrows within the category; Journal category uses `journal.archive_per_page`; main feed excludes Journal when `journal_in_main_feed` is false.
- `render_block_core/navigation-link` (`Nav\CurrentSection`): adds `current-section` when the link's category slug equals the current post's primary category slug or the queried category; removes the "Series" item when the series index is empty (F18).
- `single_template` / `category_template` (`Templates\Hierarchy`): Journal primary → `single-journal`; Writing category → `page-writing` template; adds `ttm-section-{slug}`, `ttm-in-series`, `ttm-form-{form}` body classes.

### 6.5 Hooks the plugin exposes (public API, documented in `plugins/ttm-core/README.md`)

| Hook | Type | Args |
|---|---|---|
| `ttm_config` | filter | `array $defaults` |
| `ttm_now` | filter | `DateTimeImmutable $now` |
| `ttm_lead_post_id` | filter | `int $id, string $reason` |
| `ttm_series_index` | filter | `array $index` (after rebuild, before store) |
| `ttm_verse_parsed` | filter | `array $verse, array $raw_item` |
| `ttm_purge_urls` | action | `string[] $urls` |
| `ttm_cache_max_age` | filter | `int $seconds` |
| `ttm_newsletter_subscribed` | action | `string $email_hash, string $provider` (never the raw email) |
| `ttm_section_feeds` | filter | `array $slug => url` (theme's `<link rel=alternate>` list) |

### 6.6 REST (`/wp-json/ttm/v1/`, all `GET`, public, cache headers applied)

- `GET /series` → the series index (§5.3) with computed fields; `?status=`, `?form=` filters.
- `GET /series/{slug}` → one entry with full `parts`.
- `GET /verse` → current `ttm_verse` (without `copyright` omitted — include it; attribution is mandatory).
- `GET /lead` → `{id, reason}`.

Post meta and term meta are also exposed through core endpoints via `show_in_rest` (the `ttm_featured_in_section` and `ttm_form_locked` flags included; no secrets).

### 6.7 WP-CLI (`wp ttm …`)

```text
wp ttm verse fetch [--force]           fetch now; exit 1 on failure
wp ttm verse inspect [--raw]           dump the parsed (or raw) payload for today's item
wp ttm verse log                       print ttm_verse_log
wp ttm recount [--all] [--post=<id>]   recompute ttm_word_count
wp ttm primary:assign [--dry-run]      set ttm_primary_category where empty
wp ttm series:assign <series-slug> --from-tag=<tag> [--form=<form>] [--dry-run]
                                       create/attach series term to all posts with the tag, number by publish date
wp ttm series:rebuild                  rebuild ttm_series_index
wp ttm migrate:politics [--to=child|tag] [--dry-run]
                                       move Politics under Opinion (default child) and record redirects
wp ttm migrate:redirects [--format=nginx|json]
                                       print the redirect map (old category URLs → new)
wp ttm audit [--format=table|csv|json] [--only=<check>]
                                       content-cleanup report, one row per post: classic (no block markup),
                                       no-excerpt, no-featured-image, missing-alt, multi-category (lists them),
                                       no-primary, uncategorized, politics, series-tag-candidate, legacy-footnotes,
                                       broken-internal-link (href to this host returning 404 in a local index — no HTTP)
wp ttm convert:export [--out=<file>] [--post=<id>] [--all-classic]
                                       write NDJSON of posts whose content has no `<!-- wp:` marker:
                                       {id, slug, content_raw, footnotes_meta}
wp ttm convert:import <file> [--dry-run] [--post=<id>]
                                       read NDJSON {id, blocks} written by scripts/convert-classic.mjs; validate with
                                       parse_blocks() (zero `core/freeform` unless --allow-freeform), store the original
                                       in post meta `ttm_classic_backup` (once, never overwritten), create a revision,
                                       update post_content; print per-post summary (block counts by name)
wp ttm convert:revert [--post=<id>|--all] [--dry-run]
                                       restore post_content from ttm_classic_backup
wp ttm seed [--reset]                  DEV ONLY (refuses when WP_ENVIRONMENT_TYPE=production): categories, pages,
                                       navigation, ~60 posts across sections with dates spread over 3 years,
                                       2 nonfiction series, 1 fiction serial with cover, 3 stories, 2 books,
                                       verse from docs/fixtures/verse-sample.json, journal entries with syndication
wp ttm seed --state=<quiet|empty>      seed the fallback states from 06 (quiet month; empty categories)
```

Every command returns non-zero on failure and supports `--dry-run` where it writes.

Classic → block conversion runs in Node because the only faithful converter is the editor's own `rawHandler` from `@wordpress/blocks`: `scripts/convert-classic.mjs <in.ndjson> <out.ndjson>` boots a `jsdom` window, registers the core block library (`@wordpress/block-library` `registerCoreBlocks()`), runs `rawHandler({ HTML })` per post, `serialize()`s the result, and reports any post that produced a `core/freeform` block (unconvertible fragments), a `core/html` block, or lost text (compares `textContent` before/after, normalised whitespace, must be equal). Legacy `modern-footnotes` markup (`<sup class="modern-footnotes-footnote">` + `<span class="modern-footnotes-footnote__note">`) is pre-transformed into core footnotes (`<sup data-fn="…"><a href="#…">` + the `footnotes` post meta array) before `rawHandler`. ⚠️ ASSUMPTION — `jsdom` is sufficient for `rawHandler` (it is what `@wordpress/blocks` unit tests use); a bounded spike task at the start of Phase 8 proves it on `docs/fixtures/classic-sample.html` before the CLI commands are built. Whether a post was converted is recorded in post meta `ttm_converted_at` (`Y-m-d H:i:s`).

### 6.8 Theme ⇄ plugin CSS contract

`ttm.css` is organised by the component numbers in `01 §4` (a comment header per component: `/* 4.18 series bar */`). Plugin blocks output the class names named in `01 §4` and `04 §3` exactly. The theme registers the block styles in `04 §3` with `register_block_style` and the matching CSS in `ttm.css`.

### 6.9 Newsletter stateless token

`Newsletter\Handler::token()` = `hash_hmac('sha256', floor(now / newsletter.token_ttl) . '|ttm_subscribe', wp_salt('nonce'))`; the form embeds the token and the handler accepts the current and previous window. This replaces `wp_create_nonce` so cached pages stay valid for at least `newsletter.token_ttl`. Rationale recorded here so the reviewer does not flag it as a missing nonce; the handler also has no privileged side effect (it forwards an email to a provider).

### 6.10 Cache headers contract

`Cache\Headers::send()` on `send_headers` for non-admin, non-REST, non-feed, anonymous GET requests: `Cache-Control: public, max-age=N, s-maxage=N` where `N = clamp(seconds_until_boundary, cache.min_age_seconds, cache.max_age_cap_seconds)` and boundary = next of {local midnight, local `cache.verse_boundary_hour`:00}. Feeds get `max-age=3600`. REST `ttm/v1` gets the same computed value. Adds `Vary: Cookie`? **No** (Cloudflare bypasses on the login cookie by rule; see DEPLOYMENT.md). When `$batcache` is a global array, sets `$batcache['max_age'] = N` on `init`.

## 7. Commands

Every command exits non-zero on failure and runs in a clean checkout after `npm ci && composer install`. These are copied into `docs/foundry.json`.

```text
verify:
  composer lint                 # phpcs (WordPress-Extra + VIP-Go + PHPCompatibilityWP), php -l
  composer test:unit            # PHPUnit + Brain\Monkey, tests/unit
  npm run lint                  # eslint (wp-scripts) + stylelint + theme.json/block.json checks + CSS budget
  npm run test:unit             # jest via wp-scripts, --passWithNoTests
  npm run build                 # wp-scripts build of plugins/ttm-core (blocks + editor bundle)

extraVerify:
  plugins/ttm-core/ and themes/ttm-theme/ and tests/integration/:
      npm run test:integration  # wp-env start (idempotent) + PHPUnit inside the tests container
  themes/ttm-theme/theme.json:
      npm run check:theme-json
  tests/e2e/ (Phase 8 only):
      npm run test:e2e          # wp-env must be running; Playwright + axe

build:
  npm run build

foundry.json:
  baseBranch   = "poc"          # all flight branches start from and PR into poc, not main
  branchPrefix = "poc/"
  maxRounds    = 3
  commandTimeoutMs = 900000     # integration runs pull Docker images the first time
```

Integration tests are slow (first `wp-env start` downloads images); they are in `extraVerify`, so a task touching only `docs/` or `scripts/` does not pay for them. CI (`.github/workflows/ci.yml`) runs everything on every push to `poc` and `poc/*` and on PRs into `poc` or `main`.

## 8. Phases

Each phase ends with a task that pushes the branch and records `Manual check: NOT VERIFIED (human)` naming what to open in the browser at `http://localhost:8888`. Later tasks never depend on a manual check.

### Phase 0 — Toolchain is real (mostly scaffolded already)

- Confirm the scaffold: `.wp-env.json`, `package.json`, `composer.json`, `phpcs.xml.dist`, unit bootstrap, CI workflow, Dependabot, scripts. Fix anything that does not pass `verify` on a clean checkout.
- Add `phpunit-integration.xml.dist` and `tests/integration/bootstrap.php` using the WP test suite that wp-env provides (`WP_TESTS_DIR=/wordpress-phpunit`), loading the plugin as a mu-plugin and switching to `ttm-theme`.
- `npm run test:integration` = `wp-env start` (idempotent) then `wp-env run tests-cli --env-cwd=… vendor/bin/phpunit -c phpunit-integration.xml.dist`.
- Plugin skeleton: `ttm-core.php` header, constants, composer autoloader with classmap fallback, `Config.php`, `Support/Clock.php`, `Support/Dates.php`, `Support/Text.php`, `Compat/Theme.php`, `uninstall.php`. Unit tests for `Dates::short_month` ("Sept"), `Dates::relative_day`, `Text::word_count`, `Text::sentence_excerpt`, `Text::curly_quotes`.
- Theme skeleton: `style.css` header, `theme.json` v3 with the full palette/type/spacing presets from `04 §2` and the token sheet, `index.html`, `functions.php`, fonts (Archivo 400/600/800/400i woff2, self-hosted, latin + latin-ext), `ttm.css` and `editor.css` with the resets from `04 §2`.
- **Visible result:** `npx wp-env start` boots WP 7.1.1 with both active; `/` renders the default `index.html` with the Archivo font and ground colour; CI green. Manual check: fonts load with no request to Google.

### Phase 1 — Data model and editor

- `Taxonomy\Series` with term meta, REST schema, admin columns, term edit fields (all meta), read-only part list on the term screen.
- `Meta\PostMeta`, `Meta\PrimaryCategory` (resolver in nav order), `Meta\WordCount`, `Meta\Form`; `enforce_single()` series.
- `Query\SeriesIndex` rebuild + option + hooks; `Query\Stats`.
- Editor sidebar (`blocks/../editor` bundle `src/editor/index.js`): panel "These Things Matter" with Primary section, Series (single select + create), Part number ("of M"), Part title, Form, Syndication URLs, Location (journal only), "Most read" flag. Pre-publish checks (non-blocking): missing dek, missing featured alt, series without part, duplicate part, Writing post without form.
- Admin columns: Primary section, Series (part), Words.
- Books settings screen (`ttm_books` repeater) and general settings screen (`ttm_settings`) under Settings → These Things Matter, with nonce + capability checks.
- REST `ttm/v1/series` and `/lead` (lead comes in Phase 3; stub returns 404 until then).
- CLI: `recount`, `primary:assign`, `series:assign`, `series:rebuild`, `seed` (the seed command lands here so every later phase has content).
- Tests: unit for resolvers/sanitizers; integration for taxonomy registration, meta REST round-trip with sanitization (bad enum → default, `javascript:` URL rejected), single-series enforcement, index rebuild on publish/unpublish/schedule.
- **Visible result:** seeded site; post editor shows the sidebar; term screen shows the part list. Manual check: the sidebar panel in the editor at `/wp-admin/post.php?post=<seeded>&action=edit`.

### Phase 2 — Theme foundation and chrome

- `inc/block-styles.php` (every row of `04 §3`), `inc/patterns.php` categories, `inc/image-sizes.php`, `inc/starter-content.php`, `assets/js/variations.js`, `assets/js/nav.js`.
- `ttm.css`: tokens bridge, rules, grids (`01 §3` with 1024/720 breakpoints), buttons, tags, inputs, cell heading, headline item, featured item, footer, masthead front/inner, nav overlay, body typography (`01 §4.24`), focus/selection.
- Parts: `header-front`, `header-inner`, `footer` (with `is-after-poster`), `rail` (empty until Phase 3 blocks exist; contains the block references already).
- Patterns: `masthead-front`, `masthead-inner`, `newsletter-poster`, `newsletter-box`, `pull-quote`, `code-figure`, `stat-row`.
- Templates: `page.html`, `404.html` (series strip + Latest list wired to blocks that arrive in Phase 3; renders without them), `index.html`, `search.html` shell.
- `Nav\CurrentSection` (plugin) and `Templates\Hierarchy` body classes.
- Tests: unit tests for block style registration list vs `04 §3` (a fixture list), snapshot of `theme.json` presets vs the token sheet (script), `check-budget`.
- **Visible result:** `/about/` (seeded page) shows inner masthead, H1 56px, body typography, footer; phone width shows "Menu" overlay. Manual check at 1280 and 390 wide.

### Phase 3 — Front page

- Blocks: `ttm/lead-story`, `ttm/verse-of-the-day`, `ttm/series-list`, `ttm/writing-cell`, `ttm/newsletter-form`.
- `Verse\Fetcher` (payload shape in Appendix A: `data[]` items with `id, title, scripture_reference, scripture_text, published_at`; per-item URL `verse.item_url_pattern`), `Verse\Cron` (05:00 site time + retry), `Verse\Admin`, CLI `verse fetch|inspect|log`.
- `Query\Lead`, `Query\Cells` filter, bindings `ttm/kicker`, `ttm/meta-line`, `ttm/short-date`, `ttm/relative-date`, `ttm/category-count`, `ttm/today`.
- Patterns: `lead-story`, `journal-rail`, `section-cell`, `section-cell-large`, `section-row-1`, `section-row-2`, `series-strip`; template `front-page.html`.
- Fallbacks F1, F2, F4, F6, F7, F8, F9, F10, F17, F22, F25 with integration tests (use `wp ttm seed --state=quiet` and `--state=empty` fixtures in tests via the same seeder class).
- **Visible result:** `/` matches `2a`; at 390 matches `3a`. Manual check: compare against the prototype badges `2a`/`3a`; verify the verse box shows the seeded verse with attribution link.

### Phase 4 — Article and Journal

- Blocks: `ttm/series-bar`, `ttm/series-toc`, `ttm/series-prev-next`, `ttm/syndicated-to`. Bindings `ttm/reading-time`, `ttm/word-count`, `ttm/journal-subline`, `ttm/series-name`, `ttm/series-part`.
- Patterns: `article-header`, `more-in-section`, `journal-stream`; templates `single.html`, `single-journal.html`; `Templates\Hierarchy` routing.
- Body typography for classic content: `.entry-content` rules also cover raw `<p>`, `<blockquote>`, `<pre>`, `<table>`, `<sup class="modern-footnotes-footnote">` (legacy footnotes plugin markup on old posts) and core footnotes.
- Fallbacks F11, F12, F13, F14, F23, F24 with tests.
- **Visible result:** a seeded series post matches `2b`/`3b`; a seeded journal post matches `2c`. Manual check: sticky aside at ≥ 1024; prev/next chronological on a non-series post.

### Phase 5 — Archives and search

- Blocks: `ttm/archive-by-year`, `ttm/tag-filter`, `ttm/category-stats`, `ttm/most-read`. Binding `ttm/pagination-label`. `Query\Archive` (`pre_get_posts`, year-range labels).
- Patterns: `archive-header`, `filter-row`; templates `category.html`, `category-journal.html`, `archive.html`, `search.html` (complete).
- Section feed `<link rel="alternate">` tags; `journal_in_main_feed` setting.
- Fallbacks F15, F16 with tests; pagination with year ranges tested against seeded dates.
- **Visible result:** `/category/security/` matches `1e`; `/category/journal/` is the stream; `/tag/wordpress/` and `/?s=cache` render. Manual check: `?tag=` filter within a category.

### Phase 6 — Series hub, Writing, fiction

- Blocks: `ttm/series-featured`, `ttm/series-progress`, `ttm/series-stats`, `ttm/serial-hero`, `ttm/story-tiles`, `ttm/book-grid`. `Fiction\Serials`, `Fiction\Books`.
- Templates: `page-series.html`, `taxonomy-series.html`, `page-writing.html`; Writing category → page template routing; `/series/` page + slug reservation on activation.
- Fallbacks F3, F5, F19, F20, F21 with tests.
- **Visible result:** `/series/` matches `1f`; `/series/<seeded-serial>/` renders; `/writing/` matches `2d` with the seeded cover. Manual check: cover shadow only on the Writing hero.

### Phase 7 — Cache-safety, newsletter, security hardening

- `Cache\Headers`, `Cache\Purge`, `Cache\Cloudflare` (adapter, constants only), `Cache\Batcache`.
- `Newsletter\Handler` + providers (`Jetpack` default, `CustomUrl`, `Mailto`, `None`), settings UI, honeypot, rate limit, HMAC token (custom-url only); inline "Check your inbox." state via `?subscribed=1` + a `data-state` swap in the pattern (CSS only). In wp-env, install Jetpack in the seed (`wp plugin install jetpack --activate`) so the `jetpack/subscriptions` block registers; it renders its form without a WordPress.com connection, which is enough to style it. ⚠️ ASSUMPTION — if the block refuses to render unconnected, the theme styles it against `docs/fixtures/jetpack-subscriptions.html` (captured markup) and the seed uses provider `mailto`.
- Static audit task: run the greps in §3 and record results in the log; fix violations.
- Security headers are **not** set by the plugin (Cloudflare does it) — document in DEPLOYMENT.md instead.
- Tests: `Cache\Headers` boundary math with a fixed `Clock` (midnight vs 06:00, DST day, cap); purge URL list per post/section; newsletter handler accepts current/previous token windows, rejects honeypot, rate-limits, never reflects input; providers mocked with `pre_http_request`.
- **Visible result:** `curl -I localhost:8888/` shows the computed `Cache-Control`; `wp ttm verse fetch` logs a purge. Manual check: the poster shows the Jetpack form styled per `01 §4.33`; switch settings to `mailto` and `none` and confirm F26.

### Phase 8 — Migration and cleanup tooling, e2e and accessibility

- CLI `audit` (all checks; the `broken-internal-link` check builds an in-memory slug index from the local DB, no HTTP).
- Spike (bounded, one task): `scripts/convert-classic.mjs` against `docs/fixtures/classic-sample.html` (three real pre-2016 posts including one with legacy footnotes) — output serialises with zero `core/freeform` and equal text content, or the task records what fails and the CLI still ships with `--allow-freeform`.
- CLI `convert:export`, `convert:import`, `convert:revert`; `migrate:politics`, `migrate:redirects`; existing comments closed by `migrate:politics`? **No** — a separate `wp ttm migrate:close-comments [--dry-run]` sets `comment_status=closed` on all posts and disables pingbacks.
- `docs/MIGRATION.md` cross-checked against the commands as built (update the doc if a flag changed).
- Playwright suite (`tests/e2e`): the seven screens at 1280 and 390 with axe (`serious`/`critical` = 0), keyboard focus visible on nav/items/buttons, no network requests other than same-origin documents/assets (assert with `page.on('request')`), no `wp-json` or `admin-ajax` on the front end.
- CI: add the e2e job; upload Playwright report as an artifact.
- Final `HANDOFF.md` lists every manual check.
- **Visible result:** CI green including e2e; `wp ttm migrate:politics --dry-run` prints a plan against the seed. Manual check: run the migration against a real WXR export in wp-env per `docs/MIGRATION.md`.

## 9. Open questions

Decisions recorded here override the defaults in `docs/07-open-questions.md` where they differ; the planner records anything still open under `## Decisions` in `PLAN.md`.

| # | Question | Decision for this flight |
|---|---|---|
| Q1 | Greenfield vs Powder child | **Greenfield** `ttm-theme` (no Powder dependency). |
| Q2 | Journal template selection | **Primary-category filter** via `Templates\Hierarchy`. |
| Q3 | Politics | **Child category** `opinion/politics`; `migrate:politics` default `--to=child`; redirect map emitted by `migrate:redirects`. The live site's Politics term ID is 112, Opinion does not yet exist and is created by the migration. |
| Q4 | Books storage: option repeater vs CPT | **Planner decides** and records the rationale under `## Decisions`. Design default is the `ttm_books` option repeater (a handful of items, no book pages). A CPT is acceptable if the planner judges the repeater UI costs more than `register_post_type` with `show_in_rest` and a `book` template; either way the data lives in the plugin and `ttm/book-grid` is the only consumer. |
| Q5 | Newsletter provider | **Jetpack Subscriptions** (owner decision 2026-09-20; the live site already uses Jetpack Newsletter). `ttm/newsletter-form` renders the `jetpack/subscriptions` block; `custom-url`, `mailto`, `none` remain as adapters. No Buttondown work. |
| Q6 | Most read | Manual flag `ttm_featured_in_section`. |
| Q7 | Journal in main feed | Yes, setting exists. |
| Q8 | Comments | Off, intentionally, for cache-friendliness. `migrate:close-comments` closes existing threads. Revisit only after content cleanup and migration. |
| Q9 | Verse payload | **Resolved** — see Appendix A. |
| Q10 | Writing URL | `/writing/` page is canonical (the page already exists on the live site); `/category/writing/` uses the same template (no redirect in the PoC; redirect is a one-line nginx rule in DEPLOYMENT.md). |
| Q11 | Series hub URL | `/series/` page + `series/{slug}` taxonomy. Activation reserves the page. |
| Q12 | Newsletter archive | Not built; Jetpack hosts it. `/newsletter/` page = poster + explainer. |
| Q13, Q14, Q16, Q18, Q19 | As in `07` | Accept the defaults. |
| Q15 | Analytics | Jetpack Stats, already active on the live site; no consent UI; nothing in this repo. |
| Q20 | Dark mode | Not this flight. Keep colours as presets so it can be added later. |
| Q17 | Migrating existing series | Owner to list them; candidate tags observed on the live site: `boundless-summer-challenge` (21), `cryptopals` (9), `boundless` (23). `series:assign` handles each. Fiction: the live Writing category holds posts *about* writing; no chapters exist yet, so the live front page will render F2 until fiction is filed. Document in MIGRATION.md. |
| Q-C1 | Batcache vs Cloudflare APO | Support both: the plugin sets headers and purges; nothing in the theme/plugin depends on either being present. |
| Q-C2 | "Today" in the masthead under caching | Server-rendered; correctness comes from the midnight cache boundary (§6.10). No client-side date JS. |
| Q-C3 | Nonce on newsletter form | Replaced by the HMAC window token (§6.9). |
| Q-M1 | Classic content | Render as-is through `core/post-content`; style raw HTML in `.entry-content`. |
| Q-M2 | Legacy footnotes (`modern-footnotes` plugin markup) | Style `.modern-footnotes-footnote` as the 12px accent-700 footnote number; do not convert. |
| Q-M3 | Block conversion of classic posts | **In scope** (owner decision 2026-09-20): `convert:export` → `scripts/convert-classic.mjs` → `convert:import`, with backup and revert (§6.7, Phase 8). |
| Q-M4 | Jetpack Publicize share URLs → `ttm_syndication` | Spike task in Phase 8, bounded to 1 task: read `jetpack_social_post_already_shared` / `_wpas_done_*` meta where present and populate `ttm_syndication`; otherwise leave empty. |
| Q-T1 | Repo directory names | `plugins/ttm-core`, `themes/ttm-theme` at the repo root (the repo name `ttmm_theme` is historical). |

## Appendix A — Verse API payload (verified 2026-09-20)

`GET https://dailymedtoday.com/api/v1/meditations/` returns Laravel-style pagination (20 per page, newest first), rate-limited at 60 requests/minute (`x-ratelimit-*` headers), `cache-control: no-cache`.

```json
{
  "data": [
    {
      "id": "846bb2a9-9381-4b66-baf0-14eb024e4c7b",
      "title": "Where Mercy and Hope Meet",
      "scripture_reference": "Psalm 33:20-22",
      "scripture_text": "We wait in hope for the LORD; he is our help and our shield. ...",
      "meditation_content": "…long text, NOT stored…",
      "themes": ["hope", "mercy"],
      "liturgical_season": "Ordinary Time",
      "published_at": "2026-09-20T05:00:00-07:00",
      "copyright_notice": "Scripture quotations taken from The Holy Bible, New International Version® ..."
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=15", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "last_page": 15, "per_page": 20, "total": 300 }
}
```

Mapping: `date` = `published_at` converted to site timezone `Y-m-d`; `text` = `scripture_text`; `reference` = `scripture_reference`; `title`; `url` = `verse.item_url_pattern` with `id` (`https://dailymedtoday.com/meditation/{id}` returns 200); `copyright` = `copyright_notice`. Pick the item whose date equals today in site time, else the newest item with date ≤ today. Only page 1 is ever fetched. The full sample is in `docs/fixtures/verse-sample.json` and is the fixture for `Verse\Fetcher` tests.

## Appendix B — Live site facts (for seeding and migration)

- WordPress 7.1.1, theme Powder, timezone `America/Los_Angeles`, 888 posts, permalinks `/%postname%/`.
- Categories (ID · slug · count): 8 technology 431 · 52 business 231 · 106 faith 100 · 108 journal 87 · 4 writing 70 · 14 security 36 · 112 politics 24 · 1 uncategorized 0. No `opinion` term yet.
- 43% of recent posts have more than one category; the most common pairs are technology+security and technology+business; journal posts are often also filed in technology.
- Pre-2016 posts are classic HTML; recent posts are block content. Some old posts use the `modern-footnotes` plugin markup; recent posts use core footnotes meta.
- Existing pages: `blog`, `writing`, `speaking`. No `/series/`.
- Jetpack is active (Publicize/Social, Subscriptions); the `encypher-provenance` plugin adds `_encypher_*` post meta (leave untouched).
- Cloudflare already fronts the site with APO (`cf-edge-cache: cache,platform=wordpress`) and `cache-control: max-age=7200`.

## Appendix C — Files in `docs/`

| File | What it is |
|---|---|
| `README.md`, `01`–`07` | The design handoff. Normative for visuals, content model and fallbacks. |
| `Eric Mann Newspaper.dc.html` + `image-slot.js`, `support.js` | Interactive prototype (pan/zoom canvas). Reference only; not theme code. |
| `_ds/modernist-…/styles.css` | Token sheet — hex/px source of truth. `_ds_manifest.json`, `_ds_bundle.js`, `_adherence.oxlintrc.json`: design-system tooling, ignore. |
| `SPEC.md` | This file. |
| `SETUP.md` | Local development with wp-env, running tests, CI. |
| `MIGRATION.md` | Moving the live site's content to the new model. |
| `DEPLOYMENT.md` | Production configuration: Cloudflare, Batcache, memcached, constants, purge. |
| `fixtures/verse-sample.json` | Real API response captured 2026-09-20. |
| `fixtures/jetpack-subscriptions.html` | Captured front-end markup of the `jetpack/subscriptions` block (created in Phase 7 if needed). |
| `fixtures/classic-sample.html` | Three pre-2016 posts' raw `post_content` (classic HTML, one with legacy footnotes) for the conversion spike. |
| `fixtures/seed/` | Seed content definitions used by `wp ttm seed` (created in Phase 1). |
