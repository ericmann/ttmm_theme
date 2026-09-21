# Handoff — These Things Matter build (Phases 0–8)

Branch: `build/2026-09-21` (base `poc` at `8379c6f`, current head — see git log for the exact
commit after this file is committed). 76 tasks, all `[x]` done, 0 blocked, 0 skipped. Draft PR
into `poc` stays a draft; this run does **not** run `gh pr ready` and does not merge or deploy.

Commits are unsigned by design (SPEC §2 non-goal); they will be rebased and signed by a human
before anything lands on `main`.

## 1. What's here

- `plugins/ttm-core/` — the companion plugin (content model, blocks, bindings, cache/purge,
  newsletter, cron, CLI/migration tooling). See `plugins/ttm-core/README.md` for hooks, REST
  routes, CLI flags and configuration, all cross-checked against the shipped code (P8-06).
- `themes/ttm-theme/` — the block theme (templates, patterns, `ttm.css`, `theme.json`).
- `tests/{unit,integration,e2e}` — Brain\Monkey unit tests, WP-suite integration tests (337
  passing), Playwright + axe e2e tests (48 passing across 2 viewports).
- `docs/MIGRATION.md` — the full runbook for moving eric.mann.blog onto this stack; §1–§2 is what
  the owner runs next (see §5 below).
- `docs/SETUP.md` — local dev, the test commands, and the e2e suite's own footguns.
- `docs/spikes/P8-01.md`, `docs/spikes/P8-05.md` — the two bounded research spikes.

## 2. Manual checks the owner still needs to do (human, browser)

None of these were verified by a human during this run — they're collected here from every
phase's own log entry so they can be worked through once, in order, in a browser against
`npx wp-env start && npm run env:seed`:

**Phase 0**
- Open the site and confirm fonts render with zero requests to `fonts.googleapis.com`/
  `fonts.gstatic.com` in the Network tab.
- `http://localhost:8888/` after `npx wp-env start`: page renders in Archivo on `#f3f2f2` with no
  Google Fonts request; wp-admin shows no ttm-core/ttm-theme version-mismatch notice.

**Phase 1**
- Open a series term's edit screen in wp-admin and confirm fields/part list.
- Open a post in the block editor and confirm the sidebar panel renders with all fields.
- Publish a post missing a dek/alt text/series part and confirm the pre-publish panel lists
  warnings without blocking publish; check the Posts list columns.
- Settings → These Things Matter: save the General tab, confirm it round-trips.
- Settings → These Things Matter → Books: add a row, save, confirm it persists.
- Browse the seeded series/books in `/wp-admin` once the Phase 3+ front end exists.
- After `npm run env:seed`: `/wp-admin/post.php?post=<a seeded chapter id>&action=edit` shows the
  "These Things Matter" sidebar with series, part "12 of 31", form Chapter; pre-publish panel
  warns when the excerpt is cleared; `/wp-admin/term.php?taxonomy=series&tag_ID=<the-quiet-ledger>`
  shows all fields and the ordered part list; Settings → These Things Matter has General and Books
  tabs; Posts list shows Primary section / Series / Words columns.

**Phase 2**
- Front page and an inner page, resize to ≤720px: nav overlay opens/closes and closes on link
  tap; inner masthead nav is not overlaid at ≥721px.
- Deactivate/reactivate the theme on a fresh site: 7 categories/4 pages (with templates)/Sections
  nav appear; editor shows the 3 variations in the inserter.
- Front page and an inner page: skip link/header landmarks/nav render; the 7 new patterns appear
  in the editor inserter under their categories.
- Visit a post, its category archive, a 404, and search results: current-section highlighting and
  the new template shells render.
- `http://localhost:8888/about/` at 1280: inner masthead (22px title, nav, "Newsletter"), H1 56px,
  body 18/1.65 in the 8-col column, footer links; at 390: title 18px, "Menu" opens a full-screen
  ground overlay with 24px items and 1px rules, closes with ×; no Google Fonts requests.

