# Review: phase 3 (inner-template fidelity), round 3 fixes

Round: 3

Branch `refine/2026-09-22`, base `main` (`8c2b228`), head `47531d9`. 56 tasks, all `[x]`;
0 blocked, 0 skipped. This pass reviews the three round-3 review-fix tasks (`R3-01`, `R3-02`,
`R3-03`) commit by commit against `## Review fixes (round 3)` in `docs/PLAN.md`. It re-runs every
suite and the whole-branch constraint sweep, then compares the regenerated `writing.png` and
`series-hub.png` with mock `2d` / `1f` and probes the running seeded site to confirm what they
show.

## Verdict

**CHANGES REQUESTED**: 3 fix tasks.

All three round-3 tasks do what PLAN asks. Their tests exist and bite: I proved it by mutation
(details below). Categories 1–2 (constraints, boundaries) are clean across the branch.

Three things block approval:

- **Category 3 (tests).** R3-02 added a new server-side branch with no test: the chapters variant
  returns `''` when no chapter is published. You can delete that branch and every suite still
  passes.
- **Category 5 (spec drift).** This was visible for the first time in the regenerated
  `writing.png`, now that the content is right. The series-row right cell sits at the bottom of
  every `list`/`grid-2` row. The mocks put it at the top.
- **Category 5 (spec drift).** Complete series read "9 of 9" where SPEC §6.5 says "9 chapters".
  The Writing page's left column also renders its two lists narrower than the column, because a
  core flex group sits inside the grid.

None of the §6.9 rows checks these, which is why the suite is green. Each fix task names the row
or test that would have caught its finding.

### What I verified myself

- **Suites, run by me.** `foundry_verify` passed all eight commands, run with every file round 3
  touched:
  - `composer lint`
  - `composer test:unit`: 171/171
  - `npm run lint`: budget 62463/62464, coverage 193/193 with 0 pending, fixme clean
  - `npm run test:unit`: 39 passed
  - `npm run build`
  - `forbidden-patterns`: clean
  - `npm run test:integration`: 484/484, 2039 assertions
  - `npm run test:e2e`: 451 passed

  The active theme was `ttm-theme` before and after. No phpunit process was left in
  `tests-cli`.
- **Constraint sweep, whole branch.** Every check below is clean:
  - no hex literals and no `prefers-color-scheme` in `ttm.css`
  - `210, 48, 19` absent from the Seeder; no lorem in the seed fixtures
  - `css-coverage-allow.txt` is 0 bytes, and `selectors-allow.txt` has no `pending` line
  - no `test.fixme(`, `.only(` or `test.skip(` in the e2e specs
  - `dependencies: {}`, and `TTM_CORE_API` is 1
  - nothing under `docs/phase-1/` or `docs/phase-2/` touched; `FOUNDRY_FEEDBACK.md` is not
    tracked; `stash@{0}` untouched
  - no backticks left in `posts.json`
  - the R3-03 ancestry check exits 0

  Round 3 adds no nonces, no per-visitor calls, no clock calls and no forbidden functions.
  `Seeder` already imported `Meta\Form`, so the new code crosses no new boundary.
- **R3-01 mutation.** I restored `ttm.css` to `986c49d^` and ran the twelve extended rows. All
  12 **failed**:

  | Row | Expected | Received |
  |---|---|---|
  | `art-pre` | 22 | 0 |
  | `art-pull` | 36 | 32 |
  | `js-link` | 12 | 11 |
  | `wr-stats` | 13 | 12 |
  | `wr-chapter-dek` | 400 | 600 |
  | `wr-chapter-date` | 400 | 600 |
  | `wr-tiles` | 16 | 0 |
  | `wr-books` | 16 | 0 |
  | `wr-book-title` | 2 | 12 |
  | `hub-meta` | 0 | 12 |
  | `hub-buttons` | 10 | 12 |
  | `hub-all-head` | 12 | 11 |

  Restored the file.
- **R3-01 probe.** Computed styles at 1280 on the seeded site:
  - The gap between the first `pre` and the next `p` is 22px.
  - The pull quote's margins are 36/36px and its letter-spacing is −0.42px.
  - At 390, the phone `pre` keeps its −20px inline margins and still has a 22px bottom margin.
  - `.ttm-series-featured__buttons` gap is 10px on both `/series/` and
    `/series/hardening-wordpress/`.
  - The front page carries no `.ttm-stats` and no `.ttm-story-tiles`, so no phase-2 row can
    move.
- **R3-02 mutation.** I removed `|| $ttm_is_chapters`:
  - `SeriesTocTest::test_chapters_variant_excludes_scheduled_parts` **failed**.
  - `wr-chapter-first` **failed** ("A" ≠ "SPAN").

  Restored. On the live site, `/writing/` now lists chapters 12, 11, 10 and 09 beside "All 12".
- **R3-03 mutation.**
  - I disabled the `form` override in `Seeder`. The updated essays test and
    `test_writing_short_fiction_is_the_four_spec_stories_in_mock_order` **failed**.
  - I set River's `days_ago` back to 260 and restored the backticks. The four-story test and
    `test_no_seeded_excerpt_contains_a_backtick` **failed**.

  Both restored. The live `/writing/` tiles are The Last Cron Job, A Field Guide to Empty
  Offices, Uptime (image tile) and What the River Audits.
