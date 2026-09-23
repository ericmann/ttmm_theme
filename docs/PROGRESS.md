# These Things Matter — phase 4 (real content) build progress
Branch: refine/2026-09-23
Started: 2026-09-23T05:03:31.529Z

## Tasks
- [x] P0-01 Rule 47 check, tagged-fixme guard, live-script entry points, phase-4 screenshot set
- [x] P0-02 Seed: tags for every section and the older Technology neighbour
- [x] P0-03 Seed: Reading CVEs series
- [x] P0-04 New screens and §6.11 rows as tagged fixme
- [x] P0-05 Stats invalidation, tiebreak, flush_all; Business filter row green
- [ ] P0-06 §3.1 fold-in edits (regex tightening, ValuesTest case)
- [ ] P0-07 Phase 0 screenshots and push
- [ ] P1-01 Footer: one line, eight items, no Scripture copyright
- [ ] P1-02 Series TOC F11 and chronological prev/next
- [ ] P1-03 Related series: relatedTo=current, heading, F27
- [ ] P1-04 F28: Writing page on real content and the editor-only story derivation
- [ ] P1-05 Rule 50 sweep: no-context cases in every block test
- [ ] P1-06 Phase 1 screenshots and push
- [ ] P2-01 seed --starter-only, Seeder::reset() from a live state, wp ttm stats:flush
- [ ] P2-02 primary:assign --from-yoast and series:assign --from-tags/--form/--status/--total/--name
- [ ] P2-03 migrate:excerpts --from=yoast and excerpt_length
- [ ] P2-04 migrate:images and migration.* keys
- [ ] P2-05 audit flags and --summary
- [ ] P2-06 docs/migration/series.json, import.sh, plan.sh, env:live
- [ ] P2-07 screens.mjs and the live screens manifest
- [ ] P2-08 Run env:live (skip-attachments, then full); tune excerpt_length and image_timeout; LIVE-TRIAGE skeleton
- [ ] P2-09 Phase 2 screenshots and push
- [ ] P3-01 Shortcode pre-pass module
- [ ] P3-02 convert:import footnotes, dry-run listing, verification; audit shortcode list
- [ ] P3-03 Fresh import, plan re-run, conversion counts, screenshots and push
- [ ] P4-01 live.spec.mjs and the live Playwright project
- [ ] P4-02 Run test:live and write the triage table
- [ ] P4-03 Fix render-class findings with synthetic tests
- [ ] P4-04 Fix chrome/archive-class findings with synthetic tests
- [ ] P4-05 Green test:live, LIVE-TRIAGE complete, live screenshots and push
- [ ] P5-01 backup.sh, restore.sh, drill.sh, env:drill in CI
- [ ] P5-02 Documentation (§6.13)
- [ ] P5-03 Close-out guards, CSS budget record, HANDOFF
- [ ] P5-04 Final seed reset, screenshots and push

## Log
(one entry per task, appended by implement)

