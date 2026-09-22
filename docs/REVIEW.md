# Review — phase 2 (front-page fidelity), branch `refine/2026-09-21`
Round: 1

Review of the round-1 fix commits (`73cb239..8ee9b61`: R1-01 `9285ee0`, R1-02 `072f83b`, R1-03 `262a7c9`) plus a re-sweep of the whole branch `750217f..682f3ae` (116 files) against `docs/SPEC.md` v2.0 and `docs/PLAN.md`. The round-0 review (recorded by the tool as `review: round 1`, commit `c943c0a`) covered `750217f..0c636fc` commit by commit; this round re-ran every mechanical check across the full diff rather than trusting that record. `foundry_status` reports `round: 1`; the submit tool numbers any queued tasks `R2-*` (none were queued).

Verify commands, all run by the reviewer (not taken from the log): `composer lint` (0 errors), `composer test:unit` (144/144), `npm run lint` (budget 42980/43008, coverage 166/134/4 allow-listed, `check-fixme: clean`), `npm run test:unit` (23 passed, 2 skipped as before), `npm run build`, `bash scripts/forbidden-patterns.sh` (clean), `npm run test:integration` (425/425), `npm run test:e2e` (138 passed, 0 skipped, fidelity project included), and `npm run screenshots` on the reseeded site: all seven PNGs came out **byte-identical** to the committed `docs/feedback/phase-2/*.png`, so the owner's comparison artifacts are exactly what the site renders.

Mutation samples (all caught, all restored, working tree clean afterwards):

- `Newsletter/Handler.php`: removed the `'jetpack' === Providers::current()->slug()` dispatch → `HandlerTest::test_jetpack_provider_subscribes_through_jetpack_api_not_forward` fails (subscribed list empty).
- `scripts/screenshots.mjs`: inverted the `pendingImages()` filter → both Jest `pendingImages` cases fail.
- `themes/ttm-theme/patterns/series-strip.php`: dropped `"form":"nonfiction"` → `FrontPageTest::test_series_strip_lists_the_three_nonfiction_series_newest_first` fails with The Quiet Ledger in position 2 (the original F2 symptom).
- Seeded site: `wp post meta delete 9037 _thumbnail_id` (the Technology featured post) → fidelity row `tech-img` fails; `wp ttm seed --reset` → passes again (579ms).
- `tests/e2e/fidelity.spec.mjs`: inverted the new `naturalWidth > 0` assertion to `=== 0` → `tech-img` fails (`Expected: true, Received: false`), proving the loaded-image assertion is live, not only the wrapper wait.

Whole-branch mechanical constraint greps over `git diff 750217f..HEAD` (added lines only): no data calls under `themes/`; no `wp_create_nonce|wp_nonce_field|wp_nonce_url|is_user_logged_in|$_COOKIE|$_SESSION` in any `render.php`/pattern/part/template/`inc`; no `time(|date(|wp_date(|current_time(|new DateTime` outside `Support/Clock.php`; no `<style`/`style="` in the plugin beyond the allow-list; no `fetch(|XMLHttpRequest|apiFetch|admin-ajax|wp-json` under theme JS or block `view.js`; no unbounded queries; `wp_safe_remote_*` only in `Provider/CustomUrl.php` and `Verse/Fetcher.php`; no hex literals in `ttm.css`; `grep -ri lorem docs/fixtures/seed/` empty; no `prefers-color-scheme`; `package.json` `dependencies` is `{}`; every `is-style-grid-*` group carries `"layout":{"type":"default"}` (12/12); rule-35 declarations present. The `_wpnonce` hits in `Taxonomy/SeriesAdmin.php:62-64` are pre-existing `check_admin_referer`/`wp_verify_nonce` reads on an authenticated admin term-save, not cacheable output, and are outside this flight's diff.

## Verdict

**APPROVED**

