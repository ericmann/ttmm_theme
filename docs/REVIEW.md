# Review: phase 3 (inner-template fidelity), round 4 fixes

Round: 4

Branch `refine/2026-09-22`, base `main` (`8c2b228`), head `c17bc47`. 59 tasks, all `[x]`;
0 blocked, 0 skipped. This pass reviews the three round-4 fix tasks (`R4-01`, `R4-02`, `R4-03`)
commit by commit against `## Review fixes (round 4)` in `docs/PLAN.md`. It re-runs every suite and
the whole-branch constraint sweep, probes the running seeded site, and checks the regenerated
`writing.png` and `series-hub.png` against the round-3 findings. Rounds 1 to 3 reviewed the earlier
phase and fix commits; their verdicts and mutation evidence stand, and nothing in round 4 touches
the code they covered.

## Verdict

**APPROVED**

Categories 1 to 3 (constraints, boundaries, tests) are clean across the branch, and no task is
blocked or skipped. Each round-3 finding is fixed as PLAN specified:

- The series-row count cell now sits level with the title.
- The Writing columns stretch their children.
- A complete series reads "9 chapters" / "24 chapters" / "N parts".
- The empty-chapters branch now has a test.
- The readability leftovers are cleared.

Each fix names a test that bites. What remains below is two readability observations and
owner-level spec issues. None of them blocks approval.

### What I verified myself

- **Suites, run by me.** `foundry_verify` passed all eight commands, run with the files round 4
  touched:
  - `composer lint`: warnings only, in pre-existing test files
  - `composer test:unit`: 171/171
  - `npm run lint`: budget 62568/63488, coverage 193/193 with 0 pending, fixme clean
  - `npm run test:unit`: 39 passed, 2 skipped (pre-existing spike skip)
  - `npm run build`
  - `forbidden-patterns`: clean
  - `npm run test:integration`: 490/490, 2053 assertions
  - `npm run test:e2e`: 452 passed

  The active theme was `ttm-theme` before and after. No phpunit process was left in `tests-cli`.
- **Constraint sweep, whole branch.** Every check below is clean:
  - no hex literals and no `prefers-color-scheme` in `ttm.css`
  - `210, 48, 19` absent from `Seeder.php`; no lorem in `docs/fixtures/seed/`
  - `css-coverage-allow.txt` is 0 bytes, and `selectors-allow.txt` has no `pending` line
  - no `test.fixme(`, `.only(` or `test.skip(` in the e2e specs
  - `dependencies: {}`, and `TTM_CORE_API` is 1
  - nothing under `docs/phase-1/` or `docs/phase-2/` touched; `FOUNDRY_FEEDBACK.md` is untracked
    and untouched; `stash@{0}` is untouched
  - `grep ALLOWED_FORMS Seeder.php` is empty
  - the screenshot ancestry check (`git merge-base --is-ancestor …`) exits 0

  Round 4 adds no nonce, no per-visitor call, no clock call and no forbidden function. Its only
  new numeric literals are the structural `grid-row: 1 / span 3` in CSS. The two new strings use
  `_n()` with the `ttm-core` text domain and translators comments. `Seeder`'s new
  `use TTM\Core\Meta\PostMeta` passes `BoundariesTest`, which exempts `Cli`.
- **Rule 36, extended (the amended CLAUDE.md line).** I parsed every block comment in the 12 theme
  files that contain `is-style-grid`. No group anywhere under a grid is `flex` or `constrained`.
  The only flex group in the theme, `patterns/section-cell-large.php`'s `.ttm-cell-heading`, is
  front-page chrome from phase 2 and is not a column child.
