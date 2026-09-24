# These Things Matter — phase 5 (demo content, Playground, open source) build progress
Branch: refine/2026-09-24
Started: 2026-09-24T16:34:49.705Z

## Tasks
- [x] P0-01 Flight harness: tagged-fixme window, devDependencies, entry points, demo constants
- [x] P0-02 Author name: Config keys, ttm/author-name, footer line and mastheads rewired
- [x] P0-03 §6.9 demo rows as tagged fixme
- [x] P0-04 LICENSE, readme.txt, demo LICENSE.md, version 0.2.0, check-license
- [x] P0-05 check-demo (rules 53 and 56) in npm run lint
- [x] P0-06 SI-13 Photon rewrite to home_url(); SI-16 undated attribution in docs/01
- [x] P0-07 SI-17 CodeColorer `<code lang>` pre-pass rule and audit flag
- [x] P0-08 Owner screenshot set moves to docs/feedback/phase-5
- [x] P0-09 Phase 0 screenshots and push
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

### P0-03 — cee6e73
Added describe('demo') block to tests/e2e/fidelity.spec.mjs with the 6 required test.fixme() rows (demo-lead-photo, demo-cells-photo, demo-article-hero, demo-about-portrait, demo-tile-cover, demo-alt x1280/390), each tagged // P1-05 on the same source line as test.fixme( per check-fixme.mjs's per-line scan (used the // prettier-ignore + trailing-comment convention already visible in git history for earlier phases' fixme rows, since Prettier otherwise reformats a multi-line arrow signature and separates the tag from the call). Added a loadImage() helper (sets loading=eager, waits for complete && naturalWidth>0) for demo-about-portrait's naturalHeight/naturalWidth ratio check. presets.mjs gained seedPost(slug)/seedPage(slug) reading docs/fixtures/seed/{posts,pages}.json; note posts.json has no `caption` field yet (demo-article-hero references seedPost(...).caption) -- fine since the row is fixme and not executed, but a later task must add it to the fixture when un-fixme'ing.
Tests: check-fixme reports "6 tagged fixme row(s)" and "clean"; npm run test:e2e 494 passed / 8 skipped / 0 failed (6 new + 2 pre-existing skips), exit 0.
Verified: foundry_verify ok:true, 0 constraint fails, all 7 verify commands green (composer lint/test:unit, npm run lint/test:unit/build, forbidden-patterns.sh, npm run test:e2e).

### P0-04 — c5429f9
New: LICENSE (FSF gpl-2.0.txt byte copy, sha256 8177f975...b880643 verified), plugins/ttm-core/readme.txt, themes/ttm-theme/readme.txt (WordPress.org format, both Stable tag 0.2.0, License GPL-2.0-or-later + License URI; theme readme has == Copyright == for Archivo/OFL), docs/fixtures/demo/LICENSE.md (CC0 1.0 prose/PDM photos/OFL fonts/GPL-2.0-or-later code, verse-sample exclusion note). Version bumped to 0.2.0 everywhere: package.json (npm version --no-git-tag-version), package-lock.json, ttm-core.php header+TTM_CORE_VERSION, style.css. style.css License: field changed from the old "GNU General Public License v2 or later" prose to the literal GPL-2.0-or-later.
scripts/lib/license-checks.mjs: 5 pure functions (checkLicenseFile/checkLicenseFields/checkVersions/checkRequiredFiles/checkOwnerName) over a {read,list} context, each returning a failure-string array. checkVersions compares every discovered version field to the first (package.json); checkOwnerName reads Config.php's site.author_name and scans list()'s plugins/+themes/ text files (skips png/jpg/jpeg/gif/webp/woff/woff2/zip), allowing the name only in Config.php, Author: header lines (style.css/ttm-core.php) and readme.txt Contributors:/Copyright lines. scripts/check-license.mjs wires these to git ls-files + fs, "check-license: clean" or exits 1. Added check:license npm script, appended to the lint chain.
Tests: scripts/test/check-license.test.js, 15 tests incl. "the real repository passes" (runs the checks over the actual working tree via git ls-files).
Verified: composer lint (0 errors), composer test:unit 190/190, npm run lint (incl. check:license clean), npm run test:unit 130/136 (6 pre-existing skips), npm run build, forbidden-patterns.sh clean, npm run test:integration 598/598, npm run test:e2e 494 passed/8 skipped/0 failed (re-ran after one flaky selectors.spec.mjs timeout that reproduced only under concurrent-verify load, passed both standalone and on a clean re-run), sha256sum LICENSE matches the FSF digest.

