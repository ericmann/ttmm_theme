# Review — phase 2 (front-page fidelity), branch `refine/2026-09-21`
Round: 0

Reviewed `750217f..0c636fc` (37 tasks, 114 files) commit by commit against `docs/SPEC.md` v2.0 and `docs/PLAN.md`. Every verify command was run by the reviewer (not taken from the log): `composer lint`, `composer test:unit` (142/142), `npm run lint` (budget 42980/43008, coverage 166/134/4, fixme clean), `npm run test:unit`, `npm run build`, `bash scripts/forbidden-patterns.sh`, `npm run test:integration` (422/422), `npm run test:e2e` (138 passed, 0 skipped). Mutation samples: `Text::sentence_excerpt` hard cap (unit, caught), a `Config::get()` fallback literal (unit, caught), `css-coverage.mjs` dead-rule report (Jest, caught), `Nav\CurrentSection` front-page mark (integration, caught), `Query\Cells` dek suppression (integration, caught), `.ttm-lead__kicker` letter-spacing (fidelity row `lead-kicker`, caught). All mutations restored.

## Verdict

**CHANGES REQUESTED**

Categories 1 (constraints) and 2 (boundaries) are clean across the branch. Category 3 has one functional gap the tests could not see (the Jetpack provider's POST is rejected by Jetpack itself) and one row that passes against an empty element; two seeded-content mismatches against the mock (the contract) also need fixing. No blocked or skipped tasks.

## Findings (most severe first)

### F1 — Jetpack provider cannot subscribe anyone in production (Tests / Spec drift) — P1-08
- `plugins/ttm-core/src/Newsletter/Provider/Jetpack.php:60-70`
- What is wrong: the provider emits Jetpack's widget POST (`action=subscribe`, `source`, `sub-type=widget`, `redirect_fragment`, submit `name=jetpack_subscriptions_widget`) with no nonce, as SPEC §6.3 prescribes. Verified against Jetpack 16.2 source (installed into wp-env for the check and deleted again): `Jetpack_Subscriptions::widget_submit()` (`modules/subscriptions.php:636-640`, hooked on `template_redirect`) starts with `if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'blogsub_subscribe_' . Jetpack_Options::get_option('id') ) ) { return false; }`. Every submission from this form is silently dropped. Goal 3 of the flight ("posts somewhere that works in production (Jetpack)") is not met. The P1-07 spike recorded the nonce (Outcome B) and R3 says the spike's record wins; P1-08 shipped SPEC's literal field list anyway and the HANDOFF only lists it as a spec issue.
- Why the tests did not catch it: `JetpackFieldsTest::test_provider_fields_match_captured_widget_form` asserts provider fields ⊆ fixture fields; the fixture's `_wpnonce` (a *required* field) is never checked in the other direction.
- Same file, `:68`: `redirect_fragment` consumes its own `Form::next_id()` and `Form::render()` consumes another, so the fragment is `ttm-newsletter-1` while the input is `ttm-nl-email-2`, and no element carries `id="ttm-newsletter-{n}"` at all.
- Minimal fix that keeps rule 7 (no nonce on cacheable output): route the Jetpack provider through the site's own handler. The form posts to `admin-post.php` with the same `action=ttm_subscribe` / HMAC `ttm_token` / `redirect_to` / honeypot fields as `custom-url`; `Handler::handle()`, when the resolved provider is Jetpack, calls `Jetpack_Subscriptions::init()->subscribe( $email, 0, false )` (`modules/subscriptions.php:551`, the exact method `widget_submit()` calls) instead of `forward()`. No page nonce, no per-visitor markup, Jetpack's own client does the WordPress.com call. → **R1-01**.

### F2 — Seeded front page does not match the mock in two zones (Spec drift, §6.5 / mock `2a`) — P0-07, P0-08, P3-04
- `themes/ttm-theme/patterns/series-strip.php:32`: the strip renders The Quiet Ledger · Hardening WordPress · The Consultant's Ledger. Mock `2a` (`docs/Eric Mann Newspaper.dc.html:1108-1110`) and SPEC §6.5 "Series:" list Hardening WordPress, The Consultant's Ledger, Ordinary Time. `ttm/series-list` is `form: any` ordered by last update, and the Quiet Ledger's ch. 12 (1 day ago) outranks Ordinary Time week 9 (27 days ago, fixed by the mock's Faith cell "Aug 24"). The mock cannot be reproduced by re-dating (the journal entry "New chapter Friday" also fixes the chapter as recent); the mock's strip is non-fiction, fiction lives in the Writing cell. Fix: `"form":"nonfiction"` on the strip's block (attribute already exists), plus a seeded-order test.
- `docs/fixtures/seed/posts.json` (`finishing-a-draft-you-no-longer-believe-in` 14 days, `outlining-for-people-who-hate-outlines` 39 days): both are Writing-category essays with no series, so `Meta\Form::derive()` classifies them `story`, and `Serials::stories()` (date DESC) puts "On finishing a draft… · Short story · 240 words" in the Writing cell's Also running instead of the mock's third row "The Last Cron Job · Short story · 3,100 words" (mock lines 297-301; `story-the-last-cron-job` is 40 days old). Fix: date the two essays older than the newest story (e.g. 60 and 75 days); they still exist for F2. → **R1-02**.

