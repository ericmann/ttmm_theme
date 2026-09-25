# Review — phase 5 (demo content, Playground, open source)
Round: 3

Branch `refine/2026-09-24`, base `272ec96d6a2b`, head `fec0b18`. 35/35 tasks done (30 planned + R1-01..R1-04 + R2-01), none blocked or skipped.

## Verdict

**APPROVED**

R2-01 fixes round 2's F1 at its root. `demo:check` now runs the `@wp-playground/cli` bin directly with `process.execPath` and `detached: true`. The CLI therefore leads its own process group, and its `--experimental-wasm-jspi` respawn joins that group. `stopServer()` sends SIGTERM to the whole group, waits a bounded grace period, then sends SIGKILL, and it resolves only once the group is empty. `check.mjs` awaits `stopServer()` in `finally`.

The reviewer confirmed the fix live on both the normal and `--keep` paths. Categories 1–3 are clean across the whole branch. Earlier rounds reviewed every commit up to `9b5f589` as clean apart from F1; the only code commit since then is R2-01's `14d86c4`.

## Evidence gathered by the reviewer

- **`foundry_verify` (whole repo): ok.**
  - Constraints: 35/35 clean, no fixture failures.
  - composer lint: ok.
  - composer test:unit: 193 OK.
  - npm run lint: ok. `check:license` and `check:demo` are clean, `check:fixme` has 0 tagged, css-coverage has 0 pending, and the budget is 63090/63488.
  - Jest: 224 passed, 6 pre-existing skips. This includes the new `demo-server-process.test.js`.
  - build and forbidden-patterns: ok.
- **CI at `fec0b18`:** runs `36148633182` (push) and `36148638822` (pull_request) are all green. The integration job ran:
  - `demo:build -- --out dist/demo --check-determinism`: "both runs byte-identical".
  - `demo:check -- --from dist/demo`: booted at 14:47:26 and printed `demo:check: ok` at 14:48:25.
- **Reviewer's local `npm run demo:check` (no `--keep`):** exit 0 in 1 m 23 s.
  - During the boot, `ps` showed both the CLI (pid 705714) and its wasm-jspi worker (705721) in pgid/sid 705714.
  - Immediately after exit, `pgrep -af 'wp-playground|wasm-jspi'` was empty and `ss -ltnp` showed nothing on the Playground port. Round 2's orphan is gone.
- **Reviewer's local `npm run demo:check -- --keep`:** exit 0.
  - It printed `pid/pgid 707796; stop it with: kill -TERM -707796`.
  - After the parent exited, the URL still answered: 302, then 200 with the cookie jar.
  - After running the printed command, `pgrep` was empty and the port was free.
  - The log file received the CLI's `Ready! WordPress is running on …` line.
- **Mutations of `scripts/demo/lib/server-process.mjs` (both killed by `npm run test:unit`):**
  - `process.kill( -pgid, signal )` → `process.kill( pgid, signal )`: both new tests fail.
  - Removing the SIGKILL escalation: the SIGTERM-trapping test fails.

  Round 2 already killed eight mutations across `boot.mjs`, `local-variant.mjs`, `check-assertions.mjs`, `openverse.mjs`, `demo-checks.mjs`, `DemoCommand.php`, `license-checks.mjs` and `release/lib/files.mjs`. Those files are unchanged since.
- **Scope and hygiene:** R2-01 touched exactly the files its task lists.
  - No `.gitignore`, CI, blueprint, local-variant or check-assertions changes.
  - `git ls-files dist plugins/ttm-core/build docs/fixtures/live` is empty.
  - `git status --porcelain` is clean.
  - The `@wp-playground/cli` 3.1.55 sources contain no `detached` spawn of their own, so the group kill reaches every process it starts.
- **Docs:** the HANDOFF R1-04 paragraph and the PROGRESS R1-04 entry now name the real cause: the orphaned grandchild held the pipes. The `process.exit( 0 )` comment says the same. The Redis justification is gone from `docs/spikes/P2-01.md`.

## Findings (most severe first)

