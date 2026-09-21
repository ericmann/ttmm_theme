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
- [x] P0-07 Seed rewrite — sections, journal and pages
- [x] P0-08 Seed rewrite — series and fiction
- [x] P0-09 Phase 0 push — baseline screenshots
- [x] P1-01 Full-width rules and unconstrained grids (rules 35/36)
- [x] P1-02 Front masthead per §6.1.1 (pattern, CSS, nav hub class)
- [x] P1-03 Front-page current section and nav label fill (`Nav\CurrentSection`)
- [x] P1-04 Verse copyright placement and `ttm/verse-copyright` binding
- [x] P1-05 Footer per §6.1.8
- [x] P1-06 Newsletter form contract — shared markup, provider chain, custom-url dev-accept, seed
- [x] P1-07 Spike — Jetpack Subscriptions widget POST contract
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

### P0-07 — c4b73cf
docs/fixtures/seed/posts.json: 66 posts total (40 rewritten non-fiction + 26 kept fiction rows). Non-fiction rows now use `paragraphs: N` (drawing from prose.json, P0-06) instead of lorem `content`; excerpts are the mock's deks (or original short deks where the mock gave none: php-85, block-editor, one-page-operating-agreement, etc).
Lead: signing-your-options-table (technology, days_ago:1, 52 paragraphs, featured_image, excerpt verbatim from mock). technology-post-2 slug/days_ago(4)/featured_image kept exactly (tests/e2e/lib/urls.mjs's article screen still points at it correctly, no edit needed).
Technology: 7 posts (lead + technology-post-2 + php-85-readonly-classes + block-editor-document-model + 3 techCompact fillers at 18/23/30 days — see Interpretation on why not 17/23/30). Business: 5 (charge-for-outcome.. through what-a-cto-actually-does-all-day, days 5/17/24/37/45). Security: 4 (password-manager-weakest-link.. through disclosure-timelines, days 10/21/32/141). Faith: 2 named (bug-reports day6, sabbath day19) + ordinary-time-week-1..9 (6 paragraphs each, week9=27 days per mock, week1=83, 7-day spacing) = 11 faith posts. Opinion: 3 (open-source.., city-council.., local-news.., days 9/29/417). Journal: 8 posts (journal-post-1..8), paragraphs:3, excerpts trimmed/extended to 38-48 words each (verified via node word-count script); journal-post-4 carries location:"Portland" + syndication. Writing (non-fiction): 2 (finishing-a-draft.., outlining-for-people..).
Fiction rows (quiet-ledger-ch-*, salt-iron-ch-*, story-*): kept out of scope structurally (titles/slugs/days_ago/series untouched, P0-08's job) but their lorem `content` was swapped for an equal-count `paragraphs: N` — required because the task's own lorem check/test scans the whole file regardless of scope.
pages.json: About + Newsletter rewritten, two original paragraphs each; Series/Writing pages untouched.
Tests: SeederTest — 3 new (lead resolution + thumbnail via Query\Lead::id() at set_now 2026-09-20 12:00; journal excerpt word-count range 38-48; whole-file lorem check). Existing test_seed_is_idempotent already covers count>=60.
Verified: python3 json.load valid, grep -ri lorem = 0 hits (both files), composer lint 0 errors, npm run test:integration 380/380 green (real wp-env), composer test:unit, npm run lint/build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P0-08 — daa06aa
series.json: 6 series. hardening-wordpress (in-progress, total 6, parts: hardening-part-1[new post]/technology-post-2/signing-your-options-table). the-consultants-ledger (in-progress, total 8, parts 1-5 = the 5 P0-07 Business posts oldest->newest, part5=charge-for-outcome-bill-for-hour). ordinary-time (in-progress, total 12, cadence "Sundays", parts 1-9 = ordinary-time-week-1..9). the-quiet-ledger (unchanged slug/total 31/cover/purchase_links; 12 published chapters re-spaced 30 days apart ending 1 day ago — ch1=331..ch12=1; ch7-12 use the mock's own part_titles/deks (Going Concern..Reconciliation); ch13 unchanged future:true). failover (renamed from "Salt and Iron"; salt-iron-ch-* posts renamed to failover-ch-1..9, same 5-paragraph count). salt-water-wires (brand-new 24-chapter complete novel, salt-water-wires-ch-1..24, 3 paragraphs each, cover:true — old salt-and-iron only had 9 chapters so this is genuinely new content, not a rename).
reading-cves and salt-and-iron series removed. books.json's salt-and-iron row retitled "Salt Water Wires" / series_slug + links updated.
Stories renamed: story-the-last-cron-job (50 paragraphs, 40 days — day offset given by task), story-a-field-guide-to-empty-offices (30 paragraphs, 90 days — offset not specified, chosen), story-uptime (15 paragraphs, featured_image:true, 150 days — offset not specified, chosen).
Tests: SeedStatesTest (four->six rows), SeederTest 2 new tests (published/total via SeriesIndex::by_slug, ttm_cadence meta; ch12's ttm_part_title === "Reconciliation").
Verified: composer lint 0 errors, npm run test:integration 382/382 green (real wp-env), `wp ttm seed --reset` exit 0 (91 posts, 6 series, 2 books), grep -ci lorem = 0 across all seed fixtures, composer test:unit/npm lint/build/forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P0-09 — 147933f
Reseeded cleanly (`wp-env stop && start`, `wp ttm seed --reset`): 91 posts, 6 series, 2 books, exit 0. `grep -ri lorem docs/fixtures/seed/` returns 0 hits.
`npm run screenshots` wrote all 7 PNGs; visually confirmed front-1280.png shows the mock's exact copy: "Stop trusting the database: signing your options table", "Why I moved my build pipeline off GitHub Actions — and what it cost", "Charge for the outcome, bill for the hour", "The Quiet Ledger — Ch. 12: Reconciliation". Committed (7 PNGs) and pushed: branch refine/2026-09-21, PR link printed by git push.
Full verify green: composer lint (0 errors), composer test:unit (134/134), npm run lint, npm run test:unit (5 suites), npm run build, forbidden-patterns.sh clean, npm run test:integration (382/382), npm run test:e2e (78 skipped/fixme, 48 phase-1 passed).
Manual check: NOT VERIFIED (human) — owner should open docs/feedback/phase-2/*.png and compare against design_*.png per phase-2/README.md's pairing table.
Push: origin/refine/2026-09-21 (branch existed already from foundry_run_start; this is the first push of Phase 0's content).

### P1-01 — b6bd6c7
12 is-style-grid-* groups (section-row-1/2, single/single-journal/category/page/page-series/page-writing/archive templates, archive-header, front-page's lead-row) switched constrained->default. Front-page chrome groups switched to default too: ttm-lead-row__lead, ttm-rail, ttm-journal-rail (+its ttm-cell-heading is-rail), ttm-series-strip (+its ttm-cell-heading), ttm-poster, ttm-masthead-front + its __meta/__title inner groups, plus ttm-cell-heading in category.html/page-series.html/page-writing.html (only files this task's list names — journal-stream.php/more-in-section.php/section-cell-large.php also have ttm-cell-heading but are out of this task's file list).
ttm.css: rule 35 CSS needs !important on all 4 properties to beat WP core's inline `.wp-block-separator:not(.is-style-wide):not(.is-style-dots){width:100px}` and the constrained-layout centering rule's `margin:auto !important` — confirmed empirically via live render (was 100px, now 1184px@1280 / 350px@390). Added a `@media(max-width:720px){body{--wp--style--root--padding-left/right: var(--wp--custom--gutter--phone)}}` rule since rule-2-phone's 350px needs the already-defined-but-previously-unused gutter.phone token actually wired to root-padding-aware alignment. Added `display:flex;flex-direction:column` to .ttm-rail (the only one of the changed groups that had no equivalent CSS already covering its previous layout-attribute-driven flex).
Tests: FrontPageTest::test_grid_groups_are_not_constrained (new); un-fixme'd rule-2 + rule-2-phone in fidelity.spec.mjs.
Verified: npm run lint clean, composer lint 0 errors, npm run test:integration 383/383, npm run test:e2e 76 skipped/50 passed (both rule-2 rows green), composer test:unit, npm run build, forbidden-patterns, grep is-style-grid+constrained=0. ttm-theme confirmed active; wp-env stopped after.

FLIGHT CONTROLLER NOTE (relayed, recorded for future tasks, not actioned here — see commit body):
1. .ttm-skip visible instead of screen-reader-only-until-focus -> Phase 1 masthead task (add fidelity row skip-hidden).
2. Writing cell "Also running" lists the featured serial's own ch.12 as a story -> Phase 3 Writing cell task (check ttm_form derivation + block exclusion logic).
3. technology-post-2 seeded as Hardening part 2 but mock's part 2 is "Salts, keys and the rotation you skipped" -> next task touching docs/fixtures/seed, or the Phase 2 push task.

### P1-02 — 8eaf63f
masthead-front.php: meta row's core/navigation block replaced with a plain <p class="ttm-masthead-front__links"><a>Newsletter</a><a>RSS</a><a>About</a></p> (Decision "Masthead meta links"). masthead-inner.php needed no change — already emitted ttm-nav__hub.
ttm.css: new /* 0.1 skip link */ (.ttm-skip clip-path:inset(50%) until :focus, then fixed top-left bg + 2px accent outline — fixes flight-controller item 1). 4.1 masthead front rewritten: meta row align-items:center + font-feature-settings:"tnum" 1 + padding 14px 0 (was spacing--30 token); title row gap:32px added; byline margin-top flipped from -12px (bug) to spacing--30 (12px, positive) and font-size to dek(15px); nav ul gets its own display:flex/gap:28px rule (fidelity row targets `.ttm-masthead-front__nav ul`, not the nav wrapper); .ttm-nav-series renamed to .ttm-nav__hub, split into wrapper (margin-left:auto, real visual effect) + `>a` (margin-left:auto for the row's literal check, font-weight:400, color neutral-700 !important — needed to beat core's `.wp-block-navigation-item__content.wp-block-navigation-item__content{color:inherit}` repeated-class trick, confirmed via live render); phone breakpoint gained byline 12px/meta 11px/links-a-not-first-child:none. Added minimal real CSS for .ttm-masthead-front and shared .ttm-nav (both previously allow-listed with no selector at all).
scripts/css-coverage-allow.txt: removed masthead-front, nav, nav__hub, nav-series, skip from the Phase 1 line (only newsletter-custom-url/newsletter-form__statement remain there).
Tests: ChromePartsTest 2 new tests. Un-fixme'd mast-meta/title/byline/byline-phone/nav/nav-gap/hub in fidelity.spec.mjs; fixed mast-hub's own margin-left assertion (was `toBe('auto')`, impossible since getComputedStyle never returns the literal keyword for resolved margins — SPEC's own "(x >= 1000)" annotation means a bounding-box position check, so switched to that). Added a non-SPEC skip-hidden row per the flight-controller note.
FLIGHT CONTROLLER FOLLOW-UP: item 1 (.ttm-skip visible) fixed here — see commit body for the .ttm-skip CSS and the coexistence with WP core's own auto-injected #wp-skip-link (confirmed via curl; not a conflict, ChromePartsTest needs the theme's own). Items 2 (Writing cell "Also running") and 3 (Hardening part 2 seed mismatch) still open, owned by later tasks.
Verified: npm run lint clean, composer lint 0 errors, npm run test:integration 385/385, npm run test:e2e 58 passed/69 skipped (0 failed), composer test:unit, npm run build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P1-03 — 3b870a1
Nav\CurrentSection: is_current_section() gains a front-page branch (is_front_page() && nav.front_current==='lead' → compare link path against get_category_link(PrimaryCategory::id(Lead::id()))); add_class() now adds "current-section current-menu-item" together; new fill_label() replaces a section link's anchor text with the category term's own name when the URL path matches /category/<slug>/ and slug is in sections.order (uses preg_replace_callback to avoid backreference-escaping issues with esc_html() output).
ttm.css: single `.ttm-nav .current-menu-item > a { color: accent-700 !important }` rule (folded the front-specific override into the shared .ttm-nav one since they'd be identical after the a11y fix, and .ttm-masthead-front__nav already carries .ttm-nav) — !important needed to beat core's `.wp-block-navigation-item__content.wp-block-navigation-item__content{color:inherit}` repeated-class trick (same issue P1-02 found for .ttm-nav__hub).
Tests: CurrentSectionTest 4 new tests (Config::reset() needed after add_filter('ttm_config',...) for the "none" case since Config memoizes per-process; label-fill test uses a literal /category/technology/ URL since this PHPUnit harness's plain-permalink default makes get_category_link() return ?cat= links even after set_permalink_structure(), a test-environment quirk not a real-code bug).
FIXED A BUG in my own P0-04 fidelity test: mast-current expected color('accent') but that fails WCAG AA contrast at 14px (axe caught it: 3.75:1 on the live front page) — Decision "Colour vs a11y" (already referenced in P0-04's own docblock but not applied) means accent-700; corrected the assertion and the CSS together.
plugins/ttm-core/README.md: added a paragraph on nav.front_current under Configuration.
Verified: composer lint 0 errors, npm run lint clean, npm run test:integration 389/389, npm run test:e2e 59 passed/68 skipped (0 failed, including axe on the front page), composer test:unit, npm run build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P1-04 — 1e6bf8e
Bindings\Sources: new ttm/verse-copyright source (no uses_context), returns '' unless verse.copyright_placement==='footer' and a verse is stored, plain text via finalize(). verse-of-the-day/render.php: the <small class="ttm-verse__copyright"> now also requires Config::get('verse.copyright_placement','footer')==='box' (previously rendered whenever the copyright string was non-empty, regardless of placement).
Tests: FrontSourcesTest 2 new tests; VerseOfTheDayTest 1 new (absent by default) + 1 updated (sets placement 'box' via ttm_config filter + Config::reset() before asserting the box renders it). README binding list added (enumerates all ttm/* sources including the new one).
Un-fixme'd verse-nocopy in fidelity.spec.mjs.
Verified: composer lint 0 errors, npm run lint clean, npm run test:integration 392/392 (real wp-env), npm run test:e2e 60 passed/67 skipped (0 failed), composer test:unit, npm run build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P1-05 — c8554c3
footer.html: left group (layout default) now holds one <p class="ttm-footer__meta"> bound to ttm/today format=footer (whole line via new Values::footer_line) and one <p class="ttm-footer__copyright"> bound to ttm/verse-copyright (replaces the old three-paragraph flex group + separate year binding). Right nav gets className ttm-footer__nav, layout default (CSS owns the flex/gap/separator).
Values::footer_line(site_name, year): pure, "{site} · © {year} Eric Mann · Built on WordPress". Sources::today() handles format=footer itself (get_bloginfo('name') + Clock::now()->format('Y')) since Values.php takes no WP reads.
ttm.css /* 4.34 footer */: padding bumped to spacing--40 (16px, was 12px); added .ttm-footer__copyright:empty{display:none}, .ttm-footer__nav ul flex/gap:0, .ttm-footer__nav a 400/neutral-700 + :hover accent, the wp-block-navigation-item+wp-block-navigation-item::before{content:"·"} separator; minimal .ttm-footer__left/.ttm-footer__meta rules (coverage). Fixed a real pre-existing bug: `.ttm-footer.is-after-poster` never matched anything since wp:template-part's className lands on its own wrapper div, not the part's root element (confirmed via live render) — changed to `.is-after-poster .ttm-footer`.
Tests: ValuesTest 1 new, FrontSourcesTest 1 new, ChromePartsTest 1 new (needed a real series term since F18 hides Series when the index is empty, or nav count would be 8 not 9).
Verified: composer test:unit (135/135), composer lint 0 errors, npm run lint clean, npm run test:integration 394/394 (real wp-env), npm run test:e2e 66 passed/61 skipped (0 failed), npm run build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P1-06 — ebd1fdd
New Newsletter\Form::render(action, hidden, placement, submit_name=''): the one §6.3 markup shape (label+email input `ttm-nl-email-{n}` via next_id()/reset() static counter, hidden fields, .btn.btn-ghost or .btn.btn-primary for placement=box). The $hidden array's entry matching Config's honeypot field name renders as the special .ttm-hp text input instead of plain hidden.
CustomUrl: render() now calls Form::render(); available()=endpoint_is_valid()||dev_accept_applies(); new dev_accept_applies() = empty endpoint && newsletter.dev_accept && wp_get_environment_type()!=='production'.
Handler::handle(): after validation, skips forward()+do_action('ttm_newsletter_subscribed') when CustomUrl::dev_accept_applies() (still returns the same success_url).
Providers::resolve(configured, registry, dev_accept=false): new dev_accept param inserted between configured and mailto fallback; current() passes CustomUrl::dev_accept_applies().
newsletter-form/render.php: data-state="subscribed" now also fires on ?subscribe=success (Jetpack's own redirect param), not just ?subscribed=1.
Seeder: seed_jetpack() replaced with seed_newsletter() — no WP-CLI Jetpack install, just ttm_settings.newsletter = {provider:'custom-url', endpoint:''}.
ttm.css: .ttm-newsletter-form__form flex/gap:8/align-center (input/.btn classes already self-style, redundant duplicate rules removed); .ttm-newsletter-form__statement 12px; dropped .wp-block-jetpack-subscriptions selectors. NEW: .ttm-poster .btn-ghost override (solid bg/text) — see Interpretation, a real a11y bug this task's own change surfaced.
Tests: unit ProvidersTest 2 new, HandlerTest 2 new (+ wp_get_environment_type stubbed 'production' in setUp for both HandlerTest and HandlerTuningTest, not in Files touched but required — see commit); integration NewsletterFormTest 3 new (+ f26-none test now explicitly disables dev_accept), SeedStatesTest 1 new, integration/Newsletter/HandlerTest.php extended with shared-markup assertions.
Verified: composer lint 0 errors, composer test:unit 139/139, npm run lint clean, npm run test:integration 398/398 (real wp-env), curl grep -c ttm-newsletter-form__form = 1, npm run test:e2e 66 passed/61 skipped (0 failed, axe clean after the poster-ghost fix), npm run build, forbidden-patterns all green. ttm-theme confirmed active; wp-env stopped after.

### P1-07 — d001218
Outcome B (recorded in docs/spikes/P1-jetpack-form.md with ## Outcome and ## Field list headings). Checked Jetpack 16.2 (already installed/active from an earlier phase) via reading wp-content/plugins/jetpack/modules/subscriptions/views.php's Jetpack_Subscriptions_Widget::render_widget_subscription_form(), self::is_jetpack() branch (lines 518-582) directly — no wp eval render needed, no WordPress.com connection made or attempted.
Findings: action=subscribe/source={referer}/sub-type=widget/redirect_fragment/submit name=jetpack_subscriptions_widget/email field all match SPEC §6.3's names and semantics (line refs in the spike doc), EXCEPT: (1) redirect_fragment's actual value is Jetpack's own "subscribe-blog[-N]" id scheme, not literal "ttm-newsletter-{n}"; (2) SPEC's "No nonce" claim is wrong — the form renders `wp_nonce_field('blogsub_subscribe_'.blog_id)` (line 562); (3) `Jetpack_Subscriptions_Widget::process_subscription`, SPEC's named handler method, does not exist anywhere in 16.2 (grep -rl across the whole plugin returns nothing) — no handler traceable without a real connection, out of scope here.
Implications for P1-08 recorded in the spike doc's "For P1-08" section: the nonce conflicts with cacheable-output treatment unless the block defers to Jetpack's own block/widget render rather than reconstructing markup itself (matches how NewsletterFormTest already treats jetpack as an opaque rendered block).
docs/fixtures/jetpack-subscriptions.html: verbatim-transcribed form markup with literal {current URL}/{n}/{blog_id}/{nonce} placeholders, includes the nonce field (not omitted, since it's real).
Cleanup: `wp plugin deactivate jetpack && wp plugin delete jetpack` — confirmed `wp plugin list --name=jetpack` now empty.
Verified: all three task-specified checks pass (spike file non-empty with required headings, fixture has <form>+name="email", jetpack not installed). No code changed; ttm-theme confirmed active; wp-env stopped after.
