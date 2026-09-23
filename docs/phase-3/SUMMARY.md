# Phase 3 (inner-template fidelity): build summary

**Merge:** `refine/2026-09-22` into `main`, `8c2b228` → `e8f2d56`. 137 commits, 59 tasks (all done,
0 blocked, 0 skipped), 5 review passes (the first plus fix rounds R1 to R4). Final verdict: **APPROVED**.
Final suites: unit 171/171, integration 490/490, e2e 452 passed, lint clean. CSS is 62568/63488 bytes
and the coverage allow-list is empty.

## What was built

**Phase 0: harness and shared chrome (P0-01..P0-12).** Built the measurement tools before any
inner-page CSS. Allow-list lines became single-class, task-tagged "pending" lines. The
`article-h2` slug landed with a checker that every preset variable reference resolves. A runtime
selector-coverage spec runs over 13 seeded screens. All ~190 SPEC §6.9 rows were written as tagged
fixme tests. Seed images are now neutral generated placeholders. The Hardening WordPress seed series
was rewritten to match the mocks. Also added: the 1280px page container, the inner masthead with its
phone-only overlay nav, the inner footer, and baseline screenshots.

**Phase 1: article (P1-01..P1-07).** Series bar, hero and body typography, sticky aside,
kicker/H1/dek/byline header, prev/next, series TOC, "More in {section}" and the newsletter box
copy binding.

**Phase 2: journal (P2-01..P2-04).** Journal post header, body and note columns, syndication line.
Journal stream and archive, including whole-row links (`Helpers::link_rows()`), which every later
list template reuses.

**Phase 3: archives, search, 404, page (P3-01..P3-06).** Archive header with the `ttm/archive-kind`
kicker, the tag filter row, year-grouped rows with pagination, the aside (series rail plus numbered
"Most read"), and the search, 404 and static-page templates.

**Phase 4: series hub, single series, Writing (P4-01..P4-07).** Hub header, featured block and
all-series grid. Single-series template. Writing hero, body and aside, with a ≤1024 reorder that
uses `display: contents`. Fixed the "Stories" book label and the book-grid gap.

**Phase 5: phone, wide, close-out (P5-01..P5-05).** A 390/1920 sweep of every screen fixed four
overflow bugs. Every a11y, network and selectors row now runs, and both harness guards are strict
again. Fixed a `wpautop` `<p>` escaping bug in series descriptions. Measured the CSS budget, wrote
the handoff and pushed the final screenshots.

**Review fixes (R1-01..R4-03, plus standalone `dd4dfbe`).**
- Search rows became links.
- Rule-36 nesting violations removed.
- Dead phone overrides fixed.
- SPEC-literal archive dates and serial meta.
- Declarations dropped under budget restored, then scoped to the right layout.
- Seed drift fixed.
- A dozen SPEC values restored that no row asserted.
- Writing "recent chapters" limited to published chapters; the four §6.10 stories in mock order.
- Series-row count cell moved beside the title; Writing columns stretch.
- Complete series read "N chapters" / "N parts".

## Decisions that shaped it

