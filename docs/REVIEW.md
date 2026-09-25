# Review — phase 5 (demo content, Playground, open source)
Round: 1

Branch `refine/2026-09-24`, base `272ec96d6a2b`, 30/30 tasks marked done, none blocked or skipped.

## Verdict

**CHANGES REQUESTED**

The verify set, the full integration suite, all 35 `docs/foundry.json` constraints and forbidden-patterns.sh are green. But the flight's main acceptance gate, rule 57 (`npm run demo:check` green locally and in CI; SPEC §3.2, §8 Phase 2 and Phase 4 "Visible result"), is red, and CI's integration job has been red since P2-07. The handoff calls this an unexplained Playground limitation. It is not: it is a race in `check.mjs`, reproduced and confirmed below. Two acceptance checks were also weakened to match defects instead of fixing them: the Playground photo assertions were skipped, and the `demo-lead-photo` width threshold was lowered. Three mutations survived.

## Evidence gathered by the reviewer

- `foundry_verify` (all files): ok. Constraints: 35/35 clean, no fixture failures. composer lint/test:unit (193), npm lint (incl. check:license, check:demo), Jest (216 passed / 6 pre-existing skips), build and forbidden-patterns all green.
- `npm run test:integration`: 629 tests OK (it ran as part of a mutation run).
- `npm run demo:check` (reviewer run, HEAD): **exit 1**, 5 failures: `article: status 404`, `series: status 404`, front `.ttm-series-row` count 0, `/series/` 0 titles, `/writing/` has no `.ttm-serial-hero`.
- Reviewer experiment (scratch script outside the repo, identical local blueprint and zips, using the repo's own `localBlueprint`, `rebaseAttachmentUrls` and `checkPages`). It waited for `@wp-playground/cli`'s own `Ready! WordPress is running on …` line before fetching, instead of `check.mjs`'s "first 200 containing `ttm-cell-heading__label`" probe. Result: ready after 87 s on the default 6 workers. `checkPages(pages, { skipAttachmentChecks: true })` → `[]`, **all green**. With the attachment checks on, only the two photo assertions fail.
- `git ls-files`: nothing under `dist/`, `plugins/ttm-core/build/` or `docs/fixtures/live/`, and no `*.xml/*.sql/*.csv/*.tar.gz` under `docs/`. `docs/phase-1..4/` untouched. `FOUNDRY_FEEDBACK.md` untouched.
- Mutations: 9 killed (Values empty name, DemoImage name regex, Html Photon home rewrite, openverse min width, demo-checks licence set, wxr orphan `_thumbnail_id`, shortcodes CodeColorer escaping, plus the two below as survivors). Survived: 3 (F4).

## Findings (most severe first)

### F1 — Category 3 (acceptance test red) / 5: rule 57 `demo:check` fails because of a readiness race in `check.mjs`, misdiagnosed as a Playground limitation
- **Where:** `scripts/demo/check.mjs:215` (`waitForBoot`), `scripts/demo/check.mjs:1-20` (docblock), `docs/spikes/P2-01.md:178-186`, `docs/HANDOFF.md` "Known limitation" and line 163.
- **What is wrong:** `waitForBoot()` resolves on the first 200 whose HTML contains `ttm-cell-heading__label`. That is true right after `importWxr`. The blueprint's final `wp eval` step (permalinks, `primary:assign`, recount, `series:rebuild`, `stats:flush`, `demo:verify`) is still running at that point. The implementer's own P2-06 log notes that Playground's workers accept requests before the blueprint finishes. The pages are therefore fetched before permalinks are set (article and `/series/` 404) and before the series index is rebuilt (empty strip, no serial hero). `--workers=1` "fixed" it only because a single worker is busy until the blueprint finishes. The "per-worker object cache" and "Redis object cache" explanations in the spike, the HANDOFF and the docblock are wrong. The reviewer run above passes every non-photo assertion once the check waits for the CLI's ready line.
- **What breaks:** rule 57, and SPEC §8 Phase 2/Phase 4 "Visible result: `npm run demo:check` green locally and in CI". CI's integration job is red on every push, so the Playground path is not a gate. P2-06, P2-07, P2-08 and P4-03 were marked done against red acceptance criteria.
- **Minimal fix:** readiness = the Playground server process has printed `WordPress is running on` (in a pure, Jest-tested helper), with the HTTP probe kept only as a secondary sanity check. Correct the docblock, the spike's "Playground result" section and the HANDOFF.
- **Task:** R1-01 (HANDOFF prose in R1-04).

### F2 — Category 3 / 5: Playground photo assertions skipped, and `demo:verify --attachments` zeroed in the local variant
- **Where:** `scripts/demo/lib/check-assertions.mjs:111` (`skipAttachmentChecks`), `scripts/demo/check.mjs:377` (`checkAgainstUrl( siteUrl, jar, true )`), `scripts/demo/lib/local-variant.mjs:15` (`--attachments=0`).
- **What is wrong:** SPEC §6.4 requires the Playground check to assert that the lead has a `demo-` photograph and that `demo:verify` passes with the real counts. Because the importer "never fetches from 127.0.0.1", the local variant turns both off, so the only headless run never checks a photograph. The likely cause is WordPress's own `wp_safe_remote_get()`, which the importer uses for attachments. It rejects loopback hosts and non-80/443/8080 ports (`wp_http_validate_url`) unless `http_request_host_is_external` / `http_allowed_safe_ports` allow them. The static server runs on `127.0.0.1:<ephemeral>`, so both rules apply. That can be fixed in the local variant only.
- **What breaks:** a WXR whose attachment URLs or `_thumbnail_id`s are wrong would pass CI. Rule 57 asserts less than SPEC says.
- **Minimal fix:** the local variant adds a step that writes a mu-plugin allowing the static server's host and port for `wp_safe_remote_*` (local variant only, never in `.github/blueprint.json`). Remove `skipAttachmentChecks` and the `--attachments=0` rewrite. If the fetch still fails after that, stop and mark the task blocked with evidence rather than skipping.
- **Task:** R1-01.

### F3 — Category 3 / 5: 8 of 13 demo photographs are narrower than `OPENVERSE_MIN_WIDTH`, and `demo-lead-photo` was weakened to hide it
- **Where:** `scripts/demo/fetch-images.mjs:80-103` (`downloadAndEncode`), `scripts/demo/lib/openverse.mjs:117`, `tests/e2e/fidelity.spec.mjs:984` (`toBeGreaterThanOrEqual( 900 )`), `docs/fixtures/demo/CREDITS.json`.
- **What is wrong:** SPEC §6.1 picks results with `width ≥ OPENVERSE_MIN_WIDTH` (1600) and re-encodes them to ≤ 1600 wide. `pickResult()` checks only the width Openverse *reports*; the file behind `url` is often a 960px rendition. `fetch-images.mjs` never re-checks the decoded width. CREDITS rows are 960 wide for `signing-your-options-table` (the lead and article hero), `technology-post-2`, `composer-lockfiles…`, `hardening-part-4…`, `open-source…`, `php-85…` and `story-uptime`, and 1024 wide for `transients…`. The lead renders the 960×641 original, because WP cannot generate the 1600×900 `ttm-lead` crop. P1-05 then lowered the SPEC §6.9 / PLAN threshold (`width` ≥ 1200) to 900 "since the real lead photo is a legitimate 960px download". That is the acceptance test bent to fit the defect.
- **What breaks:** the lead and article heroes are upscaled on every 1280 screen and in the README screenshots, and the §6.9 row no longer guards image quality.
- **Minimal fix:** reject a download whose decoded width is below `OPENVERSE_MIN_WIDTH` (add its id to `skipIds` and try the next result). Make `check-demo` fail on a CREDITS row or file narrower than `OPENVERSE_MIN_WIDTH`. Re-fetch the 8 rows, updating `alt`/`caption` where the photo changes. Restore `demo-lead-photo` to `width` ≥ 1200.
- **Task:** R1-02.

### F4 — Category 3: three mechanics with no test (survived mutations)
- `plugins/ttm-core/src/Cli/DemoCommand.php:144-146`: changing `! $has_form || ! $has_status` to `&&` survives the full integration suite. `test_verify_fails_loudly_on_missing_series_term_meta` inserts a term with *neither* meta, so a term missing only one is never tested. This is exactly the "import dropped one `<wp:termmeta>`" case SPEC §6.3 wants caught.
- `scripts/lib/license-checks.mjs:281`: widening the header allowance from "`Author:` in `style.css`/`ttm-core.php`" to "any line containing `Author`" survives. No test puts ` * Author: <name>` in a non-header file.
- `scripts/release/lib/files.mjs:34`: disabling the `src/editor/` exclusion survives, because the fixture has only `.js` files there, which the `src/**/*.js` rule already drops. A non-JS file under `src/editor/` (e.g. a `.json` or `.css`) would ship in the zip untested.
- **Minimal fix:** one targeted test each; no source change expected.
- **Task:** R1-03.

## Spec issues (SPEC is wrong or stale; not grounds for approval)

1. §6.4 names `.ttm-lead-story img`. The lead-story block's wrapper class is `.ttm-lead` (`Helpers::wrapper( 'lead', … )`), so that selector never matches. The implementation uses `.ttm-lead__media img`, which is correct, but the choice is not recorded in PLAN's Spec issues or the HANDOFF interpretations.
2. §6.3 says "the core exporter writes `wp:termmeta`". It does not, in any invocation (P2-01). The WXR injection is the right response.
3. §6.4 lists six separate `wp-cli` steps. Playground's php.wasm traps by about the ninth `wp-cli` step in one boot, so they had to become one `wp eval` step (P2-06). SPEC should describe the combined step.
4. §6.1 assumes Openverse's reported `width` is the width of the file at `url`. For several providers it is not (F3). SPEC should say the decoded download must meet `OPENVERSE_MIN_WIDTH`.

## Manual checks still owed (from HANDOFF.md)

- P0-09 (optional): `front.png`/`article.png` masthead "by Eric Mann" and footer line unchanged.
- P1-06: the owner reviews the 13 demo photographs and the six phase-5 screenshots for fit, crop and taste. After R1-02 this covers the re-fetched photographs.
- P2-06/P2-07/P2-08/P3-04: `npm run demo:check -- --keep`, then click through `/`, the article, `/series/`, `/writing/` and the post editor (blocks load, no build-fallback notice). R1-01 must make this possible without the "known limitation" caveat.
- P3-04: read `README.md` on GitHub as a stranger; the Playground link works once `v0.2.0` is released.
- Owner steps: merge, tag `v0.2.0`, confirm both release assets, open the README Playground link, approve or swap photographs, confirm the verse-sample licence note in `docs/fixtures/demo/LICENSE.md`.

## Notes (not findings)

- `DemoImage::attach()` sets the attachment `post_title` to the fixture alt text (`DemoImage.php:134`). PLAN's Decision "Seeder sideload" says the CREDITS title. It is harmless, since the credit line in `post_content` carries the title, but it is an unrecorded drift from PLAN.
- P0-01 edited `CLAUDE.md`'s module map, which no task called for, to fix a pre-existing `ScaffoldTest` failure caused by the plan stage's condensed map. The reason is logged and the edit is benign.
- The `.foundry/state.json` diff is the pipeline's own bookkeeping.
