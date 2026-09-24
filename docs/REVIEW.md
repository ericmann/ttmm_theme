# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 6

**Verdict: CHANGES REQUESTED**

I reviewed `aa497b2..e1bfacd`. Rounds 1 to 5 covered the branch through `860be85`. Since the round 5
review (`6517213`), the only code change is the R5-01 task commit (`998388f`). It touches
`Seeder.php`, `SeederTest.php`, `drill.sh` and `ci.yml`. The other commits since then are progress,
handoff, state and feedback commits. I read R5-01 in full against its PLAN entry and SPEC §1.3/§6.9,
and re-ran the branch-wide gates. I ran all of the following myself.

**Branch-wide gates**
- `foundry_verify`, no files: all 26 constraints are clean, with no fixture failures.
  - Green: `npm run lint` (CSS 63090/63488 bytes; coverage 191/191, 0 pending; `check-fixme` clean),
    `npm run test:unit` (113 passed, 6 skipped), `npm run build` and `forbidden-patterns.sh`.
  - `composer lint`/`test:unit` fail only on the host `composer` shim, as in rounds 1 to 5. With
    `/usr/bin/php` they pass: PHPUnit 184/184, `php -l` clean, PHPCS 0 errors, and `Seeder.php`
    still has the same 3 pre-existing warnings.
- The first `foundry_verify` call, with R5-01's files, exceeded the MCP idle timeout. I logged this
  as pipeline feedback.

**R5-01 checks**
- Integration, on the local wp-env: both new `SeederTest` cases pass, including all 7 weekday
  datasets (9/9).
- `npm run env:drill` ran locally: `drill.sh: OK (104 posts, 5 page hash(es) unchanged)`.
- 12 consecutive `curl`s of `/` give 1 unique hash.
- A `GROUP BY post_date HAVING COUNT(*) > 1` query on the seeded DB returns no rows.
- The local wp-env is left in the seeded state.

**GitHub CI for `e1bfacd`**
- The push run (35990637520) is fully green, including `npm run test:integration` and
  `npm run env:drill`.
- The PR run (35990641981) is green except for the e2e job (finding 2). Its integration and drill
  also passed.
- `git stash list` is empty, and the tree was clean before this document.

R5-01 correctly fixes round 5's finding 1:
- It reads `Clock::now()` once per run.
- It subtracts the fixture index in seconds before the weekday walk-back. Distinct indices under
  86400 cannot collide across whole-day shifts.
- `drill.sh` hashes each URL twice before the backup and exits before the wipe on a mismatch.
- The CI log step uses `--watch=false`.
- It adds no bare tunables (rule 24) and no clock calls outside `Clock` (rule 9).

## Findings (most severe first)

### 1. The "just after midnight" test passes with the ordering it guards reversed (tests; R5-01)
- **Where:** `tests/integration/Cli/SeederTest.php`, `test_journal_post_one_stays_on_sunday_when_now_is_just_after_midnight`.
  The test calls `set_now( '2026-09-24 00:00:30' )`.
- **What's wrong:**
  - `journal-post-1` is fixture index **30**. Subtracting 30 seconds from `00:00:30` gives exactly
    `00:00:00` on the same Thursday, so the offset never crosses midnight.
  - The test therefore cannot tell "offset before walk-back" (correct) from "offset after walk-back"
    (wrong).
  - Mutation check: I moved `->modify( '-' . $index . ' seconds' )` to after the weekday loop and
    re-ran the two new tests. All 9 cases still passed. I restored the file with `git checkout`,
    and it has no diff.
  - The timestamp came from the round 5 plan text, not from the implementer. The acceptance test
    still does not test its mechanic.
  - The test also reads `post_date_gmt` with `gmdate()`. The weekday pin applies to the site-local
    `post_date`, so the check is only right while the test site's timezone is UTC.
- **What would break:** A later refactor could apply the seconds offset after the walk-back. The
  seeder would then pin the Sunday journal post to Saturday when it runs in the first `index`
  seconds after midnight, and the test would stay green.
- **Minimal fix:**
  - Use a `now` whose seconds are smaller than `journal-post-1`'s fixture index, for example
    `2026-09-24 00:00:10`. The correct code then walks back from Wednesday 23:59:40 to Sunday; the
    mutated code lands on Saturday 23:59:40.
  - Assert on `post_date` via `DateTimeImmutable( $post->post_date, wp_timezone() )`, rather than on
    `post_date_gmt` with `gmdate()`.
  - Optionally, look up the fixture index from `posts.json` in the test, rather than relying on the
    literal 30.
