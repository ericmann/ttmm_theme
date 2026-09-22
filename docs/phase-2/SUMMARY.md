# Build summary — phase 2 (front-page fidelity)

## Merge line

Branch `refine/2026-09-21` → `main`. Base `750217f` → head `2c86280`, **87 commits**, 40/40 tasks done (0 blocked, 0 skipped). One review-fix round (round 0: CHANGES REQUESTED with 3 task-bearing findings; round 1: **APPROVED**, no tasks queued). Do not merge without reading "Manual checks owed" below — none of the seven has been performed.

Verify at head (all run by the reviewer): `composer lint` 0 errors, `composer test:unit` 144/144, `npm run lint` (CSS budget 42980/43008, coverage clean, `check-fixme` clean), `npm run test:unit` 23 passed / 2 skipped (pre-existing), `npm run build`, `forbidden-patterns.sh` clean, `npm run test:integration` 425/425, `npm run test:e2e` 138/138. `npm run screenshots` reproduces the committed PNGs byte-for-byte.

## What was built

**Phase 0 — Harness (P0-01..P0-09).** Measurement scaffolding before any front-page CSS: a CSS-coverage lint (`scripts/css-coverage.mjs` + a four-line glob allow-list), four new `Config` keys with a fallback-literal test, the module-boundary table (`BoundariesTest`), the `cssBudgetBytes` constant, the full `fidelity.spec.mjs`/`editors.spec.mjs` Playwright skeleton with every SPEC §6.2 row as `test.fixme`, the `npm run screenshots` script, and a complete seed rewrite (original prose replacing lorem ipsum, the mock's exact section/journal/page copy, six series including a 24-chapter novel). Baseline screenshots pushed.

**Phase 1 — Chrome (P1-01..P1-11).** Full-width rules and unconstrained grid layouts (rules 35/36), the front masthead, front-page current-section marking plus footer label fill-in (`Nav\CurrentSection`), verse-copyright placement, the footer (fixed a real `is-after-poster` selector bug), one shared newsletter form markup across every provider with a `custom-url` dev-accept path, a bounded spike into Jetpack's real widget POST contract, the Jetpack provider, block-editor registration in every context (fixed a wrong `editorScript` path that broke all 19 blocks in every editor, plus a Customizer JS-bootstrap gap), and the newsletter poster. Chrome screenshots pushed.

**Phase 2 — Lead row (P2-01..P2-06).** Lead story markup and CSS, the verse box, the journal excerpt hard cap and `ttm/category-count`'s new `entries` format, the journal rail, and the `journal.excerpt_max_words` tuning (kept at 55). Lead-row screenshots pushed.

**Phase 3 — Section rows, Writing cell, series strip (P3-01..P3-06).** Shared cell / cell-heading / headline-item components, the Technology cell's inner grid and featured image, the Writing cell (fixing a seed-derivation bug that labelled every fiction chapter a standalone "story"), the series strip's new `layout=strip`, the `cssBudgetBytes` tuning, and a seed-content fix (Hardening WordPress part 2 had been standing in for an unrelated Technology post). Section-row screenshots pushed.

**Phase 4 — Phone pass, a11y, close-out (P4-01..P4-05).** The 390px pass (fixed a horizontal-scroll bug in the phone nav) and ≤ 1024 pass, `a11y`/`network` rows un-fixme'd with a lint guard against any `test.fixme` reappearing, a body-prose-leaking-into-cell-dek fix, inner-template smoke (`/about/` as the eighth screen), CI building before every e2e run on `refine/**`, HANDOFF/SETUP, final screenshots.

**Review fixes (R1-01..R1-03).** The Jetpack provider now posts to the site's own `admin-post.php` handler (HMAC token, honeypot, rate limit) and `Handler` calls `Jetpack_Subscriptions::init()->subscribe()` server-side — the original widget-POST form could never subscribe anyone because Jetpack demands a per-visitor nonce. The series strip is filtered to `form=nonfiction` and two seeded essays were re-dated so the front page matches the mock. The screenshot script now scrolls the page and waits for every image; `tech-img` asserts a loaded `<img>`; all seven PNGs regenerated.

## Decisions that shaped it

From PLAN.md Decisions:
- (all) Five phases in SPEC §8 order, never merged; each ends with a push task committing PNGs and logging `Manual check: NOT VERIFIED (human)`.
- (all) Branch `refine/<date>` from `main`; `FOUNDRY_FEEDBACK.md` at the repo root is the owner's log, never staged.
- (P0-01) Rule 34 collection rule: a `ttm-*` token counts only inside `class=`, `"className":`, or `Helpers::wrapper()`; admin-only sources skipped; allow-list lines may use `*`/`{a,b}` globs, fail at ≥ 10 lines; dead selectors also fail.
- (P1-02, P3-01, P3-03) Colour vs a11y: any text < 18.66px SPEC asserts as `accent`/`neutral-600` is rendered and asserted as `accent-700`/`neutral-700` (`mast-current`, `cell-head-link`, `writing-btn`, byline "by"); the poster ghost button is the one 01 §2.1 exception and is excluded from axe.
- (P1-01) Layout ownership: every front-page group whose layout `ttm.css` defines uses `"layout":{"type":"default"}`; rule 36 applied to all 12 `is-style-grid-*` groups, inner templates included.
- (P1-03) `Nav\CurrentSection` adds `current-section` + `current-menu-item`; on the front page with `nav.front_current='lead'` the lead post's primary category is current; the same filter fills `/category/<slug>/` labels so `parts/footer.html` stays static.
- (P1-02) Masthead meta links are one paragraph with three `<a>`; at ≤ 720 only the first shows; date keeps `ttm/today format=masthead` at both widths.
- (P2-03, P2-04) Journal rail pattern drops `excerptLength` so core's default (55) never re-trims the plugin's sentence excerpt; `Text::sentence_excerpt()` extends to a sentence end between `$words` and `$max_words`, else hard-cuts with "…".
- (P2-03) `ttm/category-count` gains `format:'entries'` ("All {N} entries", 0 → "All →"), HTML-capable when bound into a paragraph.
- (P2-01) Lead media: `style="aspect-ratio"` dropped for `is-ratio-*` classes; 16/9 desktop, 4/3 at ≤ 720; grayscale on the `figure`.
- (P3-02) Technology featured item: `li:first-child` is the `200px 1fr` grid spanning both columns; its `.ttm-item` wrapper is `display: contents`; `ttm/meta-line` gains `readingFormat`.
- (P3-04) `ttm/series-list` `layout` enum gains `strip` (mark + title + meta only); categories joined with " · " in every layout.
- (P1-06) `Newsletter\Form` renders one markup for every provider; `{n}` is a per-request counter; `Providers::resolve()` = configured-if-available → `custom-url` if dev-accept → `mailto` → `none`; the Seeder no longer installs Jetpack.
- (P1-07) The Jetpack spike is bounded to one task; if Jetpack can't be installed it records that and hand-authors the fixture.
- (P1-09) `build/` stays gitignored; when `index.asset.php` is missing `Blocks\Registrar` registers a committed fallback script that registers every `ttm/*` block with `ServerSideRender` and shows an admin notice.
- (P0-04) Fidelity tests run in their own Playwright project `fidelity`; colours resolved from `theme.json`; grids compared as resolved px tracks; `margin-left:auto` asserted by position (`x ≥ 1000`); `rail-count-phone` counts visible entries.
- (P0-05) Screenshots via Playwright chromium against `WP_BASE_URL`, no reseed; two-element zones are page clips.
- (P0-06..P0-08) Seed: `prose.json` ≥ 40 original paragraphs, `posts.json` rows carry `paragraphs: N` (+ optional block-markup prefix); six series replace phase 1's four; tagline set by the Seeder.
- (P1-04) `verse_copyright()` reads `get_option('ttm_verse')['copyright']` directly; `verse.copyright_placement` default `footer`.
- (P1-05) Footer line "{site} · © {Y} Eric Mann · Built on WordPress" (author name is a translatable literal). Shipped signature is `Values::footer_line( string $site_name, string $year )`, not PLAN's `( DateTimeImmutable, string )`.
- (P0-03, P3-05) `cssBudgetBytes` starts at 40960, raised at most once more with the number recorded.
- (P4-03) `SCREENS` gains `about: '/about/'`; (P0-01) CI push branches gain `refine/**`.
- (P0-08) Quiet Ledger: 12 published chapters, ch. 13 scheduled, `total_parts` 31, cadence monthly.
- (P3-03) Writing cell link text "All serials & stories →" at both widths (3a's shorter text not reproduced).

Interpretation choices from HANDOFF.md (all accepted by the reviewer unless noted):
- (P0-04) Fidelity/editors specs are `.spec.mjs`, not `.spec.js` as SPEC/PLAN say — Playwright's CJS transform can't load the real-ESM `lib/*.mjs` helpers.
- (P1-02, P1-10, P3-02, P4-02) `getComputedStyle` keyword normalisations: `auto` → px (asserted by position), `transparent` → `rgba(0,0,0,0)`, `span 2` stays `"span 2"`, `-webkit-box` → `flow-root`.
- (P1-08) Jetpack provider implemented per SPEC §6.3's literal field list despite the spike finding the real widget requires a nonce — **rejected** in round 0 (F1), replaced by R1-01.
- (P1-09) Editor-registration diagnosis: wrong `file:./index.js` resolution plus missing Customizer `unstable__bootstrapServerSideBlockDefinitions`, not a missing `build/`.
- (P4-02) `Query\Cells::suppress_stale_dek()` is now site-wide (every `core/post-excerpt` outside Journal), wider than "front page" but consistent with 03 §82.
- (R1-01) Global `class_alias()`'d stubs (`\Jetpack`, `\Jetpack_Subscriptions`, `\WP_Error`, `\WP_Block_Type_Registry`) in `HandlerTest.php`; `\Jetpack::$ready` reset in `tearDown()`.
- (R1-01) Jetpack `false`/`WP_Error` → the same success redirect (phase-1 rule 20 "no oracle"); PLAN's "existing error redirect" described a path that does not exist.
- (R1-01) `class_exists('\Jetpack_Subscriptions')` false → silent success redirect, no hook fired.
- (R1-02) Both Writing essays stay in the fixture; only their `days_ago` moved (14→60, 39→75).

## Assumptions still in play

| Key | Where | Final default | Status |
|---|---|---|---|
| `journal.excerpt_max_words` | `Config::defaults()` (SPEC §5) | 55 | **Tuned** in P2-05: 0/9 posts hard-cut at 45/55/65, 0/3 rail entries hard-cut at 45; kept. Coupled to core's `excerptLength` default (55): raising it requires an explicit `excerptLength` on the rail pattern. |
| `cssBudgetBytes` | `scripts/check-budget.mjs` (SPEC §3.1 rule 30) | 43008 | **Tuned**: 40960 → 41984 (P3-03) → 43008 (P3-04); P3-05 found nothing left to trim. Final CSS 42980 bytes, 28 bytes headroom — the next CSS change will need a raise. |

No other `⚠️ ASSUMPTION` keys were introduced; `scripts/convert-classic.mjs` carries a pre-existing, verified one from phase 1.

## Spec issues

Edits the human should make to `docs/SPEC.md` (PLAN + both review rounds, deduplicated):
1. **§6.3 `jetpack`** — rewrite the contract: the widget-POST form (`action=subscribe`, `sub-type=widget`, `redirect_fragment`, no nonce, `process_subscription`) cannot work under rule 7 and the named handler does not exist in Jetpack 16.2. Shipped contract: form posts to `admin-post.php` with the `custom-url` hidden fields; `Handler` calls `Jetpack_Subscriptions::init()->subscribe( $email, 0, false )`. `?subscribe=success` note is moot (`?subscribed=1`). Also fix `CLAUDE.md` lines 35 and 51 (planner's file; regenerate next flight).
2. **§6.2 contrast rows** — `mast-current`, `cell-head-link`, `writing-btn` expected values → accent-700 / neutral-700 / accent-700; note `poster-btn` as the 01 §2.1 exception under the `a11y` row; `rail-count-phone` → "visible count 2"; `mast-hub` "margin-left auto" is asserted by position; `tech-featured` grid sits on `li:first-child` with the `.ttm-item` wrapper as `display: contents`. §6.1.1 byline "by" → neutral-700.
3. **Rule 34** — "the eleven unstyled classes" is 46 on `main`; allow glob lines and say "fewer than 10 lines".
4. **§6.1.7 / §6.5** — the strip is the three non-fiction series; say the pattern passes `form=nonfiction`.
5. **§4 import table** — add `Admin\Page` to the `Verse`/`Newsletter`/`Fiction` rows and `Meta`/`Query` to `Cache`, or move the code; `BoundariesTest::KNOWN_EXCEPTIONS` records them.
6. **§6.7** — a "no-op editor script" cannot clear "doesn't include support for"; the fallback registers each block with `ServerSideRender`.
7. **§6.5** — lead "14 min ≈ 3 200 words" vs the seed's "9 min read" and "3,100 words" rendering as 1,727; pick one.
8. **theme.json** — slug `h2` yields `--wp--preset--font-size--h-2`; `.is-style-journal-title` and `.entry-content h2` reference `--font-size--h2` and silently fail (inner-page, later flight).
9. **§6.1.5 / 02 §A** — `excerptLength: 40` on the rail pattern re-trims the plugin excerpt; removed, coupling the cap to core's default.
10. **§6.1.8** — footer labels come from a label-fill step in `Nav\CurrentSection`, not a navigation-link filter on static HTML.
11. **§6.1.6** — Writing link text is "All serials & stories →" at both widths.
12. **§6.2 `network`** vs phase 1's Jetpack host allow-list; §7 `extraVerify` duplicate `theme.json` entry; §6.6 two-element screenshot zones are page clips — all harmless, document as written.
13. **§6.5 Quiet Ledger** — "12 chapters" means 12 published of `total_parts` 31.

## Manual checks owed

None performed yet. Compare against the PNGs at head (`262a7c9` or later), not those at `c644e7f`.
1. **Phase 0 (P0-09)** — open `docs/feedback/phase-2/*.png` and compare against `design_*.png` per `phase-2/README.md`'s pairing table.
2. **Phase 1 (P1-11)** — `masthead.png` vs the top of `design_top.png`; `poster-footer.png` vs `design_footer.png` (email field + ghost "Subscribe" button).
3. **Phase 2 (P2-06)** — `lead-row.png` vs the lower half of `design_top.png`: lead image 16:9 grayscale, red kicker, 44px headline, verse box, "All N entries".
4. **Phase 3 (P3-06)** — `section-rows.png` and `series-strip.png` vs `design_blocks.png`: Technology spans two columns with a 3:2 image, 1px column rules, Writing cell with two buttons and "Also running", three series rows with red squares.
5. **Phase 4 (P4-05)** — open `http://localhost:8888/` at 390 in a real phone browser vs mock `3a`; confirm CI green on the branch including the e2e job and its `playwright-report` artifact; `front-1280.png` vs `design_*.png` end to end.
6. **Round 1 (R1-02/R1-03)** — the four regenerated PNGs (`front-1280`, `front-390`, `section-rows`, `series-strip`): `front-1280.png` should show the grey 3:2 Technology image, the strip reading "Hardening WordPress / The Consultant's Ledger / Ordinary Time", and "Also running" ending with "The Last Cron Job".
7. **Round 1 (R1-01, reviewer's addition)** — the Jetpack subscribe path cannot run in wp-env. On the live site with Jetpack connected and `newsletter.provider = 'jetpack'`, submit the poster once and confirm the address appears under Jetpack → Subscribers. Verified against Jetpack 16.2 source only.

Reviewer notes carrying no task: `HandlerTest::test_jetpack_subscribe_failure_redirects_with_error` cannot distinguish the failure branch (N1); Jetpack and custom-url compute `redirect_to` differently on non-singular inner pages (N2); `scripts/screenshots.mjs` image wait has no timeout for a lazy image inside `display:none` (N4).

## Review history

| Round | Verdict | Findings | Fix tasks | Recurred |
|---|---|---|---|---|
| 0 (`c943c0a`) | CHANGES REQUESTED | 6 (F1 Jetpack POST never subscribes; F2 seeded strip/Also-running mismatch; F3 committed PNG missing Technology image, untested `<img>`; F4–F6 recorded only) | R1-01, R1-02, R1-03 | — |
| 1 (`2c86280`) | APPROVED | 4 readability/documentation notes (N1–N4), no task; F4–F6 carried as recorded | none | No finding recurred; all three fixes mutation-verified |
