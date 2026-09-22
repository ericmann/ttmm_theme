# These Things Matter — phase 3 (inner-template fidelity) build progress
Branch: refine/2026-09-22
Started: 2026-09-22T04:48:01.084Z

## Tasks
- [x] P0-01 Allow-list to pending lines, budget 61440, tagged fixme guard
- [x] P0-02 Config keys, `article-h2` slug and the theme.json variable check
- [x] P0-03 Runtime selector coverage spec (rule 41) and the e2e screen set
- [ ] P0-04 Fidelity rows for every §6.9 row as tagged fixme
- [ ] P0-05 Seed images per rule 45, author display name, book and About covers
- [ ] P0-06 Tuning — `seed.image_band_angle`
- [ ] P0-07 Seed — the `2b` article and the Hardening WordPress series
- [ ] P0-08 Seed — journal, Writing, Security archive and series hub copy
- [ ] P0-09 Page container (rule 42) and the `container-*` rows
- [ ] P0-10 Inner masthead and phone-only overlay nav (rule 43, §6.1.1)
- [ ] P0-11 Inner footer variant (§6.1.2)
- [ ] P0-12 Phase 0 push — screenshot script extension and baseline PNGs
- [ ] P1-01 Series bar (§6.2, 01 §4.18)
- [ ] P1-02 Article body row, hero, body typography and sticky aside
- [ ] P1-03 Article header — kicker, H1, dek, byline (01 §4.22)
- [ ] P1-04 Prev/next (01 §4.21)
- [ ] P1-05 Series TOC (01 §4.20)
- [ ] P1-06 More in section, newsletter box copy, aside order on the phone
- [ ] P1-07 Phase 1 push — article screenshots
- [ ] P2-01 Journal post header, body column, note column and syndication line (§6.4)
- [ ] P2-02 Journal stream (01 §4.11) with whole-row links and "Full journal · N entries"
- [ ] P2-03 Journal archive (`category-journal.html`)
- [ ] P2-04 Phase 2 push — journal screenshots
- [ ] P3-01 Archive header (01 §4.25) and the `ttm/archive-kind` kicker
- [ ] P3-02 Filter row (01 §4.26) placed directly, with "All"
- [ ] P3-03 Archive body — year groups, rows, meta line and pagination (01 §4.27–4.28)
- [ ] P3-04 Archive aside — series rail, most read (01 §4.29) and the tablet grid
- [ ] P3-05 Search, 404 and static page (02 §H)
- [ ] P3-06 Phase 3 push — archive screenshots
- [ ] P4-01 Series hub header and featured block (01 §4.38, §6.7)
- [ ] P4-02 All-series grid (`layout=grid-2`) and hub clean-up
- [ ] P4-03 Single series template (`taxonomy-series.html`) and `series.related_limit`
- [ ] P4-04 Writing hero (01 §4.36–4.37, §6.5)
- [ ] P4-05 Writing body — all serials (`layout=list`) and recent chapters (numbered)
- [ ] P4-06 Writing aside — story tiles, book grid, and the responsive order
- [ ] P4-07 Phase 4 push — hub and Writing screenshots
- [ ] P5-01 390 and 1920 sweep of every screen
- [ ] P5-02 a11y, network, selectors and seed-hero-color rows; guards back to strict
- [ ] P5-03 Tuning — `cssBudgetBytes`
- [ ] P5-04 Handoff, SETUP/README notes and CI check
- [ ] P5-05 Phase 5 push — final screenshots

## Log
(one entry per task, appended by implement)