From PLAN.md:
- **Phasing (all).** SPEC §8's six phases, run in order. Each ends with a push task that commits screenshots.
- **S1 container (P0-09).** Kept `theme.json` root padding and `useRootPaddingAwareAlignments`, and added the `.wp-site-blocks` 1280 container. `.has-global-padding` is zeroed inside it, which is a third way beyond SPEC's "one or the other".
- **S2 nav (P0-10).** The 720 breakpoint is enforced in CSS over core's 600. With `hasIcon:false` the close button reads "Close", not "×", because markup cannot vary per viewport.
- **S3 newsletter copy (P1-06).** A new `ttm/newsletter-copy` binding: "Get the next part" on series posts and series pages, otherwise "The weekly issue."
- **S4 footer.** One string everywhere, unchanged.
- **S5 archive kicker (P3-01).** A new `ttm/archive-kind` binding. A single `ttm/archive-header` pattern serves all three archive templates.
- **S6 filter-row rule (P3-02).** Archive body carries `rule-2`, and `.ttm-filter-row + .ttm-archive-body` removes it. No `:has()`.
- **S7 seed images (P0-05/06).** Rule 45 generator; band angle from config.
- **S8 CSS budget (P0-01, P5-03).** Started at 61440 (later raised, see Assumptions).
- **Colour vs a11y (all).** §6.9 text under 18.66px in accent/neutral-500/600 is rendered and asserted as `accent-700`/`neutral-700`. Affected: nav-current, js-words, chapter/most-read/hub numbers, complete/hiatus status, TOC numbers, disabled pagination, box button.
- **Rule 44 slug (P0-02).** Kept `article-h2`, which WordPress generates as `--article-h-2`. The checker computes generated names the same way.
- **Pending harness (P0-01, P5-02).** Tagged pending allow-list lines and tagged fixme lines were allowed during the flight, then made strict in P5-02.
- **Rule 41 selectors spec (P0-03).** Auto-exempts state pseudo-classes and `@media` rules. Every other exemption is a reasoned allow-list line.
- **Whole-row links (P2-02).** A render filter turns the `ttm-archive-row`/`ttm-journal-row` group into an `<a>`. The title is `isLink:false`.
- **Term order (P1-03).** A `get_the_terms` filter puts the primary category first, then nav order ("Technology · Security").
- **Byline tags, featured caption, search classes (P1-03, P1-02, P3-05).** Render filters in `Blocks\Helpers`.
- **Pagination (P3-03).** Relabelled "← Newer" / "Older →". A disabled span renders when a side is empty. Numbers removed.
- **Empty bound blocks (P1-02).** A `ttm/*`-bound paragraph or heading that renders empty renders nothing.
- **New and extended bindings (P1-06, P3-01, P3-04, P3-05).** New: `newsletter-copy`, `archive-kind`, `section-label` (more-in / series-in), `search-summary`. Extended: `word-count whenUnsyndicated`, `category-count journal-full`, and " · " tag joins.
- **`ttm/series-list` layouts (P3-04, P4-02, P4-05).** `list|rail|grid-2|grid-3|strip`. `rows` was renamed to `list` under rule 46.
- **Block markup (P1-05, P4-01, P3-04).** `ttm/series-toc` heading and chapters variant, `ttm/series-featured` elements (h1 on series pages, `showDek`), and `ttm/most-read` on the shared numbered component.
- **Nav current (P0-10).** A single post no longer marks `/series/` current; only its primary category is marked.
- **Inner masthead (P0-10).** Site title and "by Eric Mann" are sibling elements, not the one `<a>` SPEC describes.
- **Series bar (P1-01).** Phone keeps "Part 3 of 6", where mock 3b shows "3 of 6".
- **Seed (P0-07/08).** Hardening part 4 is filed under Security and dated today, so it does not replace the front-page lead. Parts 5–6 are scheduled and the series is pinned `featured`. A journal `weekday: Sunday` field was added.
- **Content labels (P4-06, P5-05).** The book form `collection` reads "Stories". The About page gets a 3:2 portrait.
- **Writing ≤1024 order (P4-06).** `display: contents` on main/aside plus `order` 1–4. The a11y row treats landmark findings as moderate.
- **404 "Latest" (P3-05).** Numbered with CSS counters, not a plugin block.
- **Screenshots, e2e screen set, fidelity idioms (P0-03/04/12).** 14 PNGs. Text rows compare normalised `innerText`. `≈` rows use `toBeCloseTo(value, 0)`.

Interpretation choices from HANDOFF and the PROGRESS log:
- **P1-02.** `drop_empty_bound` also hides the footer verse paragraph when no verse is stored.
- **P1-05.** A scheduled TOC row carries its `title` on the `<li>`.
- **P1-06.** The phone newsletter button is full width through CSS, not a markup class. The box is `layout: default`.
- **P1-07, P3-06, P4-07.** Several allow-list lines became permanent `# state:` reasons instead of reachable selectors (spec issue 3).
- **P2-01.** Syndication links are underlined to pass axe `link-in-text-block`.
- **P2-02.** `link_rows()` falls back to `get_the_ID()` and skips rows that already contain an anchor.
- **P2-04.** `hardening-part-1` was retitled to match the mock's "01" row.
- **P3-01.** `archive_kind` precedence is category > tag > month > year > day, so a day archive reads "Month". Its test asserts a state WordPress cannot produce (spec issue 6).
- **P3-03.** Year labels are `<p>`, not headings. `ttm/most-read` reads site-wide on tag and date archives.
- **P3-06.** The `ttm-archive`/`ttm-most-read` wrappers first got a trivial rule. R1-10 replaced it with the `UNSTYLED_WRAPPERS` exemption in `check-css-coverage.mjs` (spec issue 1).
- **P4-02.** Shared series-row rules were folded rather than deleted, to keep the phase-2 strip green.
- **P4-03.** When `excludeCurrent` is set the limit is `related_limit` (4), otherwise `strip_limit` (3). The single-series featured block drops the grid.
- **P4-03.** Conditional classes in `render.php` keep the base class literal so the coverage scanner can see it.
- **P4-04.** Stored cadence is capitalised in the hero stat only ("Monthly"), since `wr-serial-form` expects lowercase.
- **P4-07.** The admin-only `ttm-book-row` was renamed `book-admin-row` instead of getting a fake theme rule.
- **P5-01.** The `.tag` chip is scoped to `a.tag`, because WordPress puts a bare `tag` class on `<body>`.
- **P5-02.** Term descriptions are read in `'raw'` context to prevent the `wpautop` `<p>` bug.
- **`dd4dfbe` (standalone).** Aside labels are uppercase and most-read numbers are unpadded. The fidelity row now reads `textContent`.
- **R1-02.** The `layout: grid` `.ttm-stats` group counts as a grid under rule 36.
- **R1-08.** The excerpt filter is scoped by `is-style-dek-l`, not `is_singular()`.
- **R1-09.** `aside-phone-order` uses `>=`, because the TOC sits flush against prev/next.
- **R3-01.** The budget gap was closed by shortening comments, not raising the budget.
- **R3-02.** An empty-chapters guard was added. R4-03 later tested it.
- **R3-03.** `Seeder::normalize_form()` is pure and has its own unit test. Story `days_ago` is 700 (Uptime) and 1090 (River), placing them in 2024 and 2023.
- **R4-01.** The count cell uses `grid-row: 1 / span 3` with `align-items: start`.
- **R4-02.** "N chapters" / "N parts" applies to `list` and `grid-2` only. `grid-3` still shows "N of N" (review O2).