### P0-05 — 23e589b
scripts/lib/demo-checks.mjs: 3 pure functions over already-read data. checkCredits({files,credits},{maxBytes,budgetBytes}) -- both-absent passes; credits null with files present fails; per-row checks all 13 required fields (creator_url allowed null via hasOwnProperty), license in cc0/pdm, bytes/sha256 match the file, file name regex ^demo-[a-z0-9-]+\.jpg$, per-file maxBytes and summed budgetBytes, orphan file/orphan row both reported. checkFixtureImages(rows, fileNames) only inspects string featured_image values (the current fixtures use boolean true, so this is a no-op today but will bite once P1-xx sets real string filenames). checkReadmeScreenshots(readme, fileNames) parses markdown-image and <img src> refs to .github/screenshots/, checks existence both ways plus .png extension; no directory + no refs passes.
scripts/check-demo.mjs wires these to docs/fixtures/demo/images/ (sha256'd via node:crypto), docs/fixtures/demo/CREDITS.json, docs/fixtures/seed/{posts,pages}.json, README.md, .github/screenshots/; prints check-demo: clean or exits 1. Added check:demo npm script, appended to the lint chain (after check:license).
Tests: scripts/test/check-demo.test.js, 15 tests per the task's acceptance list.
Verified: foundry_verify ok:true, 0 constraint fails; composer lint 0 errors, composer test:unit 190/190, npm run lint (incl. check:demo clean on the real empty-demo tree), npm run test:unit 145/151 (6 pre-existing skips), npm run build, forbidden-patterns.sh clean.

### P0-06 — 50bf0bc
Html::photon_origin_url() gained a third $home='' param: unchanged https://$match_host/... behaviour when $home is '', else scheme+host replaced by untrailingslashit($home) with path+query kept (matches[2] already includes the query string). MigrateCommand::images() now calls Html::photon_origin_url($src, $origin, home_url()), so a rewritten Photon image points at wherever this WordPress install actually is, not the live production domain -- SI-13.
docs/01-design-language.md §4.9: attribution sentence changed from "Meditation for {Mon D} from **dailymedtoday.com**" to "Meditation from **dailymedtoday.com**" (SI-16, undated). docs/MIGRATION.md §2.5a: added a sentence noting Photon URLs rewrite straight to home_url() with no sideload/fetch.
Tests: HtmlTest::test_photon_origin_url_rewrites_to_home_when_given (stubs untrailingslashit via Brain\Monkey); MigrateCommandTest's one content-asserting Photon test now expects home_url('/wp-content/uploads/2020/photo.jpg') (the other two tests sharing that fixture URL don't inspect post_content, so were left unchanged).
Verified: composer lint 0 errors, composer test:unit 191/191, npm run test:integration 598/598, grep -n "Meditation for" docs/01-design-language.md prints nothing, foundry_verify ok:true 0 constraint fails.

