# Review — These Things Matter build
Round: 3

Branch `build/2026-09-21` (base `poc` @ `8379c6f`, head `7429291`). 94 tasks (76 build + 15
round-1 fixes + 3 round-2 fixes), all done, 0 blocked, 0 skipped. This review covers the whole
branch: the three round-2 fix commits (`7dc3c72`, `e07c215`, `21df0e8`) were read diff-by-diff
against PLAN `## Review fixes (round 2)` and the SPEC sections each cites; every round-2 finding
(C1, C2, B1, T1, T2, S2, S3) was re-checked against the code as it now stands; and the CLAUDE.md
`## Constraints` greps were re-run over the entire tree, not just the round-2 diff.

## Verdict: CHANGES REQUESTED

What was verified by this reviewer (not taken from the log):

- `foundry_verify` base set green: `composer lint` 0 errors, `composer test:unit` 131/131
  (950 assertions), `npm run lint` (theme.json, block.json, budget 33070/33200), `npm run
  test:unit` 12 passed / 2 pre-existing skips, `npm run build`, `bash
  scripts/forbidden-patterns.sh` clean.
- `npm run test:integration` 371/371 (1146 assertions, 0 skipped). `npm run test:e2e` 48/48 (reseeded wp-env, 2 viewports, axe clean).
- Mutation sampling, each caught by the named test: `suppress_stale_dek()` returning content
  unchanged (CellsTest stale-query test + FrontPageStatesTest F9); `is_stale_year()` issuing a
  query per call (CellsTest warm-cache test, 423 vs 422 queries); `posts_per_page` back to a
  literal `2` (CellsTest config-count test); `Sources::short_date()` without its `! $post` guard
  (FrontSourcesTest empty-value test, TypeError); a duplicated `Older (%s) →` in `Archive.php`
  (BoundariesTest one-source test); an inline `\TTM\Core\Blocks\Helpers::class` in
  `Query/Lead.php` (BoundariesTest inline-reference test); `SeriesPosition` removed from
  CLAUDE.md (ScaffoldTest module-map test); a guarded `\TTM\Core\Query\Cells` call in
  `themes/ttm-theme/inc/patterns.php` (BoundariesTest theme-references test).
- One mutation was **not** caught: removing the `$stale_scope` decrement in
  `Cells::mark_empty()` passes all 28 F9/front-page tests (T1 below).
- Whole-tree constraint greps: theme owns no data, every `TTM\Core` reference under `themes/`
  is `Config` and guarded, no inline styles outside the allow-list, no nonces/per-visitor calls
  in cacheable output, no clock reads outside `Support/Clock.php`, no unbounded queries, no
  `wp_safe_remote_*` outside the three call sites, no dangerous PHP, front-end JS is `nav.js`
  only, no `view.js`. One rule-24 literal (C1 below).
- Every round-2 finding is closed in code: C1 (`cells.stale_count`, allow-list gone), C2
  (`Stats::category()['newest_date']`, zero queries when warm), B1 (`patterns.php` reads only
  `Config`; `section-cell.php` always emits `post-excerpt`; dek suppressed plugin-side), T1
  (source-level empty test), T2 (inline-reference scanner), S2 (one source for the pagination
  strings, relabel filters in `Bindings\Sources`), S3 (CLAUDE.md map, render.php docblock,
  HANDOFF R1-02 bullet).

Approval is withheld because categories 1 and 3 are not clean across the branch: one bare
numeric tunable survives in a `render.php` and the rule-24 script cannot see it (C1), and the
round-2 F9 mechanism ships a scope counter whose exit path has no test and whose failure mode
is site-wide (T1). Both are small; nothing else on the branch is wrong.

## Findings (most severe first)

### 1. Constraints (CLAUDE.md `## Constraints`)