## Assumptions still in play

| Key | Where | Final default | Status |
|---|---|---|---|
| `seed.image_band_angle` | `Config.php` (Seeder only) | 30 | Tuned in P0-06 (15/30/45° measured; band visible at all three). Kept at 30. |
| `cssBudgetBytes` | `scripts/check-budget.mjs` | 63488 | Measured in P5-03 (61440 kept), then raised twice to fit restored declarations: 62464 in R1-06, 63488 in R4-01. 62568 used. Sized to fit, not tuned. **`CLAUDE.md` still says 62464; update it.** |
| `excerpt_length` (§5) | `Config.php` | 55 | Pre-existing ⚠️ from an earlier flight. Not touched here, still a guess. |

`series.related_limit` (4) is new but is not an ASSUMPTION.

## Spec issues (edits for SPEC.md)

From PLAN.md:
1. **Rule 34 amendment.** Should say "pending lines tagged with the owning task, empty at flight end", and cover `check-fixme` the same way.
2. **Rule 44.** `article-h2` still hyphenates to `article-h-2`. A digit-free slug such as `article-heading` would avoid it.
3. **§6.1.1.** State that singles mark only the primary category, never `/series/`.
4. **`core/post-terms` order.** Name the `get_the_terms` ordering filter.
5. **Featured-image caption.** Core has none. Name the `figcaption` filter, or accept a bound caption paragraph.
6. **Empty bound blocks.** State the "empty `ttm/*`-bound paragraph/heading renders nothing" rule.
7. **§6.9 colours.** Carry the accent-700/neutral-700 override note in the table itself.
8. **§6.10.** Give Hardening part 4 its category (Security), so it cannot become the front-page lead.
9. **Whole-row links.** Name the render-filter mechanism, or relax rows to "title is the link".
10. **§6.5/§6.6.** Spell out the `ttm/series-list` layout enum (`list`, `rail`).
11. **Chapters heading.** Document that the label is composed when `heading` is empty.
12. **Book form.** Document that `collection` reads "Stories".
13. **Mock 3b.** Phone "3 of 6" cannot be reproduced without viewport-varying markup.
14. **Inner masthead "one `<a>`".** Not reproducible with core `site-title`.
15. **Nav close glyph.** "Close", not "×", because `hasIcon` governs both buttons.
16. **`hub-progress-color`.** Depends on a pinned featured series.
17. **Rule 41.** Auto-exempt registered `is-style-*` block styles.
18. **`wr-serial-form` vs `wr-stat-cadence`.** The two rows disagree on case.
19. **§7 `extraVerify`.** Lists `theme.json` twice.

From reviews:
20. **CLAUDE.md rule 2.** Its restatement dropped the "under `plugins/ttm-core/`" scope (review 1).
21. **§6.1.0 "one or the other".** Resolved a third way. Amend SPEC or take the stated fallback (reviews 1–5).
22. **Rule 41 `# state:` reasons.** Ratify the category or trim it (reviews 1–5).
23. **§6.9 rows that cannot fail.** `hub-grid-cats` ("· or single name") and `wr-serial-form`'s `/i` (reviews 1–5).
24. **Part-number zero-padding.** Undetermined for the hub, TOC, chapters and 404 lists, because mock 1f is templated. Needs an owner ruling (reviews 1–5).
25. **`ValuesTest` archive kind.** It asserts an impossible `month && year` state (reviews 1–5).
26. **Rule 34 wrappers.** No sanctioned home for identity-only block wrappers (`UNSTYLED_WRAPPERS`) (reviews 2–5).
27. **§6.9 coverage.** It asserts only a subset of each component's prose. Grow it to cover positions and computed widths (reviews 3–5; the root cause of R3 and R4).
28. **Rule 36 wording.** Resolved: R4-01 amended CLAUDE.md to quote SPEC §3.1 (grid column children are never `flex`/`constrained`).