None.

## Spec issues (SPEC is wrong or stale; not grounds for approval)

These are carried from rounds 1 and 2, and SPEC still does not reflect them. The implementation's responses are correct.

1. §6.4's `.ttm-lead-story img` selector never matches. The block wrapper is `.ttm-lead`, and the check uses `.ttm-lead__media img`.
2. §6.3 says the core exporter writes `wp:termmeta`. It never does, so the WXR injection in `wxr.mjs` is required.
3. §6.4's six separate `wp-cli` blueprint steps trap php.wasm. The combined `wp eval` step is the working shape.
4. §6.1 assumes the `width` Openverse reports is the width of the file at `url`. SPEC should require the decoded download to meet `OPENVERSE_MIN_WIDTH`, which is what R1-02 enforces.

## Manual checks still owed (from HANDOFF.md)

- **P0-09 (optional):** in `front.png` and `article.png`, the masthead "by Eric Mann" and the footer line are unchanged.
- **P1-06 / R1-02:** the owner reviews the 13 demo photographs (8 were re-fetched in R1-02) for fit, crop and taste. The same goes for the six `docs/feedback/phase-5/*.png` and the eight `.github/screenshots/*.png`. To swap a photo, add its id to the `exclude` list in `images.json`, then run `npm run demo:fetch-images -- --only=<file>`.
- **P2-06/P2-07/P2-08/R1-01/R1-04/R2-01:** run `npm run demo:check -- --keep` and click through `/`, the article, `/series/`, `/writing/` and the post editor. Blocks should load with no build-fallback notice. Then stop the server with the printed `kill -TERM -<pgid>`.
- **P3-04:** read `README.md` on GitHub as a stranger would. The Playground link works once `v0.2.0` is released.
- **Owner steps:**
  - Merge (rebase and sign first).
  - Tag `v0.2.0`.
  - Confirm both release assets unpack to one top-level directory.
  - Open the README Playground link.
  - Approve or swap photographs.
  - Confirm the verse-sample licence note in `docs/fixtures/demo/LICENSE.md`.

## Notes (not findings)

- **An interrupted run can still orphan the server.** Because the CLI now runs in its own session (`detached: true` calls `setsid`), a Ctrl-C or SIGTERM to `demo:check` no longer reaches it. `check.mjs` has no SIGINT/SIGTERM handler, and `finally` does not run on a signal death. The reviewer sent SIGINT to npm's process group and saw two outcomes:
  - **During boot (non-`--keep`):** the CLI was reparented to init and kept running, then died about 20 s later. It most likely failed writing its ready line into the dead stdout pipe.
  - **Between the ready line and the end of the page checks:** the CLI writes nothing more after `Ready!`, so it would stay orphaned. That window is only a few seconds.
  - **With `--keep`:** stdio goes to a file, so a Ctrl-C during boot leaves a server running with no stop command printed. The log path is printed.

  The non-interrupted paths are clean, and this was outside R2-01's scope. If it matters later, a follow-up could add `process.once( 'SIGINT' | 'SIGTERM', … )` in `check.mjs` that calls `stopServer()` and then exits, and print the pgid on the "booting" line.
- **`check.mjs`'s wiring is not under test.** Nothing tests that `check.mjs` calls `stopServer()` rather than `.kill()`. Deleting the `await stopServer(…)` in `finally` would still exit 0 through the `process.exit( 0 )` backstop. The mechanic is tested in isolation, and the wiring is covered only by the reviewer's and implementer's `pgrep` checks.
- **Failed tests leak processes.** `demo-server-process.test.js` has no `afterEach` that kills the fixture's group, so a failing run leaves the fixture processes alive. The reviewer's two mutation runs did this and the reviewer cleaned them up by hand. A `try/finally` with `process.kill( -proc.pid, 'SIGKILL' )` would make failures self-cleaning.
- **Stale HANDOFF opening.** `docs/HANDOFF.md:6` still says "plus four review-fix (`R1-*`) tasks". The Round 2 section further down gives the correct total of 35.
