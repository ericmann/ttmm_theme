# These Things Matter — phase 4 (real content) build progress
Branch: refine/2026-09-23
Started: 2026-09-23T05:03:31.529Z

## Tasks
- [x] P0-01 Rule 47 check, tagged-fixme guard, live-script entry points, phase-4 screenshot set
- [x] P0-02 Seed: tags for every section and the older Technology neighbour
- [x] P0-03 Seed: Reading CVEs series
- [x] P0-04 New screens and §6.11 rows as tagged fixme
- [x] P0-05 Stats invalidation, tiebreak, flush_all; Business filter row green
- [x] P0-06 §3.1 fold-in edits (regex tightening, ValuesTest case)
- [x] P0-07 Phase 0 screenshots and push
- [x] P1-01 Footer: one line, eight items, no Scripture copyright
- [x] P1-02 Series TOC F11 and chronological prev/next
- [x] P1-03 Related series: relatedTo=current, heading, F27
- [x] P1-04 F28: Writing page on real content and the editor-only story derivation
- [x] P1-05 Rule 50 sweep: no-context cases in every block test
- [x] P1-06 Phase 1 screenshots and push
- [x] P2-01 seed --starter-only, Seeder::reset() from a live state, wp ttm stats:flush
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

### P0-06 — c37dd85
tests/e2e/fidelity.spec.mjs: hub-grid-cats now asserts exact text "Technology · Security" (computed: the-quiet-ledger grid is sorted by last_update desc across all 7 series; hardening-wordpress's hardening-part-4-keys-in-the-environment at days_ago 0 is the most recent published part of any series, so hardening-wordpress is first; its categories in sections.order are Technology, Security). wr-serial-form asserts exact "Novel · literary thriller · monthly" (the-quiet-ledger, first row of the Writing page's is-list), no /i flag.
tests/unit/Bindings/ValuesTest.php: removed the "day is also month and year" assertSame('Month', ...) block from test_archive_kind_labels_and_empty; kept the seven single-flag assertions and both empty-input cases.
Verified: composer test:unit (171 tests) green; npm run lint green (16 tagged fixme, unchanged); npm run test:e2e --project fidelity: 341 passed, 16 skipped, 0 failed (full run, not just the two changed rows, since hub-grid-cats touches ordering semantics broadly); forbidden-patterns clean.

### P0-07 — fee247f
Captured the 17-file phase-4 seeded screenshot set via npm run screenshots against wp-env (seeded --reset); no live PNGs yet since docs/fixtures/live/screens.json doesn't exist (P2-06+). archive-business.png visually confirms the filter row (Filter · All · five tags: strategy/clients/consulting/invoicing/pricing · Newest first) between the archive head and the year list, matching SPEC §8 Phase 0's visible result.
foundry_verify green (composer lint 0 errors, composer test:unit 171, npm run lint/test:unit/build, forbidden-patterns) -- same two pre-existing footer-no-rss/verse-copyright-removed constraint misses as every prior task, unrelated to this task's PNG-only files.
Pushed refine/2026-09-23 to origin (new branch).
Manual check: NOT VERIFIED (human) -- compare docs/feedback/phase-4/archive-business.png with mock 1e line 933.

### P1-01 — 038f213
footer.html: removed .ttm-footer__copyright paragraph and the RSS nav-link (8 items: 7 sections + Series).
ttm.css: dropped .ttm-footer__copyright and .ttm-verse__copyright rules; .ttm-footer__nav ul flex-wrap nowrap at >=721 (wrap restored under 720px); added explicit font-size (caption/12px) on .ttm-footer__nav a and line-height:1.15 on .ttm-footer (both needed beyond the listed selectors to hit the <=20px ul / <=48px footer height caps -- inherited defaults were 14px font and ~1.5 line-height). Budget 62568->62640/63488.
Sources.php/Config.php/verse-of-the-day render.php: removed ttm/verse-copyright source, verse_copyright() method, verse.copyright_placement key, and the verse box's 'box' branch entirely.
Tests: ChromePartsTest renamed+adjusted (8 items, no /feed/); FrontSourcesTest's 2 verse-copyright tests deleted; VerseOfTheDayTest::test_copyright_is_rendered_as_plain_text -> test_copyright_is_never_rendered (and the now-duplicate test_copyright_is_absent_from_the_box_by_default deleted); ConfigTest key list updated. fidelity.spec.mjs: 6 footer-* rows un-fixme'd; verse-nocopy row deleted (selector it checked for no longer exists in CSS/markup at all).
Interpretation: docs/foundry.json's verse-copyright-removed constraint forbids the literal strings ttm-verse__copyright/ttm-footer__copyright/etc. anywhere in tests/, including inside assertStringNotContainsString()/substr_count() absence checks and comments -- had to scrub those too, not just the feature code, to get the constraint green.
Verified: composer lint 0 errors, composer test:unit 171; npm run lint green (191/191 coverage, 10 tagged fixme, budget 62640/63488); full npm run test:integration (498 tests) and npm run test:e2e (481 passed/10 skipped/0 failed) green; forbidden-patterns clean; grep for verse-copyright|verse_copyright|copyright_placement across plugins/themes/tests empty; foundry_verify constraints all ok:true including footer-no-rss and verse-copyright-removed (previously failing since P0-01).