**C1 — Rule 24: a bare tile-column literal in `story-tiles`, and `forbidden-patterns.sh` rule 24
only sees `=>` array syntax.** `plugins/ttm-core/blocks/story-tiles/render.php:29`
`$ttm_columns = 2;` is the default column count when the block's `columns` attribute is `0`
(its `block.json` default), so it is the value every seeded `/writing/` page renders with
(`is-cols-2`). Every sibling default in the same file and in every other block goes through
`Config::get()` (`writing.story_tiles`, `writing.plain_count`, `books.*`). `scripts/
forbidden-patterns.sh:97` matches `=>\s*[2-9]…` only, so a plain assignment is invisible to the
check that is supposed to enforce rule 24 — the R2-01 commit body records exactly this ("a bare
`= 2` assignment does not match the rule 24 regex"), which is how the original
`$query['posts_per_page'] = 2;` sat unflagged through round 1. What breaks: the constraint is
narrower than CLAUDE.md says, and the next `= N;` tunable lands silently. Fix: a
`writing.tile_columns` Config key (default `2`, design's "tiles 2-col") read in `render.php` and
listed in `ConfigTest`; extend rule 24's grep with `=\s*[2-9][0-9]*\s*;` / `=\s*[0-9]{2,}\s*;`
(same `Config.php` and HTTP-status exclusions). Reviewed and deliberately left as-is: HTTP status
codes (`Verse/Fetcher.php:192,201` `304`/`200`, the REST `404`s), structural arithmetic
(`Support/Text.php:72` `intdiv(…, 2)`, `Blocks/Helpers.php:72` `count(...) < 2`,
`Cli/Seeder.php:57` `dirname(…, 3)`), and the DEV-ONLY seeder's fixture literals
(`Cli/Seeder.php:487,572,575`). Task P6-04 / R1-02.

No other constraint hits anywhere in the tree (see the grep list above).

### 2. Boundaries (SPEC §3.1 rule 1, §4.2)

Clean. `themes/ttm-theme/**/*.php` references only `\TTM\Core\Config` (guarded) and
`BoundariesTest::test_theme_php_references_only_config_from_the_plugin` now enforces it; the
`use`-line and inline-reference scans are both green and both killed their sampled mutation; no
file under `Cache/`, `Verse/`, `Newsletter/` references `Blocks/`. The relabel filters moving
from `Query\Archive` to `Bindings\Sources` is downward (`Bindings` may import `Query`); see
SI-11 for the §4.2 wording it leaves behind.

### 3. Tests

**T1 — Rule 27: the stale-scope exit in `Cells::mark_empty()` has no test, and its failure mode
is every later section losing its dek.** `plugins/ttm-core/src/Query/Cells.php:212-214`
decrements `self::$stale_scope` after a stale `core/query` has rendered; `:181-183`
`suppress_stale_dek()` blanks *every* `core/post-excerpt` while the counter is above zero. With
the decrement deleted, all 28 tests matching `CellsTest|FrontPageStatesTest|FrontPageTest` still
pass (verified), because each renders at most one stale section and nothing after it. A
throwaway test that renders a stale Security query and then a fresh Technology query in the same
request fails under that mutation: the Technology row comes back with no `ttm-item__dek` at all.
On the real front page that is every section cell after the first stale one (and any
`post-excerpt` elsewhere on the page) — exactly the "cached HTML is wrong for everyone" class of
bug SPEC §3.2 exists to prevent. Fix:
`CellsTest::test_fresh_section_after_stale_section_keeps_its_dek` — stale section then fresh
section through `do_blocks()` in one test, assert the stale query has `is-stale` and no
`ttm-item__dek`, and the fresh query still contains `ttm-item__dek` and its excerpt text. While
in that file, `Stats::category()['newest_date']` (`Stats.php:94,108`, the field the whole F9
decision now rests on) has no direct assertion — R2-01 listed `StatsTest.php` in its files but
did not touch it; add `newest_date` to `StatsTest::test_category_stats_count_and_year_range`
(equals the newest post's `post_date`, `null` for an empty category). Task R2-01.

Everything else in this category holds: the acceptance tests named by R2-01/R2-02/R2-03 all
exist, test the mechanic rather than re-deriving it, and each failed under its sampled mutation.

### 4. Performance (SPEC §3.2)

Nothing new. `is_stale_year()` is now `get_term_by()` (object-cached) plus one transient read;
the warm-cache test pins it at zero queries. `Archive::year_range()` keeps the one bounded,
ids-only re-query per pagination link accepted in round 2. The transient's TTL-driven
recompute on a front-end request is what SPEC §5.3 itself specifies for `ttm_category_stats_*`.

### 5. Spec drift / low

**S1 — Docblock residue.** `plugins/ttm-core/src/Query/Cells.php:26-30` says the counter "lives
on the filter pair rather than in `Query\Archive`" — `Archive` was never a candidate for
section-cell state; the sentence was copied from `Blocks\Helpers::$archive_scope`. No task;
fix it if `Cells.php` is touched again.

Not findings: `cssBudgetBytes` is unchanged this round (33070/33200 used, CLAUDE.md agrees);
`plugins/ttm-core/README.md` §Configuration deliberately lists no per-key table, so
`cells.stale_count` needs no README entry; `SPEC §5.4` still lists `cells.thin_days` (SI-8,
owner).

### 6. Interpretation choices (HANDOFF §Round 2)

Accepted as the reading most consistent with SPEC: R2-01 resolving the category with
`get_term_by('slug')` before the transient read (a cached term lookup, not the request-time
`WP_Query` the task removed); `is-stale` and `is-empty` as independent classes on the same
wrapper; R2-02's deliberately simple comment stripper in the inline scanner (documented, and it
kills the sampled mutation); R2-03 keeping `Archive::year_range()`'s bounded re-query exactly as
`resolve_label()` had it, and ScaffoldTest's line-scoped module-map check with the `*Command`
shorthand.

Not accepted: none — the round-2 choices are all sound. The gap in R2-01 is a missing test (T1),
not a wrong reading.

### 7. Blocked and skipped tasks

None. Nothing to unblock.

### 8. Readability / naming

Good. `Sources::resolve_pagination_label()` reads clearly as "lookup then format"; the R2 tests
say what they prove in their names (the `…_never_blank_for_a_valid_date` rename was right). S1
is the only residue.

## Round-2 findings, verified closed

C1 `cells.stale_count` read in `Cells` only, `forbidden-patterns.sh` allow-list removed (script
still clean); C2 `Stats::category()` carries `newest_date`, flushed on `transition_post_status`,
`is_stale_year()` issues no query when warm (mutation confirmed); B1 `patterns.php` and
`section-cell.php` no longer vary per request, dek suppressed by `render_block_core/post-excerpt`
inside a `render_block_data`/`render_block_core/query` scope pair, `is-stale` from `mark_empty()`,
`FrontPageStatesTest` F9 green without the `init` re-fire, theme-reference guard test added;
T1 `FrontSourcesTest::test_short_date_and_relative_date_empty_without_post` (no `postId`, and a
nonexistent id) and the unit test renamed; T2 inline-reference scanner; S2 one source for both
pagination strings with the relabel filters in `Bindings\Sources`; S3 CLAUDE.md module map,
`archive-by-year/render.php` docblock, `Cells.php` comments, HANDOFF R1-02 bullet.

## Spec issues

Carried from rounds 1–2 and still open for the owner: **SI-1** (rule 15 "JSON" vs typed
`show_in_rest` arrays), **SI-2** (§4.2 "May import" column narrower than the design needs),
**SI-3** (`sections.technology_slug` missing from §5.4), **SI-4** (`inc/template-hierarchy.php`
listed in §4.3 / 04 §1 but not built), **SI-5** (rule 12 vs CLI term enumeration), **SI-6**
(rule 30's 25 KB budget vs 33 KB measured — decide the number once), **SI-7** (§6.10 "GET" should
read "GET/HEAD"), **SI-8** (`cells.thin_days` needs no code; remove from §5.4 / 06 F9), **SI-9**
(`Verse` → `Cache` import vs table order), **SI-10** (§5.4 has no F9 count key; code now has
`cells.stale_count = 2`).

New this round:

- **SI-11** §4.2 lists "pagination labels" under `Query/Archive` and "year grouping" too, but the
  same table forbids `Query/` importing `Bindings/` or `Blocks/`, so after R1-01/R2-03 the year
  grouping lives in `Blocks\Helpers` and the labels/relabel filters in `Bindings\Sources` (with
  `Archive` keeping only the year-range lookup). Recommend rewording the `Query/` and
  `Bindings/` rows to match, so the responsibility column and the import column agree.
- **SI-12** Rule 24's exception list ("`0`, `1`, `-1` for `array_search` results, array indices,
  and CSS values") does not cover HTTP status codes or structural arithmetic (`intdiv(…, 2)`,
  `count(…) < 2`, `dirname(…, 3)`), which the tree necessarily contains and the script
  allow-lists. Recommend the rule name those classes explicitly so the enforcement script's
  allow-list has a spec basis.

## Manual checks still owed (copied from HANDOFF.md §2, §Round 1, §Round 2)

**Phase 0**
- Open the site and confirm fonts render with zero requests to `fonts.googleapis.com`/`fonts.gstatic.com`.
- `http://localhost:8888/` after `npx wp-env start`: page renders in Archivo on `#f3f2f2` with no Google
  Fonts request; wp-admin shows no ttm-core/ttm-theme version-mismatch notice.

**Phase 1**
- Open a series term's edit screen in wp-admin and confirm fields/part list.
- Open a post in the block editor and confirm the sidebar panel renders with all fields.
- Publish a post missing a dek/alt text/series part and confirm the pre-publish panel lists warnings
  without blocking publish; check the Posts list columns.
- Settings → These Things Matter: save the General tab, confirm it round-trips; Books tab: add a row, save.
- After `npm run env:seed`: a seeded chapter's editor shows series, part "12 of 31", form Chapter;
  `term.php?taxonomy=series&tag_ID=<the-quiet-ledger>` shows all fields and the ordered part list;
  Posts list shows Primary section / Series / Words columns.

**Phase 2**
- Front page and an inner page at ≤720px: nav overlay opens/closes and closes on link tap; inner masthead
  nav is not overlaid at ≥721px.
- Deactivate/reactivate the theme on a fresh site: 7 categories/4 pages (with templates)/Sections nav
  appear; editor shows the 3 variations.
- Skip link/header landmarks/nav render; the 7 chrome patterns appear in the inserter.
- A post, its category archive, a 404 and search results: current-section highlighting and shells render.
- `/about/` at 1280 and 390 per the P2-07 log.

**Phase 3**
- `/` vs prototype badge 2a at 1280 and 3a at 390 (lead, verse box with `dailymedtoday.com` link,
  Technology span 2, Writing cell "The Quiet Ledger — Ch. 12", series strip, red poster, phone stacking).

**Phase 4**
- A seeded `hardening-wordpress` part vs 2b/3b; a non-series Technology post shows "← Previously in
  Technology"; a seeded journal post vs 2c.

**Phase 5**
- `/category/security/` vs 1e incl. `?tag=` narrowing; `/category/journal/` stream; `/tag/<seeded>/` and
  `/?s=cache`.

**Phase 6**
- `/series/` vs 1f; `/series/the-quiet-ledger/`; `/writing/` vs 2d (cover shadow only there);
  `/category/writing/` same layout.

**Phase 7**
- `curl -I http://localhost:8888/` shows the computed `Cache-Control` (HEAD now handled);
  `wp ttm verse fetch --force` logs a purge; poster shows the mailto button; switch provider to
  `mailto` and `none` and confirm F26.

**Phase 8**
- CI green on the branch including the e2e job and `playwright-report` artifact; run
  `docs/MIGRATION.md` §1–§2 against a real archive/WXR export in wp-env (audit CSV, migrate:politics,
  primary:assign, series:assign for boundless/cryptopals, recount, convert:export → convert-classic →
  convert:import, close-comments), opening the front page, `/category/technology/` and a journal post
  after each step.

**Round 1**
- R1-04/R1-08/R1-09: eyeball the verse box's "Meditation for Sept N" wording and the Writing page's h1
  (previously an h2 on `/category/writing/`) for visual regressions.
- R1-05: once real content exists, spot-check a stale-year section cell: the dek genuinely doesn't
  render and only 2 rows show.
- R1-06: run `wp ttm migrate:politics --to=child` and `wp ttm migrate:close-comments` against a real
  archive and confirm the Cloudflare/Batcache purge fires once, not once per post.
- R1-12: open a single Journal post with syndication URLs and confirm the new `.ttm-syndication` CSS
  matches 01 §4.13 (never visually reviewed).
- R1-14: rename a live category in wp-admin and confirm the masthead nav picks it up immediately.

**Round 2**
- R2-01: once a section genuinely goes stale in production (its newest post crosses
  `cells.stale_year_days`), confirm the dek is absent from the rendered HTML (not CSS-hidden), exactly
  `cells.stale_count` rows show, and — per T1 — that the sections rendered *after* it still show
  their deks.
- R2-03: page through a seeded category archive with more than one page and confirm "Older (…) →" /
  "← Newer (…)" render on both the block-bound label and the query-pagination next/previous links.
