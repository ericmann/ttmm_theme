# Phase 5 handoff — demo content, WordPress Playground, open source

Branch `refine/2026-09-24` from `main`. This flight fixed the four owner-reported defects from
phase 4, then built a full demo/open-source pipeline: real committed photographs, a generated
WordPress Playground blueprint, a public README, a tagged release workflow, and CI wiring for
all of it. 28 of 30 planned tasks are done; this document and the final screenshot/seed refresh
(P4-03) close the flight.

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
pipeline against real content (see below); one gap remains open (see "Known limitation").

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
- **P2-06/P2-07/P2-08/P3-04**: `npm run demo:check` (no `--url`) fails against real headless
  Playground on a content-rendering gap that does not reproduce against a real site. See "Known
  limitation" below — this needs a dedicated follow-up before CI's `demo:check` step can be
  treated as a real release gate.
- **P3-04**: read `README.md` on GitHub (the branch view) as a stranger — screenshots render,
  links resolve, the Playground link works once `v0.2.0` is tagged and released.

## Known limitation: headless Playground content-rendering gap

`npm run demo:check` (no `--url`) boots the real demo content in WordPress Playground
successfully (no crash, no timeout — both real bugs fixed this flight, see below) and its own
internal `wp ttm demo:verify` step passes, but four of the pages it then fetches still show
incomplete content: the article permalink 404s, and the front page's series strip, the series
hub, and the Writing page's serial hero all render empty. This **does not** reproduce against a
real site (`npm run demo:check -- --url http://localhost:8888` is fully green, as is every
Playwright/PHPUnit test against the real seeded wp-env). It is most likely a discrepancy between
the blueprint's own `wp eval` step (which runs the post-import rebuild) and the worker process
that later serves each page request, each with its own copy of WordPress's non-persistent
per-process object cache — `--workers=1` fixed it when it worked, but reproduced
`@wp-playground/cli`'s own documented worker/file-lock deadlock warning as a real, repeated,
indefinite hang, which is worse for a release gate than the content gap. Recorded in
`docs/spikes/P2-01.md`'s "Playground result" section and `scripts/demo/check.mjs`'s own docblock.
**This is CI's one red step** (`wp-env integration`'s `npm run demo:check -- --from dist/demo`);
every other job and every other step in that job is green.

## Measurements

- **Demo images**: 13 photographs, 1,585,649 bytes total, largest 320,902 bytes — both well under
  `IMAGE_MAX_BYTES` (350000) and `IMAGE_BUDGET_BYTES` (8000000); neither was tuned.
- **README screenshots**: all eight fit under `SCREENSHOT_MAX_BYTES` (1500000) with the lossless
  `sharp` re-compression step alone (no file needed the palette-reduction fallback).
  `article-1280.png` (the longest page) is the closest to the ceiling, at ~99.8% of the limit —
  a longer article than the seed's own could tip a future run into the palette fallback, which
  is expected, not a bug.
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
- **P2-06**: Playground's `fetchAttachments` never downloads a binary from a bare `127.0.0.1`
  static server (only the WXR file's own fetch works) — the local-variant blueprint's
  `demo:verify --attachments=<n>` is zeroed, and `checkPages()` skips its "has a real demo
  photograph" assertions for the local path only (`--url` mode keeps them).
- **P2-06**: Node's global `fetch()` doesn't persist cookies across its own automatic
  redirect-following; Playground's `--login` redirects `/` to itself with a `Set-Cookie`, and a
  bare `fetch()` hits "redirect count exceeded" forever. `check.mjs` carries a shared cookie jar
  across manual redirect-following for every request, and boot-readiness polls for real front-page
  content rather than any sub-500 response (Playground's workers start accepting requests before
  the blueprint's own import/rebuild step finishes).
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
   `127.0.0.1` server, but never actually downloads an attachment binary from one.
3. Term meta *does* survive `importWxr` intact once it's present in the WXR.
4. A successful `wp-cli` blueprint step's stdout is never echoed to the CLI's own console, at any
   verbosity — only a failing step's output prints.
5. Boot time: ~46s warm, ~90s cold-ish; comfortably inside the 600000ms
   `PLAYGROUND_BOOT_TIMEOUT_MS`.
6. (P2-07 follow-up) Category descriptions survive `importWxr` unconditionally; the six
   post-import `wp-cli` steps had to become one combined `wp eval` step (a real `php.wasm` memory
   limit, not content-volume-dependent); `wp_cache_flush()` was needed for some pages to serve
   real content at all (Playground's default Redis object cache).

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
5. Open the README's "Open in WordPress Playground" link and confirm it boots successfully — see
   "Known limitation" above; this is not guaranteed by CI today.
6. Approve or swap any of the 13 demo photographs (`docs/SETUP.md`'s "Replacing a demo
   photograph" section has the steps).
7. Confirm the verse-sample licence note in `docs/fixtures/demo/LICENSE.md` (the sample verse is
   a `dailymedtoday.com` meditation quoting Scripture, reproduced with attribution and excluded
   from the CC0 grant).