**Phase 3**
- `http://localhost:8888/` vs prototype badge 2a at 1280 and 3a at 390: lead 44px with grayscale
  16:9 image; rail verse box shows the seeded verse with the underlined `dailymedtoday.com` link;
  Technology spans 2 with a 3:2 image; Writing cell shows "The Quiet Ledger — Ch. 12"; series
  strip 3 rows; red poster; at 390 the nav scrolls horizontally and cells stack as zones.

**Phase 4**
- A seeded "hardening-wordpress" part vs badge 2b at 1280: series bar with segments, H1 56px max
  18ch, colour hero, sticky aside at ≥1024 with In this series / More in Technology / newsletter
  box; at 390 (3b) compact bar, full-bleed hero, stacked prev/next; a non-series Technology post
  shows "← Previously in Technology"; a seeded journal post vs 2c: big date "Sept 18",
  "Thursday · Portland", syndication line, Earlier stream.

**Phase 5**
- `/category/security/`, `/category/journal/`, `/tag/`, `/?s=` checks (see the P5-04 commit body
  for the full per-URL layout description).
- `/category/security/` vs badge 1e: 80px H1, stats right, filter row with 5 tags, rows grouped by
  year with 32px year labels, aside Series in Security + Most read; click a filter tag → `?tag=`
  narrows and the tag turns accent; `/category/journal/` is the stream; `/tag/<seeded tag>/` and
  `/?s=cache` render the 1e layout without a filter row.

**Phase 6**
- `/series/` vs badge 1f: header stats, featured series 5/7 with part list and dates, All series
  2-col grid with status squares; `/series/the-quiet-ledger/` renders the full part list and Other
  series; `/writing/` vs 2d: cover with shadow (the only shadow on the site), 64px title, three
  buttons, stat row; tiles 2-col with one cover tile in colour; In print grid without shadows;
  `/category/writing/` shows the same layout.

**Phase 7**
- `curl -I http://localhost:8888/` shows `Cache-Control: public, max-age=N` ending at the next
  local midnight or 06:00; `wp ttm verse fetch --force` logs a purge (`debug.log` or Cloudflare
  when constants are set); the front-page poster shows the mailto button (P7-04 recorded Outcome
  B: `jetpack/subscriptions` never registers unconnected); switch Settings → These Things Matter →
  Newsletter to `mailto` and `none` and confirm F26 (poster stays, statement only for `none`).

**Phase 8**
- `NOT VERIFIED (human) — CI green on the branch including the e2e job (Playwright report
  artifact); run docs/MIGRATION.md §1–§2 against a real archive or WXR export in wp-env: audit
  CSV, migrate:politics, primary:assign, series:assign for boundless/cryptopals, recount,
  convert:export → convert-classic → convert:import on the oldest posts, close-comments; open the
  front page, /category/technology/ and a journal post after each step.` (This is also §5 below,
  spelled out step by step.)

## 3. Spike outcomes