- **Task:** R5-01.

### 2. `editors.spec.mjs` races its own login, so the head's PR CI run is red (tests; SPEC §1.3 "phase 1–3 suites still pass", P5-04 "CI green")
- **Where:** `tests/e2e/editors.spec.mjs:39-46`, `login()`. The function clicks `#wp-submit` and
  returns at once. The caller then `page.goto()`s the Site Editor or the Customizer.
- **What's wrong:**
  - Nothing waits for the login POST to finish. The next `goto` can abort it before the auth cookie
    is stored.
  - In PR run 35990641981, both `editor-sed` and `editor-customizer` failed. The page snapshot in
    the `playwright-report` artifact shows the **Log In** form, not the editor.
  - The `waitForFunction(...).catch( () => {} )` then swallows the 20 s timeout. The failure is
    reported as "19 `ttm/*` blocks missing", which points at block registration instead of the
    login.
  - The push run on the same commit passed both rows, so this is a timing race.
  - The file dates from phase 3 (P1-09) and this branch does not touch it. It is still the only
    thing keeping HEAD's CI from being green, which the flight's Done criterion requires.
- **What would break:** Random red CI on this and later branches, misdiagnosed as an editor
  registration regression.
- **Minimal fix:**
  - In `login()`, wait for the navigation. Use
    `await Promise.all( [ page.waitForURL( /\/wp-admin\// ), page.locator( '#wp-submit' ).click() ] )`,
    or `waitForURL` straight after the click.
  - In `assertBlocksRegistered()`, first assert that the page is not `wp-login.php`, so that a lost
    session fails with a login message.
- **Task:** P1-09 (phase 3 file), carried under this flight's P5-04 "CI green".

## Spec issues
Carried from rounds 1 to 5 and still open. None of them is a reason to approve a deviation; the code
follows the readings recorded in earlier rounds.
- §6.11 `single-other` count 4 vs the seed's 3.
- §5 calls `excerpt_length` "existing".
- §6.7 Photon target during rehearsal.
- §6.10 "kicker equals the primary category" should read "the kicker's first term".
- §6.8 "exactly once each" is ambiguous for identical note texts.
- `docs/01-design-language.md:166` still has the dated verse attribution.
- §1.2/§6.8 miss CodeColorer's `<code lang>` **tag** syntax (40 posts, 5 of which open with a raw
  `<?php`). SPEC should add a pre-pass row, or name these posts as owner cleanup.

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e`
   line 933.
2. (P1-06) NOT VERIFIED (human): on the seeded site, open `/transients-object-caches-and-fast-enough/`,
   `/series/hardening-wordpress/` and `/`, and compare them with mocks `2b`, `1f` and `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/` and `/series/` on the live import.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock. Confirm CI
   is green including the drill. The drill is now green in CI; the e2e job is not, see finding 2.
5. (R1-09) NOT VERIFIED (human): live kickers and section cells show real categories, never
   Uncategorized.
6. (R1-09/R2-05) Owner content pass on `character-quest-service`, `hyper-vvv-windows`,
   `securing-forms-without-captcha`, `the-hackiest-hack-that-ever-was-hacked` and `use-your-head`.
7. (R2-05) NOT VERIFIED (human): compare `live-front.png`, `live-article-classic.png` and
   `journal.png` with mock 2c.
8. (R3-03) When `env:live`/`test:live` is re-run on a future export, confirm that the count of
   converted screens running the merged-paragraph check grows as more posts carry
   `ttm_converted_at`, rather than staying at 6.
9. (R5-01) Confirm that the CI "Container logs on failure" step finishes in minutes when it runs. It
   has not run yet, because the integration job passed.

## Notes
- The R5-01 HANDOFF says the full `test:integration` suite could not complete locally. CI completed
  it: the integration job passed on both runs for `e1bfacd`, so that gap is closed.
- `drill.sh`'s header comment still describes the old sequence ("seeds, backs it up, records…"). The
  pre-wipe determinism check now runs before the backup. This is not worth a task.
- The notes from rounds 2 to 5 still stand, and none of them blocks:
  - the `WP_IMPORTING` define leak in `PrimaryCategoryTest`;
  - the `Config.php:104` comment;
  - the `Seeder::reset()` comment statuses;
  - the `private-data.test.js` temp dirs;
  - `toBe( 18 )` on bounding boxes;
  - `politics_child_fixup()` overwriting a non-Opinion primary;
  - the untested `wasConverted()` try/catch;
  - the verbose R4-01 test comment.