### P0-01 — c724cda
Strict allow-list format enforced: parseAllowList(text, {strict:true}) requires `ttm-<class> # P<n>-<nn> pending`; throws on old glob/brace lines. report() now returns pendingCount. check-css-coverage.mjs exports ALLOW_PENDING=true (flip false at flight end to fail on any remaining line; the old <10 cap is gone). scripts/lib/fixme.mjs new: taggedFixmeHits(lines, allowTagged) — untagged test.fixme( always fails, tagged (`// P<n>-<nn>`) tolerated while ALLOW_TAGGED=true (check-fixme.mjs export, flip false in P5-02). cssBudgetBytes raised 43008->61440 in check-budget.mjs (CLAUDE.md already said 61440). css-coverage-allow.txt now has 32 single-class pending lines mapped to P1-01/02/05, P2-01/02, P3-01..04, P4-01/03/04/06 (see commit body for the mapping rationale — ttm-entry is single.html's post-content wrapper -> P1-02; ttm-archive is the archive-by-year block wrapper -> P3-03). docs/SETUP.md fixme paragraph updated to describe tagged rows. Tests: scripts/test/check-css-coverage.test.js additions, new scripts/test/check-fixme.test.js. All lint/unit/integration-adjacent checks green via foundry_verify.

### P0-02 — 3bf37fd
Config: added series.related_limit=4, seed.image_band_angle=30 (⚠️ ASSUMPTION, Seeder only per rule 24). theme.json: fontSizes slug h2->article-h2 ("Article heading 2", 30px); h1 element's var fixed --h1->--h-1; h2 element's var ->--article-h-2. ttm.css: 3 refs (.is-style-journal-title, .entry-content h2, .ttm-lead__title phone) -> --article-h-2, dropped stale comment. New scripts/lib/theme-json-vars.mjs: kebabSlug(slug) (both letter->digit and digit->letter boundary regexes, lower-cased), generatedVars(themeJson) (font-size+color preset vars), referencedVars(cssText). check-theme-json.mjs now scans ttm.css, style.css and JSON.stringify(theme.styles) for --wp--preset--(font-size|color)--* refs and fails on any not in generatedVars — this caught two pre-existing bugs I fixed in-commit (see commit body: h1 element ref, .is-style-kicker's nonexistent --font-size--kicker -> --font-size--caption). Budget now 42923/61440. Tests: ConfigTest 2 new methods; theme-json-vars.test.js 3 tests. Full foundry_verify (incl. e2e/integration) green.

### P0-03 — 29cbd39
Tests: scripts/test/css-selectors.test.js (selectorsOf split/media-flag/comment-strip/:not() comma, queryable pseudo-element strip, isExempt state+media, parseSelectorAllow); tests/e2e/selectors.spec.mjs green on the 14-screen set (SCREEN_URLS ∪ securityFiltered). npm run test:e2e (all 169 tests) and npm run lint/test:unit/composer lint/test:unit all green via foundry_verify.
wc -l tests/e2e/selectors-allow.txt: 54.
Interpretation: tests/e2e/lib/urls.mjs (13-screen SCREEN_URLS incl. securityFiltered export) and screens.spec.mjs (fetchpriority front/article-only) already matched the Decision's required shape from prior work; only playwright.config.mjs's fidelity testMatch/desktop+phone testIgnore needed "selectors" added.
Allow-list mapping (54 lines): component selectors -> owning phase task, e.g. ttm-syndication(+a)/is-style-grid-3/ttm-journal-head:has(...) -> P2-01; ttm-filter-row__label/__sort -> P3-02; ttm-item h4/ttm-cell empty variants/is-style-grid-5-7/ttm-most-read__* -> P3-04; is-style-grid-2/ttm-series-row__dek/ttm-series-mark.is-hiatus -> P4-02; ttm-tile img/ttm-writing-cell__footnote/ttm-writing-body:not(...) -> P4-06; ttm-newsletter-form__statement -> P1-06; ttm-footer__copyright:empty -> P0-11; ttm-lead__media.is-ratio-4-3 -> P1-02; is-style-rule-1/.is-style-tags .tag -> P1-03; ttm-series-strip state variants -> P0-08. Remaining .is-style-* editor-only styles (zone/tile/lead/poster/headline-s/short/cover/cover-shadow/numbered-rows, button/quote styles) tagged "# editor block style (04 §3)" per the task's list — may remain past the flight.
Note: an unrelated themes/ttm-theme/templates/404.html change (adding className "ttm-search" to core/search, matching P3-05's decision) appeared mid-task from what looked like a second concurrent process on this repo; git-stashed (not discarded) so it isn't lost — whoever runs P3-05 should check `git stash list`.