Categories 1 (constraints), 2 (boundaries) and 3 (tests) are clean across the entire branch after round 1; the three round-0 findings that required tasks (F1 Jetpack POST never subscribing, F2 seeded strip/Also-running mismatch, F3 empty featured column in the committed PNG and an untested `<img>`) are each fixed with a test that fails when the fix is removed (verified above). No blocked or skipped tasks. The remaining items below are readability/documentation notes and carry no task.

## Round-1 fixes — verification

- **R1-01** (`9285ee0`, F1): `Provider\Jetpack::render()` now posts to `admin-post.php` with the same `Form::handler_fields()` (`action=ttm_subscribe`, HMAC `ttm_token`, `redirect_to`, honeypot) as `custom-url`; one `Form::next_id()` per render; `data-provider="jetpack"` kept (`NewsletterFormTest::test_jetpack_provider_renders_shared_form_posting_to_admin_post` asserts exactly one `ttm-nl-email-{n}`, no `_wpnonce`, no `jetpack_subscriptions_widget`). `Handler::handle()` dispatches to `\Jetpack_Subscriptions::init()->subscribe( $email, 0, false )` only after rate-limit, honeypot, token and email validation, guarded by `class_exists()`. Rule 7 holds (no nonce anywhere in rendered output), rule 8 holds (no per-visitor markup), rule 16 holds (no new `wp_safe_remote_*`), `Newsletter/` still imports only `Config`/`Support` (`BoundariesTest` green). `JetpackFieldsTest` now asserts the captured fixture *does* carry `_wpnonce` and the provider emits neither it nor the widget submit name, which is the assertion direction round 0 found missing.
- **R1-02** (`072f83b`, F2): one block attribute (`"form":"nonfiction"`, already in `series-list/block.json`'s enum and honoured at `render.php:67`) and two `days_ago` values (14→60, 39→75). No plugin PHP change, no CSS. Three new integration tests, one of which guards the ordering at the seed layer (`SeederTest::test_seeded_writing_essays_derive_as_story_but_stay_older_than_the_last_cron_job`).
- **R1-03** (`262a7c9`, F3): `scripts/screenshots.mjs` scrolls the full document in viewport steps, awaits every `document.images` entry, and exits 1 via the pure `pendingImages()` helper if any `naturalWidth` is 0; `tech-img` asserts one `<img>`, `complete`, `naturalWidth > 0`. The regenerated `front-1280.png` shows the grey 3:2 Technology image, the strip reads Hardening WordPress / The Consultant's Ledger / Ordinary Time, and Also running ends with The Last Cron Job (viewed by the reviewer; and reproduced byte-for-byte from the running site).

## Findings (most severe first) — none require a task

### N1 — Readability — `tests/unit/Newsletter/HandlerTest.php:323` — R1-01
`test_jetpack_subscribe_failure_redirects_with_error` asserts the *success* URL (correct per phase-1 rule 20, "no oracle"), so the name is misleading, and because `do_action` is stubbed with `justReturn(null)` in `setUp()` the test cannot tell the failure branch from the success branch (it would still pass if `handle_jetpack()` fired `ttm_newsletter_subscribed` on a `WP_Error`). The dispatch mechanic itself is covered by the sibling test (mutation-verified). This matches the pre-existing pattern in the file (no custom-url test asserts the hook either). Suggested later tidy: rename to `..._redirects_to_the_same_success_url` and `Functions\expect( 'do_action' )->never()` in the failure case.

### N2 — Readability — `plugins/ttm-core/src/Newsletter/Provider/Jetpack.php:55` vs `Provider/CustomUrl.php:70` — R1-01
`redirect_to` is `is_singular() ? get_permalink() : home_url('/')` for Jetpack but `home_url( add_query_arg( null, null ) )` for custom-url, so PLAN's "both providers build identical fields" is true only on singular pages and `/`. The poster is only in `templates/front-page.html` this flight, where both resolve to the front URL, so nothing observable differs today; `Handler` validates the target with `wp_validate_redirect()` either way. Fold into one helper argument when the box placement lands on inner templates.

### N3 — Documentation drift — `CLAUDE.md:35`, `CLAUDE.md:51` — R1-01
Line 51 still reads "the newsletter form uses the HMAC token (custom-url) or Jetpack's widget POST (no nonce)" and line 35 describes `Handler` as the custom-url dev-accept handler only. After R1-01 both providers use the HMAC token and `Handler` dispatches to Jetpack's API. `plugins/ttm-core/README.md` and `docs/spikes/P1-jetpack-form.md` were updated; `CLAUDE.md` was not (it is the planner's file and R1-01's file list did not include it). The next flight's planner should regenerate it from the amended SPEC (see spec issue 1).

### N4 — Robustness — `scripts/screenshots.mjs:126-137` — R1-03
The `Promise.all` over `document.images` has no timeout: a `loading="lazy"` `<img>` inside a `display:none` subtree never fires `load`/`error`, so the script would hang rather than exit 1. No such image exists on the seeded `/` at either viewport (the script completed in this review), so this is a latent edge, not a defect.

### Carried from round 0 (recorded, no task): F4 (`cssBudgetBytes` raised in two steps to 43008, net one R6 raise, 28 bytes headroom — unchanged this round), F5 (scope drift inside P3-06/P4-02, both tested), F6 (`!important` on rule-35 declarations with comments; `Values::footer_line` signature differs from PLAN, covered by unit test).

## Interpretation choices (HANDOFF.md, Round 1) — verdicts

- Global `class_alias()`'d stubs (`\Jetpack`, `\Jetpack_Subscriptions`, `\WP_Error`, `\WP_Block_Type_Registry`) in `HandlerTest.php`, with `\Jetpack::$ready` reset in `tearDown()`: **accepted**. `tests/unit` still never loads WordPress (CLAUDE.md), the stubs default to "unavailable", and the full unit suite (144) is green in one process.
- Jetpack `false`/`WP_Error` → the same success redirect, rather than PLAN's "existing error redirect": **accepted**. `Handler` has no error redirect to take — every failure path returns `success_url()` by design (phase-1 rule 20, "no oracle"), so PLAN's wording described a path that does not exist and the implementer chose the reading consistent with SPEC.
- Both Writing essays kept in the fixture with only their dates moved (still exercising `ttm_form=story` derivation): **accepted**; `SeederTest` pins both the derivation and the ordering.
- `class_exists('\Jetpack_Subscriptions')` false → silent success redirect without firing the hook: **accepted** (Jetpack is never installed in wp-env, non-goal; the provider is only resolvable when Jetpack reports a ready connection or the block is registered, both of which imply the module is loaded).

## Blocked / skipped tasks
None.

## Spec issues (SPEC itself is wrong or incomplete; separate from findings)
1. §6.3 `jetpack` still describes the widget-POST contract (`action=subscribe`, `source`, `sub-type=widget`, `redirect_fragment=ttm-newsletter-{n}`, submit `name="jetpack_subscriptions_widget"`, "No nonce (rule 7; the widget path has none)"). Jetpack 16.2's `widget_submit()` requires `_wpnonce` for `blogsub_subscribe_{blog id}`, `Jetpack_Subscriptions_Widget::process_subscription` does not exist, and R3's "the spike's record wins" was exercised: the shipped contract is action = `admin-post.php`, the same `custom-url` hidden fields, and `Handler` calling `Jetpack_Subscriptions::init()->subscribe( $email, 0, false )` server-side. Rewrite §6.3 and CLAUDE.md line 51 to match. The `?subscribe=success` done-state note in §6.3 is now moot (the handler redirects with `?subscribed=1`).
2. §6.2 `mast-current` (accent, 14px), `cell-head-link` (neutral-600, 11px) and `writing-btn` (bg on accent) fail AA and contradict the `a11y` row; expected values should be accent-700 / neutral-700 / accent-700. `poster-btn` is the 01 §2.1 exception; say so under the `a11y` row. `rail-count-phone` should read "visible count 2". `mast-hub` "margin-left auto" is asserted by position.
3. Rule 34 "the eleven unstyled classes" / "fewer than 10 entries": 46 classes on `main`; allow glob lines and say "fewer than 10 lines".
4. §6.1.7 / §6.5: the mock's strip is the three non-fiction series; §6.1.7 should say the pattern passes `form=nonfiction` (R1-02 does this).
5. §4 import table forbids imports phase 1 shipped (`Cache` → `Meta`, `Query`; `Verse`/`Newsletter`/`Fiction` → `Admin\Page`); `BoundariesTest::KNOWN_EXCEPTIONS` records them with reasons.
6. §6.7 "no-op editor script": a no-op cannot clear "doesn't include support for"; the committed fallback registers each block with `ServerSideRender`.
7. §6.5 lead "14 min ≈ 3 200 words" vs the last bullet (read times not reproduced): the seed yields "9 min read"; "The Last Cron Job · 3,100 words" renders as 1,727 words for the same reason. Harmless; pick one.
8. theme.json font-size slug `h2` yields `--wp--preset--font-size--h-2`; two pre-existing rules on `main` (`.is-style-journal-title`, `.entry-content h2`) reference `--font-size--h2` and silently fail. Inner-page issue for a later flight.

## Manual checks still owed (from HANDOFF.md, verbatim)
1. (P0-09) NOT VERIFIED (human) — owner should open `docs/feedback/phase-2/*.png` and compare against `design_*.png` per `phase-2/README.md`'s pairing table.
2. (P1-11) NOT VERIFIED (human) — compare `docs/feedback/phase-2/masthead.png` with the top of `docs/feedback/design_top.png` and `poster-footer.png` with `design_footer.png`; the poster shows an email field and a ghost "Subscribe" button.
3. (P2-06) NOT VERIFIED (human) — `docs/feedback/phase-2/lead-row.png` vs the lower half of `docs/feedback/design_top.png` (lead image 16:9 grayscale, red kicker, 44px headline, verse box, "All N entries").
4. (P3-06) NOT VERIFIED (human) — `docs/feedback/phase-2/section-rows.png` and `series-strip.png` vs `docs/feedback/design_blocks.png` (Technology spans two columns with a 3:2 image, 1px column rules, Writing cell with two buttons and "Also running", three series rows with red squares).
5. (P4-05, owed — not yet performed as of this document) NOT VERIFIED (human) — open `http://localhost:8888/` at 390 in a real phone browser and compare with mock `3a`; confirm CI is green on the branch including the e2e job and its `playwright-report` artifact; compare `docs/feedback/phase-2/front-1280.png` with `docs/feedback/design_*.png` end to end.
6. (Round 1) NOT VERIFIED (human): compare the regenerated `docs/feedback/phase-2/*.png` (all seven, four of which actually changed pixels: `front-1280.png`, `front-390.png`, `section-rows.png`, `series-strip.png`) against `docs/feedback/design_*.png`. `front-1280.png` should now show the grey 3:2 Technology image, the series strip reading "Hardening WordPress / The Consultant's Ledger / Ordinary Time", and the Writing cell's "Also running" ending with "The Last Cron Job".
7. (R1-01, reviewer's addition) NOT VERIFIED (human) — the Jetpack subscribe path (`Handler::handle_jetpack()`) cannot run in wp-env (non-goal: no Jetpack connection). On the live site, with Jetpack connected and `newsletter.provider = 'jetpack'`, submit the poster once and confirm the address appears under Jetpack → Subscribers; the reviewer verified the call against Jetpack 16.2 source only.

Reviewer's note on checks 4/5: the PNGs at HEAD (`262a7c9`) are the ones to compare, not those at `c644e7f`; this review reproduced them byte-for-byte from the running seeded site.
