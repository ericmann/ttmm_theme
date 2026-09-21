# These Things Matter — phase 2 (front-page fidelity) build progress
Branch: refine/2026-09-21
Started: 2026-09-21T19:10:39.166Z

## Tasks
- [x] P0-01 CSS coverage lint script and allow-list
- [x] P0-02 Config keys for phase 2 and the fallback-literal test
- [x] P0-03 Boundaries table, CSS budget and SETUP note
- [x] P0-04 Fidelity and editors Playwright skeletons (all rows fixme)
- [x] P0-05 Screenshot script and phase-2 feedback folder
- [x] P0-06 Seeder prose library and tagline
- [ ] P0-07 Seed rewrite — sections, journal and pages
- [ ] P0-08 Seed rewrite — series and fiction
- [ ] P0-09 Phase 0 push — baseline screenshots
- [ ] P1-01 Full-width rules and unconstrained grids (rules 35/36)
- [ ] P1-02 Front masthead per §6.1.1 (pattern, CSS, nav hub class)
- [ ] P1-03 Front-page current section and nav label fill (`Nav\CurrentSection`)
- [ ] P1-04 Verse copyright placement and `ttm/verse-copyright` binding
- [ ] P1-05 Footer per §6.1.8
- [ ] P1-06 Newsletter form contract — shared markup, provider chain, custom-url dev-accept, seed
- [ ] P1-07 Spike — Jetpack Subscriptions widget POST contract
- [ ] P1-08 Jetpack provider on the shared form
- [ ] P1-09 Editor registration in every context (§6.7)
- [ ] P1-10 Newsletter poster per §6.1.8
- [ ] P1-11 Phase 1 push — chrome screenshots
- [ ] P2-01 Lead story CSS and markup per §6.1.3
- [ ] P2-02 Verse box per §6.1.4
- [ ] P2-03 Journal excerpt hard cap and `ttm/category-count` entries format
- [ ] P2-04 Journal rail per §6.1.5
- [ ] P2-05 Tuning — `journal.excerpt_max_words`
- [ ] P2-06 Phase 2 push — lead row screenshots
- [ ] P3-01 Section rows, cells, cell headings and headline items per §6.1.6
- [ ] P3-02 Technology cell — inner grid and featured item with image
- [ ] P3-03 Writing cell per §6.1.6
- [ ] P3-04 Series strip per §6.1.7 (`layout=strip`)
- [ ] P3-05 Tuning — `cssBudgetBytes`
- [ ] P3-06 Phase 3 push — section rows screenshots and allow-list under 10
- [ ] P4-01 Full `3a` phone pass and ≤ 1024 pass
- [ ] P4-02 a11y and network rows, zero-fixme guard
- [ ] P4-03 Inner templates smoke and CI seeding
- [ ] P4-04 Handoff, SETUP and final budget measurement
- [ ] P4-05 Phase 4 push — final screenshots

## Log
(one entry per task, appended by implement)

### P0-01 — 93dfc47
Added scripts/lib/css-coverage.mjs (pure fns: collectMarkupClasses, collectCssClasses, globToRegExp, parseAllowList, report) and scripts/check-css-coverage.mjs (CLI). Wired as npm run check:css-coverage, appended to npm run lint. CI push branches now include refine/**.
Markup scan: themes/ttm-theme/{templates,parts,patterns,inc}/**, plugins/ttm-core/blocks/*/render.php, plugins/ttm-core/src/**/*.php (SRC_SKIP left empty — no false positives found today, kept as an extension point).
CSS scan: ttm.css + style.css.
Allow-list: exactly the 7 lines specified in the task; current repo state has 0 missing/0 dead beyond it. `node scripts/check-css-coverage.mjs` prints "156 markup classes, 111 css classes, 7 allow-listed" and exits 0.
Tests: scripts/test/check-css-coverage.test.js — class attr/className-json/wrapper() collection, is-state/prefix-literal exclusion, CSS class collection, glob brace/star matching, allow-list parsing (blank/comment skip), report missing/dead after allow, allowCount>=10 fails.
npm run lint, npm run test:unit, composer lint, composer test:unit, npm run build, forbidden-patterns.sh all green (foundry_verify).

