# Review: phase 3 (inner-template fidelity), round 1 fixes

Round: 1

Branch `refine/2026-09-22`, base `main` (`8c2b228`), head `223301c`. 51 tasks, all `[x]`;
0 blocked, 0 skipped. This pass reviews the ten review-fix tasks `R1-01`..`R1-10` commit by
commit against the round-1 `## Review fixes` section of `docs/PLAN.md` and the SPEC sections
they cite. It also re-runs the whole-branch mechanical constraint sweep and every suite. The
P-task commits were reviewed in full in the previous pass. I did not re-audit them line by
line, but the constraint sweep, the rule-36 nesting sweep and the media-query shadow scan below
cover the whole branch.

## Verdict

**CHANGES REQUESTED**: 2 fix tasks.

Categories 1 and 2 (constraints, boundaries) are clean. Category 3 (tests) is not.

- R1-06 restored three SPEC/PLAN margins by editing the shared **base** rules
  `.ttm-series-mark`, `.ttm-series-row__meta` and `.ttm-series-row__dek`. It should have
  edited the `.is-list` layout the task named. The base edits moved the front-page series
  strip, which SPEC §2 forbids, and also changed the category-archive rail and the hub grid-2
  rows. R1-06's own acceptance criterion was "a fidelity row per restored value". Those three
  margins got no row, so the suite stayed green through the regression.
- The committed `docs/feedback/phase-3/*.png` files date from P5-05. They show every screen
  as it looked before R1. Most of the manual checks still owed consist of comparing those
  PNGs with the mocks.

Nine of the ten R1 tasks do what their PLAN entry asks, and I checked each one against the
running site, not only against the log.

### What I verified myself

- **Suites, run by me.** `foundry_verify` passed all six commands:
  - `composer lint`
  - `composer test:unit`: 169/169
  - `npm run lint`: budget 62188/62464, coverage 193/193 with 0 pending, `check-fixme`
    clean
  - `npm run test:unit`: 39 passed
  - `npm run build`
  - `forbidden-patterns`: clean

  I also ran `npm run test:integration` (**481/481, 1932 assertions**) and `npm run test:e2e`
  (**447 passed**, 0 failed/flaky/skipped). These counts match HANDOFF's round-1 claims.
  The active theme was still `ttm-theme` afterwards.
- **Mechanical constraint sweep, whole branch.** Every category below came back clean:
  - theme data APIs under `themes/ttm-theme/`
  - nonces in render/pattern/part/template files
  - per-visitor calls in render/bindings/theme, and `is_user_logged_in` outside
    `Cache/Headers.php`
  - clock reads outside `Support/Clock.php`
  - unbounded queries
  - dangerous PHP (`eval`, `unserialize`, `extract`, `curl_`, `file_get_contents('http'` and
    the rest of the list)
  - `wp_safe_remote_*` outside the three permitted files
  - front-end network calls in theme JS
  - `<style` and non-exempt `style="` in the plugin
  - hex literals in `ttm.css`, and `prefers-color-scheme`
  - rule 45 (`210, 48, 19` is absent) and rule 39 (no lorem)
  - `dependencies: {}` and `TTM_CORE_API === 1`
  - no `test.fixme(` / `test.skip|only|fail(` in the e2e suites
  - `css-coverage-allow.txt` at 0 bytes, and no `pending` line in `selectors-allow.txt`

  `ttm.css` is 62188 of 62464 bytes.
- **Rule 36 nesting sweep.** I ran a nesting-aware parse of every template, part and pattern
  block comment. It found zero `layout: constrained` groups under any `is-style-grid-*` or
  `layout: grid` ancestor, and zero grid groups using `constrained` or `flow`. R1-02 is
  complete.
- **Media-query shadow scan.** A postcss pass over `ttm.css` checked every `@media` rule
  against every later rule for the same selector outside a max-width media query, including
  shorthand/longhand families. It found zero hits. R1-03 is complete, and no other instance of
  the P5-01 hazard remains.
- **Rendered-output checks against the running seeded site**, using Playwright computed
  styles:
  - `/?s=ledger` rows are `<a>` grid rows, and the plain-text kicker sits in `grid-row: 1`
    (R1-01).
  - `.ttm-footer__copyright` renders on every screen because a verse is seeded, so R1-10's
    new `margin: 0` rule is live and not dead.
  - The series-row margins in finding 1.