### P0-01 — 9ff08d9
Added rule 47 private-data check to forbidden-patterns.sh (git ls-files over docs/*.xml, docs/**/*.sql*, *.tar.gz, *.csv, docs/fixtures/live/). .gitignore already had the needed entries; no change needed there.
check-fixme.mjs: ALLOW_TAGGED = true, comment "P0-01: phase 4 in flight; P5-03 flips this back"; scripts/lib/fixme.mjs untouched.
New npm scripts env:live/env:backup/env:restore/env:drill/test:live; scripts/live/*.sh created (import.sh resolves LIVE_WXR/newest docs/*.xml/newest docs/fixtures/live/*.xml, else skip line; plan/backup/restore/drill print stub lines; test-live.sh skips cleanly without docs/fixtures/live/screens.json + tests/e2e/live.spec.mjs). All executable, shellcheck clean (one info-level SC2012 left, not required).
screenshots.mjs: OUT_DIR -> docs/feedback/phase-4; new exports SEEDED_ZONES (17: phase-3's 14 + article-noseries.png, archive-business.png, footer.png selector crop), LIVE_ZONES (7, live:true), ZONES (24, combined), resolveLiveZones() (reads docs/fixtures/live/screens.json when present, fills live-article-classic.png's path from the first { classic: true } screen -- field names are my interpretation since P2-06 hasn't written that file yet).
docs/feedback/phase-4/README.md added with seeded + live tables.
Verified: npm run lint, npm run test:unit, bash scripts/forbidden-patterns.sh, npm run env:live/test:live/env:drill, composer lint/test:unit, npm run build all pass. foundry_verify flagged pre-existing footer-no-rss and verse-copyright-removed constraint misses in files this task never touches (parts/footer.html, Bindings/Sources.php etc.) -- out of scope for P0-01, left for the SPEC §6.1 task that owns those files.

### P0-02 — fc8234a
docs/fixtures/seed/posts.json: added tags to reach >=5 distinct tag slugs per section (technology 6, business 5, faith 5, writing 6, opinion 5; security untouched, still 7; journal untouched, still 0). Appended new post why-i-still-read-the-wordpress-changelog (technology, days_ago 45, paragraphs 17 -> 611 words via prose.json cycling at row_index 102, no series/featured_image/most_read) at the end of the array so no existing row's row_index (and thus its prose()-cycled word count) shifted.
tests/integration/Cli/SeederTest.php: three new tests -- test_every_section_except_journal_has_at_least_five_distinct_tags, test_transients_post_has_an_older_technology_neighbour (technology category, no series term, older post_date), test_changelog_post_is_about_six_hundred_words (ttm_word_count 500-700). Fixed a PHPCS short-ternary/unchecked-return error on get_the_terms() in the neighbour test.
Verified: npm run test:integration (494 tests, full suite) green, SeederTest alone (38 tests) green, composer lint 0 errors, npm run lint/test:unit/build green, forbidden-patterns clean, grep -ri lorem docs/fixtures/seed/ empty.

### P0-03 — 7179e05
series.json: appended "reading-cves" (nonfiction, complete, total_parts 4, cadence "", cover false, exact SPEC description) after salt-water-wires, 4 parts pointing at reading-cves-part-1..4.
posts.json: appended 4 new Security posts (reading-cves-part-1..4, days_ago 640/560/480/400, paragraphs 12, tags drawn only from {cryptography,disclosure,passwords,threat-modeling,wordpress}, no most_read) at the end of the array -- older than every existing security post so /category/security/ page 1 is untouched, and no existing row's row_index (prose() cycling) shifts.
SeedStatesTest::test_normal_state_has_series_index_with_six_rows renamed to ..._seven_rows, asserts 7.
SeederTest: new test_reading_cves_is_a_complete_security_series_of_four (SeriesIndex::by_slug row: status complete, form nonfiction, published 4; term meta ttm_total_parts 4; row['categories'] == [security term_id]; all 4 posts published, year 2024-2025).
Verified: full npm run test:integration (495 tests, was 494) green, SeederTest+SeedStatesTest alone (46 tests) green including test_security_top_tags_are_the_mock_five (simulated tag-count math before writing: top 5 by count is still exactly wordpress/threat-modeling/passwords/cryptography/disclosure, well ahead of php/integrity at 1 each), composer lint 0 errors, npm run lint/test:unit/build green, forbidden-patterns clean, no lorem.

### P0-04 — 311d562
tests/e2e/lib/urls.mjs: SCREENS.articleNoSeries (transients-object-caches-and-fast-enough) and SCREENS.businessArchive (/category/business/), both flow into SCREEN_URLS so a11y/network/selectors loops cover them automatically.
tests/e2e/fidelity.spec.mjs: added/changed the 19 SPEC §6.11 rows as test.fixme( ..., async (...) => {...} ) // P<owner> per PLAN's row-to-task map (footer-* -> P1-01, ar-filter-business* -> P0-05, toc/bar/aside/box/prevnext-auto-* -> P1-02, single-other* -> P1-03). Old footer-copy (.ttm-footer__copyright) test deleted, not kept; footer-left untouched. footer-nav count changed 9->8 with new assertions.
Gotcha: multi-line test.fixme(...) signatures get reflowed by eslint --fix/prettier, which moves a trailing `// P<owner>` comment off the `test.fixme(` line and breaks check-fixme.mjs's same-line tag requirement. Fixed by collapsing the arg destructuring to `{ page }` (fits one line) and adding `// prettier-ignore` above each multi-line call so the format (and the tag's position) is never touched again.
single-other row: SPEC table says count 4 (up to series.related_limit, form-filtered so quiet-ledger/failover/salt-water-wires never appear); PLAN's task prose said 3 for the same row. Went with SPEC per CLAUDE.md precedence (SPEC wins over PLAN).
Verified: npm run lint green (check-fixme: 19 tagged, 0 untagged); npm run test:e2e --project fidelity (fidelity+editors+selectors specs): 338 passed, 19 skipped (new fixme rows), 0 failed; forbidden-patterns clean.

### P0-05 — 897b818
Stats.php: on_set_object_terms (set_object_terms, priority 10, 6 args) -- post_tag flushes flush_for_post(); category flushes both $tt_ids and $old_tt_ids via a term_taxonomy_id -> term_id lookup. top_tags() SQL ORDER BY cnt DESC, t.slug ASC (tiebreak); empty result cached stats.cache_seconds, non-empty stats.tags_cache_seconds. flush_all() selects matching option_name rows then delete_transient() per key (see Interpretation).
Seeder.php: Stats::flush_all() called first in run() and reset().
Tests: StatsTest 5 new (tiebreak, tag-attach refresh, category-move flush, empty-result TTL via _transient_timeout_ option, flush_all); TagFilterTest::test_import_order_terms_after_status_produces_the_row (publish then tag, simulating WXR import order).
fidelity.spec.mjs: ar-filter-business/-pos/-sort-business un-fixme'd to real test(...) (owner P0-05 in the row-to-task map).
Verified: composer lint 0 errors; StatsTest+TagFilterTest+SeederTest+SeedStatesTest filter (68 tests) green; full npm run test:integration (501 tests) green; npm run lint green (16 tagged fixme remain, 0 untagged); npm run test:e2e --project fidelity: 341 passed, 16 skipped, 0 failed; forbidden-patterns clean.