### P0-07 — 6a798a2
scripts/lib/shortcodes.mjs: new transformCodeColorerTags(html), called inside preprocessShortcodes() after transformShortCodeShortcodes and before autop (autop lives in a separate module, prepare-classic.mjs, already called after preprocessShortcodes -- ordering preserved, verified by a new pipeline-order test mirroring the existing R4-01 one). Regex captures optional wrapping <pre>...</pre>, a <code ...> tag's full attribute string (to find lang= anywhere in it, not just first), and content up to </code>; a lang-less <code> or single-line lang'd <code> returns the original match untouched; multi-line -> single <pre class="wp-block-code"><code lang="x">escaped</code></pre>, extra attrs (width/height) dropped by construction. escapeOnce() made entity-aware (ENTITY_OR_BARE_CHAR_RE: a full &name;/&#N;/&#xN; reference is left alone, only a bare &/</> gets escaped) so already-escaped bodies (shape 5) aren't double-escaped -- confirmed this doesn't touch existing [cci]/[cc]/[cc_x] fixtures (none contain entities).
AuditCommand::shortcode_names() gains has_bare_codecolorer_tag(): PREG_OFFSET_CAPTURE over every <code ...lang="...".> open tag, checks the run up to the next </code> for a newline, and that the text immediately before the open tag (rtrim'd) doesn't end with <pre> or <pre class="wp-block-code">; appends 'codecolorer' to the shortcode list when true.
docs/MIGRATION.md §2.6 gained a sentence naming the new rule and the audit flag.
Tests: scripts/test/shortcodes.test.js describe('CodeColorer tag syntax') -- 5 shapes, inline-unchanged, no-lang-unchanged, idempotent, pipeline-order (20 total in file, was 11). tests/integration/Cli/AuditCommandTest.php: 3 new methods + a detail_for() helper.
Verified: composer lint 0 errors, composer test:unit 191/191, npm run lint clean, npm run test:unit 154/160 (6 pre-existing skips), npm run build, forbidden-patterns.sh clean, npm run test:integration 601/601, foundry_verify ok:true 0 constraint fails (re-ran after an initial npm-run-lint prettier failure in the new test file, fixed via --fix and re-verified).

### P0-08 — 5218344
Rewrote scripts/screenshots.mjs: SETS = { owner: { outDir: 'docs/feedback/phase-5', zones: OWNER_ZONES } } replaces the old flat SEEDED_ZONES/LIVE_ZONES/PHASE_3_ZONES/PHASE_4_ZONES/resolveLiveZones/OUT_DIR. OWNER_ZONES is the six §6.10 files (front, article, archive-technology, writing, about all 1280x900 full-page; front-390 at 390x844 full-page). Removed all live-zone/screens.json handling entirely (nothing else in the repo imports those symbols). run() now iterates Object.values(SETS), mkdir'ing each set's own outDir; capture logic factored into captureZone() (still supports selector/range crops for a future set, per the task's "so P3-01 can add readme"). Replaced the old scroll-the-page lazy-load trick with the task's exact phase-3 REVIEW F3 approach: force every img[loading="lazy"] to eager, then wait for img.complete && img.naturalWidth > 0 before checking pendingImages() and taking the shot.
New docs/feedback/phase-5/README.md: table of the six files with what to look at (photo fit/crop, editorial captions not credits, no red, no placeholder bands, masthead/footer name via Config::author_name()).
Tests: scripts/test/screenshots.test.js rewritten -- SETS.owner shape/six files/fullPage-everywhere, no-live-zones (source doesn't mention LIVE_ZONES or screens.json), front-390 viewport; pendingImages/unionClip tests kept unchanged.
Verified: foundry_verify ok:true 0 constraint fails; composer lint 0 errors, composer test:unit 191/191, npm run lint clean, npm run test:unit 150/156 (6 pre-existing skips), npm run build, forbidden-patterns.sh clean.

### P0-09 — ddd1c4a
Ran npx wp-env start, npm run env:seed -- --reset, npm run screenshots -- wrote all 6 §6.10 files (front.png, article.png, archive-technology.png, writing.png, about.png, front-390.png) to docs/feedback/phase-5/, committed. Full verify set green: composer lint (0 errors), composer test:unit 191/191, npm run lint clean, npm run test:unit 150/156 (6 pre-existing skips), npm run build, forbidden-patterns.sh clean, npm run test:integration 601/601, npm run test:e2e 494 passed/8 skipped/0 failed (confirmed real exit code 0 by redirecting to a file rather than piping through tail, since `cmd | tail` masks the pipe's real exit status; the one selectors.spec.mjs timeout seen in an earlier concurrent-verify run reproduced nowhere in this final pass).
Pushed: git push -u origin HEAD succeeded (new branch refine/2026-09-24 on origin); git log origin/refine/2026-09-24..HEAD is empty.
Manual check: NOT VERIFIED (human) -- optional: front.png/article.png masthead "by Eric Mann" and footer line unchanged.
