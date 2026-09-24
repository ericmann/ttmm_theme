# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 4

**Verdict: CHANGES REQUESTED**

Reviewed `aa497b2..079d523`. Rounds 1 to 3 covered the branch through `7de5853`. This round reads the three
R3-* task commits in full (`0be2d50`, `d26669c`, `b62cba8`) and re-checks the branch-wide gates. I ran
these myself:

- `foundry_verify`: all 26 constraints are clean, with no fixture failures. `npm run lint`,
  `npm run test:unit` (112 passed, 6 skipped), `npm run build` and `forbidden-patterns.sh` are green.
  The CSS budget is 63090 of 63488 bytes. Coverage has 0 pending, and `check-fixme` is clean.
- `composer lint` and `composer test:unit` fail inside `foundry_verify` for the same reason as rounds 1
  to 3: the host `composer` shim ("Could not open input file: /usr/local/bin/composer"). That is the
  environment, not the code. I re-ran both with `/usr/bin/php`:
  - `php -l` is clean.
  - PHPCS: 0 errors, 113 warnings, all pre-existing.
  - PHPUnit: 184/184 OK.
- `npm audit --audit-level=high`: 0 vulnerabilities. `git stash list` is empty, and the tree is clean.
- Round 3 touched no PHP, theme or block files, so the integration and e2e results from round 3 still
  apply. The GitHub CI run for `079d523` was still in progress when I checked.
- Mutation samples. I restored every file with `git checkout`.
  - `checksMergedParagraphs` returning `screen.classic`: 4 tests in `live-screens.test.js` fail.
  - `countMergedParagraphs` with no recursion: the R3-02 nested-paragraph test fails.
  - `PrimaryCategory::id()` stale-term guard removed:
    `PrimaryCategoryTest::test_id_falls_back_to_nav_order_when_stored_term_is_stale` fails.
  - **`prepareClassicHtml()` with autop run before the shortcode pre-pass: every test passes.** This is
    finding 1.

R3-01 and R3-03 do what they claim.
- The live merged-paragraph check now runs on the 6 converted screens.
- The `wasConverted()` try/catch is correct.
- The LIVE-TRIAGE rows now name the fix commit and the test.

R3-02's `innerBlocks` recursion is correctly tested. But its pipeline-order test, which is the point of
the task, does not fail when the order is reversed.

## Findings (most severe first)

### 1. The R3-02 pipeline-order test passes with the order reversed (tests; R3-02)
- **Where:** `scripts/test/convert-classic.test.js:110-129` (`prepareClassicHtml` describe).
- **What's wrong:** the test asserts only that the `<pre>` body has no literal `<p>` and no literal `<br`.
  `codeMarkup()` HTML-escapes the shortcode body. So if autop runs first, its tags end up inside the
  code as **escaped text** and the assertions never see them. Here is the real output with the order
  swapped (in the scratchpad, calling `preprocessShortcodes( autoParagraphPlainText( src ) )`):

  ```text
  correct: <p>Intro.</p>\n<pre class="wp-block-code"><code lang="php">a\n\nb</code></pre>\n<p>Outro.</p>
  swapped: <p>Intro.</p>\n<p><pre class="wp-block-code"><code lang="php">a&lt;/p&gt;\n&lt;p&gt;b</code></pre></p>\n<p>Outro.</p>
  ```

  With a single newline in the body, the swapped order also gives `a&lt;br /&gt;\nb`. Both swapped outputs
  still pass all four assertions: no literal `<p>` or `<br` in the body, and `<p>Intro.</p>` and
  `<p>Outro.</p>` are both present.
- **What would break:** if someone swapped the two calls in `scripts/lib/prepare-classic.mjs:34-39`, every
  multi-line `[cc]`/`[cci]` code block on the 105 real CodeColorer posts would show literal
  `</p> <p>` / `<br />` text inside the code, and the `<pre>` would be wrapped in a `<p>`. No unit test
  would fail. Round 3's finding 2 asked for exactly this protection, and it is still missing. The
  production order today is correct; only the guard is missing.
- **Minimal fix:** tighten the existing test (no production change):
  - Assert the code body equals `'a\n\nb'` exactly.
  - Assert `html` contains no `<p><pre` and no `</pre></p>`.
  - Add a second fixture with a single newline and a blank line in the body
    (`[cc lang="php"]a\nb\n\nc[/cc]`) and assert its body is exactly `'a\nb\n\nc'`, so the `<br />` path
    is pinned too.
- **Task:** R3-02.

## Spec issues
- Carried from rounds 1 to 3, still open:
  - §6.11 `single-other` count 4 vs the seed's 3.
  - §5 calls `excerpt_length` "existing".
  - §6.7 Photon target during rehearsal.
  - §6.10 "kicker equals the primary category" should read "the kicker's first term".
  - §6.8 "exactly once each" is ambiguous for identical note texts.
  - `docs/01-design-language.md:166` still has the dated verse attribution.
  - §1.2's export profile and §6.8's table miss CodeColorer's `<code lang>` **tag** syntax: 40 posts, 5
    of which open with a raw `<?php`. SPEC should add a pre-pass row or name these posts owner cleanup.

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e` line 933.
2. (P1-06) NOT VERIFIED (human): open `/transients-object-caches-and-fast-enough/`, `/series/hardening-wordpress/`
   and `/` on the seeded site; compare with mocks `2b`, `1f`, `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/`, `/series/` on the live import.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock; CI green including
   the drill; `git stash list` empty (I checked: it is empty).
5. (R1-09) NOT VERIFIED (human): live kickers and section cells show real categories, never Uncategorized.
6. (R1-09/R2-05) `character-quest-service`, `hyper-vvv-windows`, `securing-forms-without-captcha`,
   `the-hackiest-hack-that-ever-was-hacked`, `use-your-head`: owner content pass.
7. (R2-05) NOT VERIFIED (human): `live-front.png`, `live-article-classic.png` and `journal.png` against mock 2c.
8. (R3-03) When `env:live`/`test:live` is re-run against a future export, confirm the count of converted
   screens that run the merged-paragraph check grows as more posts carry `ttm_converted_at`, rather than
   staying at 6.

## Notes
- R3-03 folded the `wasConverted()` try/catch into its own commit, with no unit test. The fix is correct
  and matches `primaryCategoryName()`. Like the other `wp`-calling helpers in `scripts/live/screens.mjs`,
  it has no test seam. This is a harness bug found by running the task, not a content class, so rule 52's
  synthetic-test requirement does not strictly apply.
- The live manifest this run had no `mfn-*` screen among the 6 converted ones (`single-faith`, `oldest`,
  `ref-1`, `ref-2`, `cc-1`, `cc-2`). That is fine, because `[mfn]` shares the `[ref]` footnote path.
- Round 2 and 3 notes (the `WP_IMPORTING` define leak in `PrimaryCategoryTest`, the `Config.php:104`
  comment, `Seeder::reset()` comment statuses, `private-data.test.js` temp dirs, `toBe( 18 )` on bounding
  boxes, `politics_child_fixup()` overwriting a non-Opinion primary) still stand.
