# These Things Matter — phase 5 (demo content, Playground, open source) build progress
Branch: refine/2026-09-24
Started: 2026-09-24T16:34:49.705Z

## Tasks
- [x] P0-01 Flight harness: tagged-fixme window, devDependencies, entry points, demo constants
- [x] P0-02 Author name: Config keys, ttm/author-name, footer line and mastheads rewired
- [ ] P0-03 §6.9 demo rows as tagged fixme
- [ ] P0-04 LICENSE, readme.txt, demo LICENSE.md, version 0.2.0, check-license
- [ ] P0-05 check-demo (rules 53 and 56) in npm run lint
- [ ] P0-06 SI-13 Photon rewrite to home_url(); SI-16 undated attribution in docs/01
- [ ] P0-07 SI-17 CodeColorer `<code lang>` pre-pass rule and audit flag
- [ ] P0-08 Owner screenshot set moves to docs/feedback/phase-5
- [ ] P0-09 Phase 0 screenshots and push
- [ ] P1-01 images.json and the Openverse fetch script
- [ ] P1-02 Seeder sideloads demo photographs (DemoImage, --no-demo-images)
- [ ] P1-03 Fetch the 13 photographs; tune IMAGE_MAX_BYTES and IMAGE_BUDGET_BYTES
- [ ] P1-04 Fixture photographs: featured_image, alt and caption values
- [ ] P1-05 Story tile cover class; demo rows green; drill still deterministic
- [ ] P1-06 Phase 1 screenshots and push
- [ ] P2-01 Spike: export term definitions, Playground import and CLI server shape
- [ ] P2-02 DemoCommand (demo:options, demo:verify) and seed --now
- [ ] P2-03 wxr.mjs: pure WXR normalisation
- [ ] P2-04 build.mjs, blueprint template and the committed demo outputs
- [ ] P2-05 release:pack (plugin and theme zips with build/)
- [ ] P2-06 check.mjs: headless Playground check on a local variant
- [ ] P2-07 CI demo step and the term-meta record
- [ ] P2-08 Phase 2 screenshots and push
- [ ] P3-01 README screenshots (--readme); tune SCREENSHOT_MAX_BYTES
- [ ] P3-02 Public README; developer commands to SETUP.md; rule 56 strict
- [ ] P3-03 Release workflow
- [ ] P3-04 Phase 3 screenshots and push
- [ ] P4-01 Guards back to strict; allow-lists; budget and audits recorded
- [ ] P4-02 Documentation index, SETUP, plugin spec and HANDOFF
- [ ] P4-03 Final demo refresh, seed reset, screenshots and push

## Log
(one entry per task, appended by implement)

### P0-01 — 7a04a0a
Added ALLOW_TAGGED=true to scripts/check-fixme.mjs (P0-01 comment, P4-01 flips back), updated its test. Added devDependencies @wp-playground/cli ^3.1.54 (matches @wordpress/env's resolved 3.1.54/3.1.55) and sharp ^0.35.4; npm audit clean, dependencies stays {}. New npm scripts demo:fetch-images, demo:build, demo:check, release:pack -> stub files in scripts/demo/{fetch-images,build,check}.mjs and scripts/release/pack.mjs, each printing the required stub line and exit 0. scripts/demo/lib/constants.mjs exports the six SPEC §5 constants (IMAGE_MAX_BYTES 350000, IMAGE_BUDGET_BYTES 8000000 both tagged ⚠️ ASSUMPTION tuned in P1-03; OPENVERSE_MIN_WIDTH 1600, OPENVERSE_PAGE_SIZE 20, OPENVERSE_PACE_MS 3500, OPENVERSE_LICENSES 'cc0,pdm'). .gitignore gained dist/ and docs/fixtures/.demo-build/.
Interpretation: CLAUDE.md's module map (rewritten by the plan stage into condensed "unchanged" placeholders for Cache/Verse/Newsletter/Editor/Rest/Admin) already failed ScaffoldTest::test_claude_md_module_map_names_every_src_class before I touched anything -- confirmed via git stash. No later task in PLAN.md touches CLAUDE.md or ScaffoldTest, and this failure blocks composer test:unit globally for every subsequent task's foundry_verify, so I expanded those module-map lines to literally name every existing src class (kept the "(unchanged)" note) rather than leave a known-broken test in the tree.
Tests: scripts/test/check-fixme.test.js, scripts/test/demo-constants.test.js; composer test:unit now green (184/184, was failing ScaffoldTest before this commit).
Config keys: none (demo constants are script constants per rule 24 amendment, not Config keys).

### P0-02 — 830492a
Config: added 'site.author_name' => 'Eric Mann', 'site.author_url' => 'https://eric.mann.blog' to defaults(), plus Config::author_name()/author_url() accessors (fallback = defaults()[key]). New source ttm/author-name (no uses_context, like pagination-label/search-summary), formats by (default)/name/byline-link/url via pure Values::author_line(); only byline-link keeps HTML (finalize()'s allow_html param), matching ttm/meta-line's HTML-in-paragraph-content-only rule. Values::footer_line() gained a third $author_name param. masthead-inner.php and masthead-front.php now bind ttm/author-name (format by / byline-link respectively) with empty saved content instead of a literal string; front byline's rendered <span>by</span> <a href=".../about/">{name}</a> is byte-identical (verified by e2e mast-byline/mast-inner-by rows, still green). Seeder's display_name now reads Config::author_name(). grep -rn "Eric Mann" plugins themes (excluding Config.php/Author: lines) is empty -- this also cleared the owner-name-confined foundry constraint that P0-01 could not touch.
Tests: ConfigTest::test_author_accessors_return_defaults, ValuesTest (footer_line + 5 author_line cases), new tests/integration/Bindings/AuthorNameSourceTest.php (4 methods per task spec), FrontSourcesTest/ChromePartsTest/SeederTest updated to read Config::author_name(). fidelity.spec.mjs: new authorName()/escapeRegExp() in presets.mjs+spec file, FOOTER_COPY_RE built from it (replaces both hard-coded footer-copy regexes), new describe('name') block with name-footer and name-masthead rows.
Verified: composer lint/test:unit, npm run lint/test:unit/build, forbidden-patterns.sh, npm run test:integration (598/598), npm run test:e2e (494 passed, 1 skipped, 0 failed) all green; foundry_verify ok:true, 0 constraint fails.