### F3 — Committed `front-1280.png` is not what the site renders; `tech-img` passes against an empty figure (Tests) — P0-05, P3-02, P4-05
- `docs/feedback/phase-2/front-1280.png` (c644e7f) shows an empty 200px column left of "Why I moved my build pipeline…". The live seeded page renders the image (`<img loading="lazy" … -800x533.png>`, HTTP 200; a fresh full-page capture by the reviewer shows the grey 3:2 block; the same P4-05 run's `section-rows.png` shows it too). `scripts/screenshots.mjs:118-122` waits for `networkidle` and fonts only; a lazy image below the 900px fold is not guaranteed to have loaded when `fullPage: true` captures. The owner's comparison artifact is therefore wrong in exactly the zone the flight controller flagged, and the script can produce this silently.
- `tests/e2e/fidelity.spec.mjs:559-568` (`tech-img`) reads `aspect-ratio`/`filter` off the `figure` only; it passes whether or not an `<img>` is inside or loaded, so a real regression to an empty featured column would not fail any row. F8-style collapse is correct today (`:not(:has(.ttm-item-featured__media img))` only collapses when no `<img>` is emitted) — not a bug, just untested.
- Fix: the script scrolls the document (or sizes the viewport to the document height) and awaits every `document.images` entry, exiting 1 if any has `naturalWidth === 0`; `tech-img` also asserts the `img` exists with `naturalWidth > 0`; regenerate the seven PNGs after R1-02. → **R1-03**.

### F4 — `cssBudgetBytes` raised twice (Spec drift, R6) — P3-03, P3-04 — recorded, no task
- `scripts/check-budget.mjs:79`: 40960 → 41984 (P3-03) → 43008 (P3-04); R6 permits "once more". The end state (43008, both numbers in the commit bodies, HANDOFF and this file) is what a single raise in P3-05 would have produced, and 28 bytes of headroom remain. No fix; **every R1 task below changes no CSS** so the budget is untouched.

### F5 — Scope drift inside push/a11y tasks (Spec drift, PLAN "Out of scope") — P3-06, P4-02 — recorded, no task
- P3-06 ("Out of scope: any code change") edited `posts.json`/`series.json` (a new Hardening part 2); P4-02 (a11y/network rows) changed `Query\Cells::suppress_stale_dek()` and `.ttm-item__dek`. Both were flight-controller relays, both carry tests (`CellsTest`, `SeederTest`), and the `Cells` change is consistent with 03 §82 / 02 §57 ("no excerpt → no dek"). Noted so the log is honest; the excerpt suppression is now site-wide (every `core/post-excerpt` outside Journal), which is the design's intent but wider than "front page".

### F6 — Readability — P1-01, P1-02, P1-03 — no task
- `ttm.css:52-55` rule 35 declarations carry `!important` (SPEC gives them without); `:198`, `:209` also `!important`. Justified by core's `.wp-block-separator:not(.is-style-wide)` and repeated-class nav selectors; a comment explains each. Fine as is.
- `Values::footer_line( string $site_name, string $year )` differs from PLAN's `( DateTimeImmutable $now, string $site_name )`; SPEC does not fix the signature; the unit test covers it.

## Interpretation choices (HANDOFF.md) — verdicts
- accent-700 / neutral-700 for `mast-current`, `cell-head-link`, `writing-btn`, byline "by": **accepted** — 01 §2.1 requires it and the `a11y` row forbids the alternative (spec issue below).
- Poster ghost button excluded from axe: **accepted** (01 §2.1's explicit exception, documented at both scan sites).
- `rail-count-phone` as visible count: **accepted** (markup cannot vary per viewport; the third entry is CSS-hidden and the row uses `:visible`).
- Allow-list as four brace-glob lines: **accepted** (rule 34's "eleven classes" is wrong; 46 on `main`).
- P1-09 diagnosis (`file:./index.js` resolves to the source tree; Customizer needs `unstable__bootstrapServerSideBlockDefinitions`): **verified by reading `Blocks\Registrar`**; the fallback script is registered only when a build is missing and `EditorAssetsTest` proves it never enqueues on the front end.
- `getComputedStyle` keyword normalisations (`auto`, `transparent`, `span 2`, `-webkit-box` → `flow-root`): **accepted**, each documented in the row.
- Writing cell link text at both widths, `journal.excerpt_max_words` kept at 55 (measured 0/3 hard-cut at 45/55/65): **accepted**.
- P1-08 "implemented as written despite the spike": **rejected** — see F1.

## Blocked / skipped tasks
None.

## Spec issues (SPEC itself is wrong or incomplete; separate from findings)
1. §6.3 `jetpack`: "No nonce (rule 7; the widget path has none)" is false for Jetpack 16.2 — `widget_submit()` requires `_wpnonce` for `blogsub_subscribe_{blog id}`; `Jetpack_Subscriptions_Widget::process_subscription` does not exist; `redirect_fragment` is Jetpack's `subscribe-blog[-N]` scheme. The widget-POST contract as written cannot work under rule 7. Proposed rewrite: the Jetpack provider posts to the site's own `admin_post_nopriv_ttm_subscribe` (token + honeypot) and the handler calls `Jetpack_Subscriptions::init()->subscribe()` server-side (R1-01 implements this).
2. §6.2 `mast-current` (accent, 14px), `cell-head-link` (neutral-600, 11px), `writing-btn` (bg on accent) fail AA and contradict the `a11y` row; expected values should be accent-700 / neutral-700 / accent-700. `poster-btn` is the 01 §2.1 exception; say so under the `a11y` row. `rail-count-phone` should read "visible count 2". `mast-hub` "margin-left auto" is asserted by position.
3. Rule 34 "the eleven unstyled classes" / "fewer than 10 entries": 46 classes on `main`; allow glob lines and say "fewer than 10 lines".
4. §6.1.7 / §6.5: the mock's strip is the three non-fiction series; either §6.1.7 should say `form=nonfiction` (R1-02 does this) or §6.5 must date the Quiet Ledger below Ordinary Time, which contradicts the mock's own journal copy.
5. §4 import table forbids imports phase 1 shipped (`Cache` → `Meta`, `Query`; `Verse`/`Newsletter`/`Fiction` → `Admin\Page`); `BoundariesTest::KNOWN_EXCEPTIONS` records them with reasons.
6. §6.7 "no-op editor script": a no-op cannot clear "doesn't include support for"; the committed fallback registers each block with `ServerSideRender`.
7. §6.5 lead "14 min ≈ 3 200 words" vs the last bullet (read times not reproduced): the seed yields "9 min read". Harmless; pick one.
8. theme.json font-size slug `h2` yields `--wp--preset--font-size--h-2`; two pre-existing rules on `main` (`.is-style-journal-title`, `.entry-content h2`) reference `--font-size--h2` and silently fail. Inner-page issue for a later flight.

## Manual checks still owed (from HANDOFF.md, verbatim)
1. (P0-09) NOT VERIFIED (human) — owner should open `docs/feedback/phase-2/*.png` and compare against `design_*.png` per `phase-2/README.md`'s pairing table.
2. (P1-11) NOT VERIFIED (human) — compare `docs/feedback/phase-2/masthead.png` with the top of `docs/feedback/design_top.png` and `poster-footer.png` with `design_footer.png`; the poster shows an email field and a ghost "Subscribe" button.
3. (P2-06) NOT VERIFIED (human) — `docs/feedback/phase-2/lead-row.png` vs the lower half of `docs/feedback/design_top.png` (lead image 16:9 grayscale, red kicker, 44px headline, verse box, "All N entries").
4. (P3-06) NOT VERIFIED (human) — `docs/feedback/phase-2/section-rows.png` and `series-strip.png` vs `docs/feedback/design_blocks.png` (Technology spans two columns with a 3:2 image, 1px column rules, Writing cell with two buttons and "Also running", three series rows with red squares).
5. (P4-05) NOT VERIFIED (human) — open `http://localhost:8888/` at 390 in a real phone browser and compare with mock `3a`; confirm CI is green on the branch including the e2e job and its `playwright-report` artifact; compare `docs/feedback/phase-2/front-1280.png` with `docs/feedback/design_*.png` end to end.

Note for check 4/5: compare against the PNGs regenerated by R1-03, not the ones at c644e7f (see F3).