- **Mutation sampling**, restored each time with `git checkout --` or a backup, with a clean
  tree confirmed afterwards:
  - *JS/scripts*: `filterSrcFiles()` returning `files` unfiltered made
    `check-css-coverage.test.js` "filterSrcFiles (R1-10)" **fail**. The guard is real.
  - *PHP unit*: replacing R1-08's `is-style-dek-l` guard in `Helpers::excerpt_markup()` with
    `if ( false )` made
    `HelpersTest::test_excerpt_markup_is_a_no_op_outside_the_article_header_dek` **fail**.
    The guard is real.
  - *Integration*: forcing `Sources::short_date()`'s `noYear` branch off made
    `ArchiveTemplatesTest::test_archive_row_date_omits_year_but_journal_stream_keeps_it`
    **fail** (the `Nov 26` regex). The guard is real.
  - *E2E*: I did not mutate here, because R1-09's commit body records a mutation per
    tightened row. For the one e2e hole I found, finding 1 shows by live measurement that
    the suite passes with the regression in place.

---

## Findings

Most severe first.

### 1. [Tests / spec drift] R1-06 moved the front-page strip, the archive rail and the hub grid by editing shared base rules (R2-01, owner R1-06)

`themes/ttm-theme/assets/css/ttm.css`:

| Line | Rule | Before R1-06 | After |
|---|---|---|---|
| `:1611-1616` | `.ttm-series-mark` | `margin-top: 5px` | `6px` |
| `:1682-1688` | `.ttm-series-row__meta` | `margin-top: var(--wp--preset--spacing--10)` (4px) | `6px` |
| `:1715-1722` | `.ttm-series-row__dek` | `margin-top: 5px` | `4px` |

All three are unscoped base rules shared by every `ttm/series-list` layout. The task named
the `list` layout: PLAN P4-05 gives the `.is-list` rows mark 6, dek 4 and meta 6. R1-06's own
PLAN text calls them "list-row" margins. The implementer changed the base values instead of
adding `.ttm-series-list.is-list …` overrides.

I measured the result on the running seeded site at 1280:

| Screen / layout | Element | Spec | Rendered | Source |
|---|---|---|---|---|
| `/` strip (also the 404 strip) | mark margin-top | 5px | **6px** | phase-2 SPEC §6.1.7 |
| `/` strip | meta margin-top | 4px | **6px** | phase-2 SPEC §6.1.7 |
| `/category/security/` rail | mark margin-top | 5px | **6px** | PLAN P3-04 |
| `/series/` grid-2 | dek margin-top | 5px | **4px** | SPEC §6.7, PLAN P4-02 |
| `/writing/` list | mark / dek / meta | 6 / 4 / 6 | 6 / 4 / 6 (correct) | PLAN P4-05 |

**What breaks:** SPEC §2 lists as a non-goal "Front-page changes beyond what shared chrome
forces … must not move a single front-page fidelity row". This change moves front-page
elements on a task that was scoped to the Writing page's list layout. The 404 strip, the
category rail and the hub grid-2 dek move with it.

**Why the suite is green:** R1-06's acceptance criterion reads "A fidelity row per restored
value, since the absence of one is exactly why each was droppable". The list-row mark, dek
and meta margins got no row. `strip-mark`, `strip-meta`, `ar-aside-row` and `hub-grid-row`
do not read margins either.

**Minimal fix:** restore the base values: mark 5px, meta `var(--wp--preset--spacing--10)`,
dek 5px. Then add `.ttm-series-list.is-list .ttm-series-mark { margin-top: 6px }`,
`.ttm-series-list.is-list .ttm-series-row__meta { margin-top: 6px }` and
`.ttm-series-list.is-list .ttm-series-row__dek { margin-top: 4px }` under `/* 4.17 series row */`,
after the base rules. Add margin assertions to `strip-mark`, `strip-meta`, `ar-aside-row`,
`hub-grid-row` or a new `hub-grid-dek`, plus a new `wr-serial-row-margins`.

### 2. [Manual-check integrity] Committed screenshots predate every R1 fix (R2-02, owner R1-01..R1-08)

`docs/feedback/phase-3/*.png` was last written by `ebf010f` (P5-05). Several round-1 tasks
changed what those screens render:

- R1-01: search rows
- R1-04: archive dates and the serial meta line
- R1-06: hero spacing, chapter rows, and the regression in finding 1
- R1-07: two books instead of three on the Writing page
- R1-08: the newsletter box copy size

HANDOFF states that screenshots were "not re-run as part of this round". R1-04, R1-06 and R1-07
each regenerated screenshots locally and then threw them away. As a result:

- `writing.png` still shows the removed third book.
- `archive-security.png` still shows "Nov 26, 2025" wrapping in the 72px column.
- `search.png` still shows the old post-terms kicker.