- **R4-01 mutation.** The PLAN-mandated mutation (editing `ttm.css` and re-running the rows
  against the shared wp-env) was denied by the environment's permission policy, so I restored both
  files straight away with `git checkout` and mutated in the browser instead. A Playwright route
  stripped `grid-row: 1 / span 3` from the served `ttm.css`, touching no shared file. Count-top
  minus title-top at 1280:

  | Screen | Mutated | Committed code |
  |---|---|---|
  | `/writing/` | 88, 68, 68 | 0, 0, 0 |
  | `/series/` | 102, 109, 23, 23, 69, 69 | all 0 |
  | `/series/hardening-wordpress/` | 88, 23, 23, 68 | all 0 |

  The ≤1px assertions in `wr-serial-count`, `hub-grid-row` and `single-other` would all fail
  without the declaration. Round 3's own measurements of the old `flex` template (466 and 535px
  lists in a 653px column) are the pre-fix evidence for `wr-body-cols`.
- **R4-01 probe.** At 1280:
  - `.ttm-writing-body > main` and `> aside` are `display: flex` with 36 and 32px gaps.
  - All four children are 653 and 467px wide, with 0 margins, so core's `is-layout-flow` margins
    don't double the gap.
  - At 1000 and 390, both columns compute `display: contents`, and the stacked order is serials,
    stories, chapters, books, as before.
- **R4-02 probe.** `curl /writing/` shows the right cells "12 of 31", "9 chapters" and
  "24 chapters". The strip/rail `$ttm_count_word` path is unchanged, and
  `test_strip_meta_count_unchanged_for_complete_series` pins it.
- **R4-02 and R4-03 tests, by reading the code.** A live mutation was not possible under the same
  permission denial, so I traced the code instead.
  - `test_chapters_variant_with_no_published_parts_renders_nothing` reaches the new guard, not an
    earlier `return ''`:
    - `SeriesIndex::collect_parts()` indexes `future` posts, so `for_post()` finds the row and the
      `! $ttm_row` guard does not fire.
    - `total_parts = 1`, so the series is not open-ended.
    - The chapters filter empties `$ttm_rows`.

    Without the guard, the block would emit the wrapper, the heading and an empty `<ol>`, and the
    `assertSame( '', … )` would fail.
  - The two R4-02 tests assert "9 chapters" / "4 parts" and the absence of "9 of 9" / "4 of 4".
    The pre-fix code returned exactly those strings.
- **Screenshots.** `writing.png` and `series-hub.png` (from R4-03) show the count cell level with
  the title, full-width Writing lists, "9 chapters" / "24 chapters", and the four stories in mock
  order.

---

## Findings

None in categories 1 to 7.

### Readability (category 8; not queued)

- **O1.** `plugins/ttm-core/blocks/series-list/render.php:156` adds a new local list,
  `$ttm_fiction_forms = [ 'novel', 'novella', 'story-cycle' ]`. Elsewhere the codebase expresses
  "fiction" as `'nonfiction' !== $row['form']`:
  - `Fiction/Serials.php:206`
  - `Cache/Purge.php:79`
  - `Rest/SeriesController.php:86`
  - this same file at :70 and :198

  Today the two readings agree, because `Taxonomy\Series::FORMS` is exactly nonfiction plus those
  three. A future fiction form would silently read "N parts" here and nowhere else. The fix is
  a one-line change to the house idiom whenever this file is next touched.
- **O2.** Following its round-4 interpretation, `grid-3` still renders "N of N" for a complete
  series. SPEC names no `grid-3` screen and no template uses it, so this is consistent with PLAN's
  scope. If an owner ever places a `grid-3` series list, it should share the list/grid-2 wording.

### Carried observations (owner calls, unchanged)

- Round 3's O1 (seeded River word count and "~12 min" versus mock `2d`'s 4,400 and "~14 min").
- Round 2's O1 and O2 (`Stats::top_tags()` has no tiebreak; spacing above the 404 H1).
- `series-hub.png` shows "4 of 6 published · next part Sept 27". The seed deliberately publishes
  Hardening part 4 at `days_ago: 0`, so the hub reads one part ahead of SPEC §6.7's illustrative
  "3 of 6 … Sept 26", and the dates move with the day the seed runs. This dates from before this
  round and no §6.9 row asserts it.

## Interpretation choices (HANDOFF round 4)

