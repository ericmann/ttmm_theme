# Phase 5 handoff — demo content, WordPress Playground, open source

Branch `refine/2026-09-24` from `main`. This flight fixed the four owner-reported defects from
phase 4, then built a full demo/open-source pipeline: real committed photographs, a generated
WordPress Playground blueprint, a public README, a tagged release workflow, and CI wiring for
all of it. All 30 originally planned tasks plus four review-fix (`R1-*`) tasks are done; this
document and the final screenshot/seed refresh close the flight.

## What changed, by phase

**Phase 0 — harness and hygiene.** Flight tolerances (`ALLOW_TAGGED`, new devDependencies
`@wp-playground/cli`/`sharp`, stub entry points for the demo/release scripts). Author name
became data: `Config::author_name()`/`author_url()`, a new `ttm/author-name` binding source, the
masthead/footer literal "Eric Mann" strings replaced with bindings. Licence hygiene: `LICENSE`
(byte-identical FSF GPL-2.0), both `readme.txt`s, `docs/fixtures/demo/LICENSE.md`, version bumped
to `0.2.0` everywhere, `check-license`/`check-demo` added to `npm run lint`. Three live-triage
fixes (SI-13 Photon rewrite to `home_url()`, SI-16 undated verse attribution, SI-17 a CodeColorer
`<code lang>` audit flag). Owner screenshots moved to `docs/feedback/phase-5/`.

