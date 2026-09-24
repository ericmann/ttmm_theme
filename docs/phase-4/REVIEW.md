# Review — phase 4 (real content), branch `refine/2026-09-23`
Round: 7

**Verdict: APPROVED**

I reviewed `aa497b2..df3ddc5`. Rounds 1 to 6 reviewed the branch through `e1bfacd`. Since the
round 6 review (`b24baa1`), the only code change is the R6-01 task commit (`87d8795`). It is
test-only and touches `tests/integration/Cli/SeederTest.php` and `tests/e2e/editors.spec.mjs`. The
other commits are progress, handoff and state commits. I read R6-01 in full against its PLAN entry
and SPEC §1.3, re-ran the branch-wide gates, and checked CI for the head commit. I ran all of the
following myself.

**Branch-wide gates**
- `foundry_verify`, no files: all 26 constraints are clean, with no fixture failures.
  - Green: `npm run lint` (CSS 63090/63488 bytes; coverage 191/191 with 0 pending; `check-fixme`
    clean), `npm run test:unit` (113 passed, 6 skipped), `npm run build` and
    `forbidden-patterns.sh`.
  - `composer lint`/`test:unit` fail only on the host `composer`/`php` shim, as in rounds 1 to 6.
    With `/usr/bin/php` first on `PATH`, both pass: PHPUnit 184/184, and PHPCS reports 0 errors.
- `git stash list` is empty, and the tree was clean before I wrote this document.

**R6-01 checks**
- Integration, on the local wp-env: `--filter SeederTest` gives 47/47 and 480 assertions.
- Seeder mutation: I moved `->modify( '-' . (int) $index . ' seconds' )` in `Seeder::seed_posts()`
  to after the weekday walk-back.
  - The after-midnight test now **fails**: it expected `'Sunday'` and got `'Saturday'`.
  - I restored the file with `git checkout`, and it has no diff.
  - This closes round 6's finding 1.
- The weekday assertion now reads site-local `post_date` through `wp_timezone()`, which matches how
  `seed_posts()` writes it.
- Login mutation: I removed the submit and `waitForURL` from `login()`.
  - Both rows now fail on the new `not.toContain( 'wp-login.php' )` assertion. The message names the
    `wp-login.php?…&reauth=1` URL instead of "19 blocks missing".
  - I restored the file.
- `editors.spec.mjs` at the default configuration (1 worker, because the two rows share a file) is
  green on 3 of 3 local runs.
- GitHub CI for head `df3ddc5`: both runs are fully green.
  - Push run 35998084081 and PR run 35998090780 pass every job: JS, PHP 8.1/8.3, security,
    `test:integration`, `env:drill` and Playwright + axe.
  - Playwright + axe has 492 passed, including `editor-sed` and `editor-customizer`.
  - This closes round 6's finding 2 and the "CI green" part of P5-04.

No constraint, boundary or test finding remains open on the branch, and there are no blocked or
skipped tasks.

## Findings (most severe first)
None.

## Spec issues
These are carried from rounds 1 to 6 and are still open. None of them is a reason to accept a
deviation; the code follows the readings recorded in earlier rounds.
- §6.11 says `single-other` has a count of 4, but the seed has 3.
- §5 calls `excerpt_length` "existing".
- §6.7: the Photon target during rehearsal.
- §6.10 says "kicker equals the primary category"; it should say "the kicker's first term".
- §6.8 "exactly once each" is ambiguous when two notes have identical text.
- `docs/01-design-language.md:166` still has the dated verse attribution.
- §1.2/§6.8 miss CodeColorer's `<code lang>` **tag** syntax. 40 posts use it, and 5 of those open
  with a raw `<?php`. SPEC should either add a pre-pass row or name these posts as owner cleanup.

## Manual checks still owed (from HANDOFF.md)
1. (P0-07) NOT VERIFIED (human): compare `docs/feedback/phase-4/archive-business.png` with mock `1e`
   line 933.
2. (P1-06) NOT VERIFIED (human): on the seeded site, open
   `/transients-object-caches-and-fast-enough/`, `/series/hardening-wordpress/` and `/`, and compare
   them with mocks `2b`, `1f` and `2a` line 327.
3. (P4-05) NOT VERIFIED (human): the owner reads LIVE-TRIAGE.md and opens `/`, `/keeping-fresh/`,
   `/category/technology/`, `/writing/` and `/series/` on the live import.
4. (P5-04) NOT VERIFIED (human): compare every `docs/feedback/phase-4/*.png` with its mock. The CI
   part of this check is now done: both runs for `df3ddc5` are green, including the drill and e2e.
5. (R1-09) NOT VERIFIED (human): confirm that live kickers and section cells show real categories,
   never "Uncategorized".
6. (R1-09/R2-05) Owner content pass on `character-quest-service`, `hyper-vvv-windows`,
   `securing-forms-without-captcha`, `the-hackiest-hack-that-ever-was-hacked` and `use-your-head`.
7. (R2-05) NOT VERIFIED (human): compare `live-front.png`, `live-article-classic.png` and
   `journal.png` with mock 2c.
8. (R3-03) When `env:live`/`test:live` runs on a future export, confirm that the number of converted
   screens running the merged-paragraph check grows. It should not stay at 6.
9. (R5-01) When the CI "Container logs on failure" step runs, confirm that it finishes in minutes.
   It has not run yet, because the integration job passed.

## Notes
- **The R6-01 log misdiagnoses the `--repeat-each=10` failures at the default worker count.** It
  blames "resource contention" and says the login assertion never fired.
  - I reproduced the failures. `--repeat-each=5` with default workers gave 2 of 10 failing.
  - Both failed inside `login()`'s `waitForURL`, after the POST was redirected to
    `wp-login.php?redirect_to=…wp-admin%2F&reauth=1`.
  - The likely cause is several workers logging in as the same admin at once. WordPress's
    `session_tokens` user meta is updated by read-modify-write, so one login's token can be lost.
  - This cannot happen in a normal run. The two rows share one file, and the config does not set
    `fullyParallel`, so they run one after the other in one worker. CI is green. The PLAN's
    `--repeat-each=10` verification line simply doesn't work as a probe for a same-user login.
  - If this ever matters, the fix is to log in once through a `storageState` setup project. It is
    not a task.
- `SeederTest` builds `now` as `sprintf( '2026-09-24 00:00:%02d', max( 0, $index - 20 ) )`. If a
  fixture reorder ever gave `journal-post-1` an index of 80 or more, that would produce an invalid
  seconds field. It would fail loudly rather than pass silently, so it is not worth a task.
- The notes from rounds 2 to 6 still stand, and none of them blocks:
  - `drill.sh`'s header comment describes the old sequence;
  - the `WP_IMPORTING` define leak in `PrimaryCategoryTest`;
  - the `Config.php:104` comment;
  - the `Seeder::reset()` comment statuses;
  - the `private-data.test.js` temp dirs;
  - `toBe( 18 )` on bounding boxes;
  - `politics_child_fixup()` overwriting a non-Opinion primary;
  - the untested `wasConverted()` try/catch;
  - the verbose R4-01 test comment.