### P1-02 — 9c574fc
series-toc/render.php: moved $ttm_variant read before series-row resolution; only 'chapters' variant falls back to Serials::active() when no seriesId/post-series is found -- 'series' variant now returns '' instead.
series-prev-next/render.php: unchanged -- its else/chronological branch already queries WP_Query by primary category only (no series exclusion), so a series part sharing the primary category was already a valid neighbour; verified with a new test rather than a code change.
Tests: SeriesTocTest 2 new (series variant renders nothing despite an active in-progress fiction serial; chapters variant still falls back to it); SeriesPrevNextTest 2 new (title+label pair asserted together; series-part-as-chronological-neighbour).
fidelity.spec.mjs: un-fixme'd toc-absent, bar-absent, aside-noseries-order, box-noseries, prevnext-auto-label, prevnext-auto-title (all P1-02-owned rows per the row-to-task map); 4 P1-03 rows remain fixme.
Verified: composer lint 0 errors; SeriesTocTest+SeriesPrevNextTest filter (20 tests) green; full npm run test:integration (502 tests) green; npm run lint green (4 tagged fixme remain); npm run test:e2e --project fidelity: 352 passed, 4 skipped, 0 failed; forbidden-patterns clean.

### P1-03 — 260f34a
block.json: new attributes relatedTo (enum ["","current"], default "") and heading (string, default "").
render.php: new relatedTo="current" branch before the normal status/form filtering -- requires the queried object to be a series WP_Term (else ''), resolves current = SeriesIndex::get(term_id) (else ''), candidates = every other row whose form is in the fiction-class set (novel/novella/story-cycle) iff current's form is too (same form class both ways), ranked by shared-category count desc / last_update desc / slug asc, sliced to limit ?: Config::get('series.related_limit',4); empty candidates -> '' (F27). Non-empty `heading` attribute renders a `.ttm-cell-heading.is-rail` block (same shape as ttm/series-toc) before the rows, in either branch.
taxonomy-series.html: `.ttm-series-single__other` now wraps a single `<!-- wp:ttm/series-list {"relatedTo":"current","layout":"list","heading":"Other series"} /-->`; the old separate heading group is gone.
docs/06-fallbacks.md: added F27 row.
Tests: SeriesListTest 5 new (same-form-only, shared-section-then-update ranking, F27 empty, heading render, outside-series-page empty) plus a go_to_series() helper mirroring the existing excludeCurrent test's query-var pattern.
fidelity.spec.mjs: un-fixme'd the 4 P1-03 rows -- single-other's count corrected from SPEC's literal "4" to the actually-achievable 3 (related_limit is a cap, not a guarantee; hardening-wordpress only has 3 other nonfiction series once the 3 fiction ones are excluded by form) -- fidelity.spec.mjs now has zero test.fixme rows.
Verified: composer lint 0 errors; SeriesListTest alone (23 tests) green; full npm run test:integration (507 tests) green; npm run lint green (0 tagged fixme remain); npm run test:e2e --project fidelity: 356 passed, 0 skipped, 0 failed; forbidden-patterns clean; check:block-json clean.

### P1-04 — 72cb27f
Form.php: is_editor_save() (true unless WP_CLI/WP_IMPORTING defined, filterable via ttm_form_editor_save); on_save() gains optional $from_editor param, skips writing 'story' (leaves meta untouched) when not an editor save; article/chapter always written. Seeder's two Form::on_save() call sites now pass true explicitly.
Stats.php: story_count() (published ttm_form=story count, cached ttm_stats_story_count, TTL stats.cache_seconds; WP_Query fields=ids/posts_per_page=1 + found_posts keeps it bounded); invalidated on added/updated/deleted_post_meta for key ttm_form and by flush_all().
Serials::has_any_fiction() reads Stats::story_count() instead of stories(1).
Hierarchy.php: is_f28() (no fiction-form SeriesIndex row and story_count()===0); pre_get_posts route_writing_page() rewrites the /writing/ page query into the Writing category archive while F28 holds (also nulls WP_Query's already-cached queried_object/queried_object_id, set earlier in parse_query() -- pre_get_posts alone isn't enough); category_hierarchy() only prepends page-writing when !is_f28().
docs/06-fallbacks.md: F28 row added.
Tests: unit FormTest 1 new; integration SaveHooksTest 3 new, StatsTest 1 new, HierarchyTest 4 new + 1 renamed (fiction now required for the page-writing-prepend case), ArchiveTemplatesTest 1 new, HubWritingTemplatesTest's existing page-writing assertion updated to seed a fiction series first (F28 changed its default outcome).
Verified: composer lint 0 errors, composer test:unit 172; targeted filter (46 tests) then full npm run test:integration (516 tests) green; npm run lint green (0 fixme); npm run test:e2e (356 passed, 0 skipped, seed unchanged since seed always has fiction); forbidden-patterns clean; foundry_verify ok:true (extraVerify env:live/test:live also ran since Seeder.php touched, both skip cleanly as expected).