### P0-02 — eea4274
Added nav.front_current='lead', journal.excerpt_max_words=55, verse.copyright_placement='footer', newsletter.dev_accept=true to Config::defaults(); one-line comments added to cells.stale_count, writing.tile_columns, sections.technology_slug, and the four new keys. cells.thin_days was already absent (removed in an earlier flight) — confirmed via grep, nothing to remove.
tests/unit/ConfigFallbacksTest.php (new): scans plugins/ttm-core/src/**/*.php + blocks/*/render.php for single-line `Config::get( 'key', <literal> )`; decodes string/int/float/bool literals, skips `[]` (key-existence only) and non-literal args (variables); asserts every referenced key exists in defaults() and every literal `==` its default.
This surfaced a real bug: Verse/Fetcher.php's verse.user_agent fallback was 'TTM-Core/{version}' vs default 'TTM-Core/{version} (+https://eric.mann.blog)' — fixed the fallback literal to match (file added to Files touched beyond the task list, since the new acceptance test can't pass otherwise).
ConfigTest.php's expected-keys list updated with the four new keys.
Verified: composer test:unit (133/133), composer lint (0 errors), npm run lint, npm run test:unit, npm run build, forbidden-patterns, npm run test:integration (374/374) all green. grep -rn thin_days plugins tests: empty.

### P0-03 — 568e7d6
ROW_ORDER reordered to SPEC §4: ., Support, Taxonomy, Meta, Query, Fiction, Cache, Verse, Newsletter, Bindings, Blocks, Editor, Templates, Nav, Rest, Compat, Admin; Cli removed (exempt, always skipped as source, unmapped as target).
New MAY_IMPORT const encodes the exact per-row "May import" column (not just rank); Blocks/tier-6 dirs list "everything above" explicitly.
KNOWN_EXCEPTIONS (one-line reasons each): Cache->Meta, Cache->Query (Headers/Purge reading PrimaryCategory/SeriesIndex), Verse->Admin, Newsletter->Admin, Fiction->Admin (Admin\Page settings-screen inheritance). Applied in all three test methods so these don't fail; reported under "## Spec issues" in the commit body per task instructions — not refactored (cache/verse/newsletter internals out of scope).
New test_may_import_column_is_enforced_per_directory: builds a [file => [dir, used_dirs]] map from the real tree and asserts zero violations against MAY_IMPORT+KNOWN_EXCEPTIONS+self; a synthetic Support/->Query fixture (dict literal, no disk I/O) is asserted to produce a violation, proving the check has teeth.
scripts/check-budget.mjs: cssBudgetBytes 33200 -> 40960 (CLAUDE.md already said 40960; this file was stale). Current ttm.css is 33070 bytes, well under.
docs/SETUP.md: added Troubleshooting row for the wp-env stale-bind-mount "theme disappears" symptom -> `npx wp-env stop && npx wp-env start`.
Verified: composer test:unit (134/134), composer lint (0 errors), npm run lint (budget prints ".../40960 bytes"), npm run test:unit, npm run build, forbidden-patterns.sh all green.

### P0-04 — e761ded
tests/e2e/fidelity.spec.mjs: every SPEC §6.2 row transcribed as test.fixme, grouped by id+viewport (rule-2, lead-row merged per the task rule), organized into test.describe zones matching §6.1 order. a11y/network "both" rows split into @1280/@390 tests each. tests/e2e/editors.spec.mjs: editor-sed, editor-customizer as fixme, reading ttm/* names from plugins/ttm-core/blocks/*/block.json at test time.
tests/e2e/lib/presets.mjs: color(slug) reads theme.json palette, hex->rgb (rgba presets like divider pass through); px(n). tests/e2e/lib/style.mjs: computed(locator,prop), tracks(locator) (grid-template-columns -> number[]), before(locator,prop) (::before pseudo). tests/e2e/lib/urls.mjs: added ADMIN={user:'admin',pass:'password'}.
playwright.config.mjs: new `fidelity` project (testDir:'.', testMatch on the two files), testIgnore added to desktop/phone.
INTERPRETATION (important for future tasks): files are named fidelity.spec.mjs/editors.spec.mjs, not .spec.js as SPEC/PLAN literally say — Playwright's transform runs .js test files as CommonJS here, which can't require() the real-ESM lib/*.mjs helpers (confirmed failure under the actual `npm run test:e2e`); every existing tests/e2e/specs/*.mjs file already uses .mjs for this exact reason. Updated CLAUDE.md's two references and docs/SETUP.md to match; SPEC.md/PLAN.md still say .spec.js (not edited, not mine to touch) — later tasks whose "Files touched" says "tests/e2e/fidelity.spec.js" mean this file. Also avoided import.meta.url (same CJS-transform incompatibility) in favor of process.cwd()-relative paths.
Test count: 78 total (76 fidelity + 2 editors), not the task text's "84 fidelity ... 2 editors" — SPEC §6.2 has 77 data rows, one duplicate (lead-row) merges, a11y/network double via "both" viewport; recounted from the live table, transcribed faithfully rather than padded.
Verified: npm run lint clean; npm run test:e2e green (78 skipped, 48 phase-1 passed); ttm-theme confirmed still active theme after the run; wp-env stopped.

### P0-05 — d435339
scripts/screenshots.mjs exports ZONES (7 entries) and unionClip(a,b) (pure), and runs the capture only when invoked as CLI (process.argv[1] check — NOT import.meta.url, which broke Jest's dynamic import() of this module in the test; @playwright/test is imported lazily inside run() for the same reason).
Zones: front-1280/390 (fullPage), masthead/.ttm-masthead-front, lead-row/.ttm-lead-row, series-strip/.ttm-series-strip (single-element .screenshot()); section-rows (first/last .ttm-section-row via .first()/.last(), not :first-of-type/:last-of-type — that CSS pseudo matched 0 elements against the real DOM) and poster-footer (.ttm-poster to .ttm-footer) use unionClip + a viewport resize to the clip's bottom edge first (page.screenshot clip is viewport-relative, not page-relative).
Verified against real wp-env (seeded): `npm run screenshots` wrote all 7 PNGs to docs/feedback/phase-2/; deleted them afterward (out of scope to commit here — P0-09 does, and they're not needed to keep the tree clean). Confirmed ttm-theme stayed the active theme; wp-env stopped after.
Tests: scripts/test/screenshots.test.js (2 tests, both green). npm run lint, npm run test:unit (5 suites incl. this one), composer lint/test:unit, npm run build, forbidden-patterns all green (foundry_verify).

### P0-06 — 49cbba0
docs/fixtures/seed/prose.json: 48 original paragraphs (2-5 sentences, no lorem, written for this repo — technology/business/faith/writing voice matching the mock's byline).
Seeder::prose(int $row_index, int $count): private, cycles paragraphs by ($row_index + offset) % total, wraps each in a core/paragraph block via esc_html(); 0 count or missing fixture -> ''. seed_posts() loop changed to `foreach ( $rows as $index => $row )`; post_content is now `( $row['content'] ?? '' ) . $this->prose( $index, (int) ( $row['paragraphs'] ?? 0 ) )` — posts.json rows have no `paragraphs` key yet, so output is unchanged (verified: full integration suite green).
Seeder::run() calls update_option('blogdescription', ...) with the exact SPEC §6.5 tagline after seed_verse()/seed_jetpack().
scripts/forbidden-patterns.sh: allowed_core_options gained blogdescription. .wp-env.json: afterStart's blogdescription literal updated to match the SPEC tagline exactly (was a slightly different phase-1 string).
Tests: tests/integration/Cli/SeederTest.php — 3 new tests (prose() called via ReflectionMethod since it's private; no existing precedent for testing a private Seeder method, reflection seemed least invasive).
Verified: composer lint (0 errors), forbidden-patterns.sh clean, grep -ci lorem = 0, npm run test:integration 377/377 green (real wp-env run, ~2min), composer test:unit, npm run lint/test:unit/build all green. ttm-theme confirmed still active; wp-env stopped after.
