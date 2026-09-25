# Review — phase 5 (demo content, Playground, open source)
Round: 2

Branch `refine/2026-09-24`, base `272ec96d6a2b`, head `802d9a5`. 34/34 tasks done (30 planned + R1-01..R1-04), none blocked or skipped.

## Verdict

**CHANGES REQUESTED**

The four round-1 fixes work. The readiness race is gone, the Playground photo assertions run unconditionally, every demo photograph is 1600 px wide, and all three round-1 survivors are now killed. `npm run demo:check` is green in CI and when the reviewer ran it locally. One defect remains in the rule 57 tool itself. A successful `npm run demo:check` leaves the real `@wp-playground/cli` server running as an orphan: in the reviewer's run it used 1.7 GB RSS and 71 % CPU and kept listening on its port. R1-04's `process.exit( 0 )` hides this instead of fixing it.

## Evidence gathered by the reviewer

- `foundry_verify` (whole repo): ok. Constraints: 35/35 clean, no fixture failures. composer lint, composer test:unit (193), npm run lint (incl. check:license, check:demo, check:fixme `0 tagged`, css-coverage `0 pending`, budget 63090/63488), Jest (222 passed, 6 pre-existing skips), build and forbidden-patterns all green.
- `npm run test:integration`: 630 tests, 1 expected failure under the DemoCommand mutation below, which proves the new test runs. CI run `36141623788` reports `OK (630 tests, 2600 assertions)`.
- CI at `0079196` (the last code commit; later commits touch only HANDOFF/PROGRESS/state): every job green. The integration job log shows `drill.sh: OK`, then `demo:check: booting Playground …` at 13:43:34 and `demo:check: ok` at 13:44:33. The job ran `--from dist/demo`, freshly built from the seed.
- Reviewer ran `npm run demo:check` (default `.github`, local variant with the loopback mu-plugin): **exit 0 in 1 m 38 s**, all §6.4 assertions including both demo photographs.
- Immediately after that exit 0, `ps` showed `node …/wp-playground-cli server --blueprint=/tmp/ttm-demo-check-MF5uLk/blueprint.local.json --port=43277` with **ppid 1**, plus its `--experimental-wasm-jspi` worker (RSS 1,740,684 KB, 71 % CPU). `ss -ltnp` showed it still listening on :43277 2.5 minutes later. The reviewer killed both processes by hand. See F1.
- Photographs: all 13 decode at exactly 1600 px wide with `sharp`. Every CREDITS `width`/`height`/`bytes` matches the file. All are `cc0`. Total 2,790,264 bytes. A contact sheet of the 8 re-fetched images shows no faces or dominant red and fits the stated subjects (taste stays an owner manual check; see Notes).
- `.github/blueprint.json` has no `mkdir`/`writeFile` step. The loopback allowance exists only in the local variant.
- `git ls-files docs/fixtures/live` is empty. `.gitignore`, CI and CLAUDE.md changes since base are the ones round 1 already reviewed. `docs/phase-1..4/` and `FOUNDRY_FEEDBACK.md` are untouched.
- Mutations (all **killed**):
  - `boot.mjs` `isReady` → `output.length > 0`
  - `local-variant.mjs`: drop the mu-plugin steps
  - `check-assertions.mjs`: skip the article photo check
  - `openverse.mjs` `acceptEncoded`: drop the width test
  - `demo-checks.mjs` `checkCredits`: disable `minWidth`
  - `DemoCommand.php` `||` → `&&` (integration)
  - `license-checks.mjs`: drop the `isHeaderFile` guard
  - `release/lib/files.mjs` `src/editor/` → `src/editorX/`

## Findings (most severe first)

