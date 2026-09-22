# Review: phase 3 (inner-template fidelity), round 2 fixes

Round: 2

Branch `refine/2026-09-22`, base `main` (`8c2b228`), head `d0d3a3f`. 53 tasks, all `[x]`;
0 blocked, 0 skipped. This pass reviews the two round-2 review-fix tasks (`R2-01`, `R2-02`)
commit by commit against `## Review fixes (round 2)` in `docs/PLAN.md`. It re-runs every
suite and the whole-branch constraint sweep. It also adds something the earlier passes did
not do: a computed-style sweep of the SPEC §6.2–§6.7 values that no §6.9 row asserts,
checked against the running seeded site and the mock cards.

## Verdict

**CHANGES REQUESTED**: 3 fix tasks.

Categories 1–3 (constraints, boundaries, tests) are clean for R2-01 and R2-02. Both tasks
do what PLAN asks, and I proved the new rows bite (details below).

I am still not approving. The new sweep found **category 5 (spec drift)** defects on the
screens SPEC §1 says must "match its mock exactly":

- **Wrong content.** The Writing page lists a scheduled chapter under "recent chapters". Its
  "Short fiction" tiles show two non-fiction essays in place of two of the four stories
  SPEC §6.10 names.
- **Missing values.** About a dozen SPEC-stated values are absent from the article, journal,
  hub and Writing screens. The most visible: the paragraph after the article's code block
  sits flush against it (0px; the mock has 22px).

None of these is covered by a §6.9 row, which is why the suite is green. Each fix task below
names the row that would have caught its finding.

### What I verified myself

- **Suites, run by me.** `foundry_verify` with R2-01's files passed all eight commands:
  - `composer lint`
  - `composer test:unit`: 169/169
  - `npm run lint`: budget 62428/62464, coverage 193/193 with 0 pending, fixme clean
  - `npm run test:unit`: 39 passed
  - `npm run build`
  - `forbidden-patterns`: clean
  - `npm run test:integration`: 481/481, 1932 assertions
  - `npm run test:e2e`: 449 passed

  The active theme was `ttm-theme` afterwards.
- **Constraint sweep, whole branch.** Every check below is clean:
  - no hex literals and no `prefers-color-scheme` in `ttm.css`
  - `210, 48, 19` absent from the Seeder (rule 45); no lorem in the seed fixtures (rule 39)
  - `css-coverage-allow.txt` is 0 bytes, and `selectors-allow.txt` has no `pending` line
  - no `test.fixme(`, `.only(` or `.skip(` in the e2e specs
  - `dependencies: {}`, and `TTM_CORE_API` is 1
  - nothing under `docs/phase-1/` or `docs/phase-2/` touched; `FOUNDRY_FEEDBACK.md` is not
    tracked

  Round 2 touched only `ttm.css`, `fidelity.spec.mjs`, the PNGs and the docs.
- **R2-01 mutation.** I reverted `ttm.css` to `925322a^` (the state before R2-01):
  - `strip-mark`, `strip-meta`, `ar-aside-row` and `hub-grid-dek` **failed** (6≠5, 6≠4, 6≠5
    and 4≠5).
  - `wr-serial-row-margins` passed, as its log predicts.

  I then deleted only the three new `.is-list` overrides. `wr-serial-row-margins` **failed**
  (5≠6). Restored with `git checkout --`; the tree is clean.
- **R2-01 cascade.** The new `.ttm-series-list.is-list …` overrides sit after the base rules.
  No later `@media` rule targets list-row mark/dek/meta, so nothing is shadowed.
  - The one new `stylelint-disable-next-line no-descending-specificity` guards
    `.ttm-series-bar .ttm-series-mark`. That rule is in a different context and still
    renders 0.
  - The grid-2 mark keeps its own 6px override, as SPEC §6.7 says ("mark margin-top 6").
  - `/series/hardening-wordpress/`'s "Other series" is `layout=list` (SPEC §6.7), so 6/4/6
    is correct there too.
- **R2-02.**
  - The ancestry check exits 0.
  - I ran `npm run screenshots` again against the current code: 12 of 14 PNGs came out
    byte-identical to HEAD.
  - The other two, `archive-security.png` and `archive-390.png`, differ only in the order of
    two filter chips with tied counts ("cryptography" and "threat-modeling"). That comes
    from `Query\Stats::top_tags()` using `ORDER BY cnt DESC` with no tiebreak. The code is
    pre-existing and untouched by this flight; see observation O1.
  - I restored the PNGs, so the committed set does reflect the code.

---

## Findings

Most severe first. All are category 5 (spec drift). There are no category 1–4 findings.

### 1. [Spec drift] Writing "recent chapters" lists a scheduled chapter first (fix task 2)

