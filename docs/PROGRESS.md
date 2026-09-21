# These Things Matter build progress
Branch: build/2026-09-21
Started: 2026-09-21T05:15:08.115Z

## Tasks
- [x] P0-01 Branch, harness confirmation, fixtures mapping
- [x] P0-02 Config and Clock
- [ ] P0-03 Dates, Text and Html helpers
- [ ] P0-04 Plugin composition root and API-version compat
- [ ] P0-05 Self-hosted Archivo fonts, theme enqueue and base CSS
- [ ] P0-06 theme.json v3 presets and token check
- [ ] P0-07 Push, CI and manual check (Phase 0)
- [ ] P1-01 Series taxonomy, term meta, single-series enforcement
- [ ] P1-02 Post meta registration and sanitizers
- [ ] P1-03 Primary category, form derivation and word count on save
- [ ] P1-04 Series index
- [ ] P1-05 Category stats and top tags
- [ ] P1-06 Series term admin: columns, edit fields, part list
- [ ] P1-07 Editor sidebar panel
- [ ] P1-08 Pre-publish checks and post list columns
- [ ] P1-09 Admin settings page and general settings
- [ ] P1-10 Books repeater
- [ ] P1-11 REST series endpoints and lead stub
- [ ] P1-12 CLI recount, primary:assign, series:assign, series:rebuild
- [ ] P1-13 Seeder core: categories, pages, navigation, posts, images
- [ ] P1-14 Seeder fiction, verse, states and seed command; tuning series.max_purchase_links
- [ ] P1-15 Push and manual check (Phase 1)
- [ ] P2-01 Block styles, pattern categories, image sizes
- [ ] P2-02 ttm.css foundation: bridge, grids, rules, type utilities, buttons, tags, inputs, body typography
- [ ] P2-03 ttm.css chrome, nav.js, editor.css; CSS budget tuning
- [ ] P2-04 Block variations and starter content
- [ ] P2-05 Template parts and chrome patterns
- [ ] P2-06 page, 404, index and search-shell templates; current section and body classes
- [ ] P2-07 Push and manual check (Phase 2)
- [ ] P3-01 Block registrar, shared helpers, webpack entries, verse-of-the-day block
- [ ] P3-02 Verse fetcher
- [ ] P3-03 Verse cron, admin tab and CLI
- [ ] P3-04 Lead selection, cell query filter and lead REST
- [ ] P3-05 Bindings kicker, meta-line, short-date, relative-date, category-count, today; journal excerpt
- [ ] P3-06 lead-story block
- [ ] P3-07 series-list block
- [ ] P3-08 Serials query and writing-cell block
- [ ] P3-09 newsletter-form block with jetpack, mailto and none providers
- [ ] P3-10 Front-page patterns, rail, template and front CSS
- [ ] P3-11 Front-page fallback state tests (quiet, empty)
- [ ] P3-12 Push and manual check (Phase 3)
- [ ] P4-01 series-bar block
- [ ] P4-02 series-toc block
- [ ] P4-03 series-prev-next and syndicated-to blocks
- [ ] P4-04 Bindings reading-time, word-count, journal-subline, series-name, series-part
- [ ] P4-05 Template routing, article and journal patterns and templates
- [ ] P4-06 Article and journal CSS including classic content
- [ ] P4-07 Push and manual check (Phase 4)
- [ ] P5-01 Archive query, pagination labels, section feeds, journal-in-main-feed
- [ ] P5-02 archive-by-year and tag-filter blocks
- [ ] P5-03 category-stats and most-read blocks
- [ ] P5-04 Archive and search patterns, templates and CSS
- [ ] P5-05 Push and manual check (Phase 5)
- [ ] P6-01 series-progress and series-stats blocks
- [ ] P6-02 series-featured block; tuning series.hub_featured_parts
- [ ] P6-03 serial-hero block
- [ ] P6-04 story-tiles and book-grid blocks
- [ ] P6-05 Hub and Writing templates and CSS
- [ ] P6-06 Push and manual check (Phase 6)
- [ ] P7-01 Cache headers and Batcache
- [ ] P7-02 Purge and Cloudflare adapter
- [ ] P7-03 Newsletter handler, custom-url provider, settings
- [ ] P7-04 Jetpack unconnected-render check (assumption) and seed provider
- [ ] P7-05 Cache tuning: verse_boundary_hour, max_age_cap, min_age
- [ ] P7-06 Newsletter tuning: token_ttl, rate limits
- [ ] P7-07 Static audit: extend forbidden-patterns, run, fix
- [ ] P7-08 Separability tests (theme without plugin, plugin with default theme)
- [ ] P7-09 Push and manual check (Phase 7)
- [ ] P8-01 Spike: classic-to-block conversion script (jsdom + rawHandler)
- [ ] P8-02 CLI convert:export, convert:import, convert:revert
- [ ] P8-03 CLI audit
- [ ] P8-04 CLI migrate:politics, migrate:redirects, migrate:close-comments
- [ ] P8-05 Spike: Jetpack Social share URLs to ttm_syndication
- [ ] P8-06 Plugin README, MIGRATION and SETUP cross-check
- [ ] P8-07 Playwright + axe e2e suite and CI
- [ ] P8-08 Push, final manual checks and HANDOFF (Phase 8)