From HANDOFF:
29. **Conventions to adopt.**
    - Avoid a bare `.tag` class.
    - Grid and flex items whose content must shrink need `min-width: 0` and `box-sizing: border-box`.
    - A `@media` override must come after the rule it beats.
    - Prefer `textContent` over `innerText` in fidelity rows.

## Manual checks owed

All are NOT VERIFIED (human). Screenshots are in `docs/feedback/phase-3/`.
- **Phase 0 (P0-12).** Compare `article.png`'s masthead with the top of `design_article.png`. Look for a centred 1280 column, inline nav, and no "Close" button.
- **Phase 1 (P1-07).** Compare `article.png` with `design_article.png`, and `article-390.png` with mock 3b (`docs/Eric Mann Newspaper.dc.html` lines 118–163).
- **Phase 2 (P2-04).** Compare `journal.png` with `design_journal.png`. Look for the date block on the left, the 36px body, the syndication line and the "Earlier" stream.
- **Phase 3 (P3-06, `dd4dfbe`).**
  - Compare `archive-security.png` with mock 1e: 80px "Security", filter chips, two year groups, and an uppercase "Series in Security" / "Most read" rail with unpadded 1/2/3.
  - Compare `search.png` and `404.png` with 02 §H, including the 404 H1 top spacing (review O2).
- **Phase 4 (P4-07).** Compare `series-hub.png` with mock 1f, `writing.png` with `design_serial.png`, and `series-single.png` with 02 §F. The per-part date should sit on the dek's baseline.
- **Phase 5 (P5-01/P5-05).**
  - On a real phone, open `/signing-your-options-table/`, `/journal-post-1/`, `/writing/` and `/category/security/` and compare with 3b and the 02 Responsive bullets.
  - Compare every PNG with its paired mock.
  - Confirm CI is green, including the `e2e` job and its `playwright-report` artifact.
- **Round 1.** Search rows, prior-year archive rows, the Writing serial meta line, exactly two books in "In print", and newsletter box copy at 13px.
- **Round 2.** Check the front page, `/writing/`, a category archive and `/series/` against their mocks: the strip, rail and grid-2 margins should be back to their phase-2 values.
- **Round 3.**
  - `/writing/` should show the four stories in mock order and no scheduled chapter under "recent chapters".
  - `wp-config.php` should read without literal backticks on the article and the Security archive.
- **Round 4.**
  - At 1280 on `/writing/`, `/series/` and `/series/hardening-wordpress/`, the count/status cell should sit level with the title. Complete series should read "9 chapters" / "24 chapters" / "N parts".
  - On `/writing/`, the lists should fill their column at 1280, and the stacked order should be unchanged at 1000 and 390.
- **Owner calls.**
  - Part-number zero-padding (spec issue 24).
  - River seed word count and "~12 min" versus mock 2d's 4,400 and "~14 min".
  - `Stats::top_tags()` has no tiebreak.
  - The hub reads "4 of 6 · next part" one part ahead of §6.7's illustrative "3 of 6", and its dates move with the seed day.
- **Housekeeping.** `stash@{0}` ("stash foundry feedback notes before run start") belongs to the owner. Leave it or drop it as you see fit.

## Review history

- **Review 1 (after P5-05): CHANGES REQUESTED, 10 findings, fix tasks R1-01..R1-10.** Findings: search rows not links, `constrained` inside a grid (rule 36), a dead ≤720 override, archive date text, unguarded headline fixes, declarations dropped under budget, seed drift, excerpt-filter scope, loose rows, and hygiene. First pass, so nothing could recur.
- **Review 2 (after R1): CHANGES REQUESTED, 2 findings, fix tasks R2-01..R2-02.** R1-06 regressed the strip, rail and grid by editing shared base rules, and the screenshots were stale. The regression was caused by a round-1 fix. No finding repeated.
- **Review 3 (after R2): CHANGES REQUESTED, 3 findings, fix tasks R3-01..R3-03.** A scheduled chapter showed under "recent chapters", the wrong stories appeared, and about a dozen SPEC values were missing. **Recurring class:** spec values that no §6.9 row asserts (spec issue 27).
- **Review 4 (after R3): CHANGES REQUESTED, 5 findings, fix tasks R4-01..R4-03.** Findings: an untested empty branch, the count cell at the row bottom, a `flex` group inside a grid, "N of N" wording, and readability leftovers. **Recurred:** rule 36 (grid nesting, first seen in review 1), and unasserted spec values again.
- **Review 5 (after R4): APPROVED, 0 blocking findings.** Two readability notes were left unqueued: `$ttm_fiction_forms` duplicates the house "not nonfiction" idiom (`series-list/render.php:156`), and `grid-3` still reads "N of N".
