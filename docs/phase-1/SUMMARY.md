# Build summary — These Things Matter (ttm-core + ttm-theme)

**Merge line:** `build/2026-09-21` → `poc`, base `8379c6f` → head `4f9537d`, 208 commits, 96 tasks (76 build + 20 review fixes), 3 review rounds (4 review passes), final verdict **APPROVED**. Commits are unsigned by design; rebase-sign before anything reaches `main`. `FOUNDRY_FEEDBACK.md` (untracked, repo root) is the operator's log and stays out of git.

## What was built

**Phase 0 — Toolchain (P0-01..P0-07).** Config/Clock/Dates/Text/Html support layer, the plugin composition root (`Plugin::boot()`, `TTM_CORE_API` version compat), self-hosted Archivo (8 woff2, unicode-range split), `theme.json` v3 presets, base CSS, CI wiring, fixtures mapped into wp-env.

**Phase 1 — Data model and editor (P1-01..P1-15).** `series` taxonomy with term meta and single-series enforcement, post meta with sanitizers, primary category / form / word-count derivation on save, the series index and category stats (options + transients), term admin columns and part list, editor sidebar, pre-publish warnings, settings page (General, Books repeater), REST series/lead endpoints, CLI `recount`/`primary:assign`/`series:assign`/`series:rebuild`, and the seeder (`wp ttm seed`, states normal/quiet/empty, GD-generated images).

**Phase 2 — Theme foundation and chrome (P2-01..P2-07).** Block styles, image sizes, `ttm.css` (grids, rules, type, buttons, chrome), `nav.js` (no network), editor CSS, block variations and starter content, template parts and 7 chrome patterns, page/404/index/search shells, current-section nav highlighting and body classes.

**Phase 3 — Front page (P3-01..P3-12).** Block registrar and webpack entries, verse-of-the-day (fetcher, cron, admin tab, CLI), lead selection and cell query filter, bindings (kicker, meta-line, short/relative date, category-count, today), journal derived excerpt, lead-story / series-list / writing-cell / newsletter-form blocks (jetpack, mailto, none providers), front-page patterns, rail, template and CSS, quiet/empty fallback states.

**Phase 4 — Article and Journal (P4-01..P4-07).** series-bar, series-toc, series-prev-next, syndicated-to blocks; reading-time, word-count, journal-subline, series-name/part bindings; template routing via `*_template_hierarchy`; article and journal patterns, templates and CSS including classic-content styling.

**Phase 5 — Archives and search (P5-01..P5-05).** Archive query, pagination labels, section feeds, journal-in-main-feed; archive-by-year, tag-filter, category-stats, most-read blocks; archive/search patterns, templates and CSS.

**Phase 6 — Series hub, Writing, fiction (P6-01..P6-06).** series-progress, series-stats, series-featured, serial-hero, story-tiles, book-grid blocks; hub and Writing templates and CSS.

**Phase 7 — Cache-safety, newsletter, hardening (P7-01..P7-09).** Computed `Cache-Control` (GET/HEAD, anonymous), Batcache bridge, purge with Cloudflare adapter, newsletter handler with custom-url provider, token/rate-limit tuning, Jetpack unconnected-render check, extended `forbidden-patterns.sh`, separability tests (theme alone / plugin alone).

**Phase 8 — Migration tooling, e2e, docs (P8-01..P8-08).** Classic-to-block conversion (`scripts/convert-classic.mjs`, jsdom + `rawHandler`), CLI `convert:export/import/revert`, `audit`, `migrate:politics/redirects/close-comments/syndication`, plugin README, `docs/MIGRATION.md`, `docs/SETUP.md`, Playwright + axe e2e suite (48 tests, 2 viewports) in CI, HANDOFF.

**Review fixes (R1-01..R1-15, R2-01..R2-03, R3-01..R3-02).** Module-boundary repairs with a `BoundariesTest`, rule-24 Config keys and a failing (not warning) enforcement grep, F6/F9 fallback corrections, Sept date format and DST-safe verse cron, single-purge batch commands, HEAD cache headers, honeypot CSS, landmark/h1 fixes, i18n, F9 stale-year moved fully plugin-side with cached staleness, one pagination-label code path, and test-gap closures.

