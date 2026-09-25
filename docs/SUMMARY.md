# Build summary: phase 5 (demo content, Playground, open source)

**Merge line:** `refine/2026-09-24`, base `272ec96d6a2b` → head `bf0a933`. 85 commits and 35/35 tasks done (30 planned, plus R1-01..R1-04 and R2-01); none blocked or skipped. There were 3 review rounds, and the final verdict is **APPROVED**. The commits are unsigned by design, so rebase and sign them before you merge.

## What was built

**Phase 0: harness and hygiene (P0-01..P0-09).** Added the flight tolerances (tagged-fixme window, `@wp-playground/cli` and `sharp` devDependencies, stub entry points, demo constants). The author name is now data: `site.author_name`/`site.author_url` Config keys, a `ttm/author-name` binding source, and the mastheads and footer rewired to it. Licence hygiene landed: FSF `LICENSE`, both `readme.txt` files, `docs/fixtures/demo/LICENSE.md`, and version `0.2.0` everywhere. `check-license` and `check-demo` now run in `npm run lint`. The §3.1 fold-ins also landed: SI-13 (Photon rewrite to `home_url()`), SI-16 (undated verse attribution) and SI-17 (CodeColorer `<code lang>` pre-pass and audit flag). The owner screenshot set moved to `docs/feedback/phase-5/`.

**Phase 1: photographs (P1-01..P1-06).** `images.json` and an Openverse fetch script choose, re-encode and credit 13 CC0/PDM photographs. `Cli/DemoImage.php` sideloads them during seeding from a temp copy. `--no-demo-images` keeps the neutral placeholders. The fixtures carry real `featured_image`, alt and caption values, `ttm/story-tiles` emits `is-cover`, and the six `demo-*` fidelity rows are real tests.

**Phase 2: demo WXR, options, blueprint and Playground check (P2-01..P2-08).**
- A spike (`docs/spikes/P2-01.md`) answered the open export, import and Playground-CLI questions first.
- `DemoCommand` (`demo:options`, `demo:verify`) and `seed --now` landed.
- `wxr.mjs` normalises the export into a deterministic WXR with no host and no owner details.
- `build.mjs` writes `.github/{demo-content.xml,demo-options.json,blueprint.json}`.
- `release:pack` builds byte-identical plugin and theme zips.
- `check.mjs` boots a local-variant blueprint in headless `@wp-playground/cli server` and runs the §6.4 assertions.
- CI runs build, determinism and check after the drill.

**Phase 3: README, screenshots and release workflow (P3-01..P3-04).** `npm run screenshots -- --readme` generates eight compressed README screenshots. The README is public, with a Playground link, screenshots, architecture, credits and licence; developer commands moved to `docs/SETUP.md`. A `v*` tag now triggers a workflow that verifies, packs and attaches both zips to a GitHub Release.

**Phase 4: close-out (P4-01..P4-03).** `ALLOW_TAGGED` is back to `false`. The allow-lists are confirmed empty, and the CSS budget and both audits are recorded. The docs index, SETUP, plugin spec and HANDOFF are written. The final demo refresh, seed reset and screenshots are done.

**Review fixes (R1-01..R1-04, R2-01).**
- **R1-01:** the readiness race in `demo:check` is fixed. It now waits for the CLI's `WordPress is running on` line. A loopback mu-plugin in the local variant only lets the Playground photo assertions run for real.
- **R1-02:** eight photographs that were too narrow were re-fetched, and `demo-lead-photo` is restored to width ≥ 1200.
- **R1-03:** three tests kill the mutations that survived round 1.
- **R1-04:** the outputs and screenshots were regenerated, and CI is green including `demo:check`.
- **R2-01:** the Playground server runs in its own process group, and `stopServer()` kills the whole group, so no orphaned server is left behind.

## Decisions that shaped it