- **P8-01 (classic → block conversion under jsdom):** Outcome A — shipped. `rawHandler({HTML})` +
  `serialize()` from `@wordpress/blocks`, loaded via `require()` (the CJS build; the ESM build
  fails on unassisted `.json` imports under Node's stricter ESM loader), converts all 3 fixture
  posts with zero `core/freeform`/`core/html` blocks and full text preservation. Legacy
  `modern-footnotes` markup is pre-transformed into core footnotes first. Full write-up and result
  table: `docs/spikes/P8-01.md`.
- **P8-05 (Jetpack Social share URLs → `ttm_syndication`):** of the three meta keys named in SPEC
  Q-M4, only `_publicize_done_external` can carry a URL (`{service: {connection_id: url}}`, per
  Jetpack's own source — no live export was available to verify against a real fixture);
  `_wpas_done_{service}` and `jetpack_social_post_already_shared` are plain booleans and are
  documented as examined-but-inert. Full write-up: `docs/spikes/P8-05.md`.

## 4. Tuning measurements (all `⚠️ ASSUMPTION` config keys)

| Key | Default | Kept/changed | Evidence (task) |
|---|---|---|---|
| `series.max_purchase_links` | 6 | kept | max observed across the seed is 2 (the-quiet-ledger); cap bounds the admin repeater UI, not content volume (P1-14) |
| `series.hub_featured_parts` | 12 | kept | no real 1280px prototype/live-site access; seeded max (the-quiet-ledger, 13 parts) already exceeds it by one row, exercising the overflow link (P6-02) |
| `cache.verse_boundary_hour` | 6 | kept | computed ceiling across a full day (America/Los_Angeles) is 64800s at exactly 06:00, well under the 86400s cap; cap never actually binds under this schedule (P7-05) |
| `cache.max_age_cap_seconds` | 86400 | kept | see above; `newsletter.token_ttl` (86400) ≥ this value holds exactly, with zero slack (P7-05/P7-06) |
| `cache.min_age_seconds` | 60 | kept | verified adequate alongside the above (P7-05) |
| `newsletter.token_ttl` | 86400 | kept | worst-case token age (generated 1s before a boundary, cached the full `max_age_cap_seconds`) lands exactly at the handler's "previous window" acceptance boundary with zero slack — confirms it must be ≥ `cache.max_age_cap_seconds` (P7-06) |
| `newsletter.rate_limit_per_ip` | 5 | kept | 20 submissions from one IP in 600s yield exactly 5 forwards, rest silently dropped; reasoning: generous for legitimate household bursts, still an effective bot deterrent for a low-traffic weekly form (P7-06) |
| `newsletter.rate_limit_window` | 600 | kept | see above (P7-06) |
| `cssBudgetBytes` (`scripts/check-budget.mjs`, not a `Config.php` key) | 25600 | **changed twice** | 25600 → 28000 (P5-04, archive/search templates); 28000 → 33000 (P6-05, hub/writing templates). Current usage after P8-07's contrast fixes: 32990/33000 bytes — very little headroom left for any future CSS addition. |

**Jetpack outcome (P7-04):** the optimistic assumption in SPEC §6.9/§8 (Jetpack's Subscriptions
block renders unconnected, "enough to style it") does **not** hold. Live-verified in wp-env:
`wp jetpack module activate subscriptions` fails ("Newsletter could not be activated") without a
WordPress.com connection, so `jetpack/subscriptions` never registers at all. `Seeder::seed_jetpack()`
live-checks block registration (never hardcodes the result) and falls back the seed's newsletter
provider to `mailto` when the block is unregistered, exactly as SPEC's own fallback clause
anticipated.

## 5. Rule-24 warning list (bare numeric literals outside `Config.php`, P7-07)

`scripts/forbidden-patterns.sh` runs this check as a warning, not a failure. Reviewed and left
as-is:

- Three REST 404 `'status' => 404` literals (`Rest/LeadController.php`, `Rest/SeriesController.php`,
  `Rest/VerseController.php`) — an HTTP status code, not a tunable.
- Two `'timeout' => 10` literals (`Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php`),
  each with a `phpcs:ignore` comment explaining why: both run off the request/response cycle
  (a purge/subscribe forward), not on a page load, so a slightly slow timeout doesn't cost a
  visitor anything.
- One real future-`Config`-key candidate left unfixed per "no behavioural changes" scope:
  `plugins/ttm-core/blocks/writing-cell/render.php`'s `'posts_per_page' => 3`. Worth promoting to
  a `Config` key if the "also running" row count ever needs to be tunable.

## 6. What the owner runs next (MIGRATION.md §1–§2, against a real archive)

This is the literal task text, expanded to the exact commands from `docs/MIGRATION.md`:

1. **Pull a real archive from `hive`** (`MIGRATION.md` §1.1) or take a WXR export (§1.3 fallback).
2. **Restore it into wp-env** (§1.2): `wp db import`, `wp search-replace` to `localhost:8888`,
   restore uploads, install `jetpack`/`modern-footnotes`, activate `ttm-core`/`ttm-theme`.
3. Run the plan in this exact order, opening the front page, `/category/technology/`, and a
   journal post after **each** step to catch anything visually broken early:
   ```bash
   wp ttm audit --format=csv > audit.csv          # step 2.1 - the cleanup worklist
   wp ttm migrate:politics --dry-run               # step 2.2
   wp ttm migrate:politics
   wp ttm migrate:redirects --format=nginx          # add these rules to the web server/Cloudflare
   wp ttm primary:assign --dry-run                  # step 2.3
   wp ttm primary:assign
   wp ttm series:assign boundless --from-tag=boundless-summer-challenge --form=nonfiction   # step 2.4
   wp ttm series:assign cryptopals --from-tag=cryptopals --form=nonfiction
   wp ttm series:rebuild
   wp ttm recount --all                             # step 2.5
   wp ttm convert:export --all-classic --out=/tmp/classic.ndjson   # step 2.6, on the oldest posts first
   node scripts/convert-classic.mjs /tmp/classic.ndjson /tmp/blocks.ndjson
   wp ttm convert:import /tmp/blocks.ndjson --dry-run
   wp ttm convert:import /tmp/blocks.ndjson
   wp ttm migrate:syndication --dry-run             # step 2.7, best effort
   wp ttm migrate:syndication
   wp ttm migrate:close-comments --dry-run          # step 2.8
   wp ttm migrate:close-comments
   ```
4. Repeat from §1.2 (`npm run env:destroy`, start again) until this runs clean without manual
   intervention — that repeatability is the actual rehearsal goal, not a one-time pass.
5. Only once the rehearsal is boring: `docs/MIGRATION.md` §3 covers the real (private, k3s) build
   and cut-over; §5 is the ongoing content-cleanup worklist derived from `audit.csv`.

`docs/MIGRATION.md` also documents rollback (§4) if any of this needs to be undone after cut-over.

## 7. CI

`.github/workflows/ci.yml`'s e2e job now actually runs (it was gated on
`tests/e2e/playwright.config.mjs` existing, which is true as of P8-07): confirm it's green on this
branch, including the `playwright-report` artifact, before treating the branch as done. This
wasn't run by this agent — GitHub Actions CI is outside the sandboxed local-only verification loop
this run had access to; local `npm run test:e2e` and `npm run test:integration` were run directly
and are both green (48/48 and 337/337 respectively), which is the same suite CI runs.

## Round 1

Branch: `build/2026-09-21` (base `8379c6f`, head `b1dba7b`). 15 R1-* review-fix tasks, all
`[x]` done, 0 blocked, 0 skipped. Overall PROGRESS.md: 91/91 tasks done, 0 open. Draft PR into
`poc` stays a draft; commits stay unsigned (owner rebase-signs later, per the flight controller's
operator note for this run).

**Task list:** R1-01 module-boundary fixes + a `BoundariesTest` that encodes the §4.2 row order;
R1-02 Config keys for every remaining hard-coded tunable, `forbidden-patterns.sh` rule 24 now
fails instead of warning; R1-03 `SeriesIndex.last_update` from the newest published part,
hook-driven `SeriesIndexTest`, `series:assign --form` derives `ttm_form`; R1-04 verse F6
stored-verse-first fallback, Sept month format, site-local date in `Fetcher::parse_item`,
DST-safe `Verse\Cron`; R1-05 F9 stale-year cells (server-side dek omission via
`themes/ttm-theme/inc/patterns.php`, not CSS), `journal.rail_count`, writing-cell F1/F2 fixes;
R1-06 `migrate:politics --to=child` files posts under Opinion, `migrate:close-comments` purges
once per batch; R1-07 `wp ttm audit`'s missing-alt regex and broken-internal-link false
positives; R1-08 `Cache-Control` on HEAD requests; R1-09 honeypot CSS, de-duplicated nested
`<header>`/`<footer>` landmarks, `serial-hero` h1 on `/category/writing/`, CSS budget
reconciled; R1-10 `?form=fiction` REST filter; R1-11 Nav current-section on `/series/` and
series archives; R1-12 `ttm/syndicated-to` div wrapper + `wp_kses`'d sentence; R1-13 rule-26
empty-value unit tests, `PluginAloneTest` non-empty fixtures, removed a permanently-skipped
test; R1-14 i18n for masthead labels/Books/book-grid/feed title; R1-15 docs alignment
(`CLAUDE.md`, `DEPLOYMENT.md`, `convert-classic.mjs`'s report shape).

**Blocked/skipped:** none. Every task completed; no `foundry_task_block` calls this round.

**Interpretation choices, by task:**
- **R1-01** — `Query\Archive::resolve_label()`/`label_next`/`label_previous` stay on
  `Query\Archive` (duplicating the tiny pagination-label string format inline) rather than
  moving to `Bindings\Sources` as suggested, because `tests/integration/Query/ArchiveTest.php`
  calls `Archive::resolve_label()` directly and wasn't on the task's file list. The
  `ttm/archive-by-year` scope counter moved into `Blocks\Helpers` (now a registered `Plugin`
  module) rather than `Query\Archive`, because `ArchiveByYearTest` asserts
  `Helpers::$archive_scope` as a public property directly. `Meta\SeriesPosition` reads the
  `ttm_series_index` option directly (Meta/ may not import Query/). `Editor\SeriesPartList`
  (new) owns the series part list/Parts column via its own hooks (Taxonomy/ may not import
  Editor/).
- **R1-02** — rule 24's allow-list covers HTTP status codes plus `Query/Cells.php` and
  `blocks/writing-cell/render.php` (both explicitly out of scope for this task; a separate
  queued R-task owns them — none was queued this round, so they remain warnings-turned-silent
  by the allow-list, not failures).
- **R1-03** — `SeriesCommand::run()`'s `if ($form) update_term_meta(...)` block moved before
  the assignment loop so `Meta\Form::on_save()` (called per assigned post) reads the
  already-set `ttm_form` and derives `chapter` immediately.
- **R1-04** — F6's fallback condition changed from "date !== today → history" to "date > today
  → history" (a stored verse dated today-or-earlier is used directly, not skipped in favor of
  history). `Verse\Cron` no longer uses `wp_schedule_event(..., 'daily', ...)` (a fixed
  86400s-per-cycle recurrence that drifts across DST); it self-reschedules a single event via
  `next_run()` at the end of every `run_fetch()`.
- **R1-05** — F9's dek suppression is server-side (the pattern omits the `post-excerpt` block
  entirely for a stale section, via `Cells::is_stale_year()` called from
  `inc/patterns.php`), not CSS-based, since CSS-hiding would still leak real excerpt text into
  cached HTML and the CSS budget had almost no headroom. `cells.thin_days` removed from
  `Config` as dead (F9's "show what exists" needs no code — the cell query already has no date
  filter).
