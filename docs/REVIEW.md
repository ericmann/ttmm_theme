# Review — These Things Matter build
Round: 3

Branch `build/2026-09-21` (base `poc` @ `8379c6f`, head `67394eb`). 96 tasks (76 build + 15
round-1 fixes + 3 round-2 fixes + 2 round-3 fixes), all done, 0 blocked, 0 skipped. This review
covers the whole branch: the two round-3 fix commits (`b38798d`, `8909f21`) were read diff-by-diff
against PLAN `## Review fixes (round 3)` and SPEC §3.4 rules 24/27/28; both round-3 findings
(C1, T1) were re-checked against the code as it now stands; the CLAUDE.md `## Constraints`
greps were re-run over the entire tree, not just the round-3 diff; and the only round-2 residue
left open (S1) was re-read.

## Verdict: APPROVED

What was verified by this reviewer (not taken from the log):

- `foundry_verify` with the round-3 files, green end to end: `composer lint` 0 errors,
  `composer test:unit` 131/131 (951 assertions), `npm run lint` (theme.json, block.json, CSS
  budget 33070/33200 — unchanged, no CSS touched), `npm run test:unit` 12 passed / 2
  pre-existing skips, `npm run build`, `bash scripts/forbidden-patterns.sh` clean, and
  `npm run test:integration` 374/374 (1154 assertions, 0 skipped).
- Mutation sampling on the round-3 work, each applied, run, and restored with `git checkout`:
  - `story-tiles/render.php` back to `$ttm_columns = 2;` → `forbidden-patterns.sh` exits 1
    (rule 24) **and** `StoryTilesTest::test_default_columns_come_from_config` fails
    (`is-cols-2` rendered).
  - `writing.tile_columns` deleted from `Config::defaults()` →
    `ConfigTest::test_defaults_contain_every_spec_key` fails.
  - `self::$stale_scope` decrement removed from `Cells::mark_empty()` →
    `CellsTest::test_fresh_section_after_stale_section_keeps_its_dek` fails with the fresh
    Technology row missing `ttm-item__dek` (the exact site-wide leak T1 described; the other
    13 CellsTest tests still pass, which is why this test was needed).
  - `Stats::category()` `newest_date` forced to `null` →
    `StatsTest::test_category_stats_count_and_year_range` fails on its own (`null` vs
    `'2024-06-01 12:00:00'`), plus four CellsTest F9 tests.
- Mutation sampling in modules this round did not touch, to keep the whole-branch sample
  honest: `Dates::short_month` "Sept" → "Sep" (4 unit failures in DatesTest/ValuesTest);
  `Newsletter\Handler` honeypot check inverted (`HandlerTest::test_honeypot_returns_success_redirect_without_forwarding`);
  `Cache\Headers` clamp removed (`HeadersTest::test_min_age_floor`, `test_cap_applies_when_config_hour_is_far`).
- Whole-tree constraint greps (independent of the script): no `prefers-color-scheme`; no
  `wp_enqueue_style`/`<style` in the plugin; no block `view.js`/`viewScript`; no
  `fetch(`/`XMLHttpRequest`/`apiFetch`/`admin-ajax`/`wp-json` under the theme's JS;
  `package.json` `dependencies` is `{}`; no unbounded queries; no data-owning calls under
  `themes/`; no clock reads outside `Support/Clock.php`; `TTM_CORE_API === 1` defined and
  checked in `inc/bindings-compat.php`; zero hex literals in `ttm.css`. Every `TTM\Core`
  reference under `themes/` is guarded (and `BoundariesTest` pins it).
- `test:e2e` was **not** re-run this round: the only production change is the one-line
  `Config::get( 'writing.tile_columns', 2 )` substitution, which produces byte-identical
  markup (`is-cols-2`) on the default config; nothing under `themes/` or `tests/e2e/`
  changed since the round-2 review ran it 48/48 (confirmed by `git log --stat 5b011aa..HEAD`).
- Both round-3 findings are closed in code: C1 (`writing.tile_columns` in `Config::defaults()`,
  read in `render.php`, listed in `ConfigTest`; rule 24's second grep catches plain `= N;`
  assignments with the same numeric ranges and HTTP-status allow-list, and trips on nothing in
  the current tree); T1 (the two-section test above; `newest_date` asserted for a populated
  and an empty category).

Categories 1–3 are clean across the entire branch and nothing is blocked, so the branch is
approved. The items below are low-severity notes for the owner, not defects.

## Findings (most severe first)

### 1. Constraints (CLAUDE.md `## Constraints`)

Clean. `forbidden-patterns.sh` is clean and now enforces rule 24 in both `=> N` and `= N;`
forms; both mutations above confirm it fails when either form returns.

Two properties of the new rule-24 grep worth knowing, neither a finding:

- It is a superset: `=\s*[2-9][0-9]*\s*;` also matches `>= 2;`, `<= 10;`, `+= 2;`, `=== 2;`.
  Nothing in the tree hits those today (verified by grep); if one ever does, it is a literal
  comparison/increment against a tunable, which rule 24 arguably wants flagged anyway.
- It still does not see literals in function arguments, including the 43
  `Config::get( 'key', N )` fallback arguments across `src/` and `blocks/`. That is the
  convention every block and module already uses (the fallback is only reachable when the key
  is absent from `defaults()`, which `ConfigTest` prevents), and the task explicitly told the
  implementer not to widen the grep to function arguments. Accepted; see SI-12.

### 2. Boundaries (SPEC §3.1 rule 1, §4.2)

