# These Things Matter build progress
Branch: build/2026-09-21
Started: 2026-09-21T05:15:08.115Z

## Tasks
- [x] P0-01 Branch, harness confirmation, fixtures mapping
- [x] P0-02 Config and Clock
- [x] P0-03 Dates, Text and Html helpers
- [x] P0-04 Plugin composition root and API-version compat
- [x] P0-05 Self-hosted Archivo fonts, theme enqueue and base CSS
- [x] P0-06 theme.json v3 presets and token check
- [x] P0-07 Push, CI and manual check (Phase 0)
- [x] P1-01 Series taxonomy, term meta, single-series enforcement
- [x] P1-02 Post meta registration and sanitizers
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

### P0-03 — 9421779
Dates::short_month/short/short_with_year/full/compact/masthead/weekday/relative_day/days_between implemented per 03 §13 formats; relative_day uses days_between(d,now) with midnight-normalized DateTimeImmutable::diff. Correction: task's worked example paired 2026-09-18 with "Thursday" but that date is actually a Friday given now=2026-09-20 (a Sunday, per the masthead example itself) — verified with `date -d`, implemented the real weekday and fixed the test's expected string.
Text::word_count strips wp:code/wp:preformatted comment blocks, <pre>, shortcode tags (regex, tags only — inner content of paired shortcodes is kept), HTML comments, then wp_strip_all_tags + html_entity_decode, counts \S+ (unicode) tokens. sentence_excerpt implements the 4-step algorithm (exact/extend +15/cut-back to half/ellipsis) using PREG_OFFSET_CAPTURE token slicing and a terminator regex allowing trailing quote/bracket after .!?…. curly_quotes protects <tags> with \x01N\x01 placeholders before running quote-direction regex on the whole string, then restores tags — needed because quote "opening" context (start/space/(/[ ) must see real text, not reset at tag boundaries.
Html::el/text/link/classes are thin escaping wrappers (href/src via esc_url, others esc_attr).
Added wp_strip_all_tags stub to tests/unit/TestCase.php (aliases trim(strip_tags())) alongside the existing sanitize_text_field stub.
29/29 unit tests pass; full verify green.

### P0-04 — 4480d1d
Plugin::modules() returns [Compat\Theme::class]; Plugin::boot() is idempotent via a static $booted guard, calls ::register() on each module once. Added Plugin::reset() (test-only) to clear the guard between tests. Compat\Theme::register() hooks admin_notices→maybe_notice; maybe_notice bails unless get_current_screen()->id is themes/plugins, shows a non-dismissible error when Theme::mismatch(theme_requires, TTM_CORE_API) is true. Theme::mismatch(?int,int) is pure: false when theme declares nothing (null), false when equal, true otherwise.
themes/ttm-theme/inc/bindings-compat.php defines TTM_THEME_REQUIRES_API=1 and mirrors the same admin_notices logic: info notice when ttm-core absent, error when TTM_CORE_API !== TTM_THEME_REQUIRES_API; required from functions.php. Never fatals — every TTM_CORE_API/get_current_screen access is guarded.
uninstall.php: added a bounded $wpdb->prepare() DELETE on options table only, LIKE '_transient_ttm_%' / '_transient_timeout_ttm_%' (esc_like'd), still gated by TTM_REMOVE_DATA === true; terms/meta untouched. PHPCS direct-DB-query warnings are expected/unavoidable for uninstall.php and don't fail lint (warnings, not errors).
34/34 unit tests pass; BootTest integration tests still green; full verify green.

### P0-05 — 5534ba0
Fonts downloaded live (network available): Google's css2 API serves Archivo as a variable font, so the same woff2 URL covers weights 400/600/800 per style — saved that one file under archivo-{400,600,800}-{latin,latin-ext}.woff2 and the italic file under archivo-400i-{latin,latin-ext}.woff2 (8 files total, 2 unique binaries duplicated 3x). OFL.txt fetched live (real copyright year is 2020, not the task text's stale "2016" example — used the fetched text).
Unicode ranges for P0-06/theme.json: latin = U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD; latin-ext = U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF.
functions.php: wp_enqueue_scripts registers ttm-theme style (ttm.css, filemtime version) and ttm-nav script (nav.js, footer+defer, filemtime version) — only script enqueued front-end, verified by FontsTest regex. after_setup_theme adds add_editor_style([ttm.css, editor.css]). wp_head priority 1 preloads archivo-400-latin.woff2 and archivo-800-latin.woff2 only. No wp_resource_hints hook added (nothing to add — absence is the requirement).
style.css got the 04 §2 resets appended after the header block; ttm.css got a "/* 0 base */" section with --ttm-rule-1/2 bridge vars only; editor.css created with .editor-styles-wrapper{max-width:820px}.
37/37 unit tests pass (3 new FontsTest); CSS budget 244/25600 bytes; full verify green.
Manual check: NOT VERIFIED (human) — open the site and confirm fonts render with zero requests to fonts.googleapis.com/fonts.gstatic.com in the Network tab.

### P0-06 — f7e5dc4
theme.json v3: palette (bg/surface/text/accent/accent-100/600/700/divider/neutral-100..900) from the token sheet, divider hardcoded to rgba(32,30,29,0.4) (token sheet's color-mix() form is mathematically equivalent); color.custom/customGradient/defaultPalette/defaultGradients=false, gradients/duotone=[]; typography.customFontSize/fluid/defaultFontSizes/dropCap=false; 19 fontSizes (micro 11..display-xl 80, all px); 10 spacingSizes (10:4px..100:96px); fontFamilies[0].fontFace has 8 entries (400/600/800 normal + 400 italic × latin/latin-ext) each src file:./assets/fonts/<name>.woff2 with the unicode ranges from P0-05's log; shadow.presets cover; custom.rule/measure/gutter; full styles block (elements link/heading/h1/h2/h6/button/caption, blocks separator/code/quote/pullquote/post-featured-image/image/navigation/search/post-comments-form) copied verbatim from 04 §2.
scripts/check-theme-json.mjs rewritten: parses docs/_ds/modernist-.../styles.css :root --color-* vars, compares every palette slug case-insensitively against the token (divider special-cased to the rgba literal per task text), asserts the fontSizes/spacingSizes slug->value maps above, the boolean settings, and that every fontFace src starts with file:./assets/fonts/. Exits 1 on any mismatch; exit 0 confirmed.
41/41 unit tests pass (4 new ThemeJsonTest); full verify + npm run check:theme-json green.

### P0-07 — e626d8a
Pushed build/2026-09-21 to origin (-u). Opened draft PR https://github.com/ericmann/ttmm_theme/pull/1 (base poc, title "poc: These Things Matter build"). CI run 35565102508 kicked off on push (in_progress at record time; not awaited). git log origin/build/2026-09-21 shows P0-01..P0-06 commits present.
Push: done — origin/build/2026-09-21
Manual check: NOT VERIFIED (human) — open http://localhost:8888/ after npx wp-env start: page renders in Archivo on #f3f2f2 with no request to fonts.googleapis.com or fonts.gstatic.com (DevTools Network); wp-admin shows no ttm-core/ttm-theme notice.

### P1-01 — a9e9a2e
Taxonomy\Series registered on 'post': hierarchical=false, public/show_in_rest/show_admin_column=true, rewrite slug=series/with_front=false, query_var=series. All 9 term-meta keys registered with typed sanitizers + REST schema (enum for status/form, array-of-object schema for purchase_links). enforce_single() hooks set_object_terms(10,6 args), keeps tt_ids[0] via wp_set_object_terms(...,false), static $enforcing guard prevents recursion.
Pure sanitizers (unit-tested, no WP): sanitize_status/sanitize_form (enum fallback), sanitize_next_date (regex + checkdate), sanitize_cover_id (absint only). sanitize_cover_id_checked (the actual registered callback) wraps it with wp_attachment_is_image(). sanitize_purchase_links reads Config::get('series.max_purchase_links',6), drops non-http(s) urls after esc_url_raw, caps count.
IMPORTANT for future integration tests: WP core's WP_UnitTestCase_Base::tear_down() calls unregister_all_meta_keys() after every single test (see /wordpress-phpunit/includes/abstract-testcase.php:225), wiping $wp_meta_keys globally — but taxonomies/post types are NOT wiped this way. Since the plugin registers everything via 'init' fired once at bootstrap, any test needing registered meta must re-fire it. Fixed generically: TTM_IntegrationTestCase::set_up() now calls do_action('init') before every test, re-invoking the still-attached init callbacks (register_taxonomy/register_meta are idempotent). All future modules that register meta on 'init' get this for free — no per-module test scaffolding needed.
Plugin::modules() now [Compat\Theme::class, Taxonomy\Series::class].
8 integration tests + 4 new unit tests pass (49 unit total); full verify green.

### P1-02 — e7c3dfb
Meta\PostMeta registers all 9 ttm_* post-meta keys on 'post' with auth_callback current_user_can('edit_post',$post_id). ttm_form/ttm_syndication have REST enum/object schemas. sanitize_part: custom (int)-cast + "<1 => 0" logic, NOT literal absint() — absint(-5)===5 (abs-conversion) would wrongly accept negative input as a valid positive part number; used to satisfy the task's own "rejects zero and negatives" test. sanitize_primary_category: absint then term_exists($id,'category') check, else 0. sanitize_form: enum fallback to 'article'. sanitize_syndication: only x/mastodon/bluesky keys, esc_url_raw(...,['https']) drops non-https and unknown-scheme URLs, unknown network keys silently ignored.
Plugin::modules() now [Compat\Theme::class, Taxonomy\Series::class, Meta\PostMeta::class] — picks up the TTM_IntegrationTestCase::set_up() do_action('init') refire from P1-01 automatically, no new test scaffolding needed.
12 integration tests + 3 new unit tests pass (48 unit total); full verify green.