`plugins/ttm-core/blocks/series-toc/render.php:50-60`. In the `chapters` variant, the
renderer sorts and slices all of `$ttm_row['parts']`. For a closed series (The Quiet Ledger
has 31 total parts) that includes scheduled parts: only open-ended series are filtered to
published (`:51`).

On the seeded `/writing/`, the first row is `13 · Chapter 13 · Sept 28`. That chapter is
scheduled; it renders unlinked, in ink at 800, with no dek, and pushes chapter 9 off the
list. The heading beside it says "All 12".

- Mock `2d` (lines 1150–1155) and `02 §D` ("recent chapters … newest first, 4 items, dek
  line from chapter excerpt") show chapters 12, 11, 10 and 09: the published chapters.
- The F24 treatment (neutral-700 with `title="Scheduled …"`) is not applied either.

**What breaks:** SPEC §1 "Done" for `/writing/`. A reader sees a chapter that does not exist
yet at the top of "recent chapters".

**Why green:** `wr-chapter-*` assert only style. `SeriesTocTest::test_chapters_variant_newest_first_limited_with_dek`
seeds only published parts.

**Minimal fix:** in the chapters variant, filter `$ttm_rows` to `'publish' === status`
before the sort and slice. The `series` variant keeps its F24 scheduled rows.

### 2. [Spec drift] "Short fiction" shows two essays; two SPEC §6.10 stories are missing (fix task 3)

Seeded `/writing/` tiles: The Last Cron Job, "On finishing a draft you no longer believe in"
(240 words), "Outlining for people who hate outlines" (251 words), and Uptime.

- SPEC §6.10 and mock `2d` (lines 1157–1161) name the four stories as The Last Cron Job,
  A Field Guide to Empty Offices, Uptime and What the River Audits.
- The two essays in `docs/fixtures/seed/posts.json:459-479` sit in `writing` with no series.
  `Meta\Form::derive()` therefore classes them `story`.
- They are newer than Field Guide (`days_ago` 410) and River (260), so they take two of the
  four slots in `Fiction\Serials::stories()`.
- The phase-2 test `SeederTest::test_seeded_writing_essays_derive_as_story_but_stay_older_than_the_last_cron_job`
  only protects the front page's single "Also running" story.
- The mock's order also puts Field Guide (2025) second and Uptime and River as older
  (2024, 2023). The seed has Field Guide oldest.
- Smaller, same file (`:501`): part 1's manual excerpt uses markdown backticks
  (``Most `wp-config.php` files…``). They render as literal backticks on
  `/series/hardening-wordpress/` and `/category/security/`, where excerpts are plain text.

**Minimal fix:** seed-only; the content model is a non-goal. Give the two essays a locked
non-story form through a Seeder fixture field (`ttm_form` + `ttm_form_locked`). Set Uptime's
and River's `days_ago` so the order is Cron (2026), Field Guide (2025), Uptime (2024),
River (2023). Remove the backticks.

### 3. [Spec drift] SPEC §6.2/§6.4/§6.5/§6.7 values still missing (fix task 1)

These are measured with computed styles at 1280 on the seeded site. The "SPEC / mock" column
cites the SPEC section and the mock card line that states the value.

| Screen | Element | Rendered | SPEC / mock |
|---|---|---|---|
| article | `.entry-content pre` margin-bottom (`ttm.css:592`) | 0, next `<p>` touches it | mock 2b `<pre style="margin:0 0 22px">` |
| article | `.is-style-pull` margin (`:611-621`) | 32px (`spacing--60`) | §6.2 "margins 36", mock `margin:36px 0` |
| article | `.is-style-pull` letter-spacing | normal | §6.2 "28/800/1.25/−0.015em" |
| journal | `.ttm-journal-stream .ttm-cell-heading__link` | 11px | §6.4 "12px neutral-700", mock `font-size:12px` |
| hub | `.ttm-hub-all .ttm-cell-heading__link` | 11px | §6.7 "12px neutral-700" |
| hub | `.ttm-series-progress__meta` margin (`:2716`) | 12px 0 12px | §6.7 "margin 0 0 18" |
| hub | `.ttm-series-featured__buttons` gap (`:2996`) | 12px | §6.7 "flex gap 10" |
| writing | `.ttm-stats__label` (`:2939`) | 12px | §6.5 stat row "13px neutral-700", mock `font-size:13px` |
| writing | `.ttm-story-tiles` padding-top (`:2835`) | 0 | §6.5 "padding-top 16" |
| writing | `.ttm-book-grid` padding-top (`:2896`) | 0 | §6.5 "padding-top 16" |
| writing | `.ttm-book__meta` margin-top (`:2913`) | 12px | §6.5 "margin-top 2" |
| writing | chapters `__dek`/`__date` font-weight | 600 (inherited from `.ttm-numbered__row`) | mock 2d rows set no weight (400) |

**Why green:** the §6.9 rows for these elements assert other properties:

- `art-pre`: bg, border, padding
- `art-pull`: size, weight, indent
- `js-link`, `hub-all-head`: text
- `hub-meta`: size, text
- `hub-buttons`: count, texts
- `wr-stats`: tracks, rule, padding
- `wr-tiles`, `wr-books`: tracks, gap
- `wr-book-title`: title only

HANDOFF's reviewer notes name the chapters dek/date as dropped under budget. R1-06
restored their size and colour but not their weight.

**Minimal fix:** CSS only, scoped so no front-page row moves:
- The 11px `.ttm-cell-heading__link` base is phase 2's front-page value, so scope the change
  to the journal stream and hub-all.
- `.ttm-numbered__date` is shared with the 404 "Latest", so scope the weight change to
  `.ttm-series-toc.is-chapters`.

Extend the rows listed in fix task 1.

---

## Observations (not queued)

- **O1.** `plugins/ttm-core/src/Query/Stats.php:143` has no tiebreak in `top_tags()`, so
  chips with equal counts can swap between reseeds. The page is cached, so this is not a
  per-visitor variance. The code predates the flight and the owner may want a `t.name`
  tiebreak later.
- **O2.** The 404 H1 sits directly under the masthead rule: `main` has no top padding, while
  every other inner header has 40px. SPEC `02 §H` does not state a value, so I am not
  queuing it. Listed as a manual check.
- **O3.** HANDOFF's Measurements paragraph says Field Guide has `days_ago: 260`; the fixture
  says 410. Fix task 3 changes these dates anyway.

## Interpretation choices (HANDOFF round 2)

- **R2-01** placement of the `stylelint-disable-next-line` comment. Agreed; it matches the
  file's convention and the flagged rule is unaffected.
- **R2-02** none.

## Blocked and skipped tasks

None.

---

## Spec issues

Carried forward from the previous review, all still open and each needing an owner ruling:

1. Rule 34 has no sanctioned home for identity-only block wrappers. R1-10 shipped the
   `UNSTYLED_WRAPPERS` list inside `scripts/check-css-coverage.mjs`. The options are:
   - exempt `data-ttm-block` wrappers by rule;
   - permit `# hook` lines;
   - require a real rule.
2. SPEC §6.1.0 "one or the other" was resolved a third way (root padding kept and
   neutralised).
3. The `# state:` reason category in `selectors-allow.txt`.
4. §6.9 rows that cannot fail on SPEC's own wording (`wr-serial-form`'s `/i`).
5. Hub, TOC, chapter and 404 part-number zero-padding (mock 1f is templated).
6. `ValuesTest`'s impossible `month && year` archive-kind state.
7. New: §6.9 asserts a subset of each component's SPEC prose. This review found 12
   prose values with no row that had silently drifted. SPEC could require one row per
   prose value, or the owner accepts that prose values are checked only visually.