- **Screenshots.** I ran `npm run screenshots` again against the current code. All 14 PNGs came
  out byte-identical to HEAD.

---

## Findings

Most severe first.

### 1. [Tests] The new empty-chapters branch has no test (fix task 3)

`plugins/ttm-core/blocks/series-toc/render.php:66-68`. R3-02 made
`if ( $ttm_is_chapters && ! $ttm_rows ) return '';` reachable. That happens when the active serial
has no published chapter yet, for example a new serial whose chapter 1 is scheduled.

HANDOFF lists this branch as an interpretation choice, and the choice itself is right: 06's
governing rule and rules 25 and 46 forbid empty wrappers. But no test exercises it. Deleting the
three lines leaves every suite green, and the block would then render a heading plus an empty
`<ol>`.

- **Rule:** CLAUDE.md "every `ttm/*` block … empty → `''`, a test in `tests/integration/Blocks/`".
- **Minimal fix:** add `SeriesTocTest::test_chapters_variant_with_no_published_parts_renders_nothing`.

### 2. [Spec drift] The series-row count/status cell sits at the bottom of the row (fix task 1)

`themes/ttm-theme/assets/css/ttm.css:1759`. `.ttm-series-row__count` sets `grid-column: 3` but no
row. Its row also contains a title, dek and meta/categories as separate grid children, so
auto-placement puts the count in the **last** row.

Measured at 1280, the count's top sits this far below the title's top:

- `/writing/` `list`: 88px and 68px
- `/series/` `grid-2`: 102, 109, 23, 23, 69 and 69px
- `/series/hardening-wordpress/` "Other series": 88, 23, 23 and 68px

In mocks `2d` (line 494) and `1f` (line 1015), the count is the row's third child in
`align-items:start`, so it sits top-right, level with the title. SPEC §6.5 and §6.7 say
"align start … right cell count over status". `writing.png` and `series-hub.png` show the
difference plainly.

- **Why green:** `wr-serial-count` and `hub-grid-row` check text-align, text and tracks, never
  position.
- **Minimal fix:** give the count cell `grid-row: 1 / span 3` (or equivalent) so it starts in the
  title's row. CSS only.

### 3. [Spec drift] The Writing left-column lists don't fill the 7fr column (fix task 1)

`themes/ttm-theme/templates/page-writing.html:11,39`. `main` and `aside` are core
`layout: {type: flex, orientation: vertical}` groups. Core emits `align-items: flex-start` for
these, so each child shrinks to its content width. Measured at 1280, inside a 653px `main`:

- the All-serials list is 466px wide (48..514)
- the recent-chapters list is 535px wide (48..583)

Mock `2d` line 486 is a plain `display:flex;flex-direction:column;gap:36px` column, which
stretches its children. The mock's right cells line up at the column edge (x≈703). In the build
the two lists end at different x positions, and the count cells drift inward.

This predates the flight (`4e7ac83`, P6-05). SPEC §3.1 extended rule 36 says "column children
are `layout: default` (or `flow`) and `ttm.css` owns their width", so the flight should have
converted these groups. The CLAUDE.md constraint line only names `constrained`, which is why the
sweep never caught it.

- **Why green:** no row measures child width against the column.
- **Minimal fix:**
  - Make both groups `layout: default`.
  - In `ttm.css`, give `.ttm-writing-body > main` and `.ttm-writing-body > aside`
    `display: flex; flex-direction: column` with the existing gaps; children stretch by default.
  - Keep the ≤1024 `display: contents` fold, which comes later in source order and still wins.

### 4. [Spec drift] Complete series show "N of N" instead of "N chapters" / "N parts" (fix task 2)

`plugins/ttm-core/blocks/series-list/render.php:129-141`. Whenever `ttm_total_parts > 0`, the
right cell's `__parts` text is "%1$d of %2$d". So Failover reads "9 of 9" and Salt Water Wires
reads "24 of 24" on `/writing/`, `/series/` and the single-series "Other series".

- SPEC §6.5 says the right cell is `"12 of 31" / "9 chapters" over status`.
- Mock `2d` lines 1195–1196 show "9 chapters" and "24 chapters".
- Mock `1f` lines 1131–1134 show complete nonfiction series as "4 parts", "5 parts", and so on.

- **Why green:** `wr-serial-count` checks only the first row (in progress) against
  `/^\d+ of \d+/`.
- **Minimal fix:** in the `list`/`grid-2` right cell only, a complete series renders
  "{published} chapters" for fiction forms and "{published} parts" for nonfiction, pluralised
  with `_n`. Leave the `strip`/`rail` meta string (`$ttm_count_word`) alone, because the front
  page's strip must not move (SPEC §2).

### 5. [Readability] Round-3 leftovers (fix task 3)

- `tests/integration/Cli/SeederTest.php:312-324`: two stacked docblocks. The first (F2, R1-02)
  now describes the opposite of what the test asserts. The method is still named
  `…_derive_as_story_…` although it now asserts `article` + locked.