Taken from the PLAN Decisions and every Interpretation recorded in HANDOFF and the commits.
- **Phases (PLAN):** the five SPEC §8 phases, run in order. Each phase ends with a screenshots-and-push task.
- **Hygiene (PLAN):** commit signing stays off. `FOUNDRY_FEEDBACK.md`, `docs/phase-1..4/` and the WXR export are never touched. `build/` and `dist/` are never committed.
- **Open questions (PLAN):**
  - Q1: the demo is the seed, with no live posts.
  - Q2: licences are `cc0`/`pdm` only.
  - Q3: dates are relative to the build day.
  - Q4: outputs go in `.github/`, and images, credits and licence in `docs/fixtures/demo/`.
  - Q5: release assets are served through `github-proxy.com`.
  - Q6: one Config key pair, no settings screen.
  - Q7: `sharp` is used in two scripts only.
  - Q8: plain PNGs.
- **Photograph slots (PLAN, P1-04):** the lead is the article screen (`signing-your-options-table`). The second "wide" slot is `technology-post-2`, which keeps the total at 13. Each file is named `demo-<slug>.jpg`.
- **`demo-cells-photo` (PLAN):** only the Technology cell renders an image. The other four cells are asserted through their first item's article hero.
- **`demo-lead-photo` (PLAN, P1-05, R1-02):** asserts the `<img>` `width` attribute ≥ 1200, not `naturalWidth`. P1-05 lowered it to 900. R1-02 restored 1200 after re-fetching the photographs at full width.
- **`demo-about-portrait` (PLAN):** the file is tall (about 2:3), and the rendered box stays at its shipped 3:2.
- **`demo-tile-cover` (PLAN):** `is-cover` is added as a class hook with no CSS.
- **P1-05 selectors:** the lead selector is `.ttm-lead__media img`. The cell-label comparison is case-insensitive because the CSS uppercases the labels.
- **Author name (PLAN):** accessor-only reads, so the literal name exists only in `Config.php`. `byline-link` may return HTML. `Seeder` sets `display_name` from the key. `site.author_url` has no template consumer yet.
- **Licence strings and owner-name scope (PLAN):** `GPL-2.0-or-later` everywhere. `LICENSE` must match the FSF sha256. `Author:` header lines are allowed in both `style.css` and `ttm-core.php`.
- **P0-04 version check:** versions are compared to the first version found, not to a hard-coded `0.2.0`.
- **Demo licence note (PLAN):** the verse sample is excluded from the CC0 grant, with attribution. The owner confirms this.
- **Script constants (PLAN):** script-side tunables live in `constants.mjs` and `screenshots.mjs`, not in Config. The pipeline runs outside WordPress.
- **Oversize candidates (PLAN):** a candidate that is too large is skipped. The JPEG quality is never lowered.
- **R1-02 width source:** `acceptEncoded()` judges the decoded width and bytes, not the width Openverse reports.
- **P1-03 photo choice:** three Openverse queries were rewritten, and 10 candidates were rejected (faces, logos, dominant red, wrong subject).
- **Seeder sideload (PLAN, P1-02, P1-04):**
  - The fixture is copied to `wp_tempnam()` before sideloading.
  - Integration seeds have demo images off by default.
  - Alt text prefers `$row['alt']` over the title.
  - Tests force-delete their uploads, because the database rollback does not remove files.
  - The attachment `post_title` is the alt text, not the CREDITS title. Review round 1 noted this as a harmless drift from PLAN.
- **Demo dates (PLAN):** `seed --now` is pinned to the UTC build day at 12:00:00.
- **P2-04 page dates:** page dates are flattened to a fixed sentinel, because WP stamps pages with the wall-clock time.
- **WXR normalisation (PLAN, P2-03):**
  - Post ids are renumbered from 1001 and term ids from 1, across `wp:category`, `wp:tag` and `wp:term`.
  - Unstable meta is dropped.
  - Owner details are anonymised to `demo`.
  - URLs become `example.com` or raw GitHub.
  - The channel `pubDate` is removed only before `wp:wxr_version`, so the step is idempotent.
