# Phase 4 (real content): build summary

**Merge line:** `refine/2026-09-23` into `main`, `aa497b2` → `4a6b69f`. 143 commits (54 task commits: 34 planned tasks and 20 review-fix tasks). 7 review rounds. Final verdict: **APPROVED** (round 7).

## What was built
**Phase 0, Harness (P0-01..P0-07).** Added rule 47's private-data check and turned on the tagged-fixme guard for the flight. Scaffolded the live-script entry points (`env:live`, `env:backup`, `env:restore`, `env:drill`, `test:live`) as stubs that print a skip line. Defined the phase-4 screenshot set. The seed gained tags for every section and a Reading CVEs series. `Stats` now invalidates when terms are attached to already-published posts, which is what a WXR import does. It also gained `flush_all()` and a deterministic tiebreak. The fix makes the Business filter row green.

**Phase 1, The four owner-reported defects (P1-01..P1-06).**
- The footer is now one line with eight items, no Scripture copyright and no RSS.
- `ttm/series-toc` returns empty under F11 and uses chronological prev/next.
- `ttm/series-list relatedTo=current` (F27) lists only other series with the same form and section.
- F28 makes `/writing/` render the section-archive layout when the site has no fiction content.
- A rule 50 sweep covered every block's no-context case.

**Phase 2, Import and migration commands (P2-01..P2-09).**
- New and extended commands: `seed --starter-only`, a `Seeder::reset()` that works from a live state, `stats:flush`, `primary:assign --from-yoast`, `series:assign --from-tags/...`, `migrate:excerpts --from=yoast`, `migrate:images` and the full `audit` flag set with `--summary`.
- New files: `docs/migration/series.json`, plus `import.sh`, `plan.sh` and `screens.mjs` under `scripts/live/`.
- `env:live` was run against the real 888-post export to tune `excerpt_length` and `migration.image_timeout`.

**Phase 3, Classic conversion (P3-01..P3-03).**
- A shortcode pre-pass converts `[ref]`/`[mfn]` into core footnotes and `[cci]`/`[cc]`/`[cc_x]` into code blocks.
- `convert:import` writes footnotes meta. It calls `wp_slash()` first, which works around a core gotcha.
- All 724 classic posts were converted. The final state has 3 residual `textEqual: false` posts, all owner cleanup.

**Phase 4, Live triage (P4-01..P4-05).** `tests/e2e/live.spec.mjs` is a new Playwright `live` project. It runs against every URL that `screens.mjs` discovers, at 1280 and 390. Each class of finding went to `docs/feedback/phase-4/LIVE-TRIAGE.md` and was either fixed with a synthetic test or marked as owner cleanup. `test:live` exits 0.

**Phase 5, Backup drill, docs and close-out (P5-01..P5-04).** `backup.sh`, `restore.sh` and `drill.sh` now do real work. `env:drill` runs in the CI integration job. `MIGRATION.md`, `DEPLOYMENT.md`, `05-plugin-spec.md` and `SETUP.md` now match the code. `ALLOW_TAGGED` was set back to `false`. The final seed reset and screenshots are done.

**Review fixes (R1-01..R6-01).**
- The primary category now survives the importer, and `--from-yoast` goes through the WXR's own term map (R1-01, R2-03).
- `env:live` runs end to end (R1-03).
- `migration.image_hosts` is set to the SPEC list (R1-04).
- The verse attribution is undated, per SPEC §6.1.1 (R1-05).
- The paragraph merging that affected 553 of 724 posts is fixed with a real `autop` (R2-01).
- `migrate:politics` is idempotent (R2-02).
- Journal single spacing matches the mock (R2-04).
- The pipeline order is pinned by tests (R3-02, R4-01).
- Seed dates are deterministic, so the drill is green in CI (R5-01).
- The editor-login race is fixed (R6-01).

