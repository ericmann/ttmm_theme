# These Things Matter — Specification, phase 4 (real content)

Version: 4.0
Status: ready for flight (refinement pass on branch `refine/*`, base `main`)

The Foundry planner reads this file in full and derives the build plan from it. Phase 1 (`docs/phase-1/`) built the plugin and theme, phase 2 (`docs/phase-2/`) made the front page match its mock, phase 3 (`docs/phase-3/`, merged as PR #12) made every inner template match its mock on seeded content. The owner then previewed the seeded site and reported four defects (§1.1), and dropped a WXR export of the live site into `docs/` so the local environment can carry **real content** (§1.2). **This flight fixes the four defects, brings the live export into wp-env through the plugin's own migration plan, and proves every template on the real 889 posts** — the last step before the private beta site is stood up (a later flight; §2 non-goals).

Precedence: this file wins on engineering; `docs/01-design-language.md`, `docs/02-screens.md`, `docs/06-fallbacks.md` and the mock cards in `docs/Eric Mann Newspaper.dc.html` win on visuals; owner feedback quoted in §1.1 wins over the design docs where they disagree (§6.1, §6.2). `docs/phase-1/`, `docs/phase-2/` and `docs/phase-3/` are history. Numbers marked `⚠️ ASSUMPTION` carry a config key and must never be hard-coded anywhere else.

---

## 1. Overview

### 1.1 Owner-reported defects (verified against the seeded site on 2026-09-22)

1. **Section archive filter row.** Mock `1e` line 933 has the row `Filter · All · <top 5 tags> · … · Newest first` between the archive header and the year list. `/category/security/` renders it, `/category/business/` (and every other section) shows only the 2px rules around an empty slot. Cause: `ttm/tag-filter` correctly takes fallback F16 (no tags → no row), and the seed gives tags only to Security posts. Second cause, latent: `Stats::top_tags()` caches an empty result for 12 hours and is invalidated only on publish transitions, never when terms are attached to an already-published post (as a WXR import does). The live site has 1 001 tags in use, so on real content the row must appear on every section.
2. **Footer.** The mock inner footer (`1e` line 973, `2b` line 411) is one 12px line: left `These Things Matter · © 2026 Eric Mann`, right `Technology · Business · Faith · Journal · Writing · Security · Opinion · Series`. Ours adds a second left paragraph with the Scripture copyright notice from the verse API (`ttm/verse-copyright` binding) and a ninth nav item `RSS`. Owner: no Scripture copyright statement anywhere (it lives on dailymedtoday.com), no RSS, categories + Series small enough for one line.
3. **"In this series" on posts that are not in a series.** `/transients-object-caches-and-fast-enough/` (Technology, no `series` term) renders `ttm/series-toc` for **The Quiet Ledger**, the in-progress fiction serial. Cause: `series-toc/render.php` falls back to `Serials::active()` for any context without a series; that fallback exists for the `chapters` variant on `/writing/` and must not apply to the `series` variant. Fallback F11 says the block returns `''`. The series bar, prev/next and the newsletter copy binding are correct.
4. **"Other series" ignores category.** `/series/hardening-wordpress/` (Technology · Security, nonfiction) lists the four most recently updated other series of any form, so a literary thriller closes a security series. The index already carries `categories` (primary categories of the parts) and `form` per series; the block does not use them for this list.

### 1.2 Real content

`docs/ericmann039sblog.WordPress.2026-09-23.xml` (34.8 MB, WXR 1.2, gitignored — **never committed, never copied into the repo, never quoted in fixtures**) is the live site. Profile taken 2026-09-22:

| Fact | Value |
|---|---|
| Posts | 889 published, 10 draft, 3 scheduled, 1 private; 2007-07-12 → 2026-09-22; 366 of them in 2014 (a daily-writing year) |
| Format | 171 block posts, **718 classic HTML**; 406 contain shortcodes: `[ref]` 299 (footnotes), `[cci]`/`[cc]` 105 (CodeColorer code), `[caption]` 41, `[mfn]` 23 (modern-footnotes), `[audio]` 8, `[seoslides]` 2; 11 contain `<script`, 3 `<iframe` |
| Images | 266 attachments (jpg 166, png 92, webp 8), all on `eric.mann.blog`; 162 published posts have a featured image, 727 do not; **113 posts embed images from other hosts** (eamann.com 34, ttmm.wpengine.com 25, ttmm.io 23, groundedchristianity.com 10, mindsharestrategy.com 5, i0/i1/i2.wp.com 8, misc) |
| Categories | technology 432, business 231, faith 100, journal 87, writing 70, security 36, politics 24, uncategorized 0; all top-level; 90 posts in > 1 category (business+technology 31, security+technology 27, journal+technology 10) |
| Tags | 1 037 defined, 1 001 used; top: wordpress 132, security 43, brand 42, php 40, marketing 38, cryptography 29, javascript 28, strategy 26, boundless 23, boundless-summer-challenge 21, blogging 20, fiction 20 |
| Series | none (no `series` taxonomy); candidates are the tags `boundless-summer-challenge`/`boundless` and `cryptopals` (9) |
| Meta worth using | `_yoast_wpseo_primary_category` on 424 posts; `_yoast_wpseo_metadesc` widely; `_wp_old_slug` 36; `footnotes` (core) 43; no `_publicize_done_external`; 7 posts are `post-format-aside` |
| Excerpts | 562 posts have one, 327 do not |
| Pages | `blog`, `writing`, `speaking` |
| Comments | none in the export |
| Other items | 51 Jetpack `feedback`, 5 `custom_css`, Powder/other-theme `wp_template`/`wp_global_styles`, 24 `nav_menu_item`, 1 `wp_navigation`, 2 `wp_block` |
| Permalinks | `/%postname%/`; one author `ericmann` |

The migration plan in `docs/MIGRATION.md §2` was written against these facts but has only ever run on synthetic fixtures. This flight runs it on the export, in wp-env, until it is boring, and turns every defect that real content exposes into a tested fix.

### 1.3 Where this leads

The owner's plan (recorded here so the planner does not wander): after this flight, a **private beta** (`beta.mann.blog`, working name) is stood up on the k3s cluster from `docs/DEPLOYMENT.md §10`, content is imported and cleaned there, backups and caching are verified, a friend or two review it, and the cut-over is a `wp search-replace beta.mann.blog → eric.mann.blog` plus a Cloudflare Tunnel hostname move. That deployment is the **next** flight. This flight delivers the parts of it that can be built and tested in this repository: a repeatable import, a migration plan that runs clean, a backup/restore drill, and the hostname-swap rehearsal (§6.6, §6.9).

"Done": the seeded screens pass every §6.11 row; `npm run env:live` imports the export and runs the whole plan without manual intervention; `npm run test:live` passes on the imported site (§6.10); `npm run env:drill` restores a backup into a wiped wp-env and the site is byte-identical on five screens; `docs/feedback/phase-4/` holds seeded and live screenshots plus `LIVE-TRIAGE.md`; nothing private is in git; phase 1–3 suites still pass.

## 2. Goals and non-goals

Goals:

- Goal: The verse attribution loses its date (§6.1.1).
- Goal: The four §1.1 defects are fixed, each with a fidelity row that would have caught it (§6.11) and a seed change that exercises it (§6.12).
- Goal: One command imports the live export into wp-env and runs the migration plan end to end (§6.6); one command returns the environment to the e2e seed. Both are idempotent from any state.
- Goal: The migration commands handle what the export actually contains: Yoast primary categories, `[ref]`/`[cci]`/`[cc]`/`[mfn]`/`[audio]` shortcodes, images on dead domains, missing excerpts (§6.7, §6.8).
- Goal: Every template renders every real post without PHP notices, empty wrappers, unconverted shortcodes or axe violations (§6.10); defects found on real content are fixed as classes with seed-state tests, and the residue is an owner cleanup list.
- Goal: A backup and a restore are scripts in the repo, and a drill proves them on the seed in CI (§6.9).
- Goal: `docs/MIGRATION.md` and `docs/DEPLOYMENT.md` describe the WXR path, the `beta.mann.blog` hostname and the three exposure options (§6.13).
- Goal: The phase 3 spec issues that change behaviour are folded in (§3.1) so planner and reviewer read one document.

Non-goals (become `**Out of scope:**` lines on tasks):

- Non-goal: Standing up `beta.mann.blog`, k3s manifests, Cloudflare Access, S3 backup CronJobs, Batcache/memcached in wp-env, connecting Jetpack. Next flight; §6.13 only documents them.
- Non-goal: Visual changes beyond §6.1–§6.5; no front-page change; no new screens; no dark mode, comments, analytics.
- Non-goal: Hand-cleaning content (writing excerpts, choosing featured images, deciding series membership). The flight produces the worklist (`audit.csv`, `LIVE-TRIAGE.md`); the owner does the cleaning on the beta site.
- Non-goal: Importing Jetpack `feedback` entries, `custom_css`, foreign-theme templates and global styles as anything but inert rows (§9 Q5).
- Non-goal: Changing the content model, REST, cache headers or verse fetching, except the `Stats` invalidation named in §6.2.

## 3. Engineering principles

Phase 1 rules 1–33, phase 2 rules 34–40 and phase 3 rules 41–46 remain in force with the amendments below; the planner copies the whole amended set into `CLAUDE.md` `## Constraints`.

### 3.1 Amendments (fold-in of `docs/phase-3/SUMMARY.md §Spec issues`)

- Rule 2: scope is `plugins/ttm-core/` (SI-20).
- Rule 34: allow-list lines carry `# P<n>-<nn> pending` and the list is empty at flight end; `check-fixme.mjs` is held to the same standard; identity-only block wrappers are listed in `UNSTYLED_WRAPPERS` in `scripts/check-css-coverage.mjs` with a comment naming the block (SI-1, SI-26 ratified).
- Rule 41: registered `is-style-*` block styles and `# state:` reasons are accepted allow-list categories (SI-17, SI-22 ratified).
- Rule 44: the slug stays `article-h2` → `--article-h-2`; the checker computes the generated name (SI-2 closed).
- §6.1.0 (phase 3): the shipped container (root padding kept, `.has-global-padding` zeroed inside `.wp-site-blocks`) is the contract (SI-21).
- §6.1.1 (phase 3): singles mark only the primary category current, never `/series/` (SI-3). Site title and "by Eric Mann" are sibling elements (SI-14). The overlay close button reads "Close" (SI-15). Phone series bar keeps "Part 3 of 6" (SI-13).
- Term order is the `get_the_terms` filter in `Meta/PrimaryCategory`; featured-image captions, byline tags, search classes and whole-row links are render filters in `Blocks\Helpers` (SI-4, SI-5, SI-9). Empty `ttm/*`-bound paragraphs and headings render nothing (SI-6).
- `ttm/series-list` layouts are `list|rail|grid-2|grid-3|strip`; the chapters heading is composed when `heading` is empty; book form `collection` reads "Stories" (SI-10, SI-11, SI-12).
- Part numbers: the series TOC pads to two digits ("01", §6.2 of phase 3); hub part list, chapters list, Most read and the 404 Latest list are unpadded (SI-24 decided).
- `wr-serial-form` and `wr-stat-cadence`: stored cadence is lowercase; the hero stat capitalises it (SI-18 ratified). `hub-grid-cats` and `wr-serial-form` regexes are tightened to the seed's literal text (SI-23). The `ValuesTest` impossible archive-kind case is deleted (SI-25).
- §6.9 coverage (SI-27): every fidelity row added this flight asserts a **position or count**, not only a computed style, wherever the defect was one of placement or presence.
- Rule 24: `Stats::top_tags()` gets a deterministic tiebreak (count desc, then slug asc); `Config` keys unchanged.

### 3.2 New rules

47. **Private data never enters the repository.** The WXR export, database dumps, uploads and audit output live under `docs/fixtures/live/` (gitignored) or as `docs/*.xml` (gitignored, already in `.gitignore`). `scripts/forbidden-patterns.sh` fails if `git ls-files` lists any `*.xml`, `*.sql`, `*.sql.gz`, `*.tar.gz` or `*.csv` under `docs/`, or anything under `docs/fixtures/live/`. Test fixtures that imitate live content are synthetic: markup shapes, never a live post's body text. Titles and slugs of the owner's public posts may appear in `LIVE-TRIAGE.md` and screenshots.
48. **Live content is a state, not a fixture.** `npm run env:live` (import + plan) and `npm run env:seed -- --reset` are each idempotent from any prior state, including each other's. `npm run test:e2e` always starts from the seed. `npm run test:live` runs only when `docs/fixtures/live/screens.json` exists and reports "skipped: no live import" otherwise, exit 0; CI therefore never fails for lack of the export.
49. **Migration commands are idempotent, dry-run first and non-destructive.** Every `wp ttm migrate:*`, `primary:*`, `series:*`, `convert:*` command supports `--dry-run`, reports counts, may add terms and meta, and rewrites `post_content` only in `convert:import` and `migrate:images`, each of which writes `ttm_classic_backup` once (first writer wins) so `convert:revert` restores the pre-migration HTML. No command deletes posts, terms, attachments or comments.
50. **A block's data comes from its context, never from a site-wide default.** A block reads the current post, the queried term or an explicit attribute. When none yields data it returns `''`. The one sanctioned default is the `chapters` variant of `ttm/series-toc` (and `ttm/serial-hero`, `ttm/story-tiles`, `ttm/book-grid`) on the Writing page, which read `Serials::active()`. Test: every block's integration test has a "no context, other content exists" case that asserts `''`.
51. **Related lists are same-form, same-section first.** Any "other/related" list of series ranks candidates by shared section count, then last update, never crosses form (nonfiction ↔ fiction), and is omitted when no candidate exists (new fallback F27).
52. **Real content is a test surface.** Every defect found by `npm run test:live` is fixed for the class of content that produced it, with a synthetic seed-state or integration test reproducing the shape, and logged in `docs/feedback/phase-4/LIVE-TRIAGE.md` (finding · class · fix commit · test). Findings that are content problems (no excerpt, dead image, wrong category) are counted, not fixed.

## 4. Architecture

Unchanged from `docs/phase-3/SPEC.md §4` plus:

- `Cli/MigrateCommand.php` gains `migrate:images` and `migrate:excerpts`; `Cli/PrimaryCommand.php` gains `--from-yoast`; `Cli/SeriesCommand.php` gains `--from-tags=a,b` (union) and `--form`, `--status`, `--total`. `Cli/AuditCommand.php` gains flags `remote-image`, `shortcode`, `post-format-aside`. All in `TTM\Core\Cli`, importing `Meta`, `Taxonomy`, `Query` only (the §4 arrow points down).
- `Query/Stats.php` invalidates `ttm_top_tags_*` on `set_object_terms` for `post_tag` and `category` on posts, and exposes `Stats::flush_all()` used by the seeder and the import script (`wp ttm stats:flush`).
- `Blocks/series-list` gains attribute `relatedTo: "current"` (§6.4). `Blocks/series-toc` loses the `series`-variant fallback (§6.3). `Templates/Hierarchy` routes `/writing/` and `/category/writing/` to the section-archive layout when no fiction series term exists (§6.5).
- `scripts/live/` (new): `import.sh`, `plan.sh`, `backup.sh`, `restore.sh`, `screens.mjs`, `drill.sh`; wired to npm scripts `env:live`, `env:backup`, `env:restore`, `env:drill`, `test:live`. Shell scripts pass `shellcheck` if present (not required in CI).
- `scripts/convert-classic.mjs` gains a shortcode pre-pass module `scripts/lib/shortcodes.mjs` (pure functions, Jest-tested) run before `rawHandler`.
- `tests/e2e/live.spec.mjs` (Playwright project `live`, base URL as e2e, reads `docs/fixtures/live/screens.json`).
- `media_sideload_image()` is permitted in `Cli/MigrateCommand.php` only, with hosts from `--hosts` defaulting to `Config::get('migration.image_hosts')`; targets never come from request input (CLI only). `wp_safe_remote_*` remains confined to its three files.

## 5. Data and configuration

No new options. Post meta reused: `ttm_classic_backup` (first writer wins, rule 49), `ttm_converted_at`. New post meta `ttm_images_rewritten` (int, count of `src` rewrites, schema-validated, not `show_in_rest`). Config keys (new unless marked existing):

```text
archive.tag_filter_limit   = 5        (existing)
stats.tags_cache_seconds   = 43200    (existing)
series.related_limit       = 4        (existing; §6.4)
cli.series_tag_min         = 3        (existing)
excerpt_length             = 55       (existing ⚠️ ASSUMPTION; migrate:excerpts truncates Yoast descriptions to it)
migration.image_hosts      = [eamann.com, www.eamann.com, ttmm.io, www.ttmm.io, ttmm.wpengine.com, i0.wp.com, i1.wp.com, i2.wp.com]
migration.image_timeout    = 20       ⚠️ ASSUMPTION (seconds per fetch)
migration.photon_origin    = eric.mann.blog   (Photon `iN.wp.com/<host>/...` URLs whose <host> matches are rewritten to the origin URL instead of sideloaded)
```

`cssBudgetBytes` stays 63488 (`scripts/check-budget.mjs`); the footer removal frees bytes and §6.1–§6.4 add few. The planner's last-phase tuning task records the final size.

## 6. Interfaces

Measurements are the mocks' inline values; colour names are `theme.json` presets. Unless stated, phase 3 §6 stays the contract for every screen.

### 6.1 Footer (`parts/footer.html`, `.ttm-footer`, 01 §4.34 amended)

- One flex row, space-between, 12px neutral-700, 2px rule above (inner) / no rule with 16px padding after the poster (front). Left: **one** paragraph bound to `ttm/today format=footer` → "These Things Matter · © {year} Eric Mann · Built on WordPress" (S4 keeps one string; owner did not object). The `p.ttm-footer__copyright` paragraph, the `ttm/verse-copyright` binding source, `Config` key `verse.copyright_placement` and the `.ttm-footer__copyright` rule are **removed**; `Verse/Fetcher` keeps storing `copyright` (harmless, may be shown in admin) and the front-page verse block's own attribution line is unchanged. Right: `core/navigation.ttm-footer__nav` with **eight** items — the seven sections then Series — separated by " · ", 400 weight, neutral-700; **no RSS on any page** (feeds stay discoverable via `<link rel="alternate">`). At 1280 the nav list is one line (`ul` height ≤ 20px) and the whole footer ≤ 48px tall. Phone: column, 11px/1.6.
- Bindings rule 26: removing a source removes its normal/empty tests; `ConfigFallbacksTest` drops the key.

#### 6.1.1 Verse attribution (owner request, 2026-09-23)

The verse block's attribution line (`ttm/verse-of-the-day`, front-page rail) reads **"Meditation from dailymedtoday.com"**, linking to the item URL (or the site root), with no date. The date belongs to the cache boundary, not the copy; F6 (stale verse shown with *its* date) is amended the same way: the fallback shows the last good verse with the same undated attribution. `03-content-model.md §6` and `06-fallbacks.md F6` are updated; the front-page fidelity row for the attribution asserts the new text; the string stays translatable.

### 6.2 Section archive filter row (`ttm/tag-filter`, `.ttm-filter-row`)

- Contract as phase 3 §6.6 (mock `1e` line 933): flex gap 6, padding 12 0, 2px rule above, 1px below, 12px; "Filter" neutral-700 margin-right 8; `a.tag.tag-accent` "All" (active without `?tag=`), top `archive.tag_filter_limit` tags as `a.tag.tag-neutral` linking `?tag=`; right "Newest first" `margin-left: auto`.
- F16 stays for a category with **zero** tagged posts (row omitted, 2px rule kept), and is the only reason the row may be absent on a section archive. Because the live site tags every section, the **seed** gives every section ≥ 5 distinct tags (§6.12) and the fidelity table asserts the row on `/category/business/` as well as Security.
- `Stats::top_tags()`: transient invalidated on `set_object_terms` (post_tag or category on a post), on `wp ttm seed`, and by `wp ttm stats:flush` (called from `scripts/live/plan.sh`); tiebreak count desc, slug asc; an empty result is cached for `stats.cache_seconds` (3600), not 12 hours. Integration tests: attaching a tag to a published post refreshes the row on the next render; import-order (terms after status) produces the row.

### 6.3 Series TOC and F11 (`ttm/series-toc`)

- `variant=series` (default; `single.html`): source is `seriesId` attribute, else `SeriesIndex::for_post(postId)`, else `''`. No `Serials::active()` fallback. `variant=chapters` (`/writing/`): `seriesId`, else `Serials::active()`, else `''` (unchanged).
- On a post with no series term the article aside therefore leads with "More in {Category}" then the newsletter box ("The weekly issue."), the series bar is absent, and `ttm/series-prev-next mode=auto` renders the chronological pair within the primary category with labels "← Previously in {Category}" / "Next →" (title 18px/800), keeping an empty cell and its rules when a side has no neighbour (phase 3 §6.2). Verified on the seed: the transients post renders only the "Next →" cell because it is the oldest seeded Technology post; the seed gains an older Technology post (§6.12) so both cells are exercised.
- Tests: `SeriesTocTest` gains "post without series while an in-progress serial exists → `''`"; `SeriesPrevNextTest` gains the chronological case with titles asserted; fidelity rows `toc-absent`, `bar-absent`, `prevnext-auto-label`, `aside-noseries-order`, `box-noseries` on a new seeded screen `articleNoSeries = /transients-object-caches-and-fast-enough/` (added to `tests/e2e/lib/urls.mjs` and the selectors/a11y screen set).

### 6.4 Other series (`taxonomy-series.html`, `ttm/series-list relatedTo=current`)

- New attribute `relatedTo` (`"" | "current"`). With `current` on a `series` term page: current = `SeriesIndex::get(queried term)`; candidates = all other series with the **same form class** (nonfiction, or fiction = any non-`nonfiction` form); rank by `count(array_intersect(categories, current.categories))` desc, then `last_update` desc; take `limit` (default `series.related_limit` = 4). Zero candidates → block returns `''`. So that no orphan heading remains, the block renders the "Other series" heading itself when its `heading` attribute is set (as `ttm/series-toc` does) and the template's separate heading group is removed; no `:has()` (phase 3 decision S6). New fallback **F27** in `docs/06-fallbacks.md`: "Single series · no other series of the same form → section omitted."
- Any-series pages: `/series/hardening-wordpress/` (Technology · Security) lists Reading CVEs (Security, complete, new in the seed §6.12) first, then the other nonfiction series by last update; never a serial. `/series/the-quiet-ledger/` lists Failover and Salt Water Wires only.
- Hub "All series" grid and the Writing lists are unchanged (they intentionally mix forms / are fiction-only).

### 6.5 Writing page on real content (new F28)

The live site's `/writing/` page is a placeholder landing for the owner's other works and **goes away**: the theme's starter `Writing` page (template `page-writing.html`) owns the slug. The import keeps the live page's content non-destructively as a draft with slug `writing-legacy` (import script, §6.6 step 1–2 ordering: starter content first, importer's slug collision renamed by `wp post update`). The live site has zero fiction series terms, and its 70 Writing posts are essays *about* writing, not stories, so two rules apply until the owner files fiction:

- `Meta/Form::derive()` marks a Writing post without a series as `story` only on an **editor save** (`rest_after_insert_post`/`save_post` from the editor); the WXR importer, `wp post` and every `wp ttm` command leave `ttm_form` unset. The audit flags such posts `writing-no-form`. This keeps 70 essays out of "Short fiction".
- With zero fiction series terms **and** zero `ttm_form=story` posts, `/writing/` and `/category/writing/` render the section-archive layout of `1e` for category Writing (kicker "Section", H1 "Writing", description, stats, filter row, year list, aside) instead of the serial hero with empty zones (F28). `Templates/Hierarchy` decides from the series index and a cached story count (`Stats`), not a per-request query. Once one serial or story exists the fiction layout returns with its existing fallbacks (F3, F20, F21). The front-page Writing cell keeps F2. Integration tests with `render_template()` in both states; the seed stays in the fiction state, so this is not a fidelity row.

### 6.6 Live import (`npm run env:live`, `scripts/live/import.sh` + `plan.sh`)

Inputs: `LIVE_WXR` (default: the newest `docs/*.xml`, else newest `docs/fixtures/live/*.xml`), `LIVE_SKIP_ATTACHMENTS=1` (fast path), `LIVE_HOST` (default `http://localhost:8888`). Steps, each printed with a count and wall time, the whole script non-interactive:

1. `wp-env start`; `wp site empty --uploads --yes` (wipes posts, terms, comments, uploads; leaves options and users); `wp ttm stats:flush`; re-run the theme's starter content (`wp ttm seed --starter-only`, new flag: categories, Series/Writing/Newsletter/About pages, Sections navigation, nothing else). After the import, any imported page whose slug collides with a starter page (`writing`) is renamed `<slug>-legacy` and set to draft (§6.5).
2. `wp plugin install wordpress-importer --activate` if missing; `wp import <wxr> --authors=create [--skip=attachment]`. Attachments are fetched from `https://eric.mann.blog` (266 files); with the skip flag `_thumbnail_id` dangles and featured images fall back (F8/F12), which is acceptable for a fast run.
3. `wp search-replace 'https://eric.mann.blog' "$LIVE_HOST" --all-tables --precise --skip-columns=guid` (the hostname-swap rehearsal; the same line with `beta.mann.blog` → `eric.mann.blog` is the cut-over).
4. `plan.sh`: `wp ttm migrate:politics` · `wp ttm primary:assign --from-yoast` · `wp ttm primary:assign` · `wp ttm series:assign` for each entry of `docs/migration/series.json` (§9 Q1; committed, owner-editable) · `wp ttm recount --all` · `wp ttm migrate:excerpts --from=yoast` · `wp ttm migrate:images` · `wp ttm convert:export --all-classic` → `node scripts/convert-classic.mjs` → `wp ttm convert:import --allow-freeform` · `wp ttm migrate:close-comments` · `wp ttm series:rebuild` · `wp ttm stats:flush` · `wp rewrite flush` · `wp ttm verse fetch || true` (network; failure tolerated) · `wp ttm audit --format=csv > docs/fixtures/live/audit.csv`.
5. `node scripts/live/screens.mjs` writes `docs/fixtures/live/screens.json` (§6.10) and prints the audit flag counts as a markdown table for `LIVE-TRIAGE.md`.

Each plan step runs `--dry-run` first and aborts on a non-zero exit; the whole script is re-runnable (rule 48). `npm run env:seed -- --reset` after a live import must return the site to the seed: `Seeder::reset()` therefore gains `wp site empty`-equivalent behaviour behind `--reset` when non-seed posts exist (it deletes only when `WP_ENVIRONMENT_TYPE !== 'production'`, which the seeder already enforces).

### 6.7 Migration command changes (`plugins/ttm-core/src/Cli/`)

- `primary:assign --from-yoast [--dry-run]`: for posts without `ttm_primary_category`, if `_yoast_wpseo_primary_category` names a category the post has, use it; report used/skipped counts. Plain `primary:assign` then fills the rest by nav order (existing).
- `series:assign <slug> --from-tags=a,b [--from-tag=a] [--form=] [--status=] [--total=] [--name=] [--dry-run]`: union of tags; explicit meta overrides the inferred status; existing single-series rule kept.
- `migrate:excerpts --from=yoast [--dry-run]`: posts with an empty excerpt and a non-empty `_yoast_wpseo_metadesc` get it as `post_excerpt`, truncated at a sentence boundary within `excerpt_length` words; never overwrites; Journal posts excluded (their excerpt is derived). Reports counts.
- `migrate:images [--hosts=…] [--post=<id>] [--dry-run]`: for each published post whose content has `<img src>` on a listed host: Photon URLs for `migration.photon_origin` are rewritten to the origin URL; other hosts are fetched via `media_sideload_image()` (timeout `migration.image_timeout`), attached to the post, and `src` (and a wrapping `<a href>` to the same file) rewritten to the new attachment URL; on fetch failure the `src` is left as is and the post is flagged `remote-image` in the audit. `ttm_classic_backup` written first (rule 49), `ttm_images_rewritten` set. Never runs on request; CLI only.
- `audit` flags added: `remote-image` (any `<img>` off-origin after migration), `shortcode` (any remaining `[name` from a known list incl. `seoslides`), `post-format-aside`, `no-tags`, `writing-no-form` (§6.5). `--only=` accepts them. `audit --summary` prints flag counts.
- `stats:flush`: deletes every `ttm_top_tags_*` and `ttm_stats_*` transient.
- `seed --starter-only`: runs the theme's starter content only (idempotent by slug).
- Tests: `tests/integration/Cli/*Test.php` cover each flag with synthetic posts (rule 47), including a fake image host served from the tests container (`pre_http_request` filter returning a PNG body) so `migrate:images` is tested without the network.

### 6.8 Classic conversion (`scripts/convert-classic.mjs`, `scripts/lib/shortcodes.mjs`)

Pre-pass, in order, before `rawHandler`; every rule is a pure function with a Jest test on a synthetic fixture:

| Shortcode | Posts | Conversion |
|---|---|---|
| `[ref]…[/ref]` | 299 | Core footnotes: a `<sup data-fn="<uuid>" class="fn"><a href="#<uuid>" id="<uuid>-link">n</a></sup>` marker in place, the note text appended to the post's `footnotes` meta JSON via the import (`convert:import` accepts a `footnotes` array per post and writes the meta); numbering by order of appearance |
| `[mfn]…[/mfn]` | 23 | Same as `[ref]` (existing modern-footnotes path unified) |
| `[cci lang="x"]…[/cci]`, `[cc lang="x" …]…[/cc]`, `[cc_x]` | 105 | `<pre class="wp-block-code"><code lang="x">` (entities preserved, no highlighting); `rawHandler` maps it to `core/code` |
| `[caption]<img>…[/caption]` | 41 | Left to `rawHandler` (core handles `[caption]` as `core/image` with caption); asserted |
| `[audio src=…]` | 8 | `<audio controls src>` → `core/audio` |
| `[seoslides …]`, unknown `[name …]` | 2 + | Left in place; `convert:import --dry-run` lists them; `audit --only=shortcode` shows them after import |
| Bracketed prose (`[architect]`, `[i]`, `[my]`…) | ~20 | Not shortcodes; the pre-pass matches only the known names above and `[caption]`, never a generic `\[[a-z_]+` pattern |

`<script>` (11) and `<iframe>` (3) fragments become `core/html` (freeform) and are listed by `convert:import --dry-run`; `--allow-freeform` is what `plan.sh` passes. Verification after import: rendered text of every converted post equals the classic render with shortcodes stripped (existing check), extended so a `[ref]` post's footnote texts appear in the rendered page exactly once each.

### 6.9 Backup and restore drill (`scripts/live/backup.sh`, `restore.sh`, `drill.sh`)

- `backup.sh [dir]`: `wp db export` (single transaction, utf8mb4) gzipped + `tar` of `wp-content/uploads` from the wp-env container, into `docs/fixtures/live/backups/<UTC stamp>/` with a `manifest.json` (site URL, WP version, post count, sha256 of both files). Gitignored.
- `restore.sh <dir> [--host=<url>]`: `wp db import`, untar uploads, `wp search-replace <manifest url> <host> --all-tables --precise --skip-columns=guid`, `wp cache flush`, `wp ttm series:rebuild`, `wp ttm stats:flush`, `wp rewrite flush`.
- `drill.sh`: seed → backup → record `<title>` and the SHA-256 of the HTML body (whitespace-normalised, `<meta name="generator">` and nonce-free by rule) of `/`, `/signing-your-options-table/`, `/category/security/`, `/series/`, `/writing/` → `wp site empty --uploads --yes` → restore → compare post counts and the five hashes; non-zero exit on any difference. `npm run env:drill` runs it; the CI `integration` job runs it after the integration suite (it uses the seed, so no private data). These two scripts are the production runbook's backup/restore steps and are referenced from `DEPLOYMENT.md §10` and `MIGRATION.md §1.2`.

### 6.10 Live check suite (`tests/e2e/live.spec.mjs`, `npm run test:live`)

`scripts/live/screens.mjs` discovers URLs through WP-CLI after the plan and writes `docs/fixtures/live/screens.json`: the front page; the newest post in each of the seven sections; the oldest published post; two posts that contained `[ref]`, two that contained `[cci]`/`[cc]`, one with `[mfn]`, one `post-format-aside`; a post with a featured image and one without; the newest Journal post; every `/category/<section>/` page 1 and its last page; `/category/writing/` and `/writing/`; `/series/` and every series from `docs/migration/series.json`; `/tag/wordpress/`; `/2014/03/`; `/?s=wordpress`; `/speaking/`, `/blog/`; a 404. The spec is skipped when the file is absent (rule 48). For each URL at 1280 and 390:

- HTTP 200 (404 for the 404); no `Warning:`, `Notice:`, `Deprecated:`, `Fatal error` text in the body; `wp-content/debug.log` in the container gained no lines during the run (checked once at the end).
- axe serious/critical = 0.
- No cross-origin script, stylesheet, XHR or font requests. Cross-origin **images** are collected, not failed, and reported as a count per host (they should be zero after `migrate:images`; the remainder is the owner's list).
- Every `ttm-*` class present in the DOM has a rule in `ttm.css`/`style.css` (the coverage check, at runtime).
- No `[data-ttm-block]` wrapper without text or an `<img>`; no rendered text matching `\[(ref|cci|cc|mfn|audio|caption)\b` (unconverted shortcodes); no literal `&lt;p&gt;`.
- Masthead current item equals the page's section; footer present with 8 nav items; `<title>` non-empty; exactly one `<h1>`.
- Singles: kicker text equals the primary category name; byline date matches the post date; `.ttm-entry` present and non-empty; if the post is classic-converted, no `wp-block-freeform` block unless the post is in the `--dry-run` freeform list.
- Archives: year groups strictly descending; every row is a single `<a>` whose text starts with the title; pagination labels follow phase 3 §6.6.

Every failure class is triaged per rule 52 into `docs/feedback/phase-4/LIVE-TRIAGE.md` (committed): table of finding · URLs (count, one example) · class · fix (commit) or "owner cleanup" · test. The audit summary table (flag counts only) is appended.

### 6.11 Fidelity table additions and changes (`tests/e2e/fidelity.spec.mjs`)

Screens: phase 3 set plus `articleNoSeries = /transients-object-caches-and-fast-enough/`, `businessArchive = /category/business/`. Rows below are added; rows named "changed" replace the phase 3 row of the same id.

| id | screen | selector | vp | property | expected |
|---|---|---|---|---|---|
| footer-copy (changed) | article | `.ttm-footer__left p` | 1280 | count / text | 1 / matches `/^These Things Matter · © \d{4} Eric Mann · Built on WordPress$/` |
| footer-nocopyright | article, `/` | `.ttm-footer` | 1280 | text | does not contain "Scripture", "Copyright ©", "Biblica", "Zondervan" |
| footer-nav (changed) | article, `/` | `.ttm-footer__nav .wp-block-navigation-item` | 1280 | count / last text / hrefs | 8 / "Series" / none ends with `/feed/` |
| footer-one-line | article | `.ttm-footer__nav ul` / `.ttm-footer` | 1280 | height / height | ≤ 20px / ≤ 48px |
| footer-font | article | `.ttm-footer__nav a` (first) | 1280 | font-size / font-weight / color | 12px / 400 / neutral-700 |
| footer-front-nors | `/` | `.ttm-footer__nav a[href$="/feed/"]` | 1280 | count | 0 |
| ar-filter-business | `/category/business/` | `.ttm-filter-row .tag` | 1280 | count / first text | 6 / "All" |
| ar-filter-business-pos | `/category/business/` | `.ttm-filter-row` | 1280 | bounding box | below `.ttm-archive-head`, above `.ttm-archive-body`; border-top 2px, border-bottom 1px |
| ar-filter-sort-business | `/category/business/` | `.ttm-filter-row__sort` | 1280 | text | "Newest first" |
| toc-absent | articleNoSeries | `.ttm-series-toc` | 1280 | count | 0 |
| bar-absent | articleNoSeries | `.ttm-series-bar` | 1280 | count | 0 |
| aside-noseries-order | articleNoSeries | `.ttm-article aside > *` | 1280 | first / second class | `ttm-more-in` / `ttm-newsletter-box` |
| box-noseries | articleNoSeries | `.ttm-newsletter-box__title` | 1280 | text | "The weekly issue." |
| prevnext-auto-label | articleNoSeries | `.ttm-prevnext__label` | 1280 | texts | exactly ["← Previously in Technology", "Next →"] in order |
| prevnext-auto-title | articleNoSeries | `.ttm-prevnext__title` | 1280 | count / first font-size / links | 2 / 18px / both `<a>` to Technology posts outside any series or to a series part (chronology ignores series) |
| single-other (changed) | `/series/hardening-wordpress/` | `.ttm-series-single__other .ttm-series-row` | 1280 | count / first title / titles | 4 / "Reading CVEs" / none is "The Quiet Ledger", "Failover", "Salt Water Wires" |
| single-other-cats | same | `.ttm-series-single__other .ttm-series-row__categories` (first) | 1280 | text | contains "Security" |
| single-other-fiction | `/series/the-quiet-ledger/` | `.ttm-series-single__other .ttm-series-row__title` | 1280 | texts | exactly {"Failover", "Salt Water Wires"} in last-update order |
| single-other-heading | `/series/hardening-wordpress/` | `.ttm-series-single__other .ttm-cell-heading__label` | 1280 | text / count | "Other series" / 1 |
| a11y, network, selectors | every screen incl. the two new ones | page | both | as phase 3 | 0 / same-origin / every selector matches |

All other phase 3 rows stay green; `footer-copy` and `footer-nav` are the only phase 3 rows whose expectations change.

### 6.12 Seed content (fixtures only, `docs/fixtures/seed/`)

- Every section's posts carry tags so each section has ≥ 5 distinct tags with ≥ 1 post each: Technology (`wordpress`, `php`, `caching`, `performance`, `hosting`), Business (`consulting`, `pricing`, `clients`, `invoicing`, `strategy`), Faith (`prayer`, `doubt`, `psalms`, `liturgy`, `advent`), Writing (`craft`, `drafting`, `revision`, `serials`, `habit`), Opinion (`policy`, `culture`, `open-source`, `privacy`, `platforms`); Security unchanged; Journal untagged (no filter row, `aj-no-filter` stays).
- New nonfiction series **Reading CVEs** (`reading-cves`, Security, `complete`, 4 of 4, description "Four CVEs read the way an engineer reads them: what the advisory says, what the patch does, and what it would have taken to notice."), parts dated across 2024–2025 so `/category/security/` row meta reads "9 min · Series: Reading CVEs, 4 of 4" as phase 3 §6.6 already promises. `series.json` now has 7 series; the hub stats line and `hub-*` rows are re-checked.
- `transients-object-caches-and-fast-enough` stays out of every series and gets an older Technology neighbour: new post "Why I still read the WordPress changelog" (Technology, tags `wordpress`, `caching`, dated before it, ~600 words, no series), so both prev/next cells render; asserted in `SeederTest`.
- `docs/migration/series.json` (committed, not seed): the owner's series map for the live import, initial content per §9 Q1.

### 6.13 Documentation

- `docs/MIGRATION.md`: §1 gains "1.0 WXR path (what we have today)" pointing at `npm run env:live`; the archive path stays preferred for production; hostnames become `beta.mann.blog`; §2 lists the new commands and their order as `plan.sh` runs them; §3.1 gains the three exposure options (§9 Q7) with the recommended one; §5 links `audit --summary` and `LIVE-TRIAGE.md`.
- `docs/DEPLOYMENT.md §10`: backup/restore rows reference `scripts/live/backup.sh`/`restore.sh` and the drill; a "Beta hostname" paragraph.
- `docs/06-fallbacks.md`: F27, F28 added; F11 row names the prev/next labels; F16 unchanged.
- `docs/05-plugin-spec.md §10`: CLI list updated.
- `docs/feedback/phase-4/README.md`: names the mock for each seeded PNG and the URL for each live PNG.

### 6.14 Screenshots

`npm run screenshots` writes to `docs/feedback/phase-4/`: phase 3's set plus `article-noseries.png`, `archive-business.png`, `series-single.png` (re-taken), `footer.png` (front footer crop) at 1280; and, when the live import is present, `live-front.png`, `live-article-classic.png` (a converted `[ref]` post), `live-archive-technology.png`, `live-writing.png`, `live-series.png`, `live-journal.png` at 1280 and `live-front-390.png`. Live PNGs show the owner's public content and are committed.

## 7. Commands

```text
verify:
  composer lint
  composer test:unit
  npm run lint
  npm run test:unit
  npm run build
  bash scripts/forbidden-patterns.sh          (now includes the rule 47 private-data check)

extraVerify:
  plugins/ttm-core/, themes/ttm-theme/, tests/integration/:  npm run test:integration
  themes/ttm-theme/theme.json:                                npm run check:theme-json
  themes/ttm-theme/, plugins/ttm-core/blocks/, tests/e2e/:     npm run test:e2e
  scripts/live/, scripts/convert-classic.mjs, scripts/lib/:    npm run env:drill ; npm run test:unit
  plugins/ttm-core/src/Cli/:                                   npm run env:live && npm run test:live   (local only; skips cleanly without the export)

build: npm run build

foundry.json:
  baseBranch       = "main"
  branchPrefix     = "refine/"
  maxRounds        = 4
  commandTimeoutMs = 1800000     (env:live fetches 266 images and converts 718 posts)
```

New npm scripts: `env:live`, `env:backup`, `env:restore`, `env:drill`, `test:live`. `test:e2e` unchanged (seed first). CI: the `integration` job also runs `npm run env:drill`; no CI job needs the export.

## 8. Phases

Each phase ends with a task that runs `npm run screenshots`, commits the PNGs, pushes, and records `Manual check: NOT VERIFIED (human)` naming what to open.

### Phase 0 — Harness

- Rule 47 check in `forbidden-patterns.sh`; `.gitignore` already covers `docs/*.xml` and `docs/fixtures/live/`.
- §6.12 seed (tags, Reading CVEs); new screens in `urls.mjs`; §6.11 rows as tagged `fixme`; `Stats` invalidation + tiebreak + `stats:flush` (§6.2) with tests; the §3.1 fold-in edits (regex tightening, `ValuesTest` case, part-number padding note in `CLAUDE.md`).
- **Visible result:** `/category/business/` shows the filter row on the seed. Manual check: compare with mock `1e` line 933.

### Phase 1 — The four defects

- §6.1 footer (part, binding removal, CSS, rows `footer-*`); §6.2 verified; §6.3 TOC/F11 + prev/next (rows `toc-absent`, `bar-absent`, `aside-noseries-order`, `box-noseries`, `prevnext-*`); §6.4 `relatedTo` + F27 (rows `single-other*`); §6.5 F28 with integration tests.
- **Visible result:** `article-noseries.png` has no series chrome; `series-single.png` lists Reading CVEs first; `footer.png` is one line. Manual check: open the three URLs.

### Phase 2 — Import and migration commands

- `scripts/live/import.sh`, `plan.sh`, `screens.mjs`; `seed --starter-only`; `Seeder::reset()` from a live state; §6.7 commands with integration tests; `docs/migration/series.json`.
- Run `npm run env:live` with `LIVE_SKIP_ATTACHMENTS=1` first, then in full; record wall time per step in `LIVE-TRIAGE.md`.
- **Visible result:** the local site is eric.mann.blog's content under the new theme. Manual check: front page, `/category/technology/`, a 2014 post.

### Phase 3 — Classic conversion

- §6.8 shortcode pre-pass with Jest fixtures; `convert:import` footnotes meta; re-run `plan.sh` from a fresh import; `audit --only=shortcode` count recorded.
- **Visible result:** `live-article-classic.png` shows a converted `[ref]` post with core footnotes. Manual check: three `[ref]` posts and two `[cci]` posts in the editor and on the front end.

### Phase 4 — Live triage

- `live.spec.mjs` (§6.10); run it; fix every class of failure per rule 52 with a synthetic test; re-run until green; `LIVE-TRIAGE.md` complete with the audit summary; live screenshots.
- **Visible result:** `npm run test:live` green. Manual check: the owner opens `LIVE-TRIAGE.md` and five listed URLs.

### Phase 5 — Backup drill, docs, close-out

- §6.9 scripts and `env:drill` in CI; §6.13 docs; every `fixme` gone, allow-lists empty, budget recorded; HANDOFF; final screenshots; `npm run env:seed -- --reset` leaves a green `test:e2e`.
- **Visible result:** CI green including the drill; `docs/feedback/phase-4/` complete.

## 9. Open questions

| # | Question | Decision (default; owner may override in `docs/migration/series.json` or by editing this table) |
|---|---|---|
| Q1 | Which live tags become series? | `docs/migration/series.json` starts with: `boundless-summer-challenge` ("Boundless Summer Challenge", tags `boundless-summer-challenge` + `boundless`, nonfiction, complete) and `cryptopals` ("Cryptopals", tag `cryptopals`, nonfiction, complete). Nothing else until the owner adds it. The `fiction`/`horror` tags are stories about or in Writing, not serials; untouched. |
| Q2 | Primary category source | Yoast primary when present and valid, else nav order (§6.7). |
| Q3 | Excerpts from Yoast meta descriptions | Yes, non-Journal posts, never overwrite (§6.7). |
| Q4 | Images on dead domains | Sideload what still resolves, rewrite Photon to origin, flag the rest (§6.7). |
| Q5 | Jetpack `feedback`, `custom_css`, foreign templates/global styles, nav menus in the import | Imported as inert rows; not rendered by a block theme; listed in the audit summary; deletion is an owner action on the beta site. |
| Q6 | `post-format-aside` posts (7) | Flagged `post-format-aside` in the audit; not moved to Journal automatically. |
| Q7 | How is `beta.mann.blog` exposed? | Recommended: Cloudflare Tunnel hostname behind **Cloudflare Access** (email one-time-code policy listing the owner and the reviewers' addresses), `blog_public=0`. Alternatives documented: fully public, or cluster-only via a hosts-file entry pointing at the ingress. Next flight implements it. |
| Q8 | Filter row when a section truly has zero tags | F16 stands (row omitted); on the live site every section has tags. |
| Q9 | Footer "Built on WordPress" | Kept (S4). |
| Q10 | Writing page on the live site | The live landing page is retired (kept as draft `writing-legacy`); `/writing/` is the theme's fiction page, rendering the section-archive layout (F28, §6.5) until fiction exists. Imported Writing posts are not auto-marked as stories. |
| Q12 | `[seoslides]` (defunct slide engine, 2 posts) and other unknown shortcodes | Left in place, flagged `shortcode` in the audit; removal is owner content cleanup. The pre-pass never strips unknown shortcodes. |
| Q11 | Where does `beta.mann.blog` → `eric.mann.blog` happen? | `wp search-replace` as in §6.6 step 3; rehearsed by `env:live` and `restore.sh --host`. |

## 10. Deferred to later flights (owner backlog, not goals of this flight)

| # | Item | Notes for the future planner |
|---|---|---|
| D1 | **Private beta on k3s** (`beta.mann.blog`) | §1.3; manifests, Cloudflare Access, S3 backups, Batcache/memcached, Jetpack transfer, cut-over. |
| D2 | **Demo content and Playground, like WordPress/ipsum** (owner request 2026-09-23) | Ship a committed demo WXR (`docs/demo/demo-content.xml` or `plugins/ttm-core/demo/`) plus a WordPress Playground `blueprint.json` that installs plugin + theme, imports it and lands on the front page. Content is synthesised from the seed fixtures and a representative, owner-approved sample of live posts (rewritten or excerpted, with a content licence note separate from the GPL code licence), with generated images (real-looking, not the seeder's placeholders; no red). Screenshots for the README in ipsum's style: 1280 and 390, front page, article, journal post, section archive, series hub, single series, Writing; generated by `npm run screenshots -- --readme` into `docs/readme/` and embedded in `README.md`. The existing `Seeder` and `wp export` are the natural source. |
| D3 | **Open-source release, GPL-2.0-or-later** | Licence headers in `style.css`/`ttm-core.php`/`readme.txt`, `LICENSE` at root, a public README (what it is, screenshots, install via Playground link, architecture summary from `docs/README.md`), strip owner-specific defaults (hostnames, series map) into example files, confirm the fonts' licence, and make sure nothing private is in history (rule 47 already guards the tree). |

## Appendix A — Files in `docs/`

| File | What it is |
|---|---|
| `SPEC.md` | This file. |
| `ericmann039sblog.WordPress.2026-09-23.xml` | The live export. Gitignored (`docs/*.xml`). Input to `npm run env:live`. |
| `fixtures/live/` | Gitignored: `screens.json`, `audit.csv`, `backups/`. |
| `migration/series.json` | Committed owner series map read by `plan.sh`. |
| `feedback/phase-4/` | This flight's screenshots, `README.md`, `LIVE-TRIAGE.md`. |
| `Eric Mann Newspaper.dc.html` | Mock cards; footer at lines 327, 411, 973; filter row at 933. |
| `README.md`, `01`–`07` | Design handoff; `06` gains F27/F28. |
| `phase-1/`, `phase-2/`, `phase-3/` | Archived flights. `phase-3/SUMMARY.md §Spec issues` is folded into §3.1. |
| `SETUP.md`, `MIGRATION.md`, `DEPLOYMENT.md`, `fixtures/seed/`, `spikes/` | As before; `MIGRATION.md`/`DEPLOYMENT.md` per §6.13. |
