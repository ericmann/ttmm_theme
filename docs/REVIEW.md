# Review — These Things Matter build
Round: 2

Branch `build/2026-09-21` (base `poc` @ `8379c6f`, head `e8e3366`). 91 tasks (76 build + 15 round-1
fixes), all done, 0 blocked, 0 skipped. This review covers the whole branch, with the round-1 fix
commits (`dcc90ef`..`ce7d3e3`) read diff-by-diff against PLAN `## Review fixes (round 1)` and the
SPEC sections each cites, and the round-1 findings re-checked against the code as it now stands.

## Verdict: CHANGES REQUESTED

What was verified by this reviewer (not taken from the log):

- `foundry_verify` base set green: `composer lint` 0 errors, `composer test:unit` 127/127,
  `npm run lint` (theme.json, block.json, budget 33070/33200), `npm run test:unit` 12 passed /
  2 pre-existing skips, `npm run build`, `bash scripts/forbidden-patterns.sh` clean.
- `npm run test:integration` 367/367 (0 skipped, 1134 assertions); `npm run test:e2e` 48/48.
- Mutation sampling, every one caught by the named test: added an upward `use` to
  `Query/Archive.php` (BoundariesTest); dropped HEAD from `Cache\Headers` (unit HeadersTest);
  inverted `Cells::is_stale_year()` (CellsTest + FrontPageStatesTest); `last_update = now`
  (SeriesIndexTest, SeriesListTest, SerialsTest); verse F6 back to `!== today`
  (VerseOfTheDayTest); audit alt-regex regression (AuditCommandTest); `migrate:politics` without
  the primary-meta write and `close-comments` without the purge (MigrateCommandTest); REST
  `form=fiction` exact-match (SeriesRestTest); series archive not current (CurrentSectionTest);
  masthead ignoring the term name (ChromePartsTest); syndicated-to `<p>` wrapper
  (SyndicatedToTest); `series:assign` without `Form::on_save` (MaintenanceCommandsTest).
- Every round-1 finding (C1–C4, B1–B3, T1–T5, P1, S1–S13, i18n items) is closed in code, with
  three residues that are the findings below.

Approval is withheld because categories 1–3 are not clean: R1-05's F9 implementation introduced
a hard-coded tunable and a stale rule-24 allow-list (rule 24), an uncached per-request query on
every request site-wide (rule 13 / SPEC §3.2), and a new theme→plugin data call at `init`
(rule 1 / SPEC §4.2); and the rule-26 empty-value gap for `ttm/short-date`/`ttm/relative-date`
was closed with a test that does not test an empty value. Everything else on the branch holds.

## Findings (most severe first)

### 1. Constraints (CLAUDE.md `## Constraints`)

**C1 — Rule 24: F9's stale-section count is a bare literal, and the rule-24 allow-list is stale.**
`plugins/ttm-core/src/Query/Cells.php:62` `$query['posts_per_page'] = 2;` and
`themes/ttm-theme/inc/patterns.php:58` `'per_page' => $ttm_is_stale ? 2 : …` — 06 F9's "show 2"
has no `Config` key (every other cell count does: `cells.counts`, `journal.rail_count`,
`writing.plain_count`). `scripts/forbidden-patterns.sh:101` still pipes through
`grep -v 'Query/Cells.php' | grep -v 'blocks/writing-cell/render.php'` with a comment saying a
"separate, already-queued R-task" owns those files; that task was R1-05 and it is done. The
check passes with both exclusions removed (verified), so the allow-list now only hides future
regressions in those two files. HANDOFF §Round 1 R1-02 also states "none was queued this round",
which is wrong. What breaks: the constraint is silently narrower than CLAUDE.md says. Fix:
`cells.stale_count` (2) read via `Config::get()` in `Cells` only (the theme should not need the
number at all, see B1), delete the two `grep -v`s and the comment. Task R1-05, R1-02.

**C2 — Rule 13 / SPEC §3.2 rule 13, §4.4: staleness is recomputed with an uncached `WP_Query` on
every request.** `Cells::is_stale_year()` (`Cells.php:188-213`) runs a `WP_Query` per call and
caches nothing. It is called four times at `init` on *every* request — front page, admin, REST,
cron, CLI — by `themes/ttm-theme/inc/patterns.php:52` (once per section-cell pattern), and again
per section query in `filter_query_vars()` (`Cells.php:57`). The front page pays ~8 extra
queries; every other request pays 4 it never uses. The PROGRESS log records that a static memo
"broke re-registration when `init` fires twice", which is a symptom of computing this at pattern
registration rather than a reason not to cache. The `WP_Query` also has no explicit
`post_status`, so a logged-in user with private posts can get a different staleness than an
anonymous visitor (rule 8, minor). Fix: derive "newest post date" from the per-category transient
`Query\Stats` already maintains (`ttm_category_stats_{id}`, flushed on publish transitions via
`Stats::flush_for_post()`) — add a `newest_date` field there — or a sibling transient with the
same flush; `is_stale_year()` then reads the transient and issues no query on a warm cache.
Task R1-05.