Final state: `composer test:unit` 131, `npm run test:integration` 374, e2e 48/48, CSS budget 33070/33200 bytes, all lint and forbidden-pattern checks green.

## Decisions that shaped it

From PLAN.md `## Decisions` (task where it landed):
- Phases follow SPEC §8 in order; each phase ends with a `Manual check: NOT VERIFIED (human)` push task (P0-07 … P8-08).
- Books are an option repeater `ttm_books` on a settings tab, not a CPT (P1-10).
- `src/Plugin.php` is the only file that may import every module; every module exposes `register()` (P0-04).
- Admin settings shell `Admin/Page.php` + `Admin/General.php`; modules register their own tabs (P1-09).
- Integration PHPUnit config stays at `tests/integration/phpunit.xml.dist` (P0-01).
- Fixtures mapped into wp-env as `wp-content/ttm-fixtures`; seed definitions in `docs/fixtures/seed/*.json` (P0-01, P1-13).
- Blocks live in `plugins/ttm-core/blocks/<name>/`; root `webpack.config.js` with one entry per block; `editorScript` stripped when the build is missing so the front end works unbuilt (P3-01).
- `@wordpress/*` editor packages are devDependencies only; `dependencies` stays `{}` (P1-07).
- Template routing via `single_template_hierarchy` / `category_template_hierarchy` (P4-05).
- `ttm/pagination-label` is a binding source plus `render_block_core/query-pagination-*` filters (P5-01; moved to `Bindings\Sources` in R2-03).
- Verse endpoint: `Fetcher::ENDPOINT` constant; `verse.endpoint` config honoured only on the same host (P3-02).
- Journal derived excerpt via `get_the_excerpt` filter with `journal.excerpt_words` (P3-05).
- Relative-date ladder: Today / Yesterday / weekday within 6 days / "Sept 3" within 30 days / full date with year beyond (P3-05).
- `ttm/category-count` formats `articles` and `short`; 0 renders "All →" (P3-05).
- 8 Archivo woff2 files with `unicodeRange`; only 400/800 latin preloaded (P0-05).
- Inner masthead nav `overlayMenu:"always"`, un-overlaid by CSS at ≥721px; front masthead scroll row ≤720px; `nav.js` only mirrors open state (P2-03, P2-05).
- Image sizes in theme `inc/image-sizes.php` (filterable); rule 24 governs `plugins/ttm-core/` only (P2-01).
- Integration tests never install Jetpack; a stub `jetpack/subscriptions` block exercises the provider switch (P3-09).
- Seed images generated with GD at seed time; no binary fixtures (P1-13).
- Added config keys `journal_in_main_feed=true`, `comments_enabled=false`, `newsletter.list_id=''` (P0-02).
- One measured tuning task per ASSUMPTION concern (P1-14, P2-03, P6-02, P7-04, P7-05, P7-06, P8-01).
- Integration test clock pinned to `2026-09-20 12:00 America/Los_Angeles` (P1-01).
- `forbidden-patterns.sh` runs after every task (P0-01; extended P7-07, R1-02, R2-01, R3-01).
- CLI commands have a `run(array, array): array` core testable without WP-CLI (P1-12).
- Opinion cell appends "· Politics" by `sections.politics_slug` child category, not tags (P3-04).
- Branch: PLAN says `poc/2026-09-20`; the run used `build/2026-09-21` per `foundry.json` (`branchPrefix: build/`).