## Decisions that shaped it
**From PLAN.md**
- Phases: SPEC §8's six phases, run in order. Each phase ends with screenshots, a push and a `Manual check: NOT VERIFIED (human)` line (P0-07, P1-06, P2-09, P3-03, P4-05, P5-04).
- Repo hygiene: branch `refine/<date>`, signing off, `FOUNDRY_FEEDBACK.md` untouched, earlier phase docs frozen. The WXR export is gitignored and never read by tests (rule 47; P0-01, R1-07).
- Q1 series map: `series.json` starts with only two entries, `boundless-summer-challenge` and `cryptopals`. `plan.sh` reads it through `series-args.mjs`, so there is no `jq` dependency (P2-06).
- Q2 primary category: `primary:assign --from-yoast` runs first, then nav order fills the rest (P2-02). R2-03 adds `--term-map`.
- Q3 excerpts: `--from=yoast` applies to non-Journal posts and never overwrites. `excerpt_length` is a new key, not the "existing" one SPEC mentions (P2-03).
- Q4 images: Photon URLs on `migration.photon_origin` are rewritten to the origin. Other listed hosts are sideloaded. A failure leaves `src` unchanged and flags `remote-image` (P2-04).
- Q5 inert rows: Jetpack `feedback`, `custom_css`, foreign templates and `nav_menu_item` rows are counted under `inert-rows` and never deleted (P2-05).
- Q6: aside posts are flagged, never moved (P2-05).
- Q7 exposure: documented only. The recommendation is a Cloudflare Tunnel behind Access, with `blog_public=0` (P5-02).
- Q8: a zero-tag section still falls back to F16.
- Q9 footer string: "These Things Matter · © {year} Eric Mann · Built on WordPress" on every page (P1-01).
- Q10 Writing page: the live `writing` page becomes the draft `writing-legacy`. `/writing/` renders the F28 section archive (P1-04).
- Q11: the hostname swap uses `wp search-replace` in `import.sh` and `restore.sh --host` (P2-06, P5-01).
- Q12: unknown shortcodes stay in place and are listed by `audit --only=shortcode` (P3-01).
- "No RSS on any page": applied only to the footer navigation. The Journal header RSS link and the `ar-stats-rss` link stay (P1-01).
- Scripture copyright: the `ttm/verse-copyright` source, `verse.copyright_placement`, the footer paragraph and the verse block's `'box'` `<small>` are all removed. `Fetcher` still stores `copyright` (P1-01).
- Related series:
  - Nonfiction means `form === 'nonfiction'`.
  - Ranking is shared-category count, then `last_update`, then slug.
  - `limit <= 0` falls back to `series.related_limit` (P1-03).
- `single-other` count: the row asserts 3, not SPEC's 4 (P0-04, P1-03).
- F28 routing: `Hierarchy` rewrites the main query on the Writing page into the Writing category archive. The state comes from the cached series index and story count. R1-06 added `posts_per_page = archive.per_page` (P1-04, R1-06).
- Editor-only story derivation: `Form::is_editor_save()` returns false under `WP_CLI`/`WP_IMPORTING` and is filterable through `ttm_form_editor_save` (P1-04).
- `seed --starter-only`: runs the Seeder's categories, pages and navigation. It never calls the theme's starter content (P2-01).
- `Seeder::reset()` from a live state: wipes every post type, term and comment when `may_wipe()` allows it, which is never in production (P2-01, R1-08).
- Stats invalidation:
  - Flushes on `set_object_terms`.
  - `flush_all()` removes the transient families.
  - Empty results cache for 3600s, non-empty for 43200s.
  - Tiebreak is `cnt DESC, slug ASC` (P0-05).
- Harness: `ALLOW_TAGGED` was `true` during the flight and `false` at close-out. The CSS coverage allow-list stayed empty (P0-01, P5-03).
- Live-script stubs existed from P0-01, so `extraVerify` never failed on a missing script.
- `import.sh` paths: the export is copied to `docs/fixtures/live/import.xml` (gitignored), because only `docs/fixtures/` is mounted in wp-env (P2-06).
- Rule 50 sweep: blocks are split into a strict-empty set and a sanctioned-default set, with docblock reasons (P1-05).
- `screens.json` shape: `{generated, host, screens[{id, path, kind, expectStatus, section, classic, freeform}]}`. R1-02 added `primary` and R3-01 added `converted` (P2-07).

**Interpretation choices from HANDOFF.md**
- P3-02/P3-03: `wp_slash()` runs before `update_post_meta('footnotes')`. Without it, core's `sanitize_post_meta_footnotes` blanks any JSON with quoted attributes.
- P3-03: the JS pipeline's own `textEqual` replaces the more lenient PHP re-derivation. The PHP version falsely flagged 677 of 724 posts.
- P4-04: `screens.mjs` uses `--category_name=` instead of `--category=`, because WP_Query's `category` takes an ID. The fix is the new `sectionCategoryArgs()` helper, which has a unit test.
- P4-04: `UNSTYLED_WRAPPERS`/`isIdentifierClass()` are shared by the static and live coverage checks. The body-class identifiers `ttm-section-*`, `ttm-form-*` and `ttm-in-series` are exempt instead of getting CSS.
- P4-05: live checks cover the theme/plugin's own markup only:
  - The network check looks at the main frame only, and `platform.twitter.com` is allow-listed.
  - Axe excludes `.ttm-entry`.
  - Shortcode residue is asserted only on conversion screens.