- **R4-01**, `grid-row: 1 / span 3` rather than `grid-row: 1`: agreed. The span covers the
  title/dek/meta rows. `align-items: start` keeps the cell at its content height, and the probe
  shows no row grows.
- **R4-02**, the new wording scoped to `list`/`grid-2` only: agreed, per PLAN's literal scope
  (see O2).
- **R4-03**, the docblock reworded to cite `Meta\PostMeta::FORMS` / `Meta\Form::derive()`:
  agreed.

## Blocked and skipped tasks

None.

---

## Spec issues

Carried forward, each still needing an owner ruling:

1. Rule 34 has no sanctioned home for identity-only block wrappers (the `UNSTYLED_WRAPPERS`
   list in `scripts/check-css-coverage.mjs`).
2. SPEC §6.1.0 "one or the other" was resolved a third way (root padding kept and neutralised).
3. The `# state:` reason category in `selectors-allow.txt`.
4. §6.9 rows that cannot fail on SPEC's own wording (`wr-serial-form`'s `/i`).
5. Hub, TOC, chapter and 404 part-number zero-padding (mock `1f` is templated).
6. `ValuesTest`'s impossible `month && year` archive-kind state.
7. §6.9 asserts only a subset of each component's SPEC prose. Rounds 2 to 4 each found prose or
   mock values that no row read. Each fix added the missing assertion, but the table itself should
   grow to cover positions and computed widths, not just type and colour.

Round 3's spec issue 8 is resolved: R4-01 amended CLAUDE.md's rule-36 line to quote SPEC §3.1
(grid column children are `default`/`flow`, never `flex` or `constrained`).

## Manual checks still owed

Copied from `docs/HANDOFF.md`, all `NOT VERIFIED (human)`:

1. (P0-12) `article.png` masthead against the top of `design_article.png`: a centred 1280
   column, inline nav, no "Close".
2. (P1-07) `article.png` against `design_article.png`; `article-390.png` against mock `3b`.
3. (P2-04) `journal.png` against `design_journal.png`.
4. (P3-06) `archive-security.png` against mock `1e`; `search.png` and `404.png` against `02 §H`.
5. (P4-07) `series-hub.png` against mock `1f`; `writing.png` against `design_serial.png`;
   `series-single.png` against `02 §F`.
6. (P5-01/P5-05) Open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and
   `/category/security/` at 390 on a real phone and compare with `3b` and the `02` Responsive
   bullets.
7. (P5-05) Compare every `docs/feedback/phase-3/*.png` with its paired mock. Confirm CI is green,
   including the `e2e` job and its `playwright-report` artifact.
8. (Round 1) Check against their mocks:
   - search rows
   - prior-year archive rows
   - the Writing serial meta line
   - exactly two books in "In print"
   - the newsletter box copy at 13px
9. (Round 2) Check the front page, `/writing/`, a category archive and `/series/` against their
   mocks, with the strip, rail and grid-2 margins restored.
10. (Round 3) Check `/writing/` against mock `2d`: the four stories in order, and no scheduled
    chapter at the top of "recent chapters".
11. (Round 3) On `/signing-your-options-table/` and `/category/security/`, `wp-config.php` reads
    without literal backticks.
12. (Round 4) Open `/writing/`, `/series/` and `/series/hardening-wordpress/` at 1280. The
    count/status column should sit level with the series title. Complete series should read
    "9 chapters", "24 chapters" or "N parts".
13. (Round 4) Open `/writing/` at 1280, 1000 and 390. At 1280, the "All serials" and "Recent
    chapters" lists should fill their column. At 1000 and 390, the stacked order should be
    unchanged.
14. (Previous reviews) In `series-single.png`, check that the per-part date sits on the dek's
    baseline, against `02 §F`. The hub's `01`–`06` part numbering needs an owner ruling (spec
    issue 5).
15. `git stash list` shows `stash@{0}: On poc: temp: stash foundry feedback notes before run
    start`. This is the owner's stash; leave it for the owner.