Clean. Nothing in round 3 touched a module import; `BoundariesTest` (use-line, inline
fully-qualified, and theme-reference scans) is green; `themes/ttm-theme/**/*.php` references
only `\TTM\Core\Config`, guarded.

### 3. Tests

Clean. Both acceptance tests named by R3-01 exist and fail under their mutation; all three
named by R3-02 exist and fail under theirs (see the verified list above). The new tests test
the mechanic (rendered class / rendered dek / cached field), not a re-derivation of it.
`StoryTilesTest`'s `ttm_config` filter cannot leak into later tests: `TTM_IntegrationTestCase::tear_down()`
already calls `Config::reset()` (and `StoryTilesTest::tear_down()` does so again).

### 4. Performance (SPEC §3.2)

Nothing new. One extra `Config::get()` in `story-tiles/render.php` (memoised array read).

### 5. Spec drift / low

**S1 (carried from round 2, still open, no task) — docblock residue.**
`plugins/ttm-core/src/Query/Cells.php:26-30` still says the counter "lives on the filter pair
rather than in `Query\Archive`"; `Archive` was never a candidate. R3-02 was correctly
test-only and did not touch `Cells.php`. Fix whenever `Cells.php` is next edited.

**S2 (low, docs only) — HANDOFF labels `writing.tile_columns` an `⚠️ ASSUMPTION` key.** It is
the design's number ("tiles 2-col", 02 §D), not an assumption; the previous review said so
when queuing C1. Nothing in code carries the marker, so this only affects how the owner reads
the tuning table in HANDOFF §Round 3. No task.

Not findings: `cssBudgetBytes` unchanged (33070/33200, CLAUDE.md agrees); `plugins/ttm-core/README.md`
§Configuration deliberately has no per-key table, so `writing.tile_columns` needs no README
entry; SPEC §5.4 does not list the new key (SI-13, owner).

### 6. Interpretation choices (HANDOFF §Round 3)

- **R3-01** — mirroring the existing `=>` grep verbatim (same ranges, same HTTP-status
  allow-list translated to `status = NNN;`) rather than widening to function arguments or
  comparisons: accepted; it is what the task asked for and what SPEC rule 24's enforcement
  can do without a tokenizer. The implementer's claim that no reviewed-and-accepted literal
  trips the new pattern is true (script clean on the tree).
- **R3-02** — none claimed, none needed: the tests match the task text's fixtures and
  assertions exactly.

The `Config::all()` memoisation gotcha HANDOFF records (call `Config::reset()` after
`add_filter('ttm_config')` in tests) is accurate and already the precedent in `LeadTest` and
`CellsTest`; worth a line in `docs/SETUP.md` some day, not a finding.

### 7. Blocked and skipped tasks

None. Nothing to unblock.

### 8. Readability / naming

Good. `test_fresh_section_after_stale_section_keeps_its_dek` and
`test_newest_date_is_null_for_an_empty_category` say exactly what they prove; the rule-24
script comment explains why the second grep exists.

## Round-3 findings, verified closed

C1 `writing.tile_columns` (`Config.php:81`) read at `story-tiles/render.php:29`, in
`ConfigTest`'s key list, covered by `StoryTilesTest::test_default_columns_come_from_config`;
`forbidden-patterns.sh:100-103` second rule-24 grep, exits 1 on the reintroduced literal.
T1 `CellsTest::test_fresh_section_after_stale_section_keeps_its_dek` fails without the
`$stale_scope` decrement; `StatsTest` asserts `newest_date` for a populated and an empty
category and fails without the field.

## Spec issues

Carried from rounds 1–3 and still open for the owner: **SI-1** (rule 15 "JSON" vs typed
`show_in_rest` arrays), **SI-2** (§4.2 "May import" column narrower than the design needs),
**SI-3** (`sections.technology_slug` missing from §5.4), **SI-4** (`inc/template-hierarchy.php`
listed in §4.3 / 04 §1 but not built), **SI-5** (rule 12 vs CLI term enumeration), **SI-6**
(rule 30's 25 KB budget vs 33 KB measured — decide the number once), **SI-7** (§6.10 "GET" should
read "GET/HEAD"), **SI-8** (`cells.thin_days` needs no code; remove from §5.4 / 06 F9), **SI-9**
(`Verse` → `Cache` import vs table order), **SI-10** (§5.4 has no F9 count key; code has
`cells.stale_count = 2`), **SI-11** (§4.2 `Query/Archive` responsibility column vs where year
grouping and pagination labels actually live), **SI-12** (rule 24's exception list should name
HTTP status codes, structural arithmetic and `Config::get()` fallback arguments, which the
enforcement script necessarily tolerates).

New this round:

- **SI-13** §5.4 has no `writing.tile_columns` key; code now has `writing.tile_columns = 2`
  (the design's "tiles 2-col"). Add it next to `writing.story_tiles`, unmarked (it is a design
  number, not an assumption).

## Manual checks still owed (copied from HANDOFF.md §2, §Round 1, §Round 2, §Round 3)

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
  `cells.stale_count` rows show, and that the sections rendered *after* it still show their deks
  (now also pinned by `CellsTest`).
- R2-03: page through a seeded category archive with more than one page and confirm "Older (…) →" /
  "← Newer (…)" render on both the block-bound label and the query-pagination next/previous links.

**Round 3**
- R3-01: no visual change (same markup, same `is-cols-N` classes); nothing new to eyeball.
- R3-02: test-only; the F9 stale-year check above is unchanged.