- `plugins/ttm-core/src/Cli/Seeder.php:23-27`: `ALLOWED_FORMS` duplicates
  `Meta\PostMeta::FORMS`, and its docblock cites "SPEC §5.2", which does not exist in this SPEC.
  Use `PostMeta::FORMS` so the two lists cannot drift apart.
- `plugins/ttm-core/blocks/series-toc/render.php`, chapters branch: the unpublished `<span>`
  title path (`else` at ~:134) can no longer run now that chapters are filtered to published.
- `docs/HANDOFF.md:114` still says Field Guide has `days_ago: 260`; the fixture says 410. The
  round-3 Measurements note (:508) credits the earlier flag to River.

---

## Observations (not queued)

- **O1.** Seeded story word counts beyond SPEC §6.10's two named ones differ from mock `2d`.
  What the River Audits shows "725 words · 2023"; the mock has 4,400. Uptime is an image tile,
  so it shows no meta. The Writing stat shows "~12 min" (HANDOFF: `avg_minutes` 12), where §6.5's
  example and the mock say "~14 min". SPEC §6.10 does not state these numbers, so the owner can
  decide whether the seed should match the mock's copy exactly.
- **O2.** Round 2's O1 (`Stats::top_tags()` has no tiebreak) and O2 (404 H1 top spacing) are
  still open and still owner calls.

## Interpretation choices (HANDOFF round 3)

- **R3-01**, closing the budget gap by shortening comments rather than raising `cssBudgetBytes`:
  agreed. PLAN allowed it, and I read every trimmed comment; none lost information. Fix task 1
  will need the raise, because only 1 byte of headroom is left.
- **R3-02**, the empty-chapters `return ''`: agreed on the behaviour. The missing test is
  finding 1.
- **R3-03**, the pure `Seeder::normalize_form()` with a unit test, and `days_ago` 700/1090:
  agreed. Both land well inside their target years. Reuse `PostMeta::FORMS` (finding 5).

## Blocked and skipped tasks

None.

---

## Spec issues

Carried forward, each still needing an owner ruling:

1. Rule 34 has no sanctioned home for identity-only block wrappers (the `UNSTYLED_WRAPPERS`
   list in `scripts/check-css-coverage.mjs`).
2. SPEC §6.1.0 "one or the other" was resolved a third way (root padding kept and
   neutralised).
3. The `# state:` reason category in `selectors-allow.txt`.
4. §6.9 rows that cannot fail on SPEC's own wording (`wr-serial-form`'s `/i`).
5. Hub, TOC, chapter and 404 part-number zero-padding (mock 1f is templated).
6. `ValuesTest`'s impossible `month && year` archive-kind state.
7. §6.9 asserts a subset of each component's SPEC prose. This round's findings 2–4 are again
   prose/mock values that no row read.
8. New: CLAUDE.md's rule-36 constraint line forbids only `constrained` groups inside grids.
   SPEC §3.1 also limits column children to `default`/`flow`. The constraint line should quote
   SPEC so the next sweep catches `flex` column groups (finding 3).

## Manual checks still owed

Copied from `docs/HANDOFF.md`, all `NOT VERIFIED (human)`. `writing.png`, `writing-390.png`,
`series-hub.png` and `series-single.png` will change again after the fix tasks and must be
regenerated.

1. (P0-12) `article.png` masthead vs the top of `design_article.png`: a centred 1280 column,
   inline nav, no "Close".
2. (P1-07) `article.png` vs `design_article.png`; `article-390.png` vs mock `3b`.
3. (P2-04) `journal.png` vs `design_journal.png`.
4. (P3-06) `archive-security.png` vs mock `1e`; `search.png` and `404.png` vs `02 §H`.
5. (P4-07) `series-hub.png` vs mock `1f`; `writing.png` vs `design_serial.png`;
   `series-single.png` vs `02 §F`.
6. (P5-01/P5-05) Open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and
   `/category/security/` at 390 on a real phone and compare with `3b` and the `02` Responsive
   bullets.
7. (P5-05) Compare every `docs/feedback/phase-3/*.png` with its paired mock, and confirm CI is
   green, including the `e2e` job and its `playwright-report` artifact.
8. (Round 1) Search rows, prior-year archive rows and the Writing serial meta line against their
   mocks; exactly two books in "In print"; newsletter box copy at 13px.
9. (Round 2) The front page, `/writing/`, a category archive and `/series/` against their mocks
   now that the strip, rail and grid-2 margins are restored.
10. (Round 3) `/writing/` against mock `2d`: the four stories in order, and no scheduled chapter
    at the top of "recent chapters".
11. (Round 3) `/signing-your-options-table/` and `/category/security/`: `wp-config.php` reads
    without literal backticks.
12. (Previous review) `series-single.png`: the per-part date sits on the dek's baseline; confirm
    against `02 §F`.
13. (Previous review) Hub part numbering `01`–`06`: owner ruling (Spec issue 5).
14. `git stash list` shows `stash@{0}: On poc: temp: stash foundry feedback notes before run
    start`. This is the owner's stash; leave it for the owner.
