# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 3

**Verdict: CHANGES REQUESTED**

Reviewed `aa497b2..7de5853`. Rounds 1 and 2 covered the branch through `a626d69`. This round reads the five
R2-* task commits and the `chore: final green` commit in full (`e67bba9`..`b7a5a18`), then re-checks the
branch-wide gates. I checked these myself:

- `foundry_verify`: all 26 constraints clean, no fixture failures. `npm run lint`, `npm run test:unit`
  (104 passed, 6 skipped), `npm run build` and `forbidden-patterns.sh` are green. The CSS budget is 63090 of
  63488 bytes, and `npm audit --audit-level=high` finds 0 vulnerabilities.
- `composer lint` and `composer test:unit`: `foundry_verify` fails both, because of the same host
  `composer` shim as in rounds 1 and 2 (environment, not code). I re-ran them with `/usr/bin/php`: PHPCS
  0 errors (113 warnings, all pre-existing), PHPUnit 184/184 OK.
- `npm run test:integration`: 586/586 OK when run locally as one full-suite process. The "2 pre-existing
  failures" that R2-02's commit reports for a class-only run did not show up in the full suite.
- GitHub CI on `7de5853`: the pull_request run is green.
- Mutation samples. Each made its test fail, and I restored every file with `git checkout`:
  - `report.mjs` `isMergedParagraph` → `false`: `convert-classic.test.js` fails.
  - `autop.mjs` → identity: 3 tests in `autop.test.js` fail.
  - `MigrateCommand::politics_child()` fixup branch → old early return: both new `MigrateCommandTest` cases
    fail.
  - `PrimaryCommand` `translate_yoast_id` bypassed: `test_from_yoast_with_term_map_translates_source_ids`
    fails.
- Probe: I re-ran `scripts/convert-classic.mjs` on the gitignored `docs/fixtures/live/classic.ndjson`, output
  to the scratchpad. The report shows 0 `mergedParagraphs` and 3 `textEqual:false`, which matches R2-05.
  Scanning the serialized output directly, 12,244 `<p>` elements, and 4 posts still have a `<p>` with a
  blank line. All 4 are `core/html` blocks holding CodeColorer `<code lang>` code (see Notes).
- Screenshots: `live-front.png` now has a populated Opinion cell. `live-article-classic.png` shows separate
  paragraphs under "Code Review" and "Self Study". Round 2's findings 1–4 are fixed in the product.

R2-02, R2-03 and R2-04 do what they claim, and their tests would catch a regression. R2-01's converter fix
works on the real export. But two of its guards are weaker than they look:
- The end-to-end guard it added to `live.spec.mjs` can never run on a converted post.
- Its "runs, not `maybeIt`" coverage does not pin the pipeline order the task required.

## Findings (most severe first)

### 1. `live.spec.mjs` merged-paragraph check can never fire on a converted post (tests; R2-01, R2-05)
- `tests/e2e/live.spec.mjs:340` gates the new check on `if ( screen.classic )`.
- In the screens manifest, `classic` means "still has no `<!-- wp:` block markup", i.e. **not** converted:
  - `scripts/live/screens.mjs:126` sets `classic: ! content.includes( '<!-- wp:' )`.
  - `classicShortcodePosts()` hard-codes `classic: false` for the `ref-*`/`cc-*`/`mfn-*` screens
    (`scripts/live/screens.mjs:217`).
  - After `convert:import`, the only posts left without block markup are the 3 text-mismatch posts it
    skipped.
- So the assertion runs only on posts the converter never touched. Those render through `wpautop` and cannot
  hold a merged `core/paragraph`. On every converted screen, the one place the regression can occur, the
  check is skipped.
- R2-05's own commit says it: "no classic screen selected this run so it didn't fire against a live URL".
  Reverting R2-01's `autop()` would leave `test:live` green. That means the acceptance test named in R2-01
  ("on classic-converted screens, no `.ttm-entry p` whose innerHTML contains a blank line") does not exist
  in any form that would fail.
- **Fix:**
  - Give each screen a `converted` flag: true when the post has `ttm_converted_at` meta. Set it in
    `toPost()` and in `classicShortcodePosts()`, and have `buildScreens()` pass it through.
  - Gate the merged-paragraph check on `screen.converted` through a pure predicate exported from
    `scripts/live/lib/screens.mjs`.
  - Jest-test that a `ref-*` screen built from a converted post is selected by the predicate and an
    unconverted post is not.
- Belongs to R2-01.

### 2. The pipeline order (shortcode pre-pass → autop) has no running test (tests; R2-01)
- R2-01's design constraint fixed the order `preprocessShortcodes → autop → transformFootnotes → rawHandler`
  so that `[cc]`/`[cci]` bodies are already `<pre>` when `autop()` runs.