Manual checks 4 to 7 below ask a human to compare exactly these PNGs with the mocks, so today
they would validate superseded output. SPEC §1 "Done" and §8 require the PNGs to be committed
per phase. After a review round the final set has to reflect the final code.

**Minimal fix:** after R2-01 lands, run `npm run screenshots` and commit all 14 PNGs. The
fix task names the check that would have caught this.

---

## Interpretation choices (HANDOFF round 1)

- **R1-08, scoping by `is-style-dek-l` className rather than `is_singular()`.** Agreed. It is
  the narrower guard. `article-header.php:20` is the only `post-excerpt` carrying that class,
  and the filter is registered with 3 args, so `$block` arrives.
- **R1-09, `toBeGreaterThanOrEqual` in `aside-phone-order`.** Agreed. Flush stacking is
  correct markup, and overlap still fails.
- **R1-10, `UNSTYLED_WRAPPERS` in `check-css-coverage.mjs`.** This is the reading the round-1
  task itself prescribed ("via the scanner's wrapper handling, not a decorative rule"), but it
  is not what SPEC says. See Spec issue 1. I have not queued a fix task, because any fix needs
  an owner ruling first.
- **R1-02, applying rule 36 to the `layout: grid` `.ttm-stats` group in `stat-row.php`.**
  Agreed. It is the same hazard.

## Blocked and skipped tasks

None.

---

## Spec issues

1. **Rule 34 has no sanctioned home for identity-only block wrappers.** Phase-2 rule 34 says
   `Helpers::wrapper('x')` counts as `ttm-x`, and that intentionally unstyled hooks go in
   `scripts/css-coverage-allow.txt`. The phase-3 amendment requires that file to be empty
   at flight end. Together those force every block wrapper to carry a CSS rule. For
   `ttm-archive` and `ttm-most-read`, which need no styling, the only options are:
   - a decorative no-op rule, which round 1 rejected;
   - a second, unlisted allow-list inside the script, which R1-10 shipped as
     `UNSTYLED_WRAPPERS` at `scripts/check-css-coverage.mjs:97`.

   The second option is a deviation from rule 34's letter: two emitted classes have no
   selector and the lint does not fail. The owner should choose one of these:
   - amend rule 34 to exempt `data-ttm-block` wrapper classes by rule, not by a hand list;
   - permit permanent `# hook` lines in the allow-list;
   - require a real rule.

   I am recording this and not approving it.
2. Carried forward from the previous review and still open, because each needs an owner
   decision:
   - SPEC §6.1.0 "one or the other" was resolved a third way (root padding kept and
     neutralised).
   - The `# state:` reason category in `selectors-allow.txt`.
   - §6.9 rows that cannot fail on SPEC's own wording (`wr-serial-form`'s `/i`).
   - Hub, TOC, chapter and 404 part-number zero-padding (mock 1f is templated).
   - `ValuesTest`'s impossible `month && year` archive-kind state.

## Manual checks still owed

Copied from `docs/HANDOFF.md`, all still `NOT VERIFIED (human)`. Checks 1–7 must wait for
R2-02's regenerated PNGs.

1. (P0-12) `docs/feedback/phase-3/article.png` masthead vs the top of
   `docs/feedback/design_article.png`: every inner page a centred 1280 column, inline nav, no
   "Close" button.
2. (P1-07) `article.png` vs `design_article.png`, and `article-390.png` vs mock `3b`.
3. (P2-04) `journal.png` vs `design_journal.png`.
4. (P3-06) `archive-security.png` vs mock `1e`; `search.png` and `404.png` vs `02 §H`.
5. (P4-07) `series-hub.png` vs mock `1f`; `writing.png` vs `design_serial.png`;
   `series-single.png` vs `02 §F`.
6. (P5-01 / P5-05) Open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and
   `/category/security/` at 390 in a real phone browser and compare with mock `3b` and the
   `02` Responsive bullets.
7. (P5-05) Final comparison of every `docs/feedback/phase-3/*.png` against its paired mock,
   plus CI green on the branch including the `e2e` job and its `playwright-report` artifact.
8. (Round 1) Search results, prior-year archive rows and the Writing serial meta line against
   their mocks (R1-01, R1-04); exactly two books in "In print" (R1-07); newsletter box copy
   visibly 13px (R1-08).
9. (Previous review) `series-single.png`: the per-part date sits on the dek's baseline, not
   the title's. Confirm against `02 §F`.
10. (Previous review) Hub part numbering `01`–`06`: owner ruling (Spec issue 2).