### P1-05 — b102231
Added test_rule_50_no_context_with_other_content() to all 19 block test classes. Classification: strict (assert '', already correct code, or one small fix): archive-by-year (render.php now returns '' when $content is empty rather than an empty wrapper div -- the only render.php change), category-stats, most-read, series-bar, series-prev-next, series-progress, series-toc (default series variant), syndicated-to, tag-filter. Sanctioned per SPEC (assert normal output, reading Serials::active()/Books::all()): serial-hero, story-tiles, book-grid, series-toc chapters variant (covered by P1-02's existing fallback test too). Extended-sanctioned, my interpretation (assert normal output, no post/term context to read at all by design): lead-story (Query\Lead::compute()), verse-of-the-day (stored option), newsletter-form (settings singleton), series-featured (documented 03 §3 auto-pick), series-list default (SeriesIndex::all()), series-stats (SeriesIndex::all()), writing-cell (Serials::active() fallback, same shape as serial-hero/writing shelf).
Verified: composer lint 0 errors; targeted filter (159 tests, all new) green; full npm run test:integration (535 tests, was 516) green; npm run lint green; npm run test:e2e (356 passed, 0 skipped/failed, nothing visibly changed); forbidden-patterns clean.

### P1-06 — e1832b9
Reseeded, rebuilt, ran npm run screenshots against the phase-4 zones; visually confirmed article-noseries.png (no series bar/TOC, chronological "Previously in Technology"/"Next" prev-next), series-single.png ("Other series": Reading CVEs first, no fiction series, count 3), and footer.png (single line, no copyright, no RSS, 8 nav items) match the P1 defect fixes.
foundry_verify green (composer lint/test:unit, npm run lint/test:unit/build, forbidden-patterns, all constraints ok:true).
Pushed to origin refine/2026-09-23 (fee247f..e1832b9).
Manual check: NOT VERIFIED (human) -- open /transients-object-caches-and-fast-enough/, /series/hardening-wordpress/ and / on the seeded site; compare with mock 2b, 1f, 2a line 327.

### P2-01 — b9240d4
Seeder.php: run_starter() (categories+pages+navigation only, no posts/series/books/verse/newsletter); may_wipe(string $environment_type): bool pure gate; reset() rewritten -- now guarded by may_wipe(), wipes ALL posts/pages/attachments (batched by cli.batch, attachment loop uses an explicit status list since 'any' excludes 'inherit') and every category/post_tag/series term (wp_delete_term() itself refuses the default category), not just _ttm_seed-tagged rows.
SeedCommand.php: --starter-only routes to run_starter(); allowed() now delegates to Seeder::may_wipe().
StatsCommand.php (new) + Loader.php: wp ttm stats:flush -> Stats::flush_all(), message "Flushed N stats transient(s)".
Stats::flush_all() now returns int (count of transients deleted), was void.
Tests: unit SeederTest 1 new (may_wipe); integration SeedStatesTest 4 new (starter-only x2, reset-foreign-content, reset-seed-only); MaintenanceCommandsTest 1 new (stats:flush); Cli/SeederTest's old test_reset_removes_only_seeded_content renamed/inverted to test_reset_removes_every_post_not_only_seeded_content to match reset()'s new, intentionally broader contract.
Verified: composer lint 0 errors; targeted filter (21 tests) then full npm run test:integration (540 tests) green; npm run lint green; npm run test:e2e (356 passed); forbidden-patterns clean (had to reword a comment that accidentally matched the rule-12 unbounded-query regex); manually ran wp ttm seed --starter-only then wp ttm seed --reset, confirmed the site returns to the full seed (8 categories/4 pages/107 posts/7 series); foundry_verify ok:true including the env:live/test:live extraVerify triggered by Seeder.php (both skip cleanly, no export present).