- R1-01: the `PrimaryCategory::on_save()` gate uses `WP_IMPORTING` only, not `WP_CLI`. It is filterable through `ttm_primary_on_import`.
- R1-03: the exit-decision logic moved to `scripts/lib/summarize.mjs`, because Jest cannot import `convert-classic.mjs`.
- R1-06: `route_writing_page()` sets `posts_per_page` itself. `pre_get_posts` priorities were left as they were.
- R1-09: the plain-text autop fix was kept narrow. `character-quest-service` and `hyper-vvv-windows` are documented as owner cleanup. The "structural limitation" reading of `--from-yoast` was superseded by R2-03.
- R2-01: `mergedParagraphs` counts only `core/paragraph` blocks with an internal blank line. Legacy multi-line `<code>` is handled by `--allow-freeform/--allow-html`.
- R2-02: the fix lives in a separate `politics_child_fixup()`, which only touches posts that need it.
- R2-03: the WXR term map is extracted by a Node helper, `scripts/live/term-map.mjs`.
- R2-04: padding-zero applies to `.ttm-journal-head .ttm-entry` only. The article `.ttm-entry` keeps F12.
- R3-01: `converted` means that `ttm_converted_at` meta exists.
- R3-02: `prepareClassicHtml()` lives in `scripts/lib/prepare-classic.mjs`, which is Jest-importable.
- R3-03: the `wasConverted()` try/catch fix is in the R3-03 commit, not a separate task.
- R4-01, R5-01, R6-01: none. The formulas were used exactly as the tasks prescribed them.

## Assumptions still in play
| Key | Final default | Status |
|---|---|---|
| `excerpt_length` (`Config.php`) | 55 | Tuned in P2-08 by testing 40/55/70 on real Yoast descriptions. None was ever hard-cut, so 55 was kept. |
| `migration.image_timeout` (`Config.php`) | 20 s | Tuned in P2-08: 157 attempts, 0 timeouts and 65 dead links, so 20 was kept. |
| `cssBudgetBytes` (`scripts/check-budget.mjs`) | 63488 | Recorded in P5-03, not changed. The file is at 63090 bytes after R2-04. |
| `seed.image_band_angle` (`Config.php`) | 30 | Untouched. Still a guess, and it only affects the Seeder. |
| `journal.excerpt_max_words` (`Config.php`) | 55 | Carried from earlier phases and untouched this flight. |

## Spec issues (edits to make in SPEC.md)
1. §6.11: `single-other` count should be 3, or the seed needs a fifth nonfiction series (PLAN 1, reviews R1–R7).
2. §5: `excerpt_length` is called "existing", but the key is new this flight (PLAN 2, R1–R7).
3. §6.1 "no RSS on any page": state that it applies to footer navigation, or remove the Journal and `ar-stats-rss` links (PLAN 3).
4. §6.6: the export must live under `docs/fixtures/` to be visible in wp-env (PLAN 4).
5. §6.5: "render the section-archive layout" on a page URL means a main-query rewrite. Say so, or choose a 301 instead (PLAN 5).
6. §6.5: define "editor save" as not `WP_CLI` and not `WP_IMPORTING` (PLAN 6).
7. §7: `extraVerify` for `Cli/` runs `env:live`. It must skip cleanly without the export or Docker (PLAN 7). The pipeline friction below shows the binary pass/fail still bites.
8. §6.1: "the verse block's own attribution line is unchanged" should note that the `'box'` copyright `<small>` is gone (PLAN 8).
9. §6.12: the Reading CVEs row on `/category/security/` falls on page 2 (PLAN 9).
10. §3.1 rule 34 wording versus tagged fixmes during the flight (PLAN 10).
11. §6.6 step 1 "re-run the theme's starter content": the plugin cannot call the theme, so use `seed --starter-only` (PLAN 11).
12. Rule 50: name the front-page blocks that read site-wide by contract (PLAN 12).
13. §6.7 Photon: the rewrite target during rehearsal is `eric.mann.blog`, which is cross-origin until cut-over. Consider `home_url()` (review R1).
14. §6.10: "kicker equals the primary category" should read "the kicker's first term" (review R1).
15. §6.8: "exactly once each" is ambiguous when two footnotes have identical text (review R2).
16. `docs/01-design-language.md:166` still has the dated verse attribution. §6.1.1 named only docs 03 and 06 (review R2).
17. §1.2/§6.8: CodeColorer's `<code lang>` tag syntax is missing. It affects 40 posts, 5 of which start with a raw `<?php`. Add a pre-pass row or name them owner cleanup (review R3).

## Manual checks owed
- **Phase 0 (P0-07):** compare `docs/feedback/phase-4/archive-business.png` with mock `1e` (line 933). The filter row should show real tags.
- **Phase 1 (P1-06):** on the seeded site, open these pages:
  - `/transients-object-caches-and-fast-enough/` against mock `2b`: no stray serial TOC, chronological prev/next.
  - `/series/hardening-wordpress/` against mock `1f`: related series are the same form only.
  - `/` against mock `2a` line 327: the footer is one line.