Build-phase interpretation choices accepted by review (from commit bodies): `sanitize_part` int cast rejecting negatives (P1-02); `newsletter.fallback_email` in the settings overlay, `newsletter.api_key` excluded (P1-09); no `exit` after `wp_safe_redirect` with a `headers_sent()` guard (P7-03); Registrar/Sources re-registration guards (P3-01, P3-05); `query_loop_block_query_vars` reading the child block's context (P3-04); F2 requires `ttm_form_locked` (P3-08); section-cell template kept outside the scanned patterns dir (P3-10); Cloudflare listener always attached, `available()` checked at call time (P7-02); purge on publish→publish (P7-02); Jetpack Outcome B — unconnected Jetpack never registers the block, seed falls back to `mailto` (P7-04); jsdom + `rawHandler` Outcome A, CJS build via `require()` (P8-01); only `_publicize_done_external` can carry a share URL (P8-05); `partsLimit:0` explicit vs defaulted (P6-02).

Review-round interpretation choices (HANDOFF §Round 1–3):
- R1-01: pagination-label helpers stayed on `Query\Archive` (later moved by R2-03); archive scope counter moved to `Blocks\Helpers`, now a registered `Plugin` module; `Meta\SeriesPosition` reads the `ttm_series_index` option directly; new `Editor\SeriesPartList` owns the part list.
- R1-02: rule-24 allow-list for `Query/Cells.php` and `writing-cell/render.php` (removed by R2-01).
- R1-03: term `ttm_form` set before the assignment loop so `chapter` derives immediately.
- R1-04: F6 uses the stored verse when dated today-or-earlier (`date > today` → history); verse cron self-reschedules a single event instead of `'daily'`.
- R1-05: F9 dek suppression server-side, not CSS; `cells.thin_days` removed as dead.
- R1-06: `close_comments()` uses `$wpdb->update()` + `clean_post_cache()` to avoid per-post purges.
- R1-09: `masthead-inner` site-title set to level 2 (fixed a pre-existing double-h1 outside the task's file list).
- R1-14: masthead-nav test executes the pattern file directly (pattern registry is mtime-cached).
- R1-15: new `scripts/lib/report.mjs` so the "runs without block-library" test can execute under Jest.
- R2-01: category resolved via `get_term_by('slug')` before the transient read; `is-stale` and `is-empty` are independent wrapper classes.
- R2-02: `BoundariesTest` inline-reference scanner strips comments naively (not token-aware).
- R2-03: `Archive::year_range()` keeps the bounded ids-only re-query; `ScaffoldTest` module-map check is line-scoped and allows the `Cli/*Command` shorthand.
- R3-01: plain-assignment rule-24 grep mirrors the `=>` grep's ranges and HTTP-status allow-list; not widened to function arguments or comparisons.
- R3-02: none.

## Assumptions still in play

| Key | Final default | Status |
|---|---|---|
| `series.max_purchase_links` | 6 | measured (seed max 2), kept |
| `series.hub_featured_parts` | 12 | kept; no prototype access, seed overflow exercised — still a guess for real content |
| `cache.verse_boundary_hour` | 6 | measured, kept |
| `cache.max_age_cap_seconds` | 86400 | measured, kept; equals `newsletter.token_ttl` with zero slack |
| `cache.min_age_seconds` | 60 | kept, reasoned only |
| `newsletter.token_ttl` | 86400 | measured; must stay ≥ `cache.max_age_cap_seconds` |
| `newsletter.rate_limit_per_ip` | 5 | reasoned, not measured against traffic; behind Cloudflare Tunnel it is site-wide unless `CF-Connecting-IP` is restored (DEPLOYMENT.md) |
| `newsletter.rate_limit_window` | 600 | reasoned, not measured |
| `cells.stale_count` | 2 | copied from prior literal, untuned (R2-01) |
| `writing.tile_columns` | 2 | design number ("tiles 2-col"), not really an assumption (R3-01; review S2) |
| `cssBudgetBytes` (`scripts/check-budget.mjs`) | 33200 | retuned five times (25600→28000→33000→32700→33200); 33070 used — 130 bytes headroom |
| Jetpack unconnected render (SPEC §6.9/§8) | — | disproved (P7-04): block never registers without a WordPress.com connection; seed falls back to `mailto` |
| jsdom sufficiency for `rawHandler` (P8-01) | — | confirmed on 3 fixtures; not yet run on a real archive |

## Spec issues (edits the owner should make to SPEC.md)

From PLAN.md:
- §4.1: integration PHPUnit config lives at `tests/integration/phpunit.xml.dist`, not the root.
- §6.4 / 05 §11: routing filters are `single_template_hierarchy` / `category_template_hierarchy`.
- §6.3: core pagination blocks accept no bindings; document the render-filter path.
- §5.4: add `journal_in_main_feed`, `comments_enabled`, `newsletter.list_id`.
- Rule 16 vs §5.4 `verse.endpoint`: state the same-host rule.
- 04 §8: Archivo is 8 woff2 files (unicode-range split), not 3–4.
- Rule 24 scope: plugin only; theme design numbers live in `inc/image-sizes.php`, CSS, theme.json.
- 03 §13 vs F7: relative date shows the year beyond `journal.rail_window_days` even in the current year.
- §4.1: blocks in `plugins/ttm-core/blocks/` need the root `webpack.config.js` entries.
- §4.1 `docs/` read-only: name the writable operational files (MIGRATION, SETUP, HANDOFF, fixtures/seed, spikes).
- 05 §1 module names stale vs §4.2; 06 F18 / 02 §F `wp_nav_menu_items` stale vs §6.4; 01 §4.35 `ttm/story-tile` → `ttm/story-tiles`; 02 §A `ttm/exclude-lead` and 02 §E `ttm/year-divider` do not exist.
- 03 §12 newsletter copy ("The weekly issue." / "lands on Sundays") still needs owner confirmation.
- §6.6 `/verse` "without copyright omitted" — reads as "include copyright".
- 05 §3 `ttm/series-toc` needs `seriesId`; `most-read` `source: views` is treated as `manual`; 02 §A journal heading uses the count form.
- 04 §1 `variations.js` is editor-only, so rule 6 holds on the front end.

From reviews (SI-1..SI-13):
- SI-1 rule 15: "JSON via `wp_json_encode`" → "schema-validated arrays" (typed `show_in_rest` needs native arrays).
- SI-2 §4.2: widen the "May import" column for the downward imports `Editor`/`Templates`/`Cache`/`Rest` need.
- SI-3 §5.4: add `sections.technology_slug`.
- SI-4 §4.3 / 04 §1: drop `themes/ttm-theme/inc/template-hierarchy.php` (never built; routing is plugin-side).
- SI-5 rule 12: scope "every query is bounded" to request-time code, not CLI term enumeration.
- SI-6 rule 30: decide the CSS budget once (25 KB cannot hold the design; 33 KB measured).
- SI-7 §6.10: "anonymous GET" → "GET/HEAD".
- SI-8 §5.4 / 06 F9: remove `cells.thin_days` (needs no code).
- SI-9 §4.2: `Verse` → `Cache` import contradicts the table order; reorder or drop.
- SI-10 §5.4: add `cells.stale_count = 2`.
- SI-11 §4.2: year grouping lives in `Blocks\Helpers`, pagination labels in `Bindings\Sources`; reword the `Query/` and `Bindings/` rows.
- SI-12 rule 24: name the tolerated classes (HTTP status codes, structural arithmetic, `Config::get()` fallback arguments).
- SI-13 §5.4: add `writing.tile_columns = 2`, unmarked.

Open non-spec residue: `Query/Cells.php:26-30` docblock still mentions `Query\Archive` as a counter home (review S1, fix on next edit); `writing-cell/render.php` `posts_per_page => 3` is a future Config-key candidate (HANDOFF §5).

## Manual checks owed

All run against `npx wp-env start && npm run env:seed`; none were done by a human.

- **Phase 0** — Front page renders in Archivo on `#f3f2f2` with zero requests to `fonts.googleapis.com`/`fonts.gstatic.com`; wp-admin shows no ttm-core/ttm-theme version-mismatch notice.
- **Phase 1** — Series term edit screen shows fields and ordered part list; post editor sidebar shows series, part "12 of 31", form Chapter; pre-publish panel warns (does not block) on missing dek/alt/part; Posts list shows Primary section / Series / Words columns; Settings → These Things Matter General tab round-trips; Books tab row persists.
- **Phase 2** — At ≤720px the nav overlay opens, closes, and closes on link tap; inner masthead nav not overlaid at ≥721px; deactivate/reactivate theme on a fresh site creates 7 categories, 4 pages with templates, Sections nav, 3 variations in the inserter; skip link, landmarks and 7 chrome patterns present; post, category archive, 404 and search show current-section highlighting; `/about/` at 1280 and 390 per the P2-07 log.
- **Phase 3** — `/` vs prototype 2a (1280) and 3a (390): lead, verse box with underlined `dailymedtoday.com` link, Technology spans 2, Writing cell "The Quiet Ledger — Ch. 12", series strip, red poster, phone stacking.
- **Phase 4** — Seeded `hardening-wordpress` part vs 2b/3b (series bar, hero, sticky aside, stacked prev/next); non-series Technology post shows "← Previously in Technology"; seeded journal post vs 2c (big date, "Thursday · Portland", syndication line, Earlier stream).
- **Phase 5** — `/category/security/` vs 1e incl. `?tag=` narrowing and accent tag; `/category/journal/` stream; `/tag/<seeded>/` and `/?s=cache` without a filter row.
- **Phase 6** — `/series/` vs 1f; `/series/the-quiet-ledger/` full part list and Other series; `/writing/` vs 2d (cover shadow only there, tiles 2-col, In print grid); `/category/writing/` same layout.
- **Phase 7** — `curl -I http://localhost:8888/` shows the computed `Cache-Control` (HEAD included); `wp ttm verse fetch --force` logs a purge; poster shows the mailto button; switching provider to `mailto` and `none` behaves per F26.
- **Phase 8** — CI green including the e2e job and `playwright-report` artifact; rehearse `docs/MIGRATION.md` §1–§2 against a real archive/WXR in wp-env (audit, migrate:politics, primary:assign, series:assign boundless/cryptopals, recount, convert:export → convert-classic → convert:import, migrate:syndication, close-comments), opening the front page, `/category/technology/` and a journal post after each step; repeat until it runs clean unattended.
- **Round 1** — Verse box "Meditation for Sept N" wording and the Writing page h1 (R1-04/08/09); with real content, a stale-year section renders no dek and exactly 2 rows (R1-05); `migrate:politics --to=child` and `migrate:close-comments` purge once per batch (R1-06); Journal post with syndication URLs matches 01 §4.13 — never visually reviewed (R1-12); renaming a category updates the masthead nav immediately (R1-14).
- **Round 2** — A genuinely stale section in production shows no dek in HTML, exactly `cells.stale_count` rows, and later sections keep their deks (R2-01); multi-page category archive shows "Older (…) →" / "← Newer (…)" on both the binding and the pagination links (R2-03).
- **Round 3** — Nothing new to eyeball (R3-01 is markup-identical; R3-02 is test-only).

## Review history

| Round | Findings | Fix tasks | Recurred |
|---|---|---|---|
| 1 (`e07fb26`/`f25bd37`) | 28 (4 constraints, 4 boundaries, 5 tests, 2 performance, 13 spec drift/bugs) plus i18n/docs notes; CHANGES REQUESTED | R1-01..R1-15 | — |
| 2 (`2df3fd5`) | 8 (2 constraints, 1 boundary, 2 tests, 3 low); CHANGES REQUESTED | R2-01..R2-03 | Rule 24 literals (R1 C2 → R2 C1); CSS budget retuning (R1 C1 → R2 S1); F9 mechanism from R1-05 reworked |
| 3 (`51e3bce`) | 3 (1 constraint, 1 test, 1 low); CHANGES REQUESTED | R3-01..R3-02 | Rule 24 literal again (story-tiles); enforcement grep widened to `= N;` |
| Final (`4f9537d`) | 0 defects; S1 docblock residue and S2 doc note only; APPROVED | none | none |