- **R1-06** — `close_comments()` switched from `wp_update_post()` to `$wpdb->update()` +
  `clean_post_cache()`, because `wp_update_post()` fires `transition_post_status` per post and
  `Cache\Purge::on_transition()` purges on every one of those.
- **R1-09** — making `/category/writing/` get an h1 exposed a second, pre-existing,
  **untested** bug outside this task's file list: `themes/ttm-theme/patterns/masthead-inner.php`'s
  `wp:site-title` had no explicit `level`, defaulting to core's h1 — meaning `page.html`,
  `404.html`, `search.html`, `page-series.html` and `article-header.php` (each already
  declaring their own explicit level-1 heading) already rendered two h1s on every request.
  Fixed at the root: `masthead-inner`'s site-title is now level 2.
- **R1-14** — the masthead-nav-uses-category-names test executes
  `patterns/masthead-front.php` directly (`require` + `ob_start`) rather than through the
  `wp:pattern` block reference `header-front.html` uses, because WordPress caches theme
  `patterns/*.php`'s scanned output in a transient keyed by file mtimes; a category rename
  inside the test (even after re-firing `init`) doesn't show through that registry.
- **R1-15** — `scripts/lib/report.mjs` (new, not on this task's file list) was needed because
  Jest's dynamic `import()` of `convert-classic.mjs` itself fails under this environment,
  independent of and in addition to the pre-existing "spike Outcome B" `@wordpress/block-library`
  failure; a zero-import lib file (mirroring `lib/footnotes.mjs`, which already imports fine)
  was the only way to make the "runs without block-library" acceptance test actually execute.

**`⚠️ ASSUMPTION` config keys touched this round:**
- `cssBudgetBytes` (`scripts/check-budget.mjs`, not a `Config.php` key) — **changed twice**:
  33000 → 32700 (R1-09, after adding `.ttm-hp` and de-duplicating grid/rule/button CSS: 32990 →
  32612 measured); 32700 → 33200 (R1-12, after adding `01 §4.13`'s missing `.ttm-syndication`
  rule: 32612 → 33070 measured). `CLAUDE.md`'s own constraint line was stale at `25600` since
  the P5-04/P6-05 bumps (never updated); corrected to `33200` across both bumps.
- No `Config.php`-key `⚠️ ASSUMPTION` values were tuned this round (R1-02/R1-05 added several
  new, non-assumption `Config` keys with values copied from the previous hard-coded behaviour:
  `cache.cloudflare.timeout_seconds`, `newsletter.timeout_seconds`, `sections.technology_slug`,
  `books.blank_rows`, `books.link_rows`, `seed.quiet_offset_days`, `journal.rail_count` (reused
  existing default), `writing.plain_count`).

**What a human must check by hand, per task (all `NOT VERIFIED (human)` — this run is
non-interactive and never opened a browser):**
- **R1-04/R1-08/R1-09** — `/writing/` and `/category/security/` were axe-checked (0
  serious/critical) via `npm run test:e2e` (48/48 passed), but a human should still eyeball the
  verse box's "Meditation for Sept N" wording and the Writing page's h1 (previously an h2 on
  `/category/writing/`) for visual regressions.
- **R1-05** — a stale-year section cell (newest post > `cells.stale_year_days` old) should be
  spot-checked once real content exists: confirm the dek genuinely doesn't render (not just
  hidden) and only 2 rows show.