- **Term meta (P2-01, P2-04, P2-06):** `wp export` never writes `wp:termmeta`. `build.mjs` reads the series term meta through `wp eval`, and `wxr.mjs` injects it wholesale.
- **Covers (PLAN, P2-04):** generated covers cannot pass rule 53, so they are not in the demo. `ttm_cover_id` is dropped, and the book `cover_id` and `series_id` values are zeroed.
- **Newsletter (PLAN):** `ttm_settings.newsletter.provider = none`.
- **Blueprint (PLAN, P2-06):**
  - `wp site empty --yes` runs before `importWxr`.
  - `demo:verify` counts are inlined into the blueprint.
  - The six post-import `wp-cli` steps collapsed into one `wp eval` step, because php.wasm traps at about the ninth step.
- **Local check (PLAN, P2-06, R1-01, R2-01):**
  - A static `127.0.0.1` server serves the files, and the check runs against `@wp-playground/cli server`.
  - A cookie jar handles redirects manually.
  - Readiness is the CLI's ready line plus one sanity GET.
  - A loopback mu-plugin allows the loopback host (local variant only).
  - The CLI runs detached through `process.execPath`, and the process group is killed.
  - `--keep` logs to a file and prints `kill -TERM -<pgid>`.
- **Output locations (PLAN):** verification and CI build into `dist/demo`. The committed `.github/` outputs were refreshed only in P2-04, P4-03 and R1-04.
- **Zip writer (PLAN):** uses `node:zlib`, fixed timestamps and sorted entries, with no new dependency.
- **Jest ESM limits (P2-04, R2-01):** no `jsdom`, `createRequire` or `import.meta` in modules loaded through dynamic `import()`. WXR checks use a regex/tag-balance approach, and the CLI bin resolves from `process.cwd()`.
- **SI-13, SI-17 (PLAN, P0-06, P0-07):**
  - `photon_origin_url( …, $home )`.
  - A `codecolorer` audit flag.
  - `escapeOnce()` is entity-aware.
- **P0-01:** expanded CLAUDE.md's module map (not in the task scope) to fix a `ScaffoldTest` failure that already existed.
- **P0-08:** screenshots are restructured into `SETS`, and the live-zone code was removed.
- **P3-01:** the phone viewport grew to 390×2200 so clipped captures render.
- **P3-02:** the README screenshot table is HTML, with one `<img>` per line.

## Assumptions still in play

| Key | Where | Final default | Status |
|---|---|---|---|
| `IMAGE_MAX_BYTES` | `scripts/demo/lib/constants.mjs` | 350000 | Tuning task ran (P1-03); kept. Largest file after R1-02 is 327,407. Measured, not lowered. |
| `IMAGE_BUDGET_BYTES` | same | 8000000 | Kept. Total is 2,790,264, so the budget is generous and still a guess. |
| `SCREENSHOT_MAX_BYTES` | `scripts/screenshots.mjs` | 1500000 | Tuning task ran (P3-01); kept. `article-1280.png` needs the palette fallback (704,453 bytes). |
| `cssBudgetBytes` | `scripts/check-budget.mjs` | 63488 | Recorded in P4-01: 63090 used, no CSS change. |
| `excerpt_length` | `Config.php` | 55 | Carried guess, untouched. |
| `migration.image_timeout` | `Config.php` | 20 | Carried guess, untouched. |
| `seed.image_band_angle` | `Config.php` | 30 | Carried guess, untouched. |

These are script constants that the plan did not flag with ⚠️: `OPENVERSE_MIN_WIDTH` 1600 (now enforced on the decoded file), `OPENVERSE_PACE_MS` 3500, `OPENVERSE_PAGE_SIZE` 20, `PLAYGROUND_BOOT_TIMEOUT_MS` 600000, and `server-process.mjs` `DEFAULT_GRACE_MS` 5000.

## Spec issues (edits to make in SPEC.md)