- **Phase 4 (P4-05, R1-09):** read `LIVE-TRIAGE.md`, then open `/`, `/keeping-fresh/`, `/category/technology/`, `/writing/` and `/series/` on the live import. Kickers and section cells should show real categories, never "Uncategorized".
- **Phase 4 fixes (R2-05):**
  - `live-front.png`: the Opinion cell is populated.
  - `live-article-classic.png`: paragraphs are separate, with no merged walls of text.
  - `journal.png` against mock 2c: the body sits directly under the h1.
- **Phase 5 (P5-04):** compare every `docs/feedback/phase-4/*.png` (seeded and `live-*`) with its mock. CI is already green for `df3ddc5`, including drill and e2e.
- **Owner content pass (R1-09, R2-05):** `character-quest-service`, `hyper-vvv-windows`, `securing-forms-without-captcha`, `the-hackiest-hack-that-ever-was-hacked` and `use-your-head`. Also work through the `LIVE-TRIAGE.md` audit worklist (e.g. `no-featured-image` 725, `missing-alt` 212).
- **Future exports (R3-03):** the number of converted screens running the merged-paragraph check should grow beyond 6.
- **CI (R5-01):** when the "Container logs on failure" step first runs, confirm it finishes in minutes.
- **Out-of-band SPEC edits:** HANDOFF noted a `docs/SPEC.md` change made outside the tasks. It was committed separately as `376c23c` ("owner requests mid-flight"), and `git stash list` is empty. Read that commit to confirm it says what you intended.

## Review history
- Round 1: CHANGES REQUESTED. 8 findings, 9 fix tasks (R1-01..R1-09). Nothing recurred; first round.
- Round 2: CHANGES REQUESTED. 4 findings, 5 fix tasks (R2-01..R2-05). Recurred: findings 1, 3 and 4 trace to R1-09.
- Round 3: CHANGES REQUESTED. 3 findings, 3 fix tasks (R3-01..R3-03). Recurred: findings trace to R2-01 and R2-05.
- Round 4: CHANGES REQUESTED. 1 finding, 1 fix task (R4-01). Recurred: the finding traces to R3-02.
- Round 5: CHANGES REQUESTED. 1 finding, 1 fix task (R5-01). No recurrence (it traces to P5-01 and P0-08). The pipeline flagged the round as non-converging.
- Round 6: CHANGES REQUESTED. 2 findings, 1 fix task (R6-01). Recurred: finding 1 traces to R5-01. Flagged non-converging.
- Round 7: APPROVED. 0 findings, 0 fix tasks. **This was a notes-only approval.** Its `## Notes` section flagged these items without queuing work:
  - The R6-01 `--repeat-each` diagnosis was wrong. The real cause is a same-user `session_tokens` race, and the fix if it ever matters is a `storageState` setup project.
  - `SeederTest` seconds overflow if a fixture's index is ≥ 80.
  - Earlier notes still stand:
    - `drill.sh`'s header comment is stale.
    - The `WP_IMPORTING` define leaks in `PrimaryCategoryTest`.
    - The `Config.php:104` comment is stale.
    - The status list in the `Seeder::reset()` comment is stale.
    - `private-data.test.js` leaves temp dirs behind.
    - `toBe( 18 )` compares bounding boxes exactly.
    - `politics_child_fixup()` overwrites a primary category that is not Opinion.
    - The `wasConverted()` try/catch has no test.
    - The R4-01 test comment is verbose.

## Pipeline friction
Eight entries in `.foundry/feedback.jsonl`:
1. plan / ambiguous-prompt: plan-build says to commit exactly PLAN, PROGRESS, foundry.json and CLAUDE.md. But `docs/SPEC.md` and a `.gitignore` line were untracked, and the skill gave no guidance for that state.
2. implement / tool-refusal: P2-08's `extraVerify` (`env:live && test:live`) reported `ok:false`. That failure was expected, because the shortcode pre-pass lands in P3-01, so a phase-2 task could not pass it. The harness cannot represent an expected partial run.
3. implement / tool-refusal: P3-03's `extraVerify` was red again with 7 documented, out-of-scope residual posts. Binary pass/fail does not fit the flight's residual-tracking model.
4. implement / environment: `~/.local/bin/php` is a shim that routes to another project's Docker container. `composer lint` and `composer test:unit` failed until they were run with `/usr/bin/php` directly.
5. review / environment: the same shim made `foundry_verify`'s composer steps fail, so the reviewer reran them by hand.
6. implement / other: the shim cost a verify cycle every round, because `foundry_verify` cannot be pointed at a different php binary.
7. implement / env: the shim also broke `php -l`, which was worked around with `/usr/bin/php8.3`.
8. review / stall: `foundry_verify` with `Cli/`, integration and `scripts/live/` files chained `test:integration`, `env:live`, `test:live` and `env:drill`. It exceeded the 1800s MCP idle timeout and returned nothing, while its child processes kept holding wp-env. This cost 30 minutes.