- The only running tests call `autoParagraphPlainText()` (i.e. `autop`) directly on hand-written `<pre>`
  markup. `convertPost()` is covered only by the `maybeIt`-skipped rawHandler suite, which is "6 skipped"
  on every run.
- If someone swapped the two calls in `scripts/convert-classic.mjs:146-153`, every multi-line `[cc]` body
  with a blank line would gain `<p>`/`<br>` inside the code, and no test would fail.
- Related: `scripts/lib/report.mjs` `buildBlockReport()` counts `mergedParagraphs` only over top-level
  blocks. A merged paragraph inside `core/quote`, `core/list` or `core/group` inner blocks is invisible to
  the gate that `summarizeResults` relies on.
- **Fix:**
  - Extract the pre-`rawHandler` steps into a pure exported function (e.g. `prepareClassicHtml( content,
    postId )` returning `{ html, footnotes, remaining }`) and have `convertPost()` call it.
  - Jest-test it on a synthetic `[cc lang="php"]a\n\nb[/cc]` between two blank-line paragraphs: the
    `<pre>` body has no `<p>`/`<br>`, and the prose around it is two `<p>`.
  - Make `buildBlockReport()` recurse into `innerBlocks`, with a test on a nested merged paragraph.
- Belongs to R2-01.

### 3. LIVE-TRIAGE rows for the round-2 classes lack the fix commit and test (rule 52; R2-05)
- Rule 52 and R2-05's constraints ask for each finding's class, fix commit and test.
- The new "R2-05 run" table (`docs/feedback/phase-4/LIVE-TRIAGE.md`, last section) names the task IDs only.
  There is no commit hash and no test name for the paragraph-merge, Politics→Opinion and Yoast term-map
  classes.
- **Fix:** add the commit (`e67bba9`, `8c60102`, `72c538f`) and the test names to those rows during the
  finding 1 re-run.

## Spec issues
- Carried from rounds 1–2, still open:
  - §6.11 `single-other` count 4 vs the seed's 3.
  - §5 calls `excerpt_length` "existing".
  - §6.7 Photon target during rehearsal.
  - §6.10 "kicker equals the primary category" should read "the kicker's first term".
  - §6.8 "exactly once each" is ambiguous for identical note texts.
  - `docs/01-design-language.md:166` still has the dated verse attribution.
- New: §1.2's export profile and §6.8's table miss CodeColorer's **tag** syntax. 40 classic posts use
  `<code lang="x">…</code>` (multi-line PHP/JS/XML samples), and 5 of them open with a raw `<?php`, which
  the HTML parser turns into a bogus comment that swallows code up to the next `>`.
  - The currently live site renders these the same broken way: CodeColorer is inactive there too, and
    `[cci]` even shows literally. So the conversion reproduces the classic render and is not a regression.
  - A pre-pass rule mapping multi-line `<code lang>` bodies to `codeMarkup()` (escaped, `<pre>`) would
    fix all 40. SPEC should either add the row or name them owner cleanup.

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e` line 933.
2. (P1-06) NOT VERIFIED (human): open `/transients-object-caches-and-fast-enough/`, `/series/hardening-wordpress/`
   and `/` on the seeded site; compare with mocks `2b`, `1f`, `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/`, `/series/` on the live import.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock; CI green including
   the drill; `git stash list` empty.
5. (R1-09) NOT VERIFIED (human): live kickers and section cells show real categories, never Uncategorized.
6. (R1-09/R2-05) `character-quest-service`, `hyper-vvv-windows`, `securing-forms-without-captcha`,
   `the-hackiest-hack-that-ever-was-hacked`, `use-your-head`: owner content pass.
7. (R2-05) NOT VERIFIED (human): open `live-front.png` and `live-article-classic.png` (the Opinion cell is
   populated and paragraphs are separate; I looked, and both read correctly) and `journal.png` against
   mock 2c.

## Notes
- HANDOFF's round 1 "What a human must check by hand" still says `--from-yoast` needs matching term IDs.
  R2-03 superseded that. The next HANDOFF should drop it.
- R2-04's `jr-entry` gap assertion uses exact `toBe( 18 )` on bounding boxes. It is green today, but
  `toBeCloseTo( 18, 0 )` would be sturdier against sub-pixel layout.
- `politics_child_fixup()` overwrites a Politics post's stored primary with Opinion even if it was, say,
  Technology. That matches the fresh-migration path and the task, and `plan.sh` runs it before
  `--from-yoast`, so no Yoast choice is lost today.
- Round 2 notes (the `WP_IMPORTING` define leak in `PrimaryCategoryTest`, the `Config.php:104` comment,
  `Seeder::reset()` comment statuses, `private-data.test.js` temp dirs) still stand.