### 2. Boundaries (SPEC §3.1 rule 1, §4.2)

**B1 — The theme now calls `Query\Cells` to decide markup.** `themes/ttm-theme/inc/patterns.php:52`
`\TTM\Core\Query\Cells::is_stale_year( $ttm_slug )` and `inc/pattern-templates/section-cell.php:37`
omit the `core/post-excerpt` block when the plugin says a section is stale, so the registered
pattern content differs per request depending on data. Rule 1 says the theme reads plugin data
only through `ttm/*` blocks and bindings; the guarded `Config::get('cells.counts')` read accepted
in round 1 is configuration, this is a query result. The awkwardness shows in
`tests/integration/Fallbacks/FrontPageStatesTest.php:125`, which has to re-fire `init` to see the
branch. Fix (plugin side, no theme data call): `Cells` suppresses the dek itself — a
`render_block_core/post-excerpt` (or `render_block_data`) filter that returns `''` while a
section query whose `ttmSection` is stale is rendering (the scoped-flag technique
`Blocks\Helpers::track_archive_scope()` already uses), plus `is-stale` on the query wrapper from
`mark_empty()`; `section-cell.php` goes back to unconditional markup and `patterns.php` back to
`cells.counts` only. Task R1-05.

No other boundary crossings: `BoundariesTest` is green and killed the sampled upward import;
no file under `Cache/`, `Verse/`, `Newsletter/` imports `Blocks/`; theme rules 1/3 clean
(`grep` for the forbidden calls under `themes/` is empty; every `\TTM\Core` reference is guarded).

### 3. Tests

**T1 — Rule 26: `ttm/short-date` / `ttm/relative-date` still have no empty-value test.**
`tests/unit/Bindings/ValuesTest.php:136` `test_short_and_relative_date_empty_without_date`
asserts `assertNotSame('', …)` for a *valid* date — it proves the formatter is non-empty, not
that the empty value is `''`. The genuine empty branch is `Bindings\Sources::short_date()`
(`Sources.php:254-262`: no `postId` in context, or an unparsable `post_date`, → `''`) and the
matching lines in `relative_date()`; no test in `tests/integration/Bindings/` exercises it
(grep for empty/missing/without cases: none). Deleting the `if ( ! $post ) return '';` guard
fails nothing today. Fix: `FrontSourcesTest::test_short_date_and_relative_date_empty_without_post`
resolving both sources for a block with no `postId` and for a nonexistent id, asserting `''`;
rename the unit test to what it proves (`…_never_blank_for_a_valid_date`) or drop it. Task R1-13.