- **R1-06** — run `wp ttm migrate:politics --to=child` and `wp ttm migrate:close-comments`
  against a real archive (per `MIGRATION.md`) and confirm the Cloudflare/Batcache purge fires
  once, not once per post, by watching `debug.log` or the Cloudflare purge log.
- **R1-12** — open a single Journal post with syndication URLs and confirm the div-wrapped
  syndication line's new CSS (flex layout, 1px rule above) matches `01 §4.13`'s intent — this
  component had no CSS at all before this round, so it has never been visually reviewed.
- **R1-14** — rename a live category in wp-admin and confirm the masthead nav picks it up
  immediately (no cache to bust, unlike the pattern-registry caching the tests had to work
  around).

**For a reviewer who hasn't seen this code:** every R1-* task's acceptance tests pass, plus
`composer lint`, `composer test:unit` (127), `npm run lint` (CSS budget: 33070/33200 bytes),
`npm run test:unit` (JS, 12 passed/2 pre-existing skipped), `npm run build`,
`bash scripts/forbidden-patterns.sh`, `npm run test:integration` (367 tests, up from 337 at the
end of Phase 8), and `npm run test:e2e` (48/48, run manually once after R1-09 since it's not in
the standard verify set). Three deviations from the queued task files are worth a second look on
review, all documented above with the reasoning: `Blocks\Helpers` becoming a `Plugin` module
(R1-01), `themes/ttm-theme/patterns/masthead-inner.php`'s site-title level fix (R1-09), and the
new `scripts/lib/report.mjs` (R1-15).