1. §6.2: the lead is the article screen, so "5 wide + 2 wide" double-counts one post. The second slot is `technology-post-2`, and the total stays 13.
2. §6.9 `demo-cells-photo`: "≥ 4 cells have an img" is impossible without a visual change. Restate it as the Technology cell plus the first-item article heroes.
3. §6.9 `demo-lead-photo`: `naturalWidth` depends on `srcset`. Assert the `width` attribute instead.
4. §6.9 `demo-about-portrait`: the file is 2:3, but the rendered box stays 3:2 (07 Q13).
5. §6.9 `demo-tile-cover`: the block never emitted `is-cover`. It is now a class hook.
6. §6.3 step 3: there is no `ttm_newsletter` option. Use `ttm_settings.newsletter`.
7. §4/§5: nothing consumes `site.author_url` yet.
8. §6.7 / rule 55: allow the plugin header `Author:` in `ttm-core.php`, not only in `style.css`.
9. §6.2/§6.3: generated covers cannot pass rule 53, so the demo WXR has no covers (F21 on Writing).
10. Rule 55: the verse sample in `demo-options.json` is not CC0. It is excluded in `LICENSE.md`, and the owner confirms.
11. Rule 54: same-day determinism needs a pinned `--now`, id renumbering and stamp removal. SPEC should name these.
12. §6.4: `primary:assign` skips posts that have meta, and the exported term id is not remapped. The meta is dropped from the WXR.
13. §6.4 "7 section cells": assert the seven section labels, not seven `.ttm-cell`s.
14. §6.4: a fresh Playground's "Hello world!" fails `demo:verify`. Add `wp site empty --yes`.
15. §6.4 names `run-blueprint`. The assertions need `@wp-playground/cli server`.
16. §7: `extraVerify` running `demo:build` rewrites the committed files. Build into `dist/demo`.
17. §3.1 SI-17: the audit never matched `<code lang>`. A `codecolorer` flag was added.
18. §6.9: there is no front-masthead `name-*` row. It is covered by existing rows plus `AuthorNameSourceTest`.
19. §6.4 (review rounds 1–3): the `.ttm-lead-story img` selector never matches. Use `.ttm-lead__media img`.
20. §6.3 (review rounds 1–3): the core exporter never writes `wp:termmeta`. The WXR injection is required.
21. §6.4 (review rounds 1–3): six separate `wp-cli` blueprint steps trap php.wasm. Describe one combined `wp eval` step.
22. §6.1 (review rounds 1–3): require the decoded download to meet `OPENVERSE_MIN_WIDTH`. The width Openverse reports can be a 960px rendition.
23. §6.1/§6.4 (P2-06, R1-01): the local Playground variant needs a loopback allowance (`http_request_host_is_external` / `http_allowed_safe_ports`), or WordPress's SSRF guard blocks the attachment import. Readiness should be defined as the CLI's ready line.

## Manual checks owed

- **Phase 0 (P0-09, optional):** in `docs/feedback/phase-5/front.png` and `article.png`, the masthead "by Eric Mann" and the footer line should be unchanged.
- **Phase 1 (P1-06, R1-02):** review the 13 demo photographs (8 were re-fetched in R1-02), the six `docs/feedback/phase-5/*.png` and the eight `.github/screenshots/*.png` for fit, crop and taste. Look for no faces, no brand text, no dominant red and no placeholder bands. Review round 2 flagged "CHINA" stamped on the keys in `demo-hardening-part-4-…jpg` and some red in `demo-php-85-readonly-classes.jpg`. To swap a photo, add its id to `exclude` in `images.json`, then run `npm run demo:fetch-images -- --only=<file>`.
- **Phase 2 (P2-06/07/08, R1-01, R1-04, R2-01):**
  1. Run `npm run demo:check -- --keep`.
  2. Click through `/`, the article, `/series/`, `/writing/` and the post editor. Blocks should load with no build-fallback notice, and the lead photo crop should look right.
  3. Stop the server with the printed `kill -TERM -<pgid>`.
  4. Confirm that `pgrep -af wp-playground` is empty.
- **Phase 3 (P3-04):** read `README.md` on GitHub as a stranger would. Screenshots should render and links should resolve. The Playground link only works after `v0.2.0` is released.
- **Owner steps after merge:**
  1. Rebase and sign, then merge.
  2. `git tag v0.2.0 && git push origin v0.2.0`.
  3. Confirm that both release assets are attached and each unpacks to one top-level directory.
  4. Open the README Playground link and confirm it boots.
  5. Confirm the verse-sample note in `docs/fixtures/demo/LICENSE.md`.

## Review history