**T2 — `tests/unit/BoundariesTest.php:139` only parses `^use TTM\Core\…` lines.** Inline
fully-qualified references (`plugins/ttm-core/src/Editor/Columns.php:64`
`\TTM\Core\Query\SeriesIndex::for_post`, `Cli/Seeder.php:345,346,425,459,633`) are invisible,
so an upward import written inline passes the guard. Today's inline references are all
downward. Fix: also collect `\TTM\Core\<Dir>\` occurrences outside `use` lines and comments.
Task R1-01.

### 4. Performance (SPEC §3.2)

Covered by C2. Nothing else new: `close_comments` now purges once (mutation confirmed),
`posts_in_category()` and the audit attachment index are batched by `cli.batch`.

### 5. Spec drift / low

**S1 — `cssBudgetBytes` moved again.** R1-09 tightened 33000→32700 with a Measurement; R1-12
raised 32700→33200 (`scripts/check-budget.mjs:16`, 33070 used) for 01 §4.13's missing
`.ttm-syndication` rule, also with a Measurement, and CLAUDE.md agrees. Code and constraint are
consistent; SPEC rule 30's 25 KB is the open owner decision (SI-6 below). Not a code finding.

**S2 — Duplicated translator-facing strings.** `plugins/ttm-core/src/Query/Archive.php:191-203`
`format_label()` re-implements `Bindings\Values::pagination_label()` including both `__()`
strings, because R1-01 kept the relabel filters on `Archive` (PLAN R1-01 asked for them to move
to `Bindings\Sources`; the stated reason is that `ArchiveTest` calls `Archive::resolve_label()`).
Two copies of the same string will drift. Low. Task R1-01.

**S3 — Docs drift left by round 1.** `CLAUDE.md:31,35` module map lacks `Meta/SeriesPosition`,
`Editor/SeriesPartList`, and `Blocks/Helpers` as a registered `Plugin` module that owns the
archive-scope hooks; `plugins/ttm-core/blocks/archive-by-year/render.php:5` says grouping happens
in `Query\Archive::group_by_year()` (it is `Blocks\Helpers` now); `Cells.php:59-61` describes the
reverted "CSS drops the dek via `is-stale`" approach; HANDOFF §Round 1 R1-02 bullet (see C1).
R1-15 was the docs-alignment task and predates none of these. Task R1-15.

### 6. Interpretation choices (HANDOFF §Round 1)

Accepted as the reading most consistent with SPEC: R1-01 `Meta\SeriesPosition` reading the
`ttm_series_index` option directly (that is exactly "derived data is read from options"),
`Editor\SeriesPartList` owning the part list, `Blocks\Helpers` as a module; R1-03 setting the
term's `ttm_form` before the assignment loop; R1-04 `date > today` (a stored verse dated today
or earlier is the last good verse) and the self-rescheduling single cron event (`schedule()` on
`init` re-arms the chain if a run ever fails to reschedule); R1-05 removing `cells.thin_days`
(F9's "show what exists" needs no code — spec issue SI-8); R1-06 `$wpdb->update` +
`clean_post_cache()`; R1-09 fixing `masthead-inner`'s site-title level at the root; R1-14
executing the pattern file directly in the test; R1-15 `scripts/lib/report.mjs`.

Not accepted: R1-01 duplicating the pagination-label strings instead of moving the filters (S2);
R1-02's Cells/writing-cell allow-list surviving R1-05 (C1); R1-05's server-side dek omission
being done by the theme at pattern registration with a per-request query (C2, B1); R1-13's
"no empty input exists at this layer" as a reason to skip the source-level empty test (T1).

### 7. Blocked and skipped tasks

None. Nothing to unblock.

### 8. Readability / naming

Covered by S2 and S3. Otherwise the round-1 code reads well: the new tests are hook-driven where
the plan asked (SeriesIndexTest asserts `has_action('shutdown', …)` then calls `maybe_rebuild()`),
and every fix commit names its tests and its interpretation.

## Round-1 findings, verified closed

C1 budget (reconciled, one value, CLAUDE.md matches); C2 rule-24 keys (`sections.technology_slug`,
`cache.cloudflare.timeout_seconds`, `newsletter.timeout_seconds`, `books.blank_rows`,
`books.link_rows`, `seed.quiet_offset_days`, `journal.rail_count` read, `writing.shelf_limit`
read, `writing.plain_count`; residue: C1 above); C3 syndicated-to `<div>` + `wp_kses`; C4 kicker
and meta_line empty tests (residue: T1); B1–B3 all three upward imports gone, guard test added;
T1 hook-driven SeriesIndex tests; T2 `form=fiction`; T3 most-read/tag-filter `expect_non_empty`;
T4 permanent skip removed; T5 F6 test now exercises the stored-verse branch and asserts `Sept`;
P1 one purge per batch; S1 F6 order; S2 `last_update` from parts; S3 Politics posts under
Opinion with primary set; S4 F9 (implemented, but see C1/C2/B1); S5 `series:assign` derives
`chapter`; S6 series current-section; S7 HEAD; S8 `.ttm-hp`; S9 `Sept` + site-local
`published_at`; S10 audit alt/link fixes; S11 landmarks; S12 writing-cell F1/F2; S13 DST cron,
`/category/writing/` h1, `report.html` count; i18n items (masthead names, `Book %d`, form
captions, `%s RSS`); DEPLOYMENT real-IP note; CLAUDE.md rule 16 and module-map file.

## Spec issues

Carried from round 1 and still open for the owner: **SI-1** (rule 15 "JSON" vs typed
`show_in_rest` arrays), **SI-2** (§4.2 "May import" column narrower than the design needs;
`Editor`/`Templates`/`Cache`/`Rest` downward imports), **SI-3** (`sections.technology_slug` now
exists in code; add to §5.4), **SI-4** (`inc/template-hierarchy.php` listed in §4.3 and 04 §1
but not built; CLAUDE.md now dropped it), **SI-5** (rule 12 vs CLI term enumeration), **SI-6**
(rule 30's 25 KB budget vs 33 KB measured; the number should be decided once), **SI-7** (§6.10
"GET" should read "GET/HEAD"; code now does HEAD).

New this round:

- **SI-8** §5.4 lists `cells.thin_days = 90` "F9 no posts in 90 days → still show what exists".
  That branch needs no code (the cell query has no date filter), so R1-05 removed the key;
  recommend removing it from §5.4 and 06 F9's first clause, or stating it is informational.
- **SI-9** §4.2 says `Verse/` may import `Cache/`, but the table order puts `Cache` below
  `Verse`, so a strict "arrow points down" reading (which `BoundariesTest` encodes) forbids it.
  Code uses `do_action('ttm_purge_urls')` and imports nothing, so nothing is broken; recommend
  either moving `Cache` above `Verse` in the table or dropping `Cache` from `Verse`'s column.
- **SI-10** §5.4 has no key for F9's "show the 2 most recent" count; C1 adds `cells.stale_count`.

## Manual checks still owed (copied from HANDOFF.md §2 and §Round 1)

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