### F1 — Category 3/5: `demo:check` orphans the Playground server on every run; `process.exit( 0 )` hides it instead of fixing the cause
- **Where:** `scripts/demo/check.mjs:359-370` (`spawn( 'npx', [ '@wp-playground/cli', 'server', … ] )`), `scripts/demo/check.mjs:386-389` (`playgroundProc.kill()` in `finally`), `scripts/demo/check.mjs:417-422` (R1-04's `process.exit( 0 )` and its comment), `docs/HANDOFF.md` (R1-04 paragraph, "Round 1" section), `docs/PROGRESS.md` R1-04 entry.
- **What is wrong:** the process tree is `npm exec` → `sh -c wp-playground-cli` → `node wp-playground-cli server` → `node --experimental-wasm-jspi` (worker). `playgroundProc.kill()` signals only the top `npm exec` process. The CLI server and its worker are reparented to init and keep running, holding the port and the stdout/stderr pipes. The CI hang R1-04 describes (parent alive after printing `demo:check: ok`) came from those pipes. The commit blames "lingering stdio/keep-alive handles", then forces the parent to exit, so the server now outlives the check silently. The `--keep` path is also fragile: the kept server's stdout/stderr are pipes to a parent that has already exited.
- **What breaks:** every local `npm run demo:check` (the owner's P2 manual check, the `scripts/demo/` extraVerify, SETUP's instructions) leaves roughly 1.7 GB of RSS and a busy WASM worker running until someone finds and kills it. Repeated runs pile up. This very likely explains the 25–60-minute "CPU contention from unrelated processes" boots that the R1-01/R1-02 logs and the `foundry_feedback_log` entries blamed on the environment. On CI, runner teardown hides it.
- **Minimal fix:** move process handling into a small `scripts/demo/lib/server-process.mjs`:
  - Spawn the CLI as the leader of its own process group: `detached: true`, and preferably `process.execPath` + the resolved `@wp-playground/cli` bin rather than `npx`.
  - Stop it by signalling the whole group (`process.kill( -pid, 'SIGTERM' )`), await `exit` with a bounded grace, then `SIGKILL` the group.
  - For `--keep`, redirect the server's output to a log file in the kept temp dir instead of pipes, `unref()` it, and print its PID and the stop command.
  - `process.exit( 0 )` may stay as a backstop, but its comment must state the real cause. Correct the HANDOFF and PROGRESS wording.
- **Task:** new R2-01.

## Spec issues (SPEC is wrong or stale; not grounds for approval)

Carried from round 1, still unamended in SPEC (the implementation's responses are right):

1. §6.4's `.ttm-lead-story img` selector never matches. The block wrapper is `.ttm-lead`, and the check uses `.ttm-lead__media img`.
2. §6.3 says the core exporter writes `wp:termmeta`. It never does, so the WXR injection is required.
3. §6.4's six separate `wp-cli` steps trap php.wasm. The combined `wp eval` step is the working shape.
4. §6.1 assumes Openverse's reported `width` is the width of the file at `url`. SPEC should say the decoded download must meet `OPENVERSE_MIN_WIDTH`, which is what R1-02 enforces.

## Manual checks still owed (from HANDOFF.md)

- P0-09 (optional): `front.png`/`article.png` masthead "by Eric Mann" and footer line unchanged.
- P1-06 / R1-02: the owner reviews the 13 demo photographs (8 re-fetched in R1-02) and the six `docs/feedback/phase-5/*.png` and eight `.github/screenshots/*.png` for fit, crop and taste. Swap a photo via `images.json` `exclude` + `npm run demo:fetch-images -- --only=<file>`.
- P2-06/P2-07/P2-08/R1-01/R1-04: `npm run demo:check -- --keep`, then click through `/`, the article, `/series/`, `/writing/` and the post editor (blocks load, no build-fallback notice). After R2-01, confirm the kept server can be stopped with the printed command.
- P3-04: read `README.md` on GitHub as a stranger; the Playground link works once `v0.2.0` is released.
- Owner steps: merge (rebase and sign), tag `v0.2.0`, confirm both release assets unpack to one top-level directory, open the README Playground link, approve or swap photographs, confirm the verse-sample licence note in `docs/fixtures/demo/LICENSE.md`.

## Notes (not findings)

- `docs/spikes/P2-01.md:176-179` still justifies the blueprint's `wp_cache_flush()` with "Playground boots with a persistent Redis object cache enabled by default". The R1-01 bullet that follows corrects the diagnosis, but R1-01's constraint asked for the Redis explanation to be removed. R2-01 can tidy the sentence while it is correcting the HANDOFF wording.
- `demo-hardening-part-4-keys-in-the-environment.jpg` shows two blank keys with "CHINA" stamped on the bows. It is a country-of-origin mark, not a brand, but it is text on the product. Leave it to the owner's taste check.
- The rainbow fan under the typewriter keys (`demo-php-85-readonly-classes.jpg`) includes some red, but red does not dominate, and it is not the lead (`seed-hero-color` only guards the lead).
- `checkCredits()` enforces `minWidth` on the CREDITS row, not on the decoded file. The reviewer confirmed that every row matches its file, and `acceptEncoded()` writes the row from the decoded width, so the two cannot drift through the fetch path.
