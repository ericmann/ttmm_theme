# These Things Matter — phase 4 (real content) build progress
Branch: refine/2026-09-23
Started: 2026-09-23T05:03:31.529Z

## Tasks
- [x] P0-01 Rule 47 check, tagged-fixme guard, live-script entry points, phase-4 screenshot set
- [ ] P0-02 Seed: tags for every section and the older Technology neighbour
- [ ] P0-03 Seed: Reading CVEs series
- [ ] P0-04 New screens and §6.11 rows as tagged fixme
- [ ] P0-05 Stats invalidation, tiebreak, flush_all; Business filter row green
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