- **Round 1: CHANGES REQUESTED.** 4 findings, 4 fix tasks (R1-01..R1-04), not non-converging.
  - F1: a readiness race in `check.mjs` was misdiagnosed as a Playground limitation, and rule 57 was red in CI.
  - F2: the Playground photo assertions were skipped.
  - F3: 8 of the 13 photographs were narrower than `OPENVERSE_MIN_WIDTH`, and the lead threshold had been lowered to hide it.
  - F4: three mutations survived.
- **Round 2: CHANGES REQUESTED.** 1 finding, 1 fix task (R2-01), not non-converging. F1: every `demo:check` run left the Playground server orphaned, and R1-04's `process.exit(0)` hid it.
  - This was not a recurrence of a finding id.
  - It was, however, a second defect in the same `demo:check` process-handling area that R1-01 and R1-04 touched: round 2 names R1-04's fix directly.
- **Round 3: APPROVED.** 0 findings, 0 fix tasks.
  - This was a **notes-only approval**: the approving REVIEW.md carried a `## Notes` section with items that were flagged but not queued as work.
  - **Interrupting the check still orphans the server.** A Ctrl-C or SIGTERM to `demo:check` can orphan the detached server, because `check.mjs` has no signal handler and `--keep` does not print the pgid until boot completes.
  - **The stop wiring is untested.** Nothing tests that `check.mjs` calls `stopServer()`; only the `process.exit(0)` backstop would notice if it were removed.
  - **Failed tests leak processes.** `demo-server-process.test.js` leaks its fixture processes when a test fails, because it has no `afterEach` cleanup.
  - **Stale count in HANDOFF.** `docs/HANDOFF.md:6` still says "four review-fix (`R1-*`) tasks"; the total is 35.

## Pipeline friction

`feedbackCount` is 11. `.foundry/feedback.jsonl` also holds entries from earlier flights on this branch history. They are listed here in the order they were logged, with stage, category and message:

1. **plan / ambiguous-prompt:** plan-build says to commit exactly PLAN.md, PROGRESS.md, foundry.json and CLAUDE.md, but `docs/SPEC.md` was untracked, and so was a `.gitignore` line it relies on. The skill gives no guidance for that state.
2. **implement / tool-refusal:** P2-08's `foundry_verify` extraVerify (`env:live` + `test:live`) reported `ok:false` on an expected, documented stop before the shortcode pre-pass task. The harness cannot express "expected to stop partway through".
3. **implement / tool-refusal:** the same happened on P3-03. Only 7 documented residual posts remained out of scope, but the binary pass/fail produced a false red again.
4. **implement / environment:** `~/.local/bin/php` was rewritten into a shim that routes to another project's Docker container, so `composer lint` and `test:unit` failed. The workaround was calling `/usr/bin/php` directly.
5. **review / environment:** `foundry_verify`'s composer steps failed because of the same shim, and the reviewer had to rerun them by hand.
6. **implement / other:** the same shim broke `foundry_verify` every round, costing one verify cycle per round. `foundry_verify` cannot be pointed at a different php binary.
7. **implement / env:** the same shim silently broke `php -l`. The workaround was `/usr/bin/php8.3`.
8. **review / stall:** `foundry_verify` chaining `test:integration`, `env:live`, `test:live` and `env:drill` exceeded the 1800 s MCP idle timeout. It returned no results, and its child processes kept holding wp-env. This cost 30 minutes.
9. **plan / ambiguous-prompt:** plan-build asks for `docs/foundry.json` to be quoted verbatim, but it is 777 lines. The planner quoted the settings and listed the constraint ids instead.
10. **implement / tool-timeout:** `foundry_verify`'s 1800 s idle timeout was repeatedly tripped by headless Playground boots that took 25–50 minutes. The implementer fell back to running the commands by hand. Review round 2 later identified orphaned Playground servers from earlier `demo:check` runs as the likely cause of the load, not only unrelated processes.
11. **implement / tool-timeout:** the same timeout, worse: several extraVerify chains fanned out, and one Playground boot ran for more than 68 minutes before it was killed. The implementer relied on lint, unit, integration (629) and e2e (501) tests run by hand.