## Log
(one entry per task, appended by implement)

### P0-01 — 33d8341
Verified full scaffold green: composer lint/test:unit, npm lint/test:unit/build, forbidden-patterns, and npm run test:integration (wp-env start ~42s, phpunit 2/2 pass) all pass on clean checkout. Added "wp-content/ttm-fixtures": "./docs/fixtures" mapping to .wp-env.json (kept ttm-tests/ttm-vendor mappings). Added BootTest::test_fixtures_are_mapped_into_the_container asserting file_exists() for verse-sample.json and classic-sample.html via WP_CONTENT_DIR. Documented the fixtures mapping in docs/SETUP.md under "How the integration harness works", including the wp-env destroy/start rebuild tip.
Separately fixed docs/foundry.json: branchPrefix "poc/" collided with baseBranch "poc" (git can't hold both a "poc" ref and "poc/*" refs) — changed branchPrefix to "build/" so foundry_run_start could create the run branch at all. This is a pipeline-config fix, not a SPEC/PLAN task deliverable.
wp-env image pull/start time: ~43s (first start this run; images were already cached locally).

### P0-02 — 5d52053
Config::defaults() returns all §5.4 keys as flat dotted strings plus journal_in_main_feed/comments_enabled/newsletter.list_id additions. Config::all() overlays get_option('ttm_settings',[]) (flattened one level: key.subkey) onto defaults for exactly 8 allowed keys, then apply_filters('ttm_config'), memoised in static $cache; Config::reset() clears it. Config::get($key,$fallback) — param renamed from $default (PHPCS reserved-keyword warning). Clock is the only DateTime* constructor site in plugins/ttm-core/src (forbidden-patterns.sh enforces via grep -v Support/Clock.php exclusion, confirmed clean). Clock::now()=apply_filters('ttm_now', new DateTimeImmutable('now', wp_timezone())); ::at() catches Exception on invalid strings and returns null.
tests/unit/TestCase.php now stubs the shared Brain\Monkey WP function set named in the task (__, _x, esc_html__, esc_html, esc_attr, esc_url, esc_url_raw, wp_kses, sanitize_text_field, absint, wp_timezone→America/Los_Angeles, get_option→[], _n) plus a default pass-through apply_filters(tag,value)->value that individual tests override with Functions\when('apply_filters')->alias(...) for the specific tag under test (ttm_config, ttm_now). This pattern (override apply_filters per-test) will be needed by every future unit test that touches a filtered value — later tasks should follow ConfigTest/ClockTest as the model.
13/13 unit tests pass; full verify set (incl. integration) green.
