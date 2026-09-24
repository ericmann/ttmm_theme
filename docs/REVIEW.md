# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 5

**Verdict: CHANGES REQUESTED**

Reviewed `aa497b2..860be85`. Rounds 1 to 4 covered the branch through `079d523`. Since the round 4 review
(`7f94e29`), the only change is the single R4-01 task commit (`de0e31c`, test-only) plus its progress,
handoff and state commits. No PHP, theme, block, CI or ignore file changed this round. I read R4-01 in full
and re-checked the branch-wide gates. I ran these myself:

- `foundry_verify`: all 26 constraints are clean, with no fixture failures. `npm run lint`,
  `npm run test:unit` (113 passed, 6 skipped), `npm run build` and `forbidden-patterns.sh` are green.
  The CSS budget is 63090 of 63488 bytes. Coverage shows 191/191 classes with 0 pending, and
  `check-fixme` is clean.
- `composer lint` and `composer test:unit` fail inside `foundry_verify` for the same reason as rounds 1
  to 4: the host `composer` shim ("Could not open input file: /usr/local/bin/composer"). That is the
  environment, not the code. I re-ran both with `/usr/bin/php`:
  - `php -l` is clean.
  - PHPCS: 0 errors, 113 warnings, all pre-existing and unchanged.
  - PHPUnit: 184/184 OK.
- GitHub CI for `860be85`, both the push run (35976043425) and the PR run (35976048632):
  - The JS, PHP 8.1, PHP 8.3 and security-audit jobs passed. `npm run test:integration` passed.
  - **`npm run env:drill` failed** in both runs. It is the first drill failure on this branch; every earlier
    run passed it, including `f9c2b65`.
  - The job then hangs in "Container logs on failure" (see finding 1).
  - I reproduced the drill failure locally (`drill.sh: FAIL / hash changed`) and found the cause (finding 1).
    The local wp-env was left in the seeded state.
- `git stash list` is empty, and the tree was clean before this document.
- Mutation sample (the R4-01 mechanic). I swapped `scripts/lib/prepare-classic.mjs` to
  `preprocessShortcodes( autoParagraphPlainText( content ), postId )`. Both `prepareClassicHtml` tests then
  failed:
  - "runs the shortcode pre-pass before autop…"
  - "pins the <br /> path…"

  I restored the file with `git checkout`, and it has no diff. `scripts/convert-classic.mjs:148` is still
  the only production caller, through `prepareClassicHtml()`.

R4-01 does exactly what round 4's finding 1 asked:
- It pins the exact code body (`'a\n\nb'`, `'a\nb\n\nc'`).
- It asserts there is no `<p><pre` and no `</pre></p>`.
- It keeps both tests as plain `it()`, not `maybeIt`.
- It changes no production code.

## Findings (most severe first)

### 1. `env:drill` is red in CI: the seed gives posts identical `post_date`s, so seeded pages change between requests (tests / SPEC §1.3, §6.9; P5-01, seeder from P0-08)
- **Where:**
  - `plugins/ttm-core/src/Cli/Seeder.php:427-438`: `Clock::now()` is read separately for each row, and the
    `weekday` walk-back steps back whole days.
  - `scripts/live/drill.sh:24-29`: the page hashes are recorded only once.
  - `.github/workflows/ci.yml:68-70`: `npx wp-env logs tests` runs without `--watch=false`.
- **What's wrong:**
  - `journal-post-1` has `days_ago: 0, weekday: "Sunday"`. On a Thursday it walks back 4 days, which is
    the same day as `journal-post-4` (`days_ago: 4`). Both rows are inserted within the same second, so
    they get the same `post_date`. Today's seed has `2026-09-20 01:57:20` for both
    `journal-post-4,journal-post-1`.
  - On a Wednesday, the same walk-back collides with `journal-post-3`. The frozen clock of
    `SeederTest::test_journal_post_one_is_on_a_sunday_with_location_and_syndication` gives exactly that
    tie.
  - The seed has two other ties:
    - `salt-water-wires-ch-24` and `reading-cves-part-4`. The Reading CVEs series is fixture data added
      by this flight.
    - `classic-post` and `what-coordinated-disclosure-costs`.
  - `ORDER BY post_date DESC` has no tiebreaker, so MySQL returns tied rows in any order. The front
    page's journal column showed `journal-post-1` on 8 of 12 consecutive `curl`s and `journal-post-4` on
    the other 4. The hash `drill.sh` records for `/` is therefore a coin toss, and the drill fails on
    any restore that returns the other order.
  - HANDOFF (round 3) put the local drill failure down to "sandbox flakiness". That diagnosis was wrong.
- **What would break:**
  - SPEC §1.3 "Done" and P5-04 require the drill to show the site "byte-identical on five screens" and CI
    to be green including the drill. At HEAD both CI runs are red, so that acceptance criterion fails.
  - The drill cannot tell real restore data loss from seed nondeterminism.
  - A failing integration job never finishes: `wp-env logs` defaults to `--watch=true`, so the
    `if: failure()` step streams logs until the 6-hour job timeout. The run above was still stuck there at
    08:59 UTC, 24 minutes after the job started. A green integration job, drill included, takes about 6
    minutes.
  - Any e2e row that reads the front page's journal column, or a section list with a tie, can flake on
    the wrong weekday.
- **Minimal fix:**
  - Make the seeder give every seeded post a distinct, deterministic `post_date`:
    - Read `Clock::now()` once per `seed_posts()` run.
    - Offset each row by its fixture index in seconds (structural arithmetic, not a tunable), applied
      *before* the `weekday` walk-back, so the weekday check still holds.
  - Make `drill.sh` hash each URL twice before the wipe. Fail with a distinct
    "not deterministic before the wipe" message, so seed nondeterminism is never reported as restore
    data loss.
  - Change the CI log step to `npx wp-env logs all --watch=false || true`.
- **Task:** P5-01 (drill), with the seeder date rule from P0-08.

## Spec issues
- Carried from rounds 1 to 4, still open (none of these is a reason to approve a deviation; the code
  follows the readings recorded in earlier rounds):
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
   the drill.
5. (R1-09) NOT VERIFIED (human): live kickers and section cells show real categories, never Uncategorized.
6. (R1-09/R2-05) `character-quest-service`, `hyper-vvv-windows`, `securing-forms-without-captcha`,
   `the-hackiest-hack-that-ever-was-hacked`, `use-your-head`: owner content pass.
7. (R2-05) NOT VERIFIED (human): `live-front.png`, `live-article-classic.png` and `journal.png` against mock 2c.
8. (R3-03) When `env:live`/`test:live` is re-run against a future export, confirm the count of converted
   screens that run the merged-paragraph check grows as more posts carry `ttm_converted_at`, rather than
   staying at 6.

## Notes
- R4-01 added no new manual check. The P5-04 check "CI green including the drill" is currently **not**
  met (finding 1).
- The comment the R4-01 commit added in `convert-classic.test.js` is accurate, but it restates the review
  finding at length. A one-line pointer would do. This is not worth a task.
- The notes from rounds 2 to 4 still stand, none of them blocking:
  - the `WP_IMPORTING` define leak in `PrimaryCategoryTest`;
  - the `Config.php:104` comment;
  - `Seeder::reset()` comment statuses;
  - `private-data.test.js` temp dirs;
  - `toBe( 18 )` on bounding boxes;
  - `politics_child_fixup()` overwriting a non-Opinion primary;
  - the untested `wasConverted()` try/catch.