## Manual checks still owed

Copied from `docs/HANDOFF.md`, all `NOT VERIFIED (human)`. The PNGs are current as of R2-02,
but `writing.png`, `article.png` and `series-hub.png` will change again after the fix tasks
below and must be regenerated.

1. (P0-12) `article.png` masthead vs the top of `design_article.png`: a centred 1280 column,
   inline nav, no "Close".
2. (P1-07) `article.png` vs `design_article.png`; `article-390.png` vs mock `3b`.
3. (P2-04) `journal.png` vs `design_journal.png`.
4. (P3-06) `archive-security.png` vs mock `1e`; `search.png` and `404.png` vs `02 §H` (see O2).
5. (P4-07) `series-hub.png` vs mock `1f`; `writing.png` vs `design_serial.png`;
   `series-single.png` vs `02 §F`.
6. (P5-01/P5-05) Open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and
   `/category/security/` at 390 on a real phone and compare with `3b` and the `02` Responsive
   bullets.
7. (P5-05) Compare every `docs/feedback/phase-3/*.png` with its paired mock, and confirm CI
   is green, including the `e2e` job and its `playwright-report` artifact.
8. (Round 1) Search rows, prior-year archive rows and the Writing serial meta line against
   their mocks; exactly two books in "In print"; newsletter box copy at 13px.
9. (Round 2, HANDOFF) The front page, `/writing/`, a category archive and `/series/` against
   their mocks now that the strip, rail and grid-2 margins are restored.
10. (Previous review) `series-single.png`: the per-part date sits on the dek's baseline;
    confirm against `02 §F`.
11. (Previous review) Hub part numbering `01`–`06`: owner ruling (Spec issue 5).
12. `git stash list` shows `stash@{0}: On poc: temp: stash foundry feedback notes before run
    start`. This is the owner's stash; leave it for the owner.