**Phase 1 — real photographs.** `scripts/demo/images.json` + `scripts/demo/lib/openverse.mjs`
search/select/credit Openverse CC0/Public Domain photographs; `Cli/DemoImage.php` sideloads them
during seeding (`--no-demo-images` falls back to the old placeholder). All 13 photographs were
fetched, manually reviewed for taste/identifiability, and several re-fetched with adjusted
queries or exclusions (see P1-03's log). Fixture rows (`posts.json`/`pages.json`) got real
`featured_image`/`alt`/`caption` values; `story-tiles` gained an `is-cover` class for the
photographed tile. The six `demo-*` fidelity rows are real, passing tests, not `test.fixme(`.

**Phase 2 — the demo build and Playground check.** A 90-minute spike (`docs/spikes/P2-01.md`)
answered the export/import/Playground-CLI questions before any code was written, and found two
real PLAN assumptions were wrong (see "Spike findings" below). `DemoCommand` (`demo:options`,
`demo:verify`) and `seed --now` landed; `scripts/demo/lib/wxr.mjs` normalises a `wp export` into
a deterministic, host-free, owner-free WXR; `scripts/demo/build.mjs` runs the whole pipeline and
writes the three committed `.github/` outputs; `scripts/release/pack.mjs` builds deterministic
plugin/theme zips; `scripts/demo/check.mjs` boots the blueprint headless in Playground (or checks
a real site with `--url`) and asserts the SPEC §6.4 pages. CI runs the build/determinism/check
steps after the drill. Four real bugs were found and fixed only by actually running the full
pipeline against real content (see below).

**Round 1 (review fixes) — the headless boot race and the real F3 cause.** `npm run demo:check`
(no `--url`) was red in CI: `waitForBoot()` resolved on the front page's own HTML looking
populated, which raced the blueprint's own final `wp eval` rebuild step — the worker pool starts
answering requests before that step finishes, so the article/series/writing pages fetched right
after could still hit pre-import state even though the front page's own probe had already passed.
`scripts/demo/lib/boot.mjs`'s `isReady()` now waits for the `@wp-playground/cli` process's own
"WordPress is running on" line, which the CLI only prints after every blueprint step (including
the rebuild) has completed; `check.mjs` then makes exactly one sanity GET of `/` before asserting
any page. Fixing the race also exposed and fixed the local variant's real attachment gap: it
was never actually a Playground limitation as first believed, but WordPress's own SSRF guard
(`wp_http_validate_url()`) rejecting the loopback static server by default. The local variant now
ships a one-off mu-plugin (written by a `mkdir`+`writeFile` step before `importWxr`) that allows
that run's own host/port through `http_request_host_is_external`/`http_allowed_safe_ports`, so
attachments import for real in both the local-variant and `--url` paths; the demo-photograph
assertions in `check-assertions.mjs` now run unconditionally, with no skip option. Separately,
review F3 found that 8 of the 13 demo photographs had shipped narrower than
`OPENVERSE_MIN_WIDTH` — Openverse's own search-result `width` metadata disagreed with the file
its `url` field actually serves for some providers (stocksnap/rawpixel proxy everything through a
fixed 960w/1024px thumbnail regardless of the claimed width). `acceptEncoded()` now judges the
real decoded width/bytes after `sharp` re-encoding, not the API's metadata; all 8 flagged photos
were re-fetched and hand-reviewed for taste. See R1-01/R1-02's task log entries in
`docs/PROGRESS.md` for the full detail.

**Phase 3 — public-facing.** Eight optimised README screenshots (`npm run screenshots --
--readme`, `sharp`-compressed under `SCREENSHOT_MAX_BYTES`); the public README (open-in-Playground
link, screenshot table, architecture, credits, licence); a tagged-release GitHub Actions workflow
(`v*` tag → verify → pack → attach both zips to a Release).

**Phase 4 — closing.** Fixme tolerance ended (`ALLOW_TAGGED = false`); allow-lists confirmed
empty; the CSS budget and both security audits recorded; this handoff.

## Manual checks owed (every phase's `Manual check` line)

- **P0-09**: optional — `front.png`/`article.png` masthead "by Eric Mann" and footer line
  unchanged.
- **P1-06**: owner reviews the 13 demo photos and the six phase-5 screenshots for fit/crop/taste;
  swap a photo by adding its Openverse id to `scripts/demo/images.json`'s `exclude` array and
  running `npm run demo:fetch-images -- --only=<file>` (documented in `docs/SETUP.md`).
- **P2-06/P2-07/P2-08/R1-01**: owner runs `npm run demo:check -- --keep` and clicks through the
  four §6.4 pages (front, article, series, writing) plus the post editor on the booted Playground
  instance it leaves running, confirming what CI's automated assertions can't see (visual layout,
  the lead photo's crop, the editor sidebar).
- **P3-04**: read `README.md` on GitHub (the branch view) as a stranger — screenshots render,
  links resolve, the Playground link works once `v0.2.0` is tagged and released.

## Measurements

- **Demo images**: 13 photographs, 2,790,264 bytes total, largest 327,407 bytes, every one
  exactly 1600px wide (R1-02: `OPENVERSE_MIN_WIDTH`, enforced against the real decoded width, not
  Openverse's own metadata) — both the per-file and total figures stay well under
  `IMAGE_MAX_BYTES` (350000) and `IMAGE_BUDGET_BYTES` (8000000); neither constant was tuned.
- **README screenshots**: all eight fit under `SCREENSHOT_MAX_BYTES` (1500000);
  `article-1280.png` (the longest page, already the closest to the ceiling at the end of the last
  flight) needed the palette-reduction fallback this time (1,828,005 → 704,453 bytes, ~47% of the
  limit); every other screenshot fit with the lossless re-compression step alone.
- **CSS budget**: `ttm.css` is 63090 bytes against a 63488-byte budget at the end of this flight
  (unchanged by this flight's own CSS-free tasks); kept as-is (no >1024-byte shrink to justify
  lowering it).
- **Security audits**: `npm audit --audit-level=high` (0 vulnerabilities) and `composer audit`
  (no advisories) both pass; no override was needed.
- **Demo build determinism**: `npm run demo:build -- --check-determinism` produces byte-identical
  `.github/demo-content.xml`/`demo-options.json`/`blueprint.json` on two consecutive runs.
- **Release zips**: `npm run release:pack` produces byte-identical `dist/ttm-core.zip`/
  `dist/ttm-theme.zip` across runs (verified by sha256).

## Interpretation choices (from the task log)

- **P2-01/P2-03/P2-04**: `wp export` never emits `<wp:termmeta>` in any invocation — confirmed by
  grepping the bundled `wp-cli/export-command` phar itself, zero matches. `build.mjs` reads each
  series term's `ttm_status`/`ttm_form` directly via `wp eval` after seeding, and `wxr.mjs`'s
  `normalizeWxr()` injects it into the WXR, replacing any existing termmeta wholesale so re-runs
  stay idempotent.
- **P2-04**: `page`-type items never get an explicit `post_date` from `wp_insert_post()` — WP
  defaults it to the real wall-clock moment of the seed run, breaking the "two builds on the same
  day are identical" determinism promise. `wxr.mjs` flattens a page's `post_date`/`post_date_gmt`/
  `pubDate` to a fixed sentinel (pages carry no meaningful chronological ordering the front end
  displays).
- **P2-04**: `ttm_books[].series_id`, like `cover_id`, is a raw source-site term id the importer
  never remaps — `DemoCommand::options()` zeroes it too.
- **P2-04/P3-01**: dynamically `import()`-ing an `.mjs` module that itself has a top-level
  `import { createRequire } from 'node:module'` (even just to lazily `require('jsdom')`) fails
  Jest with "Must use import to load ES Module" under this project's Jest setup — confirmed with
  a minimal repro. `demo-checks.mjs`'s WXR/page checks use dependency-free regex/tag-balance
  checks instead of `jsdom`.
- **P2-06**: Playground's `php.wasm` hits a hard "memory access out of bounds" trap by roughly the
  ninth separate `wp-cli` blueprint step in one boot, regardless of which command or how much
  content (confirmed with 0/30/124 WXR items, and by removing/reordering steps). The six
  post-import `wp-cli` steps became one combined `wp eval` step calling the same command classes
  directly.
- **R1-01**: `P2-06`'s `fetchAttachments`-never-downloads finding was itself only half the story
  — the real cause was WordPress's SSRF guard, not Playground (see spike finding 2 above). Once
  the local variant's mu-plugin allows its own loopback host, attachments import for real, so the
  `demo:verify --attachments=<n>` zeroing and `checkPages()`'s skip option were both removed; the
  assertions run unconditionally in every path.
- **P2-06**: Node's global `fetch()` doesn't persist cookies across its own automatic
  redirect-following; Playground's `--login` redirects `/` to itself with a `Set-Cookie`, and a
  bare `fetch()` hits "redirect count exceeded" forever. `check.mjs` carries a shared cookie jar
  across manual redirect-following for every request (boot-readiness itself is R1-01's
  `isReady()`, waiting for the CLI's own ready line rather than probing any page — see above).
- **P3-01**: a `clip` screenshot only ever captures what the *current* viewport renders — the
  390×844 phone viewport had to grow to 390×2200 before the phone shots' clipped capture (the same
  technique the pre-existing `range` crop already used).
- **P3-02**: the README's screenshot table needed to be plain HTML with each `<img>` on its own
  text line, since the task's own verification (`grep -c ".github/screenshots/" README.md`)
  counts matching *lines*, and a markdown table row for a desktop+phone pair would otherwise put
  two references on one line and undercount.

## Config keys added this flight

- `site.author_name` (default `'Eric Mann'`) — `Config::author_name()`.
- `site.author_url` (default `'https://eric.mann.blog'`) — `Config::author_url()`.

No other `Config` keys changed. `IMAGE_MAX_BYTES`/`IMAGE_BUDGET_BYTES`/`SCREENSHOT_MAX_BYTES` are
script constants (rule 24 amendment), not `Config` keys, since the demo/Playground/release
pipeline runs entirely outside WordPress.

## Spike findings (`docs/spikes/P2-01.md`)

1. `wp export` never writes `<wp:termmeta>`, in any invocation — term meta has to be injected
   from a side-channel read of the live site, not carried through the export.
2. Playground's `importWxr` `fetchAttachments` fetches the WXR file itself fine over a local
   `127.0.0.1` server, but never actually downloads an attachment binary from one -- later traced
   (R1-01) to WordPress's own SSRF guard rejecting the loopback host by default, not a Playground
   limitation; a one-off mu-plugin allowing that host/port fixes it.
3. Term meta *does* survive `importWxr` intact once it's present in the WXR.
4. A successful `wp-cli` blueprint step's stdout is never echoed to the CLI's own console, at any
   verbosity — only a failing step's output prints.
5. Boot time: ~46s warm, ~90s cold-ish; comfortably inside the 600000ms
   `PLAYGROUND_BOOT_TIMEOUT_MS`.
6. (P2-07 follow-up) Category descriptions survive `importWxr` unconditionally; the six
   post-import `wp-cli` steps had to become one combined `wp eval` step (a real `php.wasm` memory
   limit, not content-volume-dependent).
7. (R1-01 follow-up) The real cause of the once-red `demo:check` step was a boot-readiness race,
   not per-worker object-cache splitting: the CLI's worker pool starts answering requests before
   the blueprint's own last step (the combined `wp eval` rebuild) finishes. Waiting for the CLI
   process's own "WordPress is running on" ready line before fetching any page removes the race.

## Owner steps

1. Review this branch's diff and the six `docs/feedback/phase-5/*.png` / eight
   `.github/screenshots/*.png` files for taste (photograph fit/crop, no red, no placeholder
   bands).
2. Merge `refine/2026-09-24` into `main` (rebase and sign first — commits on this branch are
   unsigned by design, per `docs/SETUP.md`'s Git workflow section).
3. Tag the release: `git tag v0.2.0 && git push origin v0.2.0`. This triggers
   `.github/workflows/release.yml`, which re-verifies, packs both zips and attaches them to a
   GitHub Release (not a draft, with generated notes).
4. Confirm the two release assets (`ttm-core.zip`, `ttm-theme.zip`) are attached and each unpacks
   to one top-level directory.
5. Open the README's "Open in WordPress Playground" link and confirm it boots successfully; CI's
   integration job now runs `npm run demo:check` (no `--url`) as a real gate (R1-01).
6. Approve or swap any of the 13 demo photographs (`docs/SETUP.md`'s "Replacing a demo
   photograph" section has the steps).
7. Confirm the verse-sample licence note in `docs/fixtures/demo/LICENSE.md` (the sample verse is
   a `dailymedtoday.com` meditation quoting Scripture, reproduced with attribution and excluded
   from the CC0 grant).

## Round 1 (review fixes)

Branch `refine/2026-09-24`, base commit `272ec96d6a2b`, head commit `30f7963`. All 4 `R1-*` fix
tasks done, 0 blocked, 0 skipped.

- **R1-01** (`c9d0157`): `npm run demo:check` (no `--url`) was red in CI on a boot-readiness race,
  not the per-worker object-cache split the phase-5 handoff had speculated — see the "Round 1"
  paragraph under "What changed, by phase" above for the full mechanic. Fixed by waiting for the
  `@wp-playground/cli` process's own ready line (`scripts/demo/lib/boot.mjs`) instead of probing
  any page. Fixing the race also uncovered the real cause of the local variant's attachment gap
  (WordPress's own SSRF guard, not a Playground limitation) and fixed it with a one-off mu-plugin.
- **R1-02** (`07445b0`): review F3 — 8 of 13 demo photographs had shipped narrower than
  `OPENVERSE_MIN_WIDTH` because Openverse's search-result `width` metadata disagreed with the file
  its `url` actually serves for some providers. `acceptEncoded()` now judges the real decoded
  width/bytes; all 8 were re-fetched and hand-reviewed for taste (several first-choice candidates
  rejected for visible brand/product logos, one face, one dominant-red sunset — see the task log
  for the full list of rejections and final choices).
- **R1-03** (`8274de8`): review F4 — one targeted test added per survived mutation
  (`demo:verify` term-meta OR/AND, `checkOwnerName`'s header-file guard, `pluginFiles`'
  `src/editor/` exclusion), each manually confirmed to fail under its exact described mutation.
  No source bugs found; test-only change.
- **R1-04** (`5e36b1c`, `0079196`): close-out — regenerated `.github/` demo outputs (confirmed
  byte-identical via `--check-determinism`) and all screenshots from the R1-02 photographs,
  rewrote this document's stale "Known limitation"/Redis/`--workers=1` passages, and updated the
  Measurements. Also found and fixed a real bug while verifying: `scripts/demo/check.mjs`'s
  `main()` had no `process.exit(0)` on its success path, which let a killed npx-spawned Playground
  child's lingering stdio/keep-alive handles keep the whole script alive indefinitely after
  printing `demo:check: ok` — this hung CI's integration job for over an hour on the first R1-04
  push (cancelled run 36134814876) before the fix. Both the push- and pull_request-triggered CI
  runs for the final commit (`36141623788`, `36141626990`) are fully green, including
  `wp-env integration`'s `demo:check` step and `Playwright + axe`.

**Interpretation choices this round** (all already noted inline above and in each task's own log
entry in `docs/PROGRESS.md`): R1-01's readiness definition (the CLI's own ready line, plus one
sanity GET); R1-02's photograph replacements (8 new subjects, chosen and reviewed by hand, no
automated taste check exists); R1-04's `process.exit(0)` fix, not in its original `Files touched`
list but directly blocking the task's own "CI green including the demo step" goal.

**⚠️ ASSUMPTION config keys**: none introduced or tuned this round. `OPENVERSE_MIN_WIDTH` (1600,
existing script constant, not a `Config` key) was enforced more strictly (R1-02) but not changed.

**What a human must check by hand this round**: everything already listed under "Manual checks
owed" above, plus R1-02's specific photograph swaps (`docs/SETUP.md`'s "Replacing a demo
photograph" section has the steps if any are still not to taste) and R1-04's
`npm run demo:check -- --keep` click-through.

**Anything a reviewer who hasn't seen this code should know**: the two Playground-related fixes
(R1-01's readiness wait, R1-04's explicit exit) both stem from the same class of problem — the
`@wp-playground/cli` process tree does not behave like a normal, promptly-exiting CLI tool under
either racing reads or a killed parent — and both were only found by actually running the full
pipeline repeatedly, not by reasoning about the code. A single local `demo:check` run against the
real Playground CDN can legitimately take anywhere from ~90 seconds to over an hour depending on
host CPU contention (confirmed directly, repeatedly, this round); this is environmental, not a
regression, and is why `foundry_verify`'s own MCP call kept timing out during this round (logged
via `foundry_feedback_log`) even after both fixes landed.
